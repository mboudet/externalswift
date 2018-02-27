<?php

/**
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCA\ExternalSwift\Backend;

use \OCP\Files\External\Backend\Backend;
use \OCP\IL10N;
use \OCP\Files\External\DefinitionParameter;
use \OCP\Files\External\Auth\AuthMechanism;

class Swift extends Backend {

	public function __construct(IL10N $l) {
		$this
			->setIdentifier('externalswift')
			->setStorageClass('\OCA\ExternalSwift\Storage\Swift')
			->setText($l->t('OpenStack Object Storage (Keystone 3)'))
			->addParameters([
                (new DefinitionParameter('user', $l->t('User'))),
                (new DefinitionParameter('password', $l->t('Password')))
                    ->setType(DefinitionParameter::VALUE_PASSWORD),
                (new DefinitionParameter('url', $l->t('Keystone url'))),
                (new DefinitionParameter('domain_id', $l->t('Domain Id'))),
                (new DefinitionParameter('project_id', $l->t('Project Id'))),
				(new DefinitionParameter('region', $l->t('Region')))
					->setFlag(DefinitionParameter::FLAG_OPTIONAL),
				(new DefinitionParameter('bucket', $l->t('Bucket'))),
			])
			->addAuthScheme(AuthMechanism::SCHEME_BUILTIN);
	}
}
