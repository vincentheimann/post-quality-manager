/**
 * Story 54 (ADR 0014): the React pages take their command list from the
 * registry labels PHP prints before the bundle (window.postqualityCommandLabels),
 * so the Free build — whose registry has no paid command — renders no paid
 * pill, tab or button. The modules read the print at import, so each case
 * loads them in isolation after setting it.
 */
// eslint-disable-next-line import/no-extraneous-dependencies -- React comes with @wordpress/element (wp-scripts); act is not re-exported there.
import { act } from 'react';
import { createRoot } from '@wordpress/element';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );

const ALL = [ 'review', 'takeaways', 'sources', 'seo', 'audit', 'rewrite' ];
const FREE_LABELS = {
	review: 'Review',
	takeaways: 'Takeaways',
	score: 'Score',
	apply: 'Apply',
};
const viewer = {
	can_trigger: true, // eslint-disable-line camelcase -- REST field name.
	hidden_commands: [], // eslint-disable-line camelcase -- REST field name.
	gated_commands: [], // eslint-disable-line camelcase -- REST field name.
};

/**
 * Load the kit modules fresh with this label print.
 *
 * @param {Object|undefined} labels window.postqualityCommandLabels, or none.
 * @return {Object} { COMMAND_ORDER, canRender, ProgressPills }.
 */
function load( labels ) {
	window.postqualityCommandLabels = labels;
	let kit;
	jest.isolateModules( () => {
		const copy = require( '../../src/kit/copy' );
		const runs = require( '../../src/kit/runs' );
		const pills = require( '../../src/kit/progress-pills' );
		kit = {
			COMMAND_ORDER: copy.COMMAND_ORDER,
			canRender: runs.canRender,
			ProgressPills: pills.default,
		};
	} );
	return kit;
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterEach( () => {
	delete window.postqualityCommandLabels;
	document.body.innerHTML = '';
} );

describe( 'Command list from the registry print', () => {
	test( 'Free build labels: review and takeaways only, no button for a paid command', () => {
		const { COMMAND_ORDER, canRender } = load( FREE_LABELS );
		expect( COMMAND_ORDER ).toEqual( [ 'review', 'takeaways' ] );
		expect( canRender( viewer, 'review' ) ).toBe( true );
		expect( canRender( viewer, 'rewrite' ) ).toBe( false );
		expect( canRender( viewer, 'sources' ) ).toBe( false );
	} );

	test( 'Pro build labels: all six, in order', () => {
		const labels = Object.fromEntries( ALL.map( ( c ) => [ c, c ] ) );
		const { COMMAND_ORDER, canRender } = load( labels );
		expect( COMMAND_ORDER ).toEqual( ALL );
		expect( canRender( viewer, 'rewrite' ) ).toBe( true );
	} );

	test( 'no print at all (tests): all six', () => {
		expect( load( undefined ).COMMAND_ORDER ).toEqual( ALL );
	} );

	test( 'Free build: the progress pills show the two free commands', async () => {
		const { ProgressPills } = load( FREE_LABELS );
		document.body.innerHTML = '<div id="root"></div>';
		const root = createRoot( document.getElementById( 'root' ) );
		await act( async () => {
			root.render( <ProgressPills commands={ [ 'review' ] } /> );
		} );
		const pills = [ ...document.querySelectorAll( '.pqm-progress-pill' ) ];
		expect( pills.map( ( el ) => el.textContent ) ).toEqual( [
			'✓ Review',
			'Takeaways',
		] );
		act( () => root.unmount() );
	} );
} );
