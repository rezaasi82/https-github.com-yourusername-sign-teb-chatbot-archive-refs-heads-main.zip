/**
 * Build configuration.
 *
 * Extends the @wordpress/scripts default so the WordPress externals, the
 * dependency-extraction plugin (which produces the `*.asset.php` manifests
 * `AssetManager` reads) and the JSX/TS toolchain all stay as shipped — only
 * the entry points and output paths are overridden.
 */

const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

module.exports = {
	...defaultConfig,

	entry: {
		app: path.resolve( __dirname, 'src/index.tsx' ),
		editor: path.resolve( __dirname, 'src/editor.ts' ),
	},

	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'assets/js' ),
		filename: '[name].js',
		// Chunks must resolve at runtime from the plugin URL, not from the
		// site root, or code splitting 404s on subdirectory installs.
		publicPath: 'auto',
	},

	resolve: {
		...defaultConfig.resolve,
		alias: {
			...( defaultConfig.resolve?.alias ?? {} ),
			'@': path.resolve( __dirname, 'src' ),
		},
		extensions: [ '.tsx', '.ts', '.jsx', '.js', '.json' ],
	},

	plugins: [
		// Drop the default CSS extractor so it can be replaced with one that
		// writes to assets/css instead of alongside the JS.
		...defaultConfig.plugins.filter(
			( plugin ) => plugin.constructor.name !== 'MiniCssExtractPlugin'
		),
		new MiniCssExtractPlugin( {
			filename: '../css/[name].css',
		} ),
	],
};
