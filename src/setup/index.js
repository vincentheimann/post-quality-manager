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
 *
 * After the first review, three configuration steps (story 55) save the
 * Blog Profile, the Editorial Standards and scheduled reviews through
 * POST /setup/settings; Skip ticks the step and writes no setting. They
 * never count toward completion, so a completed setup still shows them
 * open until they are saved or skipped.
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
const SETTINGS_PATH = '/post-quality/v1/setup/settings';
const OPEN_ARG = 'pqm_setup'; // PQM_Admin::SETUP_QUERY_ARG
const FINISH = 'finish';

/**
 * Whether a step is done. A completed setup shows every n8n step done; a
 * configuration step is done only once saved or skipped.
 *
 * @param {Object} state GET /setup state.
 * @param {Object} step  Step entry from STEPS.
 * @return {boolean} Done.
 */
function stepDone( state, step ) {
	return ( ! step.config && !! state.complete ) || !! state.steps[ step.key ];
}

/**
 * Where the dialog opens: the first unfinished step, or the closing panel
 * once every step is done.
 *
 * @param {Object} state GET /setup state.
 * @return {string} Step key or FINISH.
 */
function resumeAt( state ) {
	const open = STEPS.find( ( step ) => ! stepDone( state, step ) );
	return open ? open.key : FINISH;
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

	/**
	 * Store progress; with `settings`, save those first (POST
	 * /setup/settings) and tick only once they are stored.
	 *
	 * @param {Object}  data     POST /setup body.
	 * @param {Object=} settings POST /setup/settings body.
	 * @return {Promise<boolean>} Whether everything was stored; never rejects.
	 */
	const save = ( data, settings ) => {
		setSaving( true );
		setSaveError( '' );
		// The settings answer is the fresh state: apply it before the tick,
		// so a failed tick still shows the saved choice.
		const first = settings
			? apiFetch( {
					path: SETTINGS_PATH,
					method: 'POST',
					data: settings,
			  } ).then( setState )
			: Promise.resolve();
		return first
			.then( () =>
				apiFetch( { path: SETUP_PATH, method: 'POST', data } )
			)
			.then(
				( fresh ) => {
					setSaving( false );
					setState( fresh );
					return true;
				},
				( err ) => {
					setSaving( false );
					setSaveError(
						( err && err.message ) ||
							( settings
								? __(
										'Could not save this setting. Try again.',
										'post-quality-manager'
								  )
								: __(
										'Could not save your progress. Try again.',
										'post-quality-manager'
								  ) )
					);
					return false;
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
	const isDone = ( item ) => stepDone( state, item );

	const goTo = ( key ) => {
		setSaveError( '' );
		setCurrent( key );
	};

	const next = () => {
		if ( index < STEPS.length - 1 ) {
			goTo( STEPS[ index + 1 ].key );
		} else {
			// The closing panel needs a completed setup; until then the
			// first review is what is left.
			goTo( complete ? FINISH : 'review' );
		}
	};

	// A configuration step: save its settings (or nothing, for Skip), tick
	// it, move on. A failed save names the cause and leaves the step open.
	const finishStep = ( settings ) =>
		save( { step: step.key, done: true }, settings ).then(
			( ok ) => ok && next()
		);

	return (
		<div className="pqm-setup">
			{ ! complete && (
				<p>
					{ __(
						'New to n8n? The setup guide walks you through every step, from getting n8n to your first review, then the settings that make reviews yours.',
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
					title={ __( 'Setup guide', 'post-quality-manager' ) }
					onRequestClose={ () => setCurrent( null ) }
					size="large"
					className="pqm-setup-modal"
				>
					{ ! ( step && step.config ) && (
						<p className="pqm-muted">
							{ __(
								'The n8n menus and labels below are named as of October 2026. If your n8n looks different, look for the same words nearby.',
								'post-quality-manager'
							) }
						</p>
					) }
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
											{ isDone( item ) && (
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
											key={ step.key }
											state={ state }
											onConnected={ onConnected }
											saving={ saving }
											onSave={ finishStep }
										/>
									) }
									<StepStatus
										step={ step }
										state={ state }
										done={ isDone( step ) }
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
								{ step && step.config && ! isDone( step ) && (
									<Button
										variant="secondary"
										onClick={ () => finishStep() }
										disabled={ saving }
									>
										{ __( 'Skip', 'post-quality-manager' ) }
									</Button>
								) }
								{ step &&
									( step.key !== 'review' || complete ) &&
									( ! step.config || isDone( step ) ) && (
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
 * line for the two the plugin verifies and, once saved or skipped, for a
 * configuration step.
 *
 * @param {Object}   props
 * @param {Object}   props.step     Step entry from STEPS.
 * @param {Object}   props.state    GET /setup state.
 * @param {boolean}  props.done     Whether the step is done.
 * @param {boolean}  props.complete Whether the whole setup is complete.
 * @param {boolean}  props.saving   Whether a progress save is in flight.
 * @param {Function} props.onToggle `( done )` for a tickable step.
 * @return {Element} Done control or status line.
 */
function StepStatus( { step, state, done, complete, saving, onToggle } ) {
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

	if ( step.config ) {
		// The schedule lives on the Automation tab, hidden without
		// pqm_manage_agents and locked on a free site: no Settings promise there.
		const inSettings =
			step.key !== 'schedule' ||
			// eslint-disable-next-line camelcase -- REST field names.
			( state.can_manage_agents && state.is_pro );
		return done ? (
			<p className="pqm-setup-done pqm-muted" role="status">
				{ inSettings
					? __(
							'Done — you can change this any time in Settings.',
							'post-quality-manager'
					  )
					: __( 'Done.', 'post-quality-manager' ) }
			</p>
		) : null;
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
