<?php
/*
 * Copyright (c) 2017 Croozy (https://croozy.io)
 * Licensed under MIT (https://github.com/Croozy/yofba/blob/master/LICENSE)
 */

header('Content-Type: text/html; charset=UTF-8');

//For Cron compatibility (Before Init Session)
if (isset($argv)) {
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            $param = explode('=', $arg);
            $_GET[$param[0]] = $param[1];
        }
    }
}

//Init program
ProcessingRequest::init();

//Start program
ProcessingRequest::start();

class Config {

    /* Customize
     * Get "App ID" on your Facebook app settings
     */
    const fb_app_id = '000000000000000';

    /* Customize
     * Get "App Secret" on your Facebook app settings
     */
    const fb_app_secret = '1111111111111111111111111111111';
    const app_fb_json_albums_file_path = 'yofba_data/Album_%album_id%.json'; //%album_id% : replace with id of current album

    /* Customize
     * The ids and names of all albums are required
     */
    const app_fb_albums_array =
        array(
            array(
                "idPage"=>"111",
                "namePage" => "Page 1",
                "albums" => array(array("idAlbum" => "111111", "nameAlbum" => "Album 1 page 1"),array("idAlbum" => "111222", "nameAlbum" => "Album 2 page 1","maxPhotos"=>20))
            ),
            array(
                "idPage"=>"222",
                "namePage" => "Page 2",
                "albums" => array(array("idAlbum" => "222111", "nameAlbum" => "Album 1 page 2","maxPhotos"=>105),array("idAlbum" => "222222", "nameAlbum" => "Album 2 page 2","maxPhotos"=>80))
            )
        );
    const app_debug_mode = false;

    /* Customize
     * Token used to authorize update
     */
    const app_token = "MyCustomToken";

    /*
     * Prevent too much call, whether the password is correct or not
     */
    const app_client_access_number_of_attempts_before_the_tempory_ban_of_the_ip = 5;

    /*
     * Ban ip after number of failed try
     */
    const app_client_access_number_of_attempts_before_definitely_ban = 1000;
    const app_client_access_time_between_new_try = 1800; //In seconds
    const app_client_access_file_path = "yofba_data/ClientAccess.json";


    /*
     * List of all the Facebook fields you want to retrieve for an album
     * See doc :
     * https://developers.facebook.com/docs/graph-api/reference/album
     */
    const app_list_of_fields_album = array("name", "link", "description");

    /*
     * List of all Facebook fields you want to retrieve on album photos
     * See doc : 
     * https://developers.facebook.com/docs/graph-api/reference/photo
     */
    const app_list_of_fields_photos_album = array("id", "link", "name", "images", "likes");
    const app_count_facebook_likes = true;
    const app_count_facebook_likes_field_name = 'sum_of_likes';

    /*
     * Analyzes and extracts the main color of an image
     * /!\WARNING/!\ -> Takes many resources the first time (Pay attention to the processing time as well as the variable 'max_execution_time' of your server)
     */
    const app_get_main_color_of_image = true;
    const app_get_main_color_of_image_field_name = 'main_color';
    const app_exception_file_path = "yofba_data/exceptions.log";

    /*
     * Required if you place this code on a domain other than your recovery JSON call
     * Allow CORS call when returns JSON
     * PS : You can trust all origins with "*" but not recommended
     */
    const app_allow_cors_request_from_url = array();

}

class Session {

    public $fb_token;
    public $client_ip;
    public $client_token;
    public $client_album_id;
    public $client_get_album;
    public $php_output_message;
    public $is_json_output;

    public function __construct() {
        $this->fb_token = Facebook::getToken();
        $this->client_ip = empty($_SERVER['REMOTE_ADDR']) ?: $_SERVER['REMOTE_ADDR'];
        $this->client_token = isset($_GET['token']) ? $_GET['token'] : null;
        $this->client_page_id = isset($_GET['page_id']) && Album::getPageKeyInArray($_GET['page_id']) !== false ? $_GET['page_id'] : null;
        $this->client_album_id = isset($_GET['album_id']) && Album::getAlbumKeyInArray($_GET['album_id']) !== false ? $_GET['album_id'] : null;
        $this->client_get_album = isset($_GET['getAlbum']) ? $_GET['getAlbum'] : null;
        $this->php_output_message = "";
        $this->is_json_output = false;
    }

    private static $_instance = null;

    public static function getInstance() {

        if (is_null(self::$_instance)) {
            self::$_instance = new Session();
        }

        return self::$_instance;
    }

}

Class ProcessingRequest {

    static function init() {
        //Exception handler
        new ExceptionHandler();

        // Create directory path if not exist
        $dir_paths = array(Config::app_fb_json_albums_file_path, Config::app_client_access_file_path, Config::app_exception_file_path);
        Tools::createDirPathsIfTheyDoNotExist($dir_paths);
    }

    static function start() {

        //Update Albums
        if (isset(Session::getInstance()->client_token) && Session::getInstance()->client_token != "") {
            $isBan = ClientAccess::isAuthorised();
            if (!$isBan && Config::app_token == Session::getInstance()->client_token) {
                $time_start=microtime(true);
                try {
                    Album::updateAlbums();
                    Tools::showMessage('Update succeeded ('.strval(microtime(true)-$time_start).' sec)', MessageType::Success);
                } catch (Exception $ex) {
                    Tools::showMessage('Update failed ('.strval(microtime(true)-$time_start).' sec)', MessageType::Danger);
                }
            }
            //Don't show indice if ban
            else if (!$isBan && Config::app_token != Session::getInstance()->client_token) {
                Tools::showMessage('Bad token', MessageType::Danger);
            }
            //For all try with bad token
            if (Config::app_token != Session::getInstance()->client_token) {
                ClientAccess::failedTryAccess();
            }
        } else {
            Tools::showMessage('Please fill a token', MessageType::Danger);
        }

        //Get Album (JSON response)
        if (isset(Session::getInstance()->client_get_album)) {
            try {
                Session::getInstance()->is_json_output = true;

                $http_origin = empty($_SERVER['HTTP_ORIGIN']) ? '' : $_SERVER['HTTP_ORIGIN'];
                if ($http_origin != '') {
                    foreach (Config::app_allow_cors_request_from_url as $url) {
                        if ($http_origin == $url || $url == '*') {
                            header("Access-Control-Allow-Origin: $url");
                        }
                    }
                }

                $json = Album::getAlbumAndPhotosLocally(Session::getInstance()->client_get_album);
                if (empty($json)) {
                    http_response_code(404);
                }
                if (http_response_code() == 200) {
                    header('Content-type: application/json');
                }

                echo $json;
            } catch (Exception $ex) {
                http_response_code(500);
            }
        }
    }

}

Class ClientAccess {

    public $ip;
    public $lastBan;
    public $lastLogin;
    public $numberOfTryInCurrentUnbanStatus;
    public $totalNumberOfFailedTry;

    public function __construct($ip, $lastBan, $lastLogin, $numberOfTryInCurrentUnbanStatus, $totalNumberOfFailedTry) {
        $this->ip = $ip;
        $this->lastBan = $lastBan;
        $this->lastLogin = $lastLogin;
        $this->numberOfTryInCurrentUnbanStatus = $numberOfTryInCurrentUnbanStatus;
        $this->totalNumberOfFailedTry = $totalNumberOfFailedTry;
    }

    private static function isDefinitelyBan($client) {
        if (Config::app_client_access_number_of_attempts_before_definitely_ban != -1) {
            if ($client['totalNumberOfFailedTry'] >= Config::app_client_access_number_of_attempts_before_definitely_ban) {
                return true;
            }
        }
        return false;
    }

    static function failedTryAccess() {
        $clientAccessFile = self::getClientAccessFile();
        foreach ($clientAccessFile['data'] as &$client) {
            if (Session::getInstance()->client_ip === $client['ip']) {
                $client['totalNumberOfFailedTry'] += 1;
            }
        }
        self::setClientAccessFile($clientAccessFile);
    }

    private static function getClientAccessFile() {

        //Init file if not exist
        if (!file_exists(self::getClientAccessFilePath())) {
            $obj = new stdClass();
            $obj->data = array();
            self::setClientAccessFile($obj);
        }
        //Read file
        $app_access_check_up_ip_array = (array) json_decode(file_get_contents(self::getClientAccessFilePath()), true);


        return $app_access_check_up_ip_array;
    }

    private static function setClientAccessFile($clientAccessFile) {
        file_put_contents(self::getClientAccessFilePath(), json_encode($clientAccessFile));
    }
    
    private static function getClientAccessFilePath(){
        return Tools::getAbsoluteFilePath(Config::app_client_access_file_path);
    }

    private static function addClientAccessIfIpNotExist(&$clientAccessFile) {
        Tools::showMessage('<h2>Before check</h2>' . json_encode($clientAccessFile), MessageType::Debug);

        //Check if ip key exist
        $key = array_search(Session::getInstance()->client_ip, array_column($clientAccessFile['data'], 'ip'));

        //Save if ip key not exist
        if (isset($clientAccessFile) && $key === false) {
            $ip_ban_obj = new ClientAccess(Session::getInstance()->client_ip, null, time(), 0, 0);
            array_push($clientAccessFile['data'], (array) $ip_ban_obj);
            Tools::showMessage('You are an unknown user', MessageType::Debug);
        }
    }

    static function isAuthorised() {

        $is_ban = false;

        Tools::showMessage('<h2>Access check up</h2>', MessageType::Debug);

        $clientAccessFile = self::getClientAccessFile();
        self::addClientAccessIfIpNotExist($clientAccessFile);

        foreach ($clientAccessFile['data'] as &$client) {
            if (Session::getInstance()->client_ip === $client['ip']) {
                $definitly_ban = self::isDefinitelyBan($client);

                $client['numberOfTryInCurrentUnbanStatus'] += 1;

                //If user has reached the authorized quota then ban it
                if ($client['numberOfTryInCurrentUnbanStatus'] >= Config::app_client_access_number_of_attempts_before_the_tempory_ban_of_the_ip && !$definitly_ban) {
                    $client['lastBan'] = time();
                    Tools::showMessage('Ban', MessageType::Debug);
                    $is_ban = true;
                }

                //Check if already ban
                if ($client['lastBan'] + Config::app_client_access_time_between_new_try > time() || $definitly_ban) {
                    if (!$definitly_ban) {
                        Tools::showMessage('Try again in : ' . floor(($client['lastBan'] + Config::app_client_access_time_between_new_try + 60 - time()) / 60) . ' minutes', MessageType::Warning);
                        $client['numberOfTryInCurrentUnbanStatus'] = 1;
                    } else {
                        Tools::showMessage('You are banned, please contact the administrator of this site', MessageType::Warning);
                    }
                    $is_ban = true;
                }

                //Reset if the user has been idle during the wait time
                elseif ($client['lastLogin'] + Config::app_client_access_time_between_new_try < time()) {
                    Tools::showMessage('Reset number of try', MessageType::Debug);
                    $client['numberOfTryInCurrentUnbanStatus'] = 1;
                }


                $client['lastLogin'] = time();
                break;
            }
        }

        Tools::showMessage('<h2>After check</h2>' . json_encode($clientAccessFile), MessageType::Debug);

        self::setClientAccessFile($clientAccessFile);
        return $is_ban;
    }

}

class Facebook {

    static function getToken() {
        $url = 'https://graph.facebook.com/oauth/access_token?client_id=' . Config::fb_app_id . '&client_secret=' . Config::fb_app_secret . '&grant_type=client_credentials';
        $json = file_get_contents($url);
        return json_decode($json)->access_token;
    }

    static function getAlbumUrl($album_id) {
        $url = "https://graph.facebook.com/$album_id?fields=" . implode(",", Config::app_list_of_fields_album) . '&access_token=' . Session::getInstance()->fb_token;
        //Tools::showMessage('Url getAlbum : ' . $url, MessageType::Debug);
        return $url;
    }

    static function getAlbumPhotosUrl($album_id) {
        $url = "https://graph.facebook.com/$album_id/photos?fields=" . implode(",", Config::app_list_of_fields_photos_album) . '&access_token=' . Session::getInstance()->fb_token;
        //Tools::showMessage('Url getAlbumPhotos : ' . $url, MessageType::Debug);
        return $url;
    }

}

class Album {

    static function updateAlbums() {
        try {
            $update_only_this_page = isset(Session::getInstance()->client_page_id) && self::getPageKeyInArray(Session::getInstance()->client_page_id) !== false ? self::getPageKeyInArray(Session::getInstance()->client_page_id) : false;
            //If page id is filled
            if($update_only_this_page !== false){
                $page=Config::app_fb_albums_array[$update_only_this_page];
                $update_only_this_album = isset(Session::getInstance()->client_album_id) && self::getAlbumKeyInArray(Session::getInstance()->client_album_id) !== false ? self::getAlbumKeyInArray(Session::getInstance()->client_album_id) : false;
                //If page id and album id are filled
                if ($update_only_this_album !== false) {
                    $time_start=microtime(true);
                    $album = $page['albums'][$update_only_this_album];
                    try {
                        self::constructAndSaveJSONAlbum($album['idAlbum'], array_key_exists('maxPhotos',$album) ? $album['maxPhotos'] : -1 );
                        self::successUpdate(true,$time_start,$page['idPage'],$page['namePage'],$album['idAlbum'],$album['nameAlbum']);
                    } catch (Exception $ex) {
                        self::successUpdate(false,$time_start,$page['idPage'],$page['namePage'],$album['idAlbum'],$album['nameAlbum']);
                        throw $ex;
                    }
                }
                else{
                    foreach ($page['albums'] as $album) {
                        $time_start=microtime(true);
                        try {
                            self::constructAndSaveJSONAlbum($album['idAlbum'], array_key_exists('maxPhotos',$album) ? $album['maxPhotos'] : -1 );
                            self::successUpdate(true,$time_start,$page['idPage'],$page['namePage'],$album['idAlbum'],$album['nameAlbum']);
                        } catch (Exception $ex) {
                            self::successUpdate(false,$time_start,$page['idPage'],$page['namePage'],$album['idAlbum'],$album['nameAlbum']);
                            throw $ex;
                        } 
                    }
                }
            }
            else {
                foreach (Config::app_fb_albums_array as $page) {
                    foreach ($page['albums'] as $album) {
                        $time_start=microtime(true);
                        try {
                            self::constructAndSaveJSONAlbum($album['idAlbum'], array_key_exists('maxPhotos',$album) ? $album['maxPhotos'] : -1 );
                            self::successUpdate(true,$time_start,$page['idPage'],$page['namePage'],$album['idAlbum'],$album['nameAlbum']);
                        } catch (Exception $ex) {
                            self::successUpdate(false,$time_start,$page['idPage'],$page['namePage'],$album['idAlbum'],$album['nameAlbum']);
                            throw $ex;
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    
    static function successUpdate($success, $time_start, $page_id, $page_name, $album_id, $album_name){
        $time=microtime(true)-$time_start;
        if($success){
            Tools::showMessage('Update succeeded ('.$time.' sec) :<br>- Page : '.$page_name.' (id:'.$page_id.')<br>- Album : ' .$album_name . ' (id:' . $album_id . ')', MessageType::Success);
        }
        else{
            Tools::showMessage('Update failed ('.$time.' sec) :<br>- Page : '.$page_name.' (id:'.$page_id.')<br>- Album :' .$album_name . ' (id:' . $album_id . ')', MessageType::Danger);
        }
    }

    static function getAlbumKeyInArray($album_id) {
        $array_key = false;
        foreach (Config::app_fb_albums_array as $page) {
            $array_key = array_search($album_id, array_column($page['albums'], 'idAlbum'));
            if($array_key!==false){
                break;
            }
        }        
        return $array_key;
    }
    
    static function getPageKeyInArray($page_id) {
        $array_key = array_search($page_id, array_column(Config::app_fb_albums_array, 'idPage'));
        return $array_key;
    }

    static function constructAndSaveJSONAlbum($album_id, $album_max_photos) {
        try {
            $album=[];
            $previous_album = json_decode(self::getAlbumAndPhotosLocally($album_id), true);
            $previous_id_photos_main_color = !empty($previous_album) ? self::getIdPhotosWithMainColor($previous_album) : array();
            //Tools::showMessage('<h1>$previous_album</h1>' . json_encode($previous_album, JSON_HEX_APOS), MessageType::Debug);
            //Tools::showMessage('<h1>$previous_main_color</h1>' . json_encode($previous_id_photos_main_color, JSON_HEX_APOS), MessageType::Debug);
            $album['album'] = self::getAlbumOnFacebook($album_id);
            //Tools::showMessage(json_encode($album['album'], JSON_HEX_APOS), MessageType::Debug);
            $album['photos'] = self::getAlbumPhotosOnFacebook($album_id, $album_max_photos);
            //Tools::showMessage(json_encode($album['photos'], JSON_HEX_APOS), MessageType::Debug);

            foreach ($album['photos']['data'] as &$photo) {
                if (Config::app_count_facebook_likes) {
                    if (isset($photo['likes'])) {
                        $photo['likes'][Config::app_count_facebook_likes_field_name] = count($photo['likes']['data']);
                    } else {
                        $photo['likes'][Config::app_count_facebook_likes_field_name] = 0;
                    }
                }
                if (Config::app_get_main_color_of_image) {
                    $main_rgb = array();
                    //Main color already analyzed
                    if (!empty($previous_id_photos_main_color) && array_key_exists($photo['id'], $previous_id_photos_main_color)) {
                        $main_rgb = $previous_id_photos_main_color[$photo['id']];
                    } else {
                        $main_rgb = ColorHandling::getMainColorOfImage(end($photo['images'])['source'], 0, 0.2, 0.2);
                    }

                    $photo[Config::app_get_main_color_of_image_field_name] = $main_rgb;
                }
            }

            file_put_contents(self::getAlbumPath($album_id), json_encode($album));
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    static function getIdPhotosWithMainColor($album) {
        $id_photos_main_color = array();
        foreach ($album['photos']['data'] as $photo) {
            $id_photos_main_color[$photo['id']] = $photo[Config::app_get_main_color_of_image_field_name];
        }
        return $id_photos_main_color;
    }

    static function getAlbumPath($album_id) {
        return Tools::getAbsoluteFilePath(str_replace("%album_id%", $album_id, Config::app_fb_json_albums_file_path));
    }

    static function getAlbumOnFacebook($album_id) {
        $url = Facebook::getAlbumUrl($album_id);
        $json = json_decode(file_get_contents($url), true);
        return $json;
    }

    static function getAlbumPhotosOnFacebook($album_id, $album_max_photos) {
       $load_all = $album_max_photos === -1;
        $number_of_photos_loaded = 0;
        $number_of_remaining_photos_to_load = $album_max_photos - $number_of_photos_loaded;
        if($load_all){
                $next_url_to_call = Facebook::getAlbumPhotosUrl($album_id,100);
        }
        else{
                $next_url_to_call = Facebook::getAlbumPhotosUrl($album_id,$number_of_remaining_photos_to_load );
        }
        $json = "";
        $json_current = "";
        //Break 1 : When the defined maximum has been reached
        while($number_of_remaining_photos_to_load !== 0){
                //First time
                if(empty($json)){
                        $json = json_decode(file_get_contents($next_url_to_call), true);
                }
                else{
                        $json_current = json_decode(file_get_contents($next_url_to_call), true);
                        $json['data'] = array_merge($json['data'],$json_current['data']);
                        $json['paging'] = array_key_exists('paging',$json_current) ? $json_current['paging'] : "";
                }
                $number_of_photos_loaded = count($json['data']);

                //Break 2 : When there is no more photo to load
                if(array_key_exists('paging',$json) && array_key_exists('next',$json['paging'])){
                        $next_url_to_call = $json['paging']['next'];
                        if(!$load_all){
                                $number_of_remaining_photos_to_load = $album_max_photos - $number_of_photos_loaded;
                                $next_url_to_call = preg_replace('/limit=\d*/','limit='.$number_of_remaining_photos_to_load,$next_url_to_call);
                        }
                }
                else{
                        break;
                }
        }
        unset($json['paging']);
		
        return $json;
    }

    static function getAlbumAndPhotosLocally($album_id) {
        $json = "";
        $file_name = self::getAlbumPath($album_id);
        if (file_exists($file_name)) {
            $json = file_get_contents($file_name);
        }
        return $json;
    }

}

class ColorHandling {

    static function getMainColorOfImage($src_image, $hsv_hue_varation, $hsv_saturation_varation, $hsv_value_varation) {
        $image = self::getImage($src_image);
        if ($image != null) {
            $thumb = imagecreatetruecolor(1, 1);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
            /* Color traitment */
            $dec = imagecolorat($thumb, 0, 0);
            $hex = dechex($dec);
            $rgb = sscanf($hex, "%02x%02x%02x");
            $hsv = self::RGB_TO_HSV($rgb);
            $hsv['H'] + $hsv_hue_varation > 1 ?: $hsv['S'] += $hsv_hue_varation;
            $hsv['S'] + $hsv_saturation_varation > 1 ?: $hsv['S'] += $hsv_saturation_varation;
            $hsv['V'] + $hsv_value_varation > 1 ?: $hsv['V'] += $hsv_value_varation;
            $rgb = self::HSVtoRGB($hsv);

            return $rgb;
        }
        return array(55, 55, 55); //grey
    }

    static function getImage($src_image) {
        $image = null;
        switch (exif_imagetype($src_image)) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($src_image);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($src_image);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($src_image);
                break;
            default:
                break;
        }
        return $image;
    }

    static function RGB_TO_HSV(array $rgb) {
        $HSL = array();

        $var_R = ($rgb[0] / 255);
        $var_G = ($rgb[1] / 255);
        $var_B = ($rgb[2] / 255);

        $var_Min = min($var_R, $var_G, $var_B);
        $var_Max = max($var_R, $var_G, $var_B);
        $del_Max = $var_Max - $var_Min;

        $V = $var_Max;

        if ($del_Max == 0) {
            $H = 0;
            $S = 0;
        } else {
            $S = $del_Max / $var_Max;

            $del_R = ( ( ( $var_Max - $var_R ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
            $del_G = ( ( ( $var_Max - $var_G ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;
            $del_B = ( ( ( $var_Max - $var_B ) / 6 ) + ( $del_Max / 2 ) ) / $del_Max;

            if ($var_R == $var_Max)
                $H = $del_B - $del_G;
            else if ($var_G == $var_Max)
                $H = ( 1 / 3 ) + $del_R - $del_B;
            else if ($var_B == $var_Max)
                $H = ( 2 / 3 ) + $del_G - $del_R;

            if ($H < 0)
                $H++;
            if ($H > 1)
                $H--;
        }

        $HSL['H'] = $H;
        $HSL['S'] = $S;
        $HSL['V'] = $V;

        return $HSL;
    }

    static function HSVtoRGB(array $hsv) {
        $H = $hsv['H'];
        $S = $hsv['S'];
        $V = $hsv['V'];

        //1
        $H *= 6;
        //2
        $I = floor($H);
        $F = $H - $I;
        //3
        $M = $V * (1 - $S);
        $N = $V * (1 - $S * $F);
        $K = $V * (1 - $S * (1 - $F));
        //4
        switch ($I) {
            case 0:
                list($R, $G, $B) = array($V, $K, $M);
                break;
            case 1:
                list($R, $G, $B) = array($N, $V, $M);
                break;
            case 2:
                list($R, $G, $B) = array($M, $V, $K);
                break;
            case 3:
                list($R, $G, $B) = array($M, $N, $V);
                break;
            case 4:
                list($R, $G, $B) = array($K, $M, $V);
                break;
            case 5:
            case 6: //for when $H=1 is given
                list($R, $G, $B) = array($V, $M, $N);
                break;
        }
        return array(floor($R * 255), floor($G * 255), floor($B * 255));
    }

}

abstract class MessageType {

    const Success = 0;
    const Info = 1;
    const Warning = 2;
    const Danger = 3;
    const Debug = 4;

}

class Tools {

    private static function makeDir($dirPath, $mode) {
        return is_dir($dirPath) || mkdir($dirPath, $mode, true);
    }
    
    static function getAbsoluteFilePath($relativeFilePath){
        return dirname(__FILE__).'/'.$relativeFilePath;
    }

    static function createDirPathsIfTheyDoNotExist($dir_paths) {
        foreach ($dir_paths as $dirPath) {
            self::makeDir(dirname($dirPath), 0700); /* Only owner have rights */
        }
    }

    static function showMessage($message, $messageType = -1) {

        switch ($messageType) {
            case MessageType::Success :
                Session::getInstance()->php_output_message .= '<div class=\"alert alert-success\">' . $message . '</div>';
                break;
            case MessageType::Info :
                Session::getInstance()->php_output_message .= '<div class=\"alert alert-info\">' . $message . '</div>';
                break;
            case MessageType::Warning :
                Session::getInstance()->php_output_message .= '<div class=\"alert alert-warning\">' . $message . '</div>';
                break;
            case MessageType::Danger :
                Session::getInstance()->php_output_message .= '<div class=\"alert alert-danger\">' . $message . '</div>';
                break;
            case MessageType::Debug :
                if (Config::app_debug_mode) {
                    Session::getInstance()->php_output_message .= '<div class=\"alert alert-debug\">' . $message . '</div>';
                }
                break;
            default:
                Session::getInstance()->php_output_message .= $message;
                break;
        }
    }

}

class ExceptionHandler {

    public function __construct() {
        @register_shutdown_function(array($this, "check_for_fatal"));
        @set_error_handler(array($this, "log_error"));

        @set_exception_handler(array($this, "log_exception"));
        //ini_set("display_errors", "off");
        error_reporting(E_ALL);
    }

    /**
     * Error handler, passes flow over the exception logger with new ErrorException.
     */
    public static function log_error($num, $str, $file, $line, $context = null) {
        self::log_exception(new ErrorException($str, 0, $num, $file, $line));
    }

    /**
     * Uncaught exception handler.
     */
    public static function log_exception($e) {

        if (Config::app_debug_mode == true) {

            $message = "<div style='text-align: center;'>";
            $message .= "<h2 style='color: rgb(190, 50, 50);'>Exception Occured:</h2>";
            $message .= "<table style='width: 800px; display: inline-block;'>";
            $message .= "<tr style='background-color:rgb(230,230,230);'><th style='width: 80px;'>Type</th><td>" . get_class($e) . "</td></tr>";
            $message .= "<tr style='background-color:rgb(240,240,240);'><th>Message</th><td>{$e->getMessage()}</td></tr>";
            $message .= "<tr style='background-color:rgb(230,230,230);'><th>File</th><td>{$e->getFile()}</td></tr>";
            $message .= "<tr style='background-color:rgb(240,240,240);'><th>Line</th><td>{$e->getLine()}</td></tr>";
            $message .= "</table></div>";

            Tools::showMessage($message, MessageType::Debug);
        } else {
            if (!empty(self::getExceptionFilePath())) {
                $message = "Type: " . get_class($e) . "; Message: {$e->getMessage()}; File: {$e->getFile()}; Line: {$e->getLine()};";
                file_put_contents(self::getExceptionFilePath(), $message . PHP_EOL, FILE_APPEND);
            }
        }

        throw $e;
    }

    /**
     * Checks for a fatal error, work around for set_error_handler not working on fatal errors.
     */
    public static function check_for_fatal() {
        $error = error_get_last();
        if ($error["type"] == E_ERROR)
            self::log_error($error["type"], $error["message"], $error["file"], $error["line"]);
    }
    
    private static function getExceptionFilePath(){
        return Tools::getAbsoluteFilePath(Config::app_exception_file_path);
    }

}
?>





<?php
if (!Session::getInstance()->is_json_output) {
    ?>

    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Update albums</title>

            <!--You can replace by boostrap 3-->
            <style>
                * {
                    -webkit-box-sizing: border-box;
                    -moz-box-sizing: border-box;
                    box-sizing: border-box;
                }

                body {
                    font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
                    font-size: 14px;
                    line-height: 1.42857143;
                    color: #333;
                    margin:0;
                }

                .container {
                    padding-right: 15px;
                    padding-left: 15px;
                    margin-right: auto;
                    margin-left: auto;
                }

                @media (min-width: 768px) {
                    .container {
                        width: 750px;
                    }
                }
                @media (min-width: 992px) {
                    .container {
                        width: 970px;
                    }
                }
                @media (min-width: 1200px) {
                    .container {
                        width: 1170px;
                    }
                }

                .h1, h1 {
                    font-size: 36px;
                }

                .h2, h2 {
                    font-size: 30px;
                }

                .h1, .h2, .h3, h1, h2, h3 {
                    margin-top: 20px;
                    margin-bottom: 10px;
                }

                .h1, .h2, .h3, .h4, .h5, .h6, h1, h2, h3, h4, h5, h6 {
                    font-family: inherit;
                    font-weight: 500;
                    line-height: 1.1;
                    color: inherit;
                }
                button, input, select, textarea {
                    font-family: inherit;
                    font-size: inherit;
                    line-height: inherit;
                }

                .btn {
                    display: inline-block;
                    padding: .5rem 1rem;
                    border: 1px solid transparent;
                    border-radius: .25rem;
                }

                .btn-block {
                    display: block;
                    width: 100%;
                }

                .btn-lg {
                    padding: 10px 16px;
                    font-size: 18px;
                    line-height: 1.3333333;
                    border-radius: 6px;
                }

                .btn-primary {
                    color: #fff;
                    background-color: #337ab7;
                    border-color: #2e6da4;
                }
                .btn-primary:hover {
                    color: #fff;
                    background-color: #286090;
                    border-color: #204d74;
                }

                .sr-only {
                    position: absolute;
                    width: 1px;
                    height: 1px;
                    padding: 0;
                    margin: -1px;
                    overflow: hidden;
                    clip: rect(0,0,0,0);
                    border: 0;
                }

                .form-control {
                    display: block;
                    width: 100%;
                    padding: .5rem .75rem;
                    border: 1px solid rgba(0,0,0,.15);
                    border-radius: .25rem;
                }

                .form-control:focus {
                    color: #464a4c;
                    background-color: #fff;
                    border-color: #5cb3fd;
                    outline: 0;
                    webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075),0 0 8px rgba(102,175,233,.6);
                    box-shadow: inset 0 1px 1px rgba(0,0,0,.075),0 0 8px rgba(102,175,233,.6);
                }

                pre{
                    padding: 9.5px;
                    font-size: 13px;
                    background-color: #f5f5f5;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                }

                .alert{
                    padding: 14px;
                    margin-bottom: 20px;
                    border: 1px solid transparent;
                    border-radius: 4px;
                }

                .alert-success{
                    color: #3c763d;
                    background-color: #dff0d8;
                    border-color: #d6e9c6;
                }

                .alert-info{
                    color: #31708f;
                    background-color: #d9edf7;
                    border-color: #bce8f1;
                }

                .alert-warning{
                    color: #8a6d3b;
                    background-color: #fcf8e3;
                    border-color: #faebcc;
                }

                .alert-danger{
                    color: #a94442;
                    background-color: #f2dede;
                    border-color: #ebccd1;
                }

            </style>


            <!--Don't delete-->
            <style>
                .alert-debug{
                    color: #936400;
                    background-color: #ffbd33;
                    border-color: #ffb51a;
                }

                .title-main{
                    text-align:center;
                    padding-bottom: 40px;
                }

                .title-form{
                    text-align: center;
                    margin-top:0px;
                }


                body {
                    padding-top: 40px;
                    padding-bottom: 40px;
                    background-color: #eee;
                }

                .form-signin {
                    max-width: 380px;
                    padding-top: 20px;
                    padding-right: 20px;
                    padding-bottom: 20px;
                    padding-left: 20px;
                    margin: 0 auto;
                    background-color: #FFF;
                    border-radius:6px;
                }
                .form-signin .form-signin-heading,
                .form-signin{
                    margin-bottom: 10px;
                }
                .form-signin {
                    font-weight: normal;
                }
                .form-signin .form-control {
                    position: relative;
                    height: auto;
                    -webkit-box-sizing: border-box;
                    -moz-box-sizing: border-box;
                    box-sizing: border-box;
                    padding: 10px;
                    font-size: 16px;
                }
                .form-signin .form-control:focus {
                    z-index: 2;
                }
                .form-signin input[type="text"] {
                    margin-bottom: -1px;
                    border-bottom-right-radius: 0;
                    border-bottom-left-radius: 0;
                }
                .form-signin .btn {
                    border-top-right-radius: 0;
                    border-top-left-radius: 0;
                    margin-bottom: 20px;
                }
                .form-signin select {
                    border-radius: 0;
                }

                [required] {
                    box-shadow: none;
                }

                .loader {
                    display: none;
                    margin: auto;
                    margin-bottom: 20px;
                    border: 7px solid #f3f3f3;
                    border-radius: 50%;
                    border-top: 7px solid #337ab7;
                    width: 30px;
                    height: 30px;
                    -webkit-animation: spin 2s linear infinite;
                    animation: spin 2s linear infinite;
                }

                @-webkit-keyframes spin {
                    0% { -webkit-transform: rotate(0deg); }
                    100% { -webkit-transform: rotate(360deg); }
                }

                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

            </style>

        </head>
        <body>
            <div class="container">
                <h1 class="title-main">Update Facebook albums</h1>
                <form class="form-signin" method="get" action="">
                    <h2 class="title-form">Please fill the token</h2>
                    <label for="token" class="sr-only">Token</label>
                    <input type="text" id="token" name="token" required="" class="form-control" placeholder="Token" autofocus="" value="<?php echo Session::getInstance()->client_token; ?>">
                    <select class="form-control" id="page_id" name="page_id" onchange="pageIdChanged();">
                    </select>
                    <select class="form-control" id="album_id" name="album_id">
                    </select>
                    <button class="btn btn-lg btn-primary btn-block" type="submit" onclick="document.getElementById('loader').style.display = 'block';">Submit</button>

                    <div id="loader" class="loader"></div>
                    <script>
                        var debug = <?php echo json_encode(Config::app_debug_mode); ?>;
                        if (!debug) {
                            document.write('<?php echo Session::getInstance()->php_output_message; ?>');
                        }
                    </script>
                </form></div>
            <script>
                if (debug) {
                    document.write('<?php echo Session::getInstance()->php_output_message; ?>');
                }
            </script>
            <script>
                var fbAlbums = <?php echo json_encode(Config::app_fb_albums_array);?>;
                setSelectList("page_id",fbAlbums,"All Pages", "idPage","namePage","<?php echo Session::getInstance()->client_page_id; ?>");
                setSelectList("album_id",getAlbumsOfPage("<?php echo Session::getInstance()->client_page_id; ?>"),"All Albums", "idAlbum","nameAlbum","<?php echo Session::getInstance()->client_album_id ?>");

                
                function setSelectList(idSelectList, array, defaultOptionText, optionValue, optionText, selectedValue){
                    var selectList = document.getElementById(idSelectList);
                    removeOptions(selectList);
                    
                    var defaultOption = document.createElement("option");
                    defaultOption.value = "";
                    defaultOption.text = defaultOptionText;
                    selectList.appendChild(defaultOption);
                        
                    for (var i = 0; i < array.length; i++) {
                        var option = document.createElement("option");
                        option.value = array[i][optionValue];
                        option.text = array[i][optionText];
                        if(option.value === selectedValue){
                            option.selected = true;
                        }
                        selectList.appendChild(option);
                    }
                }
                
                function removeOptions(selectbox)
                {
                    var i;
                    for(i = selectbox.options.length - 1 ; i >= 0 ; i--)
                    {
                        selectbox.remove(i);
                    }
                }
                
                function pageIdChanged(){
                    var selectListPage = document.getElementById("page_id");
                    var pageId = selectListPage.options[selectListPage.selectedIndex].value;
                    setSelectList("album_id",getAlbumsOfPage(pageId),"All Albums", "idAlbum","nameAlbum","<?php echo Session::getInstance()->client_album_id ?>");
                }
                
                function getAlbumsOfPage(idPage){
                    var albumsPage = [];
                    //For all pages
                    for (var i = 0; i < fbAlbums.length; i++) {
                        if(idPage === "" || fbAlbums[i]["idPage"] === idPage){
                            for (var i2 = 0; i2 < fbAlbums[i]["albums"].length; i2++) {
                                albumsPage.push(fbAlbums[i]["albums"][i2]);
                            }
                        }
                    }
                    return albumsPage;
                }
            </script>
        </body>
    </html>

    <?php
}
?> 