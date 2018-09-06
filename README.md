# core

Pragma Core Module for the Pragma Framework (ORM, Models, View, Controllers)

For a project skeleton using this module, see: https://github.com/pragma-framework/framework

![stable](https://badgen.net/github/release/pragma-framework/core/stable)
![packagist](https://badgen.net/packagist/v/pragma-framework/core)
[![Build Status](https://badgen.net/travis/pragma-framework/core)](https://travis-ci.org/pragma-framework/core)
![license](https://badgen.net/badge/license/MIT/blue)

## Installation

### Using composer

	$ composer require pragma-framework/core:dev-master

### Auto-migrate database

Add in composer.json:

	"scripts": {
		"post-package-install": [
			"Pragma\\Helpers\\Migrate::postPackageInstall"
		],
		"post-package-update": [
			"Pragma\\Helpers\\Migrate::postPackageUpdate"
		],
		"pre-package-uninstall": [
			"Pragma\\Helpers\\Migrate::prePackageUninstall"
		]
	}

These scripts run DB migration for core and all associated plugins (ex: pragma-framework/historic, ...)

## Run tests

	$ vendor/bin/phpunit --bootstrap ./tests/bootstrap.config.sqlite.php tests/
	$ vendor/bin/phpunit --bootstrap ./tests/bootstrap.config.mysql.php tests/
