/**
 * Post Quality meta box entry (story 109): the second build entry, loaded
 * only on the post edit screens (PostQuality_Meta_Box::enqueue()). Mounts
 * the box on the root div PostQuality_Meta_Box::render() prints, with the
 * data PHP prints before the script (window.postqualityMetaBox). The block
 * editor moves the meta box area in and out of its pane with the root
 * inside it; the React root goes with the node.
 */
import { createRoot } from '@wordpress/element';

import MetaBox from './meta-box';

const node = document.getElementById( 'pqm-meta-box' );
if ( node && window.postqualityMetaBox ) {
	createRoot( node ).render( <MetaBox { ...window.postqualityMetaBox } /> );
}
