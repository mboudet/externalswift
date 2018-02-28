# Owncloud OpenStack Swift integration with Keystone 3 support

Based on https://github.com/BradJonesLLC/externalswift

## Installation
Install this app in a custom location, per the [admin manual](https://doc.owncloud.org/server/10.0/admin_manual/installation/apps_management_installation.html).

Requires PHP 7. The official OwnCloud Docker image does not support PHP 7,
Owncloud is compatible with PHP7, but is shipped with Php 5.6 for now.


## Known issues

This app use the Guzzle library 6.3, while owncloud use 5.3.
Changes from 5 -> 6 induce a modification in the way request() works, inducing errors the logs.
Support for Guzzle 6 seems to be planned for Owncloud 10.1..

This pull request (https://github.com/owncloud/core/pull/29773) seems to make it works, but might break other apps.


## For developers

### Building the app

The app can be built by using the provided Makefile by running:

    make

This requires the following things to be present:
* make
* which
* tar: for building the archive
* curl: used if phpunit and composer are not installed to fetch them from the web
* npm: for building and testing everything JS, only required if a package.json is placed inside the **js/** folder

The make command will install or update Composer dependencies.
