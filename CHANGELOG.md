owncloud-externalswift

## [0.0.2] 2018-02-27

### Added

    - Support for multi-domain in Keystone authentification
    - Indent errors, everywhere...

### Fixed

    - Added check for object existence using library method (Catching 404 fail, will have to check)
        
### Changed

    - Dependencies updated to php-opencloud/openstack 3.0.4 (Fix infinite loop bug)
    - Updated various part of the code to be more coherent with current owncloud packages
    - Various cleanup


### Removed

    - Support for Rackspace auth


