# core

Pragma Core Module for the Pragma Framework (ORM, Models, View, Controllers)

For a project skeleton using this module, see: https://github.com/pragma-framework/framework

[![Build Status](https://travis-ci.org/pragma-framework/core.svg?branch=master)](https://travis-ci.org/pragma-framework/core)

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
