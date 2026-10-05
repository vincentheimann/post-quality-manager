/**
 * Read-only Post Reviews table (stories 5.1 + 5.2).
 *
 * Fetches GET post-quality/v1/posts and renders a native WP admin table
 * using the shared kit components (src/kit/): StatusBadge, ScoreBadge,
 * ProgressPills and QuotaNote, fed by the response's `viewer` block.
 * In wp-admin, core preconfigures @wordpress/api-fetch with the REST root
 * and nonce middleware, so no localize bridge is needed. Filters, sorting,
 * pagination, bulk actions and the detail panel are later stories (5.3–5.5).
 */
import apiFetch from '@wordpress/api-fetch';
import { Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import ProgressPills from './kit/progress-pills';
import QuotaNote from './kit/quota-note';
import ScoreBadge from './kit/score-badge';
import StatusBadge from './kit/status-badge';

function PostRow( { item } ) {
	return (
		<tr className="pqm-post-row">
			<td>
				<strong>
					{ item.edit_link ? (
						<a href={ item.edit_link }>{ item.title }</a>
					) : (
						item.title
					) }
				</strong>
			</td>
			<td className="pqm-cell-score">
				<ScoreBadge score={ item.latest_score } />
			</td>
			<td className="pqm-cell-status">
				<StatusBadge status={ item.latest_status } />
			</td>
			<td className="pqm-cell-progress">
				<ProgressPills commands={ item.commands || [] } />
			</td>
			<td className="pqm-cell-date">
				{ item.latest_reviewed_display || (
					<em>{ __( 'Never', 'post-quality-manager' ) }</em>
				) }
			</td>
		</tr>
	);
}

export default function PostReviewsTable() {
	const [ items, setItems ] = useState( null );
	const [ total, setTotal ] = useState( 0 );
	const [ viewer, setViewer ] = useState( null );
	const [ error, setError ] = useState( false );

	useEffect( () => {
		// per_page=100 is the route's clamp maximum — load as much as one
		// request allows; pagination UI is story 5.3.
		apiFetch( { path: '/post-quality/v1/posts?per_page=100' } )
			.then( ( data ) => {
				setItems( ( data && data.items ) || [] );
				setTotal( ( data && data.total ) || 0 );
				setViewer( ( data && data.viewer ) || null );
			} )
			.catch( () => setError( true ) );
	}, [] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __(
					'Could not load your posts. Check that the site REST API is reachable, then reload this page.',
					'post-quality-manager'
				) }
			</Notice>
		);
	}

	if ( items === null ) {
		return (
			<p>
				<Spinner />{ ' ' }
				{ __( 'Loading posts…', 'post-quality-manager' ) }
			</p>
		);
	}

	return (
		<>
			{ /* Filter-bar slot (story 5.3 fills the left side); the quota
			     note rides right-aligned and renders only for eligible users. */ }
			<div className="pqm-table-topbar">
				<QuotaNote viewer={ viewer } />
			</div>
			<table className="widefat fixed striped pqm-posts-table">
				<thead>
					<tr>
						<th scope="col" className="pqm-col-title">
							{ __( 'Post', 'post-quality-manager' ) }
						</th>
						<th scope="col" className="pqm-col-score">
							{ __( 'Score', 'post-quality-manager' ) }
						</th>
						<th scope="col" className="pqm-col-status">
							{ __( 'Status', 'post-quality-manager' ) }
						</th>
						<th scope="col" className="pqm-col-progress">
							{ __( 'Progress', 'post-quality-manager' ) }
						</th>
						<th scope="col" className="pqm-col-date">
							{ __( 'Last Reviewed', 'post-quality-manager' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ items.length ? (
						items.map( ( item ) => (
							<PostRow key={ item.post_id } item={ item } />
						) )
					) : (
						<tr>
							<td colSpan={ 5 }>
								{ __(
									'No reviews found. Trigger a review to get started.',
									'post-quality-manager'
								) }
							</td>
						</tr>
					) }
				</tbody>
			</table>
			{ total > items.length && (
				<p className="description">
					{ sprintf(
						/* translators: 1: number of posts shown, 2: total number of posts */
						__(
							'Showing the %1$d most recently reviewed of %2$d posts.',
							'post-quality-manager'
						),
						items.length,
						total
					) }
				</p>
			) }
		</>
	);
}
