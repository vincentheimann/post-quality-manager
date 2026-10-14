/**
 * The Workflow File section's Copy rows (n8n provisioning, entry 2): one
 * binding in pqm-admin.js over every .pqm-copy-row, the import URL row
 * included, run in jsdom. The fixture mirrors PQM_Admin::render_copy_value();
 * render-matrix-harness.php locks the PHP side (rows, labels, aria-live).
 */

const SCRIPT = '../../post-quality-manager/admin/js/pqm-admin.js';

const I18N = {
	copied: 'Copied! Paste it in n8n via "Import from URL".',
	copiedValue: 'Copied.',
	copyFailed:
		'Could not copy automatically. The value is selected — press Ctrl+C.',
};

/**
 * One Copy row, as render_copy_value() prints it.
 *
 * @param {string} label Visible label and aria-label.
 * @param {string} value The value to copy.
 * @return {string} Markup.
 */
function row( label, value ) {
	return `
		<div class="pqm-copy-row">
			<span class="pqm-copy-label">${ label }</span>
			<span class="pqm-copy-controls">
				<input type="text" class="regular-text code pqm-copy-field" value="${ value }" readonly aria-label="${ label }" />
				<button type="button" class="button pqm-copy-button" aria-label="Copy ${ label }">Copy</button>
			</span>
			<p class="pqm-copy-feedback" aria-live="polite"></p>
		</div>`;
}

function renderSection() {
	document.body.innerHTML = `
		<div class="wrap pqm-wrap">
			<h2>Workflow File</h2>
			<ol class="pqm-workflow-steps">
				<li><div class="pqm-import-url">${ row(
					'Workflow import URL',
					'https://example.com/wp-json/post-quality/v1/workflow?token=tok'
				) }</div></li>
				<li>${ row( 'Name', 'X-PQM-Token' ) }${ row( 'Value', 'tok-123' ) }</li>
			</ol>
		</div>`;
}

/** Load pqm-admin.js fresh and run its DOMContentLoaded boot once. */
function boot() {
	window.pqmAdmin = { ajaxUrl: '/wp-admin/admin-ajax.php', i18n: I18N };
	const listen = jest.spyOn( document, 'addEventListener' );
	jest.isolateModules( () => require( SCRIPT ) );
	const [ , init ] = listen.mock.calls.find(
		( [ type ] ) => type === 'DOMContentLoaded'
	);
	listen.mockRestore();
	init();
}

/**
 * The browser's clipboard: granted, refused, or absent (not a secure context).
 *
 * @param {'granted'|'refused'|'absent'} mode How writeText behaves.
 */
function clipboard( mode ) {
	Object.defineProperty( window, 'isSecureContext', {
		value: mode !== 'absent',
		configurable: true,
	} );
	Object.defineProperty( window.navigator, 'clipboard', {
		value:
			mode === 'absent'
				? undefined
				: {
						writeText: jest.fn( () =>
							mode === 'granted'
								? Promise.resolve()
								: Promise.reject( new Error( 'denied' ) )
						),
				  },
		configurable: true,
	} );
}

const flush = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
const rows = () => Array.from( document.querySelectorAll( '.pqm-copy-row' ) );
const feedback = ( r ) => r.querySelector( '.pqm-copy-feedback' );
const click = ( r ) => r.querySelector( '.pqm-copy-button' ).click();

beforeEach( () => {
	document.execCommand = jest.fn( () => false );
} );

afterEach( () => {
	delete document.execCommand;
	jest.useRealTimers();
} );

describe( 'Copy rows in the Workflow File section', () => {
	test( 'every row copies its own value and says so in its own aria-live line', async () => {
		renderSection();
		clipboard( 'granted' );
		boot();
		const [ , header, token ] = rows();
		click( token );
		await flush();
		expect( window.navigator.clipboard.writeText ).toHaveBeenCalledWith(
			'tok-123'
		);
		expect( feedback( token ).getAttribute( 'aria-live' ) ).toBe(
			'polite'
		);
		expect( feedback( token ).textContent ).toBe( 'Copied.' );
		expect( feedback( token ).className ).toBe(
			'pqm-copy-feedback is-success'
		);
		expect( feedback( header ).textContent ).toBe( '' );

		click( header );
		await flush();
		expect( window.navigator.clipboard.writeText ).toHaveBeenLastCalledWith(
			'X-PQM-Token'
		);
		expect( feedback( header ).textContent ).toBe( 'Copied.' );
	} );

	test( 'the import URL row keeps its own line: where to paste it', async () => {
		renderSection();
		clipboard( 'granted' );
		boot();
		const [ importRow ] = rows();
		click( importRow );
		await flush();
		expect( window.navigator.clipboard.writeText ).toHaveBeenCalledWith(
			'https://example.com/wp-json/post-quality/v1/workflow?token=tok'
		);
		expect( feedback( importRow ).textContent ).toBe( I18N.copied );
	} );

	test( 'clipboard refused: the value is selected and the line says Ctrl+C', async () => {
		renderSection();
		clipboard( 'refused' );
		boot();
		const token = rows()[ 2 ];
		const field = token.querySelector( '.pqm-copy-field' );
		const select = jest.spyOn( field, 'select' );
		click( token );
		await flush();
		expect( select ).toHaveBeenCalled();
		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( feedback( token ).textContent ).toBe( I18N.copyFailed );
		expect( feedback( token ).className ).toBe(
			'pqm-copy-feedback is-error'
		);
	} );

	test( 'no clipboard API (not a secure context): the selection fallback copies', async () => {
		renderSection();
		clipboard( 'absent' );
		document.execCommand = jest.fn( () => true );
		boot();
		const header = rows()[ 1 ];
		click( header );
		await flush();
		expect( document.execCommand ).toHaveBeenCalledWith( 'copy' );
		expect( feedback( header ).textContent ).toBe( 'Copied.' );
	} );

	test( 'the line clears after a few seconds', async () => {
		renderSection();
		clipboard( 'absent' );
		document.execCommand = jest.fn( () => true );
		boot();
		jest.useFakeTimers();
		const header = rows()[ 1 ];
		click( header );
		expect( feedback( header ).textContent ).toBe( 'Copied.' );
		jest.advanceTimersByTime( 4000 );
		expect( feedback( header ).textContent ).toBe( '' );
	} );

	test( 'focusing a value selects it', () => {
		renderSection();
		clipboard( 'granted' );
		boot();
		const field = rows()[ 2 ].querySelector( '.pqm-copy-field' );
		const select = jest.spyOn( field, 'select' );
		field.dispatchEvent( new window.FocusEvent( 'focus' ) );
		expect( select ).toHaveBeenCalled();
	} );
} );
