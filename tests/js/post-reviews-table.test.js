/**
 * Story 109: the Post Reviews link the meta box opens, run in jsdom — the
 * whole table (src/post-reviews-table.js). With `postquality_post=N` in the
 * address, the list asks GET /posts for that post only (`post_id=N`), opens
 * its detail panel on the Review tab and offers "Show all posts", which
 * fetches the whole list again and drops the argument from the address. A
 * narrowed list that comes back empty offers the same button; without the
 * argument nothing changes. The WordPress-provided externals (api-fetch,
 * components, url) are not installed here: they are mocked, the url one
 * with URLSearchParams so the address handling is real.
 */
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import PostReviewsTable from '../../src/post-reviews-table';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock(
	'@wordpress/url',
	() => {
		const split = ( url ) => {
			const at = url.indexOf( '?' );
			return at < 0
				? [ url, '' ]
				: [ url.slice( 0, at ), url.slice( at + 1 ) ];
		};
		const join = ( base, params ) => {
			const query = params.toString();
			return query ? `${ base }?${ query }` : base;
		};
		return {
			getQueryArgs: ( url ) =>
				Object.fromEntries( new URLSearchParams( split( url )[ 1 ] ) ),
			addQueryArgs: ( url = '', args = {} ) => {
				const [ base, query ] = split( url );
				const params = new URLSearchParams( query );
				Object.entries( args ).forEach( ( [ key, value ] ) =>
					params.set( key, value )
				);
				return join( base, params );
			},
			removeQueryArgs: ( url, ...keys ) => {
				const [ base, query ] = split( url );
				const params = new URLSearchParams( query );
				keys.forEach( ( key ) => params.delete( key ) );
				return join( base, params );
			},
		};
	},
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		Button: ( {
			children,
			isBusy,
			isPressed,
			variant,
			size,
			__next40pxDefaultSize,
			...props
		} ) => <button { ...props }>{ children }</button>,
		Dashicon: () => null,
		Modal: () => null,
		Notice: ( { status, children } ) => (
			<div className={ 'mock-notice-' + status }>{ children }</div>
		),
		SelectControl: () => null,
		SnackbarList: () => null,
		Spinner: () => null,
		// The first tab is the open one, as in the real TabPanel.
		TabPanel: ( { tabs, children } ) => (
			<div>
				<div role="tablist">
					{ tabs.map( ( tab ) => (
						<span key={ tab.name } role="tab">
							{ tab.title }
						</span>
					) ) }
				</div>
				<div role="tabpanel" data-tab={ tabs[ 0 ].name }>
					{ children( tabs[ 0 ] ) }
				</div>
			</div>
		),
		VisuallyHidden: ( { children } ) => <span>{ children }</span>,
	} ),
	{ virtual: true }
);

const POSTS = '/post-quality/v1/posts';
const PAGE = '/wp-admin/admin.php?page=postquality-posts';

/**
 * One listed post with a done Review, as GET /posts lists it.
 *
 * @param {number} id    Post ID.
 * @param {string} title Post title.
 * @return {Object} List item.
 */
const item = ( id, title ) => ( {
	post_id: id,
	title,
	edit_link: `/wp-admin/post.php?post=${ id }&action=edit`,
	latest_score: 64,
	latest_status: 'needs_revision',
	latest_reviewed: '2026-11-01 10:00:00',
	latest_reviewed_display: '2026-11-01 10:00',
	commands: [ 'review' ],
	in_flight: [],
	wp_post_status: 'publish',
	yoast: null,
} );
const ITEMS = [ item( 3, 'First post' ), item( 9, 'Linked post' ) ];

let root;

/**
 * Open Post Reviews at `address`. GET /posts lists ITEMS, only the one
 * `post_id` names when it is sent; GET /reviews/{id} answers one done Review.
 *
 * @param {string} address The page's address.
 */
async function open( address ) {
	window.history.replaceState( null, '', address );
	document.body.innerHTML = '<div id="root"></div>';
	apiFetch.mockReset();
	apiFetch.mockImplementation( ( { path } ) => {
		if ( path.startsWith( POSTS ) ) {
			const postId = Number( listArgs( path ).post_id || 0 );
			const items = postId
				? ITEMS.filter( ( entry ) => entry.post_id === postId )
				: ITEMS;
			return Promise.resolve( {
				items,
				total: items.length,
				pages: items.length ? 1 : 0,
				counts: {
					all: 2,
					ship: 0,
					needs_revision: 2,
					major_revision: 0,
					none: 0,
				},
				viewer: {
					can_trigger: true,
					quota: null,
					gated_commands: [],
					hidden_commands: [],
					connected: true,
					can_manage_settings: true,
				},
				yoast_active: false,
			} );
		}
		const reviewed = path.match( /\/reviews\/(\d+)$/ );
		if ( reviewed ) {
			return Promise.resolve( [
				{
					id: 1,
					command: 'review',
					status: 'needs_revision',
					overall_score: 64,
					result_data: {
						top_edits: [ `Edit for post ${ reviewed[ 1 ] }.` ],
					},
					reviewed_at: '2026-11-01 10:00:00',
					reviewed_at_display: '2026-11-01 10:00',
				},
			] );
		}
		return Promise.reject( new Error( `unexpected ${ path }` ) );
	} );
	root = createRoot( document.getElementById( 'root' ) );
	await act( async () => {
		root.render( <PostReviewsTable /> );
	} );
	await settle();
}

/** Let every pending request answer and every effect run. */
async function settle() {
	for ( let i = 0; i < 3; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
}

/**
 * The query arguments of a REST path.
 *
 * @param {string} path REST path.
 * @return {Object} Arguments by name.
 */
function listArgs( path ) {
	return Object.fromEntries(
		new URLSearchParams( path.split( '?' )[ 1 ] || '' )
	);
}

const listCalls = () =>
	apiFetch.mock.calls
		.map( ( [ { path } ] ) => path )
		.filter( ( path ) => path.startsWith( POSTS ) );
const lastListArgs = () => listArgs( listCalls().at( -1 ) );
const showAll = () =>
	[ ...document.querySelectorAll( 'button' ) ].find(
		( b ) => b.textContent === 'Show all posts'
	);
const titles = () =>
	[ ...document.querySelectorAll( '.pqm-cell-title a' ) ].map(
		( a ) => a.textContent
	);

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	act( () => root.unmount() );
} );

test( 'postquality_post=9: GET /posts asks for post 9 only, its panel opens on Review, "Show all posts" is offered', async () => {
	await open( `${ PAGE }&postquality_post=9` );

	expect( listCalls() ).toHaveLength( 1 );
	expect( lastListArgs().post_id ).toBe( '9' );
	expect( titles() ).toEqual( [ 'Linked post' ] );
	const toggle = document.querySelector( '[aria-controls="pqm-detail-9"]' );
	expect( toggle.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	const panel = document.getElementById( 'pqm-detail-9' );
	expect(
		panel.querySelector( '[role="tabpanel"]' ).getAttribute( 'data-tab' )
	).toBe( 'review' );
	expect( panel.textContent ).toContain( 'Edit for post 9.' );
	expect( showAll() ).toBeDefined();
	expect( window.location.search ).toContain( 'postquality_post=9' );
} );

test( '"Show all posts" fetches the whole list again and drops postquality_post from the address', async () => {
	await open( `${ PAGE }&postquality_post=9` );

	await act( async () => {
		showAll().click();
	} );
	await settle();

	expect( listCalls() ).toHaveLength( 2 );
	expect( lastListArgs() ).not.toHaveProperty( 'post_id' );
	expect( titles() ).toEqual( [ 'First post', 'Linked post' ] );
	expect( window.location.search ).toBe( '?page=postquality-posts' );
	expect( showAll() ).toBeUndefined();
} );

test( 'without postquality_post: no post_id is sent, no panel opens, no "Show all posts"', async () => {
	await open( PAGE );

	expect( listCalls() ).toHaveLength( 1 );
	expect( lastListArgs() ).not.toHaveProperty( 'post_id' );
	expect( titles() ).toEqual( [ 'First post', 'Linked post' ] );
	expect( document.querySelector( '.pqm-expanded-row' ) ).toBeNull();
	expect( showAll() ).toBeUndefined();
	expect( document.body.textContent ).not.toContain( 'Show all posts' );
} );

test( 'a post the list does not hold (postquality_post=42): the empty state offers "Show all posts", which shows the list', async () => {
	await open( `${ PAGE }&postquality_post=42` );

	expect( lastListArgs().post_id ).toBe( '42' );
	expect( titles() ).toEqual( [] );
	const empty = document.querySelector( '.pqm-empty-filtered' );
	expect( empty.textContent ).toContain( 'No posts match these filters.' );
	expect( empty.querySelector( 'button' ).textContent ).toBe(
		'Show all posts'
	);
	expect( document.querySelector( '.pqm-show-all' ) ).toBeNull();

	await act( async () => {
		showAll().click();
	} );
	await settle();

	expect( lastListArgs() ).not.toHaveProperty( 'post_id' );
	expect( titles() ).toEqual( [ 'First post', 'Linked post' ] );
	expect( window.location.search ).toBe( '?page=postquality-posts' );
} );
