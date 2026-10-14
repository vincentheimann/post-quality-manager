/**
 * n8n Instance section (n8n provisioning, entry 1) — an island on the PHP
 * n8n Workflow page, reused by the setup wizard (entry 6), which passes
 * `onChange`. The Administrator enters the instance's address and an n8n API key; the
 * server probes the instance and stores both only when the probe passes
 * (POST /n8n/connection). The key is write-only: the server never sends it
 * back, so the field always starts empty and the page shows "set" or
 * "not set". The state comes from GET /n8n/connection, which calls nothing
 * on n8n: a reload shows the last passing probe. Forget (DELETE) removes
 * the key and keeps the address. The setup wizard (entry 6) passes
 * `onChange`, called with the stored state after every call that reads or
 * changes it (a failed probe changes nothing, so it is not called then);
 * the page mount passes none.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import './style.css';

const PATH = '/post-quality/v1/n8n/connection';

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
 * @param {Function=} props.onChange `( { base_url, key_set, probe } )` after a call that read or stored the state.
 * @return {Element|null} The section.
 */
export default function N8nConnection( { onChange } ) {
	const [ state, setState ] = useState( null ); // { base_url, key_set, probe }
	const [ loadError, setLoadError ] = useState( '' );
	const [ url, setUrl ] = useState( '' );
	const [ key, setKey ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ failure, setFailure ] = useState( '' ); // the last call's cause

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
	 * One call to the route; the reply is the stored state. A POST reply's
	 * `probe` is this run's result: a failed one changed nothing stored.
	 *
	 * @param {Object} options apiFetch options beyond the path.
	 */
	const send = ( options ) => {
		setBusy( true );
		setFailure( '' );
		apiFetch( { path: PATH, ...options } ).then(
			( data ) => {
				setBusy( false );
				if ( data.probe && ! data.probe.ok ) {
					setFailure( data.probe.message );
					return;
				}
				setState( data );
				setUrl( data.base_url );
				setKey( '' );
				if ( onChange ) {
					onChange( data );
				}
			},
			( err ) => {
				setBusy( false );
				setFailure( errorMessage( err ) );
			}
		);
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
	// The stored key is re-used for the stored address only (server rule).
	const canSubmit =
		!! url.trim() &&
		( !! key.trim() || ( state.key_set && url.trim() === state.base_url ) );

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
			<p className="pqm-n8n-key-state">
				{ state.key_set
					? __( 'API key: set', 'post-quality-manager' )
					: __( 'API key: not set', 'post-quality-manager' ) }
			</p>
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
					help={
						state.key_set
							? __(
									'A key is stored and never shown again. Leave this empty to keep it.',
									'post-quality-manager'
							  )
							: __(
									'Create one in n8n under Settings → n8n API. Use a key for this site alone, with an expiration date.',
									'post-quality-manager'
							  )
					}
					type="password"
					autoComplete="new-password"
					value={ key }
					onChange={ setKey }
					__nextHasNoMarginBottom
				/>
				<div className="pqm-n8n-connection-actions">
					<Button
						variant="primary"
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
								{ __( 'Check again', 'post-quality-manager' ) }
							</Button>
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
						</>
					) }
				</div>
			</form>
			{ failure && (
				<Notice status="error" isDismissible={ false }>
					{ failure }
				</Notice>
			) }
		</div>
	);
}
