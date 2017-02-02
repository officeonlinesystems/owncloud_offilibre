<?php
/**
 * ownCloud - OffiLibre App
 */

namespace OCA\OffiLibre;

use \OC\Files\View;

class Genesis {
	
	const DOCUMENTS_DIRNAME='/documents';
	
	protected $view;
	
	protected $path;
	
	protected $hash;
	
	
	/**
	 * Create new genesis document
	 * @param File $file 
	 * */	
	public function __construct(File $file){
		$view = $file->getOwnerView();
		$path = $file->getPath();
		$owner = $file->getOwner();
		
		$this->view = new View('/' . $owner);
		
		if (!$this->view->file_exists(self::DOCUMENTS_DIRNAME)){
			$this->view->mkdir(self::DOCUMENTS_DIRNAME );
		}
		$this->validate($view, $path);
		
		$this->hash = $view->hash('sha1', $path, false);
		$this->path = self::DOCUMENTS_DIRNAME . '/' . $this->hash . '.odt';
		if (!$this->view->file_exists($this->path)){
			//copy new genesis to /user/documents/{hash}.odt
			// get decrypted content
			$content = $view->file_get_contents($path);
			$mimetype = $view->getMimeType($path);
			
			$data = Filter::read($content, $mimetype);
			$this->view->file_put_contents($this->path, $data['content']);
		}
		
		try {
			$this->validate($this->view, $this->path);
		} catch (\Exception $e){
			throw new \Exception('Failed to copy genesis');
		}
	}
	
	public function getPath(){
		return $this->path;
	}
	
	public function getHash(){
		return $this->hash;
	}
	
	/**
	 * Check if genesis is valid
	 * @param \OC\Files\View $view 
	 * @param string $path relative to the view
	 * @throws \Exception
	 */
	protected function validate($view, $path){
		if (!$view->file_exists($path)){
			throw new \Exception('Document not found ' . $path);
		}
		if (!$view->is_file($path)){
			throw new \Exception('Object ' . $path . ' is not a file.');
		}
		//TODO check if it is a valid odt
	}

}
