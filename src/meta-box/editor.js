/**
 * The post editor around the Post Quality meta box (story 109): the post's
 * saved status, its unsaved changes and saving it. In the block editor this
 * reads the `core/editor` store by its name — `@wordpress/editor` is never
 * imported, so the bundle depends on `wp-data` only; in the Classic Editor,
 * where that store does not exist, it asks `wp.autosave`, and saving stays
 * the editor's own (it reloads the page). How both behave on WordPress 6.6
 * and 7.1: memory/admin-ui-rebuild.md (story 109).
 */
import { dispatch, select, useSelect } from '@wordpress/data';

const EDITOR_STORE = 'core/editor';

/** The statuses a Review run accepts (POST /trigger-review answers 404 for any other). */
export const REVIEWABLE = [ 'publish', 'pending' ];

/**
 * The block editor's saved status and unsaved-changes flag, kept live; null
 * in the Classic Editor (the page reloads on save, so the status printed
 * with the page is the saved one).
 *
 * @param {boolean} isBlockEditor Whether the screen runs the block editor.
 * @return {{status: string, dirty: boolean}|null} The editor's state.
 */
export function useBlockEditor( isBlockEditor ) {
	return useSelect(
		( registrySelect ) => {
			const editor = isBlockEditor && registrySelect( EDITOR_STORE );
			return editor
				? {
						status: editor.getCurrentPostAttribute( 'status' ),
						dirty: editor.isEditedPostDirty(),
					}
				: null;
		},
		[ isBlockEditor ]
	);
}

/**
 * Block editor: save the post, then say whether a review may run — the
 * save went through, nothing is left unsaved and the post is still
 * published or pending. `savePost()` keeps the post's status, resolves once
 * the post and the meta boxes are saved, never rejects and does nothing
 * while another save runs; a failed save leaves the editor's own message.
 *
 * @return {Promise<boolean>} Whether the review may start.
 */
export async function saveBlockEditor() {
	await dispatch( EDITOR_STORE ).savePost();
	const editor = select( EDITOR_STORE );
	return (
		! editor.didPostSaveRequestFail() &&
		! editor.isEditedPostDirty() &&
		REVIEWABLE.includes( editor.getCurrentPostAttribute( 'status' ) )
	);
}

/**
 * Block editor: whether the post has unsaved changes right now.
 *
 * @return {boolean} Unsaved changes.
 */
export function blockEditorDirty() {
	return select( EDITOR_STORE ).isEditedPostDirty();
}

/**
 * Classic Editor: whether the title, content or excerpt has unsaved
 * changes (`wp.autosave.server.postChanged()`).
 *
 * @return {boolean} Unsaved changes.
 */
export function classicEditorDirty() {
	const server = window.wp && window.wp.autosave && window.wp.autosave.server;
	return !! ( server && server.postChanged() );
}
