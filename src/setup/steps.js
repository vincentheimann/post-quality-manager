/**
 * Setup steps (story 36; n8n provisioning, entry 6). Two paths lead to the
 * first review and the three configuration steps (story 55,
 * config-steps.js):
 * - the key path: Connect your n8n (the n8n Instance island, reused), then
 *   Set up the workflow (the Anthropic key, sent once to POST
 *   /n8n/provision and never kept);
 * - the manual path, once the owner chose it, a connection check offered
 *   it, or the saved workflow was made by hand (`state.manual`, GET
 *   /setup's rule): one step, Set up by hand. It
 *   links to the Workflow File section of the n8n Workflow page, the one
 *   place for the manual steps, and keeps the paste-URL body so the setup
 *   can still finish.
 * Free and Pro run the same n8n steps; the Free build has no schedule step.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, TextControl } from '@wordpress/components';
import { createInterpolateElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { WORKFLOW_STATUS_EVENT } from '../kit/use-reimport-shown';
import N8nConnection from '../n8n-connection';
import { CONFIG_STEPS } from './config-steps';

const CONNECT_PATH = '/post-quality/v1/setup/connect';
const PROVISION_PATH = '/post-quality/v1/n8n/provision';

/** The Workflow File heading on the n8n Workflow page (PQM_Admin::render_workflow()). */
export const WORKFLOW_FILE_ID = 'pqm-workflow-file';

/**
 * Whether the stored n8n key passed its last check (the island's state).
 *
 * @param {Object} state GET /setup state.
 * @return {boolean} Passing.
 */
export function keyPasses( state ) {
	return !! ( state.n8n && state.n8n.key_set && state.n8n.probe );
}

/**
 * Swap the page's status block, which sits outside the island, for the
 * one a ping refreshed, then tell the islands (useReimportShown()).
 *
 * @param {string=} html The reply's `status_html`.
 */
function swapStatusBlock( html ) {
	const block = document.querySelector( '.pqm-workflow-status' );
	if ( block && html ) {
		block.outerHTML = html;
		document.dispatchEvent(
			new window.CustomEvent( WORKFLOW_STATUS_EVENT )
		);
	}
}

/**
 * Save the webhook URL and ping it (POST /setup/connect).
 *
 * @param {Object}   props
 * @param {Object}   props.state       GET /setup state.
 * @param {Function} props.onConnected `( url, pingOk )` once the URL is stored.
 * @return {Element} The connect form and its outcome.
 */
export function Connect( { state, onConnected } ) {
	const [ url, setUrl ] = useState( state.webhook_url || '' );
	const [ busy, setBusy ] = useState( false );
	const [ outcome, setOutcome ] = useState( null ); // { status, message }

	const submit = ( event ) => {
		event.preventDefault();
		setBusy( true );
		setOutcome( null );
		apiFetch( {
			path: CONNECT_PATH,
			method: 'POST',
			// eslint-disable-next-line camelcase -- REST field name.
			data: { webhook_url: url.trim() },
		} ).then(
			( data ) => {
				setBusy( false );
				// An older workflow answered: connected (the step ticks),
				// but never shown as a success.
				let status = 'success';
				if ( ! data.ping_ok ) {
					status = 'error';
				} else if ( data.outdated ) {
					status = 'warning';
				}
				setOutcome( { status, message: data.message } );
				swapStatusBlock( data.status_html );
				if ( data.saved ) {
					onConnected( url.trim(), !! data.ping_ok );
				}
			},
			( err ) => {
				setBusy( false );
				setOutcome( {
					status: 'error',
					message:
						( err && err.message ) ||
						__(
							'Could not reach the site REST API. Reload the page and try again.',
							'post-quality-manager'
						),
				} );
			}
		);
	};

	return (
		<>
			<p>
				{ __(
					'Paste the production URL and save. The plugin stores it and sends a test ping.',
					'post-quality-manager'
				) }
			</p>
			<form className="pqm-setup-connect" onSubmit={ submit }>
				<TextControl
					label={ __(
						'Production webhook URL',
						'post-quality-manager'
					) }
					type="url"
					value={ url }
					onChange={ setUrl }
					placeholder={ `https://your-n8n.example.com/webhook/${ state.webhook_path }` }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					type="submit"
					isBusy={ busy }
					disabled={ busy || ! url.trim() }
				>
					{ __( 'Save and test', 'post-quality-manager' ) }
				</Button>
			</form>
			{ outcome && (
				<Notice status={ outcome.status } isDismissible={ false }>
					{ outcome.message }
				</Notice>
			) }
			<p className="pqm-muted">
				{ __(
					'A ping answered by an up-to-date workflow proves this site reaches n8n and the webhook credential is right. Your first review confirms the way back.',
					'post-quality-manager'
				) }
			</p>
		</>
	);
}

/**
 * Connect your n8n: the n8n Instance island, which tells the dialog its
 * state, and the way to the manual path while the step is open (once the
 * key passes, it outranks the manual choice).
 *
 * @param {Object}   props
 * @param {Object}   props.state       GET /setup state.
 * @param {Function} props.onN8nChange The island's `onChange`.
 * @param {Function} props.onByHand    Stores the manual choice and opens the Workflow File section.
 * @param {boolean}  props.saving      Whether a progress save is in flight.
 * @return {Element} The step body.
 */
function ConnectN8n( { state, onN8nChange, onByHand, saving } ) {
	const done = !! state.connected || keyPasses( state );
	return (
		<>
			<p>
				{ __(
					'The plugin sets the workflow up in your n8n for you: the credentials, the workflow and its publication. It needs three values: your n8n address, an n8n API key with an expiration date, and an Anthropic API key in the next step.',
					'post-quality-manager'
				) }
			</p>
			<p className="pqm-muted">
				{ __(
					"No n8n yet? Sign up for n8n Cloud at n8n.io, or run n8n on your own server. Both sides must reach each other: this site calls n8n, and n8n calls back this site's REST API, so n8n Cloud cannot reach a local or password-protected site.",
					'post-quality-manager'
				) }
			</p>
			<N8nConnection onChange={ onN8nChange } />
			{ ! done && (
				<p>
					<Button
						variant="link"
						className="pqm-setup-by-hand"
						onClick={ onByHand }
						disabled={ saving }
					>
						{ __(
							'Set up by hand instead',
							'post-quality-manager'
						) }
					</Button>
				</p>
			) }
		</>
	);
}

/**
 * Set up the workflow: the Anthropic key, typed once and sent to POST
 * /n8n/provision. The field empties after the request, whatever its
 * answer; the key is kept nowhere else. A workflow set up whose ping
 * failed (now, or on an earlier visit: the step stays open), or a setup
 * map without a saved URL, leads to the paste-URL body.
 *
 * @param {Object}   props
 * @param {Object}   props.state         GET /setup state.
 * @param {Function} props.onProvisioned `( pingOk )` once the workflow is set up.
 * @param {Function} props.onConnected   Connect's `onConnected`.
 * @return {Element} The step body.
 */
function Provision( { state, onProvisioned, onConnected } ) {
	const [ key, setKey ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ outcome, setOutcome ] = useState( null ); // { status, message, pingOk }

	if ( outcome ) {
		return (
			<>
				<Notice status={ outcome.status } isDismissible={ false }>
					{ outcome.message }
				</Notice>
				{ ! outcome.pingOk && (
					<Connect state={ state } onConnected={ onConnected } />
				) }
			</>
		);
	}

	if ( state.connected && state.steps.provision ) {
		return (
			<p>
				{ __(
					'A webhook URL is saved on this site. Test it any time on the n8n Workflow page.',
					'post-quality-manager'
				) }
			</p>
		);
	}

	if ( state.connected ) {
		return (
			<>
				<p>
					{ __(
						'Your webhook URL is saved, but its last test ping did not get through. In n8n, double-click the Webhook Trigger, choose "Production URL" and copy the address, then save and test it here.',
						'post-quality-manager'
					) }
				</p>
				<Connect state={ state } onConnected={ onConnected } />
			</>
		);
	}

	if ( state.provisioned ) {
		return (
			<>
				<p>
					{ __(
						'Your workflow is already set up in n8n, but its URL is not saved on this site. In n8n, double-click the Webhook Trigger, choose "Production URL" and copy the address.',
						'post-quality-manager'
					) }
				</p>
				<Connect state={ state } onConnected={ onConnected } />
			</>
		);
	}

	const submit = ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( '' );
		apiFetch( {
			path: PROVISION_PATH,
			method: 'POST',
			// eslint-disable-next-line camelcase -- REST field name.
			data: { anthropic_key: key.trim() },
		} ).then(
			( reply ) => {
				setBusy( false );
				setKey( '' );
				// Set up, but the ping failed or Rewrite stays off: never a
				// plain success.
				const status =
					reply.ping_ok && reply.rewrite_ready !== false
						? 'success'
						: 'warning';
				setOutcome( {
					status,
					message: reply.message,
					pingOk: !! reply.ping_ok,
				} );
				swapStatusBlock( reply.status_html );
				onProvisioned( !! reply.ping_ok );
			},
			( err ) => {
				setBusy( false );
				// The key is never stored, so the field clears; where a
				// retry can pass, the message says to type it again.
				setKey( '' );
				const code = err && err.code;
				const step = err && err.data && err.data.step;
				const retype =
					code === 'pqm_provider_key_invalid' ||
					code === 'pqm_provider_unreachable' ||
					( code === 'pqm_provision_failed' &&
						step !== 'template' &&
						step !== 'link' );
				const message =
					( err && err.message ) ||
					__(
						'Could not reach the site REST API. Reload the page and try again.',
						'post-quality-manager'
					);
				setError(
					retype
						? message +
								' ' +
								__(
									'Enter the key again.',
									'post-quality-manager'
								)
						: message
				);
			}
		);
	};

	return (
		<>
			<p>
				{ __(
					'Enter your Anthropic API key. The plugin checks it with Anthropic, then creates the credentials and the workflow in your n8n, publishes the workflow and sends a test ping. The key goes into the n8n credential of the Claude nodes; this site never stores it.',
					'post-quality-manager'
				) }
			</p>
			{ state.leftovers && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'An earlier setup left a workflow or credentials in n8n. Setting up removes them first.',
						'post-quality-manager'
					) }
				</Notice>
			) }
			<form className="pqm-setup-connect" onSubmit={ submit }>
				<TextControl
					label={ __( 'Anthropic API key', 'post-quality-manager' ) }
					help={ __(
						'Create one in the Anthropic Console under API Keys.',
						'post-quality-manager'
					) }
					type="password"
					autoComplete="new-password"
					value={ key }
					onChange={ setKey }
					disabled={ busy }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					type="submit"
					isBusy={ busy }
					disabled={ busy || ! key.trim() }
				>
					{ __( 'Set up the workflow', 'post-quality-manager' ) }
				</Button>
			</form>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</>
	);
}

/**
 * Set up by hand: why (a stored choice, a failed check, or a workflow made
 * by hand: a saved URL without the plugin's setup), the way to the Workflow
 * File section (the steps live there, never here), the paste-URL body, and
 * the key path behind "Connect by key instead" while no webhook URL is
 * saved — a passing check there outranks the choice. A hand-made workflow
 * (a URL saved) keeps this path whatever the key (adoption, 6.8), so it is
 * not offered there.
 *
 * @param {Object}   props
 * @param {Object}   props.state              GET /setup state.
 * @param {Function} props.onConnected        Connect's `onConnected`.
 * @param {Function} props.onN8nChange        The island's `onChange`.
 * @param {Function} props.onShowWorkflowFile Closes the dialog on the Workflow File section.
 * @return {Element} The step body.
 */
function Manual( { state, onConnected, onN8nChange, onShowWorkflowFile } ) {
	const reason = state.manual_reason;
	let why = '';
	if ( reason && reason.kind === 'probe' ) {
		why = sprintf(
			/* translators: 1: date of the check, 2: why it did not pass, e.g. "The public API is turned off on this instance." */
			__(
				'On %1$s, the n8n Instance check did not pass: %2$s',
				'post-quality-manager'
			),
			reason.at,
			reason.message
		);
	} else if ( reason && reason.kind === 'choice' ) {
		why = __( 'You chose to set up n8n by hand.', 'post-quality-manager' );
	} else if ( ! reason && state.connected && ! state.provisioned ) {
		why = __(
			"This site's workflow was set up by hand.",
			'post-quality-manager'
		);
	}

	return (
		<>
			{ why && (
				<Notice status="info" isDismissible={ false }>
					{ why }
				</Notice>
			) }
			<p>
				{ createInterpolateElement(
					__(
						'The steps are in the <a>Workflow File</a> section of this page: import the workflow, create its credentials, pick them in the nodes, then publish it. Once it is published, paste its production URL here.',
						'post-quality-manager'
					),
					{
						a: (
							// eslint-disable-next-line jsx-a11y/anchor-has-content -- content comes from the translated string.
							<a
								href={ `#${ WORKFLOW_FILE_ID }` }
								onClick={ onShowWorkflowFile }
							/>
						),
					}
				) }
			</p>
			<Connect state={ state } onConnected={ onConnected } />
			{ ! state.connected && (
				<details className="pqm-setup-by-key">
					<summary>
						{ __(
							'Connect by key instead',
							'post-quality-manager'
						) }
					</summary>
					<N8nConnection onChange={ onN8nChange } />
				</details>
			) }
		</>
	);
}

/** The n8n steps: the key path's two, or the manual path's one. */
const KEY_PATH = [ 'n8n', 'provision' ];
const MANUAL_PATH = [ 'manual' ];

/**
 * Every step, in order. The n8n steps and `review` are ticked by the plugin
 * (a passing check, a saved URL, the first result); `config` steps are
 * ticked when saved or skipped.
 */
export const STEPS = [
	{
		key: 'n8n',
		title: __( 'Connect your n8n', 'post-quality-manager' ),
		Body: ConnectN8n,
	},
	{
		key: 'provision',
		title: __( 'Set up the workflow', 'post-quality-manager' ),
		Body: Provision,
	},
	{
		key: 'manual',
		title: __( 'Set up by hand', 'post-quality-manager' ),
		Body: Manual,
	},
	{
		key: 'review',
		title: __( 'Run your first review', 'post-quality-manager' ),
		Body: null, // FirstReview, rendered by the dialog (it owns completion).
	},
	...CONFIG_STEPS,
];

/**
 * The steps this site walks: the manual path once `state.manual` is set
 * (GET /setup decides it; the dialog re-reads it after the connection
 * changes), else the key path;
 * the Free build (story 54) has no schedule step, and a progress key of a
 * step it leaves out is ignored.
 *
 * @param {Object} state GET /setup state.
 * @return {Object[]} Step entries, in order.
 */
export function stepsFor( state ) {
	const n8nSteps = state.manual ? MANUAL_PATH : KEY_PATH;
	return STEPS.filter( ( step ) => {
		if (
			KEY_PATH.includes( step.key ) ||
			MANUAL_PATH.includes( step.key )
		) {
			return n8nSteps.includes( step.key );
		}
		return state.build !== 'free' || step.key !== 'schedule';
	} );
}

/** Closing panel once the setup is complete. */
export function NextSteps() {
	return (
		<>
			<p>
				{ __(
					'Setup complete. Reviews now run from Post Reviews and the Dashboard.',
					'post-quality-manager'
				) }
			</p>
			<Button variant="primary" href="admin.php?page=pqm-posts">
				{ __( 'Open Post Reviews', 'post-quality-manager' ) }
			</Button>
		</>
	);
}
