/**
 * FirstReview (story 36) — the setup's last step: pick a published post,
 * run its review from the dialog and wait for the result. The run goes
 * through the one trigger path (requestRun(), POST /trigger-review: gate,
 * quota, the `processing` row); the wait is the kit's useReviewWait (story
 * 109): it polls GET /reviews/{id} at the kit's pace until the post's newest
 * review row leaves `processing`, and gives up three minutes (the kit's
 * limit) after the run started — counted from the `processing` row's
 * `reviewed_at`, so a reopened dialog does not wait afresh for a run that
 * started long ago (story 2, goal b). Giving up names where the review went
 * and the site address n8n must reach to send the result back. A scored
 * result calls `onComplete` (POST /setup { complete: true }); a failed run
 * says where to look and leaves the step open.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { statusLabel } from '../kit/copy';
import { POSTS_PATH, inFlight, isFailedRow, requestRun } from '../kit/runs';
import ScoreBadge from '../kit/score-badge';
import useReviewWait from '../kit/use-review-wait';

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
 * The give-up line: where the review went, where n8n must send the result
 * back, and the two places to look. Without a webhook URL (the step needs
 * one, so it should not happen) the first clause is left out.
 *
 * @param {string} webhookUrl The n8n Webhook URL the review was sent to.
 * @param {string} restUrl    The site's REST root n8n sends results to.
 * @return {string} Translated message.
 */
function gaveUpText( webhookUrl, restUrl ) {
	const way = webhookUrl
		? sprintf(
				/* translators: 1: n8n Webhook URL the review was sent to; 2: the site's REST address n8n sends the result back to */
				__(
					'No result came back within 3 minutes. The review was sent to %1$s; n8n sends the result back to %2$s, which it must be able to reach — a local, private or password-protected site cannot be called back.',
					'post-quality-manager'
				),
				webhookUrl,
				restUrl
			)
		: sprintf(
				/* translators: %s: the site's REST address n8n sends the result back to */
				__(
					'No result came back within 3 minutes. n8n sends the result back to %s, which it must be able to reach — a local, private or password-protected site cannot be called back.',
					'post-quality-manager'
				),
				restUrl
			);
	return `${ way } ${ __(
		'Open Executions in n8n to see where the review stopped, or use Test again on the n8n Workflow page: it checks the way back in a few seconds.',
		'post-quality-manager'
	) }`;
}

/**
 * @param {Object}                props
 * @param {boolean}               props.connected  Whether a webhook URL is stored.
 * @param {string}                props.webhookUrl The stored n8n Webhook URL.
 * @param {string}                props.restUrl    The site's REST root (GET /setup).
 * @param {(row: Object) => void} props.onComplete Called once with the scored row.
 * @return {Element} The first-review step body.
 */
export default function FirstReview( {
	connected,
	webhookUrl,
	restUrl,
	onComplete,
} ) {
	const [ posts, setPosts ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ postId, setPostId ] = useState( 0 );
	// idle → starting → waiting → done | failed | gaveup
	const [ phase, setPhase ] = useState( 'idle' );
	const [ startError, setStartError ] = useState( '' );
	const [ row, setRow ] = useState( null );

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

	// The hook keeps the latest handlers, so onComplete never restarts the wait.
	useReviewWait( postId, phase === 'waiting', {
		onGiveUp: () => setPhase( 'gaveup' ),
		onSettled: ( latest ) => {
			setRow( latest );
			if ( isFailedRow( latest ) ) {
				setPhase( 'failed' );
				return;
			}
			setPhase( 'done' );
			onComplete( latest );
		},
	} );

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
					{ gaveUpText( webhookUrl, restUrl ) }
				</Notice>
			) }
		</>
	);
}
