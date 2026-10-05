/**
 * StatusBadge (story 5.2) — the status language per DESIGN.md D5:
 * dashicon + label + WP admin palette color inside a filled pill, never
 * color alone, no emoji. An unknown but non-empty status renders its raw
 * value on the neutral variant — never blank. Labels come from
 * statusLabel() in copy.js, shared with the filter chips (story 5.3).
 */
import { Dashicon } from '@wordpress/components';

import { statusLabel } from './copy';
import './style.css';

/** Keys match the stored review status values. */
const STATUS_MAP = {
	ship: { icon: 'yes-alt', variant: 'good' },
	needs_revision: { icon: 'flag', variant: 'warn' },
	major_revision: { icon: 'warning', variant: 'crit' },
	processing: { icon: 'update', variant: 'neutral' },
	// Failed run (story 5.4 owner decision): neutral gray, never alarming.
	error: { icon: 'dismiss', variant: 'neutral' },
};

const STATUS_NONE = { icon: 'minus', variant: 'neutral' };

export default function StatusBadge( { status } ) {
	const config = ( status && STATUS_MAP[ status ] ) || STATUS_NONE;

	return (
		<span className={ `pqm-status-badge is-${ config.variant }` }>
			<Dashicon icon={ config.icon } aria-hidden="true" />
			{ statusLabel( status ) }
		</span>
	);
}
