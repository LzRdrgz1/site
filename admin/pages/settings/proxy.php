<?php
if (!defined("JUICYCODES")) {
    exit;
}
use IT\Data;
if ($user->Info("role") != "1") {
    $html->error("Access denied - You are not authorized to access this page!")->Redirect($html->Url(""), true);
}
$html->active("manage_settings")->SetTitle("Proxy Settings");
require_once ADMINPATH . '/header.php';
$html->element("div", array("class" => "panel"), array(
    $html->element("div", array("class" => "panel-heading"), array(
        $html->element("h3", array("class" => "panel-title"), array("PROXY SETTINGS")),
        $html->element("div", array("class" => "right"), array(
            $html->element("button", array("type" => "button", "class" => "btn-toggle-collapse"), array(
                $html->element("i", array("class" => "lnr lnr-chevron-up")),
            )),
        )),
    )),
    $html->element("form", array("method" => "post", "action" => $html->Url("actions")), array(
        $html->input("hidden", "hide", false, "action", "save_settings"),
        $html->element("div", array("class" => "panel-body"), array(
            $html->element("div", array("class" => "row"), array(
                $html->element("div", array("class" => "col-lg-4 col-md-6"), array(
                    $html->element("div", array("class" => "form-group"), array(
                        $html->label("use_proxy_opt", "control-label", "Use Proxy Service"),
                        $html->tip("Whether to use proxy service or not"),
                        $html->element("select", array("class" => "form-control", "id" => "proxy_opt", "name" => "use_proxy"), array(
                            $html->option("Enable", "enable", Data::Get("use_proxy")),
                            $html->option("Disable", "disable", Data::Get("use_proxy")),
                        )),
                    )),
                    $html->element("div", array("class" => "form-group"), array(
                        $html->label("vpn_service_username", "control-label", "VPN Account Username"),
                        $html->tip("Username of your VPN service account"),
                        $html->input("text", "form-control", "vpn_service_username", "vpn_service_username", Data::Get("vpn_service_username")),
                    )),
                    $html->element("div", array("class" => "form-group"), array(
                        $html->label("vpn_service_password", "control-label", "VPN Account Password"),
                        $html->tip("Password of your VPN service account"),
                        $html->input("text", "form-control", "vpn_service_password", "vpn_service_password", Data::Get("vpn_service_password")),
                    )),
                    $html->element("div", array("class" => "form-group"), array(
                        $html->label("vpn_service_port", "control-label", "VPN Service Port"),
                        $html->tip("The port that your VPN service use"),
                        $html->input("text", "form-control", "vpn_service_port", "vpn_service_port", Data::Get("vpn_service_port")),
                    )),
                ))
            )),
        )),
        $html->element("div", array("class" => "panel-footer"), array(
            $html->element("button", array("type" => "submit", "class" => "btn btn-info"), array("SAVE SETTINGS")),
        )),
    )),
), true);
$html->script('
    $("#proxy_opt").bind("change",function(){
        $value = $(this).val();
        if ($value == "enable") {
            $("#vpn_service_username").prop("disabled", false);
            $("#vpn_service_password").prop("disabled", false);
            $("#vpn_service_port").prop("disabled", false);
        } else {
            $("#vpn_service_username").prop("disabled", true);
            $("#vpn_service_password").prop("disabled", true);
            $("#vpn_service_port").prop("disabled", true);
        }
    });
    $("#proxy_opt").trigger("change");
');
require_once ADMINPATH . '/footer.php';
