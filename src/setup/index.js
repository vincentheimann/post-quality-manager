/**
 * Setup island (story 36) — a React island on the PHP n8n Workflow page
 * (ADR 0005 keeps that page on PHP). A button opens a Modal that walks the
 * site to its first review (n8n provisioning, entry 6): Connect your n8n
 * (the n8n Instance island, reused) and Set up the workflow (POST
 * /n8n/provision), or, on the manual path, Set up by hand (steps.js).
 *
 * State comes from GET /setup, stored once per site, so the dialog reopens
 * at the first unfinished step, after a reload or another day. Nothing is
 * ticked by hand on the n8n side: a passing check, a saved webhook URL and
 * the first review (POST /setup { complete: true }) are what the plugin
 * can verify. "Set up by hand instead" stores the choice (POST /setup
 * { manual: true }) and reloads the page on its Workflow File section. The
 * welcome notice links here with `pqm_setup=1`, which opens the dialog on
 * load.
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
	Dashicon,
	Modal,
	Notice,
	VisuallyHidden,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getQueryArg, removeQueryArgs } from '@wordpress/url';

import useReimportShown from '../kit/use-reimport-shown';
import FirstReview from './first-review';
import {
	NextSteps,
	STEPS,
	WORKFLOW_FILE_ID,
	keyPasses,
	stepsFor,
} from './steps';
import './style.css';

const SETUP_PATH = '/post-quality/v1/setup';
const SETTINGS_PATH = '/post-quality/v1/setup/settings';
const OPEN_ARG = 'pqm_setup'; // PQM_Admin::SETUP_QUERY_ARG
const FINISH = 'finish';

/**
 * Whether the island reports the connection the dialog already has (its
 * read on mount, mostly), so nothing needs re-reading.
 *
 * @param {Object}  n8n   The island's state.
 * @param {Object=} known GET /setup's `n8n`.
 * @return {boolean} Same.
 */
function sameConnection( n8n, known ) {
	return (
		!! known &&
		n8n.base_url === known.base_url &&
		n8n.key_set === known.key_set &&
		JSON.stringify( n8n.probe ) === JSON.stringify( known.probe )
	);
}

/**
 * Whether a step is done. A completed setup shows every n8n step done; a
 * configuration step is done only once saved or skipped. A step ticks only
 * what the plugin did: Connect your n8n only while the stored key passes
 * (a saved URL never ticks it); the other steps read GET /setup's `steps`
 * (Set up the workflow needs the plugin's own setup), which the dialog
 * re-reads after a URL save and patches after provisioning: Set up the
 * workflow and Set up by hand stay open after a failed ping.
 *
 * @param {Object} state GET /setup state.
 * @param {Object} step  Step entry from STEPS.
 * @return {boolean} Done.
 */
function stepDone( state, step ) {
	if ( ! step.config && state.complete ) {
		return true;
	}
	switch ( step.key ) {
		case 'n8n':
			return keyPasses( state );
		default:
			return !! state.steps[ step.key ];
	}
}

/**
 * Where the dialog opens: the first unfinished step, or the closing panel
 * once every step is done.
 *
 * @param {Object} state GET /setup state.
 * @return {string} Step key or FINISH.
 */
function resumeAt( state ) {
	const open = stepsFor( state ).find(
		( step ) => ! stepDone( state, step )
	);
	return open ? open.key : FINISH;
}

export default function SetupIsland() {
	const [ state, setState ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ current, setCurrent ] = useState( null ); // null = dialog closed
	const [ saving, setSaving ] = useState( false );
	const [ saveError, setSaveError ] = useState( '' );
	// The update notice's Re-import the workflow is then the page's primary action.
	const reimportShown = useReimportShown();

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

	// The path and the ticks follow the saved URL and the n8n connection
	// (GET /setup's rules), so once either changed the dialog re-reads the
	// state instead of patching it. It stays on the current step while the
	// new path has it, else it moves to the new path's first open step (from
	// the manual step, once a key passes on a site without a URL: Set up the
	// workflow). A failed read names the cause in the dialog.
	const reread = () =>
		apiFetch( { path: SETUP_PATH } ).then(
			( fresh ) => {
				setState( fresh );
				setCurrent( ( key ) =>
					! key ||
					key === FINISH ||
					stepsFor( fresh ).some( ( item ) => item.key === key )
						? key
						: resumeAt( fresh )
				);
			},
			( err ) =>
				setSaveError(
					( err && err.message ) ||
						__(
							'Could not load the setup guide. Reload the page and try again.',
							'post-quality-manager'
						)
				)
		);

	// A URL was saved (pinged or not): re-read the state.
	const onConnected = () => reread();

	// POST /n8n/provision saved the connected origin + /webhook/ + the path.
	const onProvisioned = ( pingOk ) =>
		setState( ( prev ) => ( {
			...prev,
			connected: true,
			provisioned: true,
			leftovers: false,
			steps: { ...prev.steps, provision: pingOk, manual: pingOk },
			// eslint-disable-next-line camelcase -- REST field name.
			webhook_url: `${ prev.n8n.base_url }/webhook/${ prev.webhook_path }`,
		} ) );

	const complete = !! ( state && state.complete );
	const steps = state ? stepsFor( state ) : STEPS;
	const index = steps.findIndex( ( step ) => step.key === current );
	const step = steps[ index ];
	const isDone = ( item ) => stepDone( state, item );

	const goTo = ( key ) => {
		setSaveError( '' );
		setCurrent( key );
	};

	// The island read or stored the connection: re-read the state once it
	// changed (a key connected or forgotten), not on the island's own read.
	const onN8nChange = ( n8n ) => {
		if ( ! sameConnection( n8n, state.n8n ) ) {
			reread();
		}
	};

	// "Set up by hand instead": store the choice, then reload the page on
	// its Workflow File section, whose choice line then shows. A failed
	// write stays in the dialog.
	const onByHand = () =>
		save( { manual: true } ).then( ( ok ) => {
			if ( ok ) {
				setCurrent( null );
				window.location.hash = WORKFLOW_FILE_ID;
				window.location.reload();
			}
		} );

	// The manual step's link: close the dialog, then bring the section into
	// view and move focus to its heading once the Modal has handed focus back.
	const onShowWorkflowFile = ( event ) => {
		event.preventDefault();
		setCurrent( null );
		window.setTimeout( () => {
			const heading = document.getElementById( WORKFLOW_FILE_ID );
			if ( heading ) {
				heading.scrollIntoView();
				heading.focus();
			}
		} );
	};

	const next = () => {
		if ( index < steps.length - 1 ) {
			goTo( steps[ index + 1 ].key );
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
				variant={ complete || reimportShown ? 'secondary' : 'primary' }
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
					<div className="pqm-setup-layout">
						<nav
							className="pqm-setup-steps"
							aria-label={ __(
								'Setup steps',
								'post-quality-manager'
							) }
						>
							<ol className="pqm-setup-nav">
								{ steps.map( ( item ) => (
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
											steps.length,
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
											onProvisioned={ onProvisioned }
											onN8nChange={ onN8nChange }
											onByHand={ onByHand }
											onShowWorkflowFile={
												onShowWorkflowFile
											}
											saving={ saving }
											onSave={ finishStep }
										/>
									) }
									<StepStatus
										step={ step }
										state={ state }
										done={ isDone( step ) }
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
											goTo( steps[ index - 1 ].key )
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
 * The step's Done mark: a status line for the steps the plugin verifies
 * and, once saved or skipped, for a configuration step.
 *
 * @param {Object}  props
 * @param {Object}  props.step  Step entry from STEPS.
 * @param {Object}  props.state GET /setup state.
 * @param {boolean} props.done  Whether the step is done.
 * @return {Element} Status line.
 */
function StepStatus( { step, state, done } ) {
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
	if ( step.key === 'n8n' ) {
		text = done
			? __( 'Done — connected to n8n.', 'post-quality-manager' )
			: __(
					'This step ticks itself once the connection check passes.',
					'post-quality-manager'
			  );
	} else if ( step.key === 'provision' || step.key === 'manual' ) {
		text = done
			? __( 'Done — the webhook URL is saved.', 'post-quality-manager' )
			: __(
					'This step ticks itself once a test ping gets through.',
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
