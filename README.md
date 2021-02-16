# Findologic Plentymarkets export

[![Build Status](https://travis-ci.org/findologic/plentymarkets-rest-export.svg?branch=master)](https://travis-ci.org/findologic/plentymarkets-rest-export)

In order to start an export:
1. Copy `src/PlentyConfig.php.dist` to `src/PlentyConfig.php`.
2. Set the correct values in `src/index.php`.
3. Change directory to `src`.
4. Run `php index.php`.

## Deployment & Release

1. Ensure all necessary changes have been merged into the `develop` branch.
1. Merge all changes from `develop` into `main`.
1. Create a new release on GitHub. As branch use `develop`. This will **automatically** create
a tag for this repository.
1. Ensure that the **build passed successfully** and bump the version of the REST exporter in
the importer by running `composer require findologic/plentymarkets-rest-export:^1.35`.
   * Ensure that you specify `^` and **only** major and minor version e.g. `^1.35` or `^2.12`.
1. From here on follow the usual importer deployment procedure.
