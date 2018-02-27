<?php

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
