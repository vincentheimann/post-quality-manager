/**
 * Workflow version handshake (v5) in the setup dialog: the connect step
 * (the `Connect` body of src/setup/steps.js, which the Set up the
 * workflow and Set up by hand steps reuse) run in jsdom. A reply that
 * carries `status_html` swaps the page's body (#pqm-workflow-body, story
 * 67), which sits outside the island (AC2); an older workflow is connected
 * but warned (D2). The api-fetch and components packages are
 * WordPress-provided externals, not installed here: both are mocked.
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- React comes with @wordpress/element (wp-scripts); act is not re-exported there.
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import { Connect } from '../../src/setup/steps';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock(
	'@wordpress/components',
	() => ( {
		Button: ( { children, isBusy, variant, ...props } ) => (
			<button { ...props }>{ children }</button>
		),
		Notice: ( { status, children } ) => (
			<div className={ 'mock-notice-' + status }>{ children }</div>
		),
		TextControl: ( { value, onChange } ) => (
			<input
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		),
	} ),
	{ virtual: true }
);

const URL = 'https://n8n.example/webhook/pqm-execute';
const OLD_BLOCK =
	'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p></div><p class="description">Your n8n workflow: version 4, reported by a command.</p></div>';
const NEW_BLOCK =
	'<div class="pqm-workflow-status"><p class="description">Your n8n workflow: version 5, checked just now by a test ping.</p></div>';

let root;

/**
 * The n8n Workflow page around the island, then Connect mounted in it
 * (the stored URL prefilled) and its form submitted with this reply.
 *
 * @param {Object} reply POST /setup/connect answer.
 * @return {jest.Mock} onConnected.
 */
async function connectWith( reply ) {
	document.body.innerHTML = `<div id="pqm-setup-root"></div><div id="pqm-workflow-body" aria-live="polite">${ OLD_BLOCK }</div>`;
	apiFetch.mockReset();
	apiFetch.mockResolvedValue( reply );
	const onConnected = jest.fn();
	root = createRoot( document.getElementById( 'pqm-setup-root' ) );
	await act( async () => {
		root.render(
			<Connect
				// eslint-disable-next-line camelcase -- REST field name.
				state={ { webhook_url: URL } }
				onConnected={ onConnected }
			/>
		);
	} );
	await act( async () => {
		document.querySelector( '.pqm-setup-connect' ).dispatchEvent(
			new window.Event( 'submit', {
				bubbles: true,
				cancelable: true,
			} )
		);
	} );
	return onConnected;
}

const block = () => document.querySelectorAll( '.pqm-workflow-status' );

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	act( () => root.unmount() );
} );

describe( 'Setup dialog connect step', () => {
	test( 'a v5 reply swaps the page status block and shows a success', async () => {
		const onConnected = await connectWith( {
			saved: true,
			ping_ok: true,
			outdated: false,
			message: 'Ping OK — up to date.',
			status_html: NEW_BLOCK,
		} );
		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/post-quality/v1/setup/connect',
				method: 'POST',
				// eslint-disable-next-line camelcase -- REST field name.
				data: { webhook_url: URL },
			} )
		);
		expect( block() ).toHaveLength( 1 );
		expect( block()[ 0 ].outerHTML ).toBe( NEW_BLOCK );
		expect(
			document.querySelector( '.mock-notice-success' ).textContent
		).toBe( 'Ping OK — up to date.' );
		expect( onConnected ).toHaveBeenCalledWith( URL, true );
	} );

	test( 'an older workflow: warning notice, never a success, the step still ticks', async () => {
		const onConnected = await connectWith( {
			saved: true,
			ping_ok: true,
			outdated: true,
			message: 'Ping OK — older version.',
			status_html: NEW_BLOCK.replace( 'version 5', 'an older version' ),
		} );
		expect(
			document.querySelector( '.mock-notice-warning' ).textContent
		).toBe( 'Ping OK — older version.' );
		expect( document.querySelector( '.mock-notice-success' ) ).toBeNull();
		expect( block()[ 0 ].textContent ).toContain( 'an older version' );
		expect( onConnected ).toHaveBeenCalledWith( URL, true );
	} );

	test( 'a failed ping shows the error notice and swaps the body the reply carries (the page needs attention)', async () => {
		const FAILING =
			'<div class="pqm-workflow-status" data-state="failing"><p class="pqm-workflow-sentence">The last test just now did not pass.</p></div>';
		const onConnected = await connectWith( {
			saved: true,
			ping_ok: false,
			message: 'URL saved, but the test did not pass.',
			status_html: FAILING,
		} );
		expect(
			document.querySelector( '.mock-notice-error' ).textContent
		).toBe( 'URL saved, but the test did not pass.' );
		expect( block()[ 0 ].outerHTML ).toBe( FAILING );
		expect( onConnected ).toHaveBeenCalledWith( URL, false );
	} );

	test( 'a reply without status_html leaves the body as it is', async () => {
		await connectWith( {
			saved: false,
			ping_ok: false,
			message: 'Enter your n8n webhook URL first.',
		} );
		expect( block()[ 0 ].outerHTML ).toBe( OLD_BLOCK );
		expect( document.getElementById( 'pqm-workflow-body' ).innerHTML ).toBe(
			OLD_BLOCK
		);
	} );
} );
