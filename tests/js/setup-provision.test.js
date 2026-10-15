/**
 * n8n provisioning, entry 6: the setup dialog's n8n steps, run in jsdom —
 * the whole island (src/setup/index.js) with the n8n Instance island inside
 * it, so the step chosen on open, the island's report, the provisioning
 * body and the manual handoff meet as in the browser. The Anthropic key is
 * sent once to POST /n8n/provision and the field empties after the answer,
 * whatever it is. The WordPress-provided externals (api-fetch, components,
 * url) are not installed here: they are mocked.
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- React comes with @wordpress/element (wp-scripts); act is not re-exported there.
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import SetupIsland from '../../src/setup';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock(
	'@wordpress/url',
	() => ( {
		addQueryArgs: ( path ) => path,
		getQueryArg: () => undefined,
		removeQueryArgs: ( url ) => url,
	} ),
	{ virtual: true }
);
jest.mock(
	'@wordpress/components',
	() => ( {
		Button: ( {
			children,
			isBusy,
			isPressed,
			isDestructive,
			variant,
			__next40pxDefaultSize,
			...props
		} ) => (
			<button data-variant={ variant } { ...props }>
				{ children }
			</button>
		),
		Dashicon: () => null,
		Modal: ( { title, children } ) => (
			<div className="mock-modal">
				<h1>{ title }</h1>
				{ children }
			</div>
		),
		Notice: ( { status, children } ) => (
			<div className={ 'mock-notice-' + status }>{ children }</div>
		),
		SelectControl: () => null,
		Spinner: () => null,
		TextControl: ( { label, type, value, onChange, disabled } ) => (
			<input
				aria-label={ label }
				type={ type }
				value={ value }
				disabled={ disabled }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		),
		VisuallyHidden: ( { children } ) => <span>{ children }</span>,
	} ),
	{ virtual: true }
);

const SETUP = '/post-quality/v1/setup';
const CONNECTION = '/post-quality/v1/n8n/connection';
const PROVISION = '/post-quality/v1/n8n/provision';
const BASE = 'https://n8n.example.com';
const PATH = 'pqm-execute-example-com';
const PROVIDER_KEY = 'sk-ant-entry-6-provider-key';
const OLD_BLOCK =
	'<div class="pqm-workflow-status"><p>Not connected.</p></div>';
const NEW_BLOCK =
	'<div class="pqm-workflow-status"><p>Your workflow answers.</p></div>';

/* eslint-disable camelcase -- REST field names. */
const NOT_SET = { base_url: '', key_set: false, probe: null };
const PASSING = {
	base_url: BASE,
	key_set: true,
	probe: { ok: true, cause: '', message: 'Connected to n8n.', at: 1 },
};

/**
 * GET /setup of a fresh Pro site; `values` override it.
 *
 * @param {Object} values Fields to change.
 * @return {Object} Setup state.
 */
function siteState( values = {} ) {
	return {
		complete: false,
		connected: false,
		first_result: false,
		webhook_url: '',
		webhook_path: PATH,
		steps: {
			n8n: false,
			provision: false,
			manual: false,
			review: false,
			profile: false,
			standards: false,
			schedule: false,
		},
		n8n: NOT_SET,
		manual: false,
		manual_reason: null,
		provisioned: false,
		leftovers: false,
		build: 'pro',
		...values,
	};
}
const HAND_URL = 'https://n8n.example.com/webhook/by-hand';
const FORGOTTEN = { base_url: BASE, key_set: false, probe: null };

/**
 * GET /setup of a site the plugin set up: the setup map, the URL saved and
 * pinged, the key passing; `values` override it.
 *
 * @param {Object} values Fields to change.
 * @return {Object} Setup state.
 */
function provisionedSite( values = {} ) {
	return siteState( {
		n8n: PASSING,
		provisioned: true,
		connected: true,
		webhook_url: `${ BASE }/webhook/${ PATH }`,
		steps: {
			...siteState().steps,
			n8n: ! values.n8n || values.n8n.key_set,
			provision: true,
			manual: true,
		},
		...values,
	} );
}

/**
 * GET /setup of a site whose workflow was made by hand: a URL saved, no
 * setup map, so the manual path, its one step done; `values` override it.
 *
 * @param {Object} values Fields to change.
 * @return {Object} Setup state.
 */
function handMadeSite( values = {} ) {
	return siteState( {
		connected: true,
		webhook_url: HAND_URL,
		manual: true,
		steps: {
			...siteState().steps,
			n8n: !! ( values.n8n && values.n8n.key_set ),
			manual: true,
			review: !! values.complete,
		},
		...values,
	} );
}
/* eslint-enable camelcase */

let root;
let replies;

/**
 * Mount the island with this GET /setup state and open the dialog. Every
 * other call answers from `replies[ method + ' ' + path ]`: a function of
 * the call's data, a value (resolved) or `{ reject }`.
 *
 * @param {Object} state   GET /setup state.
 * @param {Object} answers Replies by "METHOD path".
 */
async function openDialog( state, answers = {} ) {
	document.body.innerHTML = `<div id="pqm-setup-root"></div>${ OLD_BLOCK }<h2 id="pqm-workflow-file" tabindex="-1">Workflow File</h2>`;
	replies = {
		[ `GET ${ SETUP }` ]: state,
		[ `GET ${ CONNECTION }` ]: state.n8n,
		'GET /post-quality/v1/posts': { items: [] },
		...answers,
	};
	apiFetch.mockReset();
	apiFetch.mockImplementation( ( { path, method = 'GET', data } ) => {
		let reply = replies[ `${ method } ${ path }` ];
		if ( typeof reply === 'function' ) {
			reply = reply( data );
		}
		return reply && reply.reject
			? Promise.reject( reply.reject )
			: Promise.resolve( reply );
	} );
	root = createRoot( document.getElementById( 'pqm-setup-root' ) );
	await act( async () => {
		root.render( <SetupIsland /> );
	} );
	await act( async () => {
		document.querySelector( '.pqm-setup > button' ).click();
	} );
}

const heading = () =>
	document.querySelector( '.pqm-setup-step h2' ).textContent;
const nav = () => document.querySelector( '.pqm-setup-steps' ).textContent;
const field = ( label ) =>
	document.querySelector( `input[aria-label="${ label }"]` );
const button = ( text ) =>
	[ ...document.querySelectorAll( '.pqm-setup-step button' ) ].find(
		( el ) => el.textContent === text
	);
const posts = () =>
	apiFetch.mock.calls
		.map( ( [ options ] ) => options )
		.filter( ( options ) => options.method === 'POST' );

const setupReads = () =>
	apiFetch.mock.calls.filter(
		( [ options ] ) => options.path === SETUP && ! options.method
	).length;
const navButton = ( text ) =>
	[ ...document.querySelectorAll( '.pqm-setup-nav button' ) ].find( ( el ) =>
		el.textContent.endsWith( text )
	);

/**
 * A reply of the n8n connection route that also changes what GET /setup
 * answers from then on, as storing or forgetting the key does on the server.
 *
 * @param {Object} next  GET /setup's answer afterwards.
 * @param {Object} reply The route's own reply (default: the passing key).
 * @return {Function} Reply function for `openDialog`'s answers.
 */
function storeKey( next, reply = PASSING ) {
	return () => {
		replies[ `GET ${ SETUP }` ] = next;
		return reply;
	};
}

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

/**
 * Submit the form that holds a field.
 *
 * @param {string} label Label of a field in the form.
 */
async function submitForm( label ) {
	await act( async () => {
		field( label )
			.closest( 'form' )
			.dispatchEvent(
				new window.Event( 'submit', {
					bubbles: true,
					cancelable: true,
				} )
			);
	} );
}

/** Type the Anthropic key and send it. */
async function provision() {
	await typeInto( 'Anthropic API key', PROVIDER_KEY );
	await submitForm( 'Anthropic API key' );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	act( () => root.unmount() );
	window.location.hash = '';
} );

describe( 'Setup dialog n8n steps (entry 6)', () => {
	test( 'a fresh site opens on Connect your n8n, the island inside, the key path listed', async () => {
		await openDialog( siteState() );
		expect( heading() ).toBe( 'Step 1 of 6: Connect your n8n' );
		expect( field( 'n8n address' ) ).not.toBeNull();
		expect( button( 'Set up by hand instead' ) ).toBeDefined();
		expect( nav() ).toContain( 'Set up the workflow' );
		expect( nav() ).not.toContain( 'Set up by hand' );
	} );

	test( 'the island passes: the state re-read, Connect your n8n is done, Next leads to Set up the workflow', async () => {
		await openDialog( siteState(), {
			[ `POST ${ CONNECTION }` ]: storeKey(
				siteState( {
					n8n: PASSING,
					steps: { ...siteState().steps, n8n: true },
				} )
			),
		} );
		await typeInto( 'n8n address', BASE );
		await typeInto( 'n8n API key', 'n8n-key' );
		await submitForm( 'n8n address' );
		expect( document.querySelector( '.pqm-setup-done' ).textContent ).toBe(
			'Done — connected to n8n.'
		);
		// A passing key outranks the manual choice: no longer offered.
		expect( button( 'Set up by hand instead' ) ).toBeUndefined();
		await act( async () => {
			button( 'Next' ).click();
		} );
		expect( heading() ).toBe( 'Step 2 of 6: Set up the workflow' );
	} );

	test( 'a key that passed earlier: the dialog opens on Set up the workflow', async () => {
		await openDialog( siteState( { n8n: PASSING } ) );
		expect( heading() ).toBe( 'Step 2 of 6: Set up the workflow' );
		expect( field( 'Anthropic API key' ).type ).toBe( 'password' );
	} );

	test( 'provision passes, ping ok: the key sent once, the field emptied, the status block swapped, the step done, then the first review', async () => {
		/* eslint-disable camelcase -- REST field names. */
		await openDialog( siteState( { n8n: PASSING } ), {
			[ `POST ${ PROVISION }` ]: {
				provisioned: true,
				ping_ok: true,
				workflow_id: 'wf1',
				message: 'Ping OK — up to date.',
				rewrite_ready: true,
				status_html: NEW_BLOCK,
			},
		} );
		await provision();
		expect( posts() ).toEqual( [
			{
				path: PROVISION,
				method: 'POST',
				data: { anthropic_key: PROVIDER_KEY },
			},
		] );
		/* eslint-enable camelcase */
		expect(
			document.querySelector( '.mock-notice-success' ).textContent
		).toBe( 'Ping OK — up to date.' );
		expect(
			document.querySelector( '.pqm-workflow-status' ).outerHTML
		).toBe( NEW_BLOCK );
		expect( document.body.innerHTML ).not.toContain( PROVIDER_KEY );
		expect( document.querySelector( '.pqm-setup-connect' ) ).toBeNull();
		expect( document.querySelector( '.pqm-setup-done' ).textContent ).toBe(
			'Done — the webhook URL is saved.'
		);
		await act( async () => {
			button( 'Next' ).click();
		} );
		expect( heading() ).toBe( 'Step 3 of 6: Run your first review' );
	} );

	test( 'provision passes, ping fails: the message, then the paste-URL body prefilled with the saved URL', async () => {
		await openDialog( siteState( { n8n: PASSING } ), {
			[ `POST ${ PROVISION }` ]: {
				provisioned: true,
				// eslint-disable-next-line camelcase -- REST field name.
				ping_ok: false,
				message:
					'Your workflow is set up, but the ping did not get through.',
			},
		} );
		await provision();
		expect(
			document.querySelector( '.mock-notice-warning' ).textContent
		).toBe( 'Your workflow is set up, but the ping did not get through.' );
		expect( field( 'Production webhook URL' ).value ).toBe(
			`${ BASE }/webhook/${ PATH }`
		);
		expect( document.body.innerHTML ).not.toContain( PROVIDER_KEY );
	} );

	test( 'Pro without Application Passwords: proceeds, the Rewrite message shown as a warning', async () => {
		await openDialog( siteState( { n8n: PASSING } ), {
			[ `POST ${ PROVISION }` ]: {
				provisioned: true,
				// eslint-disable-next-line camelcase -- REST field name.
				ping_ok: true,
				message: 'Ping OK. Rewrite stays off until …',
				// eslint-disable-next-line camelcase -- REST field name.
				rewrite_ready: false,
			},
		} );
		await provision();
		expect(
			document.querySelector( '.mock-notice-warning' ).textContent
		).toContain( 'Rewrite stays off' );
		expect( document.querySelector( '.mock-notice-success' ) ).toBeNull();
	} );

	test( 'provision refused: the REST message at the field, the key cleared, the step left open', async () => {
		await openDialog( siteState( { n8n: PASSING } ), {
			[ `POST ${ PROVISION }` ]: {
				reject: {
					code: 'pqm_provider_key_invalid',
					message: 'Anthropic refused this API key.',
				},
			},
		} );
		await provision();
		expect(
			document.querySelector( '.mock-notice-error' ).textContent
		).toBe( 'Anthropic refused this API key.' );
		expect( field( 'Anthropic API key' ).value ).toBe( '' );
		expect( document.body.innerHTML ).not.toContain( PROVIDER_KEY );
		expect( document.querySelector( '.pqm-setup-done' ).textContent ).toBe(
			'This step ticks itself once a test ping gets through.'
		);
		expect( heading() ).toBe( 'Step 2 of 6: Set up the workflow' );
	} );

	test( 'provisioned, URL missing: the paste-URL body instead of the key field', async () => {
		await openDialog( siteState( { n8n: PASSING, provisioned: true } ) );
		expect( heading() ).toBe( 'Step 2 of 6: Set up the workflow' );
		expect( field( 'Anthropic API key' ) ).toBeNull();
		expect( field( 'Production webhook URL' ) ).not.toBeNull();
	} );

	test( 'leftovers waiting: an info notice above the key field', async () => {
		await openDialog( siteState( { n8n: PASSING, leftovers: true } ) );
		expect(
			document.querySelector( '.mock-notice-info' ).textContent
		).toBe(
			'An earlier setup left a workflow or credentials in n8n. Setting up removes them first.'
		);
		expect( field( 'Anthropic API key' ) ).not.toBeNull();
	} );

	test( 'by hand, by choice: the choice stored, then the page reloads on the Workflow File section', async () => {
		await openDialog( siteState(), {
			[ `POST ${ SETUP }` ]: siteState( { manual: true } ),
		} );
		await act( async () => {
			button( 'Set up by hand instead' ).click();
		} );
		expect( posts() ).toEqual( [
			{ path: SETUP, method: 'POST', data: { manual: true } },
		] );
		expect( window.location.hash ).toBe( '#pqm-workflow-file' );
		expect( document.querySelector( '.mock-modal' ) ).toBeNull();
		// jsdom implements no reload: it reports the navigation as an error.
		expect( console ).toHaveErrored();
	} );

	test( 'by hand, the write fails: the cause in the dialog, nothing else', async () => {
		await openDialog( siteState(), {
			[ `POST ${ SETUP }` ]: { reject: { message: 'Could not save.' } },
		} );
		await act( async () => {
			button( 'Set up by hand instead' ).click();
		} );
		expect(
			document.querySelector( '.mock-notice-error' ).textContent
		).toBe( 'Could not save.' );
		expect( window.location.hash ).toBe( '' );
		expect( heading() ).toBe( 'Step 1 of 6: Connect your n8n' );
	} );

	test( 'a re-walk on the manual path opens on Set up by hand: the reason, the link, the paste-URL body, the key path collapsed', async () => {
		await openDialog(
			siteState( {
				manual: true,
				// eslint-disable-next-line camelcase -- REST field name.
				manual_reason: { kind: 'choice' },
			} )
		);
		expect( heading() ).toBe( 'Step 1 of 5: Set up by hand' );
		expect( nav() ).not.toContain( 'Connect your n8n' );
		expect(
			document.querySelector( '.mock-notice-info' ).textContent
		).toBe( 'You chose to set up n8n by hand.' );
		expect(
			document.querySelector(
				'.pqm-setup-step a[href="#pqm-workflow-file"]'
			).textContent
		).toBe( 'Workflow File' );
		expect( field( 'Production webhook URL' ) ).not.toBeNull();
		const details = document.querySelector( 'details.pqm-setup-by-key' );
		expect( details.open ).toBe( false );
		expect( details.querySelector( 'summary' ).textContent ).toBe(
			'Connect by key instead'
		);
		expect( details.querySelector( '.pqm-n8n-connection' ) ).not.toBeNull();
	} );

	test( 'a failed check that offered the manual path: its date and cause', async () => {
		await openDialog(
			siteState( {
				manual: true,
				// eslint-disable-next-line camelcase -- REST field name.
				manual_reason: {
					kind: 'probe',
					message: 'The public API is turned off on this instance.',
					at: 'October 14, 2026',
				},
			} )
		);
		expect(
			document.querySelector( '.mock-notice-info' ).textContent
		).toBe(
			'On October 14, 2026, the n8n Instance check did not pass: The public API is turned off on this instance.'
		);
	} );

	test( 'the key passes from the manual step, no URL saved: the key path comes back, on Set up the workflow', async () => {
		await openDialog(
			siteState( {
				manual: true,
				// eslint-disable-next-line camelcase -- REST field name.
				manual_reason: { kind: 'choice' },
			} ),
			{
				[ `POST ${ CONNECTION }` ]: storeKey(
					siteState( {
						n8n: PASSING,
						steps: { ...siteState().steps, n8n: true },
					} )
				),
			}
		);
		await typeInto( 'n8n address', BASE );
		await typeInto( 'n8n API key', 'n8n-key' );
		await submitForm( 'n8n address' );
		expect( heading() ).toBe( 'Step 2 of 6: Set up the workflow' );
		expect( nav() ).toContain( 'Connect your n8n' );
		expect( nav() ).not.toContain( 'Set up by hand' );
	} );

	test( 'the Workflow File link closes the dialog and brings the section into view', async () => {
		jest.useFakeTimers();
		const scroll = jest.fn();
		window.HTMLElement.prototype.scrollIntoView = scroll;
		await openDialog(
			siteState( {
				manual: true,
				// eslint-disable-next-line camelcase -- REST field name.
				manual_reason: { kind: 'choice' },
			} )
		);
		await act( async () => {
			document.querySelector( 'a[href="#pqm-workflow-file"]' ).click();
		} );
		expect( document.querySelector( '.mock-modal' ) ).toBeNull();
		act( () => jest.runAllTimers() );
		expect( scroll ).toHaveBeenCalledTimes( 1 );
		expect( document.activeElement.id ).toBe( 'pqm-workflow-file' );
		jest.useRealTimers();
	} );

	test( 'provisioned (setup map, URL, key passes): the key path, both n8n steps done, the dialog opens on the first review', async () => {
		await openDialog( provisionedSite() );
		expect( heading() ).toBe( 'Step 3 of 6: Run your first review' );
		expect( nav() ).toContain( 'Done: Connect your n8n' );
		expect( nav() ).toContain( 'Done: Set up the workflow' );
		expect( nav() ).not.toContain( 'Set up by hand' );
	} );

	test( 'provisioned, key forgotten: Connect your n8n open, Set up the workflow done, no "by hand" anywhere', async () => {
		await openDialog( provisionedSite( { n8n: FORGOTTEN } ) );
		expect( heading() ).toBe( 'Step 1 of 6: Connect your n8n' );
		expect( nav() ).not.toContain( 'Done: Connect your n8n' );
		expect( nav() ).toContain( 'Done: Set up the workflow' );
		expect( document.body.textContent ).not.toContain( 'by hand' );
	} );

	test( 'hand-made (URL, no setup map, no key): the manual path, Set up by hand done, its sentence, no "Connect by key instead"', async () => {
		await openDialog( handMadeSite() );
		expect( heading() ).toBe( 'Step 2 of 5: Run your first review' );
		expect( nav() ).toContain( 'Done: Set up by hand' );
		expect( nav() ).not.toContain( 'Connect your n8n' );
		expect( nav() ).not.toContain( 'Set up the workflow' );
		await act( async () => {
			navButton( 'Set up by hand' ).click();
		} );
		expect(
			document.querySelector( '.mock-notice-info' ).textContent
		).toBe( "This site's workflow was set up by hand." );
		expect(
			document.querySelector( 'details.pqm-setup-by-key' )
		).toBeNull();
		expect( field( 'Anthropic API key' ) ).toBeNull();
	} );

	test( 'hand-made, a passing key: still the manual path, no "Connect by key instead", no setup offered', async () => {
		await openDialog( handMadeSite( { n8n: PASSING } ) );
		expect( heading() ).toBe( 'Step 2 of 5: Run your first review' );
		expect( nav() ).not.toContain( 'Connect your n8n' );
		expect( nav() ).not.toContain( 'Set up the workflow' );
		await act( async () => {
			navButton( 'Set up by hand' ).click();
		} );
		expect(
			document.querySelector( '.mock-notice-info' ).textContent
		).toBe( "This site's workflow was set up by hand." );
		expect(
			document.querySelector( 'details.pqm-setup-by-key' )
		).toBeNull();
		expect( field( 'Anthropic API key' ) ).toBeNull();
		expect( button( 'Set up by hand instead' ) ).toBeUndefined();
	} );

	test( 'hand-made, complete: every n8n step done, the guide opens on the finish panel once the configuration steps are done', async () => {
		await openDialog(
			handMadeSite( {
				complete: true,
				steps: {
					...siteState().steps,
					manual: true,
					review: true,
					profile: true,
					standards: true,
					schedule: true,
				},
			} )
		);
		expect( heading() ).toBe( "You're set up" );
		expect( nav() ).toContain( 'Done: Set up by hand' );
		expect( nav() ).toContain( 'Done: Run your first review' );
		expect( nav() ).not.toContain( 'Connect your n8n' );
	} );

	test( 'hand-made, the ping after a by-hand paste failed: Set up by hand open, with the paste-URL body', async () => {
		await openDialog(
			handMadeSite( {
				steps: { ...siteState().steps, manual: false },
			} )
		);
		expect( heading() ).toBe( 'Step 1 of 5: Set up by hand' );
		expect( field( 'Production webhook URL' ).value ).toBe( HAND_URL );
		expect( document.querySelector( '.pqm-setup-done' ).textContent ).toBe(
			'This step ticks itself once a test ping gets through.'
		);
	} );

	test( 'Forget key in the dialog: the state re-read from GET /setup, the path and ticks follow it', async () => {
		await openDialog( provisionedSite(), {
			[ `DELETE ${ CONNECTION }` ]: storeKey(
				provisionedSite( { n8n: FORGOTTEN } ),
				FORGOTTEN
			),
		} );
		await act( async () => {
			navButton( 'Connect your n8n' ).click();
		} );
		expect( setupReads() ).toBe( 1 );
		await act( async () => {
			document.querySelector( '.pqm-n8n-forget' ).click();
		} );
		expect( setupReads() ).toBe( 2 );
		expect( heading() ).toBe( 'Step 1 of 6: Connect your n8n' );
		expect( nav() ).not.toContain( 'Done: Connect your n8n' );
		expect( nav() ).toContain( 'Done: Set up the workflow' );
		expect( document.querySelector( '.pqm-setup-done' ).textContent ).toBe(
			'This step ticks itself once the connection check passes.'
		);
	} );

	test( 'Forget key, the re-read fails: the load error in the dialog', async () => {
		await openDialog( provisionedSite(), {
			[ `DELETE ${ CONNECTION }` ]: storeKey(
				{ reject: { message: 'Could not reach the site.' } },
				FORGOTTEN
			),
		} );
		await act( async () => {
			navButton( 'Connect your n8n' ).click();
		} );
		await act( async () => {
			document.querySelector( '.pqm-n8n-forget' ).click();
		} );
		expect(
			document.querySelector( '.pqm-setup-step > .mock-notice-error' )
				.textContent
		).toBe( 'Could not reach the site.' );
	} );

	test( 'reopened after provisioning whose ping failed: Set up the workflow is still open, with the paste-URL body', async () => {
		/* eslint-disable camelcase -- REST field names. */
		await openDialog(
			siteState( {
				n8n: PASSING,
				provisioned: true,
				connected: true,
				webhook_url: `${ BASE }/webhook/${ PATH }`,
				steps: { ...siteState().steps, n8n: true },
			} )
		);
		/* eslint-enable camelcase */
		expect( heading() ).toBe( 'Step 2 of 6: Set up the workflow' );
		expect( field( 'Anthropic API key' ) ).toBeNull();
		expect( field( 'Production webhook URL' ).value ).toBe(
			`${ BASE }/webhook/${ PATH }`
		);
		expect( document.querySelector( '.pqm-setup-done' ).textContent ).toBe(
			'This step ticks itself once a test ping gets through.'
		);
	} );

	test( 'the Free build: the same n8n steps', async () => {
		await openDialog( siteState( { build: 'free' } ) );
		expect( heading() ).toBe( 'Step 1 of 5: Connect your n8n' );
		expect( nav() ).toContain( 'Set up the workflow' );
	} );
} );

describe( "The guide's button yields to the update notice's Re-import the workflow (plan-update-notice-primary-action)", () => {
	const REIMPORT_BLOCK =
		'<div class="pqm-workflow-status"><div class="notice notice-warning inline pqm-inline-notice"><p><strong>Workflow update available.</strong></p><p><a class="button button-primary pqm-reimport" href="#pqm-workflow-file">Re-import the workflow</a></p></div></div>';
	const guideVariant = () =>
		document.querySelector( '.pqm-setup > button' ).dataset.variant;

	/**
	 * Swap the page's status block as pqm-admin.js does (a test, window focus).
	 *
	 * @param {string} html The new block.
	 */
	async function swap( html ) {
		await act( async () => {
			document.querySelector( '.pqm-workflow-status' ).outerHTML = html;
			document.dispatchEvent(
				new window.CustomEvent( 'pqm:workflow-status' )
			);
		} );
	}

	/**
	 * Mount the island beside this status block, the dialog closed.
	 *
	 * @param {string}  block The page's status block.
	 * @param {Object=} state GET /setup state; none = still loading.
	 */
	async function mountBeside( block, state ) {
		document.body.innerHTML = `<div id="pqm-setup-root"></div>${ block }`;
		apiFetch.mockReset();
		apiFetch.mockImplementation( () =>
			state ? Promise.resolve( state ) : new Promise( () => {} )
		);
		root = createRoot( document.getElementById( 'pqm-setup-root' ) );
		await act( async () => {
			root.render( <SetupIsland /> );
		} );
	}

	test( 'setup not complete, the re-import button shown at mount: secondary', async () => {
		await mountBeside( REIMPORT_BLOCK, handMadeSite() );
		expect( guideVariant() ).toBe( 'secondary' );
		expect(
			document.querySelector( '.pqm-setup > button' ).textContent
		).toBe( 'Set up the workflow' );
	} );

	test( 'while GET /setup loads, the re-import button shown: already secondary', async () => {
		await mountBeside( REIMPORT_BLOCK );
		expect( guideVariant() ).toBe( 'secondary' );
	} );

	test( 'it flips after a swap that removes the button, and back', async () => {
		await mountBeside( REIMPORT_BLOCK, handMadeSite() );
		await swap( NEW_BLOCK );
		expect( guideVariant() ).toBe( 'primary' );
		await swap( REIMPORT_BLOCK );
		expect( guideVariant() ).toBe( 'secondary' );
	} );

	test( 'setup not complete, up to date: primary', async () => {
		await mountBeside( OLD_BLOCK, handMadeSite() );
		expect( guideVariant() ).toBe( 'primary' );
	} );

	test( "provisioning swaps in a block with the button: the guide's button follows", async () => {
		await openDialog( siteState( { n8n: PASSING } ), {
			[ `POST ${ PROVISION }` ]: {
				provisioned: true,
				// eslint-disable-next-line camelcase -- REST field name.
				ping_ok: true,
				message: 'Ping OK — older version.',
				// eslint-disable-next-line camelcase -- REST field name.
				status_html: REIMPORT_BLOCK,
			},
		} );
		expect( guideVariant() ).toBe( 'primary' );
		await provision();
		expect( guideVariant() ).toBe( 'secondary' );
	} );
} );
