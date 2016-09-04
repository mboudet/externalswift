<?php
/**
 * ownCloud - externalswift
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Brad Jones <brad@bradjonesllc.com>
 * @copyright Brad Jones 2016
 */

namespace OCA\ExternalSwift\AppInfo;

use OCA\ExternalSwift\AppInfo\Application;
use OC_App;
use OC_Mount_Config;

require_once __DIR__ . '/autoload.php';

// TODO: Is this necessary?
if (!class_exists('OC_Mount_Config')) {
	OC_App::loadApp('files_external');
}

$app = new Application();
