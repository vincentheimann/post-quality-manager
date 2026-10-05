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
 * The "never reviewed" status key — the REST `status=none` sentinel and
 * the `counts.none` key; stored reviews never carry it.
 */
export const STATUS_NONE = 'none';

/** Status labels, sentence case per the mockup; keys match the stored review status values. */
const STATUS_LABELS = {
	ship: __( 'Ready to ship', 'post-quality-manager' ),
	needs_revision: __( 'Needs revision', 'post-quality-manager' ),
	major_revision: __( 'Major revision', 'post-quality-manager' ),
	processing: __( 'Processing…', 'post-quality-manager' ),
	[ STATUS_NONE ]: __( 'Not analyzed', 'post-quality-manager' ),
};

/**
 * Display label for a review status — the one source StatusBadge and the
 * filter chips share (story 5.3), so they can never drift. Empty / null
 * reads as "not analyzed"; an unknown but non-empty status passes through
 * raw so a new status is never blank.
 *
 * @param {string|null|undefined} status Stored status, '' / null for never reviewed.
 * @return {string} Translated label.
 */
export function statusLabel( status ) {
	if ( ! status ) {
		return STATUS_LABELS[ STATUS_NONE ];
	}
	return STATUS_LABELS[ status ] || status;
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
