/**
 * BulkBar (story 5.5, DESIGN.md D4 / CAP-2) — "Review selected" above the
 * Post Reviews table. One confirmation Modal, then the selected posts are
 * queued from the client: one POST /trigger-review per post, strictly in
 * sequence, each awaited. The server enforces gate and quota on every
 * call, so a refusal (403 / 429 / 400 / 502 / n8n `success:false`) is
 * reported for that post and the queue moves on. Outcomes fill an inline,
 * dismissible Notice by title (owner decision 2026-10-05); completion
 * toasts still come from the table's polling (5.4).
 *
 * The batch captures ids and titles when it starts, so it keeps running
 * and reporting while the user filters, sorts or pages.
 */
import { Button, Modal, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/** "{title}: Review requested" or "{title}: {server message}". */
function outcomeLine( result ) {
	return sprintf(
		/* translators: 1: post title; 2: outcome, e.g. "Review requested" or the server's refusal message */
		__( '%1$s: %2$s', 'post-quality-manager' ),
		result.title,
		result.ok ? __( 'Review requested', 'post-quality-manager' ) : result.message
	);
}

/**
 * @param {Object}   props
 * @param {Array}    props.selection `[{ post_id, title }]` — selected, selectable rows of the current page.
 * @param {Object}   props.viewer    The list's `viewer` block (quota).
 * @param {Function} props.startRun  The table's `( postId, command ) => Promise<{ ok, message }>` — requestRun() from src/kit/runs.js plus polling re-arm; never rejects.
 * @param {Function} props.onStart   Called when a batch starts (clears the selection).
 */
export default function BulkBar( { selection, viewer, startRun, onStart } ) {
	const [ confirming, setConfirming ] = useState( false );
	const [ running, setRunning ] = useState( false );
	const [ batchSize, setBatchSize ] = useState( 0 ); // progress = results.length of batchSize
	const [ results, setResults ] = useState( [] );
	const count = selection.length;
	const quota = viewer && viewer.quota;

	const run = async () => {
		const batch = selection.map( ( { post_id, title } ) => ( { post_id, title } ) );
		setConfirming( false );
		onStart();
		setResults( [] );
		setBatchSize( batch.length );
		setRunning( true );

		for ( const post of batch ) {
			const outcome = await startRun( post.post_id, 'review' );
			setResults( ( prev ) => [ ...prev, { ...post, ...outcome } ] );
		}

		setRunning( false );
	};

	let hint;
	if ( running ) {
		hint = sprintf(
			/* translators: 1: posts already requested; 2: posts in the batch */
			__( 'Requesting reviews — %1$d of %2$d done…', 'post-quality-manager' ),
			results.length,
			batchSize
		);
	} else if ( count > 0 ) {
		hint = sprintf(
			/* translators: %d: number of selected posts */
			_n(
				'%d selected — one confirmation, then it is queued.',
				'%d selected — one confirmation, then they queue one by one.',
				count,
				'post-quality-manager'
			),
			count
		);
	} else {
		hint = __( 'Select posts to review them in one go.', 'post-quality-manager' );
	}

	return (
		<>
			<div className="pqm-table-topbar pqm-bulk-bar">
				<Button
					variant="secondary"
					disabled={ count === 0 || running }
					onClick={ () => setConfirming( true ) }
				>
					{ count > 0
						? sprintf(
								/* translators: %d: number of selected posts */
								_n(
									'Review selected (%d)',
									'Review selected (%d)',
									count,
									'post-quality-manager'
								),
								count
						  )
						: __( 'Review selected', 'post-quality-manager' ) }
				</Button>
				<span className="pqm-muted">{ hint }</span>
			</div>

			{ results.length > 0 && (
				<Notice
					className="pqm-bulk-report"
					status={ results.every( ( r ) => r.ok ) ? 'success' : 'warning' }
					isDismissible={ ! running }
					onRemove={ () => setResults( [] ) }
					// Announce the newest outcome only, not the whole list again.
					spokenMessage={ outcomeLine( results[ results.length - 1 ] ) }
				>
					<ul>
						{ results.map( ( result ) => (
							<li key={ result.post_id }>{ outcomeLine( result ) }</li>
						) ) }
					</ul>
				</Notice>
			) }

			{ confirming && (
				<Modal
					title={ __( 'Review selected posts', 'post-quality-manager' ) }
					onRequestClose={ () => setConfirming( false ) }
				>
					<p>
						{ sprintf(
							/* translators: %d: number of selected posts */
							_n(
								'%d post will be queued for review, one after another.',
								'%d posts will be queued for review, one after another.',
								count,
								'post-quality-manager'
							),
							count
						) }
					</p>
					{ quota && (
						<p>
							{ sprintf(
								/* translators: %d: remaining AI actions this month */
								_n(
									'%d AI action left this month. Posts past your limit are listed, not reviewed.',
									'%d AI actions left this month. Posts past your limit are listed, not reviewed.',
									quota.remaining,
									'post-quality-manager'
								),
								quota.remaining
							) }
						</p>
					) }
					<div className="pqm-confirm-actions">
						<Button variant="tertiary" onClick={ () => setConfirming( false ) }>
							{ __( 'Cancel', 'post-quality-manager' ) }
						</Button>
						<Button variant="primary" disabled={ count === 0 } onClick={ run }>
							{ sprintf(
								/* translators: %d: number of selected posts */
								_n(
									'Review %d post',
									'Review %d posts',
									count,
									'post-quality-manager'
								),
								count
							) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
}
