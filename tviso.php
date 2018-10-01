<?php

class Tviso
{
    private $app;
    private $secret;
    private $userToken;
    private $uid;

    private $domain = "https://api.tviso.com/";
	
    const INVALID_USER_TOKEN = 7;

    public function __construct(array $data)
    {
        if (!isset($data["app"]) || !isset($data["secret"])) {
            throw new TvisoException(TvisoException::MISSING_DATA, 0);
        }

        $this->app = $data["app"];
        $this->secret = $data["secret"];

        if (isset($data["user_token"])) {
            $this->userToken = $data["user_token"];
        }
    }
	
    /**
    * Gets user pending medias
    *
    */
    public function getPendingMedias () {
        return $this->query("user/media/pending/medias", [], true);
    }


    /**
    * Gets media info
    *
    * @param int $idm
    * @param int $mediaType
    * @param bool $full
    */
    public function getMediaInfo($idm, $mediaType, $full = false)
    {
        $type = "basic";

        if ($full) {
            $type = "full";
        }
        return $this->query("media/{$type}_info", [
            "idm" => $idm,
            "mediaType" => $mediaType,
        ]);
    }

    /**
     * Checks prerequisites before sending any petition
     * @param string $url
     * @param array $params
     * @param boolean $requiresUser
     *
     * @return object
     */
    public function query($url, $params = [], $requiresUser = false)
    {
        if (empty($this->authToken)) {
            $this->getAuthToken();
        }
		
        if ($requiresUser && !isset($this->userToken)) {
            throw new TvisoException(TvisoException::MISSING_USER_TOKEN);
        }

        if ($requiresUser) {
            $params['user_token'] = $this->userToken;
        }

        $params['auth_token'] = $this->authToken;

        $result = $this->curl($url, $params);
		
        if ($result->error == self::INVALID_USER_TOKEN) {
            throw new TvisoException(TvisoException::MISSING_USER_TOKEN);
        }
		
        return $result;
    }

    /**
     * Gets user_token for current app
     * @param int $uid
     * @param bool $forceRefresh
     *
     * @return string
     */
    public function getUserToken($username, $password, $forceRefresh = false)
    {
        if (empty($this->userToken) || $forceRefresh) {			
            $response = $this->query('user/user_token', [
                'username' => $username,
                "password" => $password,
                "remember" => 1
            ], false);
			
            $this->userToken = $response->user_token;
        }
        return $this->userToken;
    }

    /**
     * Gets auth_token for current app
     *
     * @return string
     * @throws Exception if fails obtaining the token
     */
    public function getAuthToken()
    {
        $response = $this->curl('auth_token', [
            'id_api' => $this->app,
            'secret' => $this->secret,
        ]);

        if ($response->error == 0) {
            $this->authToken = $response->auth_token;
            return $this->authToken;
        } else {
            throw new TvisoException(TvisoException::GET_AUTH_TOKEN_ERROR, $response->error);
        }
    }

    /**
     * Makes curl petitions
     * @param string $url
     * @param array $params
     *
     * @return object
     * @throws Exception if url is missing
     */
    private function curl($url, $params = [])
    {
        if (empty($url)) {
            throw new Exception('Missing url');
        }
        $url = $this->domain . $url;

        $ch = curl_init($url);
        $header = [];
        $header[0]  = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[]   = "Cache-Control: max-age=0";
        $header[]   = "Connection: keep-alive";
        $header[]   = "Keep-Alive: 300";
        $header[]   = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[]   = "Accept-Language: en-us,en;q=0.5";
        $header[]   = "Pragma: "; // browsers keep this blank.

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.2; en-US; rv:1.8.1.7) Gecko/20070914 Firefox/2.0.0.7');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        if (sizeof($params) > 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        $v = curl_exec($ch);

        //p($url, $params, $v);
        return json_decode($v);
    }
}
class TvisoException extends Exception
{
    const INVALID_INTEGRITY = "invalid object integrity";
    const GET_AUTH_TOKEN_ERROR = "Failed getting auth_token";
    const MISSING_DATA = "Missing required information";
    const MISSING_USER_TOKEN = "Missing user_token";

    public function __construct($constant, $extraInfo, $code = 0, Exception $previous = null) {
        parent::__construct($constant . ': ' . $extraInfo, $code, $previous);
    }
}
