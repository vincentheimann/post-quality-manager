/**
 * InsertBlock (story 23) — "Insert into Post" in the Takeaways and Sources
 * tabs. The action edits a possibly live post right away, so a Modal
 * confirms first; then POST /insert-block, where the plugin copies the
 * pattern chosen in Settings (a plain list by default) with the latest
 * stored items and saves the post — Takeaways first, Sources last, an
 * earlier insert replaced. No AI call, no quota. The outcome is a toast.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { commandLabel } from '../kit/copy';

/** Where each command's suggested items live in its result (PostQuality_Blocks::item_keys()). */
const ITEM_KEYS = {
	takeaways: 'rewritten_items',
	sources: 'rewritten_sources',
};

/**
 * The non-empty suggested items of a stored result, [] when the command
 * has no insert or the result has none.
 *
 * @param {string}  command Command key.
 * @param {unknown} data    The row's `result_data`.
 * @return {Array} Items the server would insert.
 */
export function insertItems( command, data ) {
	const items = ITEM_KEYS[ command ] && data && data[ ITEM_KEYS[ command ] ];
	// Same rule as PostQuality_Blocks::latest_items(): text only, blanks dropped.
	return Array.isArray( items )
		? items.filter(
				( item ) =>
					( typeof item === 'string' || typeof item === 'number' ) &&
					String( item ).trim() !== ''
			)
		: [];
}

/**
 * @param {Object}                                    props
 * @param {number}                                    props.postId   Post to insert into.
 * @param {string}                                    props.command  'takeaways' or 'sources'.
 * @param {number}                                    props.count    Number of items that will be inserted.
 * @param {boolean}                                   props.disabled True while the post is busy.
 * @param {(content: string, status: string) => void} props.onNotify The page's toast.
 */
export default function InsertBlock( {
	postId,
	command,
	count,
	disabled,
	onNotify,
} ) {
	const [ confirming, setConfirming ] = useState( false );
	const [ saving, setSaving ] = useState( false );

	const insert = () => {
		setSaving( true );
		apiFetch( {
			path: '/post-quality/v1/insert-block',
			method: 'POST',
			data: { post_id: postId, command },
		} )
			.then(
				( data ) => onNotify( data.message, 'success' ),
				// Gate / no items / save error: the server's message, gently.
				( err ) =>
					onNotify(
						( err && err.message ) ||
							__(
								'Could not reach the site REST API. Reload the page and try again.',
								'post-quality-manager'
							),
						'warning'
					)
			)
			.finally( () => {
				setSaving( false );
				setConfirming( false );
			} );
	};

	const where =
		command === 'sources'
			? sprintf(
					/* translators: %d: number of suggested sources */
					_n(
						'%d source goes into the post at the end, using the pattern chosen in Settings, and replaces any Sources inserted earlier.',
						'%d sources go into the post at the end, using the pattern chosen in Settings, and replace any Sources inserted earlier.',
						count,
						'post-quality-manager'
					),
					count
				)
			: sprintf(
					/* translators: %d: number of suggested takeaways */
					_n(
						'%d takeaway goes into the post at the top, using the pattern chosen in Settings, and replaces any Takeaways inserted earlier.',
						'%d takeaways go into the post at the top, using the pattern chosen in Settings, and replace any Takeaways inserted earlier.',
						count,
						'post-quality-manager'
					),
					count
				);

	return (
		<>
			<Button
				variant="primary"
				disabled={ disabled || saving }
				onClick={ () => setConfirming( true ) }
			>
				{ __( 'Insert into Post', 'post-quality-manager' ) }
			</Button>

			{ confirming && (
				<Modal
					title={ sprintf(
						/* translators: %s: agent command label, e.g. "Takeaways" */
						__( 'Insert %s into the post', 'post-quality-manager' ),
						commandLabel( command )
					) }
					onRequestClose={ () => ! saving && setConfirming( false ) }
				>
					<p>{ where }</p>
					<p>
						{ __(
							'The post is saved right away and keeps its status. The previous version stays in Revisions. If this post is open in the editor, reload it before you save there.',
							'post-quality-manager'
						) }
					</p>
					<div className="pqm-confirm-actions">
						<Button
							variant="tertiary"
							disabled={ saving }
							onClick={ () => setConfirming( false ) }
						>
							{ __( 'Cancel', 'post-quality-manager' ) }
						</Button>
						<Button
							variant="primary"
							isBusy={ saving }
							disabled={ saving }
							onClick={ insert }
						>
							{ __( 'Insert into Post', 'post-quality-manager' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
}
