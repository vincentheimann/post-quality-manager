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
 *  GET  /post-quality/v1/setup            — setup dialog state: progress, values to copy (story 36)
 *  POST /post-quality/v1/setup            — tick a setup step, or mark the setup complete (story 36)
 *  POST /post-quality/v1/setup/connect    — save the webhook URL and ping it (story 36)
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

        // Story 36: the setup dialog's state, progress and connect step.
        register_rest_route( PQM_REST_NAMESPACE, '/setup', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_setup_callback' ],
                'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'update_setup_callback' ],
                'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
                'args'                => [
                    'step'     => [ 'required' => false, 'type' => 'string' ],
                    'done'     => [ 'required' => false, 'type' => 'boolean' ],
                    'complete' => [ 'required' => false, 'type' => 'boolean' ],
                ],
            ],
        ] );

        register_rest_route( PQM_REST_NAMESPACE, '/setup/connect', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'setup_connect_callback' ],
            'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
            'args'                => [
                'webhook_url' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // Story 55: the wizard's configuration steps (profile, standards, schedule).
        register_rest_route( PQM_REST_NAMESPACE, '/setup/settings', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'setup_settings_callback' ],
            'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
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
     * The setup dialog's routes (story 36): the n8n connection is the
     * Admin's, like the n8n Workflow page that hosts the dialog.
     */
    public static function check_manage_settings(): bool {
        return current_user_can( 'pqm_manage_settings' );
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
        $label = PQM_Features::label( $command );

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
                sprintf(
                    /* translators: 1: post type, e.g. "post", 2: post slug */
                    __( 'No published or pending %1$s found with the slug "%2$s". Check the slug and post type.', 'post-quality-manager' ),
                    $post_type,
                    $slug
                ),
                [ 'status' => 404 ]
            );
        }

        $queried_post = $query->posts[0];
        $id           = $queried_post->ID;

        if ( ! current_user_can( 'read_post', $id ) ) {
            return new WP_Error(
                'pqm_forbidden',
                __( 'You do not have permission to read this post.', 'post-quality-manager' ),
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
                'enum'     => array_keys( PQM_Features::registry() ), // the one command whitelist
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
        // 0 (a v1 import sends none) leaves the last report as it is.
        $wf_version = absint( $request->get_param( 'workflow_version' ) );
        if ( $wf_version > 0 ) {
            pqm_record_workflow_version( $wf_version, 'command' );
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
                'enum'     => array_keys( PQM_Features::registry() ), // the one command whitelist
            ],
        ];
    }

    public static function trigger_review_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $settings = pqm_get_settings();

        // Every failed-start message names a step the clicker can take:
        // only pqm_manage_settings holders can fix the n8n connection.
        $can_manage = current_user_can( 'pqm_manage_settings' );

        // Not connected: admins are sent to the n8n Workflow page.
        if ( empty( $settings['n8n_webhook_url'] ) ) {
            return new WP_Error(
                'pqm_not_configured',
                $can_manage
                    ? __( 'n8n is not connected yet. Set up the workflow on the Content Quality → n8n Workflow page, then try again.', 'post-quality-manager' )
                    : __( 'n8n is not connected yet. Ask an administrator to connect the n8n workflow, then try again.', 'post-quality-manager' ),
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
                    /* translators: 1: AI actions used this month, 2: monthly quota limit, 3: reset date, e.g. "November 1, 2026" */
                    __( 'Monthly limit reached (%1$d of %2$d AI actions used). Your quota resets on %3$s — or ask an administrator to raise it.', 'post-quality-manager' ),
                    $quota['used'],
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
                __( 'Post not found. Only published or pending posts can be run.', 'post-quality-manager' ),
                [ 'status' => 404 ]
            );
        }

        // Build payload for n8n (shared with the cron batch).
        $payload = pqm_n8n_payload( $post, $command, $settings );

        // Fire-and-forget request to n8n, authenticated with the token header.
        $response = wp_remote_post( $settings['n8n_webhook_url'], [
            'headers' => pqm_n8n_headers( $settings ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 5,
        ] );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'pqm_n8n_error',
                sprintf(
                    $can_manage
                        /* translators: %s: the HTTP error message, e.g. "cURL error 7" */
                        ? __( 'Could not connect to n8n (%s). Check your Webhook URL in Settings.', 'post-quality-manager' )
                        /* translators: %s: the HTTP error message, e.g. "cURL error 7" */
                        : __( 'Could not connect to n8n (%s). Ask an administrator to check the n8n connection, then try again.', 'post-quality-manager' ),
                    $response->get_error_message()
                ),
                [ 'status' => 502 ]
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $accepted    = $status_code >= 200 && $status_code < 300;

        // Quota is spent only on success: n8n answered 2xx, so API money
        // is being spent. A failed webhook never costs the user an action.
        if ( $accepted ) {
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
            'success'         => $accepted,
            'message'         => $accepted
                ? sprintf(
                    /* translators: 1: agent command label, e.g. "Review", 2: post title */
                    __( '%1$s started for "%2$s".', 'post-quality-manager' ),
                    PQM_Features::label( $command ),
                    $post->post_title
                )
                : sprintf(
                    $can_manage
                        /* translators: 1: agent command label, e.g. "Review"; 2: HTTP status code n8n answered with */
                        ? __( '%1$s could not start — n8n answered HTTP %2$d. Check that the workflow is active in n8n, then try again.', 'post-quality-manager' )
                        /* translators: 1: agent command label, e.g. "Review"; 2: HTTP status code n8n answered with */
                        : __( '%1$s could not start — n8n answered HTTP %2$d. Ask an administrator to check the workflow in n8n, then try again.', 'post-quality-manager' ),
                    PQM_Features::label( $command ),
                    $status_code
                ),
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

        // n8n is importing it: the last report no longer says what runs
        // there, so the n8n Workflow page shows "not checked yet" until a ping.
        delete_option( 'pqm_workflow_version_seen' );

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
     *
     * Ticket 32 appends `connected` and `can_manage_settings` to `viewer`
     * (the GET /dashboard flags): Post Reviews' not-connected notice.
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
        $settings    = pqm_get_settings();

        return new WP_REST_Response( [
            'items'  => $items,
            'total'  => (int) $result['total'],
            'pages'  => (int) $result['pages'],
            // Facet counts over all listable posts, independent of the active
            // filters, so a chip's number equals a Dashboard tile's.
            'counts' => PQM_DB::count_posts_by_status(),
            'viewer' => [
                'can_trigger'         => $can_trigger,
                // Quota is meaningless for a user who cannot trigger at
                // all; exempt users and limit 0 (unlimited) are already
                // null from quota_state().
                'quota'               => $can_trigger ? PQM_Features::quota_state() : null,
                'gated_commands'      => PQM_Features::gated_commands(),
                'hidden_commands'     => PQM_Features::hidden_commands(),
                // Ticket 32: the not-connected notice and disabled Run
                // buttons — the same two flags as GET /dashboard.
                'connected'           => ! empty( $settings['n8n_webhook_url'] ),
                'can_manage_settings' => current_user_can( 'pqm_manage_settings' ),
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
                PQM_Features::label( $command )
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
        // on a free site the Automated Reviews section is locked. Shown to
        // whoever can see that section (pqm_manage_agents, ticket 16).
        $cron_nudge = null !== $average
            && empty( $settings['cron_enabled'] )
            && ! get_option( 'pqm_cron_nudge_dismissed' )
            && current_user_can( 'pqm_manage_agents' )
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

    /* ─── 9. Setup dialog (story 36) ─────────────────────────────────── */

    /**
     * The setup steps, in dialog order. The user ticks the first five
     * (POST /setup); `connect` is set by a ping through POST /setup/connect
     * and `review` reads the site's completion. The three configuration
     * steps after it (story 55) are ticked when saved or skipped; they never
     * count toward completion.
     */
    public const SETUP_STEPS = [ 'instance', 'import', 'credentials', 'link', 'publish', 'connect', 'review', 'profile', 'standards', 'schedule' ];

    /** Steps the user may tick or untick by hand. */
    private const SETUP_TICKABLE = [ 'instance', 'import', 'credentials', 'link', 'publish', 'profile', 'standards', 'schedule' ];

    /**
     * Settings the wizard may write (POST /setup/settings): the Blog Profile
     * and Editorial Standards keys of the pqm_manage_settings section, and
     * the switch of scheduled reviews (pqm_manage_agents + pro, like the
     * Settings form's Automated Reviews section). Any other key is refused.
     */
    private const SETUP_SETTING_KEYS = [ 'blog_profile', 'website_context', 'brand_voice_preset', 'brand_voice', 'cron_enabled' ];

    /** Stored progress, normalized: `['steps' => [step => bool], 'complete' => bool]`. */
    private static function setup_progress(): array {
        $stored = get_option( PQM_SETUP_OPTION, [] );

        return [
            'steps'    => is_array( $stored['steps'] ?? null ) ? $stored['steps'] : [],
            'complete' => ! empty( $stored['complete'] ),
        ];
    }

    /**
     * Everything the dialog shows: per-step progress, completion (derived
     * for sites connected before the dialog, see pqm_setup_complete()) and
     * the values the user copies into n8n.
     */
    private static function setup_state(): array {
        $settings = pqm_get_settings();
        $progress = self::setup_progress();
        $complete = pqm_setup_complete();
        $user     = wp_get_current_user();

        $steps = [];
        foreach ( self::SETUP_STEPS as $step ) {
            $steps[ $step ] = 'review' === $step ? $complete : ! empty( $progress['steps'][ $step ] );
        }

        // Preset texts in the site locale, like the payload sends them and
        // the Settings page shows them; labels in the viewer's language.
        $profiles = PQM_Admin::blog_profiles();
        $voices   = pqm_voice_presets();
        [ $profile_texts, $voice_texts ] = pqm_in_site_locale( static fn() => [
            array_map( static fn( $p ) => $p['website_context'], PQM_Admin::blog_profiles() ),
            array_map( static fn( $p ) => $p['instruction'], pqm_voice_presets() ),
        ] );
        $choices = static fn( array $presets, array $texts ): array => array_map(
            static fn( $key ) => [
                'value' => $key,
                'label' => $presets[ $key ]['label'],
                'text'  => $texts[ $key ] ?? '',
            ],
            array_keys( $presets )
        );
        $voices['custom'] = [ 'label' => __( 'Custom', 'post-quality-manager' ) ];

        return [
            'steps'         => $steps,
            'complete'      => $complete,
            'connected'     => ! empty( $settings['n8n_webhook_url'] ),
            'first_result'  => PQM_DB::has_result(),
            'webhook_url'   => (string) $settings['n8n_webhook_url'],
            'token'         => (string) $settings['webhook_token'],
            'import_url'    => PQM_Admin::workflow_import_url(),
            // Raw URL (no &amp;): it travels as JSON into an href.
            'download_url'  => add_query_arg(
                [
                    'action'   => 'pqm_download_workflow',
                    '_wpnonce' => wp_create_nonce( 'pqm_download_workflow' ),
                ],
                admin_url( 'admin-post.php' )
            ),
            // Core offers Application Passwords only over HTTPS or on a
            // `local` environment; the dialog explains the manual path otherwise.
            'app_passwords' => wp_is_application_passwords_available_for_user( $user ),
            'user_login'    => (string) $user->user_login,
            // Suffix of the template's credential names ("WordPress - <host>").
            'site_host'     => pqm_site_host(),
            // Story 55: the configuration steps' stored choices and presets.
            'blog_profile'       => (string) $settings['blog_profile'],
            'website_context'    => (string) $settings['website_context'],
            'brand_voice_preset' => (string) $settings['brand_voice_preset'],
            'brand_voice'        => (string) $settings['brand_voice'],
            'profiles'           => $choices( $profiles, $profile_texts ),
            'voice_presets'      => $choices( $voices, $voice_texts ),
            'cron_enabled'       => ! empty( $settings['cron_enabled'] ),
            'cron_frequency'     => (string) $settings['cron_frequency'],
            'cron_batch_size'    => (int) $settings['cron_batch_size'],
            'cron_min_score'     => (int) $settings['cron_min_score'],
            'cron_max_age_days'  => (int) $settings['cron_max_age_days'],
            'cron_command'       => (string) $settings['cron_command'],
            'can_manage_agents'  => current_user_can( 'pqm_manage_agents' ),
            'is_pro'             => PQM_Features::is_pro(),
        ];
    }

    public static function get_setup_callback(): WP_REST_Response {
        return new WP_REST_Response( self::setup_state(), 200 );
    }

    /**
     * `{ step, done }` ticks or unticks one of the hand-ticked steps;
     * `{ complete: true }` closes the setup (the dialog's first result).
     * Answers the fresh state. Settings are never touched.
     */
    public static function update_setup_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $progress = self::setup_progress();

        if ( true === $request->get_param( 'complete' ) ) {
            $progress['complete'] = true;
        } else {
            $step = (string) $request->get_param( 'step' );
            if ( ! in_array( $step, self::SETUP_TICKABLE, true ) ) {
                return new WP_Error(
                    'pqm_setup_unknown_step',
                    __( 'This setup step is not known. Reload the page and try again.', 'post-quality-manager' ),
                    [ 'status' => 400 ]
                );
            }
            $progress['steps'][ $step ] = (bool) $request->get_param( 'done' );
        }

        update_option( PQM_SETUP_OPTION, $progress, false );

        return new WP_REST_Response( self::setup_state(), 200 );
    }

    /**
     * The connect step: save the webhook URL, then ping it — through the
     * same two helpers as the n8n Workflow page's AJAX. A refused URL (empty,
     * n8n test URL) is not stored; a stored URL whose ping fails stays
     * stored and the step stays open. A ping that gets through records the
     * replied workflow version (the URL was just saved) and answers the
     * page's refreshed `status_html`, plus `outdated` when an older workflow
     * answered: the step still ticks, the dialog shows a warning (D2).
     */
    public static function setup_connect_callback( WP_REST_Request $request ): WP_REST_Response {
        $url   = (string) $request->get_param( 'webhook_url' );
        $saved = PQM_Admin::save_webhook_url( $url );

        if ( is_wp_error( $saved ) ) {
            return new WP_REST_Response( [
                'saved'   => false,
                'ping_ok' => false,
                'message' => $saved->get_error_message(),
            ], 200 );
        }

        $ping     = PQM_Admin::ping_webhook( esc_url_raw( $url ) );
        $progress = self::setup_progress();
        $progress['steps']['connect'] = $ping['ok'];
        update_option( PQM_SETUP_OPTION, $progress, false );

        if ( ! $ping['ok'] ) {
            return new WP_REST_Response( [
                'saved'   => true,
                'ping_ok' => false,
                /* translators: %s: why the ping failed and what to check, e.g. "n8n responded with 404. Check the URL…" */
                'message' => sprintf( __( 'URL saved, but the ping did not get through. %s', 'post-quality-manager' ), $ping['message'] ),
            ], 200 );
        }

        pqm_record_workflow_version( $ping['version'], 'test' );

        return new WP_REST_Response( [
            'saved'       => true,
            'ping_ok'     => true,
            'outdated'    => $ping['outdated'],
            'message'     => $ping['message'],
            'status_html' => PQM_Admin::render_workflow_status(),
        ], 200 );
    }

    /**
     * The configuration steps' save (story 55): a merge-save of the posted
     * keys alone, like save_webhook_url() — sanitize_settings() rebuilds the
     * whole array from a full form post, so it is not called; each key is
     * checked here the way it would sanitize it. Every other pqm_settings key
     * stays as stored. An unknown key or an invalid value refuses the whole
     * request (400, nothing stored); the scheduled-reviews switch needs
     * pqm_manage_agents on a pro site (403), the gate of the Settings form's
     * Automated Reviews section. Answers the fresh setup state.
     */
    public static function setup_settings_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $input = $request->get_json_params();
        $bad   = static fn( string $message ): WP_Error => new WP_Error( 'pqm_setup_bad_setting', $message, [ 'status' => 400 ] );

        if ( ! is_array( $input ) || [] === $input ) {
            return $bad( __( 'Nothing to save. Reload the page and try again.', 'post-quality-manager' ) );
        }
        if ( array_diff( array_keys( $input ), self::SETUP_SETTING_KEYS ) ) {
            return $bad( __( 'The setup guide cannot change this setting. Reload the page and try again.', 'post-quality-manager' ) );
        }
        if ( array_key_exists( 'cron_enabled', $input )
            && ! ( current_user_can( 'pqm_manage_agents' ) && PQM_Features::is_pro() ) ) {
            return new WP_Error(
                'pqm_setup_cron_forbidden',
                __( 'Scheduled reviews need Pro and the right to manage agents. Ask an administrator.', 'post-quality-manager' ),
                [ 'status' => 403 ]
            );
        }

        $clean = [];
        foreach ( $input as $key => $value ) {
            switch ( $key ) {
                case 'blog_profile':
                    $valid = array_keys( PQM_Admin::blog_profiles() );
                    break;
                case 'brand_voice_preset':
                    $valid = array_merge( array_keys( pqm_voice_presets() ), [ 'custom' ] );
                    break;
                case 'cron_enabled':
                    $valid = [ true, false ];
                    break;
                default: // website_context, brand_voice: free text.
                    if ( ! is_string( $value ) ) {
                        return $bad( __( 'This text could not be saved. Reload the page and try again.', 'post-quality-manager' ) );
                    }
                    $clean[ $key ] = sanitize_textarea_field( $value );
                    continue 2;
            }
            if ( ! in_array( $value, $valid, true ) ) {
                return $bad( __( 'This choice is not known. Reload the page and try again.', 'post-quality-manager' ) );
            }
            $clean[ $key ] = $value;
        }

        $settings = get_option( 'pqm_settings', [] );
        $settings = array_merge( is_array( $settings ) ? $settings : [], $clean );
        update_option( 'pqm_settings', $settings );

        // The updated_option hook that reschedules cron is registered for
        // admin requests only; a REST save schedules it itself.
        if ( array_key_exists( 'cron_enabled', $clean ) ) {
            PQM_Cron::schedule();
        }

        return new WP_REST_Response( self::setup_state(), 200 );
    }
}
