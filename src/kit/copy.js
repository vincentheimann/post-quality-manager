/**
 * Canonical command + gate copy (story 5.2) — the one JS source for
 * command order, status labels, plan-gate wording and the copy both React
 * pages share (Post Reviews and the Dashboard). Command labels are not
 * kept here: they come translated from the PHP registry
 * (PQM_Features::registry()), printed as window.pqmCommandLabels before
 * the bundle. Copy follows docs/brand.md; every string here flows
 * through @wordpress/i18n.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Canonical command order — the order pills, tabs and buttons render in:
 * the six commands, kept only where the registry labels PHP prints
 * (window.pqmCommandLabels, from PQM_Features::registry()) list them, so
 * the Free build (ADR 0014, story 54) renders no paid pill, tab or button.
 * Without the print (tests), all six.
 */
const ALL_COMMANDS = [
	'review',
	'takeaways',
	'sources',
	'seo',
	'audit',
	'rewrite',
];
export const COMMAND_ORDER = ALL_COMMANDS.filter(
	( command ) =>
		! window.pqmCommandLabels || command in window.pqmCommandLabels
);

/**
 * Display label for a command key, read from the registry labels PHP
 * prints (window.pqmCommandLabels) — carries the SEO casing ('seo' → 'SEO').
 * Unknown keys pass through raw so new commands are never blank.
 *
 * @param {string} command Command key, e.g. 'seo'.
 * @return {string} Translated label.
 */
export function commandLabel( command ) {
	return ( window.pqmCommandLabels || {} )[ command ] || command;
}

/**
 * Commands free on every plan (ADR 0003) — the same pair LOCKED_TITLE
 * names; the detail panel's "not run yet" copy reads it.
 */
export const FREE_COMMANDS = [ 'review', 'takeaways' ];

/**
 * Label for a trigger button (story 5.4): "Run Review" before a command
 * has ever run for the post, "Re-run Review" afterwards.
 *
 * @param {string}  command Command key.
 * @param {boolean} hasRun  Whether a stored row exists for this command.
 * @return {string} Translated label.
 */
export function runLabel( command, hasRun = false ) {
	const label = commandLabel( command );
	return hasRun
		? sprintf(
				/* translators: %s: agent command label, e.g. "Review" */
				__( 'Re-run %s', 'post-quality-manager' ),
				label
		  )
		: sprintf(
				/* translators: %s: agent command label, e.g. "Review" */
				__( 'Run %s', 'post-quality-manager' ),
				label
		  );
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
	// Stored by save_review on an n8n error; neutral wording per brand.md.
	error: __( 'Could not complete', 'post-quality-manager' ),
	// Stored by the template's rewrite confirmation; shown in History only.
	rewritten: __( 'Rewrite applied', 'post-quality-manager' ),
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

/** Link to the post's editor — the detail panel and the Fix-next queue. */
export const OPEN_IN_EDITOR = __( 'Open in Editor', 'post-quality-manager' );
