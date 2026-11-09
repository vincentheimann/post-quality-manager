/**
 * Story 109: the Post Quality meta box, run in jsdom — the rows of the
 * plan's I/O matrix only a browser shows. Block editor: Save and review
 * saves first and starts the run only when the save went through and
 * nothing is left unsaved; a post published without a reload offers
 * Review. Classic Editor: unsaved changes start no run and ask for a save.
 * A post that is not published or pending offers no action and still shows
 * its past review. The other rows: the result, a run in progress on load
 * (it lands, or the wait gives up), a failed run, a post never reviewed, a
 * refused start, not connected, the quota, a viewer who may not run Review.
 * The WordPress-provided externals (api-fetch, components, data, url) are
 * not installed here: they are mocked; `core/editor` is a plain object the
 * tests change.
 */
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import MetaBox from '../../src/meta-box/meta-box';
import { GIVE_UP_AFTER, POLL_INTERVAL } from '../../src/kit/use-polling';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock( '@wordpress/url', () => ( { addQueryArgs: ( path ) => path } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/components',
	() => ( {
		Button: ( { children, isBusy, variant, ...props } ) => (
			<button { ...props }>{ children }</button>
		),
		Dashicon: () => null,
		Notice: ( { status, children, actions = [] } ) => (
			<div className={ 'mock-notice-' + status }>
				{ children }
				{ actions.map( ( { label, url } ) => (
					<a key={ label } href={ url }>
						{ label }
					</a>
				) ) }
			</div>
		),
		Spinner: () => null,
		VisuallyHidden: ( { children } ) => <span>{ children }</span>,
	} ),
	{ virtual: true }
);
// `core/editor` as the box reads it by name: the saved status, the dirty
// flag, savePost() and didPostSaveRequestFail(). `mockEditor` is set per test.
jest.mock(
	'@wordpress/data',
	() => {
		const store = () => ( {
			getCurrentPostAttribute: ( key ) =>
				key === 'status' ? mockEditor.status : undefined,
			isEditedPostDirty: () => mockEditor.dirty,
			didPostSaveRequestFail: () => mockEditor.saveFailed,
		} );
		const select = ( name ) =>
			name === 'core/editor' ? store() : undefined;
		return {
			select,
			useSelect: ( mapSelect ) => mapSelect( select ),
			dispatch: () => ( { savePost: () => mockEditor.savePost() } ),
		};
	},
	{ virtual: true }
);

const POSTS = '/post-quality/v1/posts';
const REVIEWS = '/post-quality/v1/reviews/7';
const TRIGGER = '/post-quality/v1/trigger-review';
const LINK = '/wp-admin/admin.php?page=postquality-posts&postquality_post=7';

/** The viewer block of a user who may run Review, on a connected site. */
const VIEWER = {
	can_trigger: true,
	quota: null,
	gated_commands: [],
	hidden_commands: [],
	connected: true,
	can_manage_settings: true,
};

/** A done Review row with a score and two top edits. */
const DONE = {
	command: 'review',
	status: 'needs_revision',
	overall_score: 64,
	result_data: { top_edits: [ 'Shorten the intro.', 'Cite a source.' ] },
	reviewed_at: '2026-11-01 10:00:00',
	reviewed_at_display: '2026-11-01 10:00',
};

let mockEditor;
let root;

/**
 * Mount the box. GET /posts answers with `viewer`, GET /reviews with
 * `rows`, POST /trigger-review accepts the run and, as the server does,
 * puts the run's `processing` row first in GET /reviews from then on;
 * `replies` replaces any of them by "METHOD path" with a value, a function
 * (asked at each call) or `{ reject }`.
 *
 * @param {Object}              props   Box props to change.
 * @param {Array|(() => Array)} rows    GET /reviews reply.
 * @param {Object}              replies Replies to change.
 * @param {Object}              viewer  The `viewer` block of GET /posts.
 */
async function mount( props = {}, rows = [], replies = {}, viewer = VIEWER ) {
	document.body.innerHTML = '<div id="root"></div>';
	apiFetch.mockReset();
	let startedAt = ''; // the accepted run's `reviewed_at`
	const stored = () => ( typeof rows === 'function' ? rows() : rows );
	apiFetch.mockImplementation( ( { path, method = 'GET' } ) => {
		let reply = {
			[ `GET ${ POSTS }` ]: { items: [], viewer },
			[ `GET ${ REVIEWS }` ]: () =>
				startedAt ? [ processing( startedAt ), ...stored() ] : stored(),
			[ `POST ${ TRIGGER }` ]: () => {
				startedAt = gmt( Date.now() );
				return { success: true };
			},
			...replies,
		}[ `${ method } ${ path }` ];
		if ( typeof reply === 'function' ) {
			reply = reply();
		}
		return reply && reply.reject
			? Promise.reject( reply.reject )
			: Promise.resolve( reply );
	} );
	root = createRoot( document.getElementById( 'root' ) );
	await render( props );
}

/**
 * Render (or render again, as a store change would) with the test props.
 *
 * @param {Object} props Box props to change.
 */
async function render( props = {} ) {
	await act( async () => {
		root.render(
			<MetaBox
				postId={ 7 }
				status="publish"
				isBlockEditor
				reviewsUrl={ LINK }
				{ ...props }
			/>
		);
	} );
}

/** Let every pending promise settle inside act (on the fake clock too). */
async function flush() {
	await act( async () => {
		for ( let i = 0; i < 50; i++ ) {
			await Promise.resolve();
		}
	} );
}

/** Click the box's action and let the save, the start and the reload settle. */
async function clickAction() {
	await act( async () => {
		action().click();
	} );
	await flush();
	await flush();
}

/**
 * Let `ms` pass on the fake clock, flushing the polls' promises.
 *
 * @param {number} ms Milliseconds.
 */
async function wait( ms ) {
	await act( async () => {
		jest.advanceTimersByTime( ms );
	} );
}

/**
 * GMT `Y-m-d H:i:s`, as the server stores `reviewed_at`.
 *
 * @param {number} ms Time in ms.
 * @return {string} Stored date.
 */
const gmt = ( ms ) =>
	new Date( ms ).toISOString().slice( 0, 19 ).replace( 'T', ' ' );

/**
 * The `processing` row the trigger writes, started at `reviewedAt`.
 *
 * @param {string} reviewedAt Stored start date.
 * @return {Object} Review row.
 */
const processing = ( reviewedAt ) => ( {
	command: 'review',
	status: 'processing',
	overall_score: 0,
	result_data: null,
	reviewed_at: reviewedAt,
} );

const action = () =>
	[ ...document.querySelectorAll( 'button' ) ].find( ( b ) =>
		/review/i.test( b.textContent )
	);
const triggered = () =>
	apiFetch.mock.calls.filter( ( [ { path } ] ) => path === TRIGGER ).length;
const status = () => document.querySelector( '[role="status"]' ).textContent;
const text = () => document.body.textContent;

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
	window.postqualityCommandLabels = { review: 'Review' };
} );

beforeEach( () => {
	mockEditor = {
		status: 'publish',
		dirty: false,
		saveFailed: false,
		savePost: jest.fn( () => Promise.resolve() ),
	};
	delete window.wp;
} );

afterEach( () => {
	act( () => root.unmount() );
	jest.useRealTimers();
} );

afterAll( () => {
	delete window.postqualityCommandLabels;
} );

test( 'block editor, unsaved changes, the save succeeds: Save and review saves, then the run starts', async () => {
	mockEditor.dirty = true;
	mockEditor.savePost = jest.fn( () => {
		mockEditor.dirty = false;
		return Promise.resolve();
	} );
	await mount();
	expect( action().textContent ).toBe( 'Save and review' );

	await clickAction();

	expect( mockEditor.savePost ).toHaveBeenCalledTimes( 1 );
	expect( triggered() ).toBe( 1 );
	expect( status() ).toContain( 'Review is running in n8n' );
	expect( action().disabled ).toBe( true );
} );

test( 'block editor, the save fails: no run, no message of the box', async () => {
	mockEditor.dirty = true;
	mockEditor.savePost = jest.fn( () => {
		mockEditor.saveFailed = true;
		return Promise.resolve();
	} );
	await mount();

	await clickAction();

	expect( mockEditor.savePost ).toHaveBeenCalledTimes( 1 );
	expect( triggered() ).toBe( 0 );
	expect( document.querySelector( '[class^="mock-notice"]' ) ).toBeNull();
	expect( status() ).toBe( '' );
	expect( action().disabled ).toBe( false );
	expect( action().textContent ).toBe( 'Save and review' );
} );

test( 'block editor, the save did not run (changes still unsaved): no run', async () => {
	mockEditor.dirty = true; // savePost() resolves and leaves the edits: another save was running
	await mount();

	await clickAction();

	expect( mockEditor.savePost ).toHaveBeenCalledTimes( 1 );
	expect( triggered() ).toBe( 0 );
	expect( action().disabled ).toBe( false );
} );

test( 'block editor, no unsaved changes: Run Review starts the run without a save', async () => {
	await mount();
	expect( action().textContent ).toBe( 'Run Review' );

	await clickAction();

	expect( mockEditor.savePost ).not.toHaveBeenCalled();
	expect( triggered() ).toBe( 1 );
} );

test( 'Classic Editor, unsaved changes: no run, the box asks for a save', async () => {
	window.wp = { autosave: { server: { postChanged: () => true } } };
	await mount( { isBlockEditor: false } );
	expect( action().textContent ).toBe( 'Run Review' );

	await clickAction();

	expect( triggered() ).toBe( 0 );
	expect( document.querySelector( '.mock-notice-warning' ).textContent ).toBe(
		'Save your changes first, then run the review.'
	);
} );

test( 'Classic Editor, nothing unsaved: the run starts', async () => {
	window.wp = { autosave: { server: { postChanged: () => false } } };
	await mount( { isBlockEditor: false } );

	await clickAction();

	expect( triggered() ).toBe( 1 );
	expect( document.querySelector( '.mock-notice-warning' ) ).toBeNull();
} );

test( 'not reviewable (a draft): the sentence, no action, no link, the past review still shown', async () => {
	mockEditor.status = 'draft';
	await mount( { status: 'draft' }, [ DONE ] );

	expect( text() ).toContain( 'Reviews run on published and pending posts.' );
	expect( action() ).toBeUndefined();
	expect( document.querySelector( `a[href="${ LINK }"]` ) ).toBeNull();
	expect( text() ).toContain( 'Shorten the intro.' );
	expect( text() ).not.toContain( 'Not reviewed yet.' );
} );

test( 'block editor, a draft published without a reload: the box offers Review and links to Post Reviews', async () => {
	mockEditor.status = 'draft';
	await mount( { status: 'draft' } );
	expect( action() ).toBeUndefined();

	mockEditor.status = 'publish'; // the editor saved the post as published
	await render( { status: 'draft' } );

	expect( action().textContent ).toBe( 'Run Review' );
	expect( text() ).toContain( 'Not reviewed yet.' );
	expect( document.querySelector( `a[href="${ LINK }"]` ) ).not.toBeNull();
} );

test( 'result: the score with its status badge, the date, the top edits and the link to the full review', async () => {
	await mount( {}, [ DONE ] );

	expect( document.querySelector( '.pqm-score-badge' ).textContent ).toBe(
		'64'
	);
	expect( document.querySelector( '.pqm-status-badge' ).textContent ).toBe(
		'Needs revision'
	);
	expect(
		document.querySelector( '.pqm-result-meta' ).textContent
	).toContain( '2026-11-01 10:00' );
	expect(
		[ ...document.querySelectorAll( '.pqm-top-edits li' ) ].map(
			( li ) => li.textContent
		)
	).toEqual( [ 'Shorten the intro.', 'Cite a source.' ] );
	const link = document.querySelector( `a[href="${ LINK }"]` );
	expect( link.textContent ).toBe( 'Open full review' );
	expect( action().textContent ).toBe( 'Re-run Review' );
	expect( status() ).toBe( '' );
} );

test( 'running on load: the running note in the status region, then the result replaces it without a reload', async () => {
	const now = Date.parse( '2026-11-09T10:00:00Z' );
	jest.useFakeTimers();
	jest.setSystemTime( now );
	const landed = { ...DONE, reviewed_at: gmt( now + 60 * 1000 ) };
	await mount( {}, () =>
		Date.now() < now + 60 * 1000 ? [ processing( gmt( now ) ) ] : [ landed ]
	);

	expect( status() ).toContain( 'Review is running in n8n' );
	expect( document.querySelector( '.pqm-result-meta' ) ).toBeNull();
	expect( action().disabled ).toBe( true );

	await wait( 60 * 1000 + POLL_INTERVAL );

	expect( status() ).not.toContain( 'is running in n8n' );
	expect( status() ).toContain(
		'Review complete — score 64, Needs revision.'
	);
	expect( document.querySelector( '.pqm-score-badge' ).textContent ).toBe(
		'64'
	);
	expect( text() ).toContain( 'Shorten the intro.' );
	expect( action().disabled ).toBe( false );
} );

test( "running on load, a stale run: the wait gives up with the kit's give-up message", async () => {
	const now = Date.parse( '2026-11-09T10:00:00Z' );
	jest.useFakeTimers();
	jest.setSystemTime( now );
	await mount( {}, [ processing( gmt( now - GIVE_UP_AFTER - 60 * 1000 ) ) ] );
	expect( status() ).toContain( 'Review is running in n8n' );

	await wait( POLL_INTERVAL );

	expect( status() ).toBe(
		'Review is still running in n8n — results will appear when it finishes.'
	);
	expect( action().disabled ).toBe( false );
} );

test.each( [
	[
		'a stored error',
		{ status: 'error', result_data: { error: 'Claude said no.' } },
		'Review could not complete: Claude said no. Check the n8n execution log, then try again.',
	],
	[
		"the template's empty fallback",
		{ status: '', result_data: {} },
		'Review could not complete: No result was returned. Check the n8n execution log, then try again.',
	],
] )(
	'failed (%s): the existing failure sentence',
	async ( _label, values, sentence ) => {
		await mount( {}, [ { ...DONE, overall_score: 0, ...values } ] );

		expect(
			document.querySelector( '.mock-notice-warning' ).textContent
		).toBe( sentence );
		expect( document.querySelector( '.pqm-top-edits' ) ).toBeNull();
		expect( action().textContent ).toBe( 'Re-run Review' );
	}
);

test( 'never reviewed: "Not reviewed yet." and Run Review', async () => {
	await mount();

	expect( text() ).toContain( 'Not reviewed yet.' );
	expect( action().textContent ).toBe( 'Run Review' );
	expect( action().disabled ).toBe( false );
	expect( document.querySelector( `a[href="${ LINK }"]` ) ).not.toBeNull();
} );

test.each( [
	[
		'monthly quota reached (429)',
		{
			reject: {
				code: 'pqm_quota_exceeded',
				message:
					'You have used all 20 AI actions this month. They reset on 2026-12-01.',
			},
		},
		'You have used all 20 AI actions this month. They reset on 2026-12-01.',
	],
	[
		'n8n unreachable (502)',
		{
			reject: {
				code: 'pqm_n8n_error',
				message:
					'Could not connect to n8n. Check your Webhook URL in Settings.',
			},
		},
		'Could not connect to n8n. Check your Webhook URL in Settings.',
	],
	[
		'n8n refusing (200, success false)',
		{
			success: false,
			message:
				'n8n answered 500. Check the workflow in n8n, then try again.',
		},
		'n8n answered 500. Check the workflow in n8n, then try again.',
	],
] )(
	"refused start, %s: the server's message in the box, back to idle",
	async ( _label, reply, message ) => {
		await mount( {}, [], { [ `POST ${ TRIGGER }` ]: reply } );

		await clickAction();

		expect( triggered() ).toBe( 1 );
		expect(
			document.querySelector( '.mock-notice-warning' ).textContent
		).toBe( message );
		expect( status() ).toBe( '' );
		expect( action().disabled ).toBe( false );
	}
);

test.each( [
	[
		'an administrator',
		true,
		'Import the pre-configured workflow into n8n',
		'Set up the n8n workflow',
	],
	[
		'an author',
		false,
		'Ask an administrator to connect the n8n workflow to this site.',
		undefined, // no link to the n8n Workflow page
	],
] )(
	'not connected, %s: the role-aware notice instead of the action',
	async ( _label, canManage, nextStep, linkLabel ) => {
		await mount(
			{},
			[],
			{},
			{ ...VIEWER, connected: false, can_manage_settings: canManage }
		);

		const notice = document.querySelector( '.mock-notice-warning' );
		expect( notice.textContent ).toContain(
			'n8n is not connected yet, so agents cannot run.'
		);
		expect( notice.textContent ).toContain( nextStep );
		expect(
			notice.querySelector(
				'a[href="admin.php?page=postquality-workflow"]'
			)?.textContent
		).toBe( linkLabel );
		expect( action() ).toBeUndefined();
	}
);

test( 'quota: a user with a monthly quota sees how many runs are left', async () => {
	await mount(
		{},
		[],
		{},
		{
			...VIEWER,
			quota: { limit: 20, used: 6, remaining: 14, resets: '2026-12-01' },
		}
	);

	expect( document.querySelector( '.pqm-quota-note' ).textContent ).toBe(
		'14 of 20 AI actions left this month'
	);
	expect( action().textContent ).toBe( 'Run Review' );
} );

test( 'view without run: the box shows the review and the link, without its action', async () => {
	await mount(
		{},
		[ DONE ],
		{},
		{
			...VIEWER,
			can_trigger: false,
			hidden_commands: [
				'review',
				'takeaways',
				'rewrite',
				'sources',
				'seo',
				'audit',
			],
		}
	);

	expect( text() ).toContain( 'Shorten the intro.' );
	expect( action() ).toBeUndefined();
	expect( document.querySelector( '.pqm-quota-note' ) ).toBeNull();
	expect( document.querySelector( `a[href="${ LINK }"]` ) ).not.toBeNull();
} );

test( 'block editor, the save request failed while nothing is left unsaved: no run', async () => {
	mockEditor.dirty = true;
	mockEditor.savePost = jest.fn( () => {
		mockEditor.dirty = false;
		mockEditor.saveFailed = true;
		return Promise.resolve();
	} );
	await mount();

	await clickAction();

	expect( mockEditor.savePost ).toHaveBeenCalledTimes( 1 );
	expect( triggered() ).toBe( 0 );
	expect( status() ).toBe( '' );
	expect( action().disabled ).toBe( false );
} );

test( 'quota after a start: the box reloads GET /posts and shows the new count', async () => {
	const quota = () => ( {
		items: [],
		viewer: {
			...VIEWER,
			quota: {
				limit: 20,
				used: 6 + triggered(),
				remaining: 14 - triggered(),
				resets: '2026-12-01',
			},
		},
	} );
	await mount( {}, [], { [ `GET ${ POSTS }` ]: quota } );
	expect( document.querySelector( '.pqm-quota-note' ).textContent ).toBe(
		'14 of 20 AI actions left this month'
	);

	await clickAction();

	expect( triggered() ).toBe( 1 );
	expect( document.querySelector( '.pqm-quota-note' ).textContent ).toBe(
		'13 of 20 AI actions left this month'
	);
} );

test( 'a run started over a past result: only the running note shows while the newest row is processing', async () => {
	await mount( {}, [ DONE ] );
	expect( text() ).toContain( 'Shorten the intro.' );

	await clickAction();

	expect( status() ).toContain( 'Review is running in n8n' );
	expect( document.querySelector( '.pqm-result-meta' ) ).toBeNull();
	expect( document.querySelector( '.pqm-top-edits' ) ).toBeNull();
	expect( action().disabled ).toBe( true );
} );

test( 'a never-reviewed post whose run gives up: the give-up message, never "Not reviewed yet."', async () => {
	const now = Date.parse( '2026-11-09T10:00:00Z' );
	jest.useFakeTimers();
	jest.setSystemTime( now );
	await mount();
	expect( text() ).toContain( 'Not reviewed yet.' );

	await clickAction();
	expect( status() ).toContain( 'Review is running in n8n' );
	expect( text() ).not.toContain( 'Not reviewed yet.' );

	await wait( GIVE_UP_AFTER + 2 * POLL_INTERVAL );

	expect( status() ).toBe(
		'Review is still running in n8n — results will appear when it finishes.'
	);
	expect( text() ).not.toContain( 'Not reviewed yet.' );
	expect( action().textContent ).toBe( 'Re-run Review' );
	expect( action().disabled ).toBe( false );
} );
