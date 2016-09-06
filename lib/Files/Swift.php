<?php

namespace OCA\ExternalSwift\Files;

use GuzzleHttp\Psr7\Stream;
use OCA\ExternalSwift\Storage\Swift as SwiftStorage;
use OCP\Files\ObjectStore\IObjectStore;
use OpenStack\ObjectStore\v1\Models\Object as SwiftObject;

class Swift extends SwiftStorage implements IObjectStore {

  /**
   * @var array
   */
  private $params;

  public function __construct($params) {
    // ObjectHomeMountProvider sets the user to the User object, which conflicts.
    // @todo - Not sure how exactly the default Swift integration handles that.
    $params['user'] = $params['username'];

    if (!isset($params['container'])) {
      $params['container'] = 'owncloud';
    }
    if (!isset($params['autocreate'])) {
      // should only be true for tests
      $params['autocreate'] = false;
    }

    parent::__construct($params);
  }

  /**
   * @return string the container name where objects are stored
   */
  public function getStorageId() {
    return $this->params['bucket'];
  }

  /**
   * @param string $urn the unified resource name used to identify the object
   * @param resource $stream stream with the data to write
   * @throws \Exception from openstack lib when something goes wrong
   */
  public function writeObject($urn, $stream) {
    $this->getContainer()->createObject([
      'name' => $urn,
      'stream' => new Stream($stream),
    ]);
  }

  /**
   * @param string $urn the unified resource name used to identify the object
   * @return resource stream with the read data
   * @throws \Exception from openstack lib when something goes wrong
   */
  public function readObject($urn) {
    /** @var SwiftObject $object */
    $object = $this->getContainer()->getObject($urn);

    $stream = $object->download()->detach();

    // save the object content in the context of the stream to prevent it being gc'd until the stream is closed
    stream_context_set_option($stream, 'swift', 'content', $object->retrieve());

    return $stream;
  }

  /**
   * @param string $urn Unified Resource Name
   * @return void
   * @throws \Exception from openstack lib when something goes wrong
   */
  public function deleteObject($urn) {
    $this->getContainer()->getObject($urn)->delete();
  }

}
