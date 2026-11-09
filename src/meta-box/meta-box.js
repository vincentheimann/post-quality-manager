/**
 * MetaBox (story 109) — the Post Quality box under the content of a post,
 * in the block editor and the Classic Editor. Top to bottom: the latest
 * Review (or its running / failed note), the quota note, one action
 * (Review, Save and review, the not-connected notice or the sentence that
 * reviews run on published and pending posts), the link to the post's
 * review on Post Reviews.
 *
 * Data: GET /posts?post_id (its `viewer` block: gating, quota, connection,
 * never a localized snapshot) and GET /reviews/{id} (the latest Review
 * row). A run goes through the one trigger path (requestRun(), POST
 * /trigger-review); the wait is the kit's useReviewWait. A run starts only
 * on a saved published or pending post: the block editor saves first
 * (Save and review), the Classic Editor asks for a save.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, VisuallyHidden } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { runLabel } from '../kit/copy';
import NotConnectedNotice from '../kit/not-connected-notice';
import QuotaNote from '../kit/quota-note';
import ReviewSummary, {
	FailedNote,
	RunningNote,
	rowState,
} from '../kit/review-summary';
import {
	POSTS_PATH,
	canRender,
	giveUpMessage,
	isConnected,
	outcomeToast,
	requestRun,
	reviewsPath,
} from '../kit/runs';
import useReviewWait from '../kit/use-review-wait';
import {
	REVIEWABLE,
	blockEditorDirty,
	classicEditorDirty,
	saveBlockEditor,
	useBlockEditor,
} from './editor';
import './style.css';

/**
 * The newest Review row of a post's stored rows (newest first), or null.
 *
 * @param {unknown} rows GET /reviews/{id} reply.
 * @return {Object|null} Latest Review row.
 */
function latestReview( rows ) {
	return (
		( Array.isArray( rows ) ? rows : [] ).find(
			( row ) => row.command === 'review'
		) || null
	);
}

/**
 * @param {Object}  props
 * @param {number}  props.postId        The edited post.
 * @param {string}  props.status        Its saved status when the page loaded.
 * @param {boolean} props.isBlockEditor Whether the screen runs the block editor.
 * @param {string}  props.reviewsUrl    Post Reviews narrowed to this post, its panel open.
 */
export default function MetaBox( {
	postId,
	status,
	isBlockEditor,
	reviewsUrl,
} ) {
	const editor = useBlockEditor( isBlockEditor );
	const [ viewer, setViewer ] = useState( null );
	const [ row, setRow ] = useState( null );
	const [ loaded, setLoaded ] = useState( false );
	const [ loadError, setLoadError ] = useState( false );
	// idle → saving → starting → waiting → idle | gaveup
	const [ phase, setPhase ] = useState( 'idle' );
	const [ startError, setStartError ] = useState( '' );
	const [ needsSave, setNeedsSave ] = useState( false );
	const [ outcome, setOutcome ] = useState( '' );
	// Bumped after an accepted start: the new quota and the run's `processing` row.
	const [ reloads, setReloads ] = useState( 0 );

	useEffect( () => {
		Promise.all( [
			apiFetch( {
				path: addQueryArgs( POSTS_PATH, {
					post_id: postId,
					per_page: 1,
				} ),
			} ),
			apiFetch( { path: reviewsPath( postId ) } ),
		] ).then(
			( [ list, rows ] ) => {
				const latest = latestReview( rows );
				setViewer( ( list && list.viewer ) || null );
				setRow( latest );
				// A run in progress (when the page opened, or just started): wait for it.
				setPhase(
					rowState( latest ) === 'running' ? 'waiting' : 'idle'
				);
				setLoaded( true );
			},
			// A failed reload after a start keeps the box as it is; the wait goes on.
			() => reloads === 0 && setLoadError( true )
		);
	}, [ postId, reloads ] );

	useReviewWait( postId, phase === 'waiting', {
		onSettled: ( latest ) => {
			setRow( latest );
			setPhase( 'idle' );
			// A failed run's Notice speaks for itself.
			if ( rowState( latest ) === 'done' ) {
				setOutcome( outcomeToast( 'review', latest ).content );
			}
		},
		onGiveUp: () => setPhase( 'gaveup' ),
	} );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __(
					"Could not load this post's reviews. Reload the page and try again.",
					'post-quality-manager'
				) }
			</Notice>
		);
	}

	if ( ! loaded ) {
		return (
			<p className="pqm-detail-note">
				<Spinner /> { __( 'Loading reviews…', 'post-quality-manager' ) }
			</p>
		);
	}

	const savedStatus = ( editor && editor.status ) || status;
	const reviewable = REVIEWABLE.includes( savedStatus );
	const dirty = !! ( editor && editor.dirty );
	const state = rowState( row );
	const busy = [ 'saving', 'starting', 'waiting' ].includes( phase );

	const run = async () => {
		setStartError( '' );
		setNeedsSave( false );
		setOutcome( '' );
		if ( isBlockEditor && blockEditorDirty() ) {
			setPhase( 'saving' );
			if ( ! ( await saveBlockEditor() ) ) {
				// The editor shows why its save did not go through.
				setPhase( 'idle' );
				return;
			}
		} else if ( ! isBlockEditor && classicEditorDirty() ) {
			setNeedsSave( true );
			return;
		}
		setPhase( 'starting' );
		const { ok, message } = await requestRun( postId, 'review' );
		setPhase( ok ? 'waiting' : 'idle' );
		setStartError( ok ? '' : message );
		if ( ok ) {
			setReloads( ( count ) => count + 1 ); // Post Reviews refetches after a start too
		}
	};

	let action = null;
	if ( ! reviewable ) {
		action = (
			<p className="pqm-muted pqm-detail-note">
				{ __(
					'Reviews run on published and pending posts.',
					'post-quality-manager'
				) }
			</p>
		);
	} else if ( ! canRender( viewer, 'review' ) ) {
		action = null; // may view reviews, not run them
	} else if ( ! isConnected( viewer ) ) {
		action = (
			<NotConnectedNotice canManage={ !! viewer.can_manage_settings } />
		);
	} else {
		action = (
			<>
				<QuotaNote viewer={ viewer } />
				<div className="pqm-meta-box-action">
					<Button
						variant="secondary"
						onClick={ run }
						disabled={ busy }
						isBusy={ phase === 'saving' || phase === 'starting' }
					>
						{ isBlockEditor && dirty
							? __( 'Save and review', 'post-quality-manager' )
							: runLabel( 'review', !! row ) }
					</Button>
				</div>
			</>
		);
	}

	return (
		<div className="pqm-meta-box">
			{ /* The box's one live region: the run's progress and outcome. */ }
			<div className="pqm-meta-box-status" role="status">
				{ phase === 'waiting' && <RunningNote command="review" /> }
				{ phase === 'gaveup' && (
					<p className="pqm-muted pqm-detail-note">
						{ giveUpMessage( 'review' ) }
					</p>
				) }
				{ outcome && <VisuallyHidden>{ outcome }</VisuallyHidden> }
			</div>
			{ /* While a run is awaited, only the running note shows (the Review tab's rule). */ }
			{ state === 'done' && phase !== 'waiting' && (
				<ReviewSummary row={ row } />
			) }
			{ state === 'failed' && phase !== 'waiting' && (
				<FailedNote row={ row } command="review" />
			) }
			{ state === 'none' && reviewable && phase === 'idle' && (
				<p className="pqm-muted pqm-detail-note">
					{ __( 'Not reviewed yet.', 'post-quality-manager' ) }
				</p>
			) }
			{ startError && (
				<Notice
					status="warning"
					isDismissible
					onRemove={ () => setStartError( '' ) }
				>
					{ startError }
				</Notice>
			) }
			{ needsSave && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Save your changes first, then run the review.',
						'post-quality-manager'
					) }
				</Notice>
			) }
			{ action }
			{ reviewable && (
				<p className="pqm-meta-box-link">
					<a href={ reviewsUrl }>
						{ __( 'Open full review', 'post-quality-manager' ) }
					</a>
				</p>
			) }
		</div>
	);
}
