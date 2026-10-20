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
 *  GET  /post-quality/v1/setup            — setup dialog state: progress, the n8n connection and path (story 36, n8n provisioning entry 6)
 *  POST /post-quality/v1/setup            — tick a configuration step, mark the setup complete (story 36), or store the manual-path choice (n8n provisioning, entry 6)
 *  POST /post-quality/v1/setup/connect    — save the webhook URL and ping it (story 36)
 *  GET|POST|DELETE /post-quality/v1/n8n/connection — the n8n instance: state, probe + store, forget (n8n provisioning, entry 1)
 *  POST /post-quality/v1/n8n/provision    — set the workflow up on that instance, then ping it (n8n provisioning, entry 4)
 *  POST /post-quality/v1/n8n/token        — rotate the webhook token in n8n and here, then ping (n8n provisioning, entry 10)
 *  POST /post-quality/v1/n8n/workflow     — update the managed workflow to the bundled template in place, then ping (n8n provisioning, entry 9)
 *  DELETE /post-quality/v1/n8n/setup      — start over: forget the setup here and, on request, remove it from n8n (story 71)
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
                    'enum'     => array_keys( PQM_Blocks::item_keys() ),
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
                    'manual'   => [ 'required' => false, 'type' => 'boolean' ],
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

        // n8n provisioning, entry 1: the n8n instance connection (base URL + API key).
        register_rest_route( PQM_REST_NAMESPACE, '/n8n/connection', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_n8n_connection_callback' ],
                'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'connect_n8n_callback' ],
                'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
                'args'                => [
                    'base_url' => [ 'required' => false, 'type' => 'string' ],
                    'api_key'  => [ 'required' => false, 'type' => 'string' ],
                ],
            ],
            [
                'methods'             => 'DELETE', // WP_REST_Server::DELETABLE
                'callback'            => [ __CLASS__, 'forget_n8n_callback' ],
                'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
            ],
        ] );

        // n8n provisioning, entry 4: credentials, workflow, activation, webhook URL — server-side.
        register_rest_route( PQM_REST_NAMESPACE, '/n8n/provision', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'provision_n8n_callback' ],
            'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
            'args'                => [
                'anthropic_key' => [ 'required' => true, 'type' => 'string' ],
            ],
        ] );

        // n8n provisioning, entry 8: take over a hand-made workflow — the proposal (GET), the owner's confirm (POST).
        register_rest_route( PQM_REST_NAMESPACE, '/n8n/adoption', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'get_n8n_adoption_callback' ],
                'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'adopt_n8n_callback' ],
                'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
                'args'                => [
                    'workflow_id' => [ 'required' => true, 'type' => 'string' ],
                ],
            ],
        ] );

        // n8n provisioning, entry 10: rotate the webhook token — n8n's credential first, then the setting, then a ping.
        register_rest_route( PQM_REST_NAMESPACE, '/n8n/token', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'rotate_n8n_token_callback' ],
            'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
        ] );

        // n8n provisioning, entry 9: update the managed workflow to the bundled template in place, then a ping.
        register_rest_route( PQM_REST_NAMESPACE, '/n8n/workflow', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'update_n8n_workflow_callback' ],
            'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
        ] );

        // Story 71: start over — forget the setup here and, when asked, remove the map's objects from n8n.
        register_rest_route( PQM_REST_NAMESPACE, '/n8n/setup', [
            'methods'             => 'DELETE', // WP_REST_Server::DELETABLE
            'callback'            => [ __CLASS__, 'start_over_n8n_callback' ],
            'permission_callback' => [ __CLASS__, 'check_manage_settings' ],
            'args'                => [
                'remove_from_n8n' => [ 'required' => false, 'type' => 'boolean', 'default' => false ],
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
            'workflow_variant' => [
                'required'    => false,
                'type'        => 'string',
                'enum'        => [ 'free', 'pro' ],
                'default'     => 'pro',
                'description' => 'Template variant (story 54): the Free template sends free; none is the Pro one.',
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
            pqm_record_workflow_version( $wf_version, 'command', (string) $request->get_param( 'workflow_variant' ) );
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
        ];

        // Habit-loop nudge: first result is in, cron still off. Pro build
        // only (ADR 0014), and only on a licensed site — on an unlicensed one
        // the Automated Reviews section is locked. Shown to whoever can see
        // that section (pqm_manage_agents, ticket 16).
        if ( pqm_fs()->is__premium_only() ) {
            $data['cron_nudge'] = null !== $average
                && empty( $settings['cron_enabled'] )
                && ! get_option( 'pqm_cron_nudge_dismissed' )
                && current_user_can( 'pqm_manage_agents' )
                && PQM_Features::is_pro();

            if ( $data['cron_nudge'] ) {
                // Raw URL (no &amp;): it travels as JSON into an href.
                $data['cron_nudge_dismiss_url'] = add_query_arg(
                    [
                        'pqm_dismiss_cron_nudge' => '1',
                        '_wpnonce'               => wp_create_nonce( 'pqm_dismiss_cron_nudge' ),
                    ],
                    admin_url( 'admin.php?page=pqm-dashboard' )
                );
            }
        }

        return new WP_REST_Response( $data, 200 );
    }

    /* ─── 9. Setup dialog (story 36) ─────────────────────────────────── */

    /**
     * The setup steps (n8n provisioning, entry 6). The n8n steps are derived
     * from the site, never ticked, and tick only what the plugin did: `n8n`
     * (Connect your n8n) is done while a key is stored with a passing check;
     * `provision` (Set up the workflow) once the plugin set the workflow up
     * (the setup map exists) and its webhook URL is saved; `manual` (Set up
     * by hand, the other path's one step) once a webhook URL is saved. A
     * saved URL ticks `provision` and `manual` only while its last ping
     * through the dialog did not fail (the `connect` record that POST
     * /setup/connect and POST /n8n/provision write; a URL saved without one,
     * in Settings or before the dialog, counts as done). A saved URL never
     * ticks `n8n`. `review` reads the site's completion. The dialog shows
     * `n8n` and `provision` (the key path), or `manual` (the manual path),
     * then `review`; the path is GET /setup's `manual`: with a URL saved, how
     * the workflow was made (by the plugin → the key path, by hand → the
     * manual path, even with a passing key: adoption, entry 6.8, takes it
     * over); without one, pqm_manual_reason() (a stored choice or a failed
     * check, outranked by a passing key). The three configuration steps
     * after it (story 55) are ticked when saved or skipped; they never count
     * toward completion. A stored key of a step not listed here (the manual
     * steps before entry 6) is ignored; `connect` is read only through
     * `provision` and `manual`.
     */
    public const SETUP_STEPS = [ 'n8n', 'provision', 'manual', 'review', 'profile', 'standards', 'schedule' ];

    /** Pings of the webhook right after activation, at most (bug 80, D3): ping_after_activation(). */
    private const PROVISION_PINGS = 3;

    /** Steps the user may tick or untick (POST /setup): the configuration steps. */
    private const SETUP_TICKABLE = [ 'profile', 'standards', 'schedule' ];

    /**
     * Settings the wizard may write (POST /setup/settings): the Blog Profile
     * and Editorial Standards keys of the pqm_manage_settings section, and,
     * in the Pro build only (ADR 0014), `cron_enabled`, the switch of
     * scheduled reviews (pqm_manage_agents + pro, like the Settings form's
     * Automated Reviews section). Any other key is refused.
     */
    private const SETUP_SETTING_KEYS = [ 'blog_profile', 'website_context', 'brand_voice_preset', 'brand_voice' ];

    /**
     * Stored progress, normalized: `['steps' => [step => bool], 'complete' => bool]`,
     * plus `'manual' => true` once the owner chose the manual path (POST
     * /setup `{ manual: true }` writes it, pqm_manual_reason() reads it), so a
     * step tick keeps it.
     */
    private static function setup_progress(): array {
        $stored   = get_option( PQM_SETUP_OPTION, [] );
        $progress = [
            'steps'    => is_array( $stored['steps'] ?? null ) ? $stored['steps'] : [],
            'complete' => ! empty( $stored['complete'] ),
        ];
        if ( ! empty( $stored['manual'] ) ) {
            $progress['manual'] = true;
        }

        return $progress;
    }

    /**
     * Everything the dialog shows: per-step progress, completion (derived
     * for sites connected before the dialog, see pqm_setup_complete()), the
     * n8n connection (never its key) and the path the n8n steps take.
     */
    private static function setup_state(): array {
        $settings  = pqm_get_settings();
        $progress  = self::setup_progress();
        $complete  = pqm_setup_complete();
        $n8n       = PQM_N8n::public_state();
        $connected = ! empty( $settings['n8n_webhook_url'] );
        $reason    = pqm_manual_reason();
        $key_ok    = $n8n['connected'];
        // What the plugin set up in n8n (entry 6.4); a URL without it was made by hand.
        $provisioned = (bool) get_option( PQM_N8n::SETUP_OPTION );
        $leftovers   = get_option( PQM_N8n::LEFTOVERS_OPTION, false );
        $left_on     = is_array( $leftovers ) ? (string) ( $leftovers['base_url'] ?? '' ) : '';
        // A failed ping keeps the step open, so the dialog offers the paste-URL body again.
        $url_done  = $connected && false !== ( $progress['steps']['connect'] ?? null );

        // Only what the plugin did ticks a step: a saved URL ticks neither
        // Connect your n8n nor, without the setup map, Set up the workflow.
        $derived = [
            'n8n'       => $key_ok,
            'provision' => $provisioned && $url_done,
            'manual'    => $url_done,
            'review'    => $complete,
        ];
        $steps = [];
        foreach ( self::SETUP_STEPS as $step ) {
            $steps[ $step ] = $derived[ $step ] ?? ! empty( $progress['steps'][ $step ] );
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

        $state = [
            'steps'         => $steps,
            'complete'      => $complete,
            'connected'     => $connected,
            'first_result'  => PQM_DB::has_result(),
            'webhook_url'   => (string) $settings['n8n_webhook_url'],
            // The Webhook Trigger's per-site path, for the URL field's placeholder.
            'webhook_path'  => pqm_webhook_path(),
            // n8n provisioning, entry 6: the connection (no key), the path
            // the n8n steps take — with a URL saved, how the workflow was
            // made (a hand-made one keeps the manual path, even with a
            // passing key); without one, a stored choice or a failed check,
            // which a passing key outranks (pqm_manual_reason()) — and what
            // provisioning left.
            'n8n'           => $n8n,
            'manual'        => $connected ? ! $provisioned : null !== $reason,
            'manual_reason' => $reason,
            'provisioned'   => $provisioned,
            // Story 71: only a record of the connected instance is removed by the
            // next setup; one of another instance is dropped and its host named.
            'leftovers'     => is_array( $leftovers ) && $left_on === $n8n['base_url'],
            'leftovers_elsewhere' => is_array( $leftovers ) && $left_on !== $n8n['base_url'] ? PQM_N8n::host( $left_on ) : '',
            // Story 55: the configuration steps' stored choices and presets.
            'blog_profile'       => (string) $settings['blog_profile'],
            'website_context'    => (string) $settings['website_context'],
            'brand_voice_preset' => (string) $settings['brand_voice_preset'],
            'brand_voice'        => (string) $settings['brand_voice'],
            'profiles'           => $choices( $profiles, $profile_texts ),
            'voice_presets'      => $choices( $voices, $voice_texts ),
        ];
        // The Schedule step's values: Pro build only (ADR 0014).
        if ( pqm_fs()->is__premium_only() ) {
            $state += [
                'cron_enabled'       => ! empty( $settings['cron_enabled'] ),
                'cron_frequency'     => (string) $settings['cron_frequency'],
                'cron_batch_size'    => (int) $settings['cron_batch_size'],
                'cron_min_score'     => (int) $settings['cron_min_score'],
                'cron_max_age_days'  => (int) $settings['cron_max_age_days'],
                'cron_command'       => (string) $settings['cron_command'],
            ];
        }

        if ( pqm_fs()->is__premium_only() ) {
            // Bug 73: whose Application Password Set up the workflow would create — the
            // current user's, by display name; '' where the site offers none (D1).
            $user                               = wp_get_current_user();
            $state['application_password_user'] = wp_is_application_passwords_available_for_user( $user ) ? (string) ( $user->display_name ?? '' ) : '';
        }

        $state += [
            'can_manage_agents'  => current_user_can( 'pqm_manage_agents' ),
            'is_pro'             => PQM_Features::is_pro(),
            // Which build runs (story 54): the dialog leaves out the steps
            // the Free template has no node for.
            'build'              => 'free',
        ];
        if ( pqm_fs()->is__premium_only() ) {
            $state['build'] = 'pro';
        }

        return $state;
    }

    public static function get_setup_callback(): WP_REST_Response {
        return new WP_REST_Response( self::setup_state(), 200 );
    }

    /**
     * `{ step, done }` ticks or unticks a configuration step;
     * `{ complete: true }` closes the setup (the dialog's first result);
     * `{ manual: true }` stores the owner's choice to set up n8n by hand
     * ("Set up by hand instead"; only `true`: a passing key outranks the
     * choice, nothing clears it). Answers the fresh state. Settings are
     * never touched.
     */
    public static function update_setup_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $progress = self::setup_progress();

        if ( true === $request->get_param( 'complete' ) ) {
            $progress['complete'] = true;
        } elseif ( null !== $request->get_param( 'manual' ) ) {
            if ( true !== $request->get_param( 'manual' ) ) {
                return new WP_Error(
                    'pqm_setup_bad_manual',
                    __( 'This choice is not known. Reload the page and try again.', 'post-quality-manager' ),
                    [ 'status' => 400 ]
                );
            }
            $progress['manual'] = true;
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
     * stored, and the dialog shows the cause. A ping that gets through
     * records the replied workflow version (the URL was just saved; a failed
     * ping records the failure, story 67) and answers `outdated` when an
     * older workflow answered: the dialog shows a warning (D2). Every reply
     * carries the page's refreshed `status_html`, the whole body with this
     * reply's message as its first line (story 67: the page's Save and test
     * posts here too). The dialog's steps Set up the workflow and Set up by
     * hand read this ping's record (`steps.connect`, entry 6): a failed one
     * keeps them open.
     */
    public static function setup_connect_callback( WP_REST_Request $request ): WP_REST_Response {
        $url   = (string) $request->get_param( 'webhook_url' );
        $saved = PQM_Admin::save_webhook_url( $url );

        if ( is_wp_error( $saved ) ) {
            return new WP_REST_Response( [
                'saved'       => false,
                'ping_ok'     => false,
                'message'     => $saved->get_error_message(),
                'status_html' => PQM_Admin::render_workflow_body( $saved->get_error_message(), 'error' ),
            ], 200 );
        }

        $ping     = PQM_Admin::ping_webhook( esc_url_raw( $url ) );
        $progress = self::setup_progress();
        $progress['steps']['connect'] = $ping['ok'];
        update_option( PQM_SETUP_OPTION, $progress, false );

        if ( ! $ping['ok'] ) {
            /* translators: %s: why the ping failed and what to check, e.g. "n8n responded with 404. Check the URL…" */
            $message = sprintf( __( 'URL saved, but the ping did not get through. %s', 'post-quality-manager' ), $ping['message'] );

            return new WP_REST_Response( [
                'saved'       => true,
                'ping_ok'     => false,
                'message'     => $message,
                'status_html' => PQM_Admin::render_workflow_body( $message, 'error' ),
            ], 200 );
        }

        pqm_record_workflow_version( $ping['version'], 'test', $ping['variant'], $ping['miswired'] ?? null );

        return new WP_REST_Response( [
            'saved'       => true,
            'ping_ok'     => true,
            'outdated'    => $ping['outdated'],
            'message'     => $ping['message'],
            'status_html' => PQM_Admin::render_workflow_body( $ping['message'], $ping['outdated'] ? 'warning' : 'success' ),
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
        $keys = self::SETUP_SETTING_KEYS;
        if ( pqm_fs()->is__premium_only() ) {
            $keys[] = 'cron_enabled';
        }
        if ( array_diff( array_keys( $input ), $keys ) ) {
            return $bad( __( 'The setup guide cannot change this setting. Reload the page and try again.', 'post-quality-manager' ) );
        }
        if ( pqm_fs()->is__premium_only() ) {
            if ( array_key_exists( 'cron_enabled', $input )
                && ! ( current_user_can( 'pqm_manage_agents' ) && PQM_Features::is_pro() ) ) {
                return new WP_Error(
                    'pqm_setup_cron_forbidden',
                    __( 'Scheduled reviews need Pro and the right to manage agents. Ask an administrator.', 'post-quality-manager' ),
                    [ 'status' => 403 ]
                );
            }
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
        if ( pqm_fs()->is__premium_only() ) {
            if ( array_key_exists( 'cron_enabled', $clean ) ) {
                PQM_Cron::schedule();
            }
        }

        return new WP_REST_Response( self::setup_state(), 200 );
    }

    /* ─── n8n instance connection (n8n provisioning, entry 1) ───────── */

    /**
     * The stored connection: `{ base_url, key_set, key_hint, probe,
     * connected, … }`, the key itself never (only its last 4 characters).
     * Calls nothing on n8n: the page shows the stored key's last probe,
     * passing or refused (story 74).
     */
    public static function get_n8n_connection_callback(): WP_REST_Response {
        return new WP_REST_Response( PQM_N8n::public_state(), 200 );
    }

    /**
     * Probe an instance and store its base URL and key once the probe
     * passes; a failed probe leaves the stored URL and key as they were and,
     * when it offers the manual path, records its cause (PQM_N8n::record_failure()).
     * A refusal of the stored key on the stored address (cause `key` or
     * `scoped`) also replaces its probe (story 74, D1: a timeout or a 500
     * keeps the passing one; D2: a new key refused changes nothing stored).
     * Without `api_key`, the stored key re-runs the probe (Check again) —
     * for the stored address only, so a new address never receives it.
     * Answers the stored state with `probe` = this run's result.
     */
    public static function connect_n8n_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $stored = PQM_N8n::connection();
        $url    = sanitize_text_field( (string) ( $request->get_param( 'base_url' ) ?? $stored['base_url'] ) );
        $key    = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
        $origin = PQM_N8n::origin( $url );

        if ( '' === $key && ! is_wp_error( $origin ) ) {
            if ( '' === $stored['api_key'] || $origin !== $stored['base_url'] ) {
                return new WP_Error(
                    'pqm_n8n_key_required',
                    __( 'Enter an n8n API key for this address. Create one in n8n under Settings → n8n API.', 'post-quality-manager' ),
                    [ 'status' => 400 ]
                );
            }
            $key = $stored['api_key'];
        }

        $probe = PQM_N8n::probe( $url, $key );
        if ( $probe['ok'] ) {
            PQM_N8n::save( $origin, $key, $probe );
        } else {
            // Entry 2: the Workflow File section states the cause after a reload.
            PQM_N8n::record_failure( $probe );
            if ( in_array( $probe['cause'], [ 'key', 'scoped' ], true ) && $key === $stored['api_key'] && $origin === $stored['base_url'] ) {
                PQM_N8n::record_refusal( $probe );
            }
        }

        return new WP_REST_Response( [ 'probe' => $probe ] + PQM_N8n::public_state(), 200 );
    }

    /** Forget: the key, its probe and a recorded failure go, the base URL stays. Nothing changes on n8n. */
    public static function forget_n8n_callback(): WP_REST_Response {
        PQM_N8n::forget();

        return new WP_REST_Response( PQM_N8n::public_state(), 200 );
    }

    /**
     * Start over (story 71): PQM_N8n::start_over() clears the setup map,
     * the saved webhook URL and the version report, revokes the Pro build's
     * Application Password and, with `remove_from_n8n`, removes the map's
     * objects from n8n by id. Answers the n8n Instance section's state plus
     * `message` (what was removed, and whether n8n kept some of it) and
     * `status_html`, the page's body in state 2 with the message first — the
     * island swaps it, so no re-read follows. A refusal answers its error.
     */
    public static function start_over_n8n_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $result = PQM_N8n::start_over( (bool) $request->get_param( 'remove_from_n8n' ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $tone = 'success';
        if ( $result['left'] ) {
            $tone    = 'warning';
            $message = __( 'The setup was removed here. Some of it is still in n8n: the next setup removes it first.', 'post-quality-manager' );
        } elseif ( $result['removed'] ) {
            $message = __( 'The setup was removed here and in n8n. Set n8n up again when you are ready.', 'post-quality-manager' );
        } else {
            $message = __( 'The setup was removed here. Your workflow and credentials stay in n8n.', 'post-quality-manager' );
        }
        $message              = self::dropped_leftovers_prefix( (string) ( $result['dropped_leftovers'] ?? '' ) ) . $message;
        $reply                = PQM_N8n::public_state() + [ 'message' => $message ];
        $reply['status_html'] = PQM_Admin::render_workflow_body( $message, $tone );

        return new WP_REST_Response( $reply, 200 );
    }

    /**
     * Story 71: a leftovers record of another instance was dropped, not
     * removed (provisioning) or replaced by this instance's remainder (Start
     * over) — the owner removes those objects there. The sentence plus a
     * space, to go before a reply's message; '' when no host was dropped.
     */
    private static function dropped_leftovers_prefix( string $host ): string {
        if ( '' === $host ) {
            return '';
        }

        return sprintf(
            /* translators: %s: the n8n instance's host, e.g. "old.example.com" */
            __( 'An earlier setup left a workflow or credentials on %s. The plugin does not touch another instance: remove them there by hand.', 'post-quality-manager' ),
            $host
        ) . ' ';
    }

    /**
     * Provision the connected instance (PQM_N8n::provision()), then the
     * connect step's flow: save the production webhook URL — the connected
     * origin + /webhook/ + pqm_webhook_path(), as the n8n public API exposes
     * no webhook base — ping it, record the replied version. The Anthropic
     * key is passed on and never echoed. The ping goes through
     * ping_after_activation(): a 404 right after activation is usually n8n
     * still publishing (bug 80), so it is retried before it counts. A failed
     * ping keeps everything: n8n may serve webhooks on another address than
     * its editor (WEBHOOK_URL), so the reply points to pasting the Webhook
     * Trigger's production URL.
     * Answers `{ provisioned, ping_ok, workflow_id, message }`, plus the
     * page's refreshed `status_html` when the ping passed (entry 6), and on the
     * Pro build `rewrite_ready` (false when no WordPress credential was
     * created, D1).
     */
    public static function provision_n8n_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $setup = PQM_N8n::provision( sanitize_text_field( (string) $request->get_param( 'anthropic_key' ) ) );
        if ( is_wp_error( $setup ) ) {
            return $setup;
        }
        // Story 71: the host whose leftovers the run dropped without a call, on the returned map only.
        $dropped = (string) ( $setup['dropped_leftovers'] ?? '' );
        unset( $setup['dropped_leftovers'] );

        $url   = PQM_N8n::connection()['base_url'] . '/webhook/' . $setup['path'];
        $saved = PQM_Admin::save_webhook_url( $url );
        $ping  = is_wp_error( $saved )
            ? [ 'ok' => false, 'message' => $saved->get_error_message() ]
            : self::ping_after_activation( esc_url_raw( $url ) );
        $progress = self::setup_progress();
        $progress['steps']['connect'] = $ping['ok'];
        update_option( PQM_SETUP_OPTION, $progress, false );

        $reply = [
            'provisioned' => true,
            'ping_ok'     => $ping['ok'],
            'workflow_id' => $setup['workflow_id'],
            'message'     => $ping['ok'] ? $ping['message'] : self::ping_failed_sentence( $ping ),
        ];
        if ( $ping['ok'] ) {
            pqm_record_workflow_version( $ping['version'], 'test', $ping['variant'], $ping['miswired'] ?? null );
        } elseif ( 404 === ( $ping['code'] ?? 0 ) ) {
            // The page's "needs attention" says the same as the dialog, not the Test button's 404 line.
            pqm_record_workflow_failure( $reply['message'] );
        }
        if ( pqm_fs()->is__premium_only() ) {
            $reply['rewrite_ready'] = isset( $setup['credentials']['wordpress'] );
            if ( ! $reply['rewrite_ready'] ) {
                $reply['message'] .= ' ' . __( 'Application Passwords are turned off on this site, so no WordPress credential was created. Usually the site does not run on HTTPS; sometimes a security plugin turns them off. Rewrite stays off until Application Passwords are available and a WordPress credential is added in n8n on the Update WP Post (Rewrite) node.', 'post-quality-manager' );
            }
        }
        $reply['message'] = self::dropped_leftovers_prefix( $dropped ) . $reply['message'];
        // The page's body, as POST /setup/connect answers it (entry 6; story 67: in every reply, the message first).
        $reply['status_html'] = PQM_Admin::render_workflow_body( $reply['message'], self::tone( $reply ) );

        return new WP_REST_Response( $reply, 200 );
    }

    /**
     * The ping that follows an activation (bug 80, D3): n8n may still be
     * publishing the workflow when the activate reply returns, so a ping sent
     * at once can meet a 404 that is nobody's fault — see
     * memory/n8n-provisioning.md (publication outbox finding, 2026-10-20).
     * Only a 404 is retried, up to PROVISION_PINGS pings 2 s apart (filter
     * `pqm_provision_ping_retry_wait`; the harness sets 0); every other
     * answer, pass or fail, counts the first time. Provisioning is the one
     * caller that just activated something: the Test button, POST
     * /setup/connect, the rotation and the update ping once.
     */
    private static function ping_after_activation( string $url ): array {
        for ( $attempt = 1; ; $attempt++ ) {
            // The workflow was just created: a 404 here is the window, never a gone workflow (story 71, no read).
            $ping = PQM_Admin::ping_webhook( $url, true, false );
            if ( $ping['ok'] || 404 !== ( $ping['code'] ?? 0 ) || $attempt >= self::PROVISION_PINGS ) {
                return $ping;
            }
            sleep( max( 0, (int) apply_filters( 'pqm_provision_ping_retry_wait', 2 ) ) );
        }
    }

    /**
     * What the dialog says when the ping after provisioning did not get
     * through. A 404 that outlasted the retries: the workflow is active, its
     * webhook did not answer yet, so Test again first and the editor-address
     * hint second (bug 80). Anything else: the ping's own sentence, then the hint.
     */
    private static function ping_failed_sentence( array $ping ): string {
        if ( 404 === ( $ping['code'] ?? 0 ) ) {
            return __( 'Your workflow is set up and active in n8n, but its webhook did not answer yet (n8n responded with 404). Give n8n a moment, then press Test again on the n8n Workflow page. If it keeps answering 404, your n8n may serve webhooks on another address than its editor: copy the production URL from the Webhook Trigger in n8n and paste it as the n8n Webhook URL.', 'post-quality-manager' );
        }

        return sprintf(
            /* translators: %s: why the ping failed and what to check, e.g. "n8n refused the webhook credential (HTTP 401)…" */
            __( 'Your workflow is set up and active in n8n, but the ping did not get through. %s If your n8n serves webhooks on another address than its editor, copy the production URL from the Webhook Trigger in n8n and paste it as the n8n Webhook URL.', 'post-quality-manager' ),
            $ping['message']
        );
    }

    /**
     * The outcome line's tone for a reply that pinged (story 67): an error
     * when the ping did not get through, a warning when the workflow answered
     * older, mis-wired or as the Free variant, or without Rewrite; success otherwise.
     */
    private static function tone( array $reply ): string {
        if ( empty( $reply['ping_ok'] ) ) {
            return 'error';
        }

        return ! empty( $reply['outdated'] ) || ( isset( $reply['rewrite_ready'] ) && ! $reply['rewrite_ready'] ) ? 'warning' : 'success';
    }

    /* ─── Adoption of a hand-made workflow (n8n provisioning, entry 8) ─ */

    /**
     * What the plugin would manage (PQM_N8n::propose_adoption()): the
     * workflow at the saved webhook URL and its credentials by name. One GET
     * on n8n, nothing stored. On the Pro build `rewrite_ready` says whether
     * the node saving a rewrite carries a credential (D2).
     */
    public static function get_n8n_adoption_callback(): WP_REST_Response|WP_Error {
        $proposal = PQM_N8n::propose_adoption();
        if ( is_wp_error( $proposal ) ) {
            return $proposal;
        }
        if ( pqm_fs()->is__premium_only() ) {
            $proposal['rewrite_ready'] = isset( $proposal['credentials']['wordpress'] );
        }

        return new WP_REST_Response( $proposal, 200 );
    }

    /**
     * The owner's confirm: store the setup map of the workflow shown
     * (PQM_N8n::adopt(), which reads n8n again and refuses a changed one).
     * Answers the n8n Instance section's state, plus `rewrite_ready` on Pro.
     */
    public static function adopt_n8n_callback( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $setup = PQM_N8n::adopt( sanitize_text_field( (string) $request->get_param( 'workflow_id' ) ) );
        if ( is_wp_error( $setup ) ) {
            return $setup;
        }
        $reply = PQM_N8n::public_state();
        if ( pqm_fs()->is__premium_only() ) {
            $reply['rewrite_ready'] = isset( $setup['credentials']['wordpress'] );
        }

        return new WP_REST_Response( $reply, 200 );
    }

    /* ─── Webhook token rotation (n8n provisioning, entry 10) ────────── */

    /**
     * Rotate the webhook token: PQM_N8n::rotate_token() writes a new one
     * into the setup map's webhook credential in n8n, then the one-key save
     * here, then a ping of the saved URL with it, which proves the pair (no
     * diagnosis read: the PATCH stays the one n8n API call). A refused
     * rotation answers its error, nothing written. The return path needs
     * nothing: the workflow sends back the token of the payload it got.
     * Answers `{ rotated, ping_ok, outdated, message, status_html }`: the
     * page's refreshed body with the message first (story 67; the version
     * recorded when the ping passed, the failure otherwise, as provisioning
     * does). The token itself is in no reply field: the body shows the
     * page's own token row, which the saving page already carries.
     */
    public static function rotate_n8n_token_callback(): WP_REST_Response|WP_Error {
        $token = PQM_N8n::rotate_token();
        if ( is_wp_error( $token ) ) {
            return self::with_body( $token );
        }
        PQM_Admin::save_webhook_token( $token );

        $ping = PQM_Admin::ping_webhook( (string) pqm_get_settings()['n8n_webhook_url'], false );
        if ( $ping['ok'] ) {
            /* translators: %s: the test ping's message, e.g. "Ping OK — this site reaches n8n…" */
            $message = sprintf( __( 'Token rotated. %s', 'post-quality-manager' ), $ping['message'] );
        } elseif ( ! empty( $ping['refused'] ) ) {
            // n8n holds the new token in the map's credential: a refusal means the trigger carries another one.
            $message = __( 'The token was rotated in n8n and saved here. n8n still refused the test ping: your Webhook Trigger uses another credential than the one the plugin manages. In n8n, pick that credential on the Webhook Trigger, publish the workflow, then send a test ping.', 'post-quality-manager' );
        } else {
            /* translators: %s: why the ping failed and what to check, e.g. "n8n responded with 404. Check the URL…" */
            $message = sprintf( __( 'The token was rotated in n8n and saved here. %s', 'post-quality-manager' ), $ping['message'] );
        }

        $reply = [ 'rotated' => true, 'ping_ok' => $ping['ok'], 'outdated' => $ping['outdated'], 'message' => $message ];
        if ( $ping['ok'] ) {
            pqm_record_workflow_version( $ping['version'], 'test', $ping['variant'], $ping['miswired'] ?? null );
        }
        $reply['status_html'] = PQM_Admin::render_workflow_body( $message, self::tone( $reply ) );

        return new WP_REST_Response( $reply, 200 );
    }

    /* ─── One-click workflow update (n8n provisioning, entry 9) ──────── */

    /**
     * Update the managed workflow to the bundled template:
     * PQM_N8n::update_workflow() reads it once and PUTs the build's template
     * onto it (credentials and path kept), then the ordinary ping of the
     * saved URL, its diagnosis included, proves the new version answers. A
     * refused update answers its error, nothing written. Answers
     * `{ updated, ping_ok, outdated, message, status_html }`: the page's
     * refreshed body with the message first (story 67; the version recorded
     * when the ping passed, the failure otherwise, as the rotation does)
     * and, on the Pro build, `rewrite_ready` (false when the map has no
     * WordPress credential: that node stays name-only, D1).
     */
    public static function update_n8n_workflow_callback(): WP_REST_Response|WP_Error {
        $setup = PQM_N8n::update_workflow();
        if ( is_wp_error( $setup ) ) {
            return self::with_body( $setup );
        }

        // The PUT just passed: the workflow is there, so a 404 is never read as a gone workflow (story 71, no read).
        $ping    = PQM_Admin::ping_webhook( (string) pqm_get_settings()['n8n_webhook_url'], true, false );
        $message = $ping['ok']
            /* translators: %s: the test ping's message, e.g. "Ping OK — this site reaches n8n…" */
            ? sprintf( __( 'Workflow updated. %s', 'post-quality-manager' ), $ping['message'] )
            /* translators: %s: why the ping failed and what to check, e.g. "n8n responded with 404. Check the URL…" */
            : sprintf( __( 'The workflow was updated in n8n. %s', 'post-quality-manager' ), $ping['message'] );

        $reply = [ 'updated' => true, 'ping_ok' => $ping['ok'], 'outdated' => $ping['outdated'], 'message' => $message ];
        if ( $ping['ok'] ) {
            pqm_record_workflow_version( $ping['version'], 'test', $ping['variant'], $ping['miswired'] ?? null );
        }
        if ( pqm_fs()->is__premium_only() ) {
            $reply['rewrite_ready'] = isset( $setup['credentials']['wordpress'] );
            if ( ! $reply['rewrite_ready'] ) {
                $reply['message'] .= ' ' . __( 'Rewrite stays off until a WordPress credential is set in n8n on the Update WP Post (Rewrite) node.', 'post-quality-manager' );
            }
        }
        $reply['status_html'] = PQM_Admin::render_workflow_body( $reply['message'], self::tone( $reply ) );

        return new WP_REST_Response( $reply, 200 );
    }

    /**
     * A refused managed action (Rotate, Update) with the page's body in its
     * data (story 74, D4): the refusal as the outcome line, the state as it
     * is now — a refused key recorded on the way takes Rotate and Update
     * off the page at once. pqm-admin.js swaps it on an error reply too.
     */
    private static function with_body( WP_Error $error ): WP_Error {
        $error->add_data( (array) $error->get_error_data() + [ 'status_html' => PQM_Admin::render_workflow_body( $error->get_error_message(), 'error' ) ] );

        return $error;
    }
}
