/**
 * FirstReview (story 36) — the setup's last step: pick a published post,
 * run its review from the dialog and wait for the result. The run goes
 * through the one trigger path (requestRun(), POST /trigger-review: gate,
 * quota, the `processing` row); the wait polls GET /reviews/{id} at the
 * kit's pace until the post's newest review row leaves `processing`, and
 * gives up after the kit's three minutes. A scored result calls
 * `onComplete` (POST /setup { complete: true }); a failed run says where
 * to look and leaves the step open.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { statusLabel } from '../kit/copy';
import {
	POSTS_PATH,
	giveUpMessage,
	inFlight,
	isFailedRow,
	requestRun,
	reviewsPath,
} from '../kit/runs';
import ScoreBadge from '../kit/score-badge';
import { GIVE_UP_AFTER, POLL_INTERVAL } from '../kit/use-polling';

// One page is plenty to pick a post from; the title order makes it findable.
const PICK_PATH = addQueryArgs( POSTS_PATH, {
	per_page: 100,
	orderby: 'title',
	order: 'asc',
} );

/**
 * The stored failure text of a failed run (`result_data.error`, written by
 * save-review from the template's error message, bug 31), followed by a
 * space; '' for the template's empty fallback, which carries none.
 *
 * @param {Object|null} row Failed review row.
 * @return {string} Failure text and a trailing space, or ''.
 */
function failureText( row ) {
	const error = row && row.result_data && row.result_data.error;
	return typeof error === 'string' && error ? `${ error } ` : '';
}

/**
 * @param {Object}   props
 * @param {boolean}  props.connected  Whether a webhook URL is stored.
 * @param {Function} props.onComplete Called once with the scored row.
 * @return {Element} The first-review step body.
 */
export default function FirstReview( { connected, onComplete } ) {
	const [ posts, setPosts ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ postId, setPostId ] = useState( 0 );
	// idle → starting → waiting → done | failed | gaveup
	const [ phase, setPhase ] = useState( 'idle' );
	const [ startError, setStartError ] = useState( '' );
	const [ row, setRow ] = useState( null );

	// Latest callback without restarting the polling effect.
	const completed = useRef( onComplete );
	completed.current = onComplete;

	useEffect( () => {
		if ( ! connected ) {
			return;
		}
		apiFetch( { path: PICK_PATH } ).then(
			( data ) => {
				const published = ( data.items || [] ).filter(
					( item ) => item.wp_post_status === 'publish'
				);
				setPosts( published );
				// A review already running (started before the dialog was
				// closed or left): pick its post and keep waiting, so Run
				// stays off and no second paid run starts.
				const inProgress = published.find( ( item ) =>
					inFlight( item, 'review' )
				);
				if ( inProgress ) {
					setPostId( inProgress.post_id );
					setPhase( 'waiting' );
					return;
				}
				setPostId( published.length ? published[ 0 ].post_id : 0 );
			},
			( err ) => {
				setPosts( [] );
				setLoadError(
					( err && err.message ) ||
						__(
							'Could not load your posts. Reload the page and try again.',
							'post-quality-manager'
						)
				);
			}
		);
	}, [ connected ] );

	useEffect( () => {
		if ( phase !== 'waiting' ) {
			return undefined;
		}
		const startedAt = Date.now();
		let stopped = false;

		const id = setInterval( () => {
			if ( Date.now() - startedAt > GIVE_UP_AFTER ) {
				clearInterval( id );
				setPhase( 'gaveup' );
				return;
			}
			apiFetch( { path: reviewsPath( postId ) } ).then(
				( rows ) => {
					const latest = ( Array.isArray( rows ) ? rows : [] ).find(
						( r ) => r.command === 'review'
					);
					if (
						stopped ||
						! latest ||
						latest.status === 'processing'
					) {
						return;
					}
					stopped = true;
					clearInterval( id );
					setRow( latest );
					if ( isFailedRow( latest ) ) {
						setPhase( 'failed' );
						return;
					}
					setPhase( 'done' );
					completed.current( latest );
				},
				() => {} // A missed poll is retried on the next tick.
			);
		}, POLL_INTERVAL );

		return () => {
			stopped = true;
			clearInterval( id );
		};
	}, [ phase, postId ] );

	if ( ! connected ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Save the production URL in step 6 first — the review is sent to it.',
					'post-quality-manager'
				) }
			</Notice>
		);
	}

	if ( posts === null ) {
		return <Spinner />;
	}

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	if ( posts.length === 0 ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __(
					'No published posts yet. Publish a post, then come back to run its first review.',
					'post-quality-manager'
				) }
			</Notice>
		);
	}

	const running = phase === 'starting' || phase === 'waiting';

	const run = () => {
		setPhase( 'starting' );
		setStartError( '' );
		setRow( null );
		requestRun( postId, 'review' ).then( ( { ok, message } ) => {
			setPhase( ok ? 'waiting' : 'idle' );
			setStartError( ok ? '' : message );
		} );
	};

	return (
		<>
			<p>
				{ __(
					'Pick a published post and run its review. It usually takes a minute or two; this dialog waits for the score.',
					'post-quality-manager'
				) }
			</p>
			<div className="pqm-setup-run">
				<SelectControl
					label={ __( 'Post', 'post-quality-manager' ) }
					value={ String( postId ) }
					options={ posts.map( ( item ) => ( {
						value: String( item.post_id ),
						label: item.title,
					} ) ) }
					onChange={ ( value ) => setPostId( Number( value ) ) }
					disabled={ running }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					onClick={ run }
					disabled={ running || ! postId }
					isBusy={ running }
				>
					{ __( 'Run Review', 'post-quality-manager' ) }
				</Button>
			</div>

			{ running && (
				<p className="pqm-setup-waiting" role="status">
					<Spinner />
					{ __( 'Review running in n8n…', 'post-quality-manager' ) }
				</p>
			) }

			{ startError && (
				<Notice status="error" isDismissible={ false }>
					{ startError }
				</Notice>
			) }

			{ phase === 'done' && row && (
				<p className="pqm-setup-score" role="status">
					<ScoreBadge score={ row.overall_score } />{ ' ' }
					{ sprintf(
						/* translators: 1: score 0–100; 2: status label, e.g. "Needs revision" */
						__(
							'Review complete — score %1$d, %2$s. The full result is in Post Reviews.',
							'post-quality-manager'
						),
						row.overall_score,
						statusLabel( row.status )
					) }
				</p>
			) }

			{ phase === 'failed' && (
				<Notice status="error" isDismissible={ false }>
					{ failureText( row ) }
					{ __(
						'The review could not complete. Open Executions in n8n to see which node stopped, fix it, then run the review again.',
						'post-quality-manager'
					) }
				</Notice>
			) }

			{ phase === 'gaveup' && (
				<Notice status="warning" isDismissible={ false }>
					{ giveUpMessage( 'review' ) }{ ' ' }
					{ __(
						'If nothing arrives, open Executions in n8n to see where it stopped.',
						'post-quality-manager'
					) }
				</Notice>
			) }
		</>
	);
}
