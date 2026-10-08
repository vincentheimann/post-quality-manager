/**
 * Workflow version handshake (v5): the n8n Workflow page's status block in
 * pqm-admin.js, run in jsdom. The ping swaps the block in place and warns
 * when an older workflow answered (D2); a regained tab focus re-reads the
 * block from WordPress (D1). The fixture mirrors render_workflow()'s markup;
 * render-matrix-harness.php locks the PHP side (status_html, outdated).
 */

const SCRIPT = '../../post-quality-manager/admin/js/pqm-admin.js';

const OLD_BLOCK =
	'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p></div><p class="description">Template version: 5 · Your n8n workflow: version 4, reported by a command.</p></div>';
const NEW_BLOCK =
	'<div class="pqm-workflow-status"><p class="description">Template version: 5 · Your n8n workflow: version 5, checked just now by a test ping.</p></div>';
const OLDER_BLOCK =
	'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p></div><p class="description">Template version: 5 · Your n8n workflow: an older version that does not report its number, checked just now by a test ping.</p></div>';
const SERVED_BLOCK =
	'<div class="pqm-workflow-status"><p class="description">Template version: 5 · Your n8n workflow: not checked yet — send a test ping once it is imported and active.</p></div>';

/** The n8n Workflow page: status block, then the Connection Test. */
function renderPage() {
	document.body.innerHTML = `
		<div class="wrap pqm-wrap">
			<div id="pqm-setup-root"></div>
			${ OLD_BLOCK }
			<h2>Workflow File</h2>
			<h2>Connection Test</h2>
			<p>
				<button type="button" class="button pqm-test-connection">Send test ping</button>
				<span class="pqm-test-result" aria-live="polite"></span>
			</p>
		</div>`;
}

const windowListeners = [];

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
	listen.mockRestore();
	init();
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
