/**
 * n8n Instance section (n8n provisioning, entry 1; src/n8n-connection/) run
 * in jsdom: the stored state on load (connected, key set) without
 * a probe, a failed probe's cause with the stored state kept, and Forget.
 * The key field is write-only: never prefilled, emptied after a save. The
 * api-fetch and components packages are WordPress-provided externals, not
 * installed here: both are mocked.
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
		Button: ( { children, isBusy, isDestructive, variant, ...props } ) => (
			<button data-variant={ variant } { ...props }>
				{ children }
			</button>
		),
		Notice: ( { status, children } ) => (
			<div className={ 'mock-notice-' + status }>{ children }</div>
		),
		TextControl: ( { label, type, value, onChange } ) => (
			<input
				aria-label={ label }
				type={ type }
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
	probe: {
		ok: true,
		cause: '',
		message: 'Connected to n8n.',
		at: 1760000000,
	},
};
// eslint-disable-next-line camelcase -- REST field names.
const NOT_SET = { base_url: '', key_set: false, probe: null };

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

describe( 'n8n Instance section', () => {
	test( 'a reload shows the stored state: connected, key set — one GET, no probe', async () => {
		await mount( CONNECTED );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( { path: PATH } );
		expect(
			document.querySelector( '.mock-notice-success' ).textContent
		).toContain( 'Connected to n8n.' );
		expect( text() ).toContain( 'API key: set' );
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n API key' ).type ).toBe( 'password' );
		expect( document.querySelector( '.pqm-n8n-forget' ) ).not.toBeNull();
		expect( document.querySelector( '.pqm-n8n-recheck' ) ).not.toBeNull();
	} );

	test( 'not set: no notice, no Check again or Forget, Connect waits for a key', async () => {
		await mount( NOT_SET );
		expect( text() ).toContain( 'API key: not set' );
		expect( document.querySelector( '.mock-notice-success' ) ).toBeNull();
		expect( document.querySelector( '.pqm-n8n-forget' ) ).toBeNull();
		expect( document.querySelector( '.pqm-n8n-recheck' ) ).toBeNull();
		await typeInto( 'n8n address', BASE );
		expect(
			document.querySelector( 'button[type="submit"]' ).disabled
		).toBe( true );
		await typeInto( 'n8n API key', 'k' );
		expect(
			document.querySelector( 'button[type="submit"]' ).disabled
		).toBe( false );
	} );

	test( 'connect: URL and key posted; on success the state shows connected and the key field empties', async () => {
		await mount( NOT_SET );
		await typeInto( 'n8n address', ` ${ BASE }/home/workflows ` );
		await typeInto( 'n8n API key', ' secret-key ' );
		apiFetch.mockResolvedValueOnce( CONNECTED );
		await submit();
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'POST',
			// eslint-disable-next-line camelcase -- REST field names.
			data: {
				base_url: `${ BASE }/home/workflows`,
				api_key: 'secret-key',
			},
		} );
		expect(
			document.querySelector( '.mock-notice-success' ).textContent
		).toContain( 'Connected to n8n.' );
		expect( text() ).toContain( 'API key: set' );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n address' ).value ).toBe( BASE );
	} );

	test( 'a failed probe shows its cause; the stored state stays as it was', async () => {
		await mount( CONNECTED );
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
			document.querySelector( '.mock-notice-error' ).textContent
		).toBe( cause );
		expect(
			document.querySelector( '.mock-notice-success' ).textContent
		).toContain( 'Connected to n8n.' );
		expect( text() ).toContain( 'API key: set' );
	} );

	test( 'Check again posts without a key; Forget deletes and shows "not set"', async () => {
		await mount( CONNECTED );
		apiFetch.mockResolvedValueOnce( CONNECTED );
		await act( async () => {
			document.querySelector( '.pqm-n8n-recheck' ).click();
		} );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'POST',
		} );

		// eslint-disable-next-line camelcase -- REST field names.
		apiFetch.mockResolvedValueOnce( {
			base_url: BASE,
			key_set: false,
			probe: null,
		} );
		await act( async () => {
			document.querySelector( '.pqm-n8n-forget' ).click();
		} );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: PATH,
			method: 'DELETE',
		} );
		expect( text() ).toContain( 'API key: not set' );
		expect( document.querySelector( '.mock-notice-success' ) ).toBeNull();
		expect( document.querySelector( '.pqm-n8n-forget' ) ).toBeNull();
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
	} );

	test( 'onChange (the setup wizard, entry 6) gets the stored state after the read, a passing connect and Forget — never after a failed probe', async () => {
		const onChange = jest.fn();
		await mount( NOT_SET, onChange );
		expect( onChange ).toHaveBeenLastCalledWith( NOT_SET );

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

		// eslint-disable-next-line camelcase -- REST field names.
		const forgotten = { base_url: BASE, key_set: false, probe: null };
		apiFetch.mockResolvedValueOnce( forgotten );
		await act( async () => {
			document.querySelector( '.pqm-n8n-forget' ).click();
		} );
		expect( onChange ).toHaveBeenCalledTimes( 3 );
		expect( onChange ).toHaveBeenLastCalledWith( forgotten );
	} );
} );

describe( "Connect yields to the update notice's Re-import the workflow (plan-update-notice-primary-action)", () => {
	const REIMPORT_BLOCK =
		'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p><p><a class="button button-primary pqm-reimport" href="#pqm-workflow-file">Re-import the workflow</a></p></div></div>';
	const CURRENT_BLOCK =
		'<div class="pqm-workflow-status"><p class="description">Up to date.</p></div>';
	const connectVariant = () =>
		document.querySelector( 'button[type="submit"]' ).dataset.variant;

	/**
	 * Mount the section beside this status block.
	 *
	 * @param {string}    block    The page's status block.
	 * @param {Function=} onChange The setup wizard's prop.
	 */
	async function mountBeside( block, onChange ) {
		document.body.innerHTML = `${ block }<div id="pqm-n8n-connection-root"></div>`;
		apiFetch.mockReset();
		apiFetch.mockResolvedValueOnce( CONNECTED );
		root = createRoot(
			document.getElementById( 'pqm-n8n-connection-root' )
		);
		await act( async () => {
			root.render( <N8nConnection onChange={ onChange } /> );
		} );
	}

	test( 'on the page, with the re-import button shown: secondary', async () => {
		await mountBeside( REIMPORT_BLOCK );
		expect( connectVariant() ).toBe( 'secondary' );
	} );

	test( 'on the page, up to date: primary', async () => {
		await mountBeside( CURRENT_BLOCK );
		expect( connectVariant() ).toBe( 'primary' );
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
		expect( connectVariant() ).toBe( 'secondary' );
	} );

	test( 'in the setup guide (onChange), the Modal is its own surface: primary', async () => {
		await mountBeside( REIMPORT_BLOCK, jest.fn() );
		expect( connectVariant() ).toBe( 'primary' );
	} );
} );
