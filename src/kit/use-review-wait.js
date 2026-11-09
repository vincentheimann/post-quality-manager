/**
 * useReviewWait (story 109, moved from src/setup/first-review.js) — waits
 * for one post's Review: while `waiting`, it polls GET /reviews/{id} at
 * the kit's pace until the post's newest Review row leaves `processing`,
 * then calls `onSettled( row )` once with that row (done or failed: the
 * caller reads it). It gives up GIVE_UP_AFTER after the run started —
 * counted from the `processing` row's `reviewed_at`, so a run that started
 * long ago gives up at the first poll instead of waiting afresh (story 2,
 * goal b) — and calls `onGiveUp()`. A missed poll is retried on the next
 * tick. Shared by the setup dialog's first review and the post editor's
 * meta box; Post Reviews and the Dashboard watch a list instead
 * (use-polling.js).
 */
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useRef } from '@wordpress/element';

import { reviewsPath } from './runs';
import { GIVE_UP_AFTER, POLL_INTERVAL } from './use-polling';

/**
 * When a run started, in ms, from its `processing` row's `reviewed_at`
 * (GMT `Y-m-d H:i:s`); NaN for a blank or zero date.
 *
 * Ceiling: the start comes from the server's clock, the limit from the
 * browser's; a skew of minutes shortens or lengthens the wait by as much.
 * Upgrade path: a server-computed age on the row, if a site reports it.
 *
 * @param {Object} row Review row.
 * @return {number} Start time in ms, or NaN.
 */
function runStart( row ) {
	const at = typeof row.reviewed_at === 'string' ? row.reviewed_at : '';
	return at ? Date.parse( `${ at.replace( ' ', 'T' ) }Z` ) : NaN;
}

/**
 * @param {number}  postId   Post whose Review to wait for.
 * @param {boolean} waiting  Whether to wait now; turning it off stops the polls.
 * @param {Object}  handlers `onSettled( row )` with the newest Review row once it left `processing`; `onGiveUp()` when the limit passed.
 */
export default function useReviewWait( postId, waiting, handlers ) {
	// Latest handlers without restarting the polling effect.
	const latest = useRef( handlers );
	latest.current = handlers;

	useEffect( () => {
		if ( ! waiting ) {
			return undefined;
		}
		// The mount time until the run's own start is read from its
		// `processing` row (once, at the first poll that sees it).
		let startedAt = Date.now();
		let startRead = false;
		let stopped = false;

		const expired = () => Date.now() - startedAt > GIVE_UP_AFTER;
		const giveUp = () => {
			stopped = true;
			clearInterval( id );
			latest.current.onGiveUp();
		};

		const id = setInterval( () => {
			if ( expired() ) {
				giveUp();
				return;
			}
			apiFetch( { path: reviewsPath( postId ) } ).then(
				( rows ) => {
					const row = ( Array.isArray( rows ) ? rows : [] ).find(
						( r ) => r.command === 'review'
					);
					if ( stopped || ! row ) {
						return;
					}
					if ( row.status === 'processing' ) {
						if ( ! startRead ) {
							startRead = true;
							const at = runStart( row );
							startedAt = Number.isNaN( at ) ? startedAt : at;
						}
						// A run that started long ago gives up at once.
						if ( expired() ) {
							giveUp();
						}
						return;
					}
					stopped = true;
					clearInterval( id );
					latest.current.onSettled( row );
				},
				() => {} // A missed poll is retried on the next tick.
			);
		}, POLL_INTERVAL );

		return () => {
			stopped = true;
			clearInterval( id );
		};
	}, [ waiting, postId ] );
}
