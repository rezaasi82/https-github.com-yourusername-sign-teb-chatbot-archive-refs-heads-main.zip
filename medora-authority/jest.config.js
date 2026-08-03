/**
 * Jest configuration for the dashboard.
 *
 * Extends the @wordpress/scripts preset so the Babel transform, JSX handling
 * and jsdom environment come from the same toolchain as the build — a separate
 * transform pipeline for tests is how "passes in CI, breaks in the browser"
 * happens.
 */

const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,

	rootDir: __dirname,

	testMatch: [ '<rootDir>/src/**/*.test.[jt]s?(x)' ],

	moduleNameMapper: {
		...( defaultConfig.moduleNameMapper ?? {} ),
		// CSS imports carry no behaviour under test; without this, importing a
		// component that imports a stylesheet fails to parse.
		'\\.(css|scss)$': '<rootDir>/tests/js/style-mock.js',
		'^@/(.*)$': '<rootDir>/src/$1',
	},

	collectCoverageFrom: [
		'src/**/*.{ts,tsx}',
		'!src/**/*.test.{ts,tsx}',
		'!src/index.tsx',
		'!src/types.ts',
	],
};
