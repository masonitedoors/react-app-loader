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
	 * @param string   $plugin_dir_path The absolute path to the plugin directory that contains the react app.
	 * @param string   $role            The role required to view the page.
	 * @param callable $callback        The callback function that fires before assets are enqueued to the page.
	 */
	public static function register( $slug, $root_id, $plugin_dir_path, $role, $callback = false ) : void {
		add_action(
			'init',
			function() use ( $slug, $root_id, $plugin_dir_path, $role, $callback ) {
				$virtual_page = new Virtual_Page();
				$virtual_page->create(
					$slug,
					$root_id,
					$plugin_dir_path,
					$role,
					$callback
				);
			}
		);
	}

}
