/**
 * FixNextQueue (story 5.6, DESIGN.md D3) — up to five posts to fix next,
 * in the server's priority v1 order (GET /posts?orderby=priority). Each
 * row: score, title + last-review date, status badge, Review (only for
 * viewers who can run it) and Open in Editor. A running review shows
 * Processing from the server's `in_flight`, like the Post Reviews table;
 * a failed start shows as a dismissible warning Notice inside its row
 * (ticket 32).
 */
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { OPEN_IN_EDITOR, commandLabel } from '../kit/copy';
import { inFlight } from '../kit/runs';
import ScoreBadge from '../kit/score-badge';
import StatusBadge from '../kit/status-badge';

/**
 * @param {Object}      props
 * @param {Array}       props.items               Queue rows (ship rows already dropped).
 * @param {boolean}     props.canReview           Whether the viewer may run Review.
 * @param {Function}    props.isBusy              `( postId ) => boolean` — trigger pending or result awaited.
 * @param {Function}    props.isStarting          `( postId ) => boolean` — start request pending.
 * @param {Function}    props.onReview            `( postId ) => void`.
 * @param {Object|null} props.startError          The last failed start, `{ postId, command, message }`, or null.
 * @param {Function}    props.onDismissStartError Closes the failed-start notice.
 */
export default function FixNextQueue( {
	items,
	canReview,
	isBusy,
	isStarting,
	onReview,
	startError,
	onDismissStartError,
} ) {
	return (
		<ol className="pqm-queue">
			{ items.map( ( item ) => {
				const busy = isBusy( item.post_id );
				return (
					<li key={ item.post_id } className="pqm-queue-row">
						<ScoreBadge score={ item.latest_score } />
						<span className="pqm-queue-title">
							<strong>{ item.title }</strong>
							<span className="pqm-muted pqm-queue-meta">
								{ item.latest_reviewed_display
									? sprintf(
											/* translators: %s: date of the last review */
											__(
												'Last reviewed %s',
												'post-quality-manager'
											),
											item.latest_reviewed_display
									  )
									: __(
											'Never reviewed',
											'post-quality-manager'
									  ) }
							</span>
						</span>
						<StatusBadge
							status={
								busy || inFlight( item )
									? 'processing'
									: item.latest_status
							}
						/>
						<span className="pqm-queue-actions">
							{ canReview && (
								<Button
									variant="secondary"
									disabled={ busy }
									isBusy={ isStarting( item.post_id ) }
									aria-label={ sprintf(
										/* translators: %s: post title */
										__(
											'Review: %s',
											'post-quality-manager'
										),
										item.title
									) }
									onClick={ () => onReview( item.post_id ) }
								>
									{ commandLabel( 'review' ) }
								</Button>
							) }
							{ item.edit_link && (
								<Button
									variant="secondary"
									href={ item.edit_link }
									aria-label={ sprintf(
										/* translators: %s: post title */
										__(
											'Open in Editor: %s',
											'post-quality-manager'
										),
										item.title
									) }
								>
									{ OPEN_IN_EDITOR }
								</Button>
							) }
						</span>
						{ startError && startError.postId === item.post_id && (
							<Notice
								status="warning"
								className="pqm-start-error"
								isDismissible
								onRemove={ onDismissStartError }
							>
								{ startError.message }
							</Notice>
						) }
					</li>
				);
			} ) }
		</ol>
	);
}
