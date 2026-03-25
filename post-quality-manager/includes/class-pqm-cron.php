<?php
/**
 * WP-Cron scheduling for automated post reviews.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_Cron {

    const HOOK = 'pqm_cron_review_posts';

    /**
     * Add custom cron schedules.
     */
    public static function add_custom_schedules( array $schedules ): array {
        $schedules['pqm_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Once Weekly (PQM)', 'post-quality-manager' ),
        ];
        return $schedules;
    }

    /**
     * Schedule or reschedule the cron event based on current settings.
     */
    public static function schedule(): void {
        $settings = pqm_get_settings();

        // Always clear first.
        self::clear_scheduled_events();

        if ( empty( $settings['cron_enabled'] ) ) {
            return;
        }

        $recurrence = $settings['cron_frequency'] === 'weekly' ? 'pqm_weekly' : 'daily';

        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time(), $recurrence, self::HOOK );
        }
    }

    /**
     * Clear scheduled events (called on deactivation).
     */
    public static function clear_scheduled_events(): void {
        $timestamp = wp_next_scheduled( self::HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK );
        }
    }

    /**
     * Cron callback: trigger reviews for posts that need one.
     */
    public static function run_scheduled_review(): void {
        $settings = pqm_get_settings();

        if ( empty( $settings['n8n_webhook_url'] ) ) {
            return; // Cannot run without a configured webhook.
        }

        $batch_size   = absint( $settings['cron_batch_size'] ?? 5 );
        $min_score    = absint( $settings['cron_min_score'] ?? 60 );
        $max_age_days = absint( $settings['cron_max_age_days'] ?? 30 );
        $command      = sanitize_key( $settings['cron_command'] ?? 'review' );

        $posts = PQM_DB::get_posts_needing_review( $min_score, $max_age_days, $batch_size );

        foreach ( $posts as $post_data ) {
            self::trigger_single_review( (int) $post_data['post_id'], $command, $settings );
        }
    }

    /**
     * Send a single post to n8n for review.
     */
    private static function trigger_single_review( int $post_id, string $command, array $settings ): void {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }

        $content_html  = get_post_field( 'post_content', $post_id );
        $content_plain = wp_strip_all_tags( apply_filters( 'the_content', $content_html ) );
        $yoast_active  = defined( 'WPSEO_VERSION' );

        $excerpt = has_excerpt( $post_id )
            ? wp_strip_all_tags( get_the_excerpt( $post_id ) )
            : wp_trim_words( $content_plain, 55, '...' );

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

        wp_remote_post( $settings['n8n_webhook_url'], [
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
            'timeout'  => 5,
            'blocking' => false, // Fire-and-forget for cron batches.
        ] );
    }
}
