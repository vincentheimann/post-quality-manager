/**
 * Post Reviews table (stories 5.1 – 5.5, 5.10).
 *
 * Fetches GET post-quality/v1/posts and renders a native WP admin table
 * using the shared kit components (src/kit/): StatusBadge, ScoreBadge,
 * ProgressPills and QuotaNote, fed by the response's `viewer` block.
 * In wp-admin, core preconfigures `@wordpress/api-fetch` with the REST root
 * and nonce middleware, so no localize bridge is needed.
 *
 * Story 5.3: status chips, score band, sortable headers and 20-per-page
 * pagination — all server-side, one request per state change. The state
 * (status, min_score, max_score, orderby, order, paged) lives in the URL
 * query string so Dashboard tiles and links can deep-link to a filtered
 * view and a reload restores it.
 *
 * Story 5.4: a per-row Details toggle opens the DetailPanel; trigger
 * buttons call POST /trigger-review directly (no confirm for a single
 * run); the kit's useSingleRun hook (usePolling inside) watches the
 * list's server-derived `in_flight` field (3 s, auto-resumes after a
 * reload) and refetches the current query silently so rows update in
 * place; outcomes surface as toasts.
 *
 * Story 5.5: viewers who can run Review get a checkbox column (page-local
 * selection, cleared on any query change) and the BulkBar, which queues
 * the selected posts through the same startRun() path as a single run.
 *
 * Story 5.10: pending posts carry core's "— Pending" post-state marker
 * after the title; while Yoast is active (`yoast_active`) a display-only
 * Yoast SEO column after Score shows the item's `yoast` scores as kit
 * ScoreBadges.
 *
 * Ticket 32: while n8n is not connected (`viewer.connected` false) a
 * persistent notice above the table names the next step — the n8n
 * Workflow page for `can_manage_settings`, an administrator otherwise —
 * and the Run / Review selected buttons are disabled. A failed single-run
 * start shows inline in the open DetailPanel, not as a toast.
 *
 * Story 109: the Post Quality meta box links here with `postquality_post`
 * (POST_PARAM): the list narrows to that post (`post_id` on GET /posts)
 * with its detail panel open; "Show all posts" clears it like any filter.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { Fragment, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs, getQueryArgs, removeQueryArgs } from '@wordpress/url';

import { STATUS_NONE } from './kit/copy';
import NotConnectedNotice from './kit/not-connected-notice';
import ProgressPills from './kit/progress-pills';
import { POSTS_PATH, canRender, inFlight, isConnected } from './kit/runs';
import ScoreBadge from './kit/score-badge';
import StatusBadge from './kit/status-badge';
import Toasts from './kit/toasts';
import useSingleRun from './kit/use-single-run';
import BulkBar from './post-reviews/bulk-bar';
import DetailPanel from './post-reviews/detail-panel';
import FilterBar from './post-reviews/filter-bar';
import Pagination from './post-reviews/pagination';
import SortableHeader from './post-reviews/sortable-header';
import './post-reviews/style.css';

const PER_PAGE = 20;
// No `processing`: placeholders never form a post's latest status (5.4), so that filter would be empty.
const STATUSES = [ 'ship', 'needs_revision', 'major_revision', STATUS_NONE ];
const ORDERBYS = [ 'title', 'score', 'status', 'reviewed_at' ];

/** URL ⇄ state defaults; a param at its default is dropped from the URL. */
const DEFAULT_QUERY = {
	status: '',
	min_score: null,
	max_score: null,
	orderby: 'reviewed_at',
	order: 'desc',
	paged: 1,
};
const QUERY_KEYS = Object.keys( DEFAULT_QUERY );

/**
 * The meta box's link (story 109): one post, its panel open. Kept out of
 * DEFAULT_QUERY because the URL and the REST route name it differently
 * (`post` in state, `post_id` on GET /posts).
 */
const POST_PARAM = 'postquality_post';

function scoreParam( value ) {
	const n = parseInt( value, 10 );
	return Number.isInteger( n ) && n >= 0 && n <= 100 ? n : null;
}

function postParam( value ) {
	const n = parseInt( value, 10 );
	return Number.isInteger( n ) && n > 0 ? n : null;
}

/** Initial state from window.location — unknown values fall back to defaults, like the REST route. */
function readQueryFromUrl() {
	const args = getQueryArgs( window.location.href );
	const paged = parseInt( args.paged, 10 );

	return {
		status: STATUSES.includes( args.status ) ? args.status : '',
		min_score: scoreParam( args.min_score ),
		max_score: scoreParam( args.max_score ),
		orderby: ORDERBYS.includes( args.orderby )
			? args.orderby
			: DEFAULT_QUERY.orderby,
		order: args.order === 'asc' ? 'asc' : 'desc',
		paged: Number.isInteger( paged ) && paged > 1 ? paged : 1,
		post: postParam( args[ POST_PARAM ] ),
	};
}

/**
 * Only the non-default params, as strings, for the URL and the request.
 *
 * @param {Object} query Table query state.
 * @return {Object} Params that differ from DEFAULT_QUERY.
 */
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
		// 'filter': strip the retired ?filter=needs_review deep link too.
		removeQueryArgs(
			window.location.href,
			...QUERY_KEYS,
			POST_PARAM,
			'filter'
		),
		{
			...activeParams( query ),
			...( query.post ? { [ POST_PARAM ]: query.post } : {} ),
		}
	);
	window.history.replaceState( null, '', url );
}

/**
 * The REST path for a query — also the identity the polling hook keys on.
 *
 * @param {Object} query Table query state.
 * @return {string} GET /posts path with the query's params.
 */
function restPath( query ) {
	const { paged, ...rest } = activeParams( query );
	return addQueryArgs( POSTS_PATH, {
		per_page: PER_PAGE,
		page: paged || 1,
		...rest,
		...( query.post ? { post_id: query.post } : {} ),
	} );
}

function hasActiveFilters( query ) {
	return (
		query.status !== '' ||
		query.min_score !== null ||
		query.max_score !== null ||
		query.post !== null
	);
}

/**
 * Yoast SEO + readability badges; one grey "—" while Yoast has no score at all.
 *
 * @param {Object}      props
 * @param {Object|null} props.yoast The item's `yoast` block (`seo`, `readability`).
 */
function YoastBadges( { yoast } ) {
	const { seo = null, readability = null } = yoast || {};

	if ( seo === null && readability === null ) {
		return (
			<ScoreBadge
				score={ null }
				title={ __(
					'No Yoast analysis yet. Open the post in the editor to generate one.',
					'post-quality-manager'
				) }
			/>
		);
	}

	return (
		<>
			<ScoreBadge
				score={ seo }
				label={ __( 'SEO', 'post-quality-manager' ) }
				title={ __( 'Yoast SEO score', 'post-quality-manager' ) }
			/>
			<ScoreBadge
				score={ readability }
				label={ __( 'Readability', 'post-quality-manager' ) }
				title={ __(
					'Yoast readability score',
					'post-quality-manager'
				) }
			/>
		</>
	);
}

function PostRow( {
	item,
	isOpen,
	isAwaited,
	onToggle,
	canSelect,
	isSelected,
	onSelect,
	yoastActive,
} ) {
	const detailId = `pqm-detail-${ item.post_id }`;

	return (
		<tr className="pqm-post-row">
			{ canSelect && (
				<td className="pqm-cell-select">
					{ /* A post whose review is in flight cannot be selected. */ }
					<input
						type="checkbox"
						aria-label={ sprintf(
							/* translators: %s: post title */
							__( 'Select: %s', 'post-quality-manager' ),
							item.title
						) }
						checked={ isSelected }
						disabled={ inFlight( item ) }
						onChange={ onSelect }
					/>
				</td>
			) }
			<td className="pqm-cell-title">
				<strong>
					{ item.edit_link ? (
						<a href={ item.edit_link }>{ item.title }</a>
					) : (
						item.title
					) }
					{ /* Core's own post-state markup, as the Posts list renders "— Draft". */ }
					{ item.wp_post_status === 'pending' && (
						<>
							{ ' — ' }
							<span className="post-state">
								{ __( 'Pending', 'post-quality-manager' ) }
							</span>
						</>
					) }
				</strong>
			</td>
			<td className="pqm-cell-score">
				<ScoreBadge score={ item.latest_score } />
			</td>
			{ yoastActive && (
				<td className="pqm-cell-yoast">
					<YoastBadges yoast={ item.yoast } />
				</td>
			) }
			<td className="pqm-cell-status">
				{ /* A running review shows Processing; the stored latest_*
				     (and so score, order and membership) stay its last result. */ }
				<StatusBadge
					status={
						inFlight( item ) ? 'processing' : item.latest_status
					}
				/>
			</td>
			<td className="pqm-cell-progress">
				<ProgressPills commands={ item.commands || [] } />
			</td>
			<td className="pqm-cell-date">
				{ item.latest_reviewed_display || (
					<em>{ __( 'Never', 'post-quality-manager' ) }</em>
				) }
			</td>
			<td className="pqm-cell-actions">
				{ /* Closed + actively awaited: wait for the result (mockup);
				     an open panel can always be hidden again. After the
				     3-minute give-up the toggle returns. */ }
				<Button
					variant="secondary"
					className="pqm-details-toggle"
					aria-expanded={ isOpen }
					aria-controls={ detailId }
					disabled={ ! isOpen && isAwaited }
					onClick={ onToggle }
				>
					{ isOpen
						? __( 'Hide details', 'post-quality-manager' )
						: __( 'Details', 'post-quality-manager' ) }
				</Button>
			</td>
		</tr>
	);
}

export default function PostReviewsTable() {
	const [ query, setQuery ] = useState( readQueryFromUrl );
	const [ items, setItems ] = useState( null );
	const [ loadedKey, setLoadedKey ] = useState( '' ); // the query the items answer
	const [ total, setTotal ] = useState( 0 );
	const [ pages, setPages ] = useState( 0 );
	const [ counts, setCounts ] = useState( null );
	const [ viewer, setViewer ] = useState( null );
	const [ yoastActive, setYoastActive ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( false );

	// Story 5.4 state: the open panel and the panel's refetch version.
	// The single-run plumbing (refetch trigger, busy post, polling, toasts)
	// is the kit's useSingleRun; a run start or completion bumps the panel.
	// The meta box's link (story 109) opens its post's panel.
	const [ openPostId, setOpenPostId ] = useState( query.post );
	const [ detailVersion, setDetailVersion ] = useState( 0 );
	const bumpDetail = () => setDetailVersion( ( v ) => v + 1 );
	const {
		refreshKey,
		settlePending,
		awaited,
		pendingPostId,
		startRun,
		trigger,
		startError,
		dismissStartError,
		toasts,
		addToast,
		removeToast,
	} = useSingleRun( items, loadedKey, {
		onStarted: bumpDetail,
		onSettled: bumpDetail,
	} );
	// Every refreshKey change is a run refetch (polling tick or
	// post-trigger): silent — no "Updating…" announcement every 3 s.
	const seenRefreshKey = useRef( refreshKey );
	// Story 5.5: selected post ids, page-local.
	const [ selected, setSelected ] = useState( () => new Set() );

	useEffect( () => {
		let cancelled = false; // a newer request supersedes this one
		const silent = seenRefreshKey.current !== refreshKey;
		seenRefreshKey.current = refreshKey;
		const path = restPath( query );

		writeQueryToUrl( query );
		if ( ! silent ) {
			setIsLoading( true );
		}

		apiFetch( { path } )
			.then( ( data ) => {
				if ( cancelled ) {
					return;
				}
				setItems( ( data && data.items ) || [] );
				setLoadedKey( path );
				setTotal( ( data && data.total ) || 0 );
				setPages( ( data && data.pages ) || 0 );
				setCounts( ( data && data.counts ) || null );
				setViewer( ( data && data.viewer ) || null );
				setYoastActive( !! ( data && data.yoast_active ) );
				setIsLoading( false );
				settlePending( refreshKey );
			} )
			.catch( () => {
				if ( cancelled ) {
					return;
				}
				// A silent (polling) failure keeps the rows; the next tick
				// retries. The busy flag never outlives its request.
				settlePending( refreshKey );
				if ( ! silent ) {
					setError( true );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ query, refreshKey, settlePending ] );

	// Selection is page-local: any filter, sort or page change clears it.
	useEffect( () => {
		setSelected( ( prev ) => ( prev.size ? new Set() : prev ) );
	}, [ query ] );

	// A row whose review went in flight leaves the selection for good, so it
	// does not come back ticked once the review completes.
	useEffect( () => {
		if ( ! items ) {
			return;
		}
		setSelected( ( prev ) => {
			const busy = items.filter(
				( item ) => prev.has( item.post_id ) && inFlight( item )
			);
			if ( ! busy.length ) {
				return prev;
			}
			const next = new Set( prev );
			busy.forEach( ( item ) => next.delete( item.post_id ) );
			return next;
		} );
	}, [ items ] );

	// Any filter or sort change restarts at page 1.
	const setStatus = ( status ) => setQuery( { ...query, status, paged: 1 } );
	/* eslint-disable camelcase -- REST query arg names. */
	const setBand = ( min_score, max_score ) =>
		setQuery( { ...query, min_score, max_score, paged: 1 } );
	/* eslint-enable camelcase */
	const setSort = ( orderby, order ) =>
		setQuery( { ...query, orderby, order, paged: 1 } );
	const setPage = ( paged ) => setQuery( { ...query, paged } );
	// Also "Show all posts" for the meta box's one-post link (story 109).
	const clearFilters = () =>
		setQuery( {
			...query,
			status: '',
			min_score: null,
			max_score: null,
			post: null,
			paged: 1,
		} );
	const showAllPosts = __( 'Show all posts', 'post-quality-manager' );
	const toggleDetails = ( postId ) =>
		setOpenPostId( ( open ) => ( open === postId ? null : postId ) );

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
				<Spinner /> { __( 'Loading posts…', 'post-quality-manager' ) }
			</p>
		);
	}

	// Bulk Review (5.5): same rule as the panel's Review button.
	const canBulk = canRender( viewer, 'review' );
	const columns = 6 + ( canBulk ? 1 : 0 ) + ( yoastActive ? 1 : 0 );
	const selectable = items.filter( ( item ) => ! inFlight( item ) );
	const selection = selectable.filter( ( item ) =>
		selected.has( item.post_id )
	);
	const allSelected =
		selectable.length > 0 && selection.length === selectable.length;
	const toggleSelect = ( postId ) =>
		setSelected( ( prev ) => {
			const next = new Set( prev );
			if ( ! next.delete( postId ) ) {
				next.add( postId );
			}
			return next;
		} );
	const toggleSelectAll = () =>
		setSelected(
			allSelected
				? new Set()
				: new Set( selectable.map( ( item ) => item.post_id ) )
		);

	const sortProps = {
		orderby: query.orderby,
		order: query.order,
		onSort: setSort,
	};

	return (
		<>
			{ viewer && viewer.can_trigger && ! isConnected( viewer ) && (
				<NotConnectedNotice
					canManage={ !! viewer.can_manage_settings }
				/>
			) }
			<FilterBar
				status={ query.status }
				minScore={ query.min_score }
				maxScore={ query.max_score }
				counts={ counts }
				viewer={ viewer }
				onStatusChange={ setStatus }
				onBandChange={ setBand }
			/>
			{ canBulk && (
				<BulkBar
					selection={ selection }
					viewer={ viewer }
					startRun={ startRun }
					onStart={ () => setSelected( new Set() ) }
				/>
			) }
			{ /* The meta box's one-post link (story 109); an empty result
			     offers the same button in the table. */ }
			{ query.post !== null && items.length > 0 && (
				<p className="pqm-show-all">
					<Button variant="link" onClick={ clearFilters }>
						{ showAllPosts }
					</Button>
				</p>
			) }
			{ /* The page's one live region. Refetches keep the current rows
			     visible; the table only dims. Toasts render outside it —
			     SnackbarList announces its own notices. */ }
			<p className="pqm-table-updating" role="status" aria-live="polite">
				{ isLoading && (
					<>
						<Spinner />{ ' ' }
						{ __( 'Updating…', 'post-quality-manager' ) }
					</>
				) }
			</p>
			<Toasts toasts={ toasts } onRemove={ removeToast } />
			{ /* A focusable, labelled region so keyboard users can scroll it
			     sideways once the table hits its minimum width. */ }
			<div
				className="pqm-table-scroll"
				role="region"
				aria-label={ __( 'Posts', 'post-quality-manager' ) }
				tabIndex={ 0 }
			>
				<table
					className={ `widefat fixed striped pqm-posts-table${
						isLoading ? ' is-loading' : ''
					}` }
					aria-busy={ isLoading }
				>
					<thead>
						<tr>
							{ canBulk && (
								<th scope="col" className="pqm-col-select">
									<input
										type="checkbox"
										aria-label={ __(
											'Select all posts',
											'post-quality-manager'
										) }
										checked={ allSelected }
										disabled={ selectable.length === 0 }
										onChange={ toggleSelectAll }
									/>
								</th>
							) }
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
							{ yoastActive && (
								<th scope="col" className="pqm-col-yoast">
									{ __(
										'Yoast SEO',
										'post-quality-manager'
									) }
								</th>
							) }
							<SortableHeader
								column="status"
								label={ __( 'Status', 'post-quality-manager' ) }
								className="pqm-col-status"
								{ ...sortProps }
							/>
							<th scope="col" className="pqm-col-progress">
								{ __( 'Agents run', 'post-quality-manager' ) }
							</th>
							<SortableHeader
								column="reviewed_at"
								label={ __(
									'Last reviewed',
									'post-quality-manager'
								) }
								className="pqm-col-date"
								{ ...sortProps }
							/>
							<th scope="col" className="pqm-col-actions">
								{ __( 'Actions', 'post-quality-manager' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ items.length > 0 &&
							items.map( ( item ) => {
								const isOpen = openPostId === item.post_id;
								const isAwaited = awaited.has( item.post_id );
								return (
									<Fragment key={ item.post_id }>
										<PostRow
											item={ item }
											isOpen={ isOpen }
											isAwaited={ isAwaited }
											onToggle={ () =>
												toggleDetails( item.post_id )
											}
											canSelect={ canBulk }
											isSelected={
												selected.has( item.post_id ) &&
												! inFlight( item )
											}
											onSelect={ () =>
												toggleSelect( item.post_id )
											}
											yoastActive={ yoastActive }
										/>
										{ isOpen && (
											<tr
												className="pqm-expanded-row"
												id={ `pqm-detail-${ item.post_id }` }
											>
												<td colSpan={ columns }>
													<DetailPanel
														postId={ item.post_id }
														editLink={
															item.edit_link || ''
														}
														viewer={ viewer }
														busy={
															isAwaited ||
															pendingPostId ===
																item.post_id
														}
														starting={
															pendingPostId ===
															item.post_id
														}
														version={
															detailVersion
														}
														onTrigger={ (
															command
														) =>
															trigger(
																item.post_id,
																command
															)
														}
														onNotify={ addToast }
														startError={
															startError &&
															startError.postId ===
																item.post_id
																? startError.message
																: null
														}
														onDismissStartError={
															dismissStartError
														}
													/>
												</td>
											</tr>
										) }
									</Fragment>
								);
							} ) }
						{ items.length === 0 &&
							( hasActiveFilters( query ) || query.paged > 1 ? (
								<tr>
									<td
										colSpan={ columns }
										className="pqm-empty-filtered"
									>
										{ __(
											'No posts match these filters.',
											'post-quality-manager'
										) }{ ' ' }
										<Button
											variant="link"
											onClick={ clearFilters }
										>
											{ query.post !== null
												? showAllPosts
												: __(
														'Clear filters',
														'post-quality-manager'
													) }
										</Button>
									</td>
								</tr>
							) : (
								<tr>
									<td colSpan={ columns }>
										{ __(
											'No reviews found. Trigger a review to get started.',
											'post-quality-manager'
										) }
									</td>
								</tr>
							) ) }
					</tbody>
				</table>
			</div>
			<Pagination
				total={ total }
				page={ query.paged }
				pages={ pages }
				onChange={ setPage }
			/>
		</>
	);
}
