# OneCloud OpenStack Swift integration with Keystone 3 support

## Installation
Install this app in a custom location, per the [admin manual](https://doc.owncloud.org/server/9.1/admin_manual/installation/apps_management_installation.html#using-custom-app-directories).

Requires PHP 7. The official OwnCloud Docker image does not support PHP 7,
however there are [community images](https://hub.docker.com/r/bradjonesllc/docker-owncloud-php7/) available.

## Known issues

This app includes a more recent copy of the [OpenStack PHP SDK](https://github.com/php-opencloud/openstack)
than is used in OwnCloud core; Guzzle is contained in both. You will run
into some weirdness on the admin side as a result, however it doesn't appear
to affect most user-facing functionality. Until [this issue](https://github.com/owncloud/core/issues/20787)
is resolved, you will either have to live with the app store not being
able to make HTTP calls or try applying [this patch](https://patch-diff.githubusercontent.com/raw/owncloud/core/pull/25958.patch)
which blunts some of the pain.

## Using this app for primary OwnCloud storage

To use Swift object storage for the primary file location, add a section similar
to the following in your `config.php`, providing your own values of course:

```php
'objectstore' => array(
    'class' => 'OCA\\ExternalSwift\\Files\\Swift',
    'arguments' => array(
        'username' => getenv('SWIFT_USERNAME'),
        'password' => getenv('SWIFT_PASSWORD'),
        'bucket' => 'owncloud',
        'region' => 'some-region',
        'url' => 'https://auth-endpoint',
        'serviceName' => 'swift',
    ),
),
```

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

### Running tests (not yet implemented.)
You can use the provided Makefile to run all tests by using:

    make test

This will run the PHP unit and integration tests.

Of course you can also install [PHPUnit](http://phpunit.de/getting-started.html) and use the configurations directly:

    phpunit -c phpunit.xml

or:

    phpunit -c phpunit.integration.xml

for integration tests
