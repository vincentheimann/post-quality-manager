/**
 * Story 55: the setup dialog's configuration steps, run in jsdom — the
 * whole island (src/setup/index.js) on a completed site, so resume, the
 * Skip footer and the step bodies meet as in the browser. Skip ticks the
 * step and writes no setting; a step shows the stored choice (a preset
 * read-only, Custom from the stored text, never from a preset); a preset
 * pick saves its key alone. The WordPress-provided externals (api-fetch,
 * components, url) are not installed here: they are mocked.
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
			variant,
			__next40pxDefaultSize,
			...props
		} ) => <button { ...props }>{ children }</button>,
		CheckboxControl: () => null,
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
		RadioControl: ( { label, selected, options, onChange } ) => (
			<fieldset aria-label={ label }>
				{ options.map( ( option ) => (
					<input
						key={ option.value }
						type="radio"
						value={ option.value }
						checked={ option.value === selected }
						onChange={ () => onChange( option.value ) }
					/>
				) ) }
			</fieldset>
		),
		SelectControl: () => null,
		Spinner: () => null,
		TextareaControl: ( { value, onChange } ) => (
			<textarea
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		),
		VisuallyHidden: ( { children } ) => <span>{ children }</span>,
	} ),
	{ virtual: true }
);

const SETUP = '/post-quality/v1/setup';
const SETTINGS = '/post-quality/v1/setup/settings';
const FOOD_TEXT = 'A food blog for home cooks.';

/**
 * GET /setup on a completed site the plugin set up (the setup map, a
 * passing key: the key path) whose configuration steps are open.
 *
 * @param {Object} values Stored settings the steps show.
 * @return {Object} Setup state.
 */
function completedState( values = {} ) {
	/* eslint-disable camelcase -- REST field names. */
	return {
		complete: true,
		connected: true,
		steps: {
			n8n: true,
			provision: true,
			manual: true,
			review: true,
			profile: false,
			standards: false,
			schedule: false,
		},
		n8n: {
			base_url: 'https://n8n.example.com',
			key_set: true,
			probe: { ok: true, cause: '', message: 'Connected to n8n.', at: 1 },
			connected: true,
		},
		manual: false,
		manual_reason: null,
		provisioned: true,
		leftovers: false,
		blog_profile: 'custom',
		website_context: '',
		brand_voice_preset: 'custom',
		brand_voice: '',
		profiles: [
			{ value: 'marketing', label: 'Marketing Blog', text: 'Marketing.' },
			{ value: 'food', label: 'Food & Recipes Blog', text: FOOD_TEXT },
			{ value: 'custom', label: 'Custom', text: '' },
		],
		voice_presets: [
			{ value: 'casual', label: 'Casual', text: 'Casual.' },
			{ value: 'custom', label: 'Custom', text: '' },
		],
		cron_enabled: false,
		cron_frequency: 'daily',
		cron_batch_size: 5,
		cron_min_score: 60,
		cron_max_age_days: 30,
		cron_command: 'review',
		can_manage_agents: true,
		is_pro: false,
		...values,
	};
	/* eslint-enable camelcase */
}

let root;

/**
 * Mount the island with this GET /setup state, then open the dialog with
 * its button (it resumes at the first unfinished step). POSTs answer the
 * state with the posted step ticked; with `settingsError`, POST
 * /setup/settings rejects with it.
 *
 * @param {Object}  state         GET /setup state.
 * @param {Object=} settingsError Rejection of POST /setup/settings.
 */
async function openDialog( state, settingsError ) {
	// The page's status block holds the button that opens the dialog (story 67).
	document.body.innerHTML =
		'<div id="pqm-setup-root"></div><button type="button" class="pqm-open-setup">Setup guide</button>';
	apiFetch.mockReset();
	apiFetch.mockImplementation( ( { path, method, data } ) => {
		if ( method !== 'POST' ) {
			return Promise.resolve( state );
		}
		if ( path === SETTINGS ) {
			return settingsError
				? Promise.reject( settingsError )
				: Promise.resolve( { ...state, ...data } );
		}
		return Promise.resolve( {
			...state,
			steps: { ...state.steps, [ data.step ]: data.done },
		} );
	} );
	root = createRoot( document.getElementById( 'pqm-setup-root' ) );
	await act( async () => {
		root.render( <SetupIsland /> );
	} );
	await act( async () => {
		document.querySelector( '.pqm-open-setup' ).click();
	} );
}

const heading = () =>
	document.querySelector( '.pqm-setup-step h2' ).textContent;
const button = ( text ) =>
	[ ...document.querySelectorAll( '.pqm-setup-step button' ) ].find(
		( el ) => el.textContent === text
	);
const posts = () =>
	apiFetch.mock.calls
		.map( ( [ options ] ) => options )
		.filter( ( options ) => options.method === 'POST' );

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	act( () => root.unmount() );
} );

describe( 'Setup dialog configuration steps', () => {
	test( 'a completed site resumes at the open Blog Profile step', async () => {
		await openDialog( completedState() );
		expect( heading() ).toBe( 'Step 4 of 6: Choose your Blog Profile' );
	} );

	test( 'Skip ticks the step, writes no setting and moves on', async () => {
		await openDialog( completedState() );
		await act( async () => {
			button( 'Skip' ).click();
		} );
		expect( posts() ).toEqual( [
			{
				path: SETUP,
				method: 'POST',
				data: { step: 'profile', done: true },
			},
		] );
		expect( heading() ).toBe( 'Step 5 of 6: Set your Editorial Standards' );
	} );

	test( 'a stored preset is shown selected, its text read-only', async () => {
		// eslint-disable-next-line camelcase -- REST field name.
		await openDialog( completedState( { blog_profile: 'food' } ) );
		expect( document.querySelector( 'input[value="food"]' ).checked ).toBe(
			true
		);
		expect(
			document.querySelector( '.pqm-setup-preset' ).textContent
		).toBe( FOOD_TEXT );
		expect( document.querySelector( 'textarea' ) ).toBeNull();
	} );

	test( 'Custom starts from the stored custom text, never from a preset', async () => {
		await openDialog(
			// eslint-disable-next-line camelcase -- REST field name.
			completedState( { blog_profile: 'food', website_context: 'Mine.' } )
		);
		await act( async () => {
			document.querySelector( 'input[value="custom"]' ).click();
		} );
		expect( document.querySelector( 'textarea' ).value ).toBe( 'Mine.' );
		expect( document.querySelector( '.pqm-setup-preset' ) ).toBeNull();
	} );

	test( 'a preset pick saves its key alone, then ticks the step', async () => {
		await openDialog( completedState() );
		await act( async () => {
			document.querySelector( 'input[value="marketing"]' ).click();
		} );
		await act( async () => {
			button( 'Save and continue' ).click();
		} );
		expect( posts() ).toEqual( [
			{
				path: SETTINGS,
				method: 'POST',
				// eslint-disable-next-line camelcase -- REST field name.
				data: { blog_profile: 'marketing' },
			},
			{
				path: SETUP,
				method: 'POST',
				data: { step: 'profile', done: true },
			},
		] );
		expect( heading() ).toBe( 'Step 5 of 6: Set your Editorial Standards' );
	} );

	test( 'a free site: the schedule step names Pro and offers no switch', async () => {
		await openDialog(
			completedState( {
				steps: {
					...completedState().steps,
					profile: true,
					standards: true,
				},
			} )
		);
		expect( heading() ).toBe( 'Step 6 of 6: Turn on scheduled reviews' );
		expect(
			document.querySelector( '.mock-notice-info' ).textContent
		).toContain( 'available with Pro' );
		expect( button( 'Turn on scheduled reviews' ) ).toBeUndefined();
	} );

	test( 'the Free build (story 54): the same n8n steps, no schedule step', async () => {
		await openDialog(
			completedState( {
				build: 'free',
				// A schedule tick left by a former Pro install is ignored.
				steps: {
					...completedState().steps,
					profile: true,
					schedule: true,
				},
			} )
		);
		expect( heading() ).toBe( 'Step 5 of 5: Set your Editorial Standards' );
		const list = document.querySelector( '.pqm-setup-steps' ).textContent;
		expect( list ).toContain( 'Connect your n8n' );
		expect( list ).toContain( 'Set up the workflow' );
		expect( list ).not.toContain( 'scheduled reviews' );
	} );

	test( 'a failed save names the cause, leaves the step open and ticks nothing', async () => {
		await openDialog( completedState(), { message: 'X' } );
		await act( async () => {
			document.querySelector( 'input[value="marketing"]' ).click();
		} );
		await act( async () => {
			button( 'Save and continue' ).click();
		} );
		expect(
			document.querySelector( '.mock-notice-error' ).textContent
		).toBe( 'X' );
		expect( posts().map( ( options ) => options.path ) ).toEqual( [
			SETTINGS,
		] );
		expect( heading() ).toBe( 'Step 4 of 6: Choose your Blog Profile' );
		expect( button( 'Skip' ) ).toBeDefined();
	} );

	test( 'no postquality_manage_agents, even on a pro site: the schedule step explains, offers no switch', async () => {
		await openDialog(
			completedState( {
				// eslint-disable-next-line camelcase -- REST field name.
				can_manage_agents: false,
				// eslint-disable-next-line camelcase -- REST field name.
				is_pro: true,
				steps: {
					...completedState().steps,
					profile: true,
					standards: true,
				},
			} )
		);
		expect( heading() ).toBe( 'Step 6 of 6: Turn on scheduled reviews' );
		expect(
			document.querySelector( '.mock-notice-info' ).textContent
		).toContain( 'your role does not include' );
		expect( button( 'Turn on scheduled reviews' ) ).toBeUndefined();
	} );
} );
