/**
 * n8n Instance section (n8n provisioning, entry 1; src/n8n-connection/) run
 * in jsdom, one test per row of plan-n8n-connection-states' matrix: the
 * connection block (connected summary, changing, connecting) and the
 * adoption block under it (offer, proposal, managed line; the token
 * rotation is a PHP button on the page since story 67), each in place, with
 * the call made and where keyboard focus lands. The key field is
 * write-only: never prefilled, emptied after a save; the stored key's last
 * 4 characters (`key_hint`) show only in the status line and as the field's
 * masked placeholder. The api-fetch and components packages are
 * WordPress-provided externals, not installed here: both are mocked.
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- React comes with @wordpress/element (wp-scripts); act is not re-exported there.
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import N8nConnection from '../../src/n8n-connection';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock(
	'@wordpress/components',
	() => ( {
		// A forwardRef, as the real Button: the island focuses it (entry 8).
		Button: require( '@wordpress/element' ).forwardRef(
			( { children, isBusy, isDestructive, variant, ...props }, ref ) => (
				<button ref={ ref } data-variant={ variant } { ...props }>
					{ children }
				</button>
			)
		),
		Notice: ( { status, children } ) => (
			<div className={ 'mock-notice-' + status }>{ children }</div>
		),
		// Story 71: the Start over Modal and its removal checkbox.
		Modal: ( { title, className, children, onRequestClose } ) => (
			<div className={ 'mock-modal ' + ( className || '' ) }>
				<h1>{ title }</h1>
				<button
					type="button"
					className="mock-modal-close"
					onClick={ onRequestClose }
				>
					Close dialog
				</button>
				{ children }
			</div>
		),
		CheckboxControl: ( { label, checked, onChange } ) => (
			<input
				type="checkbox"
				aria-label={ label }
				checked={ checked }
				onChange={ ( e ) => onChange( e.target.checked ) }
			/>
		),
		TextControl: ( {
			label,
			help,
			type,
			placeholder,
			value,
			onChange,
		} ) => (
			<input
				aria-label={ label }
				data-help={ help }
				type={ type }
				placeholder={ placeholder }
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		),
	} ),
	{ virtual: true }
);

const BASE = 'https://n8n.example.com';
const PATH = '/post-quality/v1/n8n/connection';
const CONNECTED = {
	// eslint-disable-next-line camelcase -- REST field name.
	base_url: BASE,
	// eslint-disable-next-line camelcase -- REST field name.
	key_set: true,
	// eslint-disable-next-line camelcase -- REST field name.
	key_hint: 'ab12',
	probe: {
		ok: true,
		cause: '',
		message: 'Connected to n8n.',
		at: 1760000000,
	},
	connected: true,
	setup: null,
	adoptable: false,
	rotatable: false,
};
const NOT_SET = {
	// eslint-disable-next-line camelcase -- REST field name.
	base_url: '',
	// eslint-disable-next-line camelcase -- REST field name.
	key_set: false,
	// eslint-disable-next-line camelcase -- REST field name.
	key_hint: '',
	probe: null,
	connected: false,
	setup: null,
	adoptable: false,
};
// Story 74: the stored key refused (a re-check, or a managed call answering 401).
const REFUSED_MESSAGE =
	'n8n refused this API key. Create a new one in n8n under Settings → n8n API, then try again.';
const REFUSED = {
	...CONNECTED,
	probe: {
		ok: false,
		cause: 'key',
		message: REFUSED_MESSAGE,
		at: 1760000500,
	},
	connected: false,
};

let root;

/**
 * Mount the section with GET /n8n/connection answering `state`.
 *
 * @param {Object}    state    GET reply.
 * @param {Function=} onChange The setup wizard's prop (entry 6).
 */
async function mount( state, onChange ) {
	document.body.innerHTML = '<div id="pqm-n8n-connection-root"></div>';
	apiFetch.mockReset();
	apiFetch.mockResolvedValueOnce( state );
	root = createRoot( document.getElementById( 'pqm-n8n-connection-root' ) );
	await act( async () => {
		root.render( <N8nConnection onChange={ onChange } /> );
	} );
}

const field = ( label ) =>
	document.querySelector( `input[aria-label="${ label }"]` );
const text = () => document.body.textContent;

/**
 * Type into a mocked TextControl (React's own value setter, then input).
 *
 * @param {string} label Field label.
 * @param {string} value Value to type.
 */
async function typeInto( label, value ) {
	const input = field( label );
	const setter = Object.getOwnPropertyDescriptor(
		window.HTMLInputElement.prototype,
		'value'
	).set;
	await act( async () => {
		setter.call( input, value );
		input.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

async function submit() {
	await act( async () => {
		document.querySelector( '.pqm-n8n-connection-form' ).dispatchEvent(
			new window.Event( 'submit', {
				bubbles: true,
				cancelable: true,
			} )
		);
	} );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	act( () => root.unmount() );
} );

const $ = ( selector ) => document.querySelector( selector );
const click = async ( selector ) =>
	act( async () => {
		$( selector ).click();
	} );
const submitButton = () => $( 'button[type="submit"]' );
const primaries = () =>
	document.querySelectorAll( '.pqm-n8n-connection [data-variant="primary"]' );
const summaryLine = () => $( '.pqm-n8n-key-state' ).textContent;

describe( 'n8n Instance section: the connection block', () => {
	test( 'Connected: the summary with Check again, Change, Forget key; no field, no Connect — one GET, no probe', async () => {
		await mount( CONNECTED );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( { path: PATH } );
		expect( $( '.mock-notice-success' ).textContent ).toContain(
			'Connected to n8n.'
		);
		expect( summaryLine() ).toBe(
			'n8n at n8n.example.com · API key: set, ending in ab12'
		);
		expect( $( 'input' ) ).toBeNull();
		expect( submitButton() ).toBeNull();
		// A connected site is not told to connect.
		expect( text() ).not.toContain(
			'Connect your n8n instance with an n8n API key.'
		);
		expect(
			[ ...document.querySelectorAll( 'button' ) ].map(
				( b ) => b.textContent
			)
		).not.toContain( 'Connect' );
		const buttons = [
			...document.querySelectorAll( '.pqm-n8n-summary button' ),
		];
		expect( buttons.map( ( b ) => b.textContent ) ).toEqual( [
			'Check again',
			'Change',
			'Forget key',
		] );
		expect( buttons.map( ( b ) => b.dataset.variant ) ).toEqual( [
			'secondary',
			'secondary',
			'secondary',
		] );
	} );

	test( 'a short stored key (no hint): "set" without an ending; Change shows no placeholder', async () => {
		// eslint-disable-next-line camelcase -- REST field name.
		await mount( { ...CONNECTED, key_hint: '' } );
		expect( summaryLine() ).toBe( 'n8n at n8n.example.com · API key: set' );
		await click( '.pqm-n8n-change' );
		expect( summaryLine() ).toBe( 'API key: set' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '' );
		expect( field( 'n8n API key' ).dataset.help ).toBe(
			'A key is stored. Type a new key to replace it.'
		);
	} );

	test( 'Connecting, nothing stored: no notice, no Check again or Forget, Connect waits for a key', async () => {
		await mount( NOT_SET );
		expect( text() ).toContain(
			'Connect your n8n instance with an n8n API key.'
		);
		expect( text() ).toContain( 'API key: not set' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '' );
		expect( field( 'n8n API key' ).dataset.help ).toContain(
			'Create one in n8n under Settings → n8n API.'
		);
		expect( $( '.mock-notice-success' ) ).toBeNull();
		expect( $( '.pqm-n8n-forget' ) ).toBeNull();
		expect( $( '.pqm-n8n-recheck' ) ).toBeNull();
		expect( $( '.pqm-n8n-change' ) ).toBeNull();
		expect( submitButton().textContent ).toBe( 'Connect' );
		await typeInto( 'n8n address', BASE );
		expect( submitButton().disabled ).toBe( true );
		await typeInto( 'n8n API key', 'k' );
		expect( submitButton().disabled ).toBe( false );
	} );

	test( 'Connecting, a stored key without a passing check: the fields and Connect, Check again and Forget key beside it', async () => {
		await mount( { ...CONNECTED, probe: null, connected: false } );
		expect( $( '.mock-notice-success' ) ).toBeNull();
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '••••••••ab12' );
		expect(
			[
				...document.querySelectorAll(
					'.pqm-n8n-connection-actions button'
				),
			].map( ( b ) => b.textContent )
		).toEqual( [ 'Connect', 'Check again', 'Forget key' ] );
		expect( $( '.pqm-n8n-change' ) ).toBeNull();
	} );

	test( 'Connect: URL and key posted; on success the summary, focus on Change', async () => {
		await mount( NOT_SET );
		await typeInto( 'n8n address', ` ${ BASE }/home/workflows ` );
		await typeInto( 'n8n API key', ' secret-key-0123-wxyz ' );
		// eslint-disable-next-line camelcase -- REST field name.
		apiFetch.mockResolvedValueOnce( { ...CONNECTED, key_hint: 'wxyz' } );
		await submit();
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'POST',
			// eslint-disable-next-line camelcase -- REST field names.
			data: {
				base_url: `${ BASE }/home/workflows`,
				api_key: 'secret-key-0123-wxyz',
			},
		} );
		expect( $( '.mock-notice-success' ).textContent ).toContain(
			'Connected to n8n.'
		);
		expect( summaryLine() ).toBe(
			'n8n at n8n.example.com · API key: set, ending in wxyz'
		);
		expect( $( 'input' ) ).toBeNull();
		expect( document.activeElement ).toBe( $( '.pqm-n8n-change' ) );
	} );

	test( 'a failed Connect: the cause in the connection block, the form stays as typed', async () => {
		await mount( NOT_SET );
		await typeInto( 'n8n address', BASE );
		await typeInto( 'n8n API key', 'bad-key' );
		const cause =
			'n8n refused this API key. Create a new one in n8n under Settings → n8n API, then try again.';
		apiFetch.mockResolvedValueOnce( {
			...NOT_SET,
			probe: { ok: false, cause: 'key', message: cause },
		} );
		await submit();
		expect(
			$( '.pqm-n8n-connection-block .mock-notice-error' ).textContent
		).toBe( cause );
		expect( field( 'n8n API key' ).value ).toBe( 'bad-key' );
		expect( submitButton().textContent ).toBe( 'Connect' );
		expect( text() ).toContain( 'API key: not set' );
	} );

	test( 'Change: the fields over the stored address, key empty with the hint; Save and check and Cancel; focus on the address', async () => {
		await mount( CONNECTED );
		await click( '.pqm-n8n-change' );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( text() ).toContain(
			'Connect your n8n instance with an n8n API key.'
		);
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n API key' ).type ).toBe( 'password' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '••••••••ab12' );
		expect( field( 'n8n API key' ).dataset.help ).toBe(
			'Type a new key to replace it.'
		);
		expect(
			[
				...document.querySelectorAll(
					'.pqm-n8n-connection-actions button'
				),
			].map( ( b ) => b.textContent )
		).toEqual( [ 'Save and check', 'Cancel' ] );
		expect( $( '.pqm-n8n-recheck' ) ).toBeNull();
		expect( $( '.pqm-n8n-forget' ) ).toBeNull();
		// The stored key re-checks the stored address only (canSubmit).
		expect( submitButton().disabled ).toBe( false );
		await typeInto( 'n8n address', 'https://other.example.com' );
		expect( submitButton().disabled ).toBe( true );
		expect( document.activeElement ).toBe( field( 'n8n address' ) );
	} );

	test( 'Cancel the change: the same summary, no REST call, the typed values dropped; focus on Change', async () => {
		await mount( CONNECTED );
		await click( '.pqm-n8n-change' );
		await typeInto( 'n8n address', 'https://other.example.com' );
		await typeInto( 'n8n API key', 'typed-key' );
		await click( '.pqm-n8n-change-cancel' );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( summaryLine() ).toBe(
			'n8n at n8n.example.com · API key: set, ending in ab12'
		);
		expect( $( 'input' ) ).toBeNull();
		expect( document.activeElement ).toBe( $( '.pqm-n8n-change' ) );
		await click( '.pqm-n8n-change' );
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
	} );

	test( 'Save and check with the stored address and no key: a re-check; passes, the new state, focus on Change', async () => {
		await mount( CONNECTED );
		await click( '.pqm-n8n-change' );
		const rechecked = {
			...CONNECTED,
			probe: { ...CONNECTED.probe, at: 1760000999 },
		};
		apiFetch.mockResolvedValueOnce( rechecked );
		await submit();
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'POST',
			// eslint-disable-next-line camelcase -- REST field name.
			data: { base_url: BASE },
		} );
		expect( $( '.mock-notice-success' ).textContent ).toContain(
			new Date( 1760000999 * 1000 ).toLocaleString()
		);
		expect( $( 'input' ) ).toBeNull();
		expect( document.activeElement ).toBe( $( '.pqm-n8n-change' ) );
	} );

	test( 'Save and check with a new address and key: posted; passes, the summary shows the new state', async () => {
		await mount( CONNECTED );
		await click( '.pqm-n8n-change' );
		await typeInto( 'n8n address', 'https://other.example.com' );
		await typeInto( 'n8n API key', 'new-key-wxyz' );
		apiFetch.mockResolvedValueOnce( {
			...CONNECTED,
			// eslint-disable-next-line camelcase -- REST field names.
			base_url: 'https://other.example.com',
			// eslint-disable-next-line camelcase -- REST field name.
			key_hint: 'wxyz',
		} );
		await submit();
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'POST',
			// eslint-disable-next-line camelcase -- REST field names.
			data: {
				base_url: 'https://other.example.com',
				api_key: 'new-key-wxyz',
			},
		} );
		expect( summaryLine() ).toBe(
			'n8n at other.example.com · API key: set, ending in wxyz'
		);
		expect( document.activeElement ).toBe( $( '.pqm-n8n-change' ) );
	} );

	test( 'a failed Save and check: the cause, the form stays, the stored state as it was', async () => {
		await mount( CONNECTED );
		await click( '.pqm-n8n-change' );
		await typeInto( 'n8n address', 'https://other.example.com' );
		await typeInto( 'n8n API key', 'new-key' );
		const cause =
			'n8n refused this API key. Create a new one in n8n under Settings → n8n API, then try again.';
		apiFetch.mockResolvedValueOnce( {
			...CONNECTED,
			probe: { ok: false, cause: 'key', message: cause },
		} );
		await submit();
		expect(
			$( '.pqm-n8n-connection-block .mock-notice-error' ).textContent
		).toBe( cause );
		expect( $( '.mock-notice-success' ).textContent ).toContain(
			'Connected to n8n.'
		);
		expect( submitButton().textContent ).toBe( 'Save and check' );
		expect( field( 'n8n address' ).value ).toBe(
			'https://other.example.com'
		);
		expect( field( 'n8n API key' ).value ).toBe( 'new-key' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '••••••••ab12' );

		// Cancel drops the cause with the typed values.
		await click( '.pqm-n8n-change-cancel' );
		expect( $( '.mock-notice-error' ) ).toBeNull();
		expect( summaryLine() ).toBe(
			'n8n at n8n.example.com · API key: set, ending in ab12'
		);
	} );

	test( 'Check again from the summary posts without a key; the summary stays', async () => {
		await mount( CONNECTED );
		apiFetch.mockResolvedValueOnce( CONNECTED );
		await click( '.pqm-n8n-recheck' );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'POST',
		} );
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( $( 'input' ) ).toBeNull();
	} );

	test( 'Forget key from the summary: DELETE, Connecting with the address kept and "not set"; focus on the address', async () => {
		await mount( CONNECTED );
		// eslint-disable-next-line camelcase -- REST field names.
		apiFetch.mockResolvedValueOnce( {
			base_url: BASE,
			key_set: false,
			key_hint: '',
			probe: null,
			setup: null,
			adoptable: false,
		} );
		await click( '.pqm-n8n-forget' );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'DELETE',
		} );
		expect( summaryLine() ).toBe( 'API key: not set' );
		expect( text() ).not.toContain( 'ab12' );
		expect( $( '.mock-notice-success' ) ).toBeNull();
		expect( $( '.pqm-n8n-forget' ) ).toBeNull();
		expect( $( '.pqm-n8n-summary' ) ).toBeNull();
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '' );
		expect( submitButton().textContent ).toBe( 'Connect' );
		expect( document.activeElement ).toBe( field( 'n8n address' ) );
	} );

	test( 'the setup wizard (onChange): Connecting, then Connected; onChange after the read, a failed probe (story 74: the server may have changed), a passing connect and Forget', async () => {
		const onChange = jest.fn();
		await mount( NOT_SET, onChange );
		expect( onChange ).toHaveBeenLastCalledWith( NOT_SET );
		expect( submitButton().textContent ).toBe( 'Connect' );

		await typeInto( 'n8n address', BASE );
		await typeInto( 'n8n API key', 'bad-key' );
		const failed = {
			...NOT_SET,
			probe: { ok: false, cause: 'key', message: 'Refused.' },
		};
		apiFetch.mockResolvedValueOnce( failed );
		await submit();
		expect( onChange ).toHaveBeenCalledTimes( 2 );
		expect( onChange ).toHaveBeenLastCalledWith( failed );
		expect(
			$( '.pqm-n8n-connection-block .mock-notice-error' ).textContent
		).toBe( 'Refused.' );

		await typeInto( 'n8n API key', 'good-key' );
		apiFetch.mockResolvedValueOnce( CONNECTED );
		await submit();
		expect( onChange ).toHaveBeenCalledTimes( 3 );
		expect( onChange ).toHaveBeenLastCalledWith( CONNECTED );
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( $( 'input' ) ).toBeNull();

		// eslint-disable-next-line camelcase -- REST field names.
		const forgotten = {
			base_url: BASE,
			key_set: false,
			key_hint: '',
			probe: null,
		};
		apiFetch.mockResolvedValueOnce( forgotten );
		await click( '.pqm-n8n-forget' );
		expect( onChange ).toHaveBeenCalledTimes( 4 );
		expect( onChange ).toHaveBeenLastCalledWith( forgotten );
	} );
} );

describe( 'Story 74: the connection state tells the truth', () => {
	const errorNotices = () =>
		document.querySelectorAll(
			'.pqm-n8n-connection-block .mock-notice-error'
		);
	const checked = ( at ) =>
		`Checked ${ new Date( at * 1000 ).toLocaleString() }.`;

	/**
	 * Count pqm:workflow-refresh events while `run` runs.
	 *
	 * @param {Function} run Async steps.
	 * @return {Promise<Array>} Each event's detail.
	 */
	async function refreshesDuring( run ) {
		const details = [];
		const listener = ( e ) => details.push( e.detail );
		document.addEventListener( 'pqm:workflow-refresh', listener );
		await run();
		document.removeEventListener( 'pqm:workflow-refresh', listener );
		return details;
	}

	test( 'a stored refusal: the error Notice "{message} Checked {date}." above the open form; no summary, no success Notice, no adoption offer', async () => {
		await mount( { ...REFUSED, adoptable: false } );
		expect( errorNotices() ).toHaveLength( 1 );
		expect( errorNotices()[ 0 ].textContent ).toBe(
			`${ REFUSED_MESSAGE } ${ checked( 1760000500 ) }`
		);
		const block = $( '.pqm-n8n-connection-block' );
		expect(
			// eslint-disable-next-line no-bitwise -- DOCUMENT_POSITION_FOLLOWING is a bit mask.
			errorNotices()[ 0 ].compareDocumentPosition(
				block.querySelector( '.pqm-n8n-connection-form' )
			) & window.Node.DOCUMENT_POSITION_FOLLOWING
		).toBeTruthy();
		expect( $( '.pqm-n8n-summary' ) ).toBeNull();
		expect( $( '.mock-notice-success' ) ).toBeNull();
		expect( $( '.pqm-n8n-adopt' ) ).toBeNull();
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect(
			[
				...document.querySelectorAll(
					'.pqm-n8n-connection-actions button'
				),
			].map( ( b ) => b.textContent )
		).toEqual( [ 'Connect', 'Check again', 'Forget key' ] );
	} );

	test( 'Check again refused on the page: the reply is stored — the refusal Notice once, the form open, the offer gone; the page re-reads its body once', async () => {
		await mount( { ...CONNECTED, adoptable: true } );
		expect( $( '.pqm-n8n-adopt' ) ).not.toBeNull();
		apiFetch.mockResolvedValueOnce( { ...REFUSED, adoptable: false } );
		const refreshes = await refreshesDuring( () =>
			click( '.pqm-n8n-recheck' )
		);
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'POST',
		} );
		expect( errorNotices() ).toHaveLength( 1 );
		expect( errorNotices()[ 0 ].textContent ).toBe(
			`${ REFUSED_MESSAGE } ${ checked( 1760000500 ) }`
		);
		expect( $( '.pqm-n8n-summary' ) ).toBeNull();
		expect( $( '.pqm-n8n-connection-form' ) ).not.toBeNull();
		expect( $( '.pqm-n8n-adopt' ) ).toBeNull();
		expect( refreshes ).toEqual( [ { focus: 'pqm-n8n-connection' } ] );
	} );

	test( 'a failed re-check that keeps the connection (n8n answered 500): the summary and the passing probe stay, the cause in its Notice, no refresh', async () => {
		await mount( CONNECTED );
		const cause = 'n8n answered HTTP 500. Try again in a moment.';
		apiFetch.mockResolvedValueOnce( {
			...CONNECTED,
			probe: { ok: false, cause: 'unexpected', message: cause, at: 1 },
		} );
		const refreshes = await refreshesDuring( () =>
			click( '.pqm-n8n-recheck' )
		);
		expect( $( '.mock-notice-success' ).textContent ).toBe(
			`Connected to n8n. ${ checked( 1760000000 ) }`
		);
		expect( errorNotices()[ 0 ].textContent ).toBe( cause );
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( refreshes ).toEqual( [] );
	} );

	test( 'in the setup wizard (onChange): a refused Check again reaches onChange, the page is not asked to refresh', async () => {
		const onChange = jest.fn();
		await mount( CONNECTED, onChange );
		apiFetch.mockResolvedValueOnce( REFUSED );
		const refreshes = await refreshesDuring( () =>
			click( '.pqm-n8n-recheck' )
		);
		expect( onChange ).toHaveBeenLastCalledWith( REFUSED );
		expect( refreshes ).toEqual( [] );
		expect( errorNotices() ).toHaveLength( 1 );
	} );

	test( 'a new key passes after a refusal: the summary back, the page re-reads its body (Update, Rotate and the offer return)', async () => {
		await mount( REFUSED );
		await typeInto( 'n8n API key', 'new-key-wxyz' );
		// eslint-disable-next-line camelcase -- REST field name.
		apiFetch.mockResolvedValueOnce( { ...CONNECTED, key_hint: 'wxyz' } );
		const refreshes = await refreshesDuring( () => submit() );
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( errorNotices() ).toHaveLength( 0 );
		expect( refreshes ).toEqual( [ { focus: 'pqm-n8n-connection' } ] );
	} );
} );

describe( 'The variant prop (story 67): secondary on the page, primary in the setup dialog', () => {
	const PROPOSAL = {
		workflow: { id: 'wf-hand', name: 'My blog reviews', path: 'x' },
		credentials: {
			webhook: { id: 'c-h1', name: 'Header Auth account' },
			anthropic: { id: 'c-k1', name: 'my claude key' },
		},
	};
	const submitVariant = () => submitButton().dataset.variant;

	/**
	 * Mount the section with this variant.
	 *
	 * @param {string=} variant The prop; none = the default.
	 * @param {Object=} state   GET reply.
	 */
	async function mountWith( variant, state = NOT_SET ) {
		document.body.innerHTML = '<div id="pqm-n8n-connection-root"></div>';
		apiFetch.mockReset();
		apiFetch.mockResolvedValueOnce( state );
		root = createRoot(
			document.getElementById( 'pqm-n8n-connection-root' )
		);
		await act( async () => {
			root.render( <N8nConnection variant={ variant } /> );
		} );
	}

	test( 'the default (the setup dialog): Connect primary', async () => {
		await mountWith();
		expect( submitVariant() ).toBe( 'primary' );
		expect( primaries() ).toHaveLength( 1 );
	} );

	test( 'on the page (secondary): no primary anywhere — Connect, Save and check and Manage this workflow all secondary', async () => {
		await mountWith( 'secondary' );
		expect( submitVariant() ).toBe( 'secondary' );
		expect( primaries() ).toHaveLength( 0 );

		await mountWith( 'secondary', { ...CONNECTED, adoptable: true } );
		await click( '.pqm-n8n-change' );
		expect( submitVariant() ).toBe( 'secondary' );
		await click( '.pqm-n8n-change-cancel' );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		expect( $( '.pqm-n8n-adopt-confirm' ).dataset.variant ).toBe(
			'secondary'
		);
		expect( primaries() ).toHaveLength( 0 );
	} );

	test( 'in the dialog, connected: none in the summary; Save and check while changing; Manage this workflow only while no form is open', async () => {
		await mountWith( 'primary', { ...CONNECTED, adoptable: true } );
		expect( primaries() ).toHaveLength( 0 );

		await click( '.pqm-n8n-change' );
		expect( [ ...primaries() ] ).toEqual( [ submitButton() ] );
		await click( '.pqm-n8n-change-cancel' );

		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		expect( [ ...primaries() ] ).toEqual( [
			$( '.pqm-n8n-adopt-confirm' ),
		] );

		// The connection block stays usable beside the proposal: its form wins.
		await click( '.pqm-n8n-change' );
		expect( [ ...primaries() ] ).toEqual( [ submitButton() ] );
		expect( $( '.pqm-n8n-adopt-confirm' ).dataset.variant ).toBe(
			'secondary'
		);
	} );
} );

describe( 'Adoption of a hand-made workflow (n8n provisioning, entry 8): the adoption block', () => {
	const ADOPTION_PATH = '/post-quality/v1/n8n/adoption';
	const HAND_MADE = { ...CONNECTED, adoptable: true };
	const PROPOSAL = {
		workflow: {
			id: 'wf-hand',
			name: 'My blog reviews',
			path: 'pqm-execute',
		},
		credentials: {
			webhook: { id: 'c-h1', name: 'Header Auth account' },
			anthropic: { id: 'c-k1', name: 'my claude key' },
			wordpress: { id: 'c-b1', name: 'wp basic' },
		},
		// eslint-disable-next-line camelcase -- REST field name.
		rewrite_ready: true,
	};
	const ADOPTED = {
		...CONNECTED,
		setup: { wizard_created: false, at: 1760000000 }, // eslint-disable-line camelcase -- REST field name.
		// eslint-disable-next-line camelcase -- REST field name.
		rewrite_ready: true,
	};
	const offer = () => $( '.pqm-n8n-adopt' );
	// The connection block comes first, the adoption block under it.
	const adoptionUnderConnection = () =>
		$( '.pqm-n8n-connection-block' ).nextElementSibling ===
		$( '.pqm-n8n-adoption' );

	test( 'Offer: only while adoptable, in the adoption block under the unchanged summary', async () => {
		await mount( CONNECTED );
		expect( offer() ).toBeNull();
		expect( $( '.pqm-n8n-adoption' ) ).toBeNull();
		expect( text() ).not.toContain( 'set up by hand' );

		await mount( HAND_MADE );
		expect( offer().textContent ).toBe(
			'Let the plugin manage this workflow'
		);
		expect( offer().dataset.variant ).toBe( 'secondary' );
		expect( $( '.pqm-n8n-adoption' ).textContent ).toContain(
			'Your workflow was set up by hand. Let the plugin manage it, and you can update it and rotate the webhook token from this page later. Nothing in n8n changes.'
		);
		expect( adoptionUnderConnection() ).toBe( true );
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'Proposal: one GET, by name, in place of the offer; the summary stays above it; Cancel restores the offer', async () => {
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		expect( apiFetch ).toHaveBeenLastCalledWith( { path: ADOPTION_PATH } );
		expect( $( '.pqm-n8n-adoption .pqm-n8n-proposal' ) ).not.toBeNull();
		expect( text() ).toContain(
			'Found at your webhook URL: My blog reviews, listening on /webhook/pqm-execute.'
		);
		const roles = [
			...document.querySelectorAll( '.pqm-n8n-proposal-credentials li' ),
		].map( ( item ) => item.textContent );
		expect( roles ).toEqual( [
			'Webhook token: Header Auth account',
			'Anthropic: my claude key',
			'WordPress: wp basic',
		] );
		expect( text() ).toContain( 'Nothing in n8n changes.' );
		expect( offer() ).toBeNull();
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( adoptionUnderConnection() ).toBe( true );
		expect( $( '.pqm-n8n-adopt-confirm' ).dataset.variant ).toBe(
			'primary'
		);
		// The offer button unmounted: focus moves to the proposal.
		expect( document.activeElement ).toBe( $( '.pqm-n8n-proposal' ) );

		await click( '.pqm-n8n-adopt-cancel' );
		expect( $( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( offer() ).not.toBeNull();
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( adoptionUnderConnection() ).toBe( true );
		// Cancel unmounted: focus back on the offer button.
		expect( document.activeElement ).toBe( offer() );
	} );

	test( 'Proposal: the connection block stays usable; Change and Cancel leave the proposal open', async () => {
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		await click( '.pqm-n8n-change' );
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( $( '.pqm-n8n-proposal' ) ).not.toBeNull();
		await click( '.pqm-n8n-change-cancel' );
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( $( '.pqm-n8n-proposal' ) ).not.toBeNull();
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'Rewrite unwired (Pro): "none", Rewrite stays off; the Free build shows no WordPress line', async () => {
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( {
			...PROPOSAL,
			credentials: {
				webhook: PROPOSAL.credentials.webhook,
				anthropic: PROPOSAL.credentials.anthropic,
			},
			// eslint-disable-next-line camelcase -- REST field name.
			rewrite_ready: false,
		} );
		await click( '.pqm-n8n-adopt' );
		expect( text() ).toContain(
			'WordPress: none. Rewrite stays off until a credential is set on Update WP Post (Rewrite).'
		);

		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( {
			workflow: PROPOSAL.workflow,
			credentials: {
				webhook: PROPOSAL.credentials.webhook,
				anthropic: PROPOSAL.credentials.anthropic,
			},
		} );
		await click( '.pqm-n8n-adopt' );
		expect(
			document.querySelectorAll( '.pqm-n8n-proposal-credentials li' )
		).toHaveLength( 2 );
		expect( text() ).not.toContain( 'WordPress' );
	} );

	test( 'Confirm: POST with the id, the managed line in place of the proposal, the summary kept, onChange called', async () => {
		const onChange = jest.fn();
		await mount( HAND_MADE, onChange );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		apiFetch.mockResolvedValueOnce( ADOPTED );
		await click( '.pqm-n8n-adopt-confirm' );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: ADOPTION_PATH,
			method: 'POST',
			// eslint-disable-next-line camelcase -- REST field name.
			data: { workflow_id: 'wf-hand' },
		} );
		expect( $( '.pqm-n8n-adoption .pqm-n8n-managed' ).textContent ).toBe(
			`The plugin has managed this workflow since ${ new Date(
				1760000000 * 1000
			).toLocaleDateString() }: it can update the workflow and rotate the webhook token from this page. Nothing in n8n changed.`
		);
		expect( offer() ).toBeNull();
		expect( $( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( onChange ).toHaveBeenLastCalledWith( ADOPTED );
		expect( onChange ).toHaveBeenCalledTimes( 2 );
		// No offer any more: focus on the managed line.
		expect( document.activeElement ).toBe( $( '.pqm-n8n-managed' ) );
	} );

	test( 'Managed: a provisioned site says "set this workflow up on", no offer', async () => {
		await mount( {
			...CONNECTED,
			// eslint-disable-next-line camelcase -- REST field name.
			setup: { wizard_created: true, at: 1760000000 },
		} );
		expect( $( '.pqm-n8n-adoption .pqm-n8n-managed' ).textContent ).toBe(
			`The plugin set this workflow up on ${ new Date(
				1760000000 * 1000
			).toLocaleDateString() }. It can update the workflow and rotate the webhook token from this page.`
		);
		expect( offer() ).toBeNull();
		expect( adoptionUnderConnection() ).toBe( true );
	} );

	test( 'a refused proposal: its message in the adoption block; the offer and the summary stay', async () => {
		const onChange = jest.fn();
		await mount( HAND_MADE, onChange );
		const message =
			'In n8n, Claude - Review Agent and Claude - SEO Agent use different credentials: pick one Anthropic credential on all of them, publish the workflow, then try again.';
		apiFetch.mockRejectedValueOnce( {
			code: 'pqm_workflow_miswired',
			message,
		} );
		await click( '.pqm-n8n-adopt' );
		expect( $( '.pqm-n8n-adoption .mock-notice-error' ).textContent ).toBe(
			message
		);
		expect( $( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
		expect( offer() ).not.toBeNull();
		expect( onChange ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'a refused confirm (n8n changed since): its message, the offer again to look again', async () => {
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		const message =
			'n8n changed since you looked: another workflow listens on your n8n Webhook URL now. Press Let the plugin manage this workflow again to see it.';
		apiFetch.mockRejectedValueOnce( {
			code: 'pqm_adoption_changed',
			message,
		} );
		await click( '.pqm-n8n-adopt-confirm' );
		expect( $( '.pqm-n8n-adoption .mock-notice-error' ).textContent ).toBe(
			message
		);
		expect( $( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( offer() ).not.toBeNull();
		expect( $( '.pqm-n8n-managed' ) ).toBeNull();
		expect( document.activeElement ).toBe( offer() );
	} );

	test( 'Confirm while Change is open: the typed address and key stay in the form', async () => {
		await mount( HAND_MADE );
		await click( '.pqm-n8n-change' );
		await typeInto( 'n8n address', 'https://other.example.com' );
		await typeInto( 'n8n API key', 'typed-key' );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		apiFetch.mockResolvedValueOnce( ADOPTED );
		await click( '.pqm-n8n-adopt-confirm' );
		expect( $( '.pqm-n8n-managed' ) ).not.toBeNull();
		expect( field( 'n8n address' ).value ).toBe(
			'https://other.example.com'
		);
		expect( field( 'n8n API key' ).value ).toBe( 'typed-key' );
		expect( submitButton().textContent ).toBe( 'Save and check' );
	} );

	test( "a failed Save and check, then the offer's button: the connection cause stays, beside a refused proposal's own", async () => {
		await mount( HAND_MADE );
		await click( '.pqm-n8n-change' );
		await typeInto( 'n8n API key', 'bad-key' );
		apiFetch.mockResolvedValueOnce( {
			...HAND_MADE,
			probe: { ok: false, cause: 'key', message: 'Refused key.' },
		} );
		await submit();
		const connectionCause = () =>
			$( '.pqm-n8n-connection-block .mock-notice-error' );
		expect( connectionCause().textContent ).toBe( 'Refused key.' );

		apiFetch.mockRejectedValueOnce( { message: 'Refused proposal.' } );
		await click( '.pqm-n8n-adopt' );
		expect( connectionCause().textContent ).toBe( 'Refused key.' );
		expect( $( '.pqm-n8n-adoption .mock-notice-error' ).textContent ).toBe(
			'Refused proposal.'
		);

		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		expect( connectionCause().textContent ).toBe( 'Refused key.' );
		expect( $( '.pqm-n8n-adoption .mock-notice-error' ) ).toBeNull();
	} );

	test( 'Proposal open, Change, Save and check passes: the proposal of the previous instance closes, the offer shows', async () => {
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		await click( '.pqm-n8n-change' );
		await typeInto( 'n8n address', 'https://other.example.com' );
		await typeInto( 'n8n API key', 'new-key' );
		apiFetch.mockResolvedValueOnce( {
			...HAND_MADE,
			// eslint-disable-next-line camelcase -- REST field name.
			base_url: 'https://other.example.com',
		} );
		await submit();
		expect( $( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( offer() ).not.toBeNull();
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
	} );

	test( 'Forget key with the proposal open: the proposal closes with the offer, no confirmation; focus on the address', async () => {
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		apiFetch.mockResolvedValueOnce( {
			...NOT_SET,
			// eslint-disable-next-line camelcase -- REST field name.
			base_url: BASE,
		} );
		await click( '.pqm-n8n-forget' );
		expect( apiFetch ).toHaveBeenCalledTimes( 3 );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'DELETE',
		} );
		expect( $( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( offer() ).toBeNull();
		expect( $( '.pqm-n8n-adoption' ) ).toBeNull();
		expect( summaryLine() ).toBe( 'API key: not set' );
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( document.activeElement ).toBe( field( 'n8n address' ) );
	} );
} );

describe( 'After an adoption (story 67): the page re-reads its body', () => {
	const HAND_MADE = { ...CONNECTED, adoptable: true };
	const PROPOSAL = {
		workflow: { id: 'wf-hand', name: 'My blog reviews', path: 'x' },
		credentials: {
			webhook: { id: 'c-h1', name: 'Header Auth account' },
			anthropic: { id: 'c-k1', name: 'my claude key' },
		},
	};
	const ADOPTED = {
		...CONNECTED,
		setup: { wizard_created: false, at: 1760000000 }, // eslint-disable-line camelcase -- REST field name.
	};

	test( 'a confirmed adoption dispatches pqm:workflow-refresh once (its reply carries no body); a refused one does not', async () => {
		const refreshed = jest.fn();
		document.addEventListener( 'pqm:workflow-refresh', refreshed );
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		apiFetch.mockRejectedValueOnce( {
			code: 'pqm_adoption_changed',
			message: 'n8n changed since you looked.',
		} );
		await click( '.pqm-n8n-adopt-confirm' );
		expect( refreshed ).not.toHaveBeenCalled();

		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		apiFetch.mockResolvedValueOnce( ADOPTED );
		await click( '.pqm-n8n-adopt-confirm' );
		document.removeEventListener( 'pqm:workflow-refresh', refreshed );
		expect( refreshed ).toHaveBeenCalledTimes( 1 );
		expect( $( '.pqm-n8n-managed' ) ).not.toBeNull();
	} );

	test( "no Rotate button in the island: the token rotation is the page's", async () => {
		await mount( { ...ADOPTED, rotatable: true } );
		expect( $( '.pqm-n8n-rotate' ) ).toBeNull();
		expect( text() ).not.toContain( 'Rotate' );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'Start over (story 71)', () => {
	const SETUP_PATH = '/post-quality/v1/n8n/setup';
	const MAP = {
		at: 1760000000,
		host: 'n8n.example.com',
		same_instance: true,
	}; // eslint-disable-line camelcase -- REST field names.
	const WIZARD = {
		...CONNECTED,
		rotatable: true,
		setup: { ...MAP, wizard_created: true }, // eslint-disable-line camelcase -- REST field name.
	};
	const ADOPTED = {
		...CONNECTED,
		rotatable: true,
		setup: { ...MAP, wizard_created: false }, // eslint-disable-line camelcase -- REST field name.
	};
	const ELSEWHERE = {
		...CONNECTED,
		setup: {
			...MAP,
			host: 'old.example.com',
			same_instance: false,
			wizard_created: true,
		}, // eslint-disable-line camelcase -- REST field names.
	};
	const STARTED_OVER = {
		...CONNECTED,
		message:
			'The setup was removed here and in n8n. Set n8n up again when you are ready.',
		status_html:
			'<div class="pqm-workflow-status" data-state="connected"><p>Connected to n8n at n8n.example.com. The workflow is not set up yet.</p></div>', // eslint-disable-line camelcase -- REST field name.
	};
	const modal = () => $( '.mock-modal' );
	const checkbox = () =>
		$(
			'input[aria-label="Also remove the workflow and its credentials in n8n"]'
		);

	/**
	 * Mount on the page (variant secondary, as src/index.js mounts it), with
	 * the page's body beside the island root: a confirmed Start over asks
	 * pqm-admin.js to re-read it, never swaps it from inside the island.
	 *
	 * @param {Object} state GET reply.
	 */
	async function mountOnPage( state ) {
		document.body.innerHTML =
			'<div id="pqm-n8n-connection-root"></div><div id="pqm-workflow-body"><p class="old-body">old</p></div>';
		apiFetch.mockReset();
		apiFetch.mockResolvedValueOnce( state );
		root = createRoot(
			document.getElementById( 'pqm-n8n-connection-root' )
		);
		await act( async () => {
			root.render( <N8nConnection variant="secondary" /> );
		} );
	}

	test( 'inside the setup wizard (variant primary): no Start over button, the managed line as before', async () => {
		await mount( WIZARD );
		expect( $( '.pqm-n8n-managed' ) ).not.toBeNull();
		expect( $( '.pqm-n8n-start-over' ) ).toBeNull();
		expect( modal() ).toBeNull();
	} );

	test( 'the button under the managed line; no Modal until pressed; no call', async () => {
		await mountOnPage( WIZARD );
		const button = $( '.pqm-n8n-start-over' );
		expect( button ).not.toBeNull();
		expect( button.getAttribute( 'data-variant' ) ).toBe( 'secondary' );
		expect( $( '.pqm-n8n-managed' ).nextElementSibling ).toBe( button );
		expect( modal() ).toBeNull();
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'wizard-created: the Modal with the checkbox ticked, no hand-made warning; Cancel closes it without a call', async () => {
		await mountOnPage( WIZARD );
		await click( '.pqm-n8n-start-over' );
		expect( modal() ).not.toBeNull();
		expect( modal().querySelector( 'h1' ).textContent ).toBe(
			'Start over?'
		);
		expect( modal().textContent ).toContain( 'Your n8n API key stays.' );
		expect( checkbox().checked ).toBe( true );
		expect( modal().querySelector( '.mock-notice-warning' ) ).toBeNull();
		await click( '.pqm-n8n-start-over-cancel' );
		expect( modal() ).toBeNull();
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'adopted: the checkbox unticked, the hand-made warning', async () => {
		await mountOnPage( ADOPTED );
		await click( '.pqm-n8n-start-over' );
		expect( checkbox().checked ).toBe( false );
		expect(
			modal().querySelector( '.mock-notice-warning' ).textContent
		).toBe(
			'This workflow and its credentials were made by hand in n8n. Leave the box unticked to keep them.'
		);
	} );

	test( 'another instance: the managed line names both hosts; the Modal offers no checkbox, says the objects stay there', async () => {
		await mountOnPage( ELSEWHERE );
		expect( $( '.pqm-n8n-managed' ).textContent ).toBe(
			'The plugin set this workflow up on old.example.com; you are connected to n8n.example.com now. Update workflow and Rotate token stay off until you connect old.example.com again or start over.'
		);
		await click( '.pqm-n8n-start-over' );
		expect( checkbox() ).toBeNull();
		expect( $( '.pqm-n8n-start-over-stays' ).textContent ).toBe(
			'Your workflow and credentials stay on old.example.com: the plugin does not touch an instance it is not connected to.'
		);
		apiFetch.mockResolvedValueOnce( STARTED_OVER );
		await click( '.pqm-n8n-start-over-confirm' );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: SETUP_PATH,
			method: 'DELETE',
			data: { remove_from_n8n: false }, // eslint-disable-line camelcase -- REST field name.
		} );
	} );

	test( 'no passing key: no checkbox either, the plain "stay on" line', async () => {
		await mountOnPage( {
			...WIZARD,
			probe: {
				...WIZARD.probe,
				ok: false,
				message: 'n8n refused this API key.',
			},
			connected: false,
		} );
		await click( '.pqm-n8n-start-over' );
		expect( checkbox() ).toBeNull();
		expect( $( '.pqm-n8n-start-over-stays' ).textContent ).toBe(
			'Your workflow and credentials stay on n8n.example.com.'
		);
	} );

	test( 'confirm: DELETE /n8n/setup with the checkbox value; one pqm:workflow-refresh focusing the n8n connection disclosure, no swap from the island; the managed line is gone', async () => {
		const swapped = jest.fn();
		const refreshed = jest.fn();
		document.addEventListener( 'pqm:workflow-status', swapped );
		document.addEventListener( 'pqm:workflow-refresh', refreshed );
		await mountOnPage( WIZARD );
		await click( '.pqm-n8n-start-over' );
		await act( async () => {
			checkbox().click();
		} );
		expect( checkbox().checked ).toBe( false );
		await act( async () => {
			checkbox().click();
		} );
		apiFetch.mockResolvedValueOnce( STARTED_OVER );
		await click( '.pqm-n8n-start-over-confirm' );
		document.removeEventListener( 'pqm:workflow-status', swapped );
		document.removeEventListener( 'pqm:workflow-refresh', refreshed );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: SETUP_PATH,
			method: 'DELETE',
			data: { remove_from_n8n: true }, // eslint-disable-line camelcase -- REST field name.
		} );
		expect( refreshed ).toHaveBeenCalledTimes( 1 );
		expect( refreshed.mock.calls[ 0 ][ 0 ].detail.focus ).toBe(
			'pqm-n8n-connection'
		);
		expect( swapped ).not.toHaveBeenCalled();
		expect( document.querySelector( '.old-body' ) ).not.toBeNull();
		expect( modal() ).toBeNull();
		expect( $( '.pqm-n8n-managed' ) ).toBeNull();
		expect( $( '.pqm-n8n-start-over' ) ).toBeNull();
		expect( $( '.pqm-n8n-summary' ) ).not.toBeNull();
	} );

	test( "a refused call: the Modal closes, the cause in the adoption block's Notice, the managed line stays, no refresh", async () => {
		const swapped = jest.fn();
		document.addEventListener( 'pqm:workflow-refresh', swapped );
		await mountOnPage( ELSEWHERE );
		await click( '.pqm-n8n-start-over' );
		apiFetch.mockRejectedValueOnce( {
			code: 'pqm_other_instance',
			message:
				'The plugin set this workflow up on old.example.com, not on the n8n you are connected to now.',
		} );
		await click( '.pqm-n8n-start-over-confirm' );
		document.removeEventListener( 'pqm:workflow-refresh', swapped );
		expect( modal() ).toBeNull();
		expect( $( '.pqm-n8n-adoption .mock-notice-error' ).textContent ).toBe(
			'The plugin set this workflow up on old.example.com, not on the n8n you are connected to now.'
		);
		expect( $( '.pqm-n8n-managed' ) ).not.toBeNull();
		expect( swapped ).not.toHaveBeenCalled();
		expect( document.querySelector( '.old-body' ) ).not.toBeNull();
	} );
} );
