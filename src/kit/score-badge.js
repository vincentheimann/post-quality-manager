/**
 * ScoreBadge (story 5.2) — numeric score chip: 71+ green, 41–70 orange,
 * 1–40 red, 0 or none grey. The `pqm-score-badge` / `pqm-score-*` rules live
 * in the kit's style.css; pqm-admin.css (Settings and n8n Workflow only)
 * no longer carries them since story 5.7.
 *
 * Story 5.10: an optional `label` renders "label value" (the Yoast pair)
 * and an optional `title` becomes the span's tooltip.
 */

function scoreClass( score ) {
	if ( score === null || score === undefined ) {
		return 'none';
	}
	if ( score >= 71 ) {
		return 'green';
	}
	if ( score >= 41 ) {
		return 'orange';
	}
	if ( score > 0 ) {
		return 'red';
	}
	return 'none';
}

export default function ScoreBadge( { score, label, title } ) {
	const value = score === null || score === undefined ? '—' : score;

	return (
		<span
			className={ `pqm-score-badge pqm-score-${ scoreClass( score ) }` }
			title={ title }
		>
			{ label ? `${ label } ${ value }` : value }
		</span>
	);
}
