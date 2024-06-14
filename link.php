<?php
set_time_limit(0);
require_once "config.php";
require_once ABSPATH . "/firewall.php";
ob_end_clean();
session_write_close();
$slug = $var->get->slug;
$videos = $db->query("SELECT id,link,slug,source FROM files WHERE slug='" . $slug . "'");
if ($videos->num_rows != "1") {
    ERROR_404();
}
$links = $db->query("SELECT * FROM links WHERE uid='" . $var->get->uid . "'");
if ($links->num_rows != "1") {
    ERROR_404();
}
$link = $links->fetch_object();
$video = $videos->fetch_object();
$data = json_decode($link->data);
if ($link->type == "video_download" && $var->session("fingerprint") != $var->get->sid) {
    ERROR_404();
}
$source = $data->sources->{$var->get->quality}->file;
if (!empty($source)) {
    $source = get_video($data);
    $headers = $source["headers"];
    $db->close();
    header($headers[0]);
    if (http_response_code() != "403") {
        if ($link->type == "video_download") {
            header("Pragma: public");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Disposition: attachment; filename=\"video_" . $var->get->quality . "p.mp4\"");
        }
        if (isset($headers["Content-Type"])) {
            header("Content-Type: " . $headers["Content-Type"]);
        }
        if (isset($headers["Content-Length"])) {
            header("Content-Length: " . $headers["Content-Length"]);
        }
        if (isset($headers["Accept-Ranges"])) {
            header("Accept-Ranges: " . $headers["Accept-Ranges"]);
        }
        if (isset($headers["Content-Range"])) {
            header("Content-Range: " . $headers["Content-Range"]);
        }
        $fp = fopen($source["link"], "rb");
        while (!feof($fp)) {
            echo fread($fp, 1024 * 1024 * IT\Data::Get("chunk_size"));
            flush();
            ob_flush();
        }
        fclose($fp);
    } else {
        ERROR_404();
    }
} else {
    ERROR_404();
}
function get_video($data)
{
    global $db;
    global $var;
    global $link;
    global $video;
    global $reloads;
    $source = $data->sources->{$var->get->quality}->file;
    $cookies = implode("; ", $data->cookies);
    $options = array("http" => array("header" => set_headers($cookies)));
    stream_context_set_default($options);
    $headers = get_headers($source, true);
    if (isset($headers["Location"])) {
        if (is_array($headers["Location"])) {
            $headers["Location"] = end($headers["Location"]);
        }
        $source = $headers["Location"];
        $headers = get_headers($source, true);
    }
    $status_code = substr($headers[0], 9, 3);
    if ($status_code == "403") {
        $reloads++;
        if ($reloads <= 5) {
            $url = IT\JuicyCodes::Decode($video->link);
            if ($video->source == "drive") {
                $data = get_drive($url, $link->type);
            } else {
                if ($video->source == "photo") {
                    $data = get_photos($url, $link->type);
                }
            }
            IT\Cache::GetUID($url, $video->source, $link->type, true);
            IT\Cache::Store($data, true);
            IT\Cache::Links($data, $url, $video->source, $link->type);
            return get_video(IT\Tools::Object(array("sources" => $data->orginal, "cookies" => $data->cookies)));
        }
    }
    return array("link" => $source, "headers" => $headers);
}
function get_drive($id, $type)
{
    $url = "https://drive.google.com/e/get_video_info?docid=" . $id;
    $get = IT\JuicyCodes::GetContents($url, true);
    if ($get->status == "success" && !empty($get->contents)) {
        $contents = IT\Tools::Parse($get->contents);
        if ($contents->status == "ok") {
            $streams = explode(",", $contents->fmt_stream_map);
            foreach ($streams as $stream) {
                list($quality, $link) = explode("|", $stream);
                if (IT\JuicyCodes::Quality($quality)) {
                    $masked[IT\JuicyCodes::$quality] = array("file" => IT\JuicyCodes::SourceLink($id, $link, IT\JuicyCodes::$quality, $type), "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                    $links[IT\JuicyCodes::$quality] = array("file" => $link, "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                }
            }
            $preview = IT\JuicyCodes::GetImage($id);
        } else {
            IT\JuicyCodes::Error($contents->reason);
        }
    } else {
        IT\JuicyCodes::Error($get->error);
    }
    return IT\Tools::Object(array("sources" => $masked, "orginal" => $links, "preview" => $preview, "cookies" => $get->cookies));
}
function get_photos($ids, $type)
{
    $url = JuicyCodes::Link($ids, "photo");
    $get = IT\JuicyCodes::GetContents($url);
    $links = array();
    $preview = NULL;
    if ($get->status == "success" && !empty($get->contents)) {
        $streams = explode("url\\u003d", $get->contents);
        unset($streams[0]);
        foreach ($streams as $stream) {
            $stream = urldecode(str_replace(array("\\u003d", "\\u0026"), array("=", "&"), $stream));
            $stream = explode("&", $stream);
            $stream = $stream[0];
            preg_match("/=m([0-9]+)/", $stream, $quality);
            $quality = $quality[1];
            if (IT\JuicyCodes::Quality($quality)) {
                $masked[IT\JuicyCodes::$quality] = array("file" => IT\JuicyCodes::SourceLink($id, $link, IT\JuicyCodes::$quality, $type), "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                $links[IT\JuicyCodes::$quality] = array("file" => $stream, "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
            }
        }
        preg_match_all("/src=\"(.*)\"/U", $get->contents, $images);
        $preview = $images[1][1] ?: $images[1][2];
    } else {
        IT\JuicyCodes::Error($get->error);
    }
    return IT\Tools::Object(array("sources" => $masked, "orginal" => $links, "preview" => $preview, "cookies" => NULL));
}
function set_headers($cookies)
{
    global $link;
    if (!empty($cookies)) {
        $headers = array("Cookie: " . $cookies);
    } else {
        $headers = array();
    }
    if (isset($_SERVER["HTTP_RANGE"])) {
        $headers[] = "Range: " . $_SERVER["HTTP_RANGE"];
    }
    if ($link->type == "embed_player" && empty($_SERVER["HTTP_RANGE"])) {
        ERROR_404();
    }
    return $headers;
}
function ERROR_404()
{
    header("HTTP/1.0 404 Not Found");
    require TEMPLATES . "pages/no_video.php";
    exit;
}

?>