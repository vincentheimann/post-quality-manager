/**
 * ResultView (story 5.4) — one structured, prompt-agnostic renderer for
 * the JSON objects the n8n agents store in `result_data` (the template's
 * `Parse …` nodes: takeaways, sources, seo, audit). Keys are humanized,
 * arrays become lists, booleans ✓/✗ with hidden yes/no text, nested
 * objects become sections; a nested object shaped like a review (the
 * audit's `review` block) renders through PillarMeters (moved to
 * src/kit/review-summary.js in story 109). Empty values are skipped so a
 * sparse result never shows blank rows.
 */
import { VisuallyHidden } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { PILLARS, PillarMeters, isReviewShape } from '../kit/review-summary';
import { isEmpty, isObject } from '../kit/runs';

/**
 * `unsourced_critical` → "Unsourced critical".
 *
 * @param {string} key Result key.
 * @return {string} Readable label.
 */
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
 *
 * @param {Object} props
 * @param {Object} props.data Object to render.
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
