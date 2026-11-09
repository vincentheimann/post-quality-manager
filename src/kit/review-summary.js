/**
 * Review summary (story 109, moved from src/post-reviews/detail-panel.js
 * and result-view.js) — the pieces that show a stored run, shared by Post
 * Reviews' detail panel and the post editor's Post Quality meta box: the
 * row state, the running and failed notes, the status · score · date line,
 * the four pillar meters and the Review result (meta line, meters, top
 * edits). Markup and classes are the detail panel's (DESIGN.md D2, one
 * render source); their rules live in the kit's style.css so every entry
 * that renders them carries them.
 */
import { Notice, Spinner } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { commandLabel } from './copy';
import { isFailedRow, isObject } from './runs';
import ScoreBadge from './score-badge';
import StatusBadge from './status-badge';
import './style.css';

/** The four universal pillars (memory/lessons.md 2026-03-27), in display order. */
export const PILLARS = [
	{ key: 'structure', label: __( 'Structure', 'post-quality-manager' ) },
	{ key: 'readability', label: __( 'Readability', 'post-quality-manager' ) },
	{ key: 'engagement', label: __( 'Engagement', 'post-quality-manager' ) },
	{ key: 'credibility', label: __( 'Credibility', 'post-quality-manager' ) },
];

/**
 * A pillar score as the agents store it: `{ score: 72 }` or a bare number.
 *
 * @param {unknown} value Stored pillar value.
 * @return {number|null} Score 0–100, null when not a number.
 */
function pillarScore( value ) {
	const score = isObject( value ) ? value.score : value;
	const n = Number( score );
	return Number.isFinite( n )
		? Math.max( 0, Math.min( 100, Math.round( n ) ) )
		: null;
}

/**
 * True when the object carries at least one pillar score (a review result).
 *
 * @param {unknown} data Stored result data.
 * @return {boolean} Whether `data` is review-shaped.
 */
export function isReviewShape( data ) {
	return (
		isObject( data ) &&
		PILLARS.some( ( { key } ) => pillarScore( data[ key ] ) !== null )
	);
}

/**
 * 'none' | 'running' | 'failed' | 'done' for the latest row of a command.
 *
 * @param {Object|undefined} row Latest stored row of the command.
 * @return {string} Row state.
 */
export function rowState( row ) {
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
 * @return {unknown} The review data.
 */
function reviewData( row ) {
	const data = row && row.result_data;
	if ( isReviewShape( data ) ) {
		return data;
	}
	return isObject( data ) && isObject( data.review ) ? data.review : data;
}

export function RunningNote( { command } ) {
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

export function FailedNote( { row, command } ) {
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

/**
 * Status · score · date line above a result.
 *
 * @param {Object} props
 * @param {Object} props.row Stored review row.
 */
export function ResultMeta( { row } ) {
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
 * Four single-hue horizontal meters (DESIGN.md: WP blue, magnitude only —
 * status color lives in the badge). The bar is decorative; label and
 * number carry the information.
 *
 * @param {Object}  props
 * @param {unknown} props.data Review data carrying the pillar scores.
 */
export function PillarMeters( { data } ) {
	return (
		<div className="pqm-pillars">
			{ PILLARS.map( ( { key, label } ) => {
				const score = pillarScore( data && data[ key ] );
				return (
					<Fragment key={ key }>
						<span className="pqm-pillar-label">{ label }</span>
						<span className="pqm-meter" aria-hidden="true">
							<i style={ { width: `${ score || 0 }%` } } />
						</span>
						<span className="pqm-pillar-score">
							{ score === null ? '—' : score }
						</span>
					</Fragment>
				);
			} ) }
		</div>
	);
}

/**
 * A done Review row: the meta line, the pillar meters and the top edits.
 *
 * @param {Object} props
 * @param {Object} props.row Latest Review row, in the 'done' state.
 */
export default function ReviewSummary( { row } ) {
	const data = reviewData( row );
	const topEdits =
		isObject( data ) && Array.isArray( data.top_edits )
			? data.top_edits
			: [];

	return (
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
	);
}
