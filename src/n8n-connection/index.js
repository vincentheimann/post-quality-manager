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
 * /n8n/adoption shows what the plugin found by name, in place of the form,
 * and the owner's confirm (POST) stores the setup map; n8n is only read.
 * With a setup map (`setup`), the section says since when the plugin
 * manages the workflow.
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
	const [ failure, setFailure ] = useState( '' ); // the last call's cause
	const [ proposal, setProposal ] = useState( null ); // GET /n8n/adoption, while open
	// Where keyboard focus goes once the proposal opens or closes: the clicked button unmounts.
	const [ focusTo, setFocusTo ] = useState( null ); // 'proposal' | 'closed'
	const proposalRef = useRef( null );
	const offerRef = useRef( null );
	const managedRef = useRef( null );
	// On the page, the update notice's Re-import the workflow is then the one
	// primary action; inside the setup guide (onChange), the Modal is its own surface.
	const reimportShown = useReimportShown();

	// Opened: the proposal. Closed: the offer button when it shows again, else the managed line.
	useEffect( () => {
		if ( ! focusTo ) {
			return;
		}
		const target =
			focusTo === 'proposal'
				? proposalRef.current
				: offerRef.current || managedRef.current;
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

	/**
	 * One REST call: busy while it runs, a refusal in the failure Notice.
	 *
	 * @param {Object}    options apiFetch options.
	 * @param {Function}  done    `( data )` on success.
	 * @param {Function=} refused After a refusal.
	 */
	const call = ( options, done, refused ) => {
		setBusy( true );
		setFailure( '' );
		apiFetch( options ).then(
			( data ) => {
				setBusy( false );
				done( data );
			},
			( err ) => {
				setBusy( false );
				setFailure( errorMessage( err ) );
				if ( refused ) {
					refused();
				}
			}
		);
	};

	/**
	 * The reply is the stored state.
	 *
	 * @param {Object} data The stored state.
	 */
	const stored = ( data ) => {
		setState( data );
		setUrl( data.base_url );
		setKey( '' );
		if ( onChange ) {
			onChange( data );
		}
	};

	/**
	 * One call to the connection route. A POST reply's `probe` is this run's
	 * result: a failed one changed nothing stored.
	 *
	 * @param {Object} options apiFetch options beyond the path.
	 */
	const send = ( options ) =>
		call( { path: PATH, ...options }, ( data ) => {
			if ( data.probe && ! data.probe.ok ) {
				setFailure( data.probe.message );
				return;
			}
			stored( data );
		} );

	// Entry 8: what the plugin would manage (one read of n8n), then the owner's confirm.
	const look = () =>
		call( { path: ADOPTION_PATH }, ( data ) => {
			setProposal( data );
			setFocusTo( 'proposal' );
		} );
	const adopt = () =>
		call(
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
	const cancel = () => {
		setProposal( null );
		setFailure( '' );
		setFocusTo( 'closed' );
	};

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

	const connected = state.key_set && state.probe;
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
	// On the page, the status block's primary action wins (useReimportShown).
	const primary = ! onChange && reimportShown ? 'secondary' : 'primary';

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

	return (
		<div className="pqm-n8n-connection">
			<p>
				{ __(
					'Connect your n8n instance with an n8n API key. The plugin checks whether it can set up the workflow there; the check changes nothing in n8n.',
					'post-quality-manager'
				) }
			</p>
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
			<p className="pqm-n8n-key-state">{ keyState }</p>
			{ managed && (
				<p className="pqm-n8n-managed" tabIndex="-1" ref={ managedRef }>
					{ managed }
				</p>
			) }
			{ proposal ? (
				<div
					className="pqm-n8n-proposal"
					tabIndex="-1"
					ref={ proposalRef }
				>
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
								__(
									'Webhook token: %s',
									'post-quality-manager'
								),
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
						{ __(
							'Nothing in n8n changes.',
							'post-quality-manager'
						) }
					</p>
					<div className="pqm-n8n-connection-actions">
						<Button
							variant={ primary }
							type="button"
							className="pqm-n8n-adopt-confirm"
							isBusy={ busy }
							disabled={ busy }
							onClick={ adopt }
						>
							{ __(
								'Manage this workflow',
								'post-quality-manager'
							) }
						</Button>
						<Button
							variant="secondary"
							type="button"
							className="pqm-n8n-adopt-cancel"
							disabled={ busy }
							onClick={ cancel }
						>
							{ __( 'Cancel', 'post-quality-manager' ) }
						</Button>
					</div>
				</div>
			) : (
				<form className="pqm-n8n-connection-form" onSubmit={ connect }>
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
							{ __( 'Connect', 'post-quality-manager' ) }
						</Button>
						{ state.key_set && (
							<>
								<Button
									variant="secondary"
									type="button"
									className="pqm-n8n-recheck"
									disabled={ busy }
									onClick={ () => send( { method: 'POST' } ) }
								>
									{ __(
										'Check again',
										'post-quality-manager'
									) }
								</Button>
								<Button
									variant="secondary"
									isDestructive
									type="button"
									className="pqm-n8n-forget"
									disabled={ busy }
									onClick={ () =>
										send( { method: 'DELETE' } )
									}
								>
									{ __(
										'Forget key',
										'post-quality-manager'
									) }
								</Button>
							</>
						) }
					</div>
				</form>
			) }
			{ ! proposal && state.adoptable && (
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
			) }
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure }
				</Notice>
			) }
		</div>
	);
}
