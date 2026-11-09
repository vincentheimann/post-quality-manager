/**
 * Story 2, goal (b): the setup dialog's first-review step, run alone in
 * jsdom with fake timers. The wait counts from the run's start (the
 * `processing` row's `reviewed_at`), so a reopened dialog gives up at once
 * on a stale run, and the give-up line names the webhook URL, the site
 * address n8n must reach, Executions and Test again. One test per row of
 * the plan's I/O matrix. The WordPress-provided externals (api-fetch,
 * components, url) are not installed here: they are mocked.
 */
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import FirstReview from '../../src/setup/first-review';
import { GIVE_UP_AFTER, POLL_INTERVAL } from '../../src/kit/use-polling';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock( '@wordpress/url', () => ( { addQueryArgs: ( path ) => path } ), {
	virtual: true,
} );
jest.mock(
	'@wordpress/components',
	() => ( {
		Button: ( { children, isBusy, variant, ...props } ) => (
			<button data-variant={ variant } { ...props }>
				{ children }
			</button>
		),
		Notice: ( { status, children } ) => (
			<div className={ 'mock-notice-' + status }>{ children }</div>
		),
		SelectControl: () => null,
		Spinner: () => null,
	} ),
	{ virtual: true }
);

const POSTS = '/post-quality/v1/posts';
const REVIEWS = '/post-quality/v1/reviews/7';
const TRIGGER = '/post-quality/v1/trigger-review';
const WEBHOOK = 'https://n8n.example.com/webhook/pqm-execute-example-com';
const REST = 'https://example.com/wp-json/';
const NOW = Date.parse( '2026-10-22T10:00:00Z' );
const MINUTE = 60 * 1000;

/**
 * GET /posts item of the one published post; `values` override it.
 *
 * @param {Object} values Fields to change.
 * @return {Object} List item.
 */
function post( values = {} ) {
	return {
		post_id: 7,
		title: 'Hello world',
		wp_post_status: 'publish',
		in_flight: [],
		...values,
	};
}

/**
 * GMT `Y-m-d H:i:s`, as the server stores `reviewed_at`.
 *
 * @param {number} ms Time in ms.
 * @return {string} Stored date.
 */
const gmt = ( ms ) =>
	new Date( ms ).toISOString().slice( 0, 19 ).replace( 'T', ' ' );

const processing = ( reviewedAt ) => ( {
	command: 'review',
	status: 'processing',
	overall_score: 0,
	result_data: null,
	reviewed_at: reviewedAt,
} );

let root;
let replies;
let onComplete;

/**
 * Mount the step. Every call answers from `replies[ method + ' ' + path ]`:
 * a function, a value (resolved) or `{ reject }`.
 *
 * @param {Object} answers Replies by "METHOD path".
 * @param {Object} props   Props to change.
 */
async function mount( answers, props = {} ) {
	document.body.innerHTML = '<div id="root"></div>';
	replies = answers;
	onComplete = jest.fn();
	apiFetch.mockReset();
	apiFetch.mockImplementation( ( { path, method = 'GET' } ) => {
		let reply = replies[ `${ method } ${ path }` ];
		if ( typeof reply === 'function' ) {
			reply = reply();
		}
		return reply && reply.reject
			? Promise.reject( reply.reject )
			: Promise.resolve( reply );
	} );
	root = createRoot( document.getElementById( 'root' ) );
	await act( async () => {
		root.render(
			<FirstReview
				connected
				webhookUrl={ WEBHOOK }
				restUrl={ REST }
				onComplete={ onComplete }
				{ ...props }
			/>
		);
	} );
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

async function runReview() {
	await act( async () => {
		runButton().click();
	} );
}

const runButton = () =>
	[ ...document.querySelectorAll( 'button' ) ].find(
		( b ) => b.textContent === 'Run Review'
	);
const warning = () => document.querySelector( '.mock-notice-warning' );
const reviewPolls = () =>
	apiFetch.mock.calls.filter( ( [ { path } ] ) => path === REVIEWS ).length;

/**
 * The give-up line names both addresses and the two places to look.
 *
 * @param {Element|null} notice Warning notice.
 */
function expectGiveUpLine( notice ) {
	expect( notice ).not.toBeNull();
	const text = notice.textContent;
	expect( text ).toContain( 'No result came back within 3 minutes.' );
	expect( text ).toContain( WEBHOOK );
	expect( text ).toContain( REST );
	expect( text ).toContain( 'Executions' );
	expect( text ).toContain( 'Test again' );
	expect( text ).not.toContain( 'still running in n8n' );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	jest.useFakeTimers();
	jest.setSystemTime( NOW );
} );

afterEach( () => {
	act( () => root.unmount() );
	jest.useRealTimers();
} );

/**
 * A fresh run: the trigger writes the `processing` row now, then the rows
 * answer from `reviews` (a function of the poll).
 *
 * @param {(() => Object)|Object} reviews GET /reviews reply.
 */
async function startRun( reviews ) {
	await mount( {
		[ `GET ${ POSTS }` ]: { items: [ post() ] },
		[ `POST ${ TRIGGER }` ]: { success: true },
		[ `GET ${ REVIEWS }` ]: reviews,
	} );
	await runReview();
}

test( 'result lands in time: the score line, onComplete, no warning', async () => {
	const scored = {
		command: 'review',
		status: 'needs_revision',
		overall_score: 64,
		result_data: { summary: 'ok' },
		reviewed_at: gmt( NOW + MINUTE ),
	};
	await startRun( () =>
		Date.now() < NOW + MINUTE ? [ processing( gmt( NOW ) ) ] : [ scored ]
	);
	expect( runButton().disabled ).toBe( true );

	await wait( MINUTE + POLL_INTERVAL );

	expect(
		document.querySelector( '.pqm-setup-score' ).textContent
	).toContain( 'Review complete — score 64' );
	expect( onComplete ).toHaveBeenCalledTimes( 1 );
	expect( onComplete ).toHaveBeenCalledWith( scored );
	expect( warning() ).toBeNull();
} );

test( 'run fails: the failed notice, unchanged', async () => {
	await startRun( [
		{
			command: 'review',
			status: 'error',
			overall_score: 0,
			result_data: { error: 'Claude said no.' },
			reviewed_at: gmt( NOW ),
		},
	] );

	await wait( POLL_INTERVAL );

	const notice = document.querySelector( '.mock-notice-error' );
	expect( notice.textContent ).toContain( 'Claude said no.' );
	expect( notice.textContent ).toContain( 'The review could not complete.' );
	expect( onComplete ).not.toHaveBeenCalled();
	expect( warning() ).toBeNull();
} );

test( 'nothing lands: within 3 minutes of the run, the warning names both addresses; Run Review enabled again', async () => {
	await startRun( [ processing( gmt( NOW ) ) ] );

	await wait( GIVE_UP_AFTER - POLL_INTERVAL );
	expect( warning() ).toBeNull();
	expect( runButton().disabled ).toBe( true );

	await wait( 2 * POLL_INTERVAL );
	expectGiveUpLine( warning() );
	expect( runButton().disabled ).toBe( false );
	expect( onComplete ).not.toHaveBeenCalled();
} );

test( 'resume, recent run: waits about 2 more minutes, then the same warning', async () => {
	await mount( {
		[ `GET ${ POSTS }` ]: {
			items: [ post( { in_flight: [ 'review' ] } ) ],
		},
		[ `GET ${ REVIEWS }` ]: [ processing( gmt( NOW - MINUTE ) ) ],
	} );
	expect( runButton().disabled ).toBe( true );

	await wait( 2 * MINUTE - POLL_INTERVAL );
	expect( warning() ).toBeNull();

	await wait( 2 * POLL_INTERVAL );
	expectGiveUpLine( warning() );
	expect( runButton().disabled ).toBe( false );
} );

test( 'resume, stale run: the warning at the first poll, no fresh wait', async () => {
	await mount( {
		[ `GET ${ POSTS }` ]: {
			items: [ post( { in_flight: [ 'review' ] } ) ],
		},
		[ `GET ${ REVIEWS }` ]: [ processing( gmt( NOW - 10 * MINUTE ) ) ],
	} );

	await wait( POLL_INTERVAL );

	expectGiveUpLine( warning() );
	expect( reviewPolls() ).toBe( 1 );
	expect( runButton().disabled ).toBe( false );
} );

test.each( [
	[ 'blank', '' ],
	[ 'zero', '0000-00-00 00:00:00' ],
] )(
	'unparseable reviewed_at (%s): falls back to the mount time',
	async ( _label, reviewedAt ) => {
		await mount( {
			[ `GET ${ POSTS }` ]: {
				items: [ post( { in_flight: [ 'review' ] } ) ],
			},
			[ `GET ${ REVIEWS }` ]: [ processing( reviewedAt ) ],
		} );

		await wait( GIVE_UP_AFTER - POLL_INTERVAL );
		expect( warning() ).toBeNull();

		await wait( 2 * POLL_INTERVAL );
		expectGiveUpLine( warning() );
	}
);

test( 'missed poll: retried on the next tick, the limit still holds', async () => {
	await startRun( { reject: { message: 'offline' } } );

	await wait( 3 * POLL_INTERVAL );
	expect( reviewPolls() ).toBe( 3 );
	expect( warning() ).toBeNull();

	await wait( GIVE_UP_AFTER );
	expectGiveUpLine( warning() );
	expect( runButton().disabled ).toBe( false );
} );

test( 'no URL in state: the sentence leaves out the URL clause, never a blank address', async () => {
	await mount(
		{
			[ `GET ${ POSTS }` ]: {
				items: [ post( { in_flight: [ 'review' ] } ) ],
			},
			[ `GET ${ REVIEWS }` ]: [ processing( gmt( NOW - 10 * MINUTE ) ) ],
		},
		{ webhookUrl: '' }
	);

	await wait( POLL_INTERVAL );

	const text = warning().textContent;
	expect( text ).toContain( REST );
	expect( text ).not.toContain( 'was sent to' );
	expect( text ).not.toMatch( /to\s*;/ );
	expect( text ).toContain( 'Test again' );
} );
