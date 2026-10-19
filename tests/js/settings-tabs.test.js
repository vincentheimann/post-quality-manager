/**
 * Story 27: the Settings tabs in pqm-admin.js, run in jsdom. The fixture
 * mirrors render_settings()'s tab markup; render-matrix-harness.php locks
 * that markup on the PHP side.
 */

const SCRIPT = '../../post-quality-manager/admin/js/pqm-admin.js';

/**
 * The Settings page for these tab slugs: tab bar, the one form, a panel each.
 *
 * @param {string[]} slugs Tab slugs in render order; the first starts active.
 */
function renderPage( slugs ) {
	document.body.className = 'js';
	document.body.innerHTML = `
		<div class="wrap pqm-wrap">
			<nav class="nav-tab-wrapper pqm-tabs">
				${ slugs
					.map(
						( slug, i ) =>
							`<a href="#tab-${ slug }" id="pqm-tab-${ slug }" class="nav-tab${
								i === 0 ? ' nav-tab-active' : ''
							}">${ slug }</a>`
					)
					.join( '' ) }
			</nav>
			<form method="post" action="options.php">
				<input type="hidden" name="_wp_http_referer" value="/wp-admin/admin.php?page=pqm-settings" />
				${ slugs
					.map(
						( slug, i ) =>
							`<div class="pqm-tab-panel${
								i === 0 ? ' is-active' : ''
							}" id="pqm-panel-${ slug }">${
								PANEL_BODY[ slug ]
							}</div>`
					)
					.join( '' ) }
			</form>
		</div>`;
}

const PANEL_BODY = {
	connection:
		'<h2>License</h2><input type="url" id="pqm_notification_url" />',
	ai: '<h2>AI Model</h2><h2 id="pqm-context-settings">AI Prompts &amp; Context</h2>',
	automation: '<h2 id="pqm-cron-settings">Automated Reviews (Cron)</h2>',
	usage: '<h2 id="pqm-quota-settings">Monthly Quota</h2>',
};
const ALL = [ 'connection', 'ai', 'automation', 'usage' ];

/**
 * Load pqm-admin.js fresh and run its DOMContentLoaded boot once.
 *
 * @param {string} hash URL hash the page opens with, e.g. '#tab-ai'.
 */
function boot( hash = '' ) {
	window.history.replaceState(
		null,
		'',
		'/wp-admin/admin.php?page=pqm-settings' + hash
	);
	window.pqmAdmin = {};
	const listen = jest.spyOn( document, 'addEventListener' );
	jest.isolateModules( () => require( SCRIPT ) );
	const [ , init ] = listen.mock.calls.find(
		( [ type ] ) => type === 'DOMContentLoaded'
	);
	listen.mockRestore();
	init();
}

const tab = ( slug ) => document.getElementById( 'pqm-tab-' + slug );
const panel = ( slug ) => document.getElementById( 'pqm-panel-' + slug );
const referer = () =>
	document.querySelector( 'input[name="_wp_http_referer"]' ).value;
const activeSlugs = () =>
	ALL.filter( ( slug ) => panel( slug )?.classList.contains( 'is-active' ) );
const press = ( slug, key, extra = {} ) =>
	tab( slug ).dispatchEvent(
		new window.KeyboardEvent( 'keydown', {
			key,
			bubbles: true,
			cancelable: true,
			...extra,
		} )
	);

beforeEach( () => {
	window.Element.prototype.scrollIntoView = jest.fn();
	document.documentElement.dir = '';
	document.documentElement.className = '';
} );

describe( 'Settings tabs', () => {
	test( 'boot adds the ARIA tabs pattern and keeps the first tab', () => {
		renderPage( ALL );
		boot();
		expect(
			document.querySelector( '.pqm-tabs' ).getAttribute( 'role' )
		).toBe( 'tablist' );
		ALL.forEach( ( slug, i ) => {
			expect( tab( slug ).getAttribute( 'role' ) ).toBe( 'tab' );
			expect( tab( slug ).getAttribute( 'aria-controls' ) ).toBe(
				'pqm-panel-' + slug
			);
			expect( tab( slug ).getAttribute( 'aria-selected' ) ).toBe(
				i === 0 ? 'true' : 'false'
			);
			expect( tab( slug ).tabIndex ).toBe( i === 0 ? 0 : -1 );
			expect( panel( slug ).getAttribute( 'role' ) ).toBe( 'tabpanel' );
			expect( panel( slug ).getAttribute( 'aria-labelledby' ) ).toBe(
				'pqm-tab-' + slug
			);
		} );
		expect( activeSlugs() ).toEqual( [ 'connection' ] );
		expect( window.location.hash ).toBe( '' );
	} );

	test( 'a click opens its panel, records the hash and the save referer', () => {
		renderPage( ALL );
		boot();
		tab( 'automation' ).click();
		expect( activeSlugs() ).toEqual( [ 'automation' ] );
		expect( tab( 'automation' ).getAttribute( 'aria-selected' ) ).toBe(
			'true'
		);
		expect( tab( 'automation' ).classList ).toContain( 'nav-tab-active' );
		expect( tab( 'connection' ).classList ).not.toContain(
			'nav-tab-active'
		);
		expect( window.location.hash ).toBe( '#tab-automation' );
		expect( referer() ).toBe(
			'/wp-admin/admin.php?page=pqm-settings#tab-automation'
		);
		tab( 'usage' ).click(); // the referer keeps one hash, never two
		expect( referer() ).toBe(
			'/wp-admin/admin.php?page=pqm-settings#tab-usage'
		);
	} );

	test( 'arrow keys move with wrap-around, Home and End jump, focus follows', () => {
		renderPage( ALL );
		boot();
		press( 'connection', 'ArrowRight' );
		expect( activeSlugs() ).toEqual( [ 'ai' ] );
		expect( document.activeElement ).toBe( tab( 'ai' ) );
		press( 'ai', 'ArrowLeft' );
		press( 'connection', 'ArrowLeft' );
		expect( activeSlugs() ).toEqual( [ 'usage' ] );
		press( 'usage', 'ArrowRight' );
		expect( activeSlugs() ).toEqual( [ 'connection' ] );
		press( 'connection', 'End' );
		expect( activeSlugs() ).toEqual( [ 'usage' ] );
		press( 'usage', 'Home' );
		expect( activeSlugs() ).toEqual( [ 'connection' ] );
	} );

	test( 'on a right-to-left site the arrows follow the mirrored layout', () => {
		document.documentElement.dir = 'rtl';
		renderPage( ALL );
		boot();
		press( 'connection', 'ArrowLeft' );
		expect( activeSlugs() ).toEqual( [ 'ai' ] );
		press( 'ai', 'ArrowRight' );
		expect( activeSlugs() ).toEqual( [ 'connection' ] );
	} );

	test( 'modified arrows stay with the browser (Alt+Left is Back)', () => {
		renderPage( ALL );
		boot();
		[ 'altKey', 'ctrlKey', 'metaKey', 'shiftKey' ].forEach( ( mod ) => {
			press( 'connection', 'ArrowRight', { [ mod ]: true } );
		} );
		expect( activeSlugs() ).toEqual( [ 'connection' ] );
	} );

	test.each( [
		[ '#tab-ai', 'ai', false ],
		[ '#pqm-cron-settings', 'automation', true ],
		[ '#pqm-quota-settings', 'usage', true ],
		[ '#pqm-context-settings', 'ai', true ],
	] )( 'the hash %s opens the %s tab', ( hash, slug, scrolls ) => {
		renderPage( ALL );
		boot( hash );
		expect( activeSlugs() ).toEqual( [ slug ] );
		expect( window.location.hash ).toBe( '#tab-' + slug );
		expect( referer() ).toContain( '#tab-' + slug );
		expect( window.Element.prototype.scrollIntoView ).toHaveBeenCalledTimes(
			scrolls ? 1 : 0
		);
	} );

	test.each( [
		[ 'an unknown hash', ALL, '#nothing-here' ],
		[
			'a tab this role lacks (Editor)',
			[ 'ai', 'automation' ],
			'#tab-usage',
		],
	] )(
		'%s keeps the first tab and leaves the URL alone',
		( _, slugs, hash ) => {
			renderPage( slugs );
			boot( hash );
			expect( activeSlugs() ).toEqual( [ slugs[ 0 ] ] );
			expect( window.location.hash ).toBe( hash );
			expect( referer() ).not.toContain( '#' );
		}
	);

	test( 'boot clears the inline guard that hid the panels', () => {
		document.documentElement.classList.add( 'pqm-tabs-pending' );
		renderPage( ALL );
		boot( '#tab-usage' );
		expect( document.documentElement.classList ).not.toContain(
			'pqm-tabs-pending'
		);
	} );

	test( 'a field the browser rejects opens its panel', () => {
		renderPage( ALL );
		boot();
		tab( 'ai' ).click();
		document
			.getElementById( 'pqm_notification_url' )
			.dispatchEvent( new Event( 'invalid', { cancelable: true } ) );
		expect( activeSlugs() ).toEqual( [ 'connection' ] );
	} );
} );
