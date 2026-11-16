/**
 * Bug 128: the Dashboard's Fix next queue (src/dashboard/fix-next-queue.js)
 * shows Processing only while a review runs, as Post Reviews does (story
 * 5.4). A post busy with another command, or waiting for its Review start,
 * keeps its last status; its Review button is disabled, and spins while
 * the start is pending. The WordPress-provided externals (api-fetch,
 * components) are not installed here: they are mocked.
 */
import { act } from 'react';
import { createRoot } from '@wordpress/element';

import FixNextQueue from '../../src/dashboard/fix-next-queue';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock(
	'@wordpress/components',
	() => ( {
		Button: ( { children, isBusy, variant, ...props } ) => (
			<button data-spinning={ isBusy ? 'yes' : 'no' } { ...props }>
				{ children }
			</button>
		),
		Dashicon: () => null,
		Notice: ( { children } ) => <div>{ children }</div>,
	} ),
	{ virtual: true }
);

/**
 * One queue row, as GET /posts?orderby=priority lists it.
 *
 * @param {number}   id       Post ID.
 * @param {string[]} inFlight Commands in flight for the post.
 * @return {Object} Queue item.
 */
const row = ( id, inFlight ) => ( {
	post_id: id,
	title: `Post ${ id }`,
	edit_link: `/wp-admin/post.php?post=${ id }&action=edit`,
	latest_score: 64,
	latest_status: 'needs_revision',
	latest_reviewed_display: '2026-11-01 10:00',
	in_flight: inFlight,
} );

let root;

/**
 * Render the queue: post 1 runs Key Takeaways (awaited by the polling
 * hook), post 2 runs a review beside Key Takeaways and is not awaited (the
 * hook gave up, or the page was reloaded), so only the server's in_flight
 * can make it read Processing; post 3's Review start is pending with
 * nothing in flight yet.
 */
function render() {
	document.body.innerHTML = '<div id="root"></div>';
	const busy = new Set( [ 1, 3 ] );
	act( () => {
		root = createRoot( document.getElementById( 'root' ) );
		root.render(
			<FixNextQueue
				items={ [
					row( 1, [ 'takeaways' ] ),
					row( 2, [ 'review', 'takeaways' ] ),
					row( 3, [] ),
				] }
				canReview
				isBusy={ ( postId ) => busy.has( postId ) }
				isStarting={ ( postId ) => postId === 3 }
				onReview={ () => {} }
				startError={ null }
				onDismissStartError={ () => {} }
			/>
		);
	} );
}

/**
 * The status badge's label and the Review button of one row.
 *
 * @param {number} index Row index.
 * @return {{badge: string, review: HTMLButtonElement}} The row's parts.
 */
const rowParts = ( index ) => {
	const li = document.querySelectorAll( '.pqm-queue-row' )[ index ];
	return {
		badge: li.querySelector( '.pqm-status-badge' ).textContent,
		review: li.querySelector( 'button[aria-label^="Review:"]' ),
	};
};

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	act( () => root.unmount() );
} );

test( 'a post with only another command in flight keeps its last status, Review disabled', () => {
	render();
	const { badge, review } = rowParts( 0 );
	expect( badge ).toBe( 'Needs revision' );
	expect( review.disabled ).toBe( true );
	expect( review.dataset.spinning ).toBe( 'no' );
} );

test( 'a post with a review in flight shows Processing, from the server alone', () => {
	render();
	expect( rowParts( 1 ).badge ).toBe( 'Processing…' );
} );

test( 'a pending Review start keeps the last status; the button spins', () => {
	render();
	const { badge, review } = rowParts( 2 );
	expect( badge ).toBe( 'Needs revision' );
	expect( review.disabled ).toBe( true );
	expect( review.dataset.spinning ).toBe( 'yes' );
} );
