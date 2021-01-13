<?php

declare( strict_types = 1 );

namespace ReactAppLoader;

/**
 * Handles the creation of the virtual page where to react app will be located.
 */
class Virtual_Page {

	/**
	 * The slug to tell WordPress to stop handling so react can handle routing.
	 * This is relative to the root of the blog.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * The id of the root element we will be mounting our react app to.
	 *
	 * @var string
	 */
	private $root_id;

	/**
	 * The absolute path to the plugin directory that has the CRA based react app. Can also be a URL to a remote CRA base react app.
	 *
	 * @var string
	 */
	private $cra_directory;

	/**
	 * The user role required to view to view this page.
	 *
	 * @var string
	 */
	private $role;

	/**
	 * The key identifier for our app.
	 * Also used as the WordPress query variable to generate our virtual page.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * The callback.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * An array of subdirectories off of the defined slug that we DO WANT WordPress to handle.
	 *
	 * @var array
	 */
	private $wp_permalinks;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string   $slug            The slug to tell WordPress to stop handling so react can handle routing.
	 * @param string   $root_id         The id of the root element we will be mounting our react app to.
	 * @param string   $cra_directory   The absolute path to the plugin directory that has the CRA based react app. Can also be a URL to a remote CRA base react app.
	 * @param string   $role            The role required to view the page.
	 * @param callable $callback        The callback function that fires before assets are enqueued to the page.
	 * @param array    $wp_permalinks   An array of subdirectories off of the defined slug that we DO WANT WordPress to handle.
	 */
	public function create( $slug, $root_id, $cra_directory, $role, $callback, $wp_permalinks ) {
		$this->slug          = $slug;
		$this->root_id       = $root_id;
		$this->cra_directory = $cra_directory;
		$this->role          = $role;
		$this->key           = basename( $cra_directory );
		$this->callback      = $callback;
		$this->wp_permalinks = $wp_permalinks;

		$this->generate_page();
		$this->disable_wp_rewrite();
		$this->handle_request();
		$this->reserve_slug();
	}

	/**
	 * Create the virtual page the react app with live within.
	 */
	public function generate_page(): void {
		add_filter(
			'query_vars',
			function( $query_vars ) {
				$query_vars[] = $this->key;
				return $query_vars;
			}
		);

		add_filter(
			'generate_rewrite_rules',
			function ( $wp_rewrite ) {
				$wp_rewrite->rules = array_merge(
					[ $this->slug . '/?$' => 'index.php?' . $this->key . '=1' ],
					$wp_rewrite->rules
				);
			}
		);

		add_action(
			'template_redirect',
			function() {
				$query_var = intval( get_query_var( $this->key ) );

				if ( $query_var ) {
					// Update our theme body classes.
					$this->update_body_classes();

					// Display our page content.
					self::display_page_content();
					die;
				}
			}
		);
	}

	/**
	 * Prevent WordPress from thinking that react app routes are separate WordPress pages.
	 */
	public function disable_wp_rewrite(): void {
		$regex_pattern = '^' . $this->slug . '/(.*)$';

		if ( ! empty( $this->wp_permalinks ) ) {
			$ignored_permalinks = implode( '|', $this->wp_permalinks );
			$regex_pattern      = '^' . $this->slug . '/(?!' . $ignored_permalinks . ')(.*)$';
		}

		add_rewrite_rule(
			$regex_pattern,
			'index.php?' . $this->key . '=1',
			'top'
		);
	}

	/**
	 * Handles various aspects of updating requests to our virtual pages.
	 */
	public function handle_request(): void {
		add_filter(
			'request',
			function( $request ) {
				// Do nothing if this is not a request for a registered React app.
				if ( ! array_key_exists( $this->key, $request ) ) {
					return $request;
				}

				// Remove the trailing slash from our URL.
				self::remove_trailing_slash();

				/**
				 * Have WordPress ignore all URL query variables if the request is explicity for a
				 * registered React application.
				 *
				 * This prevents any conflicts with WordPress' reservered terms and query vars used
				 * by a registered React application.
				 *
				 * i.e.
				 *    `?p=3` may be intended to be page 3 of some paginated results within React
				 *     but WordPress thinks we want to go get a post with ID=3.
				 *
				 * @link https://codex.wordpress.org/Function_Reference/register_taxonomy#Reserved_Terms
				 */
				$react_app_loader_request = [
					$this->key => '1',
				];

				return $react_app_loader_request;
			}
		);
	}

	/**
	 * Prevent top level permalink slugs that will cause conflicts.
	 *
	 * New rewrite slugs are introduced when CPTs or Custom Taxonomies are created.
	 * This code adds a check when Pages or Posts are created, that their Permalink
	 * does not conflict with one of the reserved top level slugs you define.
	 *
	 * In the case of a bad (i.e conflicting slug), WordPress appends a "-2" to
	 * the permalink.
	 */
	public function reserve_slug(): void {
		add_filter( 'wp_unique_post_slug_is_bad_hierarchical_slug', [ $this, 'fe_prevent_slug_conflict' ], 10, 4 );
		add_filter( 'wp_unique_post_slug_is_bad_flat_slug', [ $this, 'fe_prevent_slug_conflict' ], 10, 3 );
	}

	/**
	 * Is this a top level permalink slug that conflicts with a reserved slug.
	 *
	 * @param bool   $is_bad_slug Is the slug being passed in already marked as a bad slug.
	 * @param string $slug The slug to be tested for uniqueness.
	 * @param string $post_type The post type the slug is going to be assigned to.
	 * @param int    $post_parent_id (optional) When the slug is for a heirarchical post type,
	 *                                       the post ID of the parent post.
	 * @return bool Does this slug conflict with an existing slug or reserved slug.
	 */
	public function fe_prevent_slug_conflict( $is_bad_slug, $slug, $post_type, $post_parent_id = 0 ) {
		$reserved_top_level_slugs = apply_filters(
			'fe_reserved_top_level_slugs',
			[ $this->slug ]
		);
		if (
			// Only check top level post slugs (i.e. not child posts).
			0 === $post_parent_id
			&& in_array( $slug, $reserved_top_level_slugs, true )
		) {
			$is_bad_slug = true;
		}
		return $is_bad_slug;
	}

	/**
	 * Add body class to theme to better identify this page type.
	 */
	public function update_body_classes() {
		add_filter(
			'body_class',
			function( $classes ) {
				$plugin_body_classes = [
					'react-app-loader',
					$this->key,
				];

				$updated_classes = array_merge( $classes, $plugin_body_classes );

				return $updated_classes;
			}
		);
	}

	/**
	 * Check if the string is a valid URL.
	 *
	 * @param string $possible_url String to check if a URL.
	 * @return boolean
	 */
	private static function is_url( string $possible_url ) {
		$parts = wp_parse_url( $possible_url );

		if ( isset( $parts['host'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Removes the trailing slash from current request URL.
	 */
	public static function remove_trailing_slash() {
		add_filter(
			'user_trailingslashit',
			function( $string ) {
				return untrailingslashit( $string );
			},
			1
		);
	}

	/**
	 * Handles the displaying of our virtual page content.
	 */
	public function display_page_content(): void {
		// Redirect user to homepage if they do not have permissions.
		$user = wp_get_current_user();

		// Redirect user to homepage if they do not have the required role and the React app does not explicity define `nopriv`.
		if ( ! in_array( $this->role, (array) $user->roles, true ) && 'nopriv' !== $this->role ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		$assets = new Assets();

		// Handle the loading of assets from a remote URL or a local plugin.
		if ( self::is_url( $this->cra_directory ) ) {
			$assets->enqueue_remote( $this->cra_directory );
		} else {
			$assets->enqueue( $this->cra_directory );
		}

		// Fire our callback if one is defined.
		if ( false !== $this->callback ) {
			call_user_func( $this->callback );
		}

		// Display the current theme's header.
		get_header();

		// Display the root element to mount our react app to.
		echo "<div id='$this->root_id'></div>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Display the current theme's footer.
		get_footer();
	}
}
