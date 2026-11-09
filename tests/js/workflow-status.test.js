/**
 * The n8n Workflow page in pqm-admin.js, run in jsdom (story 67): one
 * body under the H1 (#pqm-workflow-body) that every reply swaps whole —
 * the test's (admin-ajax), the REST actions' (Update workflow, Rotate
 * token, Save and test, "Set up by hand instead") and the refresh on
 * window focus or when the setup dialog closes (postquality:setup-closed). Each
 * swap dispatches postquality:workflow-status once, moves focus sensibly and
 * leaves no detached node written. The fixture mirrors
 * render_workflow_body()'s markup; render-matrix-harness.php locks the
 * PHP side (the states, status_html, the one primary).
 */

const SCRIPT = '../../post-quality-manager/admin/js/pqm-admin.js';

/**
 * A body as render_workflow_body() renders it: the status block (an
 * optional outcome line, the sentence, the actions) then the disclosures.
 *
 * @param {Object} parts
 * @param {string} parts.state    data-state.
 * @param {string} parts.sentence The state sentence.
 * @param {string} parts.actions  The actions row's inner HTML.
 * @param {string} parts.rest     What follows the block.
 * @param {string} parts.outcome  An outcome line, '' for none.
 * @return {string} The body's inner HTML.
 */
function body( { state, sentence, actions, rest = '', outcome = '' } ) {
	return (
		`<div class="pqm-workflow-status" data-state="${ state }">${ outcome }` +
		`<p class="pqm-workflow-sentence">${ sentence }</p>` +
		`<p class="pqm-workflow-actions">${ actions }</p></div>${ rest }`
	);
}

const TEST_AGAIN =
	'<button type="button" class="button pqm-test-connection">Test again</button>';
const TEST_PRIMARY =
	'<button type="button" class="button button-primary pqm-test-connection">Test again</button>';
const UPDATE =
	'<button type="button" class="button button-primary pqm-update-workflow" data-pqm-post="n8n/workflow" data-pqm-busy="Updating the workflow…">Update workflow</button>';
// Story 76: an adopted map's Update asks first (the n8n connection island listens).
const UPDATE_ADOPTED = UPDATE.replace(
	'data-pqm-post="n8n/workflow"',
	'data-pqm-post="n8n/workflow" data-pqm-confirm="update"'
);
const REIMPORT =
	'<a class="button button-primary pqm-reimport" href="#pqm-by-hand">Re-import the workflow</a>';
const BY_HAND =
	'<details class="pqm-disclosure" id="pqm-by-hand"><summary><h2>Set up by hand</h2></summary><p>Steps.</p></details>';
const WEBHOOK =
	'<details class="pqm-disclosure" id="pqm-webhook"><summary><h2>Webhook</h2></summary>' +
	'<form class="pqm-webhook-form" data-pqm-post="setup/connect"><input type="url" name="webhook_url" value="https://n8n.example/webhook/x">' +
	'<button type="submit" class="button pqm-save-webhook" data-pqm-busy="Saving and testing…">Save and test</button></form>' +
	'<button type="button" class="button pqm-rotate-token" data-pqm-post="n8n/token" data-pqm-busy="Rotating the token…">Rotate token</button></details>';
const CONNECTION =
	'<details class="pqm-disclosure" id="pqm-n8n-connection"><summary><h2>n8n connection</h2></summary><div id="pqm-n8n-connection-root"></div></details>';

const SET_UP = body( {
	state: 'set-up',
	sentence: 'Your workflow answers.',
	actions: `<button type="button" class="button button-primary pqm-open-setup" data-step="review">Run your first review</button> ${ TEST_AGAIN }`,
	rest: BY_HAND + CONNECTION + WEBHOOK,
} );
const OUTDATED = body( {
	state: 'outdated',
	sentence: 'Your workflow is version 5; this plugin needs 6.',
	actions: `${ REIMPORT } ${ TEST_AGAIN }`,
	rest: BY_HAND + CONNECTION + WEBHOOK,
} );
const MANAGED_OUTDATED = body( {
	state: 'outdated',
	sentence: 'Your workflow is version 5; this plugin needs 6.',
	actions: `${ UPDATE } ${ TEST_AGAIN }`,
	rest: CONNECTION + WEBHOOK,
} );
// The built app's island root sits in CONNECTION; without it (no built app, D2) the click posts.
const ADOPTED_OUTDATED = MANAGED_OUTDATED.replace( UPDATE, UPDATE_ADOPTED );
const ADOPTED_NO_APP = ADOPTED_OUTDATED.replace(
	'<div id="pqm-n8n-connection-root"></div>',
	''
);
const FAILING = body( {
	state: 'failing',
	sentence: 'The last test just now did not pass: n8n responded with 404.',
	actions: TEST_PRIMARY,
	rest: BY_HAND + CONNECTION + WEBHOOK,
	outcome:
		'<p class="pqm-workflow-outcome is-error">n8n responded with 404.</p>',
} );
const NOT_SET_UP = body( {
	state: 'not-set-up',
	sentence: 'Not connected to n8n yet.',
	actions:
		'<button type="button" class="button button-primary pqm-open-setup">Set up n8n</button>',
	rest: '<p><a href="#pqm-by-hand" class="pqm-by-hand-link" data-pqm-post="setup" data-pqm-body="{&quot;manual&quot;:true}" data-pqm-focus="pqm-by-hand">Set up by hand instead</a></p>',
} );
const MANUAL = body( {
	state: 'manual',
	sentence: 'Setting up by hand.',
	actions: '',
	rest:
		BY_HAND.replace( 'id="pqm-by-hand"', 'id="pqm-by-hand" open=""' ) +
		CONNECTION,
} );

/**
 * The n8n Workflow page around this body.
 *
 * @param {string} inner The body's inner HTML.
 */
function renderPage( inner = SET_UP ) {
	document.body.innerHTML = `
		<div class="wrap pqm-wrap">
			<h1>n8n Workflow</h1>
			<div id="pqm-setup-root"></div>
			<div id="pqm-workflow-body" aria-live="polite">${ inner }</div>
		</div>`;
}

const windowListeners = [];
const documentListeners = [];

/** Load pqm-admin.js fresh and run its DOMContentLoaded boot once. */
function boot() {
	window.postqualityAdmin = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		pageNonce: 'nonce-123',
		restUrl: '/wp-json/post-quality/v1/',
		restNonce: 'rest-nonce-456',
		i18n: {
			testing: 'Testing the connection…',
			networkError: 'The request did not go through.',
		},
	};
	const listen = jest.spyOn( document, 'addEventListener' );
	const listenWindow = jest.spyOn( window, 'addEventListener' );
	jest.isolateModules( () => require( SCRIPT ) );
	const [ , init ] = listen.mock.calls.find(
		( [ type ] ) => type === 'DOMContentLoaded'
	);
	listen.mockClear();
	init();
	// The boot's own document listeners, removed after each test.
	documentListeners.push( ...listen.mock.calls );
	listen.mockRestore();
	windowListeners.push( ...listenWindow.mock.calls );
	listenWindow.mockRestore();
}

/**
 * Answer every fetch with this admin-ajax JSON body (or reject).
 *
 * @param {unknown} json   Parsed JSON the server answers.
 * @param {boolean} reject The request fails (network).
 */
function answer( json, reject = false ) {
	global.fetch = jest.fn( () =>
		reject
			? Promise.reject( new Error( 'offline' ) )
			: Promise.resolve( { json: () => Promise.resolve( json ) } )
	);
}

/**
 * Answer the REST call with this HTTP status and JSON body (or reject).
 *
 * @param {number}  httpStatus The HTTP status (a REST error is 4xx/5xx).
 * @param {unknown} json       Parsed JSON the route answers.
 * @param {boolean} reject     The request fails (network).
 */
function answerRest( httpStatus, json, reject = false ) {
	global.fetch = jest.fn( () =>
		reject
			? Promise.reject( new Error( 'offline' ) )
			: Promise.resolve( {
					ok: httpStatus < 400,
					status: httpStatus,
					json: () => Promise.resolve( json ),
				} )
	);
}

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
const bodyEl = () => document.getElementById( 'pqm-workflow-body' );
const status = () => document.querySelectorAll( '.pqm-workflow-status' );
const outcome = () => document.querySelector( '.pqm-workflow-outcome' );
const $ = ( selector ) => document.querySelector( selector );
const sentAjax = ( call = 0 ) =>
	Object.fromEntries( global.fetch.mock.calls[ call ][ 1 ].body.entries() );
let heard;
const listener = () => heard++;

beforeEach( () => {
	heard = 0;
	document.addEventListener( 'postquality:workflow-status', listener );
} );

afterEach( () => {
	document.removeEventListener( 'postquality:workflow-status', listener );
	windowListeners
		.splice( 0 )
		.forEach( ( [ type, fn ] ) => window.removeEventListener( type, fn ) );
	documentListeners
		.splice( 0 )
		.forEach( ( [ type, fn ] ) =>
			document.removeEventListener( type, fn )
		);
	delete global.fetch;
} );

describe( 'Test the connection / Test again', () => {
	test( 'a passing ping: the saved URL pinged, the busy label meanwhile, the body swapped whole, the region kept, the islands told once', async () => {
		renderPage( OUTDATED );
		boot();
		const region = bodyEl();
		answer( {
			success: true,
			data: {
				message: 'Ping OK — up to date.',
				outdated: false,
				status_html: SET_UP,
			},
		} );
		const button = $( '.pqm-test-connection' );
		button.click();
		expect( button.disabled ).toBe( true );
		expect( button.textContent ).toBe( 'Testing the connection…' );
		expect( outcome().textContent ).toBe( 'Testing the connection…' );
		await flush();
		expect( sentAjax() ).toEqual( {
			action: 'postquality_test_connection',
			webhook_url: '',
			_wpnonce: 'nonce-123',
		} );
		expect( bodyEl() ).toBe( region );
		expect( region.getAttribute( 'aria-live' ) ).toBe( 'polite' );
		expect( region.innerHTML ).toBe( SET_UP );
		expect( status() ).toHaveLength( 1 );
		expect( heard ).toBe( 1 );
		// Swapped out with its body: left as it was once the ping went out.
		expect( button.isConnected ).toBe( false );
	} );

	test( 'a failed ping: the error reply carries the body too, swapped in with its outcome line', async () => {
		renderPage();
		boot();
		answer( {
			success: false,
			data: {
				message: 'n8n responded with 404.',
				status_html: FAILING,
			},
		} );
		$( '.pqm-test-connection' ).click();
		await flush();
		expect( bodyEl().innerHTML ).toBe( FAILING );
		expect( outcome().className ).toBe( 'pqm-workflow-outcome is-error' );
		expect( heard ).toBe( 1 );
	} );

	test( 'focus on the button that went with the old body lands on the new primary action, else on the status block', async () => {
		renderPage( FAILING );
		boot();
		const button = $( '.pqm-test-connection' );
		button.focus();
		answer( {
			success: true,
			data: { message: 'Ping OK.', outdated: false, status_html: SET_UP },
		} );
		button.click();
		await flush();
		expect( document.activeElement ).toBe( $( '.pqm-open-setup' ) );

		$( '.pqm-test-connection' ).focus();
		answer( { success: true, data: { status_html: MANUAL } } );
		$( '.pqm-test-connection' ).click();
		await flush();
		expect( document.activeElement ).toBe( status()[ 0 ] );
		expect( status()[ 0 ].getAttribute( 'tabindex' ) ).toBe( '-1' );
	} );

	test.each( [
		[
			'an error string (no URL saved)',
			[ { success: false, data: 'Save your n8n webhook URL first.' } ],
			'Save your n8n webhook URL first.',
		],
		[
			'a network failure',
			[ null, true ],
			'The request did not go through.',
		],
	] )(
		'%s: the body stays, the outcome line says so, the button enabled again, no event',
		async ( _, args, message ) => {
			renderPage();
			boot();
			answer( ...args );
			$( '.pqm-test-connection' ).click();
			await flush();
			expect( bodyEl().innerHTML ).not.toBe( SET_UP ); // the outcome line was added
			expect( status()[ 0 ].firstChild ).toBe( outcome() );
			expect( outcome().textContent ).toBe( message );
			expect( outcome().className ).toBe(
				'pqm-workflow-outcome is-error'
			);
			expect( $( '.pqm-test-connection' ).disabled ).toBe( false );
			expect( $( '.pqm-test-connection' ).textContent ).toBe(
				'Test again'
			);
			expect( heard ).toBe( 0 );
		}
	);
} );

describe( 'REST actions: Update workflow, Rotate token, Save and test, Set up by hand instead', () => {
	test( 'Update workflow posts to n8n/workflow with the nonce and an empty JSON body, shows its busy label, then swaps the body', async () => {
		renderPage( MANAGED_OUTDATED );
		boot();
		answerRest( 200, {
			updated: true,
			ping_ok: true,
			outdated: false,
			message: 'Workflow updated.',
			status_html: SET_UP,
		} );
		// Story 76: a provisioned map's button never asks.
		const asked = jest.fn();
		document.addEventListener( 'postquality:confirm-action', asked );
		const button = $( '.pqm-update-workflow' );
		button.click();
		document.removeEventListener( 'postquality:confirm-action', asked );
		expect( asked ).not.toHaveBeenCalled();
		expect( button.disabled ).toBe( true );
		expect( button.textContent ).toBe( 'Updating the workflow…' );
		expect( outcome().textContent ).toBe( 'Updating the workflow…' );
		await flush();
		const [ url, init ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wp-json/post-quality/v1/n8n/workflow' );
		expect( init ).toEqual( {
			method: 'POST',
			headers: {
				'X-WP-Nonce': 'rest-nonce-456',
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: '{}',
		} );
		expect( bodyEl().innerHTML ).toBe( SET_UP );
		expect( heard ).toBe( 1 );
	} );

	test( 'Rotate token, in the Webhook disclosure, posts to n8n/token and swaps the body', async () => {
		renderPage();
		boot();
		answerRest( 200, {
			rotated: true,
			ping_ok: true,
			outdated: false,
			message: 'Token rotated.',
			status_html: SET_UP.replace( 'answers.', 'answers!' ),
		} );
		$( '.pqm-rotate-token' ).click();
		expect( $( '.pqm-rotate-token' ).textContent ).toBe(
			'Rotating the token…'
		);
		await flush();
		expect( global.fetch.mock.calls[ 0 ][ 0 ] ).toBe(
			'/wp-json/post-quality/v1/n8n/token'
		);
		expect( $( '.pqm-workflow-sentence' ).textContent ).toBe(
			'Your workflow answers!'
		);
	} );

	test( 'Save and test (the form) posts its field to setup/connect, on submit, and swaps the body', async () => {
		renderPage();
		boot();
		answerRest( 200, {
			saved: true,
			ping_ok: true,
			outdated: false,
			message: 'Ping OK.',
			status_html: SET_UP,
		} );
		const form = $( '.pqm-webhook-form' );
		form.querySelector( 'input' ).value = ' https://n8n.example/webhook/y ';
		const submit = new window.Event( 'submit', {
			bubbles: true,
			cancelable: true,
		} );
		form.dispatchEvent( submit );
		expect( submit.defaultPrevented ).toBe( true );
		expect( $( '.pqm-save-webhook' ).textContent ).toBe(
			'Saving and testing…'
		);
		await flush();
		const [ url, init ] = global.fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wp-json/post-quality/v1/setup/connect' );
		expect( init.body ).toBe(
			'{"webhook_url":"https://n8n.example/webhook/y"}'
		);
		expect( heard ).toBe( 1 );
	} );

	test( '"Set up by hand instead" posts {manual: true} to setup, then re-reads the body (no status_html in that reply) and opens and focuses Set up by hand', async () => {
		renderPage( NOT_SET_UP );
		boot();
		const calls = [];
		global.fetch = jest.fn( ( url, init ) => {
			calls.push( [ url, init ] );
			return Promise.resolve( {
				ok: true,
				status: 200,
				json: () =>
					Promise.resolve(
						calls.length === 1
							? { manual: true }
							: { success: true, data: { status_html: MANUAL } }
					),
			} );
		} );
		const click = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		$( '.pqm-by-hand-link' ).dispatchEvent( click );
		expect( click.defaultPrevented ).toBe( true );
		// No busy label on the link: no (empty) outcome line either.
		expect( outcome() ).toBeNull();
		await flush();
		await flush();
		expect( calls[ 0 ][ 0 ] ).toBe( '/wp-json/post-quality/v1/setup' );
		expect( calls[ 0 ][ 1 ].body ).toBe( '{"manual":true}' );
		expect( calls[ 1 ][ 0 ] ).toBe( '/wp-admin/admin-ajax.php' );
		expect(
			Object.fromEntries( calls[ 1 ][ 1 ].body.entries() ).action
		).toBe( 'postquality_workflow_status' );
		expect( bodyEl().innerHTML ).toBe( MANUAL );
		const details = document.getElementById( 'pqm-by-hand' );
		expect( details.open ).toBe( true );
		expect( document.activeElement ).toBe(
			details.querySelector( 'summary' )
		);
		expect( heard ).toBe( 1 );
	} );

	test.each( [
		[
			'a refused action (a REST error)',
			[
				502,
				{
					code: 'pqm_update_failed',
					message:
						'The plugin could not finish updating the workflow.',
					data: { status: 502 },
				},
			],
			'The plugin could not finish updating the workflow.',
		],
		[
			'a network failure',
			[ 0, null, true ],
			'The request did not go through.',
		],
	] )(
		'%s: the body stays, the message in the outcome line, the button enabled again, no event',
		async ( _, args, message ) => {
			renderPage( MANAGED_OUTDATED );
			boot();
			answerRest( ...args );
			$( '.pqm-update-workflow' ).click();
			await flush();
			expect( status()[ 0 ].textContent ).toContain(
				'this plugin needs 6.'
			);
			expect( outcome().textContent ).toBe( message );
			expect( outcome().className ).toBe(
				'pqm-workflow-outcome is-error'
			);
			expect( $( '.pqm-update-workflow' ).disabled ).toBe( false );
			expect( $( '.pqm-update-workflow' ).textContent ).toBe(
				'Update workflow'
			);
			expect( heard ).toBe( 0 );
		}
	);

	test( 'story 74 (D4): a refusal whose data carries the body (Rotate, Update) swaps it in, the outcome line inside, the islands told once', async () => {
		renderPage( MANAGED_OUTDATED );
		boot();
		const refused =
			'<div class="pqm-workflow-status" data-state="outdated"><p class="pqm-workflow-outcome is-error">The plugin could not finish updating the workflow: n8n refused the API key.</p></div>';
		answerRest( 502, {
			code: 'pqm_update_failed',
			message:
				'The plugin could not finish updating the workflow: n8n refused the API key.',
			data: { status: 502, status_html: refused },
		} );
		$( '.pqm-update-workflow' ).click();
		await flush();
		expect( bodyEl().innerHTML ).toBe( refused );
		expect( $( '.pqm-update-workflow' ) ).toBeNull();
		expect( outcome().textContent ).toBe(
			'The plugin could not finish updating the workflow: n8n refused the API key.'
		);
		expect( heard ).toBe( 1 );
	} );

	test( "story 76: an adopted map's Update dispatches the cancelable postquality:confirm-action; prevented, nothing is sent and no busy label shows", async () => {
		renderPage( ADOPTED_OUTDATED );
		boot();
		answerRest( 200, { updated: true, status_html: SET_UP } );
		const asked = jest.fn( ( event ) => event.preventDefault() );
		document.addEventListener( 'postquality:confirm-action', asked );
		const button = $( '.pqm-update-workflow' );
		button.click();
		document.removeEventListener( 'postquality:confirm-action', asked );
		await flush();
		expect( asked ).toHaveBeenCalledTimes( 1 );
		const [ event ] = asked.mock.calls[ 0 ];
		expect( event.cancelable ).toBe( true );
		expect( event.detail.action ).toBe( 'update' );
		expect( event.detail.element ).toBe( button );
		expect( global.fetch ).not.toHaveBeenCalled();
		expect( button.disabled ).toBe( false );
		expect( button.textContent ).toBe( 'Update workflow' );
		expect( outcome() ).toBeNull();
		expect( bodyEl().innerHTML ).toBe( ADOPTED_OUTDATED );
	} );

	test( "story 76: an adopted map's Update unheard while the built app is on the page (its island not mounted yet) sends nothing, the outcome line says so", async () => {
		renderPage( ADOPTED_OUTDATED );
		boot();
		answerRest( 200, { updated: true, status_html: SET_UP } );
		$( '.pqm-update-workflow' ).click();
		await flush();
		expect( global.fetch ).not.toHaveBeenCalled();
		expect( $( '.pqm-update-workflow' ).disabled ).toBe( false );
		expect( $( '.pqm-update-workflow' ).textContent ).toBe(
			'Update workflow'
		);
		expect( outcome().textContent ).toBe(
			'The page is still loading its state. Try again in a moment.'
		);
		expect( heard ).toBe( 0 );
	} );

	test( "story 76 (D2): an adopted map's Update with no listener (no built app) posts as before", async () => {
		renderPage( ADOPTED_NO_APP );
		boot();
		answerRest( 200, { updated: true, status_html: SET_UP } );
		$( '.pqm-update-workflow' ).click();
		expect( $( '.pqm-update-workflow' ).textContent ).toBe(
			'Updating the workflow…'
		);
		await flush();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( global.fetch.mock.calls[ 0 ][ 0 ] ).toBe(
			'/wp-json/post-quality/v1/n8n/workflow'
		);
		expect( bodyEl().innerHTML ).toBe( SET_UP );
		expect( heard ).toBe( 1 );
	} );

	test( 'a second click while the first is in flight sends nothing', async () => {
		renderPage( MANAGED_OUTDATED );
		boot();
		answerRest( 200, { updated: true, status_html: SET_UP } );
		$( '.pqm-update-workflow' ).click();
		$( '.pqm-update-workflow' ).click();
		await flush();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'Status refresh: window focus and the setup dialog', () => {
	test( 'focus re-reads the body from WordPress and swaps it', async () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: OUTDATED } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( sentAjax() ).toEqual( {
			action: 'postquality_workflow_status',
			_wpnonce: 'nonce-123',
		} );
		expect( bodyEl().innerHTML ).toBe( OUTDATED );
		expect( heard ).toBe( 1 );
	} );

	test( 'focus when nothing changed (the fresh status block equals the current one): read, but no swap, no event — the island and typed values stay', async () => {
		renderPage();
		boot();
		const island = $( '#pqm-n8n-connection-root' );
		island.innerHTML = '<input value="typed-key" />';
		answer( { success: true, data: { status_html: SET_UP } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( $( '#pqm-n8n-connection-root' ) ).toBe( island );
		expect( $( '#pqm-n8n-connection-root input' ).value ).toBe(
			'typed-key'
		);
		expect( heard ).toBe( 0 );
		// An event-driven refresh swaps even when nothing changed.
		document.dispatchEvent(
			new window.CustomEvent( 'postquality:workflow-refresh' )
		);
		await flush();
		expect( $( '#pqm-n8n-connection-root' ) ).not.toBe( island );
		expect( heard ).toBe( 1 );
	} );

	test( 'a swap keeps the disclosures the owner opened open', async () => {
		renderPage();
		boot();
		document.getElementById( 'pqm-webhook' ).open = true;
		answer( { success: true, data: { status_html: OUTDATED } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		expect( bodyEl().innerHTML ).not.toBe( SET_UP );
		expect( document.getElementById( 'pqm-webhook' ).open ).toBe( true );
		expect( document.getElementById( 'pqm-by-hand' ).open ).toBe( false );
	} );

	test( 'focus while the owner types in the body (the URL field focused) reads nothing: what they typed stays', () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: OUTDATED } } );
		$( '.pqm-webhook-form input' ).focus();
		window.dispatchEvent( new window.Event( 'focus' ) );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	test( 'the dialog closes (postquality:setup-closed): the body re-read, the named element opened and focused', async () => {
		renderPage( NOT_SET_UP );
		boot();
		answer( { success: true, data: { status_html: MANUAL } } );
		document.dispatchEvent(
			new window.CustomEvent( 'postquality:setup-closed', {
				detail: { focus: 'pqm-by-hand' },
			} )
		);
		await flush();
		expect( sentAjax().action ).toBe( 'postquality_workflow_status' );
		expect( bodyEl().innerHTML ).toBe( MANUAL );
		expect( document.activeElement ).toBe( $( '#pqm-by-hand summary' ) );
	} );

	test( 'the island asks for a refresh (postquality:workflow-refresh): the body re-read', async () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: MANAGED_OUTDATED } } );
		document.dispatchEvent(
			new window.CustomEvent( 'postquality:workflow-refresh' )
		);
		await flush();
		expect( bodyEl().innerHTML ).toBe( MANAGED_OUTDATED );
	} );

	test.each( [
		[ 'network failure', [ null, true ] ],
		[ 'expired nonce (admin-ajax answers -1)', [ -1 ] ],
		[ 'no capability (403 error)', [ { success: false, data: 'Nope.' } ] ],
	] )(
		'a failed read (%s) leaves the body, shows nothing',
		async ( _, args ) => {
			renderPage();
			boot();
			answer( ...args );
			window.dispatchEvent( new window.Event( 'focus' ) );
			await flush();
			expect( bodyEl().innerHTML ).toBe( SET_UP );
			expect( outcome() ).toBeNull();
			expect( heard ).toBe( 0 );
		}
	);

	test( 'a page without the body (Settings) does not listen for focus', () => {
		document.body.innerHTML = '<div class="wrap pqm-wrap"></div>';
		boot();
		answer( { success: true, data: { status_html: SET_UP } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );

describe( 'Re-import the workflow (a hand-made setup needing attention)', () => {
	beforeEach( () => {
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
	} );

	afterEach( () => {
		delete window.HTMLElement.prototype.scrollIntoView;
	} );

	test( 'a click opens Set up by hand and focuses its summary, without the jump', () => {
		renderPage( OUTDATED );
		boot();
		const click = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		$( '.pqm-reimport' ).dispatchEvent( click );
		expect( click.defaultPrevented ).toBe( true );
		const details = document.getElementById( 'pqm-by-hand' );
		expect( details.open ).toBe( true );
		expect(
			window.HTMLElement.prototype.scrollIntoView
		).toHaveBeenCalledTimes( 1 );
		expect( document.activeElement ).toBe(
			details.querySelector( 'summary' )
		);
	} );

	test( 'the link of a swapped-in body works the same', async () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: OUTDATED } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		$( '.pqm-reimport' ).click();
		expect( document.getElementById( 'pqm-by-hand' ).open ).toBe( true );
	} );

	test( 'other clicks on the page move no focus', () => {
		renderPage( OUTDATED );
		boot();
		$( '.pqm-workflow-sentence' ).click();
		expect( document.getElementById( 'pqm-by-hand' ).open ).toBe( false );
		expect(
			window.HTMLElement.prototype.scrollIntoView
		).not.toHaveBeenCalled();
	} );
} );
