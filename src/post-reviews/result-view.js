/**
 * ResultView (story 5.4) — one structured, prompt-agnostic renderer for
 * the JSON objects the n8n agents store in `result_data` (the template's
 * `Parse …` nodes: takeaways, sources, seo, audit). Keys are humanized,
 * arrays become lists, booleans ✓/✗ with hidden yes/no text, nested
 * objects become sections; a nested object shaped like a review (the
 * audit's `review` block) renders through PillarMeters. Empty values are
 * skipped so a sparse result never shows blank rows.
 */
import { VisuallyHidden } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/** The four universal pillars (memory/lessons.md 2026-03-27), in display order. */
export const PILLARS = [
	{ key: 'structure', label: __( 'Structure', 'post-quality-manager' ) },
	{ key: 'readability', label: __( 'Readability', 'post-quality-manager' ) },
	{ key: 'engagement', label: __( 'Engagement', 'post-quality-manager' ) },
	{ key: 'credibility', label: __( 'Credibility', 'post-quality-manager' ) },
];

export function isObject( value ) {
	return value !== null && typeof value === 'object' && ! Array.isArray( value );
}

/** Null, '', [] or {} — what a failed n8n run stores as its result. */
export function isEmpty( value ) {
	return (
		value === null ||
		value === undefined ||
		value === '' ||
		( Array.isArray( value ) && value.length === 0 ) ||
		( isObject( value ) && Object.keys( value ).length === 0 )
	);
}

/** A pillar score as the agents store it: `{ score: 72 }` or a bare number. */
function pillarScore( value ) {
	const score = isObject( value ) ? value.score : value;
	const n = Number( score );
	return Number.isFinite( n ) ? Math.max( 0, Math.min( 100, Math.round( n ) ) ) : null;
}

/** True when the object carries at least one pillar score (a review result). */
export function isReviewShape( data ) {
	return (
		isObject( data ) &&
		PILLARS.some( ( { key } ) => pillarScore( data[ key ] ) !== null )
	);
}

/**
 * Four single-hue horizontal meters (DESIGN.md: WP blue, magnitude only —
 * status color lives in the badge). The bar is decorative; label and
 * number carry the information.
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

/** `unsourced_critical` → "Unsourced critical". */
function humanize( key ) {
	const text = String( key ).replace( /[_-]+/g, ' ' ).trim();
	return text.charAt( 0 ).toUpperCase() + text.slice( 1 );
}

function Scalar( { value } ) {
	if ( typeof value === 'boolean' ) {
		return (
			<span className={ `pqm-bool ${ value ? 'is-yes' : 'is-no' }` }>
				<span aria-hidden="true">{ value ? '✓' : '✗' }</span>
				<VisuallyHidden>
					{ value
						? __( 'Yes', 'post-quality-manager' )
						: __( 'No', 'post-quality-manager' ) }
				</VisuallyHidden>
			</span>
		);
	}
	return <>{ String( value ) }</>;
}

function Value( { value } ) {
	if ( Array.isArray( value ) ) {
		return (
			<ul className="pqm-result-list">
				{ value.map( ( entry, index ) => (
					<li key={ index }>
						<Value value={ entry } />
					</li>
				) ) }
			</ul>
		);
	}
	if ( isObject( value ) ) {
		return <ObjectView data={ value } />;
	}
	return <Scalar value={ value } />;
}

/**
 * Key/value rows for the scalars of one object, then a section per
 * nested object or array. A review-shaped object leads with its meters.
 */
function ObjectView( { data } ) {
	const review = isReviewShape( data );
	const entries = Object.entries( data ).filter(
		( [ key, value ] ) =>
			! isEmpty( value ) &&
			! ( review && PILLARS.some( ( p ) => p.key === key ) )
	);
	const scalars = entries.filter(
		( [ , value ] ) => ! isObject( value ) && ! Array.isArray( value )
	);
	const nested = entries.filter(
		( [ , value ] ) => isObject( value ) || Array.isArray( value )
	);

	return (
		<>
			{ review && <PillarMeters data={ data } /> }
			{ scalars.length > 0 && (
				<dl className="pqm-result-rows">
					{ scalars.map( ( [ key, value ] ) => (
						<div className="pqm-result-row" key={ key }>
							<dt>{ humanize( key ) }</dt>
							<dd>
								<Scalar value={ value } />
							</dd>
						</div>
					) ) }
				</dl>
			) }
			{ nested.map( ( [ key, value ] ) => (
				<section className="pqm-result-section" key={ key }>
					<h4>{ humanize( key ) }</h4>
					<Value value={ value } />
				</section>
			) ) }
		</>
	);
}

export default function ResultView( { data } ) {
	if ( isEmpty( data ) ) {
		return (
			<p className="pqm-muted">
				{ __( 'No result was returned.', 'post-quality-manager' ) }
			</p>
		);
	}
	if ( ! isObject( data ) && ! Array.isArray( data ) ) {
		// Raw string result_data (invalid JSON kept as saved by n8n).
		return <pre className="pqm-result-raw">{ String( data ) }</pre>;
	}
	return (
		<div className="pqm-result-view">
			<Value value={ data } />
		</div>
	);
}
