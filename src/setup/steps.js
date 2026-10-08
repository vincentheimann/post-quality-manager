/**
 * Setup steps (story 36) — the seven steps of the setup dialog in order,
 * plus the closing "next steps" panel. n8n's labels are named as of the
 * n8n docs read 2026-10-07 (the dialog says layouts may differ); node
 * names match templates/n8n-workflow.json. The credentials copy also lives
 * in PHP on the n8n Workflow page (PQM_Admin::render_credentials_list(),
 * harness-checked); this one is checked by the owner's walk-through.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, TextControl } from '@wordpress/components';
import { createInterpolateElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import CopyValue from './copy-value';

const CONNECT_PATH = '/post-quality/v1/setup/connect';
const APP_PASSWORDS_PATH = '/wp/v2/users/me/application-passwords';

/**
 * The credential names the template expects (templates/n8n-workflow.json,
 * `__PQM_SITE_HOST__` filled in) — the same names the n8n Workflow page lists.
 *
 * @param {Object} state GET /setup state.
 * @return {{webhook: string, wordpress: string, claude: string}} Credential names.
 */
function credentialNames( state ) {
	return {
		webhook: `Post Quality webhook - ${ state.site_host }`,
		wordpress: `WordPress - ${ state.site_host }`,
		claude: 'Anthropic - Claude',
	};
}

/** Bold n8n label inside a translated sentence. */
const strong = { strong: <strong /> };

/**
 * Translated sentence with <strong> n8n labels.
 *
 * @param {string} text Translated text with <strong>…</strong> tags.
 * @return {Element} Interpolated element.
 */
function withLabels( text ) {
	return createInterpolateElement( text, strong );
}

function Instance() {
	return (
		<>
			<p>
				{ __(
					'The reviews run in n8n, an automation tool between this site and Claude. Pick one:',
					'post-quality-manager'
				) }
			</p>
			<ul className="pqm-setup-list">
				<li>
					{ withLabels(
						__(
							'<strong>n8n Cloud</strong> — sign up at n8n.io, nothing to install.',
							'post-quality-manager'
						)
					) }
				</li>
				<li>
					{ withLabels(
						__(
							'<strong>Self-hosted n8n</strong> — on your own server, for example with Docker.',
							'post-quality-manager'
						)
					) }
				</li>
			</ul>
			<p>
				{ __(
					"Both sides must reach each other over the network: this site calls n8n, and n8n calls back this site's REST API. A local or password-protected site cannot be reached from n8n Cloud.",
					'post-quality-manager'
				) }
			</p>
			<p>
				{ __(
					'You also need an Anthropic API key for Claude: create one in the Anthropic Console under API Keys. It goes into n8n in step 3.',
					'post-quality-manager'
				) }
			</p>
		</>
	);
}

function Import( { state } ) {
	return (
		<>
			<p>
				{ withLabels(
					__(
						'In n8n, open a new, empty workflow. Open the workflow menu — the three dots at the top right — and choose <strong>Import from URL</strong>. Paste this link:',
						'post-quality-manager'
					)
				) }
			</p>
			<CopyValue
				label={ __( 'Workflow import URL', 'post-quality-manager' ) }
				value={ state.import_url }
			/>
			<p className="pqm-muted">
				{ __(
					"It includes your webhook token, which also opens your n8n workflow's trigger — keep it private.",
					'post-quality-manager'
				) }
			</p>
			<p>
				{ withLabels(
					__(
						'Import from URL not working (for example, n8n cannot reach this site)? Download the file and choose <strong>Import from File</strong> in the same menu.',
						'post-quality-manager'
					)
				) }
			</p>
			<Button variant="secondary" href={ state.download_url }>
				{ __( 'Download n8n workflow', 'post-quality-manager' ) }
			</Button>
		</>
	);
}

/**
 * The WordPress credential's password: an Application Password created
 * through core's own REST route, whose answer carries it exactly once.
 *
 * @param {Object}  props
 * @param {boolean} props.available Whether core allows Application Passwords for this user.
 * @return {Element} Create button, the new password, or the manual path.
 */
function AppPassword( { available } ) {
	const [ password, setPassword ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );

	if ( ! available ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __(
					'Application Passwords are turned off on this site, so the plugin cannot create one here. Usually the site does not run on HTTPS; sometimes a security plugin turns them off. Switch the site to HTTPS, or allow Application Passwords in that plugin, then create one under Users → Profile → Application Passwords. You can still mark this step done: only Rewrite needs the WordPress credential.',
					'post-quality-manager'
				) }
			</Notice>
		);
	}

	if ( password ) {
		return (
			<>
				<CopyValue
					label={ __( 'Password', 'post-quality-manager' ) }
					value={ password }
				/>
				<p className="pqm-muted">
					{ __(
						'Shown once — paste it into n8n now. You can revoke it any time under Users → Profile.',
						'post-quality-manager'
					) }
				</p>
			</>
		);
	}

	const create = () => {
		setBusy( true );
		setError( '' );
		apiFetch( {
			path: APP_PASSWORDS_PATH,
			method: 'POST',
			// Core wants unique names per user; the time keeps a re-run valid.
			data: {
				name: `Post Quality Manager (n8n) ${ new Date().toLocaleString() }`,
			},
		} ).then(
			( data ) => {
				setBusy( false );
				setPassword( data.password );
			},
			( err ) => {
				setBusy( false );
				setError(
					( ( err && err.message ) ||
						__(
							'Could not create the Application Password.',
							'post-quality-manager'
						) ) +
						' ' +
						__(
							'Create one under Users → Profile → Application Passwords instead.',
							'post-quality-manager'
						)
				);
			}
		);
	};

	return (
		<>
			<p>
				{ __(
					'Password: an Application Password for your account. n8n edits posts with it when you run Rewrite, so your account must be allowed to edit posts.',
					'post-quality-manager'
				) }
			</p>
			<Button
				variant="secondary"
				onClick={ create }
				isBusy={ busy }
				disabled={ busy }
			>
				{ __(
					'Create an Application Password',
					'post-quality-manager'
				) }
			</Button>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</>
	);
}

function Credentials( { state } ) {
	const names = credentialNames( state );
	const nameLabel = __( 'Credential name', 'post-quality-manager' );

	return (
		<>
			<p>
				{ withLabels(
					__(
						'n8n keeps secrets in credentials. To add one, choose <strong>Create</strong> in the side menu, then <strong>Credential</strong>, search for the type and fill in the fields below. You can also create each one from its node in step 4.',
						'post-quality-manager'
					)
				) }
			</p>
			<p>
				{ __(
					'n8n names a new credential after its type, for example "Header Auth account". Rename each one to the credential name shown: it is the name the imported workflow expects, and it tells the two Header Auth credentials apart.',
					'post-quality-manager'
				) }
			</p>

			<section className="pqm-setup-credential">
				<h3>{ names.webhook }</h3>
				<CopyValue label={ nameLabel } value={ names.webhook } />
				<p>
					{ withLabels(
						__(
							'Type: <strong>Header Auth</strong>. Goes in the Webhook Trigger only, so only this site can start the workflow.',
							'post-quality-manager'
						)
					) }
				</p>
				<CopyValue
					label={ __( 'Name', 'post-quality-manager' ) }
					value="X-PQM-Token"
				/>
				<CopyValue
					label={ __( 'Value', 'post-quality-manager' ) }
					value={ state.token }
				/>
			</section>

			<section className="pqm-setup-credential">
				<h3>{ names.claude }</h3>
				<CopyValue label={ nameLabel } value={ names.claude } />
				<p>
					{ withLabels(
						__(
							'Type: <strong>Header Auth</strong>. Goes in the six Claude nodes.',
							'post-quality-manager'
						)
					) }
				</p>
				<CopyValue
					label={ __( 'Name', 'post-quality-manager' ) }
					value="x-api-key"
				/>
				<p>
					{ withLabels(
						__(
							'<strong>Value</strong>: your Anthropic API key.',
							'post-quality-manager'
						)
					) }
				</p>
			</section>

			<section className="pqm-setup-credential">
				<h3>{ names.wordpress }</h3>
				<CopyValue label={ nameLabel } value={ names.wordpress } />
				<p>
					{ withLabels(
						__(
							'Type: <strong>Basic Auth</strong>. Goes in Update WP Post (Rewrite).',
							'post-quality-manager'
						)
					) }
				</p>
				<CopyValue
					label={ __( 'User', 'post-quality-manager' ) }
					value={ state.user_login }
				/>
				<AppPassword available={ !! state.app_passwords } />
			</section>
		</>
	);
}

function Link( { state } ) {
	const names = credentialNames( state );

	return (
		<>
			<p>
				{ __(
					'n8n does not link credentials to an imported workflow by itself, so it marks these nodes "Credentials … are not set". Double-click each node, pick its credential in the credential field, then close the node:',
					'post-quality-manager'
				) }
			</p>
			<ul className="pqm-setup-list">
				<li>
					{ withLabels(
						sprintf(
							/* translators: %s: credential name, e.g. "Post Quality webhook - example.com" */
							__(
								'<strong>Webhook Trigger</strong> → %s',
								'post-quality-manager'
							),
							names.webhook
						)
					) }
				</li>
				<li>
					{ withLabels(
						sprintf(
							/* translators: %s: credential name "Anthropic - Claude" */
							__(
								'<strong>Claude - Review Agent</strong>, <strong>Claude - Takeaways Agent</strong>, <strong>Claude - Sources Agent</strong>, <strong>Claude - SEO Agent</strong>, <strong>Claude - Audit Agent</strong>, <strong>Claude - Rewrite Agent</strong> → %s',
								'post-quality-manager'
							),
							names.claude
						)
					) }
				</li>
				<li>
					{ withLabels(
						sprintf(
							/* translators: %s: credential name, e.g. "WordPress - example.com" */
							__(
								'<strong>Update WP Post (Rewrite)</strong> → %s',
								'post-quality-manager'
							),
							names.wordpress
						)
					) }
				</li>
			</ul>
			<p>
				{ withLabels(
					__(
						'You have two Header Auth credentials: check the name before you pick. No credential yet? Choose <strong>Create new credential</strong> in the same field.',
						'post-quality-manager'
					)
				) }
			</p>
		</>
	);
}

function Publish() {
	return (
		<>
			<p>
				{ withLabels(
					__(
						'Save the workflow, then click <strong>Publish</strong> in the header above the canvas (older n8n versions: switch the <strong>Active</strong> toggle on).',
						'post-quality-manager'
					)
				) }
			</p>
			<p>
				{ withLabels(
					__(
						'Double-click the <strong>Webhook Trigger</strong> node. The top of its panel shows <strong>Test URL</strong> and <strong>Production URL</strong>: choose <strong>Production URL</strong> and copy the address.',
						'post-quality-manager'
					)
				) }
			</p>
			<p className="pqm-muted">
				{ __(
					'The production URL contains /webhook/. A URL with /webhook-test/ only listens while the n8n editor is open, so this site refuses it.',
					'post-quality-manager'
				) }
			</p>
		</>
	);
}

/**
 * Save the webhook URL and ping it (POST /setup/connect).
 *
 * @param {Object}   props
 * @param {Object}   props.state       GET /setup state.
 * @param {Function} props.onConnected `( url, pingOk )` once the URL is stored.
 * @return {Element} The connect form and its outcome.
 */
function Connect( { state, onConnected } ) {
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
				// The page's version status sits outside this island: swap
				// in the block the ping refreshed.
				const block = document.querySelector( '.pqm-workflow-status' );
				if ( block && data.status_html ) {
					block.outerHTML = data.status_html;
				}
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
					placeholder="https://your-n8n.example.com/webhook/pqm-execute"
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
 * The seven steps, in order. `tickable` steps carry a Done checkbox;
 * the others are ticked by the plugin (ping, first result).
 */
export const STEPS = [
	{
		key: 'instance',
		title: __( 'Get an n8n instance', 'post-quality-manager' ),
		tickable: true,
		Body: Instance,
	},
	{
		key: 'import',
		title: __( 'Import the workflow', 'post-quality-manager' ),
		tickable: true,
		Body: Import,
	},
	{
		key: 'credentials',
		title: __( 'Create the three credentials', 'post-quality-manager' ),
		tickable: true,
		Body: Credentials,
	},
	{
		key: 'link',
		title: __( 'Link each credential to its node', 'post-quality-manager' ),
		tickable: true,
		Body: Link,
	},
	{
		key: 'publish',
		title: __(
			'Publish and copy the production URL',
			'post-quality-manager'
		),
		tickable: true,
		Body: Publish,
	},
	{
		key: 'connect',
		title: __( 'Paste the URL here', 'post-quality-manager' ),
		tickable: false,
		Body: Connect,
	},
	{
		key: 'review',
		title: __( 'Run your first review', 'post-quality-manager' ),
		tickable: false,
		Body: null, // FirstReview, rendered by the dialog (it owns completion).
	},
];

/** Closing panel once the setup is complete: the habit settings. */
export function NextSteps() {
	return (
		<>
			<p>
				{ __(
					'Setup complete. Reviews now run from Post Reviews and the Dashboard.',
					'post-quality-manager'
				) }
			</p>
			<p>
				{ __(
					'Two settings make them yours:',
					'post-quality-manager'
				) }
			</p>
			<ul className="pqm-setup-list">
				<li>
					<a href="admin.php?page=pqm-settings#pqm-context-settings">
						{ __(
							'Choose a Blog Profile',
							'post-quality-manager'
						) }
					</a>{ ' ' }
					{ __(
						'— reviews then fit your niche; Custom lets you describe your site in your own words.',
						'post-quality-manager'
					) }
				</li>
				<li>
					<a href="admin.php?page=pqm-settings#pqm-cron-settings">
						{ __(
							'Turn on scheduled reviews',
							'post-quality-manager'
						) }
					</a>{ ' ' }
					{ __(
						'— posts get reviewed in the background (Pro).',
						'post-quality-manager'
					) }
				</li>
			</ul>
			<Button variant="primary" href="admin.php?page=pqm-posts">
				{ __( 'Open Post Reviews', 'post-quality-manager' ) }
			</Button>
		</>
	);
}
