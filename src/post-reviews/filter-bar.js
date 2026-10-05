/**
 * FilterBar (story 5.3) — the mockup's filter row above the Post Reviews
 * table: status chips (All + four statuses, each with its facet count),
 * the score-band select and the quota note riding right. Page-local:
 * nothing outside Post Reviews consumes it.
 *
 * Chips are @wordpress/components Buttons with `isPressed`, which renders
 * `aria-pressed`; labels come from statusLabel() so chips and StatusBadge
 * share one source. Score bands align to the plugin's thresholds (owner
 * decision 2026-10-05): a band equals a ScoreBadge color.
 */
import { Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { STATUS_NONE, statusLabel } from '../kit/copy';
import QuotaNote from '../kit/quota-note';

/** Chip order follows the mockup; '' is the "All" chip (no status filter). */
const CHIP_STATUSES = [ '', 'ship', 'needs_revision', 'major_revision', STATUS_NONE ];

/**
 * Score bands as `min_score` / `max_score` pairs; null = unbounded.
 * `value` is only the select's option key — the URL carries min/max.
 * "Below 41" starts at 1: score 0 is the processing / error placeholder,
 * which ScoreBadge renders in the neutral color, not red.
 */
export const SCORE_BANDS = [
	{ value: '', min: null, max: null, label: __( 'Any', 'post-quality-manager' ) },
	{ value: 'low', min: 1, max: 40, label: __( 'Below 41', 'post-quality-manager' ) },
	{ value: 'mid', min: 41, max: 70, label: __( '41–70', 'post-quality-manager' ) },
	{ value: 'high', min: 71, max: null, label: __( '71+', 'post-quality-manager' ) },
];

/** The band whose bounds equal the given min/max, or undefined (custom deep link). */
export function findBand( min, max ) {
	return SCORE_BANDS.find( ( band ) => band.min === min && band.max === max );
}

function chipLabel( status ) {
	return status ? statusLabel( status ) : __( 'All', 'post-quality-manager' );
}

export default function FilterBar( {
	status,
	minScore,
	maxScore,
	counts,
	viewer,
	legacyFilter,
	onStatusChange,
	onBandChange,
} ) {
	const band = findBand( minScore, maxScore );

	return (
		<div className="pqm-table-topbar pqm-filter-bar">
			<div
				className="pqm-filter-chips"
				role="group"
				aria-label={ __( 'Filter by status', 'post-quality-manager' ) }
			>
				{ CHIP_STATUSES.map( ( chip ) => {
					// The legacy ?filter=needs_review view (Dashboard card) is
					// not a chip state: nothing is pressed until a chip is chosen.
					const pressed = ! legacyFilter && chip === status;
					const count = counts ? counts[ chip || 'all' ] : undefined;

					return (
						<Button
							key={ chip || 'all' }
							className="pqm-filter-chip"
							isPressed={ pressed }
							onClick={ () => onStatusChange( chip ) }
						>
							{ chipLabel( chip ) }
							{ typeof count === 'number' && (
								<span className="pqm-filter-chip-count">
									{ count }
								</span>
							) }
						</Button>
					);
				} ) }
			</div>
			<SelectControl
				className="pqm-filter-score"
				label={ __( 'Score', 'post-quality-manager' ) }
				labelPosition="side"
				value={ band ? band.value : '' }
				options={ SCORE_BANDS.map( ( { value, label } ) => ( {
					value,
					label,
				} ) ) }
				onChange={ ( value ) => {
					const next =
						SCORE_BANDS.find( ( b ) => b.value === value ) ||
						SCORE_BANDS[ 0 ];
					onBandChange( next.min, next.max );
				} }
				size="compact"
				__nextHasNoMarginBottom
			/>
			<QuotaNote viewer={ viewer } />
		</div>
	);
}
