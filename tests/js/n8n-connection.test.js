/**
 * n8n Instance section (n8n provisioning, entry 1; src/n8n-connection/) run
 * in jsdom: the stored state on load (connected, key set) without
 * a probe, a failed probe's cause with the stored state kept, and Forget.
 * The key field is write-only: never prefilled, emptied after a save; the
 * stored key's last 4 characters (`key_hint`) show only in the status line
 * and as the field's masked placeholder. The
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

describe( 'n8n Instance section', () => {
	test( 'a reload shows the stored state: connected, key set — one GET, no probe', async () => {
		await mount( CONNECTED );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( { path: PATH } );
		expect(
			document.querySelector( '.mock-notice-success' ).textContent
		).toContain( 'Connected to n8n.' );
		expect( text() ).toContain( 'API key: set, ending in ab12' );
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '••••••••ab12' );
		expect( field( 'n8n API key' ).dataset.help ).toBe(
			'Type a new key to replace it.'
		);
		expect( field( 'n8n API key' ).type ).toBe( 'password' );
		expect( document.querySelector( '.pqm-n8n-forget' ) ).not.toBeNull();
		expect( document.querySelector( '.pqm-n8n-recheck' ) ).not.toBeNull();
	} );

	test( 'a short stored key (no hint): "set" without an ending, no placeholder', async () => {
		// eslint-disable-next-line camelcase -- REST field name.
		await mount( { ...CONNECTED, key_hint: '' } );
		expect(
			document.querySelector( '.pqm-n8n-key-state' ).textContent
		).toBe( 'API key: set' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '' );
		expect( field( 'n8n API key' ).dataset.help ).toBe(
			'A key is stored. Type a new key to replace it.'
		);
	} );

	test( 'not set: no notice, no Check again or Forget, Connect waits for a key', async () => {
		await mount( NOT_SET );
		expect( text() ).toContain( 'API key: not set' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '' );
		expect( field( 'n8n API key' ).dataset.help ).toContain(
			'Create one in n8n under Settings → n8n API.'
		);
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
		expect(
			document.querySelector( '.mock-notice-success' ).textContent
		).toContain( 'Connected to n8n.' );
		expect( text() ).toContain( 'API key: set, ending in wxyz' );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '••••••••wxyz' );
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
		expect( text() ).toContain( 'API key: set, ending in ab12' );
		expect( field( 'n8n API key' ).value ).toBe( 'new-key' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '••••••••ab12' );
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
			key_hint: '',
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
		expect( text() ).not.toContain( 'ab12' );
		expect( document.querySelector( '.mock-notice-success' ) ).toBeNull();
		expect( document.querySelector( '.pqm-n8n-forget' ) ).toBeNull();
		expect( field( 'n8n address' ).value ).toBe( BASE );
		expect( field( 'n8n API key' ).value ).toBe( '' );
		expect( field( 'n8n API key' ).placeholder ).toBe( '' );
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
		const forgotten = {
			base_url: BASE,
			key_set: false,
			key_hint: '',
			probe: null,
		};
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

describe( 'Adoption of a hand-made workflow (n8n provisioning, entry 8)', () => {
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
	const offer = () => document.querySelector( '.pqm-n8n-adopt' );
	const click = async ( selector ) =>
		act( async () => {
			document.querySelector( selector ).click();
		} );

	test( 'the offer shows only while adoptable', async () => {
		await mount( CONNECTED );
		expect( offer() ).toBeNull();
		expect( text() ).not.toContain( 'set up by hand' );

		await mount( HAND_MADE );
		expect( offer().textContent ).toBe(
			'Let the plugin manage this workflow'
		);
		expect( offer().dataset.variant ).toBe( 'secondary' );
		expect( text() ).toContain(
			'Your workflow was set up by hand. Let the plugin manage it, and you can update it and rotate the webhook token from here later. Nothing in n8n changes.'
		);
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'Let the plugin manage: one GET, the proposal by name in place of the form', async () => {
		await mount( HAND_MADE );
		apiFetch.mockResolvedValueOnce( PROPOSAL );
		await click( '.pqm-n8n-adopt' );
		expect( apiFetch ).toHaveBeenLastCalledWith( { path: ADOPTION_PATH } );
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
		expect(
			document.querySelector( '.pqm-n8n-connection-form' )
		).toBeNull();
		expect( offer() ).toBeNull();
		expect(
			document.querySelector( '.pqm-n8n-adopt-confirm' ).dataset.variant
		).toBe( 'primary' );
		// The offer button unmounted: focus moves to the proposal.
		expect( document.activeElement ).toBe(
			document.querySelector( '.pqm-n8n-proposal' )
		);

		await click( '.pqm-n8n-adopt-cancel' );
		expect(
			document.querySelector( '.pqm-n8n-connection-form' )
		).not.toBeNull();
		expect( document.querySelector( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( offer() ).not.toBeNull();
		// Cancel unmounted: focus back on the offer button.
		expect( document.activeElement ).toBe( offer() );
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

	test( 'confirm: POST with the id, the managed line, onChange called', async () => {
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
		expect( document.querySelector( '.pqm-n8n-managed' ).textContent ).toBe(
			`The plugin has managed this workflow since ${ new Date(
				1760000000 * 1000
			).toLocaleDateString() }.`
		);
		expect( offer() ).toBeNull();
		expect( document.querySelector( '.pqm-n8n-proposal' ) ).toBeNull();
		expect(
			document.querySelector( '.pqm-n8n-connection-form' )
		).not.toBeNull();
		expect( onChange ).toHaveBeenLastCalledWith( ADOPTED );
		expect( onChange ).toHaveBeenCalledTimes( 2 );
		// No offer any more: focus on the managed line.
		expect( document.activeElement ).toBe(
			document.querySelector( '.pqm-n8n-managed' )
		);
	} );

	test( 'a provisioned site: "set this workflow up on", no offer', async () => {
		await mount( {
			...CONNECTED,
			// eslint-disable-next-line camelcase -- REST field name.
			setup: { wizard_created: true, at: 1760000000 },
		} );
		expect( document.querySelector( '.pqm-n8n-managed' ).textContent ).toBe(
			`The plugin set this workflow up on ${ new Date(
				1760000000 * 1000
			).toLocaleDateString() }.`
		);
		expect( offer() ).toBeNull();
	} );

	test( 'a refused proposal shows its message in the failure Notice; the form stays', async () => {
		const onChange = jest.fn();
		await mount( HAND_MADE, onChange );
		const message =
			'In n8n, Claude - Review Agent and Claude - SEO Agent use different credentials: pick one Anthropic credential on all of them, publish the workflow, then try again.';
		apiFetch.mockRejectedValueOnce( {
			code: 'pqm_workflow_miswired',
			message,
		} );
		await click( '.pqm-n8n-adopt' );
		expect(
			document.querySelector( '.mock-notice-error' ).textContent
		).toBe( message );
		expect( document.querySelector( '.pqm-n8n-proposal' ) ).toBeNull();
		expect(
			document.querySelector( '.pqm-n8n-connection-form' )
		).not.toBeNull();
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
		expect(
			document.querySelector( '.mock-notice-error' ).textContent
		).toBe( message );
		expect( document.querySelector( '.pqm-n8n-proposal' ) ).toBeNull();
		expect( offer() ).not.toBeNull();
		expect( document.querySelector( '.pqm-n8n-managed' ) ).toBeNull();
	} );
} );
