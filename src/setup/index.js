/**
 * Setup island (story 36) — the only React on the PHP n8n Workflow page
 * (ADR 0005 keeps that page on PHP). A button opens a Modal that walks a
 * novice through connecting n8n in seven steps, each with a Done mark.
 *
 * State comes from GET /setup: progress is stored once per site, so the
 * dialog reopens at the first unfinished step, after a reload or another
 * day. The user ticks the n8n-side steps (POST /setup { step, done }); the
 * plugin ticks what it can verify — the connect step through its ping
 * (POST /setup/connect), the setup itself through the first review
 * (POST /setup { complete: true }). The welcome notice links here with
 * `pqm_setup=1`, which opens the dialog on load.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	Dashicon,
	Modal,
	Notice,
	VisuallyHidden,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getQueryArg, removeQueryArgs } from '@wordpress/url';

import FirstReview from './first-review';
import { NextSteps, STEPS } from './steps';
import './style.css';

const SETUP_PATH = '/post-quality/v1/setup';
const OPEN_ARG = 'pqm_setup'; // PQM_Admin::SETUP_QUERY_ARG
const FINISH = 'finish';

/**
 * Where the dialog opens: the first unfinished step, or the closing panel
 * once the setup is complete.
 *
 * @param {Object} state GET /setup state.
 * @return {string} Step key or FINISH.
 */
function resumeAt( state ) {
	if ( state.complete ) {
		return FINISH;
	}
	const open = STEPS.find( ( step ) => ! state.steps[ step.key ] );
	return open ? open.key : 'review';
}

export default function SetupIsland() {
	const [ state, setState ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ current, setCurrent ] = useState( null ); // null = dialog closed
	const [ saving, setSaving ] = useState( false );
	const [ saveError, setSaveError ] = useState( '' );

	useEffect( () => {
		apiFetch( { path: SETUP_PATH } ).then(
			( data ) => {
				setState( data );
				const href = window.location.href;
				if ( getQueryArg( href, OPEN_ARG ) ) {
					setCurrent( resumeAt( data ) );
					// Drop the arg so a reload does not reopen the dialog.
					window.history.replaceState(
						null,
						'',
						removeQueryArgs( href, OPEN_ARG )
					);
				}
			},
			( err ) =>
				setLoadError(
					( err && err.message ) ||
						__(
							'Could not load the setup guide. Reload the page and try again.',
							'post-quality-manager'
						)
				)
		);
	}, [] );

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	const save = ( data ) => {
		setSaving( true );
		setSaveError( '' );
		return apiFetch( { path: SETUP_PATH, method: 'POST', data } ).then(
			( next ) => {
				setSaving( false );
				setState( next );
			},
			( err ) => {
				setSaving( false );
				setSaveError(
					( err && err.message ) ||
						__(
							'Could not save your progress. Try again.',
							'post-quality-manager'
						)
				);
			}
		);
	};

	const onConnected = ( url, pingOk ) =>
		setState( ( prev ) => ( {
			...prev,
			connected: true,
			// eslint-disable-next-line camelcase -- REST field name.
			webhook_url: url,
			steps: { ...prev.steps, connect: pingOk },
		} ) );

	const complete = !! ( state && state.complete );
	const index = STEPS.findIndex( ( step ) => step.key === current );
	const step = STEPS[ index ];
	const isDone = ( key ) => complete || !! state.steps[ key ];

	const goTo = ( key ) => {
		setSaveError( '' );
		setCurrent( key );
	};

	const next = () => {
		if ( index === STEPS.length - 1 ) {
			goTo( FINISH );
		} else {
			goTo( STEPS[ index + 1 ].key );
		}
	};

	return (
		<div className="pqm-setup">
			{ ! complete && (
				<p>
					{ __(
						'New to n8n? The setup guide walks you through every step, from getting n8n to your first review.',
						'post-quality-manager'
					) }
				</p>
			) }
			<Button
				variant={ complete ? 'secondary' : 'primary' }
				onClick={ () => goTo( resumeAt( state ) ) }
				disabled={ ! state }
				isBusy={ ! state }
			>
				{ complete
					? __( 'Setup guide', 'post-quality-manager' )
					: __( 'Set up the workflow', 'post-quality-manager' ) }
			</Button>

			{ state && current && (
				<Modal
					title={ __(
						'Set up the n8n workflow',
						'post-quality-manager'
					) }
					onRequestClose={ () => setCurrent( null ) }
					size="large"
					className="pqm-setup-modal"
				>
					<p className="pqm-muted">
						{ __(
							'The n8n menus and labels below are named as of October 2026. If your n8n looks different, look for the same words nearby.',
							'post-quality-manager'
						) }
					</p>
					<div className="pqm-setup-layout">
						<nav
							className="pqm-setup-steps"
							aria-label={ __(
								'Setup steps',
								'post-quality-manager'
							) }
						>
							<ol className="pqm-setup-nav">
								{ STEPS.map( ( item ) => (
									<li key={ item.key }>
										<Button
											variant="tertiary"
											isPressed={ item.key === current }
											aria-current={
												item.key === current
													? 'step'
													: undefined
											}
											onClick={ () => goTo( item.key ) }
										>
											{ isDone( item.key ) && (
												<>
													<Dashicon
														icon="yes-alt"
														aria-hidden="true"
													/>
													<VisuallyHidden>
														{ __(
															'Done:',
															'post-quality-manager'
														) }{ ' ' }
													</VisuallyHidden>
												</>
											) }
											{ item.title }
										</Button>
									</li>
								) ) }
								{ complete && (
									<li>
										<Button
											variant="tertiary"
											isPressed={ current === FINISH }
											aria-current={
												current === FINISH
													? 'step'
													: undefined
											}
											onClick={ () => goTo( FINISH ) }
										>
											{ __(
												'Next steps',
												'post-quality-manager'
											) }
										</Button>
									</li>
								) }
							</ol>
						</nav>

						<div className="pqm-setup-step">
							{ step ? (
								<>
									<h2>
										{ sprintf(
											/* translators: 1: step number, 2: number of steps, 3: step title */
											__(
												'Step %1$d of %2$d: %3$s',
												'post-quality-manager'
											),
											index + 1,
											STEPS.length,
											step.title
										) }
									</h2>
									{ step.key === 'review' ? (
										<FirstReview
											connected={ !! state.connected }
											onComplete={ () =>
												save( { complete: true } )
											}
										/>
									) : (
										<step.Body
											state={ state }
											onConnected={ onConnected }
										/>
									) }
									<StepStatus
										step={ step }
										done={ isDone( step.key ) }
										complete={ complete }
										saving={ saving }
										onToggle={ ( done ) =>
											save( { step: step.key, done } )
										}
									/>
								</>
							) : (
								<>
									<h2>
										{ __(
											"You're set up",
											'post-quality-manager'
										) }
									</h2>
									<NextSteps />
								</>
							) }

							{ saveError && (
								<Notice status="error" isDismissible={ false }>
									{ saveError }
								</Notice>
							) }

							<div className="pqm-confirm-actions">
								{ index > 0 && (
									<Button
										variant="tertiary"
										onClick={ () =>
											goTo( STEPS[ index - 1 ].key )
										}
									>
										{ __( 'Back', 'post-quality-manager' ) }
									</Button>
								) }
								{ step &&
									( step.key !== 'review' || complete ) && (
										<Button
											variant="primary"
											onClick={ next }
										>
											{ __(
												'Next',
												'post-quality-manager'
											) }
										</Button>
									) }
								{ ! step && (
									<Button
										variant="secondary"
										onClick={ () => setCurrent( null ) }
									>
										{ __(
											'Close',
											'post-quality-manager'
										) }
									</Button>
								) }
							</div>
						</div>
					</div>
				</Modal>
			) }
		</div>
	);
}

/**
 * The step's Done mark: a checkbox for the steps the user ticks, a status
 * line for the two the plugin verifies.
 *
 * @param {Object}   props
 * @param {Object}   props.step     Step entry from STEPS.
 * @param {boolean}  props.done     Whether the step is done.
 * @param {boolean}  props.complete Whether the whole setup is complete.
 * @param {boolean}  props.saving   Whether a progress save is in flight.
 * @param {Function} props.onToggle `( done )` for a tickable step.
 * @return {Element} Done control or status line.
 */
function StepStatus( { step, done, complete, saving, onToggle } ) {
	if ( step.tickable ) {
		return (
			<div className="pqm-setup-done">
				<CheckboxControl
					label={ __( 'Done', 'post-quality-manager' ) }
					// A completed setup shows every step done; the stored tick stays as it is.
					checked={ done }
					disabled={ saving || complete }
					onChange={ onToggle }
					__nextHasNoMarginBottom
				/>
			</div>
		);
	}

	let text;
	if ( step.key === 'connect' ) {
		text = done
			? __( 'Done — the ping got through.', 'post-quality-manager' )
			: __(
					'This step ticks itself once the ping gets through.',
					'post-quality-manager'
			  );
	} else {
		text = done
			? __( 'Done — the first result is in.', 'post-quality-manager' )
			: __(
					'This step ticks itself when the first result arrives.',
					'post-quality-manager'
			  );
	}

	return (
		<p className="pqm-setup-done pqm-muted" role="status">
			{ text }
		</p>
	);
}
