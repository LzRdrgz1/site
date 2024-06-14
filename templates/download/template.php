<?php
if (!defined("JUICYCODES")) {
    exit;
}
use IT\Data;
use IT\JuicyCodes;

function template_header($preview = false)
{
    $components = array(
	    "html"        => '<link href="https://fonts.googleapis.com/css?family=Muli:900&display=swap" rel="stylesheet">',
		"stylesheets" => array("https://cdnjs.cloudflare.com/ajax/libs/normalize/5.0.0/normalize.min.css", "https://codepen.io/ryne/pen/PoPoqgO.css"),
    );
    return $components;
}

function template_body($source = array(), $subtitle = false, $preview = false, $slug, $dl = false)
{
    global $video;

    $dl_links = array();
    foreach ($source as $i => $link) {
    	
        if ($link->label != "NA") {
            $dl_links[] = '<a class="white" href="' . JuicyCodes::Protect($link->file) . '"><p><span class="bg"></span><span class="base"></span><span class="text">Download ' . $link->label . '</span></p></a>';
        }
    }
    $html = '';
    if (empty($dl_links)) {
        $html .= '<div><a class="white" href="#"><p><span class="bg"></span><span class="base"></span><span class="text">No Download</span></p></a></div>';
    } else {
        $html .= '<div><h1 style="color: #FFF; text-align: center;">' . ($video->title ?: IT\Data::Get("default_title")) . '</h1>' . implode("", $dl_links) . '</div>';
    }
    $html .= '';
    return $html;
}

function template_footer($pop_ad = false, $pop_ad_code = false, $banner_ad = false, $banner_ad_code = false)
{
    $html = null;
    if ($banner_ad) {
        $html .= '
            <div class="banner" id="banner">
                <span class="close" onclick="return JuicyCodes.Close(); ">X</span>
                ' . $banner_ad_code . '
            </div>
        ';
    }
    if ($pop_ad) {
        $html .= $pop_ad_code;
    }
    if (JuicyCodes::GetError()) {
        $html .= '<script>window.alert("' . JuicyCodes::GetError() . '");</script>';
    }
    return $html;
}