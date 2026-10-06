/**
 * Runs (story 5.6) — the one trigger + outcome source shared by Post
 * Reviews (single and bulk runs) and the Dashboard queue: the
 * POST /trigger-review request, the requested / outcome / give-up copy,
 * the REST paths both pages read, the gate and connection helpers over
 * the list's `viewer` block and the `in_flight` check. The single-run plumbing
 * around it (refresh, polling, toasts) lives in use-single-run.js.
 */
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

import { commandLabel, statusLabel } from './copy';

/** GET /posts — the Post Reviews list and the Dashboard queue. */
export const POSTS_PATH = '/post-quality/v1/posts';

/**
 * GET /reviews/{id} — every stored row of a post, newest first.
 *
 * @param {number} postId Post ID.
 * @return {string} REST path.
 */
export function reviewsPath( postId ) {
	return `/post-quality/v1/reviews/${ postId }`;
}

/**
 * Whether the viewer gets a button for `command` at all: can trigger and
 * the command is not role-hidden. A plan-gated command still renders
 * (locked, see isGated()).
 *
 * @param {Object|null} viewer  The list's `viewer` block.
 * @param {string}      command Command key.
 * @return {boolean} Whether to render the command's button.
 */
export function canRender( viewer, command ) {
	return (
		!! viewer &&
		!! viewer.can_trigger &&
		! ( viewer.hidden_commands || [] ).includes( command )
	);
}

/**
 * Whether `command` is plan-gated for the viewer (visible but locked).
 *
 * @param {Object|null} viewer  The list's `viewer` block.
 * @param {string}      command Command key.
 * @return {boolean} Whether the command is locked by the plan.
 */
export function isGated( viewer, command ) {
	return !! viewer && ( viewer.gated_commands || [] ).includes( command );
}

/**
 * Whether n8n is connected (a webhook URL is set). An older response
 * without the `connected` key reads as connected, so nothing changes.
 *
 * @param {Object|null} viewer The list's `viewer` block.
 * @return {boolean} Whether runs can start.
 */
export function isConnected( viewer ) {
	return ! viewer || viewer.connected !== false;
}

/**
 * Whether a list item has `command` running (the server's `in_flight`).
 *
 * @param {Object} item    GET /posts item.
 * @param {string} command Command key.
 * @return {boolean} Whether the command is in flight for the post.
 */
export function inFlight( item, command = 'review' ) {
	return (
		Array.isArray( item.in_flight ) && item.in_flight.includes( command )
	);
}

/**
 * Plain object (not null, not an array).
 *
 * @param {*} value Any value.
 * @return {boolean} Whether `value` is a plain object.
 */
export function isObject( value ) {
	return (
		value !== null && typeof value === 'object' && ! Array.isArray( value )
	);
}

/**
 * Null, '', [] or {} — what a failed n8n run stores as its result.
 *
 * @param {*} value Any value.
 * @return {boolean} Whether `value` is empty.
 */
export function isEmpty( value ) {
	return (
		value === null ||
		value === undefined ||
		value === '' ||
		( Array.isArray( value ) && value.length === 0 ) ||
		( isObject( value ) && Object.keys( value ).length === 0 )
	);
}

/**
 * A stored row that is a failed run — the saved `error` status, or the
 * template's fallback shape (empty status, `{}` result). Shared with the
 * detail panel.
 *
 * @param {Object|null|undefined} row Stored review row.
 * @return {boolean} Whether the row is a failed run.
 */
export function isFailedRow( row ) {
	return (
		!! row &&
		( row.status === 'error' ||
			( ! row.status && isEmpty( row.result_data ) ) )
	);
}

/**
 * Ask the server to run `command` for a post. The server enforces gate and
 * quota and writes the `processing` row on an n8n 2xx. Resolves
 * `{ ok, message }` with the server's refusal copy; never rejects.
 *
 * @param {number} postId  Post ID.
 * @param {string} command Command key, e.g. 'review'.
 * @return {Promise<{ok: boolean, message: string}>} Outcome of the request.
 */
export function requestRun( postId, command ) {
	return apiFetch( {
		path: '/post-quality/v1/trigger-review',
		method: 'POST',
		data: { post_id: postId, command },
	} ).then(
		( data ) => {
			// n8n answered non-2xx: the server's role-aware message is the
			// one wording; the fallback only covers an empty message.
			if ( data && data.success === false ) {
				return {
					ok: false,
					message:
						data.message ||
						sprintf(
							/* translators: %s: agent command label, e.g. "Review" */
							__(
								'%s could not start. Ask an administrator to check the n8n connection, then try again.',
								'post-quality-manager'
							),
							commandLabel( command )
						),
				};
			}
			return { ok: true, message: '' };
		},
		// Gate / quota / not configured / n8n down: the server's message, gently.
		( err ) => ( {
			ok: false,
			message:
				( err && err.message ) ||
				__(
					'Could not reach the site REST API. Reload the page and try again.',
					'post-quality-manager'
				),
		} )
	);
}

/**
 * Toast copy right after a run was accepted.
 *
 * @param {string} command Command key.
 * @return {string} Translated message.
 */
export function requestedMessage( command ) {
	return sprintf(
		/* translators: %s: agent command label, e.g. "Review" */
		__(
			'%s requested — running in the background, this page stays usable.',
			'post-quality-manager'
		),
		commandLabel( command )
	);
}

/**
 * Toast copy when polling gives up on a run (it may still finish later).
 *
 * @param {string} command Command key.
 * @return {string} Translated message.
 */
export function giveUpMessage( command ) {
	return sprintf(
		/* translators: %s: agent command label, e.g. "Review" */
		__(
			'%s is still running in n8n — results will appear when it finishes.',
			'post-quality-manager'
		),
		commandLabel( command )
	);
}

/**
 * Toast for a run that just left `in_flight`, from its stored row.
 *
 * @param {string}      command Command key.
 * @param {Object|null} row     Latest stored row for the command, null when unknown.
 * @return {{status: string, content: string}} SnackbarList notice fields.
 */
function outcomeToast( command, row ) {
	const label = commandLabel( command );

	if ( ! row ) {
		return {
			status: 'info',
			content: sprintf(
				/* translators: %s: agent command label, e.g. "Review" */
				__(
					"%s finished — the result is in the post's Details on Post Reviews.",
					'post-quality-manager'
				),
				label
			),
		};
	}

	if ( isFailedRow( row ) ) {
		return {
			status: 'warning',
			content: sprintf(
				/* translators: %s: agent command label, e.g. "Review" */
				__(
					'%s could not complete. Check the n8n execution log, then try again.',
					'post-quality-manager'
				),
				label
			),
		};
	}

	if ( command === 'rewrite' ) {
		return {
			status: 'success',
			content: __(
				'Rewrite complete — the post is saved as Pending Review.',
				'post-quality-manager'
			),
		};
	}

	if ( row.overall_score > 0 ) {
		return {
			status: 'success',
			content: sprintf(
				/* translators: 1: agent command label, e.g. "Review"; 2: score 0–100; 3: status label, e.g. "Ready to ship" */
				__(
					'%1$s complete — score %2$d, %3$s.',
					'post-quality-manager'
				),
				label,
				row.overall_score,
				statusLabel( row.status )
			),
		};
	}

	return {
		status: 'success',
		content: sprintf(
			/* translators: %s: agent command label, e.g. "Takeaways" */
			__( '%s complete.', 'post-quality-manager' ),
			label
		),
	};
}

/**
 * Outcome toast for a settled run: reads the post's stored rows and builds
 * the toast from the newest row of `command`. Resolves null when that row
 * is still the `processing` placeholder — the post only left the polled
 * list (pushed out of the queue or off a page), the run is not done.
 * Never rejects.
 *
 * @param {number} postId  Post ID.
 * @param {string} command Command key.
 * @return {Promise<{status: string, content: string}|null>} SnackbarList notice fields, or null for no toast.
 */
export function settledToast( postId, command ) {
	return apiFetch( { path: reviewsPath( postId ) } ).then(
		( rows ) => {
			const row = ( Array.isArray( rows ) ? rows : [] ).find(
				( r ) => r.command === command
			);
			return row && row.status === 'processing'
				? null
				: outcomeToast( command, row );
		},
		() => outcomeToast( command, null )
	);
}
