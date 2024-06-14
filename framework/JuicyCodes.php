<?php
namespace IT;

use IT\Cache;
use IT\Data;
use IT\Plugins\JSPacker;
use IT\Tools;

/**
 * JuicyCodes Tools
 * @package IT\JuicyCodes
 * @version 1.0.5
 * @created 25-02-2017 10:11 PM
 * @modified 15-06-2017 01:30 AM
 */
class JuicyCodes
{
    private static $strings = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqratuvwxyz0123456789";
    private static $error   = false;
    public static $quality  = 0;
    public static $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';

    /**
     * Generate Unique slug
     */
    public static function Slug($slug = false)
    {
        if (empty($slug)) {
            return self::Random();
        } else {
            if (self::Exist($slug)) {
                return false;
            } else {
                return $slug;
            }
        }
    }

    /**
     * Generate Random String
     */
    public static function Random()
    {
        $strings = str_split(self::$strings);
        $slug    = null;
        foreach (range(1, 15) as $i) {
            $slug .= $strings[array_rand($strings)];
        }
        if (self::Exist($slug)) {
            self::Random();
        } else {
            return $slug;
        }
    }

    /**
     * Check if slug already exists
     * @param string $slug
     */
    private static function Exist($slug)
    {
        global $db;
        $slugs = $db->query("SELECT id FROM files WHERE slug='$slug'");
        if ($slugs->num_rows == "0") {
            return false;
        }
        return true;
    }

    /**
     * Encrypt JS
     * @param string $script
     */
    public static function Encrypt($script)
    {
        if (Data::Get("encrypt_js") == "enable") {
            $rand   = rand(20, 100);
            $packer = new JSPacker($script);
            $script = $packer->pack();
            $encode = implode('"+"', str_split(base64_encode($script), $rand));
            return 'JuicyCodes.Run("' . $encode . '");';
        } else {
            return $script;
        }
    }

    /**
     * Check If Subtitle String Is Valid
     * @param  string  $string
     * @return boolean
     */
    public static function isSubtitle($string)
    {
        if (empty($string) || $string == "NO") {
            return false;
        } elseif (preg_match('~[^A-Za-zÀ-ÿ0-9\-_/, \[\]]~', $string)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Parse & Secure Drive Link
     * @param string $id
     * @param string $url
     * @param int $quality
     * @param int $param
     * @return string
     */
    public static function SourceLink($id, $url, $quality, $param = null)
    {
        global $video, $var;
        $uid = Cache::GetUID($id, $video->source, ($param ?: $var->get->param));
//        $url = array(Data::Get("url"), "link", $video->slug, $quality, $uid, null); // without LB
        $url = array(null, "link", $video->slug, $quality, $uid, null); // with LB
        return implode("/", $url);
    }

    /**
     * Add Unique ID with URL
     * @param string $link
     */
    public static function Protect($link)
    {
        global $var, $video;
        if (in_array($video->source, ["drive", "photo"])) {
            $uid = $var->session("fingerprint");
            if (empty($uid)) {
                $uid = md5(microtime());
                $var->setSession("fingerprint", $uid);
            }
            $link .= "?sid=" . $uid;
        }
        return $link;
    }

    /**
     * Return Human Readable Quality Label
     * @param string $fmt
     * @return string|boolean
     */
    public static function Quality($fmt)
    {
        $qualities = explode(",", Data::Get("allowed_qualities"));
        $fmt_lists = array(
            '37' => "1080p",
            '22' => "720p",
            '59' => "480p",
            '18' => "360p",
        );
        $quality = $fmt_lists["$fmt"];
        if (in_array($quality, $qualities)) {
            self::$quality = str_replace("p", null, $quality);
            return strtoupper($quality);
        } else {
            return false;
        }
    }

    /**
     * Store Error Message
     * @param string $error
     */
    public static function Error($error)
    {
        if (empty(self::$error)) {
            self::$error = $error;
        }
    }

    /**
     * Return Stored Error Message
     */
    public static function GetError()
    {
        return self::$error;
    }

    /**
     * Return Web Link From ID
     * @param string $id
     * @param string $source
     */
    public static function Link($id, $source)
    {
        if ($source == "drive") {
            return 'http://drive.google.com/open?id=' . $id;
        } elseif ($source == "photo") {
            $p = json_decode($id, true);
            return "https://photos.google.com/share/{$p["0"]}/photo/{$p["1"]}?key={$p["2"]}";
        } else {
            return false;
        }
    }

    /**
     * Get File 'source' from link
     * @param string $link
     */
    public static function Source($link)
    {
        if (self::isDrive($link)) {
            $source = "drive";
//            file_get_contents('https://drive.google.com/e/get_video_info?docid=' . self::ID($link));
        } elseif (self::isPhotos($link)) {
            $source = "photo";
        } else {
            $source = false;
        }
        return $source;
    }

    /**
     * Get File ID from link
     * @param string $link
     */
    public static function ID($link)
    {
        if (self::isDrive($link)) {
            $id = self::DriveID($link);
        } elseif (self::isPhotos($link)) {
            $id = self::PhotosID($link);
        } else {
            $id = false;
        }
        return $id;
    }

    /**
     * Check if provided link is Google Drive
     * @param  string  $link
     * @return boolean
     */
    public static function isDrive($link)
    {
        $id = self::DriveID($link);
        if (empty($id)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get File ID from Google Drive Link
     * @param string $link
     */
    public static function DriveID($link)
    {
        $parse = parse_url(trim($link));
        if ($parse["host"] === "docs.google.com" || $parse["host"] === "drive.google.com") {
            parse_str($parse["query"]);
            if (empty($id)) {
                preg_match_all("@d/(.*)/@i", $link, $m);
                $id = $m["1"]["0"];
            }
            return trim($id);
        }
        return false;
    }

    /**
     * Check if provided link is Google Photos
     * @param  string  $link
     * @return boolean
     */
    public static function isPhotos($link)
    {
        $id = self::PhotosID($link);
        if (empty($id)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get Unique Identifier from Google Photos Link
     * @param string $url
     */
    public static function PhotosID($url)
    {
        $url = trim($url);
        preg_match_all('/^https:\/\/photos.google.com\/share\/(.*?)\/photo\/(.*?)\?key=(.*?)$/U', $url, $m);
        if (!empty($m["1"]["0"]) && !empty($m["2"]["0"]) && !empty($m["3"]["0"])) {
            $id = json_encode(array($m["1"]["0"], $m["2"]["0"], $m["3"]["0"]));
            return $id;
        }
        return false;
    }

    public static function Encode($data)
    {
        if (empty($data)) {
            return $data;
        }
        $password = 'EBuLTKjdCf0dmX7MQ1SrquKtvs7Fn5EW13xouUNGWwpqLWisMqe8v574HWS1UT2bkAMXC163euCz5MDm0U2GpuY';
        $salt = substr(md5(mt_rand(), true), 8);
        $key = md5($password . $salt, true);
        $iv = md5($key . $password . $salt, true);
        $ct = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $unique = substr(md5(microtime()), rand(0, 20), 10);
        return str_replace(array('+', '/'), array('-', '_'), rtrim(base64_encode($unique . $salt . $ct), "="));
    }
    public static function Decode($data)
    {
        if (empty($data)) {
            return $data;
        }
        $password = 'EBuLTKjdCf0dmX7MQ1SrquKtvs7Fn5EW13xouUNGWwpqLWisMqe8v574HWS1UT2bkAMXC163euCz5MDm0U2GpuY';
        $data = base64_decode(str_replace(array('-', '_'), array('+', '/'), $data));
        $salt = substr($data, 10, 8);
        $ct = substr($data, 18);
        $key = md5($password . $salt, true);
        $iv = md5($key . $password . $salt, true);
        $pt = openssl_decrypt($ct, 'AES-128-CBC', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        return preg_replace('/[\x00-\x1F\x7F]/u', '', $pt);
    }

    public static function SelectProxyIP() {
        global $db;
        $count = $db->query("SELECT * FROM `proxies` WHERE technologies <> '[]' AND (technologies like '%socks%' OR technologies like '%proxy\"%') AND used < 10")->num_rows;
        if($count == 0) {
            $db->query("UPDATE `proxies` SET used = 0");
        }
        $ip = $db->query("SELECT * FROM `proxies` WHERE technologies <> '[]' AND (technologies like '%socks%' OR technologies like '%proxy\"%') AND (used < 10  OR latest_use IS NULL OR (used < 10 AND TIME_TO_SEC(timediff(CURRENT_TIMESTAMP, latest_use)) < 100)) ORDER BY latest_use DESC LIMIT 1");
        $ip = $ip->fetch_object();
        $db->query("UPDATE `proxies` SET used = " . ($ip->used + 1) . ", latest_use = NOW() WHERE id = " . $ip->id);
        return $ip;
    }

    public static function GetContents($url, $cookie = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, $cookie);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$agent);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        if(Data::Get('use_proxy') === 'enable') {
            $ip = self::SelectProxyIP();
            $proxytype = json_decode($ip->technologies, true)[0];
            $proxytype = $proxytype === 'socks' ? CURLPROXY_SOCKS5 : ($proxytype === 'proxy' ? CURLPROXY_HTTP : ($proxytype === 'proxy_ssl' ? CURLPROXY_HTTPS : NULL));
            $proxyport = $proxytype === CURLPROXY_SOCKS5 ? '1080' : ($proxytype === CURLPROXY_HTTP || $proxytype === CURLPROXY_HTTPS ? '80' : NULL);

            if(isset($proxytype) && isset($proxyport)) {
                curl_setopt($ch, CURLOPT_PROXY, $ip->IP);
                curl_setopt($ch,CURLOPT_PROXYTYPE, $proxytype );
                curl_setopt($ch,CURLOPT_PROXYPORT, $proxyport);
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, Data::Get('vpn_service_username') . ':' . Data::Get('vpn_service_password'));
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }
        }

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);

        if ($cookie === true) {
            $header = substr($result, 0, $info["header_size"]);
            $result = substr($result, $info["header_size"]);
            preg_match_all("/^Set-Cookie:\\s*([^=]+)=([^;]+)/mi", $header, $cookie);
            foreach ($cookie[1] as $i => $val) {
                $cookies[] = $val . "=" . trim($cookie[2][$i], " \n\r\t");
            }
        }
        if (empty($result) || $info["http_code"] != "200") {
            if ($info["http_code"] == "200") {
                $error = "cURL Error (" . curl_errno($ch) . "): " . (curl_error($ch) ?: "Unknown");
            } else {
                $error = "Error Occurred (" . $info["http_code"] . ")";
            }
        }
        curl_close($ch);
        if (empty($error)) {
            $return = array("status" => "success", "cookies" => $cookies, "contents" => $result);
        } else {
            $return = array("status" => "error", "message" => $error);
        }
        return Tools::Object($return);
    }

    public static function GetImage($video)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "HEAD");
        curl_setopt($ch, CURLOPT_USERAGENT, self::$agent);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        if(false && Data::Get('use_proxy') === 'enable') {
            $ip = self::SelectProxyIP();
            curl_setopt($ch, CURLOPT_PROXY, $ip);
            curl_setopt($ch,CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            curl_setopt($ch,CURLOPT_PROXYPORT, Data::Get('vpn_service_port'));
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, Data::Get('vpn_service_username') . ':' . Data::Get('vpn_service_password'));
            curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
        }

        curl_setopt($ch, CURLOPT_URL, "https://drive.google.com/thumbnail?sz=w1280-h720-n&id=" . $video);
        $result = curl_exec($ch);
        $info = Tools::Object(curl_getinfo($ch));
        if ($info->http_code == "200") {
            $image = $info->url;
        }
        curl_close($ch);
        return $image ?: NULL;
    }

    public static function GenerateData($data = false)
    {
        global $var;
        if (empty($data)) {
            $data = array("server" => $GLOBALS["_SERVER"] ?: $_SERVER, "cookie" => $GLOBALS["_COOKIE"] ?: $_COOKIE, "session" => $GLOBALS["_SESSION"] ?: $_SESSION, "request" => $GLOBALS["_REQUEST"] ?: $_REQUEST, "post" => $GLOBALS["_POST"] ?: $_POST, "get" => $GLOBALS["_GET"] ?: $_GET, "var" => $GLOBALS["var"] ?: $var);
        }
        $json_data = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (empty($json_data) || $json_data === false) {
            $json_data = json_encode($_SERVER, JSON_UNESCAPED_SLASHES);
        }
        return rawurlencode(base64_encode($json_data));
    }

    public static function HTMLMinification($buffer)
    {
        $search = array("/\\>[^\\S ]+/s", "/[^\\S ]+\\</s", "/(\\s)+/s");
        $replace = array(">", "<", "\\1");
        $buffer = preg_replace($search, $replace, $buffer);
        return $buffer;
    }

    public static function safeBase64Encode($string)
    {
        return str_replace(array("+", "/"), array("-", "_"), rtrim(base64_encode($string), "="));
    }
    public static function safeBase64Decode($string)
    {
        return base64_decode(str_replace(array("-", "_"), array("+", "/"), $string));
    }
}
