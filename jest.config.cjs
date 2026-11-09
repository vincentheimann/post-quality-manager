/**
 * Jest for the unit tests in tests/js (`npm run test:unit`, story 103).
 *
 * @wordpress/scripts 36+ no longer ships Jest or its WordPress defaults; this
 * is the setup its "Keep an existing Jest suite" guide gives
 * (node_modules/@wordpress/scripts/docs/vitest-migration.md). The
 * github-actions reporter annotates failures in CI, as scripts' own reporter
 * did before; it stays silent everywhere else.
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	reporters: [ 'default', 'github-actions' ],
	transform: {
		'\\.[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
};
