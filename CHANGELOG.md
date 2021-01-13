# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.4.0] - 2021-01-13

### Added

- Added support for loading a remote react app.

## [1.3.0] - 2020-03-10

### Added

- Added new theme body classes `react-app-loader` and `{plugin directory name}` for registered React app pages.

### Changed

- Removed trailing slash for registered React app pages

### Fixed

- Removed enqueue_scripts dependency of `react` &amp; `react-dom` from WordPress core since those are already bundled in create-react-app.

## [1.2.1] - 2020-03-03

### Fixed

- Fixed query var collisions between React application & WordPress.

## [1.2.0] - 2019-12-17

### Added

- New optional argument. An array of subdirectories off of the defined slug that we DO WANT WordPress to handle.

## [1.1.0] - 2019-11-27

### Added

- Callback argument.

## [1.0.0] - 2019-11-20

### Added

- Initial release of plugin.
