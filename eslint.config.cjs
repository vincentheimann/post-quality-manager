/**
 * ESLint for `npm run lint:js` (src and tests/js, story 103).
 *
 * @wordpress/scripts 36+ lints unit tests with Vitest rules by default; the
 * tests here run on Jest, so this keeps the WordPress rules and lints the
 * tests with eslint-plugin-jest, as its "Keep an existing Jest suite" guide
 * gives (node_modules/@wordpress/scripts/docs/vitest-migration.md).
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );
const jestPlugin = require( 'eslint-plugin-jest' );

// The @wordpress/* packages WordPress serves at runtime: the project does not
// declare them (the build turns them into script handles, index.asset.php; the
// tests mock components, api-fetch and url).
// @wordpress/eslint-plugin exempted them from the import rules until 23.0.0;
// this keeps that exemption, with the same pattern (icons, interface and
// style-engine are bundled, not served, so they stay checked).
const WP_EXTERNALS = '^@wordpress/(?!(icons|interface|style-engine))';

module.exports = [
	{ ignores: [ '**/build/**', '**/node_modules/**', '**/vendor/**' ] },
	...wpPlugin.configs.recommended,
	{
		settings: { 'import/internal-regex': WP_EXTERNALS },
		rules: {
			'import/no-unresolved': [ 'error', { ignore: [ WP_EXTERNALS ] } ],
		},
	},
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
	{
		...jestPlugin.configs[ 'flat/recommended' ],
		files: [
			'**/@(test|__tests__)/**/*.{js,jsx,ts,tsx,mjs,cjs}',
			'**/*.@(test|spec).{js,jsx,ts,tsx,mjs,cjs}',
		],
		rules: {
			...jestPlugin.configs[ 'flat/recommended' ].rules,
			// An error, as in @wordpress/eslint-plugin's test-unit config before 27.
			'jest/expect-expect': 'error',
		},
	},
];
