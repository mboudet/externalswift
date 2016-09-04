<?php

namespace OCA\ExternalSwift\AppInfo;

use OC\Server;
use OCA\Files_External\Lib\Config\IBackendProvider;
use OCA\Files_External\Service\BackendService;
use \OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;

class Application extends App implements IBackendProvider {

  /**
   * @{inheritdoc}
   */
  public function __construct(array $urlParams = array()) {
    parent::__construct('externalswift', $urlParams);

    $container = $this->getContainer();

    $container->registerService('OCP\Files\Config\IUserMountCache', function (IAppContainer $c) {
      return $c->getServer()->query('UserMountCache');
    });

    // We need to get the service off the files_external container.
    /** @var Server $server */
    $server = $container->getServer();
    $filesContainer = $server->getAppContainer('files_external');
    /** @var BackendService $backendService */
    $backendService = $filesContainer->query('OCA\\Files_External\\Service\\BackendService');
    $backendService->registerBackendProvider($this);
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
