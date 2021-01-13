<?php
// phpcs:disable

declare( strict_types = 1 );

namespace ReactAppLoader;

/**
 * The core react integration class.
 */
class API {

	/**
	 * Provides an API for Create React App projects to register their page within WordPress.
	 *
	 * @param string   $slug            The slug to tell WordPress to stop handling so react can handle routing.
	 * @param string   $root_id         The id of the root element we will be mounting our react app to.
	 * @param string   $cra_directory   The absolute path to the plugin directory that has the CRA based react app. Can also be a URL to a remote CRA base react app.
	 * @param string   $role            The role required to view the page.
	 * @param callable $callback        The callback function that fires before assets are enqueued to the page.
	 * @param array    $wp_permalinks   An array of subdirectories off of the defined slug that we DO WANT WordPress to handle.
	 */
	public static function register( $slug, $root_id, $cra_directory, $role, $callback = false, $wp_permalinks = [] ): void {
		add_action(
			'init',
			function() use ( $slug, $root_id, $cra_directory, $role, $callback, $wp_permalinks ) {
				$virtual_page = new Virtual_Page();
				$virtual_page->create(
					$slug,
					$root_id,
					$cra_directory,
					$role,
					$callback,
					$wp_permalinks
				);
			}
		);
	}

}
