<?php
/**
 * Insert into Post (story 23): writes the latest Takeaways / Sources items
 * into the post. The site owner designs the presentation as a pattern in
 * Appearance → Patterns (Settings → Agent-Specific Overrides → Pattern);
 * the plugin copies it inline, puts the items into its first List and
 * leaves everything else as designed. No pattern chosen (or it is gone):
 * a plain List. Takeaways go first, Sources last; a re-insert replaces the
 * earlier copy. Blocks are built as parsed-block arrays and written with
 * WordPress's own serializer — no AI call, no quota, no History row. The
 * previous version stays in WP revisions.
 *
 * Matching: every top-level block of an inserted copy carries
 * `metadata.pqmInsert` ("takeaways" / "sources") in its comment attributes,
 * so the pattern's markup is untouched. Without a marked block, a hand-made
 * copy is recognised at the edge (first blocks for Takeaways, last for
 * Sources): unmarked blocks named like the copy's top-level blocks, in
 * order, the first one with the pattern's first block name (metadata.name).
 *
 * Known ceilings: only top-level blocks are matched. The hand-made fallback
 * needs the pattern's first block to have a block name (the editor's
 * Advanced panel). Theme- and plugin-registered patterns are not listed.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PQM_Blocks {

    /** Where each command's items live in its stored result (n8n template's Parse nodes). */
    const ITEM_KEYS = [
        'takeaways' => 'rewritten_items',
        'sources'   => 'rewritten_sources',
    ];

    /** Rows read to find the command's latest — the detail panel's window (GET /reviews). */
    const ROWS_LIMIT = 50;

    /** The "Plain list" copy: an empty `core/list` as the editor saves it. */
    const PLAIN_LIST = [
        'blockName'    => 'core/list',
        'attrs'        => [],
        'innerBlocks'  => [],
        'innerHTML'    => '<ul class="wp-block-list"></ul>',
        'innerContent' => [ '<ul class="wp-block-list"></ul>' ],
    ];

    /**
     * Write the latest stored items of $command into the post.
     * Permission and feature gate are checked by the REST route.
     *
     * @return true|WP_Error True once saved; 409 when there is nothing to insert.
     */
    public static function insert( int $post_id, string $command ): bool|WP_Error {
        $items = self::latest_items( $post_id, $command );
        $post  = get_post( $post_id );

        if ( ! $items || ! $post ) {
            return new WP_Error(
                'pqm_nothing_to_insert',
                sprintf(
                    /* translators: %s: agent command label, e.g. "Takeaways" */
                    __( 'Run %s first, then insert.', 'post-quality-manager' ),
                    self::label( $command )
                ),
                [ 'status' => 409 ]
            );
        }

        // The Pattern setting sits in the Pro-locked Overrides: free sites insert a plain list.
        // A pattern that was deleted, unpublished or lost its List falls back the same way.
        $id   = (int) ( pqm_get_settings()['agent_prompts'][ $command ]['pattern'] ?? 0 );
        $copy = $id && PQM_Features::is_pro() && isset( self::patterns()[ $id ] )
            ? parse_blocks( (string) get_post( $id )->post_content )
            : [];

        $blocks = self::place( parse_blocks( $post->post_content ), $items, $copy, $command );

        // wp_update_post() expects slashed data; status is kept, a revision is saved.
        $updated = wp_update_post(
            wp_slash( [ 'ID' => $post_id, 'post_content' => serialize_blocks( $blocks ) ] ),
            true
        );

        return is_wp_error( $updated ) ? $updated : true;
    }

    /**
     * The site's user patterns that can take the items: published `wp_block`
     * posts holding a `core/list` at any depth. Single source for the Pattern
     * dropdown, its sanitizer and insert().
     *
     * @return array<int, string> Pattern ID => title, by title.
     */
    public static function patterns(): array {
        $patterns = [];
        $posts    = get_posts( [
            'post_type'   => 'wp_block',
            'post_status' => 'publish',
            'orderby'     => 'title',
            'order'       => 'ASC',
            'numberposts' => -1, // No cap: this list also gates insert() and the sanitizer.
        ] );

        foreach ( $posts as $pattern ) {
            if ( self::has_list( parse_blocks( (string) $pattern->post_content ) ) ) {
                $patterns[ (int) $pattern->ID ] = (string) $pattern->post_title;
            }
        }

        return $patterns;
    }

    /** Translatable agent label for this class's copy and the route's success message. */
    public static function label( string $command ): string {
        return 'sources' === $command
            ? __( 'Sources', 'post-quality-manager' )
            : __( 'Takeaways', 'post-quality-manager' );
    }

    /**
     * Build the copy for $items and place it in $blocks (parsed, top level).
     *
     * @param array  $blocks  parse_blocks() output of the post.
     * @param array  $items   Plain-text items, non-empty.
     * @param array  $copy    The pattern's parse_blocks() output, or [] for a plain list.
     * @param string $command 'takeaways' (first) or 'sources' (last).
     * @return array Blocks with the new or replaced copy.
     */
    public static function place( array $blocks, array $items, array $copy, string $command ): array {
        $blocks = array_values( $blocks );
        $copy   = array_values( array_filter( $copy, static fn( array $block ) => ! self::is_gap( $block ) ) );
        $inner  = self::list_items( $items );

        // The first List gets the items; a copy without one becomes a plain list.
        $new = self::fill( $copy, $inner ) ?? self::fill( [ self::PLAIN_LIST ], $inner );
        foreach ( $new as $i => $block ) {
            $new[ $i ]['attrs']['metadata']['pqmInsert'] = $command;
        }

        $run = self::marked_run( $blocks, $command ) ?? self::signature_run( $blocks, $new, 'sources' === $command );

        if ( null !== $run ) {
            // Stray parts of an earlier copy elsewhere in the post go too.
            $keep = static fn( array $block ) => self::marker( $block ) !== $command;

            return [
                ...array_filter( array_slice( $blocks, 0, $run[0] ), $keep ),
                ...$new,
                ...array_filter( array_slice( $blocks, $run[1] + 1 ), $keep ),
            ];
        }

        if ( ! $blocks ) {
            return $new;
        }

        // Block whitespace = a freeform (null-name) block beside the new copy.
        $gap = [ 'blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "\n\n", 'innerContent' => [ "\n\n" ] ];

        return 'sources' === $command ? [ ...$blocks, $gap, ...$new ] : [ ...$new, $gap, ...$blocks ];
    }

    /**
     * Newest stored row of $command for the post → its trimmed, non-empty items.
     */
    private static function latest_items( int $post_id, string $command ): array {
        foreach ( PQM_DB::get_reviews_for_post( $post_id, self::ROWS_LIMIT ) as $row ) {
            if ( $row['command'] !== $command ) {
                continue;
            }

            $data  = json_decode( (string) $row['result_data'], true );
            $items = is_array( $data ) ? ( $data[ self::ITEM_KEYS[ $command ] ] ?? [] ) : [];
            if ( ! is_array( $items ) ) {
                return [];
            }

            $items = array_map( static fn( $item ) => is_string( $item ) || is_int( $item ) || is_float( $item ) ? trim( (string) $item ) : '', $items );

            return array_values( array_filter( $items, static fn( string $item ) => '' !== $item ) );
        }

        return [];
    }

    /** One `core/list-item` per item, text escaped. */
    private static function list_items( array $items ): array {
        $inner = [];
        foreach ( $items as $item ) {
            $li      = '<li>' . esc_html( $item ) . '</li>';
            $inner[] = [ 'blockName' => 'core/list-item', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => $li, 'innerContent' => [ $li ] ];
        }

        return $inner;
    }

    /** Whether $blocks holds a `core/list` at any depth. */
    private static function has_list( array $blocks ): bool {
        foreach ( $blocks as $block ) {
            if ( 'core/list' === $block['blockName'] || self::has_list( $block['innerBlocks'] ?? [] ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * $blocks with the first `core/list` (depth-first, document order)
     * refilled with $inner, or null when there is none.
     */
    private static function fill( array $blocks, array $inner ): ?array {
        foreach ( $blocks as $i => $block ) {
            if ( 'core/list' === $block['blockName'] ) {
                $blocks[ $i ] = self::refill( $block, $inner );

                return $blocks;
            }

            $filled = self::fill( $block['innerBlocks'] ?? [], $inner );
            if ( null !== $filled ) {
                // Same number of inner blocks: the parent's innerContent still lines up.
                $blocks[ $i ]['innerBlocks'] = $filled;

                return $blocks;
            }
        }

        return null;
    }

    /**
     * An existing block with $inner as its inner blocks; its attrs and
     * wrapper markup (the HTML around its inner blocks) are kept.
     */
    private static function refill( array $existing, array $inner ): array {
        $chunks = (array) ( $existing['innerContent'] ?? [] );
        $nulls  = array_keys( $chunks, null, true );

        if ( $nulls ) {
            $open  = implode( '', array_slice( $chunks, 0, $nulls[0] ) );
            $close = implode( '', array_slice( $chunks, end( $nulls ) + 1 ) );
        } else {
            // No inner blocks yet: they go before the wrapper's last closing tag.
            $html  = (string) ( $existing['innerHTML'] ?? '' );
            $at    = strrpos( $html, '</' );
            $open  = false === $at ? $html : substr( $html, 0, $at );
            $close = false === $at ? '' : substr( $html, $at );
        }

        $existing['innerBlocks']  = $inner;
        $existing['innerHTML']    = $open . $close;
        $existing['innerContent'] = [ $open, ...array_fill( 0, count( $inner ), null ), $close ];

        return $existing;
    }

    /** The command a top-level block was inserted for ('' when none). */
    private static function marker( array $block ): string {
        $marker = $block['attrs']['metadata']['pqmInsert'] ?? '';

        return is_string( $marker ) ? $marker : '';
    }

    /** Block whitespace: a null-name block with only whitespace. */
    private static function is_gap( array $block ): bool {
        return null === $block['blockName'] && '' === trim( (string) ( $block['innerHTML'] ?? '' ) );
    }

    /**
     * [first, last] index of the earlier copy: from the first block marked
     * for $command through the marked blocks and whitespace that follow it.
     */
    private static function marked_run( array $blocks, string $command ): ?array {
        $run = null;
        foreach ( $blocks as $i => $block ) {
            if ( self::marker( $block ) === $command ) {
                $run = [ $run[0] ?? $i, $i ];
            } elseif ( null !== $run && ! self::is_gap( $block ) ) {
                break;
            }
        }

        return $run;
    }

    /**
     * [first, last] index of a hand-made copy at the edge: unmarked blocks
     * named like $new's top-level blocks, in order, the first one carrying
     * the same non-empty metadata.name as the pattern's first block.
     */
    private static function signature_run( array $blocks, array $new, bool $last ): ?array {
        $name = $new[0]['attrs']['metadata']['name'] ?? '';
        if ( ! is_string( $name ) || '' === $name ) {
            return null;
        }

        $solid = array_keys( array_filter( $blocks, static fn( array $block ) => ! self::is_gap( $block ) ) );
        if ( count( $solid ) < count( $new ) ) {
            return null;
        }

        $run = $last ? array_slice( $solid, -count( $new ) ) : array_slice( $solid, 0, count( $new ) );
        foreach ( $run as $k => $i ) {
            if ( $blocks[ $i ]['blockName'] !== $new[ $k ]['blockName'] || '' !== self::marker( $blocks[ $i ] ) ) {
                return null;
            }
        }

        return ( $blocks[ $run[0] ]['attrs']['metadata']['name'] ?? '' ) === $name ? [ $run[0], end( $run ) ] : null;
    }
}
