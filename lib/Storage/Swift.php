<?php
/**
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Benjamin Liles <benliles@arch.tamu.edu>
 * @author Christian Berendt <berendt@b1-systems.de>
 * @author Daniel Tosello <tosello.daniel@gmail.com>
 * @author Felix Moeller <mail@felixmoeller.de>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Martin Mattel <martin.mattel@diemattels.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Philipp Kapfer <philipp.kapfer@gmx.at>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Tim Dettrick <t.dettrick@uq.edu.au>
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Brad Jones <brad@bradjonesllc.com>
 *
 * @copyright Copyright (c) 2016, ownCloud GmbH.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\ExternalSwift\Storage;

use Guzzle\Http\Exception\ClientErrorResponseException;
use GuzzleHttp\Psr7\Stream;
use Icewind\Streams\IteratorDirectory;
use OpenStack\ObjectStore\v1\Models\Container;
use OpenStack\ObjectStore\v1\Models\Object as SwiftObject;
use OpenStack\ObjectStore\v1\Service;
use OpenStack\OpenStack;

class Swift extends \OCP\Files\Storage\StorageAdapter {

	/**
	 * @var Service./
	 */
	private $connection;
	/**
	 * @var Container
	 */
	private $container;
	/**
	 * @var OpenStack
	 */
	private $anchor;
	/**
	 * @var string
	 */
	private $bucket;
	/**
	 * Connection parameters
	 *
	 * @var array
	 */
	private $params;
	/**
	 * @var array
	 */
	private static $tmpFiles = [];

	/**
	 * Key value cache mapping path to data object. Maps path to
	 * \OpenCloud\OpenStack\ObjectStorage\Resource\DataObject for existing
	 * paths and path to false for not existing paths.
	 * @var \OCP\ICache
	 */
	private $objectCache;

	/**
	 * @param string $path
	 */
	private function normalizePath($path) {
		$path = trim($path, '/');

		if (!$path) {
			$path = '.';
		}

		$path = str_replace('#', '%23', $path);

		return $path;
	}

	const SUBCONTAINER_FILE = '.subcontainers';

	/**
	 * Fetches an object from the API.
	 * If the object is cached already or a
	 * failed "doesn't exist" response was cached,
	 * that one will be returned.
	 *
	 * @param string $path
	 * @return object|bool object
	 * or false if the object did not exist
	 */
	private function fetchObject($path) {
		if ($this->objectCache->hasKey($path)) {
			// might be "false" if object did not exist from last check
			return $this->objectCache->get($path);
		}

        if (! $this->getContainer()->objectExists($path) ) {
            $this->objectCache->set($path, false);
            return false;
        }

		try {
			$object = $this->getContainer()->getObject($path);
			$object->retrieve();
			$this->objectCache->set($path, $object);
			return $object;

		} catch (ClientErrorResponseException $e) {
            /** Should not happen, 404 is tested for above */

            \OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
			$this->objectCache->set($path, false);
			return false;
		}
	}

	/**
	 * Returns whether the given path exists.
	 *
	 * @param string $path
	 *
	 * @return bool true if the object exist, false otherwise
	 */
	private function doesObjectExist($path) {
		return $this->fetchObject($path) !== false;
	}

	public function __construct($params) {

/**
		if ((empty($params['key']) and empty($params['password']))
			or empty($params['user']) or empty($params['bucket'])
			or empty($params['region'])
		) {
			throw new \Exception("API Key or password, Username, Bucket and Region have to be configured.");
		}
*/
		$this->id = 'swift::' . $params['user'] . md5($params['bucket']);

		$this->bucket = $params['bucket'];

		$this->params = $params;
		$this->objectCache = new \OC\Cache\CappedMemoryCache();
	}

	public function mkdir($path) {
		$path = $this->normalizePath($path);

		if ($this->is_dir($path)) {
			return false;
		}

		if ($path !== '.') {
			$path .= '/';
		}

		try {
			$opts = [
				'name' => $path,
				'content' => 'httpd/unix-directory',
			];
			$this->getContainer()->createObject($opts);
			// invalidate so that the next access gets the real object
			// with all properties
			$this->objectCache->remove($path);
		} catch (Exceptions\CreateUpdateError $e) {
			\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}

		return true;
	}

	public function file_exists($path) {
		$path = $this->normalizePath($path);

		if ($path !== '.' && $this->is_dir($path)) {
			$path .= '/';
		}

		return $this->doesObjectExist($path);
	}

	public function rmdir($path) {

// Check last function : wrong version?
		$path = $this->normalizePath($path);
// isDeletable ? Check
		if (!$this->is_dir($path) || !$this->isDeletable($path)) {
			return false;
		}
// Remove every file in it.. could be more efficient

		$dh = $this->opendir($path);
		while ($file = readdir($dh)) {
			if (\OC\Files\Filesystem::isIgnoredDir($file)) {
				continue;
			}

			if ($this->is_dir($path . '/' . $file)) {
				$this->rmdir($path . '/' . $file);
			} else {
				$this->unlink($path . '/' . $file);
			}
		}

		try {
// ? Why change its name before deletion?
//			$this->getContainer()->dataObject()->setName($path . '/')->delete();
            $this->getContainer()->getObject($path . '/')->delete();
			$this->objectCache->remove($path . '/');
		} catch (Exceptions\DeleteError $e) {
			\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}

		return true;
	}

	public function opendir($path) {
		$path = $this->normalizePath($path);
        if ($path === '.') {
            $path = '';
        } else {
            $path .= '/';
        }
		$path = str_replace('%23', '#', $path); // the prefix is sent as a query param, so revert the encoding of #

		try {
			$files = [];
			$objects = $this->getContainer()->listObjects(array(
		        'prefix' => $path,
		        'delimiter' => '/'
			));

			/** @var SwiftObject $object */
			foreach ($objects as $object) {
				$file = basename($object->name);
				if ($file !== basename($path)) {
					$files[] = $file;
				}
			}

			return IteratorDirectory::wrap($files);
		} catch (\Exception $e) {
			\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}

	}

	public function stat($path) {

		$path = $this->normalizePath($path);

		if ($path === '.') {
			$path = '';
		} else if ($this->is_dir($path)) {
			$path .= '/';
		}

		try {
			/** @var SwiftObject $object */
			$object = $this->fetchObject($path);
			if (!$object) {
				return false;
			}
		} catch (ClientErrorResponseException $e) {
			\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
			return false;
		}

		$dateTime = \DateTime::createFromFormat(\DateTime::RFC1123, $object->lastModified);
		if ($dateTime !== false) {
			$mtime = $dateTime->getTimestamp();
		} else {
			$mtime = null;
		}

		$objectMetadata = $object->getMetadata();
		if (!empty($objectMetadata['timestamp'])) {
			$mtime = floor($objectMetadata['timestamp']);
		}

		$stat = [];

//Might be empty..
		$stat['size'] = (int) $object->contentLength;
		$stat['mtime'] = $mtime;
		$stat['atime'] = time();
		return $stat;
	}

	public function filetype($path) {

		$path = $this->normalizePath($path);

		if ($path !== '.' && $this->doesObjectExist($path)) {
			return 'file';
		}

		if ($path !== '.') {
			$path .= '/';
		}

		if ($this->doesObjectExist($path)) {
			return 'dir';
		}
	}

	public function unlink($path) {
        \OCP\Util::writeLog('files_external', 'unlink called ' . $path , \OCP\Util::WARN);
		$path = $this->normalizePath($path);

		if ($this->is_dir($path)) {
			return $this->rmdir($path);
		}

		try {
			$this->getContainer()->getObject($path)->delete();
			$this->objectCache->remove($path);
			$this->objectCache->remove($path . '/');
		} catch (\Exception $e) {
			if ($e->getCode() !== 404) {
				\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
			}
            \OCP\Util::writeLog('files_external', $e->getCode(), \OCP\Util::WARN); 
			return false;
		}
		return true;
	}

	public function fopen($path, $mode) {
		$path = $this->normalizePath($path);
		switch ($mode) {
			case 'r':
			case 'rb':
                    \OCP\Util::writeLog('files_external', 'fopen rb' . $path , \OCP\Util::WARN);
                try {
                    $c = $this->getContainer();
                    return $c->getObject($path)->download()->detach();
                } catch (\Throwable $e) {
                    \OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
                    return false;
                }
			case 'w':
			case 'wb':
			case 'a':
			case 'ab':
			case 'r+':
			case 'w+':
			case 'wb+':
			case 'a+':
			case 'x':
			case 'x+':
			case 'c':
			case 'c+':

                \OCP\Util::writeLog('files_external', 'fopen c+ ' . $path , \OCP\Util::WARN);
                if (strrpos($path, '.') !== false) {
                    $ext = substr($path, strrpos($path, '.'));
                } else {
                    $ext = '';
                }
                $tmpFile = \OCP\Files::tmpFile($ext);
                \OC\Files\Stream\Close::registerCallback($tmpFile, array($this, 'writeBack'));
                // Fetch existing file if required
                if ($mode[0] !== 'w' && $this->file_exists($path)) {
                    if ($mode[0] === 'x') {
                        // File cannot already exist
                        return false;
                    }
                    $source = $this->fopen($path, 'r');
                    file_put_contents($tmpFile, $source);
                    // Seek to end if required
                    if ($mode[0] === 'a') {
                        fseek($tmpFile, 0, SEEK_END);
                    }
                }
                self::$tmpFiles[$tmpFile] = $path;
                return fopen('close://' . $tmpFile, $mode);
		}
	}

	public function touch($path, $mtime = null) {
        \OCP\Util::writeLog('files_external', 'touch called ' . $path , \OCP\Util::WARN);
		$path = $this->normalizePath($path);
		if (is_null($mtime)) {
			$mtime = time();
		}
		$metadata = array('timestamp' => (string) $mtime);
		if ($this->file_exists($path)) {
			if ($this->is_dir($path) && $path != '.') {
				$path .= '/';
			}
			$object = $this->fetchObject($path);
			if ($object->mergeMetadata($metadata)) {
				// invalidate target object to force repopulation on fetch
				$this->objectCache->remove($path);
			}
			return true;
		} else {
			$opts = [
				'contentType' => \OC::$server->getMimeTypeDetector()->detectPath($path),
				'name' => $path,
				'metadata' => $metadata,
			];
            \OCP\Util::writeLog('files_external', 'Trying to create file', \OCP\Util::ERROR);
			$this->getContainer()->createObject($opts);
			// invalidate target object to force repopulation on fetch
			$this->objectCache->remove($path);
			return true;
		}
	}

	public function copy($path1, $path2) {

        \OCP\Util::writeLog('files_external', 'copy called' , \OCP\Util::WARN);

		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);

		$fileType = $this->filetype($path1);
		if ($fileType === 'file') {

			// make way
			$this->unlink($path2);

			try {
				/** @var SwiftObject $source */
				$source = $this->fetchObject($path1);
				$source->copy(['destination' => $this->bucket . '/' . $path2]);
				// invalidate target object to force repopulation on fetch
				$this->objectCache->remove($path2);
				$this->objectCache->remove($path2 . '/');
			} catch (\Throwable $e) {
				\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
				return false;
			}

		} else if ($fileType === 'dir') {

			// make way
			$this->unlink($path2);

			try {
				$source = $this->fetchObject($path1 . '/');
				$source->copy($this->bucket . '/' . $path2 . '/');
				// invalidate target object to force repopulation on fetch
				$this->objectCache->remove($path2);
				$this->objectCache->remove($path2 . '/');
			} catch (ClientErrorResponseException $e) {
				\OCP\Util::writeLog('files_external', $e->getMessage(), \OCP\Util::ERROR);
				return false;
			}

			$dh = $this->opendir($path1);
			while ($file = readdir($dh)) {
				if (\OC\Files\Filesystem::isIgnoredDir($file)) {
					continue;
				}

				$source = $path1 . '/' . $file;
				$target = $path2 . '/' . $file;
				$this->copy($source, $target);
			}

		} else {
			//file does not exist
			return false;
		}
		return true;
	}

	public function rename($path1, $path2) {
        \OCP\Util::writeLog('files_external', 'rename called ' .$path1 . " " . $path2 , \OCP\Util::WARN);

		$path1 = $this->normalizePath($path1);
		$path2 = $this->normalizePath($path2);

		$fileType = $this->filetype($path1);

		if ($fileType === 'dir' || $fileType === 'file') {
			// copy
			if ($this->copy($path1, $path2) === false) {
				return false;
			}

			// cleanup
			if ($this->unlink($path1) === false) {
				$this->unlink($path2);
				return false;
			}

			return true;
		}

		return false;
	}

	public function getId() {
		return $this->id;
	}

	/**
	 * Returns the connection
	 *
	 * @return =Service connected client
	 * @throws \Exception if connection could not be made
	 */
	public function getConnection() {
		if (!is_null($this->connection)) {
			return $this->connection;
		}

// for ease of test
        $settings = [
            'authUrl' => $this->params['region'],
            'region' => $this->params['region'],
            'user'    => [
                'name' => $this->params['user'],
                'password' => $this->params['password'],
                'domain'   => ['id' => '0de861d3d0fc43eabf692d6dbb1cc257']
            ],
            'scope' => [
                'project' => ['id' => 'e47a5557f1094e9f9932bc93b90dbc98']
            ]
        ];

		$this->anchor = new OpenStack($settings);

		$connection = $this->anchor->objectStoreV1();

		$this->connection = $connection;

		return $this->connection;
	}

	/**
	 * Returns the initialized object store container.
	 *
	 * @return Container
	 */
	public function getContainer() {
		if (!is_null($this->container)) {
			return $this->container;
		}

		try {
			$this->container = $this->getConnection()->getContainer($this->bucket);
		} catch (ClientErrorResponseException $e) {
// Maybe not..
			$this->container = $this->getConnection()->createContainer($this->bucket);
		}

		if (!$this->file_exists('.')) {
			$this->mkdir('.');
		}

		return $this->container;
	}

	public function writeBack($tmpFile) {

        \OCP\Util::writeLog('files_external', 'writeback called on ' . $tmpFile . ' ' . self::$tmpFiles[$tmpFile], \OCP\Util::WARN);
        if (!isset(self::$tmpFiles[$tmpFile])) {
            return false;
        }
        $stream = new Stream(fopen($tmpFile, 'r'));
        $this->getContainer()->createObject(['name' => self::$tmpFiles[$tmpFile], 'stream' => $stream]);
        $this->objectCache->remove(self::$tmpFiles[$tmpFile]);
        unlink($tmpFile);
    }

	public function hasUpdated($path, $time) {
		if ($this->is_file($path)) {
			return parent::hasUpdated($path, $time);
		}
		$path = $this->normalizePath($path);
		$dh = $this->opendir($path);
		$content = [];
		while (($file = readdir($dh)) !== false) {
			$content[] = $file;
		}
		if ($path === '.') {
			$path = '';
		}
		$cachedContent = $this->getCache()->getFolderContents($path);
		$cachedNames = array_map(function ($content) {
			return $content['name'];
		}, $cachedContent);
		sort($cachedNames);
		sort($content);
		return $cachedNames != $content;
	}

	public static function checkDependencies() {
		return true;
	}

}
