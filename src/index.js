/**
 * Admin app entry point (stories 5.1, 5.6, 36).
 *
 * One bundle for the React pages: mounts the Post Reviews table or the
 * Dashboard on whichever root div the PHP shell rendered
 * (PQM_Admin::render_app_shell()), and the setup dialog and n8n Instance
 * islands on the PHP n8n Workflow page. React and all @wordpress/* dependencies
 * are served by WordPress core via dependency extraction — nothing is
 * bundled beyond this app's own code.
 */
import { createRoot } from '@wordpress/element';

import Dashboard from './dashboard';
import PostReviewsTable from './post-reviews-table';
import SetupIsland from './setup';
import N8nConnection from './n8n-connection';

const pages = [
	[ 'pqm-post-reviews-root', PostReviewsTable ],
	[ 'pqm-dashboard-root', Dashboard ],
	[ 'pqm-setup-root', SetupIsland ],
	[ 'pqm-n8n-connection-root', N8nConnection ],
];

pages.forEach( ( [ id, Page ] ) => {
	const root = document.getElementById( id );
	if ( root ) {
		createRoot( root ).render( <Page /> );
	}
} );
