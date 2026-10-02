/**
 * QuotaNote (story 5.2) — the current user's remaining monthly AI
 * actions. Renders nothing at all unless the viewer can trigger AND a
 * quota applies: `viewer.quota` is null for exempt users and when the
 * Admin set the limit to 0 (unlimited).
 */
import { __, sprintf } from '@wordpress/i18n';

import './style.css';

export default function QuotaNote( { viewer } ) {
	if ( ! viewer || ! viewer.can_trigger || ! viewer.quota ) {
		return null;
	}

	return (
		<span className="pqm-quota-note">
			{ sprintf(
				/* translators: 1: remaining AI actions, 2: monthly limit */
				__(
					'%1$d of %2$d AI actions left this month',
					'post-quality-manager'
				),
				viewer.quota.remaining,
				viewer.quota.limit
			) }
		</span>
	);
}
