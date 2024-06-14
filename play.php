<?php
require_once "config.php";
if (IT\Data::Get("minify_html") == "enable") {
    ob_start("html_minification");
}

require_once ABSPATH . "/firewall.php";

$slug = $var->get->slug;
$videos = $db->query("SELECT id,link,embed,slug,subtitle,preview,title,type,source,views FROM files WHERE slug='" . $slug . "'");
if ($videos->num_rows != "1") {
    header("HTTP/1.0 404 Not Found");
    require TEMPLATES . "pages/no_video.php";
    exit;
}
$video = $videos->fetch_object();
$link = IT\JuicyCodes::Decode($video->link);
$player = IT\Data::Get("player");
$preview = IT\JuicyCodes::Decode($video->preview);
$subtitle = IT\JuicyCodes::Decode($video->subtitle);

if ($var->get->param === "embed_player") {
    if ($video->type == "2" || IT\Data::Get("embed_player") === "disable") {
        require TEMPLATES . 'pages/no_player.php';
        exit;
    }
    if (IT\Data::Get('subtitle') === 'on') {
        if (IT\JuicyCodes::isSubtitle($subtitle)) {
            $subtitles = explode(',', $subtitle);
            $subtitle = array();
            foreach ($subtitles as $subs) {
                if (IT\Data::Get("auto_cc") == "enable") {
                    $default = $default ? false : true;
                }
                $subtitle[] = array('file' => IT\Data::Get('url') . '/assets/subtitle/' . $video->id . "_" . IT\Tools::Clean($subs) . ".srt", "label" => $subs, "kind" => "captions", "default" => $default);
            }
        } else {
            $subtitle = NULL;
        }
    }
    $upside = false;
    if (IT\Data::Get('file_download') === 'enable' && $video->type != "1" && IT\Data::Get("dl_btn") === "on") {
        $upside = true;
    }
    if ($player === 'jcplayer') {
        $temp_name = 'jcplayer';
        require TEMPLATES . 'jcplayer/template.php';
    } else {
        if ($player === "jwplayer") {
            $temp_name = 'jwplayer';
            require TEMPLATES . 'jwplayer/template.php';
        } else {
            if ($player === 'videojs') {
                $temp_name = 'videojs';
                require TEMPLATES . 'videojs/template.php';
            } else {
                require TEMPLATES . 'pages/no_player.php';
                exit;
            }
        }
    }
} else {
    if ($var->get->param === "video_download" && IT\Data::Get("rely") === "core") {
        if ($video->type == "1" || IT\Data::Get("file_download") === "disable") {
            require TEMPLATES . "pages/no_download.php";
            exit;
        }
        $upside = false;
        if (IT\Data::Get("embed_player") == "enable" && $video->type != "2") {
            $upside = true;
        }
        $temp_name = 'download';
        require TEMPLATES . 'download/template.php';
    } else {
        header('HTTP/1.0 404 Not Found');
        require TEMPLATES . 'pages/404_error.php';
        exit;
    }
}
$components = IT\Tools::Object(template_header($preview));
$assets = array();
foreach ($components->stylesheets as $css) {
    $assets[] = '<link rel="stylesheet" type="text/css" href="' . (IT\Tools::GetHost($css) ? $css : IT\Data::Get('url') . '/templates/' . $temp_name . '/assets/' . $css) . '">';
}
foreach ($components->javascripts as $js) {
    $assets[] = '<script src="' . (IT\Tools::GetHost($js) ? $js : IT\Data::Get('url') . '/templates/' . $temp_name . '/assets/' . $js) . '"></script>';
}
$assets[] = $components->html;
$pop_ad = IT\Data::Get('pop_ad') === 'enable';
$banner_ad = IT\Data::Get('banner_ad') === 'enable';
$vast_ad = IT\Data::Get('vast_ad') === 'enable';
$pop_ad_code = base64_decode(IT\Data::Get('pop_ad_code'));
$banner_ad_code = base64_decode(IT\Data::Get('banner_ad_code'));
$vast_ad_code = base64_decode(IT\Data::Get('vast_ad_code'));
$data = get_video($link);
if (empty($preview) || IT\Data::Get('custom_preview') === "hide") {
    $preview = NULL;
    if (IT\Data::Get('auto_preview') === 'enable') {
        $preview = $data->preview;
    }
    $preview = $preview ?: str_replace('{ASSETS}', IT\Data::Get('url') . '/assets', IT\Data::Get('default_preview'));
}
echo "\n<!doctype html>\n<html>\n    <head>\n        <meta charset=\"utf-8\">\n        <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0\">\n        <title>" . ($video->title ?: IT\Data::Get("default_title")) . "</title>\n        " . implode("\n\t\t", $assets) . "\n    </head>\n    <body>\n        " . template_body($data->sources, $subtitle, $preview, $video->slug, $upside, $vast_ad, $vast_ad_code) . "\n        " . template_footer($pop_ad, $pop_ad_code, $banner_ad, $banner_ad_code) . "\n    </body>\n</html>\n";
$db->update("files", array("views" => $video->views + 1), array("id" => (string) $video->id));
$db->query("INSERT INTO stats (date,views) VALUES('" . $var->date . "', '1') ON DUPLICATE KEY UPDATE views=views+1");
function get_video($url)
{
    global $video;
    global $var;
    if (IT\Data::Get("rely") == "core") {
        if (IT\Data::Get("caching") == "on") {
            $data = IT\Cache::Get($url, $video->source, $var->get->param);
        }
        if (empty($data)) {
            if ($video->source == "drive") {
                $data = get_drive($url, $video->slug);
            } else {
                if ($video->source == "photo") {
                    $data = get_photos($url);
                } else {
                    $data = array();
                }
            }
            IT\Cache::Store($data);
            IT\Cache::Links($data, $url, $video->source, $var->get->param);
        }
    } else {
        $data->sources = $video->embed;
    }
    if (empty($data) || empty($data->sources)) {
        $data = IT\Tools::Object(array("sources" => array(array("file" => str_replace("{ASSETS}", IT\Data::Get("url") . "/assets", IT\Data::Get("default_video")), "label" => "NA", "type" => "video/mp4")), "preview" => $data->preview));
        IT\JuicyCodes::Error("No Link Found");
    }
    $sources = (array) $data->sources;
    if (IT\Data::Get("quality_order") == "asc") {
        ksort($sources);
    } else {
        krsort($sources);
    }
    $data->sources = IT\Tools::Object(array_values($sources));
    return $data;
}
function get_drive($id, $slug = false)
{
    $url = 'https://drive.google.com/file/d/' . $id . '/view?hl=en-US';
    $get = IT\JuicyCodes::GetContents($url, true);
    if ($get->status === "success" && !empty($get->contents)) {
        $isError = preg_match('/"reason","(.*)"/', $get->contents, $error);
        if ($isError == false) {
            $streams = explode("url\\u003d", $get->contents);
            unset($streams[0]);
            foreach ($streams as $stream) {
                $stream = urldecode(str_replace(array("\\u003d", "\\u0026"), array("=", "&"), $stream));
                $stream = explode("&type", $stream);
                $stream = $stream[0];
                preg_match("/itag=([0-9]+)/", $stream, $quality);
                $quality = $quality[1];
                if (IT\JuicyCodes::Quality($quality)) {
                    $masked[IT\JuicyCodes::$quality] = array("file" => IT\JuicyCodes::SourceLink($id, $stream, IT\JuicyCodes::$quality), "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                    $links[IT\JuicyCodes::$quality] = array("file" => $stream, "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
                }
            }
            $preview = IT\JuicyCodes::GetImage($id);
//            $preview = IT\Data::Get("url") . "/preview/$slug/";
        } else {
            IT\JuicyCodes::Error($error[1]);
        }
    } else {
        IT\JuicyCodes::Error($get->error);
    }
    return IT\Tools::Object(array("sources" => $masked, "orginal" => $links, "preview" => $preview, "cookies" => $get->cookies));
}
function get_photos($ids)
{
    $url = IT\JuicyCodes::Link($ids, "photo");
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
                $masked[IT\JuicyCodes::$quality] = array("file" => IT\JuicyCodes::SourceLink($id, $link, IT\JuicyCodes::$quality), "label" => IT\JuicyCodes::Quality($quality), "type" => "video/mp4");
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

function html_minification($buffer)
{
    $search = array("/\\>[^\\S ]+/s", "/[^\\S ]+\\</s", "/(\\s)+/s");
    $replace = array(">", "<", "\\1");
    $buffer = preg_replace($search, $replace, $buffer);
    return $buffer;
}