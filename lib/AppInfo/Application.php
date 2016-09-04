<?php

namespace OCA\ExternalSwift\AppInfo;

use OCA\Files_External\Lib\Config\IBackendProvider;
use \OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

class Application extends App implements IBackendProvider {

  /**
   * @{inheritdoc}
   */
  public function __construct(array $urlParams = array()) {
    parent::__construct('files_external', $urlParams);

    $container = $this->getContainer();

    $container->registerService('OCP\Files\Config\IUserMountCache', function (IAppContainer $c) {
      return $c->getServer()->query('UserMountCache');
    });

    $backendService = $container->query('OCA\\Files_External\\Service\\BackendService');
    $backendService->registerBackendProvider($this);
    $backendService->registerAuthMechanismProvider($this);

    // app developers: do NOT depend on this! it will disappear with oC 9.0!
    \OC::$server->getEventDispatcher()->dispatch(
      'OCA\\Files_External::loadAdditionalBackends'
    );
  }

  /**
   * @{inheritdoc}
   */
  public function getBackends() {
    $container = $this->getContainer();

    $backends = [
      $container->query('OCA\ExternalSwift\Backend\Swift'),
    ];

    return $backends;
  }

}
