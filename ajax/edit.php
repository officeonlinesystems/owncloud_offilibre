<?php

use OCP\IUser;

OC_JSON::checkLoggedIn();
OCP\JSON::callCheck();

$defaults = new \OCP\Defaults();

$userManager = \OC::$server->getUserManager();

$datadir = \OC::$server->getConfig()->getSystemValue('datadirectory');
$userx = \OC_User::getUser();
$title = (string)$_POST['title'];
$username = (string)$_POST['username'];

$pathinfo = pathinfo($title);
$extension = $pathinfo['extension'];
$fileorigen = $datadir . "/" . $userx . "/files/" . $title;

$postdata = array(
    'name' => $title,
    'extension' => $extension
);

$response =  do_post_request("http://www.offidocs.com/owncloudupload.php?username=". $username, $postdata, $fileorigen); 
$owncloudserver = get_string_between($response, "SERVER", "FIN");
$response = get_string_between($response, "OKFILE", "---");

OCP\JSON::success(array('redirect' => "http://www.offidocs.com/owncloudredirect.php?file_path=file://" . $response . "&username=" . $username . "&ext=yes&service=" . $owncloudserver));

function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function do_post_request($url, $postdata, $file) 
{ 
    $data = ""; 
    $boundary = "---------------------".substr(md5(rand(0,32000)), 0, 10); 

    //Collect Postdata 
    foreach($postdata as $key => $val) 
    { 
        $data .= "--$boundary\n"; 
        $data .= "Content-Disposition: form-data; name=\"".$key."\"\n\n".$val."\n"; 
    } 

    $data .= "--$boundary\n"; 

    $fileContents = file_get_contents($file); 

    $data .= "Content-Disposition: form-data; name=\"fuDocument\"; filename=\"1111111111\"\n"; 
    $data .= "Content-Transfer-Encoding: binary\n\n"; 
    $data .= $fileContents."\n"; 
    $data .= "--$boundary--\n"; 

    $params = array('http' => array( 
           'method' => 'POST', 
           'header' => 'Content-Type: multipart/form-data; boundary='.$boundary, 
           'content' => $data 
        )); 

   $ctx = stream_context_create($params); 
   $fp = fopen($url, 'rb', false, $ctx); 

   if (!$fp) { 
      throw new Exception("Problem with $url, $php_errormsg"); 
   } 

   $response = @stream_get_contents($fp); 
   if ($response === false) { 
      throw new Exception("Problem reading data from $url, $php_errormsg"); 
   } 
   return $response; 
} 

