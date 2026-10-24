/**
 * Configuration steps (story 55) — after the first review, the setup dialog
 * walks the Blog Profile, the Editorial Standards and scheduled reviews.
 * Each body shows the stored choice from GET /setup and follows the
 * Settings page's control model (story 26): a preset is a read-only choice
 * whose text shows muted, Custom is the one editable path and starts from
 * the stored custom text. A body saves through `onSave( data )` (the
 * dialog posts it to POST /setup/settings, ticks the step and moves on);
 * Skip lives in the dialog's footer and writes no setting.
 */
import {
	Button,
	Notice,
	RadioControl,
	TextareaControl,
} from '@wordpress/components';
import { createInterpolateElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { commandLabel } from '../kit/copy';

const SETTINGS_URL = 'admin.php?page=pqm-settings';
// The same address as PHP's PQM_PRICING_URL; the render-matrix harness fails when they differ.
const PRICING_URL = 'https://postquality.com/pricing/';

/**
 * A preset choice with its Custom text (Blog Profile, Editorial Standards).
 *
 * @param {Object}   props
 * @param {string}   props.why         One-line reason the setting matters.
 * @param {string}   props.label       Radio group label.
 * @param {string}   props.textLabel   Custom textarea label.
 * @param {Array}    props.options     `[{ value, label, text }]` from GET /setup.
 * @param {string}   props.choiceKey   Settings key of the radio.
 * @param {string}   props.textKey     Settings key of the Custom text.
 * @param {Object}   props.state       GET /setup state.
 * @param {string}   props.placeholder Custom textarea placeholder.
 * @param {string}   props.emptyHint   What the AI assumes for an empty text.
 * @param {boolean}  props.saving      Whether a save is in flight.
 * @param {Function} props.onSave      `( data )` saves and moves on.
 * @return {Element} The step body.
 */
function PresetChoice( {
	why,
	label,
	textLabel,
	options,
	choiceKey,
	textKey,
	state,
	placeholder,
	emptyHint,
	saving,
	onSave,
} ) {
	const [ choice, setChoice ] = useState( state[ choiceKey ] );
	const [ text, setText ] = useState( state[ textKey ] );
	const preset = options.find( ( item ) => item.value === choice );
	const isCustom = choice === 'custom' || ! preset;

	// A preset saves its key alone; Custom saves the key and its text.
	const save = () =>
		onSave(
			isCustom
				? { [ choiceKey ]: 'custom', [ textKey ]: text }
				: { [ choiceKey ]: choice }
		);

	return (
		<>
			<p>{ why }</p>
			<RadioControl
				label={ label }
				selected={ isCustom ? 'custom' : choice }
				options={ options.map( ( item ) => ( {
					value: item.value,
					label: item.label,
				} ) ) }
				onChange={ setChoice }
			/>
			{ isCustom ? (
				<TextareaControl
					label={ textLabel }
					help={ emptyHint }
					value={ text }
					onChange={ setText }
					placeholder={ placeholder }
					rows={ 4 }
					__nextHasNoMarginBottom
				/>
			) : (
				<p className="pqm-muted pqm-setup-preset" aria-live="polite">
					{ preset.text }
				</p>
			) }
			<div className="pqm-setup-save">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save and continue', 'post-quality-manager' ) }
				</Button>
			</div>
		</>
	);
}

function Profile( props ) {
	return (
		<PresetChoice
			{ ...props }
			why={ __(
				'Who you write for. Reviews and rewrites then judge your posts for your niche and your readers.',
				'post-quality-manager'
			) }
			label={ __( 'Blog Profile', 'post-quality-manager' ) }
			textLabel={ __( 'Website Context', 'post-quality-manager' ) }
			options={ props.state.profiles }
			choiceKey="blog_profile"
			textKey="website_context"
			placeholder={ __(
				'e.g. A blog about home automation for tech-savvy homeowners. Readers want hands-on tutorials, honest product experiences, and privacy-friendly setups.',
				'post-quality-manager'
			) }
			emptyHint={ __(
				'Describe your site and readers in two or three sentences. Empty: the AI assumes "a general audience blog".',
				'post-quality-manager'
			) }
		/>
	);
}

function Standards( props ) {
	return (
		<PresetChoice
			{ ...props }
			why={ __(
				'The voice and rules every post follows. Reviews check them, rewrites apply them.',
				'post-quality-manager'
			) }
			label={ __( 'Editorial Standards', 'post-quality-manager' ) }
			textLabel={ __(
				'Your Editorial Standards',
				'post-quality-manager'
			) }
			// eslint-disable-next-line camelcase -- REST field name.
			options={ props.state.voice_presets }
			choiceKey="brand_voice_preset"
			textKey="brand_voice"
			placeholder={ __(
				'e.g. Active voice, short paragraphs, no buzzwords. Back every statistic with a source.',
				'post-quality-manager'
			) }
			emptyHint={ __(
				'Empty: the AI falls back to "professional yet accessible, active voice".',
				'post-quality-manager'
			) }
		/>
	);
}

/**
 * Scheduled reviews: one click turns them on with the stored knobs (the
 * defaults on a new site), for a pro site and a user who manages agents.
 *
 * @param {Object}   props
 * @param {Object}   props.state  GET /setup state.
 * @param {boolean}  props.saving Whether a save is in flight.
 * @param {Function} props.onSave `( data )` saves and moves on.
 * @return {Element} The step body.
 */
function Schedule( { state, saving, onSave } ) {
	const why = (
		<p>
			{ __(
				'Scheduled reviews re-check your posts in the background, so older posts stay sharp without a click.',
				'post-quality-manager'
			) }
		</p>
	);
	// Role first, then plan — like the Settings page (hidden beats locked).
	// eslint-disable-next-line camelcase -- REST field name.
	if ( ! state.can_manage_agents ) {
		return (
			<>
				{ why }
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Scheduled reviews are set in Settings → Automation, which your role does not include. Ask an administrator to turn them on.',
						'post-quality-manager'
					) }
				</Notice>
			</>
		);
	}

	// eslint-disable-next-line camelcase -- REST field name.
	if ( ! state.is_pro ) {
		return (
			<>
				{ why }
				<Notice status="info" isDismissible={ false }>
					{ state.cron_enabled // eslint-disable-line camelcase -- REST field name.
						? __(
								'Scheduled reviews are set up on this site, but changing their settings needs Pro.',
								'post-quality-manager'
						  )
						: __(
								'Scheduled reviews are available with Pro.',
								'post-quality-manager'
						  ) }{ ' ' }
					<a href={ PRICING_URL }>
						{ __( 'Get Pro', 'post-quality-manager' ) }
					</a>
				</Notice>
			</>
		);
	}

	/* eslint-disable camelcase -- REST field names. */
	const knobs = sprintf(
		/* translators: 1: agent label, e.g. "Review"; 2: "every day" or "every week"; 3: posts per run; 4: score 0–100; 5: days */
		__(
			'%1$s runs %2$s on up to %3$d posts: first those it has not run on yet, then those whose latest result scores below %4$d or is older than %5$d days.',
			'post-quality-manager'
		),
		commandLabel( state.cron_command ),
		state.cron_frequency === 'weekly'
			? __( 'every week', 'post-quality-manager' )
			: __( 'every day', 'post-quality-manager' ),
		state.cron_batch_size,
		state.cron_min_score,
		state.cron_max_age_days
	);
	const enabled = state.cron_enabled;
	/* eslint-enable camelcase */

	return (
		<>
			{ why }
			<p>
				{ enabled
					? __( 'Scheduled reviews are on.', 'post-quality-manager' )
					: __(
							'Turn them on with these settings:',
							'post-quality-manager'
					  ) }{ ' ' }
				{ knobs }
			</p>
			<p>
				{ createInterpolateElement(
					__(
						'Tune them any time in <a>Settings → Automation</a>.',
						'post-quality-manager'
					),
					// eslint-disable-next-line jsx-a11y/anchor-has-content -- the text comes from the translation.
					{ a: <a href={ `${ SETTINGS_URL }#pqm-cron-settings` } /> }
				) }
			</p>
			{ ! enabled && (
				<div className="pqm-setup-save">
					<Button
						variant="primary"
						// eslint-disable-next-line camelcase -- REST field name.
						onClick={ () => onSave( { cron_enabled: true } ) }
						isBusy={ saving }
						disabled={ saving }
					>
						{ __(
							'Turn on scheduled reviews',
							'post-quality-manager'
						) }
					</Button>
				</div>
			) }
		</>
	);
}

/** The three configuration steps, in order (the dialog appends them). */
export const CONFIG_STEPS = [
	{
		key: 'profile',
		title: __( 'Choose your Blog Profile', 'post-quality-manager' ),
		config: true,
		Body: Profile,
	},
	{
		key: 'standards',
		title: __( 'Set your Editorial Standards', 'post-quality-manager' ),
		config: true,
		Body: Standards,
	},
	{
		key: 'schedule',
		title: __( 'Turn on scheduled reviews', 'post-quality-manager' ),
		config: true,
		Body: Schedule,
	},
];
