/**
 * useSingleRun (story 5.8) — the single-run plumbing shared by Post
 * Reviews and the Dashboard queue: the list refetch trigger, the post
 * that stays busy from the click until the post-trigger refetch resolves,
 * polling (usePolling), the run toasts (requested / outcome / give-up) and
 * the last failed single-run start (`startError`, ticket 32), which the
 * page shows as an inline Notice where the user clicked — never a toast.
 *
 * The page keeps its own list request: it reruns that request whenever
 * `refreshKey` changes and calls `settlePending( refreshKey )` once the
 * request resolves or fails. `startRun()` is the one trigger path (single
 * and bulk runs); `trigger()` is a single run with its toasts.
 */
import { useCallback, useRef, useState } from '@wordpress/element';

import {
	giveUpMessage,
	requestRun,
	requestedMessage,
	settledToast,
} from './runs';
import { useToasts } from './toasts';
import usePolling from './use-polling';

/**
 * @param {Array|null} items                 Current list items (`post_id`, `in_flight`).
 * @param {string}     listKey               Identity of the query the items answer.
 * @param {Object}     callbacks             The page's own reactions.
 * @param {Function}   [callbacks.onStarted] `( postId, command )` after a run was accepted.
 * @param {Function}   [callbacks.onSettled] `( postId, command )` once a run's result landed.
 * @return {Object} `refreshKey`, `settlePending( key )`, `awaited`, `pendingPostId`, `startRun( postId, command )`, `trigger( postId, command )`, `startError` (`{ postId, command, message }` or null), `dismissStartError()`, `toasts`, `addToast( content, status )`, `removeToast( id )`.
 */
export default function useSingleRun( items, listKey, callbacks = {} ) {
	const [ refreshKey, setRefreshKey ] = useState( 0 );
	const refreshCounter = useRef( 0 );
	const [ pendingPostId, setPendingPostId ] = useState( null );
	const pendingUntilKey = useRef( null );
	const [ startError, setStartError ] = useState( null );
	const { toasts, addToast, removeToast } = useToasts();

	// Latest page callbacks without re-creating the handlers every render.
	const latest = useRef( callbacks );
	latest.current = callbacks;

	/** Refetch the list; returns the refreshKey that request carries. */
	const refetch = useCallback( () => {
		refreshCounter.current += 1;
		setRefreshKey( refreshCounter.current );
		return refreshCounter.current;
	}, [] );

	/** The list request for `key` settled: a post-trigger busy flag ends. */
	const settlePending = useCallback( ( key ) => {
		if (
			pendingUntilKey.current !== null &&
			key >= pendingUntilKey.current
		) {
			pendingUntilKey.current = null;
			setPendingPostId( null );
		}
	}, [] );

	// Completion: the row is already updated in place by the refetch (or
	// the post left the current view because its result changed it); the
	// outcome — score + status, or a failure — comes from the stored row.
	const onSettled = useCallback(
		( postId, command ) => {
			if ( latest.current.onSettled ) {
				latest.current.onSettled( postId, command );
			}
			settledToast( postId, command ).then(
				( toast ) => toast && addToast( toast.content, toast.status )
			);
		},
		[ addToast ]
	);

	const onGiveUp = useCallback(
		( postId, command ) => addToast( giveUpMessage( command ), 'info' ),
		[ addToast ]
	);

	const { awaited, restart } = usePolling( items, listKey, {
		onTick: refetch,
		onSettled,
		onGiveUp,
	} );

	// One trigger path for single and bulk runs (D4 / F6): requestRun()
	// asks the server, which writes the processing row on an n8n 2xx; a
	// success re-arms polling and refetches — rows switch to Processing.
	// Resolves `{ ok, message }`; never rejects.
	const startRun = ( postId, command ) =>
		requestRun( postId, command ).then( ( outcome ) => {
			if ( outcome.ok ) {
				restart( postId, command );
				if ( latest.current.onStarted ) {
					latest.current.onStarted( postId, command );
				}
				refetch();
			}
			return outcome;
		} );

	// Single run: no confirm; the post stays busy until the post-trigger
	// refetch resolves. A failed start is kept as `startError` (inline
	// Notice, until dismissed or the next click); success keeps its toast.
	const trigger = ( postId, command ) => {
		setPendingPostId( postId );
		setStartError( null );

		startRun( postId, command ).then( ( { ok, message } ) => {
			if ( ! ok ) {
				setStartError( { postId, command, message } );
				setPendingPostId( null );
				return;
			}
			addToast( requestedMessage( command ), 'info' );
			pendingUntilKey.current = refreshCounter.current; // the refetch startRun just issued
		} );
	};

	const dismissStartError = useCallback( () => setStartError( null ), [] );

	return {
		refreshKey,
		settlePending,
		awaited,
		pendingPostId,
		startRun,
		trigger,
		startError,
		dismissStartError,
		toasts,
		addToast,
		removeToast,
	};
}
