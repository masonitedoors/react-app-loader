<?php
// phpcs:disable

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
	 * @param array $opts {
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
			'scripts'  => [
				'react',
				'react-dom',
			],
			'styles'   => [],
		];

		$opts = wp_parse_args( $opts, $defaults );

		// Ensure react & react-dom are dependencies.
		$opts['scripts'] = array_merge( $opts['scripts'], [ 'react', 'react-dom' ] );
		$opts['scripts'] = array_unique( $opts['scripts'] );
		$assets          = self::get_assets_list( $directory );
		$base_url        = $opts['base_url'];

		if ( empty( $base_url ) ) {
			$base_url = self::infer_base_url( $directory );
		}

		if ( empty( $assets ) ) {
			trigger_error( 'React App Loader: Unable to find React asset manifest.', E_USER_WARNING );
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
		$directory = trailingslashit( $directory );

		$assets = self::load_asset_file( $directory . 'build/asset-manifest.json' );

		if ( ! empty( $assets ) ) {

			if ( ! array_key_exists( 'entrypoints', $assets ) ) {
				trigger_error( 'React App Loader: Entrypoints key was not found within your react app\'s asset-manifest.json. This may indicate that you are using an unsupported version of react-scripts. Your react app should be using react-scripts@3.2.0 or later.', E_USER_WARNING );
				return;
			}

			$filtered_assets = array_map(
				function( $value ) {
					return "build/$value";
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
		$url = trailingslashit( trailingslashit( plugins_url() ) . basename( $path ) );
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