/**
 * Sparkline (story 5.6, DESIGN.md: the one chart) — catalog average score
 * as of each week's end, from GET /dashboard `trend`. Inline SVG, single
 * series in WP blue, recessive grid, endpoint dot + direct label, no
 * legend; hover shows a crosshair + tooltip from React state (mockup).
 * Null points (no analyzed post yet) are skipped; with fewer than two
 * points a sentence replaces the chart.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

const W = 560;
const H = 90;
const PAD = { top: 12, right: 40, bottom: 12, left: 10 };

/**
 * @param {Object} props
 * @param {Array}  props.trend `[{ label, average }]`, oldest first.
 */
export default function Sparkline( { trend } ) {
	const [ hover, setHover ] = useState( null ); // index into `points`
	const slots = trend.length;
	const step = slots > 1 ? ( W - PAD.left - PAD.right ) / ( slots - 1 ) : 0;
	const points = trend
		.map( ( p, i ) => ( { x: PAD.left + i * step, value: p.average, label: p.label } ) )
		.filter( ( p ) => p.value !== null && p.value !== undefined );

	if ( points.length < 2 ) {
		return (
			<p className="pqm-muted pqm-spark-empty">
				{ __(
					'No score history yet — the trend appears once analyzed posts span two weeks.',
					'post-quality-manager'
				) }
			</p>
		);
	}

	const values = points.map( ( p ) => p.value );
	const lo = Math.max( 0, Math.min( ...values ) - 5 );
	const hi = Math.min( 100, Math.max( ...values ) + 5 );
	const plotH = H - PAD.top - PAD.bottom;
	points.forEach( ( p ) => {
		p.y = PAD.top + ( ( hi - p.value ) / ( hi - lo ) ) * plotH;
	} );

	const first = points[ 0 ];
	const last = points[ points.length - 1 ];
	const active = hover !== null ? points[ hover ] : null;

	const onMove = ( event ) => {
		const rect = event.currentTarget.getBoundingClientRect();
		const x = ( ( event.clientX - rect.left ) * W ) / rect.width;
		let best = 0;
		points.forEach( ( p, i ) => {
			if ( Math.abs( p.x - x ) < Math.abs( points[ best ].x - x ) ) {
				best = i;
			}
		} );
		setHover( best );
	};

	return (
		<div className="pqm-spark">
			<svg
				className="pqm-spark-svg"
				viewBox={ `0 0 ${ W } ${ H }` }
				role="img"
				aria-label={ sprintf(
					/* translators: 1: number of weeks; 2: first average score; 3: latest average score */
					__(
						'Average score trend over the last %1$d weeks, from %2$d to %3$d',
						'post-quality-manager'
					),
					slots,
					first.value,
					last.value
				) }
				onMouseMove={ onMove }
				onMouseLeave={ () => setHover( null ) }
			>
				<g stroke="#e3e5e8" strokeWidth="1">
					{ [ PAD.top, PAD.top + plotH / 2, PAD.top + plotH ].map( ( y ) => (
						<line key={ y } x1={ PAD.left } y1={ y } x2={ W - PAD.right } y2={ y } />
					) ) }
				</g>
				{ active && (
					<line
						x1={ active.x }
						y1={ PAD.top }
						x2={ active.x }
						y2={ PAD.top + plotH }
						stroke="#a7aaad"
						strokeWidth="1"
					/>
				) }
				<polyline
					fill="none"
					stroke="#2271b1"
					strokeWidth="2"
					strokeLinejoin="round"
					strokeLinecap="round"
					points={ points.map( ( p ) => `${ p.x },${ p.y }` ).join( ' ' ) }
				/>
				<circle cx={ last.x } cy={ last.y } r="3.5" fill="#2271b1" />
				{ active && active !== last && (
					<circle cx={ active.x } cy={ active.y } r="3" fill="#2271b1" />
				) }
				<text
					x={ last.x + 8 }
					y={ last.y + 4 }
					fontSize="12"
					fontWeight="600"
					fill="#1d2327"
				>
					{ last.value }
				</text>
			</svg>
			{ active && (
				<div
					className="pqm-spark-tip"
					aria-hidden="true"
					style={ {
						left: `${ ( active.x / W ) * 100 }%`,
						top: `${ ( active.y / H ) * 100 }%`,
						// Inline, not in style.css: the RTL build must not flip it.
						transform: 'translate(-50%, -130%)',
					} }
				>
					{ sprintf(
						/* translators: 1: week end date; 2: average score */
						__( 'Week ending %1$s — average %2$d', 'post-quality-manager' ),
						active.label,
						active.value
					) }
				</div>
			) }
		</div>
	);
}
