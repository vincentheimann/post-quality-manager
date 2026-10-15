/**
 * useReimportShown (plan-update-notice-primary-action) — whether the n8n
 * Workflow page's status block shows the update notice's **Re-import the
 * workflow** button (PQM_Admin::render_workflow_status()). That button is
 * then the page's one primary action, so the islands turn their own
 * buttons secondary. The rendered block is the one source of truth: it is
 * read at first render (the PHP block is in the page before the islands
 * mount, so there is no flash) and again on each `pqm:workflow-status`
 * event, which every swap of the block dispatches (pqm-admin.js'
 * swapWorkflowStatus(), the setup dialog's swapStatusBlock()).
 */
import { useEffect, useState } from '@wordpress/element';

export const WORKFLOW_STATUS_EVENT = 'pqm:workflow-status';

const shown = () =>
	!! document.querySelector( '.pqm-workflow-status .pqm-reimport' );

/**
 * @return {boolean} The status block shows the re-import button.
 */
export default function useReimportShown() {
	const [ value, setValue ] = useState( shown );

	useEffect( () => {
		const update = () => setValue( shown() );
		document.addEventListener( WORKFLOW_STATUS_EVENT, update );
		return () =>
			document.removeEventListener( WORKFLOW_STATUS_EVENT, update );
	}, [] );

	return value;
}
