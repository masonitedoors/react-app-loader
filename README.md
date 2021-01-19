# React App Loader

[![React App Loader on Packagist](https://img.shields.io/packagist/v/masonitedoors/react-app-loader.svg?style=flat)](https://packagist.org/packages/masonitedoors/react-app-loader) [![Build Status](https://travis-ci.org/masonitedoors/react-app-loader.svg?branch=master)](https://travis-ci.org/masonitedoors/react-app-loader)

> An mu-plugin that provides an API for loading React applications built with [create-react-app](https://github.com/facebook/create-react-app) into the front-end of WordPress.

## Requirements

- PHP >= 7.1
- WordPress >= 5.0
- React application built using Create React App >= [3.2](https://github.com/facebook/create-react-app/releases/tag/v3.2.0)

## Setup

### Loader

Add the plugin to your site's [mu-plugins](https://wordpress.org/support/article/must-use-plugins/) directory.

This plugin is also available to install via [composer](https://getcomposer.org/).

```sh
composer require masonitedoors/react-app-loader
```

### React App

While this loader does not require any specific structure to your React application, it does require the React app to be loaded as a WordPress plugin. In order to do this, we will need to add a single PHP file so WordPress can recognize it as a WordPress plugin. Although the PHP file can be named anything, the filename should match the nomenclature of the React app directory name in order to follow [WordPress plugin development best practices](https://developer.wordpress.org/plugins/plugin-basics/best-practices/).

```text
/react-app-name
     /public
     /src
     package.json
     package-lock.json
     README.md
     react-app-name.php
     yarn.lock
```

This PHP file (e.g. `react-app-name.php`) [should include header comment](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/) what tells WordPress that a file is a plugin and provides information about the plugin.

Example:

```php
<?php
/**
 * Plugin Name:       My React App
 * Description:       A brief description of what this plugins does.
 * Version:           1.0.0
 * Author:            Masonite
 * Author URI:        https://www.masonite.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
```

## PHP Interface

### `ReactAppLoader\API::register()`

The `register` method has 4 required parameters and should be called within the [`plugins_loaded`](https://codex.wordpress.org/Plugin_API/Action_Reference/plugins_loaded) action.

| Parameter         | Type     | Description                                                                                                                                                                                                                                                                                                               |
| :---------------- | :------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| \$slug            | string   | The page slug where the React app will live on the site. The loader will also reserve this slug with WordPress, preventing any new posts from being made at the same URL. If any existing posts share the defined slug, they will not be able to be accessed on the front-end of the site once rewrite rules are flushed. |
| \$root_id         | string   | The id of the root element that the React app should mount to. By default, Create React App has this as `'root'`.                                                                                                                                                                                                         |
| \$cra_directory   | string   | The absolute path to the plugin directory that contains the react app. In most situations, this should be `plugin_dir_path( __FILE__ )`. This can also be a URL to the base directory of a React app on a different website.                                                                                              |
| \$role            | string   | The WordPress user role required to view the page. If a user tries to access the page without this role, they will be redirected to the site's [home_url()](https://developer.wordpress.org/reference/functions/home_url/). If no authentication is needed, this should be set as `'nopriv'`.                             |
| \$callback        | callable | Optional callback function. This is only fired on the registered page before the React app assets are enqueued.                                                                                                                                                                                                           |
| \$wp_permalinks   | array    | Optional array of subdirectories off of the defined slug that we DO WANT WordPress to handle.                                                                                                                                                                                                                             |

## Usage

With the loader installed as an mu-plugin, we can utilize the `ReactAppLoader\API::register()` method to register our React app WordPress plugin with the loader.

```php
add_action( 'plugins_loaded', function() {
    \ReactAppLoader\API::register(
        'my-react-app-slug',
        'root',
        plugin_dir_path( __FILE__ ),
        'administrator'
    );
});
```
### URL Structure

When a React plugin is registered with this loader plugin, a [virtual page](https://metabox.io/how-to-create-a-virtual-page-in-wordpress/) is created within WordPress. As a result, this new page will not show up within the regular pages/posts list in wp-admin. Because of the nature of creating a virtual page by adding new rewrite rules to WordPress, the rewrite rules will need to be flushed before the new page will be accessible.

#### Flushing WordPress Rewrite Rules

You'll need to refresh your site's rewrite rules in the database before you will see any React apps registered with the loader. This can be done by visiting your site's permalinks settings page in the admin area.

> Visiting the Permalinks screen triggers a flush of rewrite rules

[Settings → Permalinks](https://codex.wordpress.org/Settings_Permalinks_Screen#Save_Changes)

Rewrite rules can also be flushed via [WP-CLI](https://developer.wordpress.org/cli/commands/rewrite/flush/).

```sh
wp rewrite flush
```

#### Trailing Slash

Trailing slash has been removed for registerd React app pages. This was done in an effort to create consistency in behavior with create-react-app's node-server structure (the environment that fires up when you run `npm start`).

### Remote React App Support

Support for loading React apps hosted on other websites is available as of version __1.4.0__.

This can be achived by using a URL to the root of the React app For the 3rd argument in the register method.
If your React app's asset-manifest.json is https://example.org/my-react-app/asset-manifest.json, use the first part of the URL (omit asset-manifest.json).

```php
add_action( 'plugins_loaded', function() {
    \ReactAppLoader\API::register(
        'my-react-app-slug',
        'root',
        'https://example.org/my-react-app/',
        'nopriv'
    );
});
```

## Recommendations

### Using Images Within Your React App

It is up the React app to ensure that any images used are using absolute paths. It is recommended to use a digital asset management service such as [Widen](https://www.widen.com/). Any images using relative paths within the React app will be broken when the app is loaded within WordPress.

### Avoiding Conflicting Styles

When the React app is loaded from within WordPress, there is potential for styling conflicts introduced from theme/plugin CSS. It is recommended that you scope your React app's base styles within the React app at a root component using [CSS Modules](https://facebook.github.io/create-react-app/docs/adding-a-css-modules-stylesheet).

App.js:

```jsx
import styles from "./App.module.scss";

function App() {
  return <div className={styles.App}>...</div>;
}
```

App.module.scss:

```scss
// App root styles.
.App {
  ...
  // Our apps base element styles. These will be globally scoped under App.
  :global {
    h1 {
      ...
    }
    p {
      ...
    }
    a {
      ...
    }
    em {
      ...
    }
  }
}
```

Once implemented, our base styles will now be scoped under the root hashed component:

<img src="data:image/png;base64,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" alt="" style="margin-bottom:30px">

[![Edit this example on codesandbox](https://codesandbox.io/static/img/play-codesandbox.svg)](https://codesandbox.io/s/kxm1r32j3?hidenavigation=1&module=%2Fsrc%2FApp.module.scss)

## Common Issues

**I am getting a 404 when hitting the page slug I registered.**

Verify that your React app WordPress plugin has been activated. Your site's rewrite rules might not have been flushed or flushed properly. See [Flushing WordPress Rewrite Rules](#flushing-wordpress-rewrite-rules) for instructions.

**I am able to hit the page slug I registered, but my React app is not loading.**

This could be happening from a few things:

1.  If your React app is using react router, you may need to specify a basename. This should be the exact same value as the slug registerd with the loader. See [Browser Router](https://github.com/ReactTraining/react-router/blob/master/packages/react-router-dom/docs/api/BrowserRouter.md).

2.  The loader relies on the newer asset-manifest.json structure that was introduced in [create-react-app v3.2.0](https://github.com/facebook/create-react-app/releases/tag/v3.2.0). If your React app was built using an eariler version of create-react-app, you will need to update your React app.

3.  The asset-manfiest.json could not be found at all within your React app. Most likely your React app was never built or the build failed.

**I am being redirected to my site's homepage when trying to access the page slug I registered.**

This happens when your current WordPress user does not have the same role that was defined when registering with the loader.

## Credit

This plugin is based off the great work done by [humanmade/react-wp-scripts](https://github.com/humanmade/react-wp-scripts).
