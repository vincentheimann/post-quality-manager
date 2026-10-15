/**
 * n8n Instance section (n8n provisioning, entry 1) — an island on the PHP
 * n8n Workflow page, reused by the setup wizard (entry 6), which passes
 * `onChange`. The Administrator enters the instance's address and an n8n
 * API key; the server probes the instance and stores both only when the
 * probe passes (POST /n8n/connection). The key is write-only: the server
 * never sends it back, only its last four characters (`key_hint`), so the
 * field always starts empty, with the hint as its masked placeholder, and
 * the page shows "set, ending in …" or "not set". The state comes from GET
 * /n8n/connection, which calls nothing on n8n: a reload shows the last
 * passing probe. Forget (DELETE) removes the key and keeps the address.
 * The setup wizard (entry 6) passes `onChange`, called with the stored
 * state after every call that reads or changes it (a failed probe changes
 * nothing, so it is not called then); the page mount passes none.
 * Entry 8: with a passing key and a saved webhook URL but no setup map
 * (`adoptable`), the section offers to manage the hand-made workflow: GET
 * /n8n/adoption shows what the plugin found by name, in place of the offer,
 * and the owner's confirm (POST) stores the setup map; n8n is only read.
 * With a setup map (`setup`), the section says since when the plugin
 * manages the workflow.
 * Two blocks that never swap (plan-n8n-connection-states): the connection
 * block shows connected (a summary, Check again, Change, Forget key),
 * changing (the fields, Save and check, Cancel) or connecting (the fields,
 * Connect); the adoption block below it shows the managed line, the
 * proposal or the offer, each in place.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import useReimportShown from '../kit/use-reimport-shown';
import './style.css';

const PATH = '/post-quality/v1/n8n/connection';
const ADOPTION_PATH = '/post-quality/v1/n8n/adoption';

/**
 * A credential as the proposal shows it: n8n's name, else its id.
 *
 * @param {Object} credential `{ id, name }`.
 * @return {string} What to show.
 */
const credentialName = ( credential ) => credential.name || credential.id;

/**
 * The date of a Unix timestamp, in the viewer's locale.
 *
 * @param {number} at Seconds.
 * @return {string} The date.
 */
const dateOf = ( at ) => new Date( at * 1000 ).toLocaleDateString();

/**
 * Connected: a key stored and its last check passing (the server keeps the
 * last passing probe only).
 *
 * @param {Object} data The stored state.
 * @return {boolean} Whether the section shows the summary.
 */
const isConnected = ( data ) =>
	!! ( data.key_set && data.probe && data.probe.ok );

/**
 * The REST error's own message, or the generic one.
 *
 * @param {Object} err apiFetch rejection.
 * @return {string} Message to show.
 */
function errorMessage( err ) {
	return (
		( err && err.message ) ||
		__(
			"Could not reach this site's REST API. Reload the page and try again.",
			'post-quality-manager'
		)
	);
}

/**
 * @param {Object}    props
 * @param {Function=} props.onChange `( { base_url, key_set, key_hint, probe, setup, adoptable } )` after a call that read or stored the state.
 * @return {Element|null} The section.
 */
export default function N8nConnection( { onChange } ) {
	const [ state, setState ] = useState( null ); // { base_url, key_set, key_hint, probe, setup, adoptable }
	const [ loadError, setLoadError ] = useState( '' );
	const [ url, setUrl ] = useState( '' );
	const [ key, setKey ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	// Each block's last cause, shown in the block that made the call.
	const [ failure, setFailure ] = useState( {} ); // { connection?, adoption? }
	const [ changing, setChanging ] = useState( false ); // Change pressed on a connected site
	const [ proposal, setProposal ] = useState( null ); // GET /n8n/adoption, while open
	// Where keyboard focus goes after a state change: the pressed button unmounts.
	const [ focusTo, setFocusTo ] = useState( null ); // 'change' | 'summary' | 'proposal' | 'closed'
	const formRef = useRef( null );
	const changeRef = useRef( null );
	const proposalRef = useRef( null );
	const offerRef = useRef( null );
	const managedRef = useRef( null );
	// On the page, the update notice's Re-import the workflow is then the one
	// primary action; inside the setup guide (onChange), the Modal is its own surface.
	const reimportShown = useReimportShown();

	// change: the address field. summary: the Change button. proposal: the
	// proposal. closed: the offer button when it shows again, else the managed line.
	useEffect( () => {
		if ( ! focusTo ) {
			return;
		}
		const targets = {
			change: () =>
				formRef.current && formRef.current.querySelector( 'input' ),
			summary: () => changeRef.current,
			proposal: () => proposalRef.current,
			closed: () => offerRef.current || managedRef.current,
		};
		const target = targets[ focusTo ]();
		if ( target ) {
			target.focus();
		}
		setFocusTo( null );
	}, [ focusTo ] );

	// One read on mount; a new `onChange` prop never re-reads.
	useEffect( () => {
		apiFetch( { path: PATH } ).then(
			( data ) => {
				setState( data );
				setUrl( data.base_url );
				if ( onChange ) {
					onChange( data );
				}
			},
			( err ) => setLoadError( errorMessage( err ) )
		);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}
	if ( ! state ) {
		return null;
	}

	const connected = isConnected( state );
	// A form shows while nothing passes, or while the owner changes a passing connection.
	const formOpen = changing || ! connected;

	/**
	 * Set one block's failure Notice; the other block's stays.
	 *
	 * @param {string} block   'connection' | 'adoption'.
	 * @param {string} message The cause, '' to drop it.
	 */
	const failIn = ( block, message ) =>
		setFailure( ( current ) => ( { ...current, [ block ]: message } ) );
	const clearFailure = ( block ) => failIn( block, '' );

	/**
	 * One REST call: busy while it runs, a refusal in the failure Notice of
	 * the block that made it; the other block's cause stays.
	 *
	 * @param {string}    block   'connection' | 'adoption'.
	 * @param {Object}    options apiFetch options.
	 * @param {Function}  done    `( data )` on success.
	 * @param {Function=} refused After a refusal.
	 */
	const call = ( block, options, done, refused ) => {
		setBusy( true );
		clearFailure( block );
		apiFetch( options ).then(
			( data ) => {
				setBusy( false );
				done( data );
			},
			( err ) => {
				setBusy( false );
				failIn( block, errorMessage( err ) );
				if ( refused ) {
					refused();
				}
			}
		);
	};

	/**
	 * The reply is the stored state. Nothing to adopt any more (Forget key):
	 * an open proposal closes with the offer. The fields stay as typed: the
	 * adoption block never rewrites the connection block's form.
	 *
	 * @param {Object} data The stored state.
	 */
	const stored = ( data ) => {
		setState( data );
		if ( ! data.adoptable ) {
			setProposal( null );
		}
		if ( onChange ) {
			onChange( data );
		}
	};

	/**
	 * One call to the connection route. A POST reply's `probe` is this run's
	 * result: a failed one changed nothing stored, and the form stays. A
	 * passing one shows the summary; Forget shows the fields again.
	 *
	 * @param {Object} options apiFetch options beyond the path.
	 */
	const send = ( options ) =>
		call( 'connection', { path: PATH, ...options }, ( data ) => {
			if ( data.probe && ! data.probe.ok ) {
				failIn( 'connection', data.probe.message );
				return;
			}
			stored( data );
			setUrl( data.base_url );
			setKey( '' );
			// A new connection: a proposal read from the previous one closes.
			if ( changing ) {
				setProposal( null );
			}
			setChanging( false );
			if ( ! isConnected( data ) ) {
				setFocusTo( 'change' ); // the fields replaced the summary
			} else if ( formOpen ) {
				setFocusTo( 'summary' ); // the summary replaced the form
			}
		} );

	const connect = ( event ) => {
		event.preventDefault();
		// eslint-disable-next-line camelcase -- REST field names.
		const data = { base_url: url.trim() };
		if ( key.trim() ) {
			// eslint-disable-next-line camelcase -- REST field name.
			data.api_key = key.trim();
		}
		send( { method: 'POST', data } );
	};

	// Change opens the fields over the stored address, the key empty (write-only).
	const change = () => {
		setUrl( state.base_url );
		setKey( '' );
		setChanging( true );
		setFocusTo( 'change' );
	};
	const cancelChange = () => {
		setUrl( state.base_url );
		setKey( '' );
		setChanging( false );
		clearFailure( 'connection' );
		setFocusTo( 'summary' );
	};

	// Entry 8: what the plugin would manage (one read of n8n), then the owner's confirm.
	const look = () =>
		call( 'adoption', { path: ADOPTION_PATH }, ( data ) => {
			setProposal( data );
			setFocusTo( 'proposal' );
		} );
	const adopt = () =>
		call(
			'adoption',
			{
				path: ADOPTION_PATH,
				method: 'POST',
				// eslint-disable-next-line camelcase -- REST field name.
				data: { workflow_id: proposal.workflow.id },
			},
			( data ) => {
				setProposal( null );
				stored( data );
				setFocusTo( 'closed' );
			},
			// Refused (n8n changed since, or the read failed): the offer again, to look again.
			() => {
				setProposal( null );
				setFocusTo( 'closed' );
			}
		);
	const cancelProposal = () => {
		setProposal( null );
		clearFailure( 'adoption' );
		setFocusTo( 'closed' );
	};

	// The stored key's last 4 characters, empty for a short key (server rule).
	const hint = ( state.key_set && state.key_hint ) || '';
	let keyState = __( 'API key: not set', 'post-quality-manager' );
	let keyHelp = __(
		'Create one in n8n under Settings → n8n API. Use a key for this site alone, with an expiration date.',
		'post-quality-manager'
	);
	if ( hint ) {
		keyState = sprintf(
			/* translators: %s: the stored key's last four characters */
			__( 'API key: set, ending in %s', 'post-quality-manager' ),
			hint
		);
		keyHelp = __( 'Type a new key to replace it.', 'post-quality-manager' );
	} else if ( state.key_set ) {
		keyState = __( 'API key: set', 'post-quality-manager' );
		keyHelp = __(
			'A key is stored. Type a new key to replace it.',
			'post-quality-manager'
		);
	}
	// The stored key is re-used for the stored address only (server rule).
	const canSubmit =
		!! url.trim() &&
		( !! key.trim() || ( state.key_set && url.trim() === state.base_url ) );
	// One primary at most: the open form's submit, else the proposal's confirm.
	// On the page, the status block's primary action wins (useReimportShown).
	const primary = ! onChange && reimportShown ? 'secondary' : 'primary';
	const confirmVariant = formOpen ? 'secondary' : primary;

	let managed = '';
	if ( state.setup ) {
		managed = state.setup.wizard_created
			? sprintf(
					/* translators: %s: date */
					__(
						'The plugin set this workflow up on %s.',
						'post-quality-manager'
					),
					dateOf( state.setup.at )
			  )
			: sprintf(
					/* translators: %s: date */
					__(
						'The plugin has managed this workflow since %s.',
						'post-quality-manager'
					),
					dateOf( state.setup.at )
			  );
	}

	/**
	 * The failure Notice, in the block whose call failed.
	 *
	 * @param {string} block 'connection' | 'adoption'.
	 * @return {Element|null} The Notice.
	 */
	const failureIn = ( block ) =>
		failure[ block ] ? (
			<Notice status="error" isDismissible={ false }>
				{ failure[ block ] }
			</Notice>
		) : null;

	// In the summary, and in the form while a stored key does not pass.
	const recheckButton = (
		<Button
			variant="secondary"
			type="button"
			className="pqm-n8n-recheck"
			disabled={ busy }
			onClick={ () => send( { method: 'POST' } ) }
		>
			{ __( 'Check again', 'post-quality-manager' ) }
		</Button>
	);
	const forgetButton = (
		<Button
			variant="secondary"
			isDestructive
			type="button"
			className="pqm-n8n-forget"
			disabled={ busy }
			onClick={ () => send( { method: 'DELETE' } ) }
		>
			{ __( 'Forget key', 'post-quality-manager' ) }
		</Button>
	);

	const form = (
		<form
			className="pqm-n8n-connection-form"
			onSubmit={ connect }
			ref={ formRef }
		>
			<TextControl
				label={ __( 'n8n address', 'post-quality-manager' ) }
				help={ __(
					'The address you open n8n at. Only the domain is kept.',
					'post-quality-manager'
				) }
				type="url"
				value={ url }
				onChange={ setUrl }
				placeholder="https://n8n.example.com"
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'n8n API key', 'post-quality-manager' ) }
				help={ keyHelp }
				type="password"
				autoComplete="new-password"
				placeholder={ hint ? '••••••••' + hint : undefined }
				value={ key }
				onChange={ setKey }
				__nextHasNoMarginBottom
			/>
			<div className="pqm-n8n-connection-actions">
				<Button
					variant={ primary }
					type="submit"
					isBusy={ busy }
					disabled={ busy || ! canSubmit }
				>
					{ changing
						? __( 'Save and check', 'post-quality-manager' )
						: __( 'Connect', 'post-quality-manager' ) }
				</Button>
				{ changing && (
					<Button
						variant="secondary"
						type="button"
						className="pqm-n8n-change-cancel"
						disabled={ busy }
						onClick={ cancelChange }
					>
						{ __( 'Cancel', 'post-quality-manager' ) }
					</Button>
				) }
				{ ! changing && state.key_set && (
					<>
						{ recheckButton }
						{ forgetButton }
					</>
				) }
			</div>
		</form>
	);

	const summary = (
		<div className="pqm-n8n-connection-actions pqm-n8n-summary">
			{ recheckButton }
			<Button
				variant="secondary"
				type="button"
				className="pqm-n8n-change"
				ref={ changeRef }
				disabled={ busy }
				onClick={ change }
			>
				{ __( 'Change', 'post-quality-manager' ) }
			</Button>
			{ forgetButton }
		</div>
	);

	let adoption = null;
	if ( managed ) {
		adoption = (
			<p className="pqm-n8n-managed" tabIndex="-1" ref={ managedRef }>
				{ managed }
			</p>
		);
	} else if ( proposal ) {
		adoption = (
			<div className="pqm-n8n-proposal" tabIndex="-1" ref={ proposalRef }>
				<p>
					{ sprintf(
						/* translators: 1: the n8n workflow's name; 2: its webhook path */
						__(
							'Found at your webhook URL: %1$s, listening on /webhook/%2$s.',
							'post-quality-manager'
						),
						proposal.workflow.name,
						proposal.workflow.path
					) }
				</p>
				<ul className="pqm-n8n-proposal-credentials">
					<li>
						{ sprintf(
							/* translators: %s: an n8n credential's name */
							__( 'Webhook token: %s', 'post-quality-manager' ),
							credentialName( proposal.credentials.webhook )
						) }
					</li>
					<li>
						{ sprintf(
							/* translators: %s: an n8n credential's name */
							__( 'Anthropic: %s', 'post-quality-manager' ),
							credentialName( proposal.credentials.anthropic )
						) }
					</li>
					{ /* Pro build only: the server sends rewrite_ready there. */ }
					{ 'rewrite_ready' in proposal && (
						<li>
							{ proposal.credentials.wordpress
								? sprintf(
										/* translators: %s: an n8n credential's name */
										__(
											'WordPress: %s',
											'post-quality-manager'
										),
										credentialName(
											proposal.credentials.wordpress
										)
								  )
								: __(
										'WordPress: none. Rewrite stays off until a credential is set on Update WP Post (Rewrite).',
										'post-quality-manager'
								  ) }
						</li>
					) }
				</ul>
				<p>
					{ __( 'Nothing in n8n changes.', 'post-quality-manager' ) }
				</p>
				<div className="pqm-n8n-connection-actions">
					<Button
						variant={ confirmVariant }
						type="button"
						className="pqm-n8n-adopt-confirm"
						isBusy={ busy }
						disabled={ busy }
						onClick={ adopt }
					>
						{ __( 'Manage this workflow', 'post-quality-manager' ) }
					</Button>
					<Button
						variant="secondary"
						type="button"
						className="pqm-n8n-adopt-cancel"
						disabled={ busy }
						onClick={ cancelProposal }
					>
						{ __( 'Cancel', 'post-quality-manager' ) }
					</Button>
				</div>
			</div>
		);
	} else if ( state.adoptable ) {
		adoption = (
			<div className="pqm-n8n-adoption-offer">
				<p>
					{ __(
						'Your workflow was set up by hand. Let the plugin manage it, and you can update it and rotate the webhook token from here later. Nothing in n8n changes.',
						'post-quality-manager'
					) }
				</p>
				<Button
					variant="secondary"
					type="button"
					className="pqm-n8n-adopt"
					ref={ offerRef }
					isBusy={ busy }
					disabled={ busy }
					onClick={ look }
				>
					{ __(
						'Let the plugin manage this workflow',
						'post-quality-manager'
					) }
				</Button>
			</div>
		);
	}
	const adoptionFailure = failureIn( 'adoption' );

	return (
		<div className="pqm-n8n-connection">
			<div className="pqm-n8n-connection-block">
				{ formOpen && (
					<p>
						{ __(
							'Connect your n8n instance with an n8n API key. The plugin checks whether it can set up the workflow there; the check changes nothing in n8n.',
							'post-quality-manager'
						) }
					</p>
				) }
				{ connected ? (
					<Notice status="success" isDismissible={ false }>
						{ state.probe.message }{ ' ' }
						{ sprintf(
							/* translators: %s: date and time of the last check */
							__( 'Checked %s.', 'post-quality-manager' ),
							new Date( state.probe.at * 1000 ).toLocaleString()
						) }
					</Notice>
				) : null }
				<p className="pqm-n8n-key-state">
					{ formOpen
						? keyState
						: sprintf(
								/* translators: 1: the n8n instance's host; 2: the stored key's state, e.g. "API key: set, ending in ab12" */
								__(
									'n8n at %1$s · %2$s',
									'post-quality-manager'
								),
								state.base_url.replace( /^https?:\/\//, '' ),
								keyState
						  ) }
				</p>
				{ formOpen ? form : summary }
				{ failureIn( 'connection' ) }
			</div>
			{ ( adoption || adoptionFailure ) && (
				<div className="pqm-n8n-adoption">
					{ adoption }
					{ adoptionFailure }
				</div>
			) }
		</div>
	);
}
