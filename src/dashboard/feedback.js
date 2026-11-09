/**
 * Feedback card (story 79) — the last card of the Dashboard, collapsed to
 * one line. The user picks Feedback or Bug, writes a message, sees the
 * environment lines the server hands over in `GET /dashboard` and "Send by
 * email" opens the mail client on a mailto: to the support mailbox; Copy
 * puts the same text on the clipboard for a browser without a mail client.
 * The server sends nothing: the only request is the per-user Hide / Send
 * feedback toggle, POST /dashboard/feedback.
 *
 * The pure builders are exported for the Jest rows.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	RadioControl,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

export const FEEDBACK_PATH = '/post-quality/v1/dashboard/feedback';
export const SUPPORT_EMAIL = 'hello@postquality.com';

// Read by the maintainer, so not translated (like the environment lines).
const SUBJECTS = {
	feedback: 'Post Quality Manager: feedback',
	bug: 'Post Quality Manager: bug report',
};

/**
 * Subject and body of the email.
 *
 * @param {string}   kind        'feedback' or 'bug'.
 * @param {string}   message     What the user wrote.
 * @param {string[]} environment The lines from GET /dashboard.
 * @return {{subject: string, body: string}} The two parts.
 */
export function mailText( kind, message, environment ) {
	return {
		subject: SUBJECTS[ kind ] || SUBJECTS.feedback,
		body:
			( message || '' ).trim() +
			'\n\n---\n' +
			( environment || [] ).join( '\n' ),
	};
}

/**
 * The mailto: URL — subject and body percent-encoded, line breaks as %0A.
 *
 * @param {string}   kind        'feedback' or 'bug'.
 * @param {string}   message     What the user wrote.
 * @param {string[]} environment The lines from GET /dashboard.
 * @return {string} The URL.
 */
export function mailtoUrl( kind, message, environment ) {
	const { subject, body } = mailText( kind, message, environment );
	return (
		'mailto:' +
		SUPPORT_EMAIL +
		'?subject=' +
		encodeURIComponent( subject ) +
		'&body=' +
		encodeURIComponent( body )
	);
}

/**
 * What Copy puts on the clipboard: the Subject line, a blank line, the body.
 *
 * @param {string}   kind        'feedback' or 'bug'.
 * @param {string}   message     What the user wrote.
 * @param {string[]} environment The lines from GET /dashboard.
 * @return {string} The text.
 */
export function copyText( kind, message, environment ) {
	const { subject, body } = mailText( kind, message, environment );
	return 'Subject: ' + subject + '\n\n' + body;
}

/**
 * Puts the text on the clipboard. The promise settles once the browser has
 * answered, so the caller learns copied or refused from it alone. Without
 * the Clipboard API (an admin served over plain HTTP) it refuses, and the
 * card shows the text in a read-only field instead.
 *
 * @param {string} text The text to copy.
 * @return {Promise} Resolves when copied, rejects when refused.
 */
function copyToClipboard( text ) {
	const clipboard = window.navigator && window.navigator.clipboard;
	if ( ! clipboard || ! clipboard.writeText ) {
		return Promise.reject( new Error( 'No Clipboard API' ) );
	}
	return clipboard.writeText( text );
}

/**
 * @param {Object} props
 * @param {Object} props.feedback `GET /dashboard` → feedback: { dismissed, environment }.
 */
export default function FeedbackCard( { feedback } ) {
	const [ dismissed, setDismissed ] = useState(
		!! ( feedback && feedback.dismissed )
	);
	const [ open, setOpen ] = useState( false );
	const [ kind, setKind ] = useState( 'feedback' );
	const [ message, setMessage ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	// 'sent' | 'copied' | 'refused' | null — the line under the actions.
	const [ outcome, setOutcome ] = useState( null );
	const environment = ( feedback && feedback.environment ) || [];
	const ready = message.trim() !== '';
	// Hide and Send feedback swap the subtree that held the focused button;
	// focus moves to what replaces it (the link, or the open card's heading).
	const wrapRef = useRef( null );
	const focusAfterToggle = useRef( false );

	useEffect( () => {
		if ( ! focusAfterToggle.current || ! wrapRef.current ) {
			return;
		}
		focusAfterToggle.current = false;
		const target = wrapRef.current.querySelector(
			dismissed ? '.pqm-feedback-reopen button' : '.pqm-feedback-title'
		);
		if ( target ) {
			target.focus();
		}
	}, [ dismissed ] );

	const toggle = ( hide ) => {
		setBusy( true );
		setError( null );
		apiFetch( {
			path: FEEDBACK_PATH,
			method: 'POST',
			data: { dismissed: hide },
		} ).then(
			() => {
				setBusy( false );
				focusAfterToggle.current = true;
				setDismissed( hide );
				if ( ! hide ) {
					setOpen( true );
				}
			},
			() => {
				setBusy( false );
				setError(
					hide
						? __(
								'Could not hide the card. Try again in a moment.',
								'post-quality-manager'
							)
						: __(
								'Could not reopen the card. Try again in a moment.',
								'post-quality-manager'
							)
				);
			}
		);
	};

	const inlineError = error && (
		<Notice status="error" isDismissible={ false }>
			{ error }
		</Notice>
	);

	if ( dismissed ) {
		return (
			<div ref={ wrapRef } className="pqm-feedback-reopen">
				<Button
					variant="link"
					isBusy={ busy }
					disabled={ busy }
					onClick={ () => toggle( false ) }
				>
					{ __( 'Send feedback', 'post-quality-manager' ) }
				</Button>
				{ inlineError }
			</div>
		);
	}

	const title = (
		<h2 className="pqm-dash-heading pqm-feedback-title" tabIndex={ -1 }>
			{ __( 'Send feedback or report a bug', 'post-quality-manager' ) }
		</h2>
	);
	const hide = (
		<Button
			variant="tertiary"
			isBusy={ busy }
			disabled={ busy }
			onClick={ () => toggle( true ) }
		>
			{ __( 'Hide', 'post-quality-manager' ) }
		</Button>
	);

	if ( ! open ) {
		return (
			<Card className="pqm-dash-card pqm-feedback">
				<CardBody>
					<div className="pqm-feedback-row">
						{ title }
						<Button
							variant="secondary"
							onClick={ () => setOpen( true ) }
						>
							{ __( 'Write a message', 'post-quality-manager' ) }
						</Button>
						{ hide }
					</div>
					{ inlineError }
				</CardBody>
			</Card>
		);
	}

	let outcomeLine = null;
	if ( outcome === 'sent' ) {
		outcomeLine = sprintf(
			/* translators: %s: the support email address */
			__(
				'Your email app should open now. If nothing happens, copy the email and send it to %s.',
				'post-quality-manager'
			),
			SUPPORT_EMAIL
		);
	} else if ( outcome === 'copied' ) {
		outcomeLine = sprintf(
			/* translators: %s: the support email address */
			__(
				'Copied. Paste it into an email to %s.',
				'post-quality-manager'
			),
			SUPPORT_EMAIL
		);
	} else if ( outcome === 'refused' ) {
		outcomeLine = __(
			'This browser did not allow copying. Select the text below and copy it yourself.',
			'post-quality-manager'
		);
	}

	return (
		<div ref={ wrapRef }>
			<Card className="pqm-dash-card pqm-feedback">
				<CardHeader>
					<div className="pqm-feedback-row">
						{ title }
						{ hide }
					</div>
				</CardHeader>
				<CardBody>
					<RadioControl
						label={ __(
							'What is it about?',
							'post-quality-manager'
						) }
						selected={ kind }
						options={ [
							{
								label: __( 'Feedback', 'post-quality-manager' ),
								value: 'feedback',
							},
							{
								label: __( 'Bug', 'post-quality-manager' ),
								value: 'bug',
							},
						] }
						onChange={ ( value ) => {
							setKind( value );
							setOutcome( null );
						} }
					/>
					{ kind === 'bug' && (
						<p className="pqm-feedback-security">
							{ __(
								'A security problem? Email it, never post it in public.',
								'post-quality-manager'
							) }
						</p>
					) }
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Your message', 'post-quality-manager' ) }
						value={ message }
						rows={ 5 }
						onChange={ ( value ) => {
							setMessage( value );
							setOutcome( null );
						} }
					/>
					<p className="pqm-feedback-env-heading pqm-muted">
						{ __(
							'Added to your email so we can reproduce a problem:',
							'post-quality-manager'
						) }
					</p>
					<pre className="pqm-feedback-env">
						{ environment.join( '\n' ) }
					</pre>
					<div className="pqm-feedback-actions">
						<Button
							variant="primary"
							href={ mailtoUrl( kind, message, environment ) }
							disabled={ ! ready }
							onClick={ () => setOutcome( 'sent' ) }
						>
							{ __( 'Send by email', 'post-quality-manager' ) }
						</Button>
						<Button
							variant="secondary"
							disabled={ ! ready }
							onClick={ () =>
								copyToClipboard(
									copyText( kind, message, environment )
								).then(
									() => setOutcome( 'copied' ),
									() => setOutcome( 'refused' )
								)
							}
						>
							{ __( 'Copy the email', 'post-quality-manager' ) }
						</Button>
					</div>
					{ outcomeLine && (
						<p className="pqm-feedback-outcome">{ outcomeLine }</p>
					) }
					{ outcome === 'refused' && (
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'The email', 'post-quality-manager' ) }
							value={ copyText( kind, message, environment ) }
							readOnly
							rows={ 8 }
							onChange={ () => {} }
						/>
					) }
					{ inlineError }
				</CardBody>
			</Card>
		</div>
	);
}
