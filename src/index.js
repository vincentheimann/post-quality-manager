/**
 * Post Reviews admin app — entry point (tracer bullet, story 5.1).
 *
 * Mounts the read-only Post Reviews table on the root div rendered by
 * PQM_Admin::render_posts_app(). React and all @wordpress/* dependencies
 * are served by WordPress core via dependency extraction — nothing is
 * bundled beyond this app's own code.
 */
import { createRoot } from '@wordpress/element';

import PostReviewsTable from './post-reviews-table';

const root = document.getElementById( 'pqm-post-reviews-root' );

if ( root ) {
	createRoot( root ).render( <PostReviewsTable /> );
}
