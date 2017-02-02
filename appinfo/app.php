<?php

/**
 * ownCloud - OffiLibre App
 */

namespace OCA\OffiLibre\AppInfo;

use OCA\OffiLibre\Config;

$app = new Application();
$c = $app->getContainer();

\OCP\App::registerAdmin('offilibre', 'admin');

$navigationEntry = function () use ($c) {
	return [
		'id' => 'offilibre_index',
		'order' => 2,
		'href' => $c->query('ServerContainer')->getURLGenerator()->linkToRoute('offilibre.document.index'),
		'icon' => $c->query('ServerContainer')->getURLGenerator()->imagePath('offilibre', 'app.svg'),
		'name' => $c->query('L10N')->t('OffiLibre')
	];
};
$c->getServer()->getNavigationManager()->add($navigationEntry);

//Script for registering file actions
$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener(
	'OCA\Files::loadAdditionalScripts',
	function() {
		\OCP\Util::addScript('offilibre', 'viewer/viewer');
		\OCP\Util::addStyle('offilibre', 'viewer/odfviewer');
	}
);

if (class_exists('\OC\Files\Type\TemplateManager')) {
    $manager = \OC_Helper::getFileTemplateManager();

    $manager->registerTemplate('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'apps/offilibre/assets/docxtemplate.docx');
    $manager->registerTemplate('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'apps/offilibre/assets/xlsxtemplate.xlsx');
    $manager->registerTemplate('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'apps/offilibre/assets/pptxtemplate.pptx');
}

//Listen to delete file signal
\OCP\Util::connectHook('OC_Filesystem', 'delete', "OCA\OffiLibre\Storage", "onDelete");
