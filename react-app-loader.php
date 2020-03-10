<?php
/**
 * Plugin Name:       React App Loader
 * Description:       An mu-plugin that provides an API for loading React applications built with create-react-app into the front-end of WordPress.
 * Version:           1.3.0
 * Author:            Masonite
 * Author URI:        https://www.masonite.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       react-app-loader
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
defined( 'WPINC' ) || die();

/**
 * Autoload the plugin's classes.
 */
require_once __DIR__ . '/inc/autoload.php';
