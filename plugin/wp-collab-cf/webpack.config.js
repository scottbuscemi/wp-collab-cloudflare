const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	resolve: {
		...( defaultConfig.resolve || {} ),
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			// Point 'yjs' imports to a tiny shim that reads from globalThis.Yjs
			// at runtime, avoiding bundling a second copy of the library.
			yjs: path.resolve( __dirname, 'src/yjs-shim.js' ),
		},
	},
};
