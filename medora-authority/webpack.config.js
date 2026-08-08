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
const RtlCssPlugin = require( 'rtlcss-webpack-plugin' );
const { CleanWebpackPlugin } = require( 'clean-webpack-plugin' );

/**
 * The stylesheet name `AssetManager` enqueues, given a webpack chunk name.
 *
 * A file named `style.css` is pulled into its own chunk by the wp-scripts
 * default config and named `./style-<entry>`, so `src/style.css` imported by
 * the `app` entry would emit `assets/css/style-app.css` while
 * `AssetManager::enqueueApp()` asks for `assets/css/app.css` — a stylesheet
 * that 404s and a dashboard with no styling at all. Naming it after its entry
 * keeps the two in step.
 */
const stylesheet = ( chunkName ) =>
	'../css/' + chunkName.replace( /^\.\/style-/, '' ) + '.css';

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
		// Webpack skips writing an output file whose bytes are unchanged, which
		// leaves its mtime behind the sources it was built from. `bin/package.php`
		// refuses to ship a bundle older than src/, and would then refuse a
		// build that had in fact just run. Always writing makes that check
		// mean what it says.
		compareBeforeEmit: false,
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
		// Drop the default CSS extractors so they can be replaced with ones
		// that write to assets/css instead of alongside the JS.
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'MiniCssExtractPlugin' &&
				plugin.constructor.name !== 'RtlCssPlugin' &&
				plugin.constructor.name !== 'CleanWebpackPlugin'
		),
		// Same as the default, except that `.gitkeep` survives. It is what
		// keeps assets/js/ in the repository, and the stock cleaner deletes it
		// on every build — leaving a deletion in `git status` that someone
		// eventually commits, after which a fresh clone has no directory for
		// the bundle.
		new CleanWebpackPlugin( {
			cleanOnceBeforeBuildPatterns: [ '**/*', '!.gitkeep' ],
			cleanStaleWebpackAssets: false,
		} ),
		new MiniCssExtractPlugin( {
			filename: ( { chunk } ) => stylesheet( chunk.name ),
		} ),
		// `wp_style_add_data( $handle, 'rtl', 'replace' )` swaps `app.css` for
		// `app-rtl.css` in the same directory, so the RTL sheet is derived from
		// the name the LTR one was actually emitted under. The default template
		// resolves against `output.path` (assets/js), which put it somewhere
		// WordPress would never look — and the Persian dashboard is the common
		// case here, not the edge one.
		new RtlCssPlugin( {
			filename: ( { cssFileName } ) =>
				cssFileName.replace( /\.css$/, '-rtl.css' ),
		} ),
	],
};
