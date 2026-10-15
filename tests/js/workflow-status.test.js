/**
 * Workflow version handshake (v5): the n8n Workflow page's status block in
 * pqm-admin.js, run in jsdom. The ping swaps the block in place and warns
 * when an older workflow answered (D2); a regained tab focus re-reads the
 * block from WordPress (D1); the update notice's Re-import the workflow
 * button moves focus to the Workflow File heading, also after a swap. The
 * fixture mirrors render_workflow()'s markup; render-matrix-harness.php
 * locks the PHP side (status_html, outdated, the button).
 */

const SCRIPT = '../../post-quality-manager/admin/js/pqm-admin.js';

const REIMPORT =
	'<p><a class="button button-primary pqm-reimport" href="#pqm-workflow-file">Re-import the workflow</a></p>';
const OLD_BLOCK =
	'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p>' +
	REIMPORT +
	'</div><p class="description">Template version: 5 · Your n8n workflow: version 4, reported by a command.</p></div>';
const NEW_BLOCK =
	'<div class="pqm-workflow-status"><p class="description">Template version: 5 · Your n8n workflow: version 5, checked just now by a test ping.</p></div>';
const OLDER_BLOCK =
	'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p>' +
	REIMPORT +
	'</div><p class="description">Template version: 5 · Your n8n workflow: an older version that does not report its number, checked just now by a test ping.</p></div>';
const SERVED_BLOCK =
	'<div class="pqm-workflow-status"><p class="description">Template version: 5 · Your n8n workflow: not checked yet — send a test ping once it is imported and active.</p></div>';

/** The n8n Workflow page: status block, then the Connection Test. */
function renderPage() {
	document.body.innerHTML = `
		<div class="wrap pqm-wrap">
			<div id="pqm-setup-root"></div>
			${ OLD_BLOCK }
			<h2 id="pqm-workflow-file" tabindex="-1">Workflow File</h2>
			<h2>Connection Test</h2>
			<p>
				<button type="button" class="button pqm-test-connection">Send test ping</button>
				<span class="pqm-test-result" aria-live="polite"></span>
			</p>
		</div>`;
}

const windowListeners = [];
const documentListeners = [];

/** Load pqm-admin.js fresh and run its DOMContentLoaded boot once. */
function boot() {
	window.pqmAdmin = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		pageNonce: 'nonce-123',
		i18n: { testing: 'Testing…', networkError: 'Network error.' },
	};
	const listen = jest.spyOn( document, 'addEventListener' );
	const listenWindow = jest.spyOn( window, 'addEventListener' );
	jest.isolateModules( () => require( SCRIPT ) );
	const [ , init ] = listen.mock.calls.find(
		( [ type ] ) => type === 'DOMContentLoaded'
	);
	listen.mockClear();
	init();
	// The boot's own document listeners (the re-import click), removed after each test.
	documentListeners.push( ...listen.mock.calls );
	listen.mockRestore();
	windowListeners.push( ...listenWindow.mock.calls );
	listenWindow.mockRestore();
}

/**
 * Answer every fetch with this admin-ajax JSON body (or reject).
 *
 * @param {*}       body   Parsed JSON the server answers.
 * @param {boolean} reject The request fails (network).
 */
function answer( body, reject = false ) {
	global.fetch = jest.fn( () =>
		reject
			? Promise.reject( new Error( 'offline' ) )
			: Promise.resolve( { json: () => Promise.resolve( body ) } )
	);
}

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
const status = () => document.querySelectorAll( '.pqm-workflow-status' );
const result = () => document.querySelector( '.pqm-test-result' );
const sent = ( call = 0 ) =>
	Object.fromEntries( global.fetch.mock.calls[ call ][ 1 ].body.entries() );

afterEach( () => {
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

describe( 'Test ping on the n8n Workflow page', () => {
	test( 'a v5 reply swaps the status block in place and shows a success', async () => {
		renderPage();
		boot();
		answer( {
			success: true,
			data: {
				message: 'Ping OK — up to date.',
				outdated: false,
				status_html: NEW_BLOCK,
			},
		} );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
		expect( sent() ).toMatchObject( {
			action: 'pqm_test_connection',
			_wpnonce: 'nonce-123',
		} );
		expect( status() ).toHaveLength( 1 );
		expect( status()[ 0 ].outerHTML ).toBe( NEW_BLOCK );
		expect( document.body.innerHTML ).not.toContain(
			'Workflow update available.'
		);
		expect( result().textContent ).toBe( 'Ping OK — up to date.' );
		expect( result().className ).toBe( 'pqm-test-result is-success' );
	} );

	test( 'an older workflow: the block shows the notice, the result is a warning, never a success', async () => {
		renderPage();
		boot();
		answer( {
			success: true,
			data: {
				message: 'Ping OK — older version.',
				outdated: true,
				status_html: OLDER_BLOCK,
			},
		} );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
		expect( status()[ 0 ].outerHTML ).toBe( OLDER_BLOCK );
		expect( result().classList ).toContain( 'is-warning' );
		expect( result().classList ).not.toContain( 'is-success' );
		expect( result().classList ).not.toContain( 'is-error' );
	} );

	test( 'an error string leaves the block as it is', async () => {
		renderPage();
		boot();
		answer( { success: false, data: 'n8n responded with 404.' } );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
		expect( status()[ 0 ].outerHTML ).toBe( OLD_BLOCK );
		expect( result().textContent ).toBe( 'n8n responded with 404.' );
		expect( result().className ).toBe( 'pqm-test-result is-error' );
	} );

	test( 'a success without status_html (unsaved URL) leaves the block as it is', async () => {
		renderPage();
		boot();
		answer( {
			success: true,
			data: { message: 'Ping OK — up to date.', outdated: false },
		} );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
		expect( status()[ 0 ].outerHTML ).toBe( OLD_BLOCK );
		expect( result().classList ).toContain( 'is-success' );
	} );
} );

describe( 'Status refresh when the tab regains focus', () => {
	test( 'focus re-reads the block from WordPress and swaps it', async () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: SERVED_BLOCK } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( sent() ).toEqual( {
			action: 'pqm_workflow_status',
			_wpnonce: 'nonce-123',
		} );
		expect( status() ).toHaveLength( 1 );
		expect( status()[ 0 ].outerHTML ).toBe( SERVED_BLOCK );
	} );

	test( 'a second focus swaps the block the first one put in', async () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: SERVED_BLOCK } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		answer( { success: true, data: { status_html: NEW_BLOCK } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		expect( status() ).toHaveLength( 1 );
		expect( status()[ 0 ].outerHTML ).toBe( NEW_BLOCK );
	} );

	test.each( [
		[ 'network failure', [ null, true ] ],
		[ 'expired nonce (admin-ajax answers -1)', [ -1 ] ],
		[ 'no capability (403 error)', [ { success: false, data: 'Nope.' } ] ],
	] )(
		'a failed read (%s) leaves the block, shows nothing',
		async ( _, args ) => {
			renderPage();
			boot();
			answer( ...args );
			window.dispatchEvent( new window.Event( 'focus' ) );
			await flush();
			expect( status()[ 0 ].outerHTML ).toBe( OLD_BLOCK );
			expect( result().textContent ).toBe( '' );
		}
	);

	test( 'a page without the block (Settings) does not listen for focus', () => {
		document.body.innerHTML = '<div class="wrap pqm-wrap"></div>';
		boot();
		answer( { success: true, data: { status_html: NEW_BLOCK } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );

describe( 'Re-import the workflow, in the update notice', () => {
	const heading = () => document.getElementById( 'pqm-workflow-file' );
	const reimport = () => document.querySelector( '.pqm-reimport' );

	beforeEach( () => {
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
	} );

	afterEach( () => {
		delete window.HTMLElement.prototype.scrollIntoView;
	} );

	test( 'a click brings the Workflow File section into view and focuses its heading, without the jump', () => {
		renderPage();
		boot();
		const click = new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} );
		reimport().dispatchEvent( click );
		expect( click.defaultPrevented ).toBe( true );
		expect(
			window.HTMLElement.prototype.scrollIntoView
		).toHaveBeenCalledTimes( 1 );
		expect( document.activeElement ).toBe( heading() );
	} );

	test( 'the button of a swapped-in block works the same (a test found an older workflow)', async () => {
		renderPage();
		boot();
		answer( {
			success: true,
			data: {
				message: 'Ping OK — older version.',
				outdated: true,
				status_html: OLDER_BLOCK,
			},
		} );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
		expect( status()[ 0 ].outerHTML ).toBe( OLDER_BLOCK );
		reimport().click();
		expect( document.activeElement ).toBe( heading() );
	} );

	test( 'the button of a block swapped in on window focus works the same', async () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: OLDER_BLOCK } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		reimport().click();
		expect( document.activeElement ).toBe( heading() );
	} );

	test( 'other clicks on the page move no focus', () => {
		renderPage();
		boot();
		document.querySelector( '.pqm-workflow-status p' ).click();
		expect( document.activeElement ).not.toBe( heading() );
		expect(
			window.HTMLElement.prototype.scrollIntoView
		).not.toHaveBeenCalled();
	} );
} );

describe( 'Test again, in the mis-wiring notice (n8n provisioning, entry 7)', () => {
	const MISWIRED_BLOCK =
		'<div class="pqm-workflow-status"><div class="notice notice-error inline pqm-inline-notice"><p>In n8n, the webhook credential “Header Auth account” is set on Claude - Review Agent instead of your Anthropic credential: pick the Anthropic one there, publish the workflow, then test again.</p><p><button type="button" class="button button-primary pqm-test-connection">Test again</button><span class="pqm-test-result" aria-live="polite"></span></p></div><p class="description">Template version: 5 · Your n8n workflow: version 5, checked just now by a test ping.</p></div>';
	const checkAgain = () =>
		document.querySelector( '.pqm-workflow-status .pqm-test-connection' );
	// Two result lines now: Test again's, in the block, and the Connection Test's.
	const againResult = () =>
		document.querySelector( '.pqm-workflow-status .pqm-test-result' );
	const testResult = () =>
		document.querySelector( '.pqm-wrap > p > .pqm-test-result' );

	/** The page after a test found the mis-wiring: the block swapped in. */
	async function miswiredPage() {
		renderPage();
		boot();
		answer( {
			success: true,
			data: {
				message: 'In n8n, the webhook credential …',
				outdated: true,
				status_html: MISWIRED_BLOCK,
			},
		} );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
	}

	test( 'a test that finds it: the block shows the sentence and Test again, the result line a warning', async () => {
		await miswiredPage();
		expect( status()[ 0 ].outerHTML ).toBe( MISWIRED_BLOCK );
		expect( testResult().classList ).toContain( 'is-warning' );
	} );

	test( 'the Test again button inside the swapped block pings, swaps again, keeps the focus on the new block and writes nothing to a detached node', async () => {
		await miswiredPage();
		const resultBefore = testResult().outerHTML;
		const button = checkAgain();
		button.focus();
		answer( {
			success: true,
			data: {
				message: 'Ping OK — up to date.',
				outdated: false,
				status_html: NEW_BLOCK,
			},
		} );
		button.click();
		expect( button.disabled ).toBe( true );
		await flush();
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
		expect( sent() ).toMatchObject( {
			action: 'pqm_test_connection',
			webhook_url: '',
			_wpnonce: 'nonce-123',
		} );
		expect( status() ).toHaveLength( 1 );
		expect( status()[ 0 ].outerHTML ).toBe(
			NEW_BLOCK.replace(
				'class="pqm-workflow-status"',
				'class="pqm-workflow-status" tabindex="-1"'
			)
		);
		// The focused button went with the old block: focus lands on the new one.
		expect( document.activeElement ).toBe( status()[ 0 ] );
		// Swapped out with its block: left as it was once the ping went out.
		expect( button.isConnected ).toBe( false );
		expect( button.disabled ).toBe( true );
		// The Connection Test's result line is not Test again's.
		expect( testResult().outerHTML ).toBe( resultBefore );
	} );

	test.each( [
		[
			'an error reply',
			[ { success: false, data: 'n8n responded with 404.' } ],
			'n8n responded with 404.',
		],
		[ 'a network failure', [ null, true ], 'Network error.' ],
	] )(
		'a Test again that fails (%s): the block stays, the message shows beside the button, the button enabled again',
		async ( _, args, message ) => {
			await miswiredPage();
			const resultBefore = testResult().outerHTML;
			answer( ...args );
			checkAgain().click();
			await flush();
			expect( status() ).toHaveLength( 1 );
			expect( againResult().textContent ).toBe( message );
			expect( againResult().className ).toBe(
				'pqm-test-result is-error'
			);
			expect( checkAgain().disabled ).toBe( false );
			expect( testResult().outerHTML ).toBe( resultBefore );
		}
	);
} );

describe( 'Each swap of the block tells the islands (pqm:workflow-status)', () => {
	let heard;
	const listener = () => heard++;

	beforeEach( () => {
		heard = 0;
		document.addEventListener( 'pqm:workflow-status', listener );
	} );

	afterEach( () => {
		document.removeEventListener( 'pqm:workflow-status', listener );
	} );

	test( 'a test ping that swaps the block dispatches it once, after the swap', async () => {
		renderPage();
		boot();
		let seen = null;
		document.addEventListener(
			'pqm:workflow-status',
			() => ( seen = document.querySelector( '.pqm-reimport' ) ),
			{ once: true }
		);
		answer( {
			success: true,
			data: {
				message: 'Ping OK — up to date.',
				outdated: false,
				status_html: NEW_BLOCK,
			},
		} );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
		expect( heard ).toBe( 1 );
		expect( seen ).toBeNull(); // the new block, without the button
	} );

	test( 'a window-focus refresh that swaps the block dispatches it once', async () => {
		renderPage();
		boot();
		answer( { success: true, data: { status_html: SERVED_BLOCK } } );
		window.dispatchEvent( new window.Event( 'focus' ) );
		await flush();
		expect( heard ).toBe( 1 );
	} );

	test.each( [
		[
			'an error string',
			{ success: false, data: 'n8n responded with 404.' },
		],
		[
			'a success without status_html',
			{ success: true, data: { message: 'Ping OK.', outdated: false } },
		],
	] )( 'no swap (%s), no event', async ( _, body ) => {
		renderPage();
		boot();
		answer( body );
		document.querySelector( '.pqm-test-connection' ).click();
		await flush();
		expect( heard ).toBe( 0 );
	} );
} );
