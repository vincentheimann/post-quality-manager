/**
 * Dashboard (story 5.6, DESIGN.md D3 / F1) — answers "what should I fix
 * next?": stat tiles that deep-link to Post Reviews chips, the Fix-next
 * queue, the average-score sparkline and the contextual nudges.
 *
 * Data: the queue is GET /posts?orderby=priority&per_page=5 (its items,
 * `in_flight`, `viewer` and `counts` — the tiles' numbers); GET /dashboard
 * adds the average, the 8-week trend and the nudge flags. A queue Review
 * runs through the same trigger + polling + toast path as Post Reviews
 * (src/kit/runs.js, use-polling.js, toasts.js).
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import FixNextQueue from './dashboard/fix-next-queue';
import Sparkline from './dashboard/sparkline';
import StatTiles from './dashboard/stat-tiles';
import './dashboard/style.css';
import {
	giveUpMessage,
	requestRun,
	requestedMessage,
	settledToast,
} from './kit/runs';
import Toasts, { useToasts } from './kit/toasts';
import usePolling from './kit/use-polling';
import { canRender } from './post-reviews/detail-panel';

const QUEUE_SIZE = 5;
const QUEUE_PATH = addQueryArgs( '/post-quality/v1/posts', {
	orderby: 'priority',
	per_page: QUEUE_SIZE,
} );
const DASHBOARD_PATH = '/post-quality/v1/dashboard';

function Loading() {
	return (
		<p>
			<Spinner />{ ' ' }
			{ __( 'Loading dashboard…', 'post-quality-manager' ) }
		</p>
	);
}

/** Not connected: only the call to action; the button only for admins. */
function ConnectCard( { canManage } ) {
	return (
		<Card className="pqm-dash-card">
			<CardBody>
				<h2 className="pqm-dash-heading">
					{ __( 'Connect n8n to start reviewing', 'post-quality-manager' ) }
				</h2>
				{ canManage ? (
					<>
						<p>
							{ __(
								'Import the pre-configured workflow into n8n and connect it to this site — about 10 minutes, one time.',
								'post-quality-manager'
							) }
						</p>
						<Button variant="primary" href="admin.php?page=pqm-workflow">
							{ __( 'Set up the n8n workflow', 'post-quality-manager' ) }
						</Button>
					</>
				) : (
					<p>
						{ __(
							'Reviews start once an administrator connects the n8n workflow to this site.',
							'post-quality-manager'
						) }
					</p>
				) }
			</CardBody>
		</Card>
	);
}

export default function Dashboard() {
	const [ dash, setDash ] = useState( null );
	const [ list, setList ] = useState( null ); // { items, counts, viewer }
	const [ error, setError ] = useState( false );
	// Queue refetch trigger (polling ticks, post-trigger refresh) and the
	// /dashboard refetch version (after each settled run: average + trend move).
	const [ refreshKey, setRefreshKey ] = useState( 0 );
	const refreshCounter = useRef( 0 );
	const [ dashVersion, setDashVersion ] = useState( 0 );
	// A post stays busy from the click until the post-trigger refetch resolves.
	const [ pendingPostId, setPendingPostId ] = useState( null );
	const pendingUntilKey = useRef( null );
	const { toasts, addToast, removeToast } = useToasts();

	const refetch = useCallback( () => {
		refreshCounter.current += 1;
		setRefreshKey( refreshCounter.current );
		return refreshCounter.current;
	}, [] );

	// Refetch failures keep the data on screen; only a failed first load errors.
	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: DASHBOARD_PATH } ).then(
			( data ) => ! cancelled && setDash( data ),
			() => ! cancelled && dashVersion === 0 && setError( true )
		);
		return () => {
			cancelled = true;
		};
	}, [ dashVersion ] );

	useEffect( () => {
		let cancelled = false;
		const settlePending = () => {
			if ( pendingUntilKey.current !== null && refreshKey >= pendingUntilKey.current ) {
				pendingUntilKey.current = null;
				setPendingPostId( null );
			}
		};

		apiFetch( { path: QUEUE_PATH } ).then(
			( data ) => {
				if ( cancelled ) {
					return;
				}
				setList( {
					items: ( data && data.items ) || [],
					counts: ( data && data.counts ) || null,
					viewer: ( data && data.viewer ) || null,
				} );
				settlePending();
			},
			() => {
				if ( cancelled ) {
					return;
				}
				settlePending();
				if ( refreshKey === 0 ) {
					setError( true );
				}
			}
		);
		return () => {
			cancelled = true;
		};
	}, [ refreshKey ] );

	const onSettled = useCallback(
		( postId, command ) => {
			setDashVersion( ( v ) => v + 1 );
			settledToast( postId, command ).then(
				( toast ) => toast && addToast( toast.content, toast.status )
			);
		},
		[ addToast ]
	);

	const onGiveUp = useCallback(
		( postId, command ) => addToast( giveUpMessage( command ), 'info' ),
		[ addToast ]
	);

	const { awaited, restart } = usePolling( list ? list.items : null, QUEUE_PATH, {
		onTick: refetch,
		onSettled,
		onGiveUp,
	} );

	// Same path as a single run on Post Reviews: request, then re-arm
	// polling and refetch so the row switches to Processing.
	const review = ( postId ) => {
		setPendingPostId( postId );
		requestRun( postId, 'review' ).then( ( { ok, message } ) => {
			if ( ! ok ) {
				addToast( message, 'warning' );
				setPendingPostId( null );
				return;
			}
			restart( postId, 'review' );
			addToast( requestedMessage( 'review' ), 'info' );
			pendingUntilKey.current = refetch();
		} );
	};

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __(
					'Could not load the dashboard. Check that the site REST API is reachable, then reload this page.',
					'post-quality-manager'
				) }
			</Notice>
		);
	}

	if ( ! dash ) {
		return <Loading />;
	}

	if ( ! dash.connected ) {
		return <ConnectCard canManage={ !! dash.can_manage_settings } />;
	}

	if ( ! list ) {
		return <Loading />;
	}

	const { counts, viewer } = list;
	const canReview = canRender( viewer, 'review' );
	const queue = list.items.filter( ( item ) => item.latest_status !== 'ship' );
	const total = counts ? counts.all : 0;
	const analyzed = counts ? counts.all - counts.none : 0;

	let empty = null;
	if ( total === 0 ) {
		empty = __(
			'No published or pending posts yet. Publish a post to start reviewing.',
			'post-quality-manager'
		);
	} else if ( queue.length === 0 ) {
		empty = __(
			'Nothing to fix next — every analyzed post is ready to ship.',
			'post-quality-manager'
		);
	}

	return (
		<div className="pqm-dashboard">
			{ total > 0 && analyzed === 0 && canReview && (
				<Notice status="info" isDismissible={ false } className="pqm-dash-notice">
					<strong>
						{ __( 'You are all set — run your first review.', 'post-quality-manager' ) }
					</strong>{ ' ' }
					{ __(
						'Pick a post below and click Review. The score, issues and suggested edits land here in about a minute.',
						'post-quality-manager'
					) }
				</Notice>
			) }

			<StatTiles average={ dash.average_score } counts={ counts } />

			<Card className="pqm-dash-card">
				<CardHeader>
					<h2 className="pqm-dash-heading">
						{ __( 'Fix next', 'post-quality-manager' ) }
					</h2>
				</CardHeader>
				<CardBody>
					{ empty ? (
						<p className="pqm-muted pqm-queue-empty">{ empty }</p>
					) : (
						<FixNextQueue
							items={ queue }
							canReview={ canReview }
							isBusy={ ( postId ) =>
								awaited.has( postId ) || pendingPostId === postId
							}
							onReview={ review }
						/>
					) }
				</CardBody>
			</Card>

			<Card className="pqm-dash-card">
				<CardHeader>
					<h2 className="pqm-dash-heading">
						{ sprintf(
							/* translators: %d: number of weeks */
							__( 'Average score — last %d weeks', 'post-quality-manager' ),
							( dash.trend || [] ).length
						) }
					</h2>
				</CardHeader>
				<CardBody>
					<Sparkline trend={ dash.trend || [] } />
				</CardBody>
			</Card>

			{ dash.cron_nudge && (
				<Notice
					status="info"
					isDismissible={ false }
					className="pqm-dash-notice"
					actions={ [
						{
							label: __( 'Enable scheduled reviews', 'post-quality-manager' ),
							url: 'admin.php?page=pqm-settings#pqm-cron-settings',
							variant: 'primary',
						},
						{
							label: __( 'Dismiss', 'post-quality-manager' ),
							url: dash.cron_nudge_dismiss_url,
						},
					] }
				>
					<strong>
						{ __( 'Let reviews run themselves.', 'post-quality-manager' ) }
					</strong>{ ' ' }
					{ __(
						'Scheduled reviews keep scoring your back catalog automatically — you pick the frequency and batch size.',
						'post-quality-manager'
					) }
				</Notice>
			) }

			<Toasts toasts={ toasts } onRemove={ removeToast } />
		</div>
	);
}
