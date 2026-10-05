/**
 * Admin app entry point (stories 5.1, 5.6).
 *
 * One bundle for the React pages: mounts the Post Reviews table or the
 * Dashboard on whichever root div the PHP shell rendered
 * (PQM_Admin::render_app_shell()). React and all @wordpress/* dependencies
 * are served by WordPress core via dependency extraction — nothing is
 * bundled beyond this app's own code.
 */
import { createRoot } from '@wordpress/element';

import Dashboard from './dashboard';
import PostReviewsTable from './post-reviews-table';

const pages = [
	[ 'pqm-post-reviews-root', PostReviewsTable ],
	[ 'pqm-dashboard-root', Dashboard ],
];

pages.forEach( ( [ id, Page ] ) => {
	const root = document.getElementById( id );
	if ( root ) {
		createRoot( root ).render( <Page /> );
	}
} );
