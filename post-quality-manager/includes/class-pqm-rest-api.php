<?php
/**
 * REST API endpoints for the Post Quality Manager plugin.
 *
 * Endpoints:
 *  GET  /post-quality/v1/post-score      — expose post data + Yoast scores
 *  POST /post-quality/v1/save-review      — n8n posts review results here
 *  GET  /post-quality/v1/reviews/{id}     — fetch stored reviews for a post
 *  POST /post-quality/v1/trigger-review   — WordPress triggers n8n review
 *  GET  /post-quality/v1/workflow         — site-configured workflow for n8n "Import from URL"
 *  GET  /post-quality/v1/posts            — read-only post list with latest scores (Post Reviews app)
 *  GET  /post-quality/v1/dashboard        — average score, 8-week trend, nudge flags (Dashboard app)
 *  POST /post-quality/v1/insert-block     — write the latest Takeaways / Sources into the post (story 23)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_REST_API {

    /* ─── Route Registration ─────────────────────────────────────────── */

    public static function register_routes(): void {

        // GET post data + Yoast scores.
        register_rest_route( PQM_REST_NAMESPACE, '/post-score', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'post_score_callback' ],
            'permission_callback' => [ __CLASS__, 'check_view_reviews' ],
            'args'                => self::post_score_args(),
        ] );

        // n8n POSTs review results.
        register_rest_route( PQM_REST_NAMESPACE, '/save-review', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'save_review_callback' ],
            'permission_callback' => [ __CLASS__, 'check_webhook_token' ],
            'args'                => self::save_review_args(),
        ] );

        // Fetch stored reviews for a post.
        register_rest_route( PQM_REST_NAMESPACE, '/reviews/(?P<post_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_reviews_callback' ],
            'permission_callback' => [ __CLASS__, 'check_view_reviews' ],
        ] );

        // Trigger a review via n8n.
        register_rest_route( PQM_REST_NAMESPACE, '/trigger-review', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'trigger_review_callback' ],
            'permission_callback' => [ __CLASS__, 'check_can_trigger' ],
            'args'                => self::trigger_review_args(),
        ] );

        // Serve the site-configured workflow for n8n "Import from URL".
        register_rest_route( PQM_REST_NAMESPACE, '/workflow', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'workflow_callback' ],
            'permission_callback' => [ __CLASS__, 'check_workflow_token' ],
            'args'                => [
                'token' => [
                    'required'          => false,
                    'type'              => 'string',
                    'description'       => 'Webhook token authorizing the export.',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        // New (story 5.1): read-only post list for the React Post Reviews app.
        register_rest_route( PQM_REST_NAMESPACE, '/posts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'list_posts_callback' ],
            'permission_callback' => [ __CLASS__, 'check_view_reviews' ],
            'args'                => self::list_posts_args(),
        ] );

        // Story 5.6: Dashboard read model — average, trend, nudge flags.
        register_rest_route( PQM_REST_NAMESPACE, '/dashboard', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'dashboard_callback' ],
            'permission_callback' => [ __CLASS__, 'check_view_reviews' ],
        ] );

        // Story 23: Insert into Post — plugin-side, no n8n call, no quota.
        register_rest_route( PQM_REST_NAMESPACE, '/insert-block', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'insert_block_callback' ],
            'permission_callback' => [ __CLASS__, 'check_can_insert' ],
            'args'                => [
                'post_id' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
                'command' => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => array_keys( PQM_Blocks::ITEM_KEYS ),
                ],
            ],
        ] );
    }

    /* ─── Permission Callbacks ───────────────────────────────────────── */

    /**
     * Read access to scores and stored reviews (Dashboard / Post Reviews).
     */
    public static function check_view_reviews(): bool {
        return current_user_can( 'pqm_view_reviews' );
    }

    /**
     * May the current user trigger any agent at all? Holding any registry
     * capability gets past the route; the per-command decision (tier and
     * exact capability) is PQM_Features::can() inside the callback of
     * POST /trigger-review.
     */
    public static function check_can_trigger(): bool {
        $caps = array_unique( array_column( PQM_Features::registry(), 'capability' ) );

        foreach ( $caps as $cap ) {
            if ( current_user_can( $cap ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Insert into Post edits the post, so the user must be able to edit
     * that post, then run its agent (Takeaways free, Sources Pro) — a
     * refusal carries the same copy as POST /trigger-review.
     */
    public static function check_can_insert( WP_REST_Request $request ): bool|WP_Error {
        if ( ! current_user_can( 'edit_post', (int) $request->get_param( 'post_id' ) ) ) {
            return false;
        }

        $command = (string) $request->get_param( 'command' );
        $refusal = PQM_Features::why_not( $command );

        return null === $refusal ? true : self::gate_error( $command, $refusal );
    }

    /**
     * The feature gate's refusal (CAP-1 / CAP-3): role copy for 'role',
     * the upgrade copy for 'plan'; 403 either way.
     */
    private static function gate_error( string $command, string $refusal ): WP_Error {
        $label = 'seo' === $command ? 'SEO' : ucfirst( $command );

        return new WP_Error(
            'pqm_feature_gated',
            'role' === $refusal
                ? sprintf(
                    /* translators: %s: agent command label, e.g. "Rewrite" */
                    __( '%s is not available for your role. Ask an administrator to extend it.', 'post-quality-manager' ),
                    $label
                )
                : sprintf(
                    /* translators: %s: agent command label, e.g. "Rewrite" */
                    __( '%s is a Pro agent. Upgrade to unlock it — Review and Takeaways stay free.', 'post-quality-manager' ),
                    $label
                ),
            [ 'status' => 403 ]
        );
    }

    /**
     * Token-based auth for the save-review webhook.
     * Accepts either the X-PQM-Token header or standard WP auth.
     */
    public static function check_webhook_token( WP_REST_Request $request ): bool {
        // Allow standard WP auth (Application Passwords).
        if ( current_user_can( 'pqm_run_free_agents' ) ) {
            return true;
        }

        $settings       = pqm_get_settings();
        $expected_token  = $settings['webhook_token'];
        $provided_token  = $request->get_header( 'X-PQM-Token' ) ?? '';

        if ( empty( $expected_token ) || empty( $provided_token ) ) {
            return false;
        }

        return hash_equals( $expected_token, $provided_token );
    }

    /**
     * Token-based auth for the workflow export. n8n's "Import from URL"
     * fetches without WP cookies, so the shared secret travels as a query
     * arg instead of a header. Logged-in admins pass too.
     */
    public static function check_workflow_token( WP_REST_Request $request ): bool {
        if ( current_user_can( 'pqm_manage_settings' ) ) {
            return true;
        }

        $expected = pqm_get_settings()['webhook_token'];
        $provided = (string) $request->get_param( 'token' );

        if ( empty( $expected ) || empty( $provided ) ) {
            return false;
        }

        return hash_equals( $expected, $provided );
    }

    /* ─── 1. GET /post-score ────────────────────────────────────────── */

    private static function post_score_args(): array {
        return [
            'slug' => [
                'required'          => true,
                'type'              => 'string',
                'description'       => 'The post slug.',
                'sanitize_callback' => 'sanitize_title',
                'validate_callback' => fn( $v ) => ! empty( $v ),
            ],
            'post_type' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => 'post',
                'description'       => 'Post type to query.',
                'sanitize_callback' => 'sanitize_key',
                'validate_callback' => fn( $v ) => post_type_exists( $v ) && is_post_type_viewable( $v ),
            ],
        ];
    }

    public static function post_score_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $slug      = $request->get_param( 'slug' );
        $post_type = $request->get_param( 'post_type' );

        $query = new WP_Query( [
            'name'           => $slug,
            'post_type'      => $post_type,
            'post_status'    => [ 'publish', 'pending' ],
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );

        if ( ! $query->have_posts() ) {
            return new WP_Error(
                'pqm_not_found',
                sprintf( 'No published or pending %s found with slug: %s', $post_type, $slug ),
                [ 'status' => 404 ]
            );
        }

        $queried_post = $query->posts[0];
        $id           = $queried_post->ID;

        if ( ! current_user_can( 'read_post', $id ) ) {
            return new WP_Error(
                'pqm_forbidden',
                'You do not have permission to read this post.',
                [ 'status' => rest_authorization_required_code() ]
            );
        }

        // Setup global post data for shortcodes.
        global $post;
        $original_post = $post;
        $post          = $queried_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        setup_postdata( $post );

        $content_html  = get_post_field( 'post_content', $id );
        $content_plain = wp_strip_all_tags( apply_filters( 'the_content', $content_html ) );

        $excerpt = has_excerpt( $id )
            ? wp_strip_all_tags( get_the_excerpt( $id ) )
            : wp_trim_words( $content_plain, 55, '...' );

        // Restore.
        $post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
        if ( $post ) {
            setup_postdata( $post );
        } else {
            wp_reset_postdata();
        }

        $yoast_active = defined( 'WPSEO_VERSION' );

        $data = [
            'id'                => $id,
            'slug'              => $queried_post->post_name,
            'post_type'         => $queried_post->post_type,
            'title'             => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
            'link'              => get_permalink( $id ),
            'excerpt'           => $excerpt,
            'content_html'      => $content_html,
            'content'           => $content_plain,
            'yoast_active'      => $yoast_active,
            'seo_score'         => $yoast_active ? (int) get_post_meta( $id, '_yoast_wpseo_linkdex', true ) : null,
            'readability_score' => $yoast_active ? (int) get_post_meta( $id, '_yoast_wpseo_content_score', true ) : null,
            'focus_keyphrase'   => $yoast_active ? (string) get_post_meta( $id, '_yoast_wpseo_focuskw', true ) : null,
            'meta_description'  => $yoast_active ? (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ) : null,
            'seo_title'         => $yoast_active ? (string) get_post_meta( $id, '_yoast_wpseo_title', true ) : null,
        ];

        return new WP_REST_Response( $data, 200 );
    }

    /* ─── 2. POST /save-review (n8n callback) ────────────────────────── */

    private static function save_review_args(): array {
        return [
            'post_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'command' => [
                'required' => true,
                'type'     => 'string',
                'enum'     => [ 'score', 'review', 'takeaways', 'sources', 'seo', 'audit', 'rewrite', 'apply' ],
            ],
            'overall_score' => [
                'required' => false,
                'type'     => 'integer',
                'default'  => 0,
            ],
            'status' => [
                'required' => false,
                'type'     => 'string',
                'default'  => '',
            ],
            'error_message' => [
                'required' => false,
                'type'     => 'string',
            ],
            'result' => [
                'required' => false,
            ],
            'workflow_version' => [
                'required'    => false,
                'type'        => 'integer',
                'default'     => 0,
                'description' => 'Template version echoed by the n8n workflow, used to flag outdated imports.',
            ],
        ];
    }

    public static function save_review_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param( 'post_id' );

        // Verify the post exists.
        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_Error( 'pqm_invalid_post', 'Post not found.', [ 'status' => 404 ] );
        }

        $command = sanitize_key( $request->get_param( 'command' ) );
        $score   = absint( $request->get_param( 'overall_score' ) );
        $status  = sanitize_text_field( $request->get_param( 'status' ) );
        $error   = sanitize_text_field( $request->get_param( 'error_message' ) );
        $result  = $request->get_param( 'result' );

        // If it's an error, save the error message in the result field.
        if ( $status === 'error' || ! empty( $error ) ) {
            $status = $status ?: 'error';
            $result = [ 'error' => $error ?: 'An unknown error occurred in n8n.' ];
        }

        // Remember which template version the running workflow was imported
        // from — the n8n Workflow page compares it against PQM_WORKFLOW_VERSION.
        $wf_version = absint( $request->get_param( 'workflow_version' ) );
        if ( $wf_version > 0 && $wf_version !== (int) get_option( 'pqm_workflow_version_seen', 0 ) ) {
            update_option( 'pqm_workflow_version_seen', $wf_version, false );
        }

        $row_id = PQM_DB::save_review( $post_id, $command, $score, $status, $result );

        if ( ! $row_id ) {
            return new WP_Error( 'pqm_db_error', 'Failed to save review.', [ 'status' => 500 ] );
        }

        return new WP_REST_Response( [
            'success' => true,
            'id'      => $row_id,
            'message' => sprintf( 'Review saved for post %d (%s).', $post_id, $command ),
        ], 201 );
    }

    /* ─── 3. GET /reviews/{post_id} ──────────────────────────────────── */

    /** Rows the detail panel's History tab can show per post (story 5.4). */
    private const REVIEWS_LIMIT = 50;

    /**
     * Stored `reviewed_at` values are GMT (`current_time( 'mysql', true )`),
     * so display conversion goes through get_date_from_gmt() — the site's
     * timezone and date/time format. Blank / zero dates render as ''.
     */
    private static function display_date( string $gmt, bool $with_time = true ): string {
        if ( '' === $gmt || '0000-00-00 00:00:00' === $gmt ) {
            return '';
        }

        $format = get_option( 'date_format' ) . ( $with_time ? ' ' . get_option( 'time_format' ) : '' );

        return get_date_from_gmt( $gmt, $format );
    }

    /**
     * Stored reviews for one post, newest first. Story 5.4 keeps the
     * array shape and adds per row an int `overall_score` and a
     * `reviewed_at_display` in the site's date/time format — the same
     * conversion `/posts` uses, so the panel and the table agree.
     */
    public static function get_reviews_callback( WP_REST_Request $request ): WP_REST_Response {
        $post_id = (int) $request->get_param( 'post_id' );
        $reviews = PQM_DB::get_reviews_for_post( $post_id, self::REVIEWS_LIMIT );

        foreach ( $reviews as &$review ) {
            // Decode result_data JSON; on failure it stays the raw string as saved by n8n.
            $decoded = json_decode( $review['result_data'], true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $review['result_data'] = $decoded;
            }

            $review['overall_score']       = (int) ( $review['overall_score'] ?? 0 );
            $review['reviewed_at_display'] = self::display_date( (string) ( $review['reviewed_at'] ?? '' ) );
        }
        unset( $review );

        return new WP_REST_Response( $reviews, 200 );
    }

    /* ─── 4. POST /trigger-review ────────────────────────────────────── */

    private static function trigger_review_args(): array {
        return [
            'post_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'command' => [
                'required' => false,
                'type'     => 'string',
                'default'  => 'review',
                'enum'     => [ 'score', 'review', 'takeaways', 'sources', 'seo', 'audit', 'rewrite', 'apply' ],
            ],
        ];
    }

    public static function trigger_review_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings = pqm_get_settings();

        if ( empty( $settings['n8n_webhook_url'] ) ) {
            return new WP_Error(
                'pqm_not_configured',
                'n8n webhook URL is not configured. Go to Content Quality → Settings.',
                [ 'status' => 400 ]
            );
        }

        $post_id = (int) $request->get_param( 'post_id' );
        $command = sanitize_key( $request->get_param( 'command' ) );

        // Feature gate (CAP-1 / CAP-3): one check on POST /trigger-review —
        // gated commands are refused here, before any webhook.
        // A role refusal gets role copy, a plan refusal the upgrade copy.
        $refusal = PQM_Features::why_not( $command );
        if ( $refusal !== null ) {
            return self::gate_error( $command, $refusal );
        }

        // Author quota (CAP-4): checked after the gate — role/plan refusals
        // keep priority — and before any webhook call. Cron posts to n8n
        // directly and never enters this callback, so it stays quota-free.
        $quota = PQM_Features::quota_state();
        if ( $quota !== null && $quota['remaining'] < 1 ) {
            return new WP_Error(
                'pqm_quota_exceeded',
                sprintf(
                    /* translators: 1: monthly quota limit, 2: reset date, e.g. "November 1, 2026" */
                    __( 'Monthly limit reached (%1$d of %1$d AI actions used). Your quota resets on %2$s — or ask an administrator to raise it.', 'post-quality-manager' ),
                    $quota['limit'],
                    $quota['resets']
                ),
                [ 'status' => 429 ]
            );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_status, [ 'publish', 'pending' ], true ) ) {
            return new WP_Error(
                'pqm_invalid_post',
                'Post not found or not published/pending.',
                [ 'status' => 404 ]
            );
        }

        // Build payload for n8n (shared with the cron batch).
        $payload = pqm_n8n_payload( $post, $command, $settings );

        // Fire-and-forget request to n8n.
        $response = wp_remote_post( $settings['n8n_webhook_url'], [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 5,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'pqm_n8n_error',
                'Failed to reach n8n: ' . $response->get_error_message(),
                [ 'status' => 502 ]
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        // Quota is spent only on success: n8n answered 2xx, so API money
        // is being spent. A failed webhook never costs the user an action.
        if ( $status_code >= 200 && $status_code < 300 ) {
            PQM_Features::consume_quota();
            $quota = PQM_Features::quota_state();

            // The `processing` placeholder is written once, here, on the
            // POST /trigger-review route: the Post Reviews polling
            // reads it back as `in_flight` and the n8n callback's result
            // row supersedes it. A refused or failed trigger writes nothing.
            PQM_DB::save_review(
                $post_id,
                $command,
                0,
                'processing',
                [ 'summary' => __( 'Running asynchronously in n8n...', 'post-quality-manager' ) ]
            );
        }

        return new WP_REST_Response( [
            'success'         => $status_code >= 200 && $status_code < 300,
            'message'         => sprintf( 'Review triggered for "%s" (command: %s).', $post->post_title, $command ),
            'n8n_status'      => $status_code,
            // Post-trigger remaining count; null for exempt/unlimited users.
            'quota_remaining' => $quota !== null ? $quota['remaining'] : null,
        ], 200 );
    }

    /* ─── 5. GET /workflow (n8n "Import from URL") ───────────────────── */

    public static function workflow_callback() {
        $json = pqm_workflow_json();

        if ( $json === null ) {
            return new WP_Error(
                'pqm_template_missing',
                __( 'Workflow template not found. Please reinstall the plugin.', 'post-quality-manager' ),
                [ 'status' => 500 ]
            );
        }

        $response = new WP_REST_Response( json_decode( $json ) );
        // Older n8n versions fetch import URLs from the browser, not the server.
        $response->header( 'Access-Control-Allow-Origin', '*' );

        return $response;
    }

    /* ─── 6. GET /posts (read-only list for the Post Reviews app) ────────── */

    private static function list_posts_args(): array {
        return [
            'per_page' => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 20,
                'sanitize_callback' => 'absint',
            ],
            'page' => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'orderby' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => 'reviewed_at',
                'sanitize_callback' => 'sanitize_key',
            ],
            'order' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => 'desc',
                'sanitize_callback' => 'sanitize_key',
            ],
            'filter' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => 'all',
                'sanitize_callback' => 'sanitize_key',
            ],
            'status' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_key',
            ],
            // No sanitize_callback on the score args: WP passes three arguments and
            // PHP internals (intval…) fatal on that — see score_bound().
            'min_score' => [
                'required'          => false,
                'type'              => 'integer',
            ],
            'max_score' => [
                'required'          => false,
                'type'              => 'integer',
            ],
        ];
    }

    /** Stored review statuses plus the "never reviewed" sentinel accepted by `status=`. */
    private const LIST_STATUSES = [ 'ship', 'needs_revision', 'major_revision', PQM_DB::STATUS_NONE ];

    /**
     * The score args carry no sanitize_callback on purpose: WP's schema
     * sanitizing casts them to int, and WP calls a sanitize_callback with
     * three arguments — PHP internals such as intval() fatal on that.
     *
     * Clamp a score-band bound to 0–100; absent (null / empty) stays null,
     * which the DB layer reads as unbounded.
     */
    private static function score_bound( $value ): ?int {
        if ( null === $value || '' === $value ) {
            return null;
        }

        return max( 0, min( 100, (int) $value ) );
    }

    /**
     * One Yoast score meta of a post (story 5.10). Yoast stores '' until
     * it has analyzed the post; that reads as null, not 0.
     */
    private static function yoast_score( int $post_id, string $key ): ?int {
        $value = get_post_meta( $post_id, $key, true );

        return '' === $value ? null : (int) $value;
    }

    /**
     * Read-only list of posts with their latest review, for the React
     * Post Reviews table. Wraps PQM_DB::get_all_posts_with_scores(); bad
     * params are clamped to the DB method's accepted set, never an error.
     *
     * The additive `viewer` block (story 5.2) reports the current user's
     * trigger eligibility, quota snapshot and gated/hidden command
     * partition — read-only reporting of PQM_Features state, no
     * semantics change.
     *
     * Story 5.3 adds server-side filters (`status` incl. `none`,
     * `min_score`/`max_score`), `orderby=status` and the filter-independent
     * `counts` block the status chips display. Story 5.4 adds the per-item
     * `in_flight` command list the detail panel's polling watches. Story
     * 5.6 adds `orderby=priority` (the Dashboard Fix-next queue, fixed
     * direction — see PQM_DB::priority_order_sql()).
     *
     * Story 5.10 appends the item fields `wp_post_status` (the "— Pending"
     * marker) and `yoast` ({seo, readability}, each int or null when Yoast
     * has no score; null while Yoast is inactive) plus the top-level
     * `yoast_active` flag that shows the table's Yoast SEO column.
     */
    public static function list_posts_callback( WP_REST_Request $request ): WP_REST_Response {
        $per_page = absint( $request->get_param( 'per_page' ) );
        $per_page = $per_page > 0 ? min( $per_page, 100 ) : 20;

        $page = max( 1, absint( $request->get_param( 'page' ) ) );

        $orderby = sanitize_key( (string) $request->get_param( 'orderby' ) );
        if ( ! in_array( $orderby, [ 'score', 'reviewed_at', 'title', 'status', 'priority' ], true ) ) {
            $orderby = 'reviewed_at';
        }

        $order  = strtoupper( (string) $request->get_param( 'order' ) ) === 'ASC' ? 'ASC' : 'DESC';
        $filter = 'needs_review' === $request->get_param( 'filter' ) ? 'needs_review' : 'all';

        $status = sanitize_key( (string) $request->get_param( 'status' ) );
        if ( ! in_array( $status, self::LIST_STATUSES, true ) ) {
            $status = '';
        }

        $result = PQM_DB::get_all_posts_with_scores( [
            'per_page'  => $per_page,
            'page'      => $page,
            'orderby'   => $orderby,
            'order'     => $order,
            'status'    => $status,
            'min_score' => self::score_bound( $request->get_param( 'min_score' ) ),
            'max_score' => self::score_bound( $request->get_param( 'max_score' ) ),
            'filter'    => $filter,
        ] );

        $post_ids  = array_map( 'intval', array_column( $result['items'], 'post_id' ) );
        $commands  = PQM_DB::get_commands_for_posts( $post_ids );
        $in_flight = PQM_DB::get_in_flight_for_posts( $post_ids );

        $yoast_active = defined( 'WPSEO_VERSION' );
        if ( $yoast_active && ! empty( $post_ids ) ) {
            update_postmeta_cache( $post_ids ); // one query for every row's Yoast metas
        }

        $items = [];
        foreach ( $result['items'] as $row ) {
            $post_id  = (int) $row['post_id'];
            $reviewed = (string) ( $row['latest_reviewed'] ?? '' );

            $items[] = [
                'post_id'                 => $post_id,
                'title'                   => (string) $row['title'],
                'edit_link'               => get_edit_post_link( $post_id, 'raw' ),
                'latest_score'            => null !== $row['latest_score'] ? (int) $row['latest_score'] : null,
                'latest_status'           => (string) ( $row['latest_status'] ?? '' ),
                'latest_reviewed'         => $reviewed,
                'latest_reviewed_display' => self::display_date( $reviewed ),
                'commands'                => $commands[ $post_id ] ?? [],
                // Commands whose latest row is still `processing` (story
                // 5.4): the polling and auto-resume signal, server-derived.
                'in_flight'               => $in_flight[ $post_id ] ?? [],
                'wp_post_status'          => (string) ( $row['wp_post_status'] ?? '' ),
                'yoast'                   => $yoast_active ? [
                    'seo'         => self::yoast_score( $post_id, '_yoast_wpseo_linkdex' ),
                    'readability' => self::yoast_score( $post_id, '_yoast_wpseo_content_score' ),
                ] : null,
            ];
        }

        $can_trigger = self::check_can_trigger();

        return new WP_REST_Response( [
            'items'  => $items,
            'total'  => (int) $result['total'],
            'pages'  => (int) $result['pages'],
            // Facet counts over all listable posts, independent of the active
            // filters, so a chip's number equals a Dashboard tile's.
            'counts' => PQM_DB::count_posts_by_status(),
            'viewer' => [
                'can_trigger'     => $can_trigger,
                // Quota is meaningless for a user who cannot trigger at
                // all; exempt users and limit 0 (unlimited) are already
                // null from quota_state().
                'quota'           => $can_trigger ? PQM_Features::quota_state() : null,
                'gated_commands'  => PQM_Features::gated_commands(),
                'hidden_commands' => PQM_Features::hidden_commands(),
            ],
            'yoast_active' => $yoast_active,
        ], 200 );
    }

    /* ─── 7. POST /insert-block (Insert into Post, story 23) ─────────── */

    /**
     * Write the latest Takeaways / Sources items into the post
     * (PQM_Blocks::insert). Gate and edit rights are the permission
     * callback's; 409 when there is nothing to insert yet.
     */
    public static function insert_block_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $command = (string) $request->get_param( 'command' );
        $result  = PQM_Blocks::insert( (int) $request->get_param( 'post_id' ), $command );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( [
            'success' => true,
            'message' => sprintf(
                /* translators: %s: agent command label, e.g. "Takeaways" */
                __( '%s inserted into the post. The previous version is in Revisions.', 'post-quality-manager' ),
                PQM_Blocks::label( $command )
            ),
        ], 200 );
    }

    /* ─── 8. GET /dashboard (Dashboard app, story 5.6) ───────────────── */

    /**
     * The Dashboard's own numbers — everything else (queue, tile counts,
     * viewer) comes from `GET /posts?orderby=priority`. The average is the
     * trend's last point, so the tile and the sparkline's endpoint agree
     * by construction. Nudge flags keep the former PHP Dashboard's rules
     * (removed in story 5.7); the dismiss link itself is still handled by
     * the PHP shell.
     */
    public static function dashboard_callback(): WP_REST_Response {
        $settings  = pqm_get_settings();
        $trend     = PQM_DB::get_score_trend();
        $average   = $trend ? $trend[ count( $trend ) - 1 ]['average'] : null;
        $can_admin = current_user_can( 'pqm_manage_settings' );

        // Habit-loop nudge: first result is in, cron still off. Pro only —
        // on a free site the Automated Reviews section is locked.
        $cron_nudge = null !== $average
            && empty( $settings['cron_enabled'] )
            && ! get_option( 'pqm_cron_nudge_dismissed' )
            && $can_admin
            && PQM_Features::is_pro();

        $data = [
            'average_score'       => $average,
            'trend'               => array_map(
                static fn( $point ) => [
                    'label'   => self::display_date( $point['end'], false ),
                    'average' => $point['average'],
                ],
                $trend
            ),
            'connected'           => ! empty( $settings['n8n_webhook_url'] ),
            'can_manage_settings' => $can_admin,
            'cron_nudge'          => $cron_nudge,
        ];

        if ( $cron_nudge ) {
            // Raw URL (no &amp;): it travels as JSON into an href.
            $data['cron_nudge_dismiss_url'] = add_query_arg(
                [
                    'pqm_dismiss_cron_nudge' => '1',
                    '_wpnonce'               => wp_create_nonce( 'pqm_dismiss_cron_nudge' ),
                ],
                admin_url( 'admin.php?page=pqm-dashboard' )
            );
        }

        return new WP_REST_Response( $data, 200 );
    }
}
