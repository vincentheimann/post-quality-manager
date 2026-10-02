/**
 * Read-only Post Reviews table (tracer bullet, story 5.1).
 *
 * Fetches GET post-quality/v1/posts and renders a native WP admin table.
 * In wp-admin, core preconfigures @wordpress/api-fetch with the REST root
 * and nonce middleware, so no localize bridge is needed. Filters, sorting,
 * pagination, bulk actions and the detail panel are later stories (5.3–5.5).
 */
import apiFetch from '@wordpress/api-fetch';
import { Dashicon, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Status language (ADR 0005): icon + label + WP admin palette color —
 * never color alone, no emoji. Keys match the stored review status values.
 */
const STATUS_MAP = {
	ship: {
		icon: 'yes-alt',
		color: '#00a32a',
		label: __( 'Ready to Ship', 'post-quality-manager' ),
	},
	needs_revision: {
		icon: 'flag',
		color: '#dba617',
		label: __( 'Needs Revision', 'post-quality-manager' ),
	},
	major_revision: {
		icon: 'warning',
		color: '#d63638',
		label: __( 'Major Revision', 'post-quality-manager' ),
	},
	processing: {
		icon: 'update',
		color: '#2271b1',
		label: __( 'Processing…', 'post-quality-manager' ),
	},
};

const STATUS_NONE = {
	icon: 'minus',
	color: '#646970',
	label: __( 'Not analyzed', 'post-quality-manager' ),
};

/** Canonical command order + labels for the progress chips. */
const COMMAND_ORDER = [ 'review', 'takeaways', 'sources', 'seo', 'audit', 'rewrite' ];
const COMMAND_LABELS = {
	review: __( 'Review', 'post-quality-manager' ),
	takeaways: __( 'Takeaways', 'post-quality-manager' ),
	sources: __( 'Sources', 'post-quality-manager' ),
	seo: __( 'SEO', 'post-quality-manager' ),
	audit: __( 'Audit', 'post-quality-manager' ),
	rewrite: __( 'Rewrite', 'post-quality-manager' ),
};

/** Mirror of PQM_Admin::score_class() so the existing badge CSS applies. */
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

function StatusBadge( { status } ) {
	const config = ( status && STATUS_MAP[ status ] ) || STATUS_NONE;
	// An unknown but non-empty status still shows its raw value, never nothing.
	const label = status && ! STATUS_MAP[ status ] ? status : config.label;

	return (
		<span style={ { color: config.color } }>
			<Dashicon icon={ config.icon } aria-hidden="true" />{ ' ' }
			{ label }
		</span>
	);
}

function ScoreBadge( { score } ) {
	return (
		<span className={ `pqm-score-badge pqm-score-${ scoreClass( score ) }` }>
			{ score === null || score === undefined ? '—' : score }
		</span>
	);
}

function ProgressChips( { commands } ) {
	const ran = [
		...COMMAND_ORDER.filter( ( c ) => commands.includes( c ) ),
		...commands.filter( ( c ) => ! COMMAND_ORDER.includes( c ) ),
	];

	if ( ! ran.length ) {
		return (
			<div className="pqm-pills">
				<span className="pqm-pill pqm-pill-pending">
					— { __( 'Not analyzed', 'post-quality-manager' ) }
				</span>
			</div>
		);
	}

	return (
		<div className="pqm-pills">
			{ ran.map( ( cmd ) => (
				<span key={ cmd } className="pqm-pill pqm-pill-done">
					✓ { COMMAND_LABELS[ cmd ] || cmd }
				</span>
			) ) }
		</div>
	);
}

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
				<ProgressChips commands={ item.commands || [] } />
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
	const [ error, setError ] = useState( false );

	useEffect( () => {
		// per_page=100 is the route's clamp maximum — load as much as one
		// request allows; pagination UI is story 5.3.
		apiFetch( { path: '/post-quality/v1/posts?per_page=100' } )
			.then( ( data ) => {
				setItems( ( data && data.items ) || [] );
				setTotal( ( data && data.total ) || 0 );
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
