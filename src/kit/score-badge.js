/**
 * ScoreBadge (story 5.2) — numeric score chip. Thresholds mirror
 * PQM_Admin::score_class(); class names stay `pqm-score-badge` /
 * `pqm-score-*` so the shared pqm-admin.css badge rules keep applying
 * (that CSS loads on every pqm- admin page).
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
