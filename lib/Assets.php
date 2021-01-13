<?php

declare( strict_types = 1 );

namespace ReactAppLoader;

/**
 * The main class to handle finding & enqueuing
 * our static assets.
 */
class Assets {

	/**
	 * Enqueue our react app assets.
	 *
	 * @param string $directory Root directory containing `src` and `build` directory.
	 * @param array  $opts {.
	 *     @type string $base_url Root URL containing `src` and `build` directory. Only needed for production.
	 *     @type string $handle   Style/script handle. (Default is last part of directory name.)
	 *     @type array  $scripts  Script dependencies.
	 *     @type array  $styles   Style dependencies.
	 * }
	 */
	public function enqueue( $directory, $opts = [] ) : void {
		$defaults = [
			'base_url' => '',
			'handle'   => basename( $directory ),
			'scripts'  => [],
			'styles'   => [],
		];

		$opts = wp_parse_args( $opts, $defaults );

		$opts['scripts'] = array_unique( $opts['scripts'] );
		$assets          = self::get_assets_list( $directory );
		$base_url        = $opts['base_url'];

		if ( empty( $base_url ) ) {
			$base_url = self::infer_base_url( $directory );
		}

		if ( empty( $assets ) ) {
			trigger_error( 'React App Loader: Unable to find React asset manifest.', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return;
		}

		// There will be at most one JS and one CSS file in vanilla Create React App manifests.
		$has_css = false;

		foreach ( $assets as $asset_path ) {
			$is_js      = preg_match( '/\.js$/', $asset_path );
			$is_css     = preg_match( '/\.css$/', $asset_path );
			$is_runtime = preg_match( '/(runtime|bundle)/', $asset_path );

			if ( ! $is_js && ! $is_css ) {
				// Assets such as source maps and images are also listed; ignore these.
				continue;
			}

			// Set a dynamic handle as we can have more than one JS entry point.
			// Treats the runtime file as primary to make setting dependencies easier.
			$handle = $opts['handle'] . ( $is_runtime ? '' : '-' . sanitize_key( basename( $asset_path ) ) );

			if ( $is_js ) {
				wp_enqueue_script(
					$handle,
					self::get_asset_uri( $asset_path, $base_url ),
					$opts['scripts'],
					null,
					true
				);
			} elseif ( $is_css ) {
				$has_css = true;
				wp_enqueue_style(
					$handle,
					self::get_asset_uri( $asset_path, $base_url ),
					$opts['styles']
				);
			}
		}

		// Ensure CSS dependencies are always loaded, even when using CSS-in-JS in
		// development.
		if ( ! $has_css ) {
			wp_register_style(
				$opts['handle'],
				null,
				$opts['styles']
			);

			wp_enqueue_style( $opts['handle'] );
		}
	}

	/**
	 * Enqueue our remote react app assets.
	 *
	 * @param string $base_url Base URL to our remote react app.
	 */
	public function enqueue_remote( $base_url ) : void {
		$url      = trailingslashit( $base_url ) . 'asset-manifest'; // .json delibritately omitted since we expect custom  node server endpoint.
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body   = wp_remote_retrieve_body( $response );
		$assets = json_decode( $body );

		if ( empty( $assets ) ) {
			return;
		}

		foreach ( $assets as $asset_url ) {
			$is_js  = preg_match( '/\.js$/', $asset_url );
			$is_css = preg_match( '/\.css$/', $asset_url );
			$handle = sanitize_key( basename( $asset_url ) );

			if ( $is_js ) {
				wp_enqueue_script( $handle, $asset_url, [], null, true );
			} elseif ( $is_css ) {
				wp_enqueue_style( $handle, $asset_url, [] );
			}
		}
	}

	/**
	 * Attempt to load a file at the specified path and parse its contents as JSON.
	 *
	 * @param string $path The path to the JSON file to load.
	 * @return array|null;
	 */
	public static function load_asset_file( $path ) {
		if ( ! file_exists( $path ) ) {
			return null;
		}

		$contents = file_get_contents( $path );

		if ( empty( $contents ) ) {
			return null;
		}

		return json_decode( $contents, true );
	}

	/**
	 * Check a directory for a root or build asset manifest file, and attempt to
	 * decode and return the asset list JSON if found.
	 *
	 * @param string $directory Root directory containing `src` and `build` directory.
	 * @return array|null;
	 */
	public static function get_assets_list( string $directory ) {
		$assets    = [];
		$directory = trailingslashit( $directory );

		// Check if asset-manifest.json is exists in the root of the react app or within a build subdirectory.
		$root_manifest = file_exists( $directory . 'build/asset-manifest.json' ) ? false : true;

		if ( $root_manifest ) {
			$assets = self::load_asset_file( $directory . 'asset-manifest.json' );
		} else {
			$assets = self::load_asset_file( $directory . 'build/asset-manifest.json' );
		}

		if ( ! empty( $assets ) ) {

			if ( ! array_key_exists( 'entrypoints', $assets ) ) {
				trigger_error( 'React App Loader: Entrypoints key was not found within your react app\'s asset-manifest.json. This may indicate that you are using an unsupported version of react-scripts. Your react app should be using react-scripts@3.2.0 or later.', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				return;
			}

			$filtered_assets = array_map(
				function( $value ) use ( $root_manifest ) {
					return $root_manifest ? $value : "build/$value";
				},
				array_values( $assets['entrypoints'] )
			);

			return $filtered_assets;
		}

		return null;
	}

	/**
	 * Infer a base web URL for a file system path.
	 *
	 * @param string $path Filesystem path for which to return a URL.
	 * @return string|null
	 */
	public static function infer_base_url( string $path ) {
		$path = wp_normalize_path( $path );
		$url  = trailingslashit( trailingslashit( plugins_url() ) . basename( $path ) );
		return $url;
	}

	/**
	 * Return web URIs or convert relative filesystem paths to absolute paths.
	 *
	 * @param string $asset_path A relative filesystem path or full resource URI.
	 * @param string $base_url   A base URL to prepend to relative bundle URIs.
	 * @return string
	 */
	public static function get_asset_uri( string $asset_path, string $base_url ) {
		if ( strpos( $asset_path, '://' ) !== false ) {
			return $asset_path;
		}

		return trailingslashit( $base_url ) . $asset_path;
	}
}
