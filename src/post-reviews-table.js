/**
 * Post Reviews table (stories 5.1 – 5.4).
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
 * view and a reload restores it.
 *
 * Story 5.4: a per-row Details toggle opens the DetailPanel; trigger
 * buttons call POST /trigger-review directly (no confirm for a single
 * run); one usePolling hook watches the list's server-derived `in_flight`
 * field (3 s, auto-resumes after a reload) and refetches the current
 * query silently so rows update in place; outcomes surface as toasts.
 * Bulk actions are story 5.5.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import {
	Fragment,
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs, getQueryArgs, removeQueryArgs } from '@wordpress/url';

import { STATUS_NONE, commandLabel, statusLabel } from './kit/copy';
import ProgressPills from './kit/progress-pills';
import ScoreBadge from './kit/score-badge';
import StatusBadge from './kit/status-badge';
import DetailPanel, { isFailedRow } from './post-reviews/detail-panel';
import FilterBar from './post-reviews/filter-bar';
import Pagination from './post-reviews/pagination';
import SortableHeader from './post-reviews/sortable-header';
import Toasts, { useToasts } from './post-reviews/toasts';
import usePolling from './post-reviews/use-polling';

const PER_PAGE = 20;
const COLUMNS = 6;
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

/** The REST path for a query — also the identity the polling hook keys on. */
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

function inFlight( item, command ) {
	return Array.isArray( item.in_flight ) && item.in_flight.includes( command );
}

/** Toast copy for a run that just left `in_flight`, from its stored row. */
function outcomeToast( command, row ) {
	const label = commandLabel( command );

	if ( ! row ) {
		return {
			status: 'info',
			content: sprintf(
				/* translators: %s: agent command label, e.g. "Review" */
				__( '%s finished — open Details to see the result.', 'post-quality-manager' ),
				label
			),
		};
	}

	if ( isFailedRow( row ) ) {
		return {
			status: 'warning',
			content: sprintf(
				/* translators: %s: agent command label, e.g. "Review" */
				__(
					'%s could not complete. Check the n8n execution log, then try again.',
					'post-quality-manager'
				),
				label
			),
		};
	}

	if ( command === 'rewrite' ) {
		return {
			status: 'success',
			content: __(
				'Rewrite complete — the post is saved as Pending Review.',
				'post-quality-manager'
			),
		};
	}

	if ( row.overall_score > 0 ) {
		return {
			status: 'success',
			content: sprintf(
				/* translators: 1: agent command label, e.g. "Review"; 2: score 0–100; 3: status label, e.g. "Ready to ship" */
				__( '%1$s complete — score %2$d, %3$s.', 'post-quality-manager' ),
				label,
				row.overall_score,
				statusLabel( row.status )
			),
		};
	}

	return {
		status: 'success',
		content: sprintf(
			/* translators: %s: agent command label, e.g. "Takeaways" */
			__( '%s complete.', 'post-quality-manager' ),
			label
		),
	};
}

function PostRow( { item, isOpen, isAwaited, onToggle } ) {
	const detailId = `pqm-detail-${ item.post_id }`;

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
				{ /* A running review shows Processing; the stored latest_*
				     (and so score, order and membership) stay its last result. */ }
				<StatusBadge
					status={ inFlight( item, 'review' ) ? 'processing' : item.latest_status }
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
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( false );

	// Story 5.4 state: the open panel, a refetch trigger for the current
	// query (silent for polling ticks — no "Updating…" announcement every
	// 3 s), the panel's refetch version and the post with a pending
	// trigger. A post stays busy from the click until the post-trigger
	// refetch (refreshKey ≥ pendingUntilKey) resolves.
	const [ openPostId, setOpenPostId ] = useState( null );
	const [ refreshKey, setRefreshKey ] = useState( 0 );
	const refreshCounter = useRef( 0 );
	const silentRefresh = useRef( false );
	const [ detailVersion, setDetailVersion ] = useState( 0 );
	const [ pendingPostId, setPendingPostId ] = useState( null );
	const pendingUntilKey = useRef( null );
	const { toasts, addToast, removeToast } = useToasts();

	/** Refetch the current query; returns the refreshKey that request carries. */
	const refetch = useCallback( ( silent = false ) => {
		silentRefresh.current = silent;
		refreshCounter.current += 1;
		setRefreshKey( refreshCounter.current );
		return refreshCounter.current;
	}, [] );

	useEffect( () => {
		let cancelled = false; // a newer request supersedes this one
		const silent = silentRefresh.current;
		silentRefresh.current = false;
		const path = restPath( query );

		writeQueryToUrl( query );
		if ( ! silent ) {
			setIsLoading( true );
		}

		const settlePending = () => {
			if ( pendingUntilKey.current !== null && refreshKey >= pendingUntilKey.current ) {
				pendingUntilKey.current = null;
				setPendingPostId( null );
			}
		};

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
				setIsLoading( false );
				settlePending();
			} )
			.catch( () => {
				if ( cancelled ) {
					return;
				}
				// A silent (polling) failure keeps the rows; the next tick
				// retries. The busy flag never outlives its request.
				settlePending();
				if ( ! silent ) {
					setError( true );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ query, refreshKey ] );

	// Completion: the row is already updated in place by the refetch (or
	// the post left the current view because its result changed it); the
	// outcome — score + status, or a failure — comes from the stored row.
	const onSettled = useCallback(
		( postId, command ) => {
			setDetailVersion( ( v ) => v + 1 );
			apiFetch( { path: `/post-quality/v1/reviews/${ postId }` } )
				.then( ( rows ) => {
					const row = ( Array.isArray( rows ) ? rows : [] ).find(
						( r ) => r.command === command
					);
					const toast = outcomeToast( command, row );
					addToast( toast.content, toast.status );
				} )
				.catch( () => {
					const toast = outcomeToast( command, null );
					addToast( toast.content, toast.status );
				} );
		},
		[ addToast ]
	);

	const onGiveUp = useCallback(
		( postId, command ) => {
			addToast(
				sprintf(
					/* translators: %s: agent command label, e.g. "Review" */
					__(
						'%s is still running in n8n — results will appear when it finishes.',
						'post-quality-manager'
					),
					commandLabel( command )
				),
				'info'
			);
		},
		[ addToast ]
	);

	const { awaited, restart } = usePolling( items, loadedKey, {
		onTick: () => refetch( true ),
		onSettled,
		onGiveUp,
	} );

	// Trigger flow (D4 / F6): no confirm for a single run; the server
	// writes the processing row on an n8n 2xx and the next refetch shows
	// it — that refetch also ends the busy state.
	const trigger = ( postId, command ) => {
		const label = commandLabel( command );
		setPendingPostId( postId );

		apiFetch( {
			path: '/post-quality/v1/trigger-review',
			method: 'POST',
			data: { post_id: postId, command },
		} )
			.then( ( data ) => {
				if ( data && data.success === false ) {
					addToast(
						sprintf(
							/* translators: 1: agent command label, e.g. "Review"; 2: HTTP status code n8n answered with */
							__(
								'%1$s could not start — n8n answered HTTP %2$d. Check that the workflow is active in n8n, then try again.',
								'post-quality-manager'
							),
							label,
							data.n8n_status || 0
						),
						'warning'
					);
					setPendingPostId( null );
					return;
				}
				addToast(
					sprintf(
						/* translators: %s: agent command label, e.g. "Review" */
						__(
							'%s requested — running in the background, this page stays usable.',
							'post-quality-manager'
						),
						label
					),
					'info'
				);
				restart( postId, command );
				setDetailVersion( ( v ) => v + 1 );
				pendingUntilKey.current = refetch( true );
			} )
			.catch( ( err ) => {
				// Gate / quota / not configured / n8n down: the server's message, gently.
				addToast(
					( err && err.message ) ||
						__(
							'Could not reach the site REST API. Reload the page and try again.',
							'post-quality-manager'
						),
					'warning'
				);
				setPendingPostId( null );
			} );
	};

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
										onToggle={ () => toggleDetails( item.post_id ) }
									/>
									{ isOpen && (
										<tr
											className="pqm-expanded-row"
											id={ `pqm-detail-${ item.post_id }` }
										>
											<td colSpan={ COLUMNS }>
												<DetailPanel
													postId={ item.post_id }
													editLink={ item.edit_link || '' }
													viewer={ viewer }
													busy={
														isAwaited ||
														pendingPostId === item.post_id
													}
													version={ detailVersion }
													onTrigger={ ( command ) =>
														trigger( item.post_id, command )
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
								<td colSpan={ COLUMNS } className="pqm-empty-filtered">
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
								<td colSpan={ COLUMNS }>
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
