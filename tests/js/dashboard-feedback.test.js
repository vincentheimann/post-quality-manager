/**
 * The Dashboard's feedback card (story 79, src/dashboard/feedback.js) run
 * in jsdom: the pure builders (subject per kind, body = message + the
 * environment lines, the mailto: encoding), the disabled state on an empty
 * message, the Bug kind's security line and issue link, Copy and its
 * refusal, and the per-user Hide / Send feedback toggle — one POST each, the
 * only requests the card makes. The api-fetch, components and compose
 * packages are WordPress-provided externals, not installed here: all three
 * are mocked.
 */
import { act } from 'react';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import FeedbackCard, {
	FEEDBACK_PATH,
	copyText,
	mailText,
	mailtoUrl,
} from '../../src/dashboard/feedback';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock(
	'@wordpress/components',
	() => {
		const { forwardRef } = require( '@wordpress/element' );
		const box = ( name ) =>
			function Box( { className, children } ) {
				return (
					<div className={ `mock-${ name } ${ className || '' }` }>
						{ children }
					</div>
				);
			};
		return {
			// As the real Button: an anchor while it has an href and is
			// enabled, a disabled <button> otherwise; a forwardRef (Copy).
			Button: forwardRef(
				(
					{ children, isBusy, variant, href, disabled, ...props },
					ref
				) =>
					href && ! disabled ? (
						<a
							ref={ ref }
							data-variant={ variant }
							href={ href }
							{ ...props }
						>
							{ children }
						</a>
					) : (
						<button
							ref={ ref }
							data-variant={ variant }
							disabled={ disabled }
							{ ...props }
						>
							{ children }
						</button>
					)
			),
			Card: box( 'card' ),
			CardBody: box( 'card-body' ),
			CardHeader: box( 'card-header' ),
			Notice: ( { status, children } ) => (
				<div className={ 'mock-notice-' + status }>{ children }</div>
			),
			RadioControl: ( { label, selected, options, onChange } ) => (
				<fieldset aria-label={ label }>
					{ options.map( ( option ) => (
						<input
							key={ option.value }
							type="radio"
							aria-label={ option.label }
							checked={ selected === option.value }
							onChange={ () => onChange( option.value ) }
						/>
					) ) }
				</fieldset>
			),
			TextareaControl: ( { label, value, onChange, readOnly } ) => (
				<textarea
					aria-label={ label }
					value={ value }
					readOnly={ readOnly }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
			),
		};
	},
	{ virtual: true }
);
// The Clipboard API as browsers give it: writeText() settles in a later
// task, after the click has bubbled; `clipboardWorks` picks resolve or
// reject, and `hasClipboard` false removes the API (an admin over HTTP).
let clipboardWorks = true;
let hasClipboard = true;
const writeText = jest.fn(
	() =>
		new Promise( ( resolve, reject ) =>
			setTimeout( () =>
				clipboardWorks ? resolve() : reject( new Error( 'denied' ) )
			)
		)
);
Object.defineProperty( window.navigator, 'clipboard', {
	configurable: true,
	get: () => ( hasClipboard ? { writeText } : undefined ),
} );

const ENV = [
	'Post Quality Manager 3.9.0 (Pro)',
	'WordPress 6.8',
	'PHP 8.3.12',
	'Workflow version 7',
	'Theme: Twenty Twenty-Five',
];
const ENV_TEXT = ENV.join( '\n' );

describe( 'the pure builders', () => {
	test( 'subject per kind; unknown kinds read as feedback', () => {
		expect( mailText( 'feedback', 'x', ENV ).subject ).toBe(
			'Post Quality Manager: feedback'
		);
		expect( mailText( 'bug', 'x', ENV ).subject ).toBe(
			'Post Quality Manager: bug report'
		);
		expect( mailText( 'other', 'x', ENV ).subject ).toBe(
			'Post Quality Manager: feedback'
		);
	} );

	test( 'body = the trimmed message, a blank line, --- and the environment lines', () => {
		expect( mailText( 'feedback', '  Love the queue \n', ENV ).body ).toBe(
			'Love the queue\n\n---\n' + ENV_TEXT
		);
		expect( mailText( 'bug', 'x', [] ).body ).toBe( 'x\n\n---\n' );
	} );

	test( 'Feedback mail row: the matrix URL', () => {
		expect( mailtoUrl( 'feedback', 'Love the queue', ENV ) ).toBe(
			'mailto:hello@postquality.com?subject=Post%20Quality%20Manager%3A%20feedback&body=Love%20the%20queue%0A%0A---%0A' +
				encodeURIComponent( ENV_TEXT )
		);
	} );

	test( 'encoding: &, ?, #, = and + never split the URL; line breaks are %0A; non-ASCII is UTF-8 percent-encoded', () => {
		const url = mailtoUrl( 'bug', 'a&b?c#d=e+f\nété', [ 'L1' ] );
		const [ , query ] = url.split( '?' );
		const params = new URLSearchParams( query );
		expect(
			url.startsWith( 'mailto:hello@postquality.com?subject=' )
		).toBe( true );
		expect( url.split( '?' ).length ).toBe( 2 );
		expect( url ).toContain( 'a%26b%3Fc%23d%3De%2Bf%0A%C3%A9t%C3%A9' );
		expect( params.get( 'body' ) ).toBe( 'a&b?c#d=e+f\nété\n\n---\nL1' );
		expect( params.get( 'subject' ) ).toBe(
			'Post Quality Manager: bug report'
		);
	} );

	test( 'Copy row: the Subject line, a blank line, the body as in the mailto', () => {
		expect( copyText( 'feedback', 'Love the queue', ENV ) ).toBe(
			'Subject: Post Quality Manager: feedback\n\nLove the queue\n\n---\n' +
				ENV_TEXT
		);
	} );
} );

let root;

/**
 * Mount the card with the given `GET /dashboard` feedback value.
 *
 * @param {Object} feedback { dismissed, environment }.
 */
async function mount( feedback = { dismissed: false, environment: ENV } ) {
	document.body.innerHTML = '<div id="root"></div>';
	apiFetch.mockReset();
	root = createRoot( document.getElementById( 'root' ) );
	await act( async () => {
		root.render( <FeedbackCard feedback={ feedback } /> );
	} );
}

const $ = ( selector ) => document.querySelector( selector );
const text = () => document.body.textContent;
const buttonNamed = ( label ) =>
	Array.from( document.querySelectorAll( 'button, a' ) ).find(
		( el ) => el.textContent === label
	);
const click = async ( label ) =>
	act( async () => {
		buttonNamed( label ).click();
	} );
// Lets the clipboard's later task and its state update run.
const settle = async () =>
	act( async () => {
		await new Promise( ( resolve ) => setTimeout( resolve, 5 ) );
	} );

/**
 * Type into the mocked TextareaControl (React's own value setter, then input).
 *
 * @param {string} value What to type.
 */
async function type( value ) {
	const field = $( 'textarea[aria-label="Your message"]' );
	const setter = Object.getOwnPropertyDescriptor(
		window.HTMLTextAreaElement.prototype,
		'value'
	).set;
	await act( async () => {
		setter.call( field, value );
		field.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	} );
}

beforeAll( () => {
	global.IS_REACT_ACT_ENVIRONMENT = true;
} );

beforeEach( () => {
	clipboardWorks = true;
	hasClipboard = true;
	writeText.mockClear();
} );

afterEach( () => {
	if ( root ) {
		act( () => root.unmount() );
		root = null;
	}
} );

describe( 'the card', () => {
	test( 'Collapsed: one line, Write a message and Hide; no request', async () => {
		await mount();
		expect( $( '.mock-card' ) ).not.toBeNull();
		expect( text() ).toContain( 'Send feedback or report a bug' );
		expect( buttonNamed( 'Write a message' ) ).toBeDefined();
		expect( buttonNamed( 'Hide' ) ).toBeDefined();
		expect( $( 'textarea' ) ).toBeNull();
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test( 'Empty message: Send by email and Copy disabled, on whitespace too; a message enables both and the mailto carries it', async () => {
		await mount();
		await click( 'Write a message' );
		expect( text() ).toContain(
			'Added to your email so we can reproduce a problem:'
		);
		expect( $( '.pqm-feedback-env' ).textContent ).toBe( ENV_TEXT );
		expect( buttonNamed( 'Send by email' ).disabled ).toBe( true );
		expect( buttonNamed( 'Copy the email' ).disabled ).toBe( true );
		await type( '   \n ' );
		expect( buttonNamed( 'Send by email' ).disabled ).toBe( true );
		await type( 'Love the queue' );
		const send = buttonNamed( 'Send by email' );
		expect( send.tagName ).toBe( 'A' );
		expect( send.getAttribute( 'href' ) ).toBe(
			mailtoUrl( 'feedback', 'Love the queue', ENV )
		);
		expect( send.getAttribute( 'target' ) ).toBeNull();
		expect( buttonNamed( 'Copy the email' ).disabled ).toBe( false );
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test( 'Bug kind: the bug subject, the security sentence and the issue link; Feedback has none of them', async () => {
		await mount();
		await click( 'Write a message' );
		expect( text() ).not.toContain( 'A security problem?' );
		expect( text() ).not.toContain( 'open an issue on GitHub' );
		expect( $( 'a[href*="github.com"]' ) ).toBeNull();
		await act( async () => {
			$( 'input[aria-label="Bug"]' ).click();
		} );
		expect( $( '.pqm-feedback-security' ).textContent ).toBe(
			'A security problem? Email it, never post it in public. For anything else, you can also open an issue on GitHub.'
		);
		const issue = buttonNamed( 'open an issue on GitHub' );
		expect( issue.tagName ).toBe( 'A' );
		expect( issue.getAttribute( 'href' ) ).toBe(
			'https://github.com/vincentheimann/post-quality-manager/issues/new/choose'
		);
		expect( issue.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( issue.getAttribute( 'rel' ) ).toBe( 'noreferrer' );
		await type( 'The queue is empty' );
		expect( buttonNamed( 'Send by email' ).getAttribute( 'href' ) ).toBe(
			mailtoUrl( 'bug', 'The queue is empty', ENV )
		);
	} );

	test( 'Send by email: the line about the mail app; Copy: copied', async () => {
		await mount();
		await click( 'Write a message' );
		await type( 'Love the queue' );
		const send = buttonNamed( 'Send by email' );
		// jsdom cannot open a mailto: (it logs "Not implemented: navigation");
		// the browser's default action is not what this row tests.
		send.addEventListener( 'click', ( event ) => event.preventDefault() );
		await act( async () => {
			send.dispatchEvent(
				new window.MouseEvent( 'click', {
					bubbles: true,
					cancelable: true,
				} )
			);
		} );
		expect( text() ).toContain(
			'If nothing happens, copy the email and send it to hello@postquality.com.'
		);
		await click( 'Copy the email' );
		// Nothing claimed before the clipboard answers.
		expect( text() ).not.toContain( 'did not allow copying' );
		await settle();
		expect( writeText ).toHaveBeenCalledWith(
			copyText( 'feedback', 'Love the queue', ENV )
		);
		expect( text() ).toContain(
			'Copied. Paste it into an email to hello@postquality.com.'
		);
		expect( $( 'textarea[readonly]' ) ).toBeNull();
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	test( 'Copy refused: the exact text stays selectable in a read-only field', async () => {
		clipboardWorks = false;
		await mount();
		await click( 'Write a message' );
		await type( 'Love the queue' );
		await click( 'Copy the email' );
		await settle();
		expect( text() ).toContain( 'did not allow copying' );
		expect( $( 'textarea[readonly]' ).value ).toBe(
			copyText( 'feedback', 'Love the queue', ENV )
		);
	} );

	test( 'Copy after a copy: a later refusal is shown, never a stale Copied', async () => {
		await mount();
		await click( 'Write a message' );
		await type( 'Love the queue' );
		await click( 'Copy the email' );
		await settle();
		expect( text() ).toContain( 'Copied.' );
		clipboardWorks = false;
		await click( 'Copy the email' );
		await settle();
		expect( text() ).toContain( 'did not allow copying' );
		expect( text() ).not.toContain( 'Copied.' );
	} );

	test( 'No Clipboard API (an admin over HTTP): refused, the text in a read-only field', async () => {
		hasClipboard = false;
		await mount();
		await click( 'Write a message' );
		await type( 'Love the queue' );
		await click( 'Copy the email' );
		await settle();
		expect( text() ).toContain( 'did not allow copying' );
		expect( $( 'textarea[readonly]' ) ).not.toBeNull();
	} );

	test( 'Hide: one POST {dismissed: true}; the card becomes the Send feedback link; reopen: one POST {dismissed: false}, the card open', async () => {
		await mount();
		apiFetch.mockResolvedValueOnce( { dismissed: true } );
		await click( 'Hide' );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: FEEDBACK_PATH,
			method: 'POST',
			data: { dismissed: true },
		} );
		expect( $( '.mock-card' ) ).toBeNull();
		expect( $( '.pqm-feedback-reopen' ) ).not.toBeNull();
		expect( buttonNamed( 'Send feedback' ) ).toBeDefined();
		// The focused Hide button is gone: focus is on the link that replaced it.
		expect( document.activeElement ).toBe( buttonNamed( 'Send feedback' ) );

		apiFetch.mockResolvedValueOnce( { dismissed: false } );
		await click( 'Send feedback' );
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		expect( apiFetch ).toHaveBeenLastCalledWith( {
			path: FEEDBACK_PATH,
			method: 'POST',
			data: { dismissed: false },
		} );
		expect( $( '.mock-card' ) ).not.toBeNull();
		expect( $( 'textarea[aria-label="Your message"]' ) ).not.toBeNull();
		expect( document.activeElement ).toBe( $( '.pqm-feedback-title' ) );
	} );

	test( 'Reopen fails: the link stays, an inline notice says so, outside any paragraph', async () => {
		await mount( { dismissed: true, environment: ENV } );
		apiFetch.mockRejectedValueOnce( new Error( 'offline' ) );
		await click( 'Send feedback' );
		expect( $( '.mock-card' ) ).toBeNull();
		expect( buttonNamed( 'Send feedback' ) ).toBeDefined();
		expect( $( '.mock-notice-error' ).textContent ).toContain(
			'Could not reopen the card.'
		);
		expect( $( 'p .mock-notice-error' ) ).toBeNull();
	} );

	test( 'Hide fails: the card stays, an inline notice says so', async () => {
		await mount();
		apiFetch.mockRejectedValueOnce( new Error( 'offline' ) );
		await click( 'Hide' );
		expect( $( '.mock-card' ) ).not.toBeNull();
		expect( $( '.mock-notice-error' ).textContent ).toContain(
			'Could not hide the card.'
		);
	} );

	test( 'Dismissed on load: only the Send feedback link, no card, no request', async () => {
		await mount( { dismissed: true, environment: ENV } );
		expect( $( '.mock-card' ) ).toBeNull();
		expect( buttonNamed( 'Send feedback' ) ).toBeDefined();
		expect( apiFetch ).not.toHaveBeenCalled();
	} );
} );
