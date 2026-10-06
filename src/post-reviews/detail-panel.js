/**
 * DetailPanel (story 5.4) — the per-post detail row of Post Reviews:
 * fetches GET /reviews/{id} and renders an accessible TabPanel — Review
 * (pillar meters, top edits, actions), one tab per agent (one structured
 * ResultView, Run / Re-run), History (every stored row). Gating mirrors
 * the list's `viewer` block: a hidden command renders no button, a
 * plan-gated one a locked, focusable button (`aria-disabled`, no-op) with
 * the lock copy; the quota note sits only beside a rendered button.
 * Rewrite is a button in the Review tab's action row, never a tab (its
 * outcome lives in History and the toast). A failed run (status `error`,
 * or an empty status with an empty result — what the shipped template
 * posts) shows the stored error or "No result was returned."; it never
 * reads as success. A failed start (not connected, n8n unreachable or
 * refusing, gate / quota refusal) shows as a dismissible warning Notice
 * above the tabs (ticket 32); while n8n is not connected the Run buttons
 * are disabled.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Dashicon,
	Notice,
	Spinner,
	TabPanel,
	VisuallyHidden,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import {
	COMMAND_ORDER,
	FREE_COMMANDS,
	LOCKED_SUFFIX,
	LOCKED_TITLE,
	OPEN_IN_EDITOR,
	commandLabel,
	runLabel,
} from '../kit/copy';
import QuotaNote from '../kit/quota-note';
import {
	canRender,
	isConnected,
	isFailedRow,
	isGated,
	isObject,
	reviewsPath,
} from '../kit/runs';
import ScoreBadge from '../kit/score-badge';
import StatusBadge from '../kit/status-badge';
import InsertBlock, { insertItems } from './insert-block';
import ResultView, { PillarMeters, isReviewShape } from './result-view';

/** Tabs: every canonical command except Rewrite, then History. */
const TAB_COMMANDS = COMMAND_ORDER.filter( ( c ) => c !== 'rewrite' );

/**
 * 'none' | 'running' | 'failed' | 'done' for the latest row of a command.
 *
 * @param {Object|undefined} row Latest stored row of the command.
 * @return {string} Row state.
 */
function rowState( row ) {
	if ( ! row ) {
		return 'none';
	}
	if ( row.status === 'processing' ) {
		return 'running';
	}
	return isFailedRow( row ) ? 'failed' : 'done';
}

/**
 * Where the review's pillars live: the row itself, or a nested `review` (audit-style).
 *
 * @param {Object|undefined} row Stored review row.
 * @return {*} The review data.
 */
function reviewData( row ) {
	const data = row && row.result_data;
	if ( isReviewShape( data ) ) {
		return data;
	}
	return isObject( data ) && isObject( data.review ) ? data.review : data;
}

/* ─── Small presentational pieces ──────────────────────────────────── */

function RunningNote( { command } ) {
	return (
		<p className="pqm-muted pqm-detail-note">
			<Spinner />{ ' ' }
			{ sprintf(
				/* translators: %s: agent command label, e.g. "Review" */
				__(
					'%s is running in n8n — results appear here when it finishes.',
					'post-quality-manager'
				),
				commandLabel( command )
			) }
		</p>
	);
}

function FailedNote( { row, command } ) {
	const data = row.result_data;
	const stored = isObject( data ) && data.error ? String( data.error ) : '';

	return (
		<Notice status="warning" isDismissible={ false }>
			{ sprintf(
				/* translators: 1: agent command label, e.g. "Review"; 2: stored error text or "No result was returned." */
				__(
					'%1$s could not complete: %2$s Check the n8n execution log, then try again.',
					'post-quality-manager'
				),
				commandLabel( command ),
				stored ||
					__( 'No result was returned.', 'post-quality-manager' )
			) }
		</Notice>
	);
}

function NotRunNote( { command, viewer } ) {
	let copy;
	if ( isGated( viewer, command ) ) {
		copy = __( 'Available with Pro.', 'post-quality-manager' );
	} else if ( FREE_COMMANDS.includes( command ) ) {
		copy = sprintf(
			/* translators: %s: agent command label, e.g. "Takeaways" */
			__(
				'Not run yet — %s is free on every plan.',
				'post-quality-manager'
			),
			commandLabel( command )
		);
	} else {
		copy = __( 'Not run yet.', 'post-quality-manager' );
	}
	return <p className="pqm-muted pqm-detail-note">{ copy }</p>;
}

/**
 * Status · score · date line above a result.
 *
 * @param {Object} props
 * @param {Object} props.row Stored review row.
 */
function ResultMeta( { row } ) {
	return (
		<p className="pqm-result-meta">
			{ ( row.status || isFailedRow( row ) ) && (
				<StatusBadge
					status={ isFailedRow( row ) ? 'error' : row.status }
				/>
			) }
			{ row.overall_score > 0 && (
				<ScoreBadge score={ row.overall_score } />
			) }
			<span className="pqm-muted">
				{ row.reviewed_at_display || row.reviewed_at }
			</span>
		</p>
	);
}

/**
 * Trigger button for one command, gated like the viewer block says:
 * hidden → nothing; plan-gated → a locked button that stays focusable on
 * every supported WP version (`aria-disabled` + no-op, no `disabled`
 * attribute) with a lock icon, the LOCKED_TITLE tooltip and a
 * visually-hidden LOCKED_SUFFIX in its name; otherwise Run / Re-run,
 * disabled while the post is busy (busy-styled only while its start
 * request is pending) or while n8n is not connected (the page notice
 * says why).
 *
 * @param {Object}   props
 * @param {string}   props.command   Command key.
 * @param {boolean}  props.hasRun    Whether a stored row exists for the command.
 * @param {Object}   props.viewer    The list's `viewer` block.
 * @param {boolean}  props.disabled  True while the post is busy.
 * @param {boolean}  props.starting  True while the post's start request is pending.
 * @param {Function} props.onTrigger `( command ) => void`.
 */
function CommandButton( {
	command,
	hasRun,
	viewer,
	disabled,
	starting,
	onTrigger,
} ) {
	if ( ! canRender( viewer, command ) ) {
		return null;
	}

	if ( isGated( viewer, command ) ) {
		return (
			<Button
				variant="secondary"
				className="pqm-locked"
				aria-disabled="true"
				title={ LOCKED_TITLE }
				onClick={ ( event ) => event.preventDefault() }
			>
				<Dashicon icon="lock" aria-hidden="true" />
				{ commandLabel( command ) }
				<VisuallyHidden>{ ' ' + LOCKED_SUFFIX }</VisuallyHidden>
			</Button>
		);
	}

	return (
		<Button
			variant="secondary"
			disabled={ disabled || ! isConnected( viewer ) }
			isBusy={ starting }
			onClick={ () => onTrigger( command ) }
		>
			{ runLabel( command, hasRun ) }
		</Button>
	);
}

/* ─── Tabs ─────────────────────────────────────────────────────────── */

function ReviewTab( {
	row,
	editLink,
	viewer,
	disabled,
	starting,
	onTrigger,
	latest,
} ) {
	const state = rowState( row );
	const data = state === 'done' ? reviewData( row ) : null;
	const topEdits =
		isObject( data ) && Array.isArray( data.top_edits )
			? data.top_edits
			: [];
	const anyButton =
		canRender( viewer, 'review' ) || canRender( viewer, 'rewrite' );

	return (
		<>
			{ state === 'running' && <RunningNote command="review" /> }
			{ state === 'failed' && (
				<FailedNote row={ row } command="review" />
			) }
			{ state === 'none' && (
				<NotRunNote command="review" viewer={ viewer } />
			) }
			{ state === 'done' && (
				<>
					<ResultMeta row={ row } />
					<PillarMeters data={ data } />
					{ topEdits.length > 0 && (
						<>
							<h3 className="pqm-detail-heading">
								{ __( 'Top edits', 'post-quality-manager' ) }
							</h3>
							<ol className="pqm-top-edits">
								{ topEdits.map( ( edit, index ) => (
									<li key={ index }>{ String( edit ) }</li>
								) ) }
							</ol>
						</>
					) }
				</>
			) }
			<div className="pqm-panel-actions">
				{ editLink && (
					<Button variant="primary" href={ editLink }>
						{ OPEN_IN_EDITOR }
					</Button>
				) }
				<CommandButton
					command="review"
					hasRun={ !! row }
					viewer={ viewer }
					disabled={ disabled }
					starting={ starting }
					onTrigger={ onTrigger }
				/>
				<CommandButton
					command="rewrite"
					hasRun={ !! latest.rewrite }
					viewer={ viewer }
					disabled={ disabled }
					starting={ starting }
					onTrigger={ onTrigger }
				/>
				{ anyButton && <QuotaNote viewer={ viewer } /> }
			</div>
		</>
	);
}

function AgentTab( {
	command,
	row,
	postId,
	editLink,
	viewer,
	disabled,
	starting,
	onTrigger,
	onNotify,
} ) {
	const state = rowState( row );
	// Insert into Post (story 23): a done Takeaways / Sources run with items,
	// for a viewer who may edit the post and run the agent (gated alike).
	const items =
		state === 'done' ? insertItems( command, row.result_data ) : [];
	const canInsert =
		items.length > 0 &&
		!! editLink &&
		canRender( viewer, command ) &&
		! isGated( viewer, command );

	return (
		<>
			{ state === 'running' && <RunningNote command={ command } /> }
			{ state === 'failed' && (
				<FailedNote row={ row } command={ command } />
			) }
			{ state === 'none' && (
				<NotRunNote command={ command } viewer={ viewer } />
			) }
			{ state === 'done' && (
				<>
					<ResultMeta row={ row } />
					<ResultView data={ row.result_data } />
				</>
			) }
			<div className="pqm-panel-actions">
				{ canInsert && (
					<InsertBlock
						postId={ postId }
						command={ command }
						count={ items.length }
						disabled={ disabled }
						onNotify={ onNotify }
					/>
				) }
				<CommandButton
					command={ command }
					hasRun={ !! row }
					viewer={ viewer }
					disabled={ disabled }
					starting={ starting }
					onTrigger={ onTrigger }
				/>
				{ canRender( viewer, command ) && (
					<QuotaNote viewer={ viewer } />
				) }
			</div>
		</>
	);
}

function HistoryTab( { rows } ) {
	if ( rows.length === 0 ) {
		return (
			<p className="pqm-muted pqm-detail-note">
				{ __(
					'No reviews found. Trigger a review to get started.',
					'post-quality-manager'
				) }
			</p>
		);
	}

	return (
		<ul className="pqm-run-history">
			{ rows.map( ( row ) => (
				<li key={ row.id }>
					<span className="pqm-run-history-cmd">
						{ commandLabel( row.command ) }
					</span>
					{ row.overall_score > 0 && (
						<ScoreBadge score={ row.overall_score } />
					) }
					{ ( row.status || isFailedRow( row ) ) && (
						<StatusBadge
							status={ isFailedRow( row ) ? 'error' : row.status }
						/>
					) }
					<span className="pqm-muted">
						{ row.reviewed_at_display || row.reviewed_at }
					</span>
				</li>
			) ) }
		</ul>
	);
}

/* ─── Panel ────────────────────────────────────────────────────────── */

/**
 * @param {Object}      props
 * @param {number}      props.postId              Post whose reviews to show.
 * @param {string}      props.editLink            Edit URL, '' when the viewer cannot edit.
 * @param {Object}      props.viewer              The list's `viewer` block.
 * @param {boolean}     props.busy                True while the post is awaited or a trigger is pending.
 * @param {boolean}     props.starting            True while the post's start request is pending.
 * @param {number}      props.version             Bump to refetch (a run completed or started).
 * @param {Function}    props.onTrigger           `( command ) => void`.
 * @param {Function}    props.onNotify            `( content, status ) => void` — the page's toast (Insert into Post).
 * @param {string|null} props.startError          This post's failed-start message, null when none.
 * @param {Function}    props.onDismissStartError Closes the failed-start notice.
 */
export default function DetailPanel( {
	postId,
	editLink,
	viewer,
	busy,
	starting,
	version,
	onTrigger,
	onNotify,
	startError,
	onDismissStartError,
} ) {
	const [ rows, setRows ] = useState( null );
	const [ error, setError ] = useState( false );

	useEffect( () => {
		let cancelled = false;

		apiFetch( { path: reviewsPath( postId ) } )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setRows( Array.isArray( data ) ? data : [] );
					setError( false );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setError( true );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ postId, version ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __(
					"Could not load this post's reviews. Reload the page and try again.",
					'post-quality-manager'
				) }
			</Notice>
		);
	}

	if ( rows === null ) {
		return (
			<p className="pqm-detail-note">
				<Spinner /> { __( 'Loading reviews…', 'post-quality-manager' ) }
			</p>
		);
	}

	// Rows arrive newest first: the first row per command is its latest.
	const latest = {};
	rows.forEach( ( row ) => {
		if ( ! latest[ row.command ] ) {
			latest[ row.command ] = row;
		}
	} );

	const tabs = [
		...TAB_COMMANDS.map( ( command ) => ( {
			name: command,
			title:
				rowState( latest[ command ] ) === 'done' ? (
					<>
						<span className="pqm-tab-done" aria-hidden="true">
							{ '✓ ' }
						</span>
						{ commandLabel( command ) }
						<VisuallyHidden>
							{ ' ' +
								__( '(has a result)', 'post-quality-manager' ) }
						</VisuallyHidden>
					</>
				) : (
					commandLabel( command )
				),
		} ) ),
		{
			name: 'history',
			title: sprintf(
				/* translators: %d: number of stored review rows */
				__( 'History (%d)', 'post-quality-manager' ),
				rows.length
			),
		},
	];

	return (
		<div className="pqm-detail-panel">
			{ startError && (
				<Notice
					status="warning"
					className="pqm-start-error"
					isDismissible
					onRemove={ onDismissStartError }
				>
					{ startError }
				</Notice>
			) }
			<TabPanel className="pqm-detail-tabs" tabs={ tabs }>
				{ ( tab ) => {
					if ( tab.name === 'history' ) {
						return <HistoryTab rows={ rows } />;
					}
					if ( tab.name === 'review' ) {
						return (
							<ReviewTab
								row={ latest.review }
								latest={ latest }
								editLink={ editLink }
								viewer={ viewer }
								disabled={ busy }
								starting={ starting }
								onTrigger={ onTrigger }
							/>
						);
					}
					return (
						<AgentTab
							command={ tab.name }
							row={ latest[ tab.name ] }
							postId={ postId }
							editLink={ editLink }
							viewer={ viewer }
							disabled={ busy }
							starting={ starting }
							onTrigger={ onTrigger }
							onNotify={ onNotify }
						/>
					);
				} }
			</TabPanel>
		</div>
	);
}
