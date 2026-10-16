/**
 * swapStatusBlock — swap the n8n Workflow page's status block
 * (PQM_Admin::render_workflow_status()), which sits outside the islands,
 * for the one a reply refreshed (`status_html`), then tell the islands
 * (useReimportShown()). Used by the setup wizard's steps (a ping or a
 * provisioning) and the n8n Instance section's token rotation (n8n
 * provisioning, entry 10).
 */
import { WORKFLOW_STATUS_EVENT } from './use-reimport-shown';

/**
 * @param {string=} html The reply's `status_html`; nothing happens without one.
 */
export default function swapStatusBlock( html ) {
	const block = document.querySelector( '.pqm-workflow-status' );
	if ( block && html ) {
		block.outerHTML = html;
		document.dispatchEvent(
			new window.CustomEvent( WORKFLOW_STATUS_EVENT )
		);
	}
}
