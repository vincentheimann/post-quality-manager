<?php
/**
 * Database CRUD operations for the pqm_reviews table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_DB {

    /**
     * Get the full table name.
     */
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'pqm_reviews';
    }

    /**
     * Insert a new review result.
     *
     * @return int|false  Inserted row ID or false on failure.
     */
    public static function save_review( int $post_id, string $command, int $score, string $status, array $result, string $reviewed_at = '' ): int|false {
        global $wpdb;

        if ( empty( $reviewed_at ) ) {
            $reviewed_at = current_time( 'mysql', true );
        }

        $inserted = $wpdb->insert(
            self::table_name(),
            [
                'post_id'      => $post_id,
                'command'      => sanitize_key( $command ),
                'overall_score'=> absint( $score ),
                'status'       => sanitize_text_field( $status ),
                'result_data'  => wp_json_encode( $result ),
                'reviewed_at'  => $reviewed_at,
                'created_at'   => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%d', '%s', '%s', '%s', '%s' ]
        );

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /**
     * Get all reviews for a post, newest first.
     */
    public static function get_reviews_for_post( int $post_id, int $limit = 20 ): array {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE post_id = %d ORDER BY reviewed_at DESC LIMIT %d",
                $post_id,
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get which commands have been executed for a batch of post IDs.
     *
     * @param  int[] $post_ids
     * @return array  Keyed by post_id → array of command names.
     */
    public static function get_commands_for_posts( array $post_ids ): array {
        if ( empty( $post_ids ) ) {
            return [];
        }

        global $wpdb;
        $table = self::table_name();

        $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
        $sql          = "SELECT post_id, command FROM {$table} WHERE post_id IN ($placeholders) GROUP BY post_id, command";
        $rows         = $wpdb->get_results(
            $wpdb->prepare( $sql, ...$post_ids ), // phpcs:ignore WordPress.DB.PreparedSQL
            ARRAY_A
        ) ?: [];

        $map = [];
        foreach ( $rows as $row ) {
            $map[ (int) $row['post_id'] ][] = $row['command'];
        }

        return $map;
    }

    /**
     * Get the most recent review for a post + command.
     */
    public static function get_latest_review( int $post_id, string $command = 'review' ): ?array {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE post_id = %d AND command = %s ORDER BY reviewed_at DESC LIMIT 1",
                $post_id,
                $command
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get paginated list of posts with their latest review scores.
     *
     * @param array $args {
     *     @type int    $per_page   Default 20.
     *     @type int    $page       Default 1.
     *     @type string $orderby    'score', 'reviewed_at', 'title'. Default 'reviewed_at'.
     *     @type string $order      'ASC' or 'DESC'. Default 'DESC'.
     *     @type string $status     Filter by review status.
     *     @type string $filter     'needs_review' or 'all'.
     * }
     * @return array { items: array, total: int, pages: int }
     */
    public static function get_all_posts_with_scores( array $args = [] ): array {
        global $wpdb;

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'reviewed_at',
            'order'    => 'DESC',
            'status'   => '',
            'filter'   => 'all',
        ];
        $args  = wp_parse_args( $args, $defaults );
        $table = self::table_name();

        // Subquery: latest review per post.
        $latest_subquery = "
            SELECT r1.*
            FROM {$table} r1
            INNER JOIN (
                SELECT post_id, MAX(reviewed_at) AS max_date
                FROM {$table}
                WHERE command = 'review'
                GROUP BY post_id
            ) r2 ON r1.post_id = r2.post_id AND r1.reviewed_at = r2.max_date
        ";

        // Get all published and pending posts with optional LEFT JOIN to reviews.
        $where_clauses = [ "p.post_status IN ('publish', 'pending')", "p.post_type = 'post'" ];
        $having_clauses = [];

        if ( $args['filter'] === 'needs_review' ) {
            $having_clauses[] = 'latest_score IS NULL OR latest_score < 71';
        }

        if ( ! empty( $args['status'] ) ) {
            $where_clauses[] = $wpdb->prepare( 'lr.status = %s', $args['status'] );
        }

        $where_sql  = implode( ' AND ', $where_clauses );
        $having_sql = ! empty( $having_clauses ) ? 'HAVING ' . implode( ' AND ', $having_clauses ) : '';

        // Map orderby to SQL columns.
        $order_map = [
            'score'       => 'latest_score',
            'reviewed_at' => 'latest_reviewed',
            'title'       => 'p.post_title',
        ];
        $order_col = $order_map[ $args['orderby'] ] ?? 'latest_reviewed';
        $order_dir = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

        // Count total.
        $count_sql = "
            SELECT COUNT(*) FROM (
                SELECT p.ID,
                    lr.overall_score AS latest_score
                FROM {$wpdb->posts} p
                LEFT JOIN ( {$latest_subquery} ) lr ON p.ID = lr.post_id
                WHERE {$where_sql}
                GROUP BY p.ID
                {$having_sql}
            ) AS counted
        ";
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL

        $pages  = (int) ceil( $total / $args['per_page'] );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        // Fetch items.
        $items_sql = "
            SELECT p.ID AS post_id,
                   p.post_title AS title,
                   p.post_name AS slug,
                   p.post_type,
                   p.post_status AS wp_post_status,
                   lr.overall_score AS latest_score,
                   lr.status AS latest_status,
                   lr.reviewed_at AS latest_reviewed,
                   lr.command AS latest_command
            FROM {$wpdb->posts} p
            LEFT JOIN ( {$latest_subquery} ) lr ON p.ID = lr.post_id
            WHERE {$where_sql}
            GROUP BY p.ID
            {$having_sql}
            ORDER BY {$order_col} {$order_dir}
            LIMIT %d OFFSET %d
        ";

        $items = $wpdb->get_results(
            $wpdb->prepare( $items_sql, $args['per_page'], $offset ), // phpcs:ignore WordPress.DB.PreparedSQL
            ARRAY_A
        ) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Get posts that need a review (never reviewed, low score, or stale).
     */
    public static function get_posts_needing_review( int $min_score = 60, int $max_age_days = 30, int $limit = 10 ): array {
        global $wpdb;
        $table    = self::table_name();
        $cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( $max_age_days * DAY_IN_SECONDS ) );

        $sql = "
            SELECT p.ID AS post_id, p.post_title AS title, p.post_name AS slug, p.post_type,
                   lr.overall_score, lr.reviewed_at
            FROM {$wpdb->posts} p
            LEFT JOIN (
                SELECT r1.post_id, r1.overall_score, r1.reviewed_at
                FROM {$table} r1
                INNER JOIN (
                    SELECT post_id, MAX(reviewed_at) AS max_date
                    FROM {$table}
                    WHERE command = 'review'
                    GROUP BY post_id
                ) r2 ON r1.post_id = r2.post_id AND r1.reviewed_at = r2.max_date
            ) lr ON p.ID = lr.post_id
            WHERE p.post_status = 'publish'
              AND p.post_type = 'post'
              AND (
                  lr.post_id IS NULL
                  OR lr.overall_score < %d
                  OR lr.reviewed_at < %s
              )
            ORDER BY lr.reviewed_at ASC, p.post_date DESC
            LIMIT %d
        ";

        return $wpdb->get_results(
            $wpdb->prepare( $sql, $min_score, $cutoff, $limit ), // phpcs:ignore WordPress.DB.PreparedSQL
            ARRAY_A
        ) ?: [];
    }

    /**
     * Dashboard aggregate stats.
     */
    public static function get_dashboard_stats(): array {
        global $wpdb;
        $table = self::table_name();

        // Total published and pending posts.
        $total_posts = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish', 'pending') AND post_type = 'post'"
        );

        // Posts with at least one review.
        $reviewed = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$table}"
        );

        // Average latest score (for review command).
        $avg_score = (float) $wpdb->get_var(
            "SELECT AVG(latest_score) FROM (
                SELECT r1.overall_score AS latest_score
                FROM {$table} r1
                INNER JOIN (
                    SELECT post_id, MAX(reviewed_at) AS max_date
                    FROM {$table}
                    WHERE command = 'review'
                    GROUP BY post_id
                ) r2 ON r1.post_id = r2.post_id AND r1.reviewed_at = r2.max_date
            ) AS t"
        );

        // Status distribution.
        $status_counts = $wpdb->get_results(
            "SELECT lr.status, COUNT(*) AS cnt FROM (
                SELECT r1.post_id, r1.status
                FROM {$table} r1
                INNER JOIN (
                    SELECT post_id, MAX(reviewed_at) AS max_date
                    FROM {$table}
                    WHERE command = 'review'
                    GROUP BY post_id
                ) r2 ON r1.post_id = r2.post_id AND r1.reviewed_at = r2.max_date
            ) lr GROUP BY lr.status",
            ARRAY_A
        ) ?: [];

        $distribution = [];
        foreach ( $status_counts as $row ) {
            $distribution[ $row['status'] ] = (int) $row['cnt'];
        }

        // Last 10 reviews.
        $recent = $wpdb->get_results(
            "SELECT r.*, p.post_title
             FROM {$table} r
             INNER JOIN {$wpdb->posts} p ON r.post_id = p.ID
             ORDER BY r.reviewed_at DESC
             LIMIT 10",
            ARRAY_A
        ) ?: [];

        return [
            'total_posts'  => $total_posts,
            'reviewed'     => $reviewed,
            'not_reviewed' => $total_posts - $reviewed,
            'avg_score'    => round( $avg_score, 1 ),
            'distribution' => $distribution,
            'recent'       => $recent,
        ];
    }
}
