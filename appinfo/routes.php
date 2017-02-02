<?php
/**
 * ownCloud - OffiLibre App
 */

namespace OCA\OffiLibre;

$application = new \OCA\OffiLibre\AppInfo\Application();
$application->registerRoutes($this, [
	'routes' => [
		//users
		['name' => 'user#rename', 'url' => 'ajax/user/rename', 'verb' => 'POST'],
		['name' => 'user#disconnectUser', 'url' => 'ajax/user/disconnect', 'verb' => 'POST'],
		['name' => 'user#disconnectGuest', 'url' => 'ajax/user/disconnectGuest', 'verb' => 'POST'],
		//session
		['name' => 'session#join', 'url' => 'session/user/join/{fileId}', 'verb' => 'POST'],
		['name' => 'session#poll', 'url' => 'session/user/poll', 'verb' => 'POST'],
		['name' => 'session#save', 'url' => 'session/user/save', 'verb' => 'POST'],
		['name' => 'session#joinAsGuest', 'url' => 'session/guest/join/{token}', 'verb' => 'POST'],
		['name' => 'session#pollAsGuest', 'url' => 'session/guest/poll/{token}', 'verb' => 'POST'],
		['name' => 'session#saveAsGuest', 'url' => 'session/guest/save/{token}', 'verb' => 'POST'],
		//documents
		['name' => 'document#index', 'url' => 'index', 'verb' => 'GET'],
		['name' => 'document#serve', 'url' => 'ajax/genesis/{esId}', 'verb' => 'GET'],
		['name' => 'document#get', 'url' => 'ajax/documents/get/{fileId}', 'verb' => 'GET'],
		['name' => 'document#listAll', 'url' => 'ajax/documents/list', 'verb' => 'GET'],
	]
]);

$this->create('offilibre_edit', 'ajax/edit.php')
        ->actionInclude('offilibre/ajax/edit.php');
