<?php
/**
 * ownCloud - OffiLibre App
 */

namespace OCA\OffiLibre\Controller;

use \OCP\AppFramework\Controller;
use \OCP\IRequest;
use \OCP\IConfig;
use \OCP\IL10N;
use \OCP\AppFramework\Http\ContentSecurityPolicy;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\TemplateResponse;

use \OCA\OffiLibre\AppConfig;
use \OCA\OffiLibre\Db;
use \OCA\OffiLibre\Helper;
use \OCA\OffiLibre\Storage;
use \OCA\OffiLibre\Download;
use \OCA\OffiLibre\DownloadResponse;
use \OCA\OffiLibre\File;
use \OCA\OffiLibre\Genesis;
use \OC\Files\View;
use \OCP\ICacheFactory;
use \OCP\ILogger;

class ResponseException extends \Exception {
	private $hint;

	public function __construct($description, $hint = '') {
		parent::__construct($description);
		$this->hint = $hint;
	}

	public function getHint() {
		return $this->hint;
	}
}

class DocumentController extends Controller {

	private $uid;
	private $l10n;
	private $settings;
	private $appConfig;
	private $cache;
	private $logger;
	const ODT_TEMPLATE_PATH = '/assets/odttemplate.odt';

	public function __construct($appName, IRequest $request, IConfig $settings, AppConfig $appConfig, IL10N $l10n, $uid, ICacheFactory $cache, ILogger $logger){
		parent::__construct($appName, $request);


		$this->uid = $uid;
		$this->l10n = $l10n;
		$this->settings = $settings;
		$this->appConfig = $appConfig;
		$this->cache = $cache->create($appName);
		$this->logger = $logger;
	}

	/**
	 * @param \SimpleXMLElement $discovery
	 * @param string $mimetype
	 */
	private function getWopiSrcUrl($discovery_parsed, $mimetype) {


		if(is_null($discovery_parsed) || $discovery_parsed == false) {
			return null;
		}

		$result = $discovery_parsed->xpath(sprintf('/wopi-discovery/net-zone/app[@name=\'%s\']/action', $mimetype));
		if ($result && count($result) > 0) {
			return array(
				'urlsrc' => (string)$result[0]['urlsrc'],
				'action' => (string)$result[0]['name']
			);
		}

		return null;
	}

	/**
	 * Log the user with given $userid.
	 * This function should only be used from public controller methods where no
	 * existing session exists, for example, when loolwsd is directly calling a
	 * public method with its own access token. After validating the access
	 * token, and retrieving the correct user with help of access token, it can
	 * be set as current user with help of this method.
	 *
	 * @param string $userid
	 */
	private function loginUser($userid) {

		\OC::$server->getLogger()->debug('DocumentController loginUser');
		\OC_Util::tearDownFS();

		$users = \OC::$server->getUserManager()->search($userid, 1, 0);
		if (count($users) > 0) {
			$user = array_shift($users);
			if (strcasecmp($user->getUID(), $userid) === 0) {
				// clear the existing sessions, if any
				\OC::$server->getSession()->close();

				// initialize a dummy memory session
				$session = new \OC\Session\Memory('');
				// wrap it
				$cryptoWrapper = \OC::$server->getSessionCryptoWrapper();
				$session = $cryptoWrapper->wrapSession($session);
				// set our session
				\OC::$server->setSession($session);

				\OC::$server->getUserSession()->setUser($user);
			}
		}

		\OC_Util::setupFS();
	}

	/**
	 * Log out the current user
	 * This is helpful when we are artifically logged in as someone
	 */
	private function logoutUser() {
		\OC_Util::tearDownFS();

		\OC::$server->getSession()->close();
	}

	private function responseError($message, $hint = ''){
		$errors = array('errors' => array(array('error' => $message, 'hint' => $hint)));
		$response = new TemplateResponse('', 'error', $errors, 'error');
		return $response;
	}


	/**
     * Return true if the currently logged in user is a tester.
     * This depends on whether current user is the member of one of the groups
     * mentioned in settings (test_server_groups)
     */
     private function isTester() {
		 $tester = false;

         return $tester;
     }

	/** Return the content of discovery.xml - either from cache, or download it.
	 */
	private function getDiscovery(){

		$discovery = "<xml></xml>";
		return $discovery;
	}

	/** Prepare document(s) structure
	 */
	private function prepareDocuments($rawDocuments){
		\OC::$server->getLogger()->debug('DocumentController prepareDocuments');


		$discovery_parsed = null;
		try {
			$discovery = $this->getDiscovery();

			$loadEntities = libxml_disable_entity_loader(true);
			$discovery_parsed = simplexml_load_string($discovery);
			libxml_disable_entity_loader($loadEntities);

		}
		catch (ResponseException $e) {
			return array(
				'status' => 'error',
				'message' => $e->getMessage(),
				'hint' => $e->getHint()
			);
		}

		$fileIds = array();
		$documents = array();
		$lolang = strtolower(str_replace('_', '-', $this->settings->getUserValue($this->uid, 'core', 'lang', 'en')));
		foreach ($rawDocuments as $key=>$document) {
			if (is_object($document)){
				$documents[] = $document->getData();
			} else {
				$documents[$key] = $document;
			}
			$documents[$key]['icon'] = preg_replace('/\.png$/', '.svg', \OCP\Template::mimetype_icon($document['mimetype']));
			$documents[$key]['hasPreview'] = \OC::$server->getPreviewManager()->isMimeSupported($document['mimetype']);
			$ret = $this->getWopiSrcUrl($discovery_parsed, $document['mimetype']);
			$documents[$key]['urlsrc'] = $ret['urlsrc'];
			$documents[$key]['action'] = $ret['action'];
			$documents[$key]['lolang'] = $lolang;
			$fileIds[] = $document['fileid'];
		}

		usort($documents, function($a, $b){
			return @$b['mtime']-@$a['mtime'];
		});

		$session = new Db\Session();
		$sessions = $session->getCollectionBy('file_id', $fileIds);

		$members = array();
		$member = new Db\Member();
		foreach ($sessions as $session) {
			$members[$session['es_id']] = $member->getActiveCollection($session['es_id']);
		}

		return array(
			'status' => 'success', 'documents' => $documents,'sessions' => $sessions,'members' => $members
		);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(){

		\OC::$server->getLogger()->debug('DocumentController index');

		$user = \OC::$server->getUserSession()->getUser();
		$usergroups = array_filter(\OC::$server->getGroupManager()->getUserGroupIds($user));
		$usergroups = join('|', $usergroups);
		\OC::$server->getLogger()->debug('User is in groups: {groups}', [ 'app' => $this->appName, 'groups' => $usergroups ]);

		\OC::$server->getNavigationManager()->setActiveEntry( 'offilibre_index' );
		$maxUploadFilesize = \OCP\Util::maxUploadFilesize("/");
		$response = new TemplateResponse('offilibre', 'documents', [
			'enable_previews' =>		$this->settings->getSystemValue('enable_previews', true),
			'uploadMaxFilesize' =>		$maxUploadFilesize,
			'uploadMaxHumanFilesize' =>	\OCP\Util::humanFileSize($maxUploadFilesize),
			'allowShareWithLink' =>		$this->settings->getAppValue('core', 'shareapi_allow_links', 'yes'),
			'wopi_url' =>			$webSocket,
			'doc_format' =>			$this->appConfig->getAppValue('doc_format')
		]);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedScriptDomain('\'self\' http://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js http://cdnjs.cloudflare.com/ajax/libs/jquery-mousewheel/3.1.12/jquery.mousewheel.min.js \'unsafe-eval\' ' . $wopiRemote);
		/* frame-src is deprecated on Firefox, but Safari wants it! */
		$policy->addAllowedFrameDomain('\'self\' http://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js http://cdnjs.cloudflare.com/ajax/libs/jquery-mousewheel/3.1.12/jquery.mousewheel.min.js \'unsafe-eval\' ' . $wopiRemote . ' blob:');
		$policy->addAllowedChildSrcDomain('\'self\' http://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js http://cdnjs.cloudflare.com/ajax/libs/jquery-mousewheel/3.1.12/jquery.mousewheel.min.js \'unsafe-eval\' ' . $wopiRemote);
		$policy->addAllowedConnectDomain($webSocket);
		$policy->addAllowedImageDomain('*');
		$policy->allowInlineScript(true);
		$policy->addAllowedFontDomain('data:');
		$response->setContentSecurityPolicy($policy);

		return $response;
	}


	/**
	 * @NoAdminRequired
	 * @PublicPage
	 * Process partial/complete file download
	 */
	public function serve($esId){

		\OC::$server->getLogger()->debug('DocumentController serve');
		$session = new Db\Session();
		$session->load($esId);

		$filename = $session->getGenesisUrl() ? $session->getGenesisUrl() : '';
		return new DownloadResponse($this->request, $session->getOwner(), $filename);
	}

	/**
	 * @NoAdminRequired
	 */
	public function download($path){
		\OC::$server->getLogger()->debug('DocumentController download');
		if (!$path){
			$response = new JSONResponse();
			$response->setStatus(Http::STATUS_BAD_REQUEST);
			return $response;
		}

		$fullPath = '/files' . $path;
		$fileInfo = \OC\Files\Filesystem::getFileInfo($path);
		if ($fileInfo){
			$file = new File($fileInfo->getId());
			$genesis = new Genesis($file);
			$fullPath = $genesis->getPath();
		}
		return new DownloadResponse($this->request, $this->uid, $fullPath);
	}



	/**
	 * @NoAdminRequired
	 * Get file information about single document with fileId
	 */
	public function get($fileId){
		$documents = array();
		$documents[0] = Storage::getDocumentById($fileId);

		return $this->prepareDocuments($documents);
	}


	/**
	 * @NoAdminRequired
	 * lists the documents the user has access to (including shared files, once the code in core has been fixed)
	 * also adds session and member info for these files
	 */
	public function listAll(){
		\OC::$server->getLogger()->debug('DocumentController listAll');
		return $this->prepareDocuments(Storage::getDocuments());
	}
}
