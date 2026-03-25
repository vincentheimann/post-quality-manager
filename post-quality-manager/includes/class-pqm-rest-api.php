<?php
/**
 * REST API endpoints for the Post Quality Manager plugin.
 *
 * Endpoints:
 *  GET  /post-quality/v1/post-score      — existing: expose post data + Yoast scores
 *  POST /post-quality/v1/save-review      — n8n posts review results here
 *  GET  /post-quality/v1/reviews/{id}     — fetch stored reviews for a post
 *  POST /post-quality/v1/trigger-review   — WordPress triggers n8n review
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_REST_API {

    /* ─── Route Registration ─────────────────────────────────────────── */

    public static function register_routes(): void {

        // Existing endpoint: GET post data + Yoast scores.
        register_rest_route( PQM_REST_NAMESPACE, '/post-score', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'post_score_callback' ],
            'permission_callback' => [ __CLASS__, 'check_edit_posts' ],
            'args'                => self::post_score_args(),
        ] );

        // New: n8n POSTs review results.
        register_rest_route( PQM_REST_NAMESPACE, '/save-review', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'save_review_callback' ],
            'permission_callback' => [ __CLASS__, 'check_webhook_token' ],
            'args'                => self::save_review_args(),
        ] );

        // New: fetch stored reviews for a post.
        register_rest_route( PQM_REST_NAMESPACE, '/reviews/(?P<post_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ __CLASS__, 'get_reviews_callback' ],
            'permission_callback' => [ __CLASS__, 'check_edit_posts' ],
        ] );

        // New: trigger a review via n8n.
        register_rest_route( PQM_REST_NAMESPACE, '/trigger-review', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'trigger_review_callback' ],
            'permission_callback' => [ __CLASS__, 'check_edit_posts' ],
            'args'                => self::trigger_review_args(),
        ] );
    }

    /* ─── Permission Callbacks ───────────────────────────────────────── */

    public static function check_edit_posts(): bool {
        return current_user_can( 'edit_posts' );
    }

    /**
     * Token-based auth for the save-review webhook.
     * Accepts either the X-PQM-Token header or standard WP auth.
     */
    public static function check_webhook_token( WP_REST_Request $request ): bool {
        // Allow standard WP auth (Application Passwords).
        if ( current_user_can( 'edit_posts' ) ) {
            return true;
        }

        $settings       = pqm_get_settings();
        $expected_token  = $settings['webhook_token'] ?? '';
        $provided_token  = $request->get_header( 'X-PQM-Token' ) ?? '';

        if ( empty( $expected_token ) || empty( $provided_token ) ) {
            return false;
        }

        return hash_equals( $expected_token, $provided_token );
    }

    /* ─── 1. GET /post-score (existing logic, moved here) ────────────── */

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
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
        ] );

        if ( ! $query->have_posts() ) {
            return new WP_Error(
                'pqm_not_found',
                sprintf( 'No published %s found with slug: %s', $post_type, $slug ),
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
                'enum'     => [ 'review', 'audit', 'score', 'seo', 'takeaways', 'sources' ],
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

    public static function get_reviews_callback( WP_REST_Request $request ): WP_REST_Response {
        $post_id = (int) $request->get_param( 'post_id' );
        $reviews = PQM_DB::get_reviews_for_post( $post_id );

        // Decode result_data JSON for each review.
        foreach ( $reviews as &$review ) {
            $decoded = json_decode( $review['result_data'], true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $review['result_data'] = $decoded;
            }
            // If json_decode fails, $review['result_data'] remains the raw string as saved by n8n.
        }

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
                'enum'     => [ 'review', 'audit', 'score', 'seo', 'takeaways', 'sources', 'rewrite', 'apply' ],
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

        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return new WP_Error(
                'pqm_invalid_post',
                'Post not found or not published.',
                [ 'status' => 404 ]
            );
        }

        // Collect post data (same as post-score endpoint).
        $content_html  = get_post_field( 'post_content', $post_id );
        $content_plain = wp_strip_all_tags( apply_filters( 'the_content', $content_html ) );
        $yoast_active  = defined( 'WPSEO_VERSION' );

        $excerpt = has_excerpt( $post_id )
            ? wp_strip_all_tags( get_the_excerpt( $post_id ) )
            : wp_trim_words( $content_plain, 55, '...' );

        // Build payload for n8n.
        $payload = [
            'callback_url'     => rest_url( PQM_REST_NAMESPACE . '/save-review' ),
            'auth_token'       => $settings['webhook_token'],
            'notification_url' => $settings['notification_url'] ?? '',
            'command'          => $command,
            'post_id'          => $post_id,
            'slug'             => $post->post_name,
            'post_type'        => $post->post_type,
            'title'            => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ),
            'link'             => get_permalink( $post_id ),
            'excerpt'          => $excerpt,
            'content'          => mb_substr( $content_plain, 0, 15000 ),
            'content_html'     => mb_substr( $content_html, 0, 20000 ),
            'seo_score'        => $yoast_active ? (int) get_post_meta( $post_id, '_yoast_wpseo_linkdex', true ) : null,
            'readability_score'=> $yoast_active ? (int) get_post_meta( $post_id, '_yoast_wpseo_content_score', true ) : null,
            'focus_keyphrase'  => $yoast_active ? (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) : '',
            'meta_description' => $yoast_active ? (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ) : '',
            'seo_title'        => $yoast_active ? (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ) : '',
        ];

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

        return new WP_REST_Response( [
            'success'     => $status_code >= 200 && $status_code < 300,
            'message'     => sprintf( 'Review triggered for "%s" (command: %s).', $post->post_title, $command ),
            'n8n_status'  => $status_code,
        ], 200 );
    }
}
