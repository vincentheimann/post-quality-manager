/**
 * StatTiles (story 5.6) — the Dashboard's tiles mirror the Post Reviews
 * chips (owner decision 2026-10-05): each status tile opens exactly one
 * chip (`?status=…`) and shows that chip's number from the same `counts`
 * block, so tile and chip agree by construction. The Average score tile
 * opens the list sorted by score, lowest first.
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { STATUS_NONE } from '../kit/copy';
import StatusBadge from '../kit/status-badge';

/** Most urgent first, like the queue. */
const STATUS_TILES = [
	'major_revision',
	'needs_revision',
	STATUS_NONE,
	'ship',
];

/**
 * Relative to wp-admin/admin.php, where the Dashboard lives.
 *
 * @param {Object} args Post Reviews query args, e.g. `{ status: 'ship' }`.
 * @return {string} Post Reviews URL.
 */
function postReviewsUrl( args ) {
	return addQueryArgs( 'admin.php', { page: 'postquality-posts', ...args } );
}

/**
 * @param {Object}      props
 * @param {number|null} props.average Average score, null without analyzed posts.
 * @param {Object|null} props.counts  The `counts` block of GET /posts.
 */
export default function StatTiles( { average, counts } ) {
	return (
		<div className="pqm-tiles">
			<a
				className="pqm-tile"
				href={ postReviewsUrl( { orderby: 'score', order: 'asc' } ) }
			>
				<span className="pqm-tile-label">
					{ __( 'Average score', 'post-quality-manager' ) }
				</span>
				<span className="pqm-tile-value">
					{ average === null || average === undefined
						? '—'
						: average }
				</span>
			</a>
			{ STATUS_TILES.map( ( status ) => (
				<a
					key={ status }
					className="pqm-tile"
					href={ postReviewsUrl( { status } ) }
				>
					<span className="pqm-tile-label">
						<StatusBadge status={ status } />
					</span>
					<span className="pqm-tile-value">
						{ counts ? counts[ status ] : '—' }
					</span>
				</a>
			) ) }
		</div>
	);
}
