/**
 * usePolling (story 5.4, DESIGN.md D6) — the one polling loop of the
 * Post Reviews page. It watches the list's server-derived `in_flight`
 * field: while any listed post has a command in flight, one 3-second
 * interval asks the table to refetch the current query silently. Because
 * the signal is server state, not label text, the loop resumes by itself
 * after a reload, for any command and any locale.
 *
 * Completion is a server fact seen on a refetch of the *same* query: the
 * command left `in_flight` (its result row arrived), or the post left the
 * list altogether (its result changed its status / score and the current
 * filter, sort or page no longer holds it — the placeholder itself never
 * moves a post, see PQM_DB::latest_review_subquery()). Either way
 * `onSettled( postId, command )` fires once. A change of `listKey` (the
 * user navigated: filter, sort, page) resets tracking and forgets.
 *
 * Each post+command is awaited for at most three minutes; after that
 * `onGiveUp` fires and the post is no longer *actively awaited* (the
 * Details toggle returns), while the entry stays known so a stale
 * placeholder is never re-armed by every refetch. A new trigger for that
 * post calls `restart()` so the next list response arms it afresh.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

export const POLL_INTERVAL = 3000;
export const GIVE_UP_AFTER = 3 * 60 * 1000;

const keyOf = ( postId, command ) => `${ postId }:${ command }`;

function sameSet( a, b ) {
	if ( a.size !== b.size ) {
		return false;
	}
	for ( const value of a ) {
		if ( ! b.has( value ) ) {
			return false;
		}
	}
	return true;
}

/** Post ids with at least one entry still awaited (not given up). */
function awaitedPosts( tracked ) {
	const set = new Set();
	tracked.forEach( ( entry ) => {
		if ( ! entry.gaveUp ) {
			set.add( entry.postId );
		}
	} );
	return set;
}

/**
 * @param {Array|null} items    Current list items (`post_id`, `in_flight`).
 * @param {string}     listKey  Identity of the query the items answer; a change resets tracking.
 * @param {Object}     handlers `onTick()`, `onSettled( postId, command )`, `onGiveUp( postId, command )`.
 * @return {{awaited: Set<number>, restart: Function}} Actively awaited post ids; `restart( postId, command )` re-arms a key after a new trigger.
 */
export default function usePolling( items, listKey, handlers ) {
	const tracked = useRef( new Map() ); // key → { postId, command, startedAt, gaveUp }
	const trackedKey = useRef( listKey );
	const givenUp = useRef( new Set() ); // keys already given up: never re-armed by navigation or reload-less refetches
	const [ awaited, setAwaited ] = useState( () => new Set() );

	// Latest handlers without re-arming the interval on every render.
	const latest = useRef( handlers );
	latest.current = handlers;

	const publish = useCallback( () => {
		const next = awaitedPosts( tracked.current );
		setAwaited( ( prev ) => ( sameSet( prev, next ) ? prev : next ) );
	}, [] );

	// Reconcile the tracked set with every list response.
	useEffect( () => {
		if ( ! items ) {
			return;
		}

		if ( trackedKey.current !== listKey ) {
			tracked.current.clear(); // the user navigated: forget, never "complete" (given-up keys stay given up)
			trackedKey.current = listKey;
		}

		const listed = new Map(
			items.map( ( item ) => [
				item.post_id,
				new Set( item.in_flight || [] ),
			] )
		);
		const now = Date.now();
		const settled = [];

		tracked.current.forEach( ( entry, key ) => {
			const flights = listed.get( entry.postId );
			// Same query: the post left the list or the command left
			// in_flight — both mean its result row landed.
			if ( ! flights || ! flights.has( entry.command ) ) {
				tracked.current.delete( key );
				settled.push( entry );
			}
		} );

		listed.forEach( ( flights, postId ) => {
			flights.forEach( ( command ) => {
				const key = keyOf( postId, command );
				if ( ! tracked.current.has( key ) && ! givenUp.current.has( key ) ) {
					tracked.current.set( key, {
						postId,
						command,
						startedAt: now,
						gaveUp: false,
					} );
				}
			} );
		} );

		publish();

		settled.forEach( ( entry ) =>
			latest.current.onSettled( entry.postId, entry.command )
		);
	}, [ items, listKey, publish ] );

	// One interval while anything is actively awaited.
	useEffect( () => {
		if ( awaited.size === 0 ) {
			return;
		}

		const id = setInterval( () => {
			const now = Date.now();
			const expired = [];

			tracked.current.forEach( ( entry, key ) => {
				if ( ! entry.gaveUp && now - entry.startedAt > GIVE_UP_AFTER ) {
					entry.gaveUp = true;
					givenUp.current.add( key );
					expired.push( entry );
				}
			} );

			publish();
			expired.forEach( ( entry ) =>
				latest.current.onGiveUp( entry.postId, entry.command )
			);

			if ( awaitedPosts( tracked.current ).size > 0 ) {
				latest.current.onTick();
			}
		}, POLL_INTERVAL );

		return () => clearInterval( id );
	}, [ awaited, publish ] );

	// A new trigger for a known (possibly given-up) key: drop it so the
	// next list response arms it with a fresh start time.
	const restart = useCallback(
		( postId, command ) => {
			tracked.current.delete( keyOf( postId, command ) );
			givenUp.current.delete( keyOf( postId, command ) );
			publish();
		},
		[ publish ]
	);

	return { awaited, restart };
}
