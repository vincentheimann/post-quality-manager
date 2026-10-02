/**
 * StatusBadge (story 5.2) — the status language per DESIGN.md D5:
 * dashicon + label + WP admin palette color inside a filled pill, never
 * color alone, no emoji. An unknown but non-empty status renders its raw
 * value on the neutral variant — never blank.
 */
import { Dashicon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import './style.css';

/** Keys match the stored review status values. */
const STATUS_MAP = {
	ship: {
		icon: 'yes-alt',
		variant: 'good',
		label: __( 'Ready to Ship', 'post-quality-manager' ),
	},
	needs_revision: {
		icon: 'flag',
		variant: 'warn',
		label: __( 'Needs Revision', 'post-quality-manager' ),
	},
	major_revision: {
		icon: 'warning',
		variant: 'crit',
		label: __( 'Major Revision', 'post-quality-manager' ),
	},
	processing: {
		icon: 'update',
		variant: 'neutral',
		label: __( 'Processing…', 'post-quality-manager' ),
	},
};

const STATUS_NONE = {
	icon: 'minus',
	variant: 'neutral',
	label: __( 'Not analyzed', 'post-quality-manager' ),
};

export default function StatusBadge( { status } ) {
	const config = ( status && STATUS_MAP[ status ] ) || STATUS_NONE;
	// An unknown but non-empty status still shows its raw value, never nothing.
	const label = status && ! STATUS_MAP[ status ] ? status : config.label;

	return (
		<span className={ `pqm-status-badge is-${ config.variant }` }>
			<Dashicon icon={ config.icon } aria-hidden="true" />
			{ label }
		</span>
	);
}
