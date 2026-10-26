/**
 * Admin app entry point (stories 5.1, 5.6, 36, 67).
 *
 * One bundle for the React pages: mounts the Post Reviews table or the
 * Dashboard on whichever root div the PHP shell rendered
 * (PostQuality_Admin::render_app_shell()), and the setup dialog and n8n connection
 * islands on the PHP n8n Workflow page. The n8n connection island sits
 * inside the page's body (#pqm-workflow-body), which every reply swaps
 * whole (story 67): it is mounted at load and again on every
 * postquality:workflow-status, on the fresh root node, reading its state anew.
 * React and all @wordpress/* dependencies are served by WordPress core via
 * dependency extraction — nothing is bundled beyond this app's own code.
 */
import { createRoot } from '@wordpress/element';

import Dashboard from './dashboard';
import PostReviewsTable from './post-reviews-table';
import SetupIsland from './setup';
import N8nConnection from './n8n-connection';
import { WORKFLOW_STATUS_EVENT } from './kit/swap-status-block';

const pages = [
	[ 'pqm-post-reviews-root', PostReviewsTable ],
	[ 'pqm-dashboard-root', Dashboard ],
	[ 'pqm-setup-root', SetupIsland ],
];

pages.forEach( ( [ id, Page ] ) => {
	const root = document.getElementById( id );
	if ( root ) {
		createRoot( root ).render( <Page /> );
	}
} );

// On the page, every island button is secondary: the status block holds
// the one primary action.
let connectionRoot = null;
function mountConnection() {
	if ( connectionRoot ) {
		connectionRoot.unmount();
		connectionRoot = null;
	}
	const node = document.getElementById( 'pqm-n8n-connection-root' );
	if ( node ) {
		connectionRoot = createRoot( node );
		connectionRoot.render( <N8nConnection variant="secondary" /> );
	}
}
mountConnection();
document.addEventListener( WORKFLOW_STATUS_EVENT, mountConnection );
