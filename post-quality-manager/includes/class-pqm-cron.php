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

        // Site-level plan gate: a stored paid-tier command (e.g. left
        // behind after a license lapse) must never fire on a free site.
        // Cron runs with no user, so only the plan half applies; skip the
        // whole batch before querying any posts.
        if ( ! PQM_Features::plan_allows( $command ) ) {
            return;
        }

        $posts = PQM_DB::get_posts_needing_review( $min_score, $max_age_days, $batch_size, $command );

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

        // Shared payload builder — cron batches now carry the same brand
        // voice, website context, and per-agent 'llm' fragments as manual
        // triggers, so scheduled reviews run with the configured models.
        $payload = pqm_n8n_payload( $post, $command, $settings );

        wp_remote_post( $settings['n8n_webhook_url'], [
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'body'     => wp_json_encode( $payload ),
            'timeout'  => 5,
            'blocking' => false, // Fire-and-forget for cron batches.
        ] );
    }
}
