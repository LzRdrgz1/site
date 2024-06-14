<?php
require_once "config.php";

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

$image = file_get_contents("https://drive.google.com/thumbnail?sz=w1280-h720-n&id=" . $link);

header('Content-type: image/jpeg;');
header("Content-Length: " . strlen($image));

echo $image;
exit();