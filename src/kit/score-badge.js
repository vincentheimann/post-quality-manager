/**
 * ScoreBadge (story 5.2) — numeric score chip: 71+ green, 41–70 orange,
 * 1–40 red, 0 or none grey. The `pqm-score-badge` / `pqm-score-*` rules live
 * in the kit's style.css (moved from pqm-admin.css in story 5.7).
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

export default function ScoreBadge( { score } ) {
	return (
		<span className={ `pqm-score-badge pqm-score-${ scoreClass( score ) }` }>
			{ score === null || score === undefined ? '—' : score }
		</span>
	);
}
