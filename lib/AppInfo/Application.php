<?php

namespace OCA\ExternalSwift\AppInfo;

use OCP\AppFramework\App;
use OCP\Files\External\Config\IBackendProvider;

class Application extends App implements IBackendProvider {

  public function __construct(array $urlParams = array()) {
    parent::__construct('externalswift', $urlParams);

    $container = $this->getContainer();

    $backendService = $container->getServer()->getStoragesBackendService();
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
