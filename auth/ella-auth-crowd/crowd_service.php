<?php


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}



class crowd_service{

  private $HTTP;

  private $request_url;
  private $request_headers;
  private $request_method;
  private $request_data;
  private $request_service;

  private $server_address;
  private $crowd_application;
  private $crowd_application_password;
  private $crowd_port;
  private $crowd_sso_domain;

  function crowd_service(){

    //crowd info
    $this->server_address = "http://aws-crowd:8095/crowd/";
    $this->crowd_application = "moodle";
    $this->crowd_application_password = "H9nWqmEm8J65198";
    $this->crowd_sso_domain = ".ochin.org";

    //headers
    $this->request_headers = array('Accept: application/json','Content-Type: application/xml');
    $this->request_method = 'POST';

  }


  /**

  *//*  
    Authorizes user based on username and password
  */
  function authorize($username, $password){
    $this->request_service = 'rest/usermanagement/latest/session';
    $this->request_method = 'POST';

    $password = htmlentities($password);//escape special chars for password

    //compile request data
    $this->request_data = '<?xml version="1.0" encoding="UTF-8"?>
      <authentication-context>
        <username>' . $username .'</username>
        <password>' . $password . '</password>
        <validation-factors>
          <validation-factor>
            <name>remote_address</name>
            <value>10.33.1.157</value>
          </validation-factor>
        </validation-factors>
      </authentication-context>';

    //make request
    $request = $this->callCrowd($this->request_service, $this->request_method, $this->request_data);
    //var_dump($request);
    //process request and return
    if($request->response == 200 || $request->response == 201 ){
      $this->setCookie($request->token);
      return true;
    }else{
      return false;
    }

  }

  /**

  *//*  
    Authenticate a user/password combination
  */
  function authenticate($username, $password){
    $this->request_service = 'rest/usermanagement/latest/authentication?username='.$username;
    $this->request_method = 'POST';
    $this->request_data = '<?xml version="1.0" encoding="UTF-8"?>
                            <password>
                              <value>'.$password.'</value>
                            </password>';


    $request = $this->callCrowd($this->request_service, $this->request_method, $this->request_data);

    if($request->response == 200){
      return true;
    }else{
      return false;
    }

  }

  /**

  *//*  
    Returns bool true or false on logout attempt
  */
  public function logout($crowd_token) {

    $this->request_service = 'rest/usermanagement/latest/session/' . $crowd_token;

    $this->request_method = 'DELETE';

    $request = $this->callCrowd($this->request_service, $this->request_method);
    //var_dump($request);

    //process request and return
    if ($request->response == '204') {
      $this->deleteCookie();
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**

  *//*  
    Returns User Object
  */
  public function getUser($user_token) {

    $this->request_service = 'rest/usermanagement/latest/session/'.$user_token;
    $this->request_method = 'GET';

    $result = $this->callCrowd($this->request_service, $this->request_method);
    //var_dump($result);
    if ($result->response == '200' || $result->response =='201') {
      return array("firstname" => $result->user->{'first-name'}, "lastname" => $result->user->{'last-name'}, "email" => $result->user->{'email'});
    } else {
      return FALSE;
    }
  }

  /**

  *//*  
    Returns User Object
  */
  public function getUsername($user_token) {

    $this->request_service = 'rest/usermanagement/latest/session/'.$user_token;
    $this->request_method = 'GET';

    $result = $this->callCrowd($this->request_service, $this->request_method);
    //var_dump($result);
    if ($result->response == '200' || $result->response =='201') {
      return $result->user->{'name'};
    } else {
      return FALSE;
    }
  }

  /**

  *//*  
    Returns User Object
  */
  public function getUserByName($username) {

    $this->request_service = 'rest/usermanagement/latest/user?username='.$username;
    $this->request_method = 'GET';

    $result = $this->callCrowd($this->request_service, $this->request_method);
    //var_dump($result);
    if ($result->response == '200' || $result->response =='201') {
      return array("firstname" => $result->{'first-name'}, "lastname" => $result->{'last-name'}, "email" => $result->{'email'});
    } else {
      return FALSE;
    }
  }

  /**

  *//*  
    Returns boolean when checking validity of usertoken
  */
  public function isLoggedIn($user_token) {
    $this->request_service = 'rest/usermanagement/latest/session/'.$user_token;
    $this->request_method = 'GET';


    $result = $this->callCrowd($this->request_service, $this->request_method);

    if ($result->response == '200' || $result->response =='201') {
      return true;
    } else {
      return FALSE;
    }

  }
  /**

  *//*  
    Returns boolean when checking validity of usertoken
  */
  public function listGroups() {
    $this->request_service = 'rest/usermanagement/latest/search?entity-type=group&expand=group';
    $this->request_method = 'POST';
    $this->request_data = '<?xml version="1.0" encoding="UTF-8"?>
    <property-search-restriction>
      <property>
        <name>active</name>
        <type>BOOLEAN</type>
      </property>
      <match-mode>EXACTLY_MATCHES</match-mode>
      <value>true</value>
    </property-search-restriction>';

    $result = $this->callCrowd($this->request_service, $this->request_method, $this->request_data);

    if ($result->response == '200' || $result->response =='201') {
      return $result;
    } else {
      return FALSE;
    }
  }

  /**

  *//*  
    Returns boolean when checking validity of usertoken
  */
  public function userGroups($username) {
    $this->request_service = 'rest/usermanagement/latest/user/group/direct?username='.$username;
    $this->request_method = 'GET';

    $result = $this->callCrowd( $this->request_service, $this->request_method );

    if ($result->response == '200' || $result->response =='201') {
      return $result;
    } else {
      return FALSE;
    }
  }

  /**

  *//*  
    Changes user password
  */
  public function changePassword($username, $newpassword) {

    $this->request_service = 'rest/usermanagement/latest/user/password?username='.$username;
    $this->request_method = 'PUT';
    $this->request_data = '<?xml version="1.0" encoding="UTF-8"?>
                           <password>
                             <value>'.$newpassword.'</value>
                           </password>';

    $result = $this->callCrowd( $this->request_service, $this->request_method, $this->request_data);
    //var_dump($result);
    return $result;

  }

  /**

  *//*  
    Cookie Functions
  */
  function setCookie($auth_token) {
    setcookie('crowd.token_key', $auth_token, NULL, '/', $this->crowd_sso_domain);
  }
  function deleteCookie() {
    setcookie("crowd.token_key", false, time()-3600, "/", $this->crowd_sso_domain);
  }
  function getCookie() {
    if(!isset($_COOKIE['crowd_token_key'])){
      return false;
    }else{
      return $_COOKIE['crowd_token_key'];
    }
  }

  /**

  *//*  
    Makes a HTTP call to the crowd API
    Returns result code and resulting JSON
  */
  function callCrowd($service, $method, $data = ""){

    //compile url
    $this->request_url = $this->server_address.$service;
    $handle = curl_init(); 
    curl_setopt($handle, CURLOPT_URL, $this->server_address.$service);
    curl_setopt($handle, CURLOPT_HTTPHEADER, $this->request_headers);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    //curl_setopt($handle, CURLOPT_VERBOSE, 1);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
    curl_setopt($handle, CURLOPT_USERPWD, $this->crowd_application.":".$this->crowd_application_password); 
    curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC); 


    //Determine call method
    if($method == "POST"){
      curl_setopt($handle, CURLOPT_POST, true);
    }else if($method == "GET"){
      curl_setopt($handle, CURLOPT_HTTPGET, true);
    }else if($method == "DELETE"){
      curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "DELETE");
    }else if($method == "PUT"){
      curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "PUT");
    }

    //get response code and pass it along with json
    $result = curl_exec($handle);
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE); 
    $result = json_decode($result);
    $result->response = new stdClass();
    $result->response = $httpCode;

    //var_dump(curl_getinfo($handle));

    //close curl and return
    curl_close($handle);

    return $result;
  }



}


?>
