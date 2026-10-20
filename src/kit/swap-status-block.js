/**
 * swapStatusBlock — the one swap of the n8n Workflow page's body for React
 * callers (story 67): every reply that carries `status_html` carries the
 * whole body under the H1 (PQM_Admin::render_workflow_body()), which sits
 * outside the setup island. The swap replaces #pqm-workflow-body's content
 * and dispatches WORKFLOW_STATUS_EVENT, on which src/index.js remounts the
 * n8n connection island and pqm-admin.js binds its rows again. Used by the
 * setup wizard's steps (a ping or a provisioning).
 *
 * requestWorkflowRefresh() is for a change whose reply carries no body (an
 * adoption, the wizard's close): pqm-admin.js re-reads the body from
 * WordPress, never pinging n8n, and focuses `focus` (an element id) after
 * the swap.
 */

export const WORKFLOW_STATUS_EVENT = 'pqm:workflow-status';
export const SETUP_CLOSED_EVENT = 'pqm:setup-closed';
export const WORKFLOW_REFRESH_EVENT = 'pqm:workflow-refresh';
// Dispatched (cancelable) by pqm-admin.js before a [data-pqm-confirm] control posts (story 76).
export const CONFIRM_ACTION_EVENT = 'pqm:confirm-action';

/**
 * @param {string=} html The reply's `status_html`; nothing happens without one.
 */
export default function swapStatusBlock( html ) {
	const body = document.getElementById( 'pqm-workflow-body' );
	if ( body && html ) {
		body.innerHTML = html;
		document.dispatchEvent(
			new window.CustomEvent( WORKFLOW_STATUS_EVENT )
		);
	}
}

/**
 * @param {string}  event The event to dispatch: SETUP_CLOSED_EVENT or WORKFLOW_REFRESH_EVENT.
 * @param {string=} focus An element id of the fresh body to open and focus.
 */
export function requestWorkflowRefresh( event, focus ) {
	document.dispatchEvent(
		new window.CustomEvent( event, { detail: { focus } } )
	);
}
