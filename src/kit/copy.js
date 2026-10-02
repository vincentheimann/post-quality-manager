/**
 * Canonical command + gate copy (story 5.2) — the one JS source for
 * command order, command labels and plan-gate wording, consumed by the
 * Post Reviews table now and stories 5.3–5.6 later. Copy follows
 * docs/brand.md; every string flows through @wordpress/i18n.
 */
import { __ } from '@wordpress/i18n';

/**
 * Canonical command order — the order pills, tabs and buttons render in.
 */
export const COMMAND_ORDER = [
	'review',
	'takeaways',
	'sources',
	'seo',
	'audit',
	'rewrite',
];

const COMMAND_LABELS = {
	review: __( 'Review', 'post-quality-manager' ),
	takeaways: __( 'Takeaways', 'post-quality-manager' ),
	sources: __( 'Sources', 'post-quality-manager' ),
	seo: __( 'SEO', 'post-quality-manager' ),
	audit: __( 'Audit', 'post-quality-manager' ),
	rewrite: __( 'Rewrite', 'post-quality-manager' ),
};

/**
 * Display label for a command key — carries the SEO casing ('seo' → 'SEO').
 * Unknown keys pass through raw so new commands are never blank.
 *
 * @param {string} command Command key, e.g. 'seo'.
 * @return {string} Translated label.
 */
export function commandLabel( command ) {
	return COMMAND_LABELS[ command ] || command;
}

/**
 * Title attribute for a plan-locked command button (visible but locked —
 * the plan gate; role-hidden commands render no button at all).
 */
export const LOCKED_TITLE = __(
	'Available with Pro — Review and Takeaways stay free.',
	'post-quality-manager'
);

/**
 * Visually-hidden suffix appended to a locked button's accessible name,
 * so screen readers hear why the button is disabled.
 */
export const LOCKED_SUFFIX = __(
	'(available with Pro)',
	'post-quality-manager'
);
