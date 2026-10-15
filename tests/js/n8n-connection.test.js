/**
 * n8n Instance section (n8n provisioning, entry 1; src/n8n-connection/) run
 * in jsdom, one test per row of plan-n8n-connection-states' matrix: the
 * connection block (connected summary, changing, connecting) and the
 * adoption block under it (offer, proposal, managed line), each in place,
 * with the call made and where keyboard focus lands. The key field is
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
	setup: null,
	adoptable: false,
};
const NOT_SET = {
	// eslint-disable-next-line camelcase -- REST field name.
	base_url: '',
	// eslint-disable-next-line camelcase -- REST field name.
	key_set: false,
	// eslint-disable-next-line camelcase -- REST field name.
	key_hint: '',
	probe: null,
	setup: null,
	adoptable: false,
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
		await mount( { ...CONNECTED, probe: null } );
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

	test( 'the setup wizard (onChange): Connecting, then Connected; onChange after the read, a passing connect and Forget — never after a failed probe', async () => {
		const onChange = jest.fn();
		await mount( NOT_SET, onChange );
		expect( onChange ).toHaveBeenLastCalledWith( NOT_SET );
		expect( submitButton().textContent ).toBe( 'Connect' );

		await typeInto( 'n8n address', BASE );
		await typeInto( 'n8n API key', 'bad-key' );
		apiFetch.mockResolvedValueOnce( {
			...NOT_SET,
			probe: { ok: false, cause: 'key', message: 'Refused.' },
		} );
		await submit();
		expect( onChange ).toHaveBeenCalledTimes( 1 );

		await typeInto( 'n8n API key', 'good-key' );
		apiFetch.mockResolvedValueOnce( CONNECTED );
		await submit();
		expect( onChange ).toHaveBeenCalledTimes( 2 );
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
		expect( onChange ).toHaveBeenCalledTimes( 3 );
		expect( onChange ).toHaveBeenLastCalledWith( forgotten );
	} );
} );

describe( "One primary at most; it yields to the update notice's Re-import the workflow (plan-update-notice-primary-action)", () => {
	const REIMPORT_BLOCK =
		'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p><p><a class="button button-primary pqm-reimport" href="#pqm-workflow-file">Re-import the workflow</a></p></div></div>';
	const CURRENT_BLOCK =
		'<div class="pqm-workflow-status"><p class="description">Up to date.</p></div>';
	const PROPOSAL = {
		workflow: { id: 'wf-hand', name: 'My blog reviews', path: 'x' },
		credentials: {
			webhook: { id: 'c-h1', name: 'Header Auth account' },
			anthropic: { id: 'c-k1', name: 'my claude key' },
		},
	};
	const submitVariant = () => submitButton().dataset.variant;

	/**
	 * Mount the section beside this status block.
	 *
	 * @param {string}    block    The page's status block.
	 * @param {Function=} onChange The setup wizard's prop.
	 * @param {Object=}   state    GET reply.
	 */
	async function mountBeside( block, onChange, state = NOT_SET ) {
		document.body.innerHTML = `${ block }<div id="pqm-n8n-connection-root"></div>`;
		apiFetch.mockReset();
		apiFetch.mockResolvedValueOnce( state );
		root = createRoot(
			document.getElementById( 'pqm-n8n-connection-root' )
		);
		await act( async () => {
			root.render( <N8nConnection onChange={ onChange } /> );
		} );
	}

	test( 'on the page, with the re-import button shown: Connect secondary', async () => {
		await mountBeside( REIMPORT_BLOCK );
		expect( submitVariant() ).toBe( 'secondary' );
		expect( primaries() ).toHaveLength( 0 );
	} );

	test( 'on the page, up to date: Connect primary', async () => {
		await mountBeside( CURRENT_BLOCK );
		expect( submitVariant() ).toBe( 'primary' );
		expect( primaries() ).toHaveLength( 1 );
	} );

	test( 'on the page, it follows each swap of the block', async () => {
		await mountBeside( CURRENT_BLOCK );
		await act( async () => {
			document.querySelector( '.pqm-workflow-status' ).outerHTML =
				REIMPORT_BLOCK;
			document.dispatchEvent(
				new window.CustomEvent( 'pqm:workflow-status' )
			);
		} );
		expect( submitVariant() ).toBe( 'secondary' );
	} );

	test( 'in the setup guide (onChange), the Modal is its own surface: primary', async () => {
		await mountBeside( REIMPORT_BLOCK, jest.fn() );
		expect( submitVariant() ).toBe( 'primary' );
	} );

	test( 'connected: none in the summary; Save and check while changing; Manage this workflow only while no form is open', async () => {
		await mountBeside( CURRENT_BLOCK, undefined, {
			...CONNECTED,
			adoptable: true,
		} );
		expect( primaries() ).toHaveLength( 0 );

		await click( '.pqm-n8n-change' );
		expect( [ ...primaries() ] ).toEqual( [ submitButton() ] );
		expect( submitVariant() ).toBe( 'primary' );
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

	test( 'on the page, with the re-import button shown: no primary with the proposal open either', async () => {
		await mountBeside( REIMPORT_BLOCK, undefined, {
			...CONNECTED,
			adoptable: true,
		} );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		expect( primaries() ).toHaveLength( 0 );
		await click( '.pqm-n8n-change' );
		expect( primaries() ).toHaveLength( 0 );
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
			'Your workflow was set up by hand. Let the plugin manage it, and you can update it and rotate the webhook token from here later. Nothing in n8n changes.'
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
			).toLocaleDateString() }.`
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
			).toLocaleDateString() }.`
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
