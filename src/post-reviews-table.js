/**
 * Read-only Post Reviews table (stories 5.1 + 5.2 + 5.3).
 *
 * Fetches GET post-quality/v1/posts and renders a native WP admin table
 * using the shared kit components (src/kit/): StatusBadge, ScoreBadge,
 * ProgressPills and QuotaNote, fed by the response's `viewer` block.
 * In wp-admin, core preconfigures @wordpress/api-fetch with the REST root
 * and nonce middleware, so no localize bridge is needed.
 *
 * Story 5.3: status chips, score band, sortable headers and 20-per-page
 * pagination — all server-side, one request per state change. The state
 * (status, min_score, max_score, orderby, order, paged) lives in the URL
 * query string so Dashboard tiles and links can deep-link to a filtered
 * view and a reload restores it. Bulk actions and the detail panel are
 * later stories (5.4–5.5).
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs, getQueryArgs, removeQueryArgs } from '@wordpress/url';

import { STATUS_NONE } from './kit/copy';
import ProgressPills from './kit/progress-pills';
import ScoreBadge from './kit/score-badge';
import StatusBadge from './kit/status-badge';
import FilterBar from './post-reviews/filter-bar';
import Pagination from './post-reviews/pagination';
import SortableHeader from './post-reviews/sortable-header';

const PER_PAGE = 20;
const STATUSES = [ 'ship', 'needs_revision', 'major_revision', 'processing', STATUS_NONE ];
const ORDERBYS = [ 'title', 'score', 'status', 'reviewed_at' ];

/** URL ⇄ state defaults; a param at its default is dropped from the URL. */
const DEFAULT_QUERY = {
	status: '',
	min_score: null,
	max_score: null,
	orderby: 'reviewed_at',
	order: 'desc',
	paged: 1,
	// Legacy ?filter=needs_review from the PHP Dashboard card — honoured
	// until story 5.6 replaces the card; shown as "no chip pressed".
	filter: '',
};
const QUERY_KEYS = Object.keys( DEFAULT_QUERY );

function scoreParam( value ) {
	const n = parseInt( value, 10 );
	return Number.isInteger( n ) && n >= 0 && n <= 100 ? n : null;
}

/** Initial state from window.location — unknown values fall back to defaults, like the REST route. */
function readQueryFromUrl() {
	const args = getQueryArgs( window.location.href );
	const paged = parseInt( args.paged, 10 );

	return {
		status: STATUSES.includes( args.status ) ? args.status : '',
		min_score: scoreParam( args.min_score ),
		max_score: scoreParam( args.max_score ),
		orderby: ORDERBYS.includes( args.orderby ) ? args.orderby : DEFAULT_QUERY.orderby,
		order: args.order === 'asc' ? 'asc' : 'desc',
		paged: Number.isInteger( paged ) && paged > 1 ? paged : 1,
		filter: args.filter === 'needs_review' ? 'needs_review' : '',
	};
}

/** Only the non-default params, as strings, for the URL and the request. */
function activeParams( query ) {
	const params = {};
	QUERY_KEYS.forEach( ( key ) => {
		if ( query[ key ] !== DEFAULT_QUERY[ key ] ) {
			params[ key ] = query[ key ];
		}
	} );
	return params;
}

function writeQueryToUrl( query ) {
	const url = addQueryArgs(
		removeQueryArgs( window.location.href, ...QUERY_KEYS ),
		activeParams( query )
	);
	window.history.replaceState( null, '', url );
}

function restPath( query ) {
	const { paged, ...rest } = activeParams( query );
	return addQueryArgs( '/post-quality/v1/posts', {
		per_page: PER_PAGE,
		page: paged || 1,
		...rest,
	} );
}

function hasActiveFilters( query ) {
	return (
		query.status !== '' ||
		query.min_score !== null ||
		query.max_score !== null ||
		query.filter !== ''
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
	const [ query, setQuery ] = useState( readQueryFromUrl );
	const [ items, setItems ] = useState( null );
	const [ total, setTotal ] = useState( 0 );
	const [ pages, setPages ] = useState( 0 );
	const [ counts, setCounts ] = useState( null );
	const [ viewer, setViewer ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( false );

	useEffect( () => {
		let cancelled = false; // a newer request supersedes this one
		writeQueryToUrl( query );
		setIsLoading( true );

		apiFetch( { path: restPath( query ) } )
			.then( ( data ) => {
				if ( cancelled ) {
					return;
				}
				setItems( ( data && data.items ) || [] );
				setTotal( ( data && data.total ) || 0 );
				setPages( ( data && data.pages ) || 0 );
				setCounts( ( data && data.counts ) || null );
				setViewer( ( data && data.viewer ) || null );
				setIsLoading( false );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setError( true );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ query ] );

	// Any filter or sort change restarts at page 1; an explicit filter action
	// (chip or band) also replaces the legacy needs_review view.
	const setStatus = ( status ) =>
		setQuery( { ...query, status, filter: '', paged: 1 } );
	const setBand = ( min_score, max_score ) =>
		setQuery( { ...query, min_score, max_score, filter: '', paged: 1 } );
	const setSort = ( orderby, order ) =>
		setQuery( { ...query, orderby, order, paged: 1 } );
	const setPage = ( paged ) => setQuery( { ...query, paged } );
	const clearFilters = () =>
		setQuery( {
			...query,
			status: '',
			min_score: null,
			max_score: null,
			filter: '',
			paged: 1,
		} );

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

	const sortProps = {
		orderby: query.orderby,
		order: query.order,
		onSort: setSort,
	};

	return (
		<>
			<FilterBar
				status={ query.status }
				minScore={ query.min_score }
				maxScore={ query.max_score }
				counts={ counts }
				viewer={ viewer }
				legacyFilter={ query.filter !== '' }
				onStatusChange={ setStatus }
				onBandChange={ setBand }
			/>
			{ /* Refetches keep the current rows visible; the table only dims. */ }
			<p className="pqm-table-updating" role="status" aria-live="polite">
				{ isLoading && (
					<>
						<Spinner />{ ' ' }
						{ __( 'Updating…', 'post-quality-manager' ) }
					</>
				) }
			</p>
			<table
				className={ `widefat fixed striped pqm-posts-table${
					isLoading ? ' is-loading' : ''
				}` }
				aria-busy={ isLoading }
			>
				<thead>
					<tr>
						<SortableHeader
							column="title"
							label={ __( 'Post', 'post-quality-manager' ) }
							className="pqm-col-title"
							{ ...sortProps }
						/>
						<SortableHeader
							column="score"
							label={ __( 'Score', 'post-quality-manager' ) }
							className="pqm-col-score"
							{ ...sortProps }
						/>
						<SortableHeader
							column="status"
							label={ __( 'Status', 'post-quality-manager' ) }
							className="pqm-col-status"
							{ ...sortProps }
						/>
						<th scope="col" className="pqm-col-progress">
							{ __( 'Analyzed', 'post-quality-manager' ) }
						</th>
						<SortableHeader
							column="reviewed_at"
							label={ __( 'Last reviewed', 'post-quality-manager' ) }
							className="pqm-col-date"
							{ ...sortProps }
						/>
					</tr>
				</thead>
				<tbody>
					{ items.length > 0 &&
						items.map( ( item ) => (
							<PostRow key={ item.post_id } item={ item } />
						) ) }
					{ items.length === 0 &&
						( hasActiveFilters( query ) || query.paged > 1 ? (
							<tr>
								<td colSpan={ 5 } className="pqm-empty-filtered">
									{ __(
										'No posts match these filters.',
										'post-quality-manager'
									) }{ ' ' }
									<Button
										variant="link"
										onClick={ clearFilters }
									>
										{ __(
											'Clear filters',
											'post-quality-manager'
										) }
									</Button>
								</td>
							</tr>
						) : (
							<tr>
								<td colSpan={ 5 }>
									{ __(
										'No reviews found. Trigger a review to get started.',
										'post-quality-manager'
									) }
								</td>
							</tr>
						) ) }
				</tbody>
			</table>
			<Pagination
				total={ total }
				page={ query.paged }
				pages={ pages }
				onChange={ setPage }
			/>
		</>
	);
}
