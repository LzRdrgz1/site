<?php
if (!defined("JUICYCODES")) {
    exit;
}
$user->CheckLogin($html->url("login"));
$action = $var->post->action ?: $var->get->action;
if ($user->Info("role") != "1") {
    $disabled = array("add_user", "ban_user", "delete_user", "clear_all_cache", "clear_expired_cache", "save_settings");
    if (in_array($action, $disabled)) {
        $html->error("Access denied - You are not authorized to access this page!")->Redirect($html->Url(""), true);
    }
}
if ($var->post->action == "add_link") {
    $error->vaildate("post", array("jc_link" => array("error" => "Please enter a video link")))->vaildate("post", array("jc_type" => array("compare" => "not_in", "string" => array("1", "2", "3"), "error" => "Please select valid 'Generation Type'")), true);
    if ($error->is_empty() && !IT\JuicyCodes::ID($var->post->jc_link)) {
        $error->add("Invalid Video Link");
    }
    if ($error->is_empty() && !empty($var->post->jc_slug)) {
        if (255 < mb_strlen($var->post->jc_slug)) {
            $error->add("Custom Slug can't be more then 255 characters!");
        }
        if (mb_strlen($var->post->jc_slug) < 5) {
            $error->add("Custom Slug can't be less then 5 characters!");
        }
    }
    if ($error->is_empty() && !IT\JuicyCodes::Slug($var->post->jc_slug)) {
        $error->add("Slug Already Exists!");
    }
    if ($error->is_empty()) {
        $id = IT\JuicyCodes::ID($var->post->jc_link);
        $slug = IT\JuicyCodes::Slug($var->post->jc_slug);
        $source = IT\JuicyCodes::Source($var->post->jc_link);
        $data = array("link" => IT\JuicyCodes::Encode($id), "slug" => $slug, "source" => $source, "type" => $var->post->jc_type, "date" => $var->timestamp(), "user" => $user->id);
        if (!empty($var->post->jc_embed)) {
            $data["embed"] = $var->post->jc_embed;
        }
        if (!empty($var->post->jc_title)) {
            $data["title"] = $var->post->jc_title;
        }
        if (!empty($var->post->jc_preview)) {
            $var->setCookie("jc_preview", "url");
            $data["preview"] = IT\JuicyCodes::Encode($var->post->jc_preview);
        }
        $var->setCookie("jc_type", $var->post->jc_type);
        $insert = $db->insert("files", $data);
        if ($insert) {
            $video = $db->id;
            if (!empty(IT\File::Get("jc_preview")->name)) {
                $jc_preview_name = md5("preview_" . $video) . ".jpg";
                $jc_preview_dir = ABSPATH . "assets/previews/" . $jc_preview_name;
                if (IT\File::Upload("jc_preview", $jc_preview_dir, true)) {
                    $var->setCookie("jc_preview", "file");
                    $update["preview"] = IT\JuicyCodes::Encode(IT\Data::Get("url") . "/assets/previews/" . $jc_preview_name);
                }
            }
            foreach (IT\File::Reverse($_FILES["subtitle"]) as $key => $subs) {
                if (!empty($subs["name"]) && !empty($var->post->subtitle_label[(string) $key])) {
                    $label = IT\Tools::Clean($var->post->subtitle_label[(string) $key]);
                    if (IT\File::Upload($subs["tmp_name"], ABSPATH . "assets/subtitle/" . $video . "_" . $label . ".srt", true)) {
                        $sub_data[] = $var->post->subtitle_label[(string) $key];
                    }
                }
            }
            if (count($sub_data) != "0") {
                $update["subtitle"] = IT\JuicyCodes::Encode(implode(",", $sub_data));
            } else {
                $update["subtitle"] = IT\JuicyCodes::Encode("NO");
            }
            if (!empty($update)) {
                $db->update("files", $update, array("id" => $video));
            }
            $var->setSession("links", json_encode(array("slug" => $slug, "type" => $var->post->jc_type)));
            $html->success("Link Successfully Added!")->Redirect($html->Url("add/link"));
        } else {
            $html->error("Uanable To Add Link!")->Redirect($html->Url("add/link"));
        }
    } else {
        $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("add/link"));
    }
} else {
    if ($var->post->action == "add_links") {
        $error->vaildate("post", array("jc_links" => array("error" => "Please enter a video link")))->vaildate("post", array("jc_type" => array("compare" => "not_in", "string" => array("1", "2", "3"), "error" => "Please select valid 'Generation Type'")), true);
        if ($error->is_empty()) {
            $links = preg_replace("~\\R~u", "\n", $_POST["jc_links"]);
            $links = explode("\n", $links);
            $slugs = $data = array();
            foreach ($links as $link) {
                if (IT\JuicyCodes::ID($link)) {
                    $id = IT\JuicyCodes::ID($link);
                    $slug = IT\JuicyCodes::Slug();
                    $source = IT\JuicyCodes::Source($link);
                    if (!empty($id) && !empty($slug) && !empty($source)) {
                        $data[] = array("link" => IT\JuicyCodes::Encode($id), "slug" => $slug, "source" => $source, "subtitle" => IT\JuicyCodes::Encode("NO"), "type" => $var->post->jc_type, "date" => $var->timestamp(), "user" => $user->id);
                        $slugs[] = $slug;
                    }
                }
            }
            if (empty($slugs)) {
                $html->error("Please enter a link!")->Redirect($html->Url("add/links"));
            } else {
                if ($db->insert("files", $data)) {
                    $var->setCookie("jc_type", $var->post->jc_type);
                    $var->setSession("links", json_encode(array("slug" => $slugs, "type" => $var->post->jc_type)));
                    $html->success("Links Successfully Added!")->Redirect($html->Url("add/links"));
                } else {
                    $html->error("Uanable To Add Link!")->Redirect($html->Url("add/links"));
                }
            }
        } else {
            $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("add/links"));
        }
    } else {
        if ($var->post->action == "edit_link") {
            $error->vaildate("post", array("jc_link" => array("error" => "Please enter a video link"), "jc_slug" => array("error" => "Please enter a video slug")))->vaildate("post", array("jc_type" => array("compare" => "not_in", "string" => array("1", "2", "3"), "error" => "Please select valid 'Generation Type'")), true);
            if ($error->is_empty()) {
                $files = $db->select("files", array("subtitle"), array("id" => $var->post->id));
                if ($files->num_rows != "1") {
                    $error->add("Invalid Link Selected!");
                } else {
                    $file = $files->fetch_object();
                }
            }
            if ($error->is_empty() && !IT\JuicyCodes::ID($var->post->jc_link)) {
                $error->add("Invalid Video Link");
            }
            if ($error->is_empty() && !empty($var->post->jc_slug)) {
                if (255 < mb_strlen($var->post->jc_slug)) {
                    $error->add("Custom Slug can't be more then 255 characters!");
                }
                if (mb_strlen($var->post->jc_slug) < 5) {
                    $error->add("Custom Slug can't be less then 5 characters!");
                }
            }
            if ($error->is_empty()) {
                $id = IT\JuicyCodes::ID($var->post->jc_link);
                $slug = $var->post->jc_slug;
                $video = $var->post->id;
                $source = IT\JuicyCodes::Source($var->post->jc_link);
                $data = array("link" => IT\JuicyCodes::Encode($id), "slug" => $slug, "embed" => $var->post->jc_embed, "source" => $source, "type" => $var->post->jc_type, "title" => $var->post->jc_title, "preview" => IT\JuicyCodes::Encode($var->post->jc_preview));
                if (!empty(IT\File::Get("jc_preview")->name)) {
                    $jc_preview_name = md5("preview_" . $video) . ".jpg";
                    $jc_preview_dir = ABSPATH . "assets/previews/" . $jc_preview_name;
                    if (IT\File::Upload("jc_preview", $jc_preview_dir, true)) {
                        $var->setCookie("jc_preview", "file");
                        $data["preview"] = IT\JuicyCodes::Encode(IT\Data::Get("url") . "/assets/previews/" . $jc_preview_name);
                    }
                }
                $subtitle = IT\JuicyCodes::Decode($file->subtitle, "stfu_ovi");
                if (IT\JuicyCodes::isSubtitle($subtitle)) {
                    $subtitles = explode(",", $subtitle);
                    if (!empty($var->post->remove_sub)) {
                        $subs = array_filter(explode(",", $var->post->remove_sub));
                        foreach ($subs as $subtitle) {
                            $subtitle_name = IT\Tools::Clean($subtitle);
                            $subtitle_path = ABSPATH . "assets/subtitle/" . $var->post->id . "_" . $subtitle_name . ".srt";
                            if (file_exists($subtitle_path)) {
                                unlink($subtitle_path);
                            }
                        }
                    } else {
                        $subs = array();
                    }
                    $sub_data = array_diff($subtitles, $subs);
                }
                foreach (IT\File::Reverse($_FILES["subtitle"]) as $key => $subs) {
                    if (!empty($subs["name"]) && !empty($var->post->subtitle_label[(string) $key])) {
                        $label = IT\Tools::Clean($var->post->subtitle_label[(string) $key]);
                        if (IT\File::Upload($subs["tmp_name"], ABSPATH . "assets/subtitle/" . $video . "_" . $label . ".srt", true)) {
                            $sub_data[] = $var->post->subtitle_label[(string) $key];
                        }
                    }
                }
                if (count($sub_data) != "0") {
                    $data["subtitle"] = IT\JuicyCodes::Encode(implode(",", $sub_data));
                } else {
                    $data["subtitle"] = IT\JuicyCodes::Encode("NO");
                }
                $update = $db->update("files", $data, array("id" => $var->post->id));
                if ($update) {
                    $html->success("Link Successfully Updated!")->Redirect($html->Url("manage/links"));
                } else {
                    $html->error("Uanable To Update Link!")->Redirect($html->Url("link/edit/" . $var->post->id));
                }
            } else {
                $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("link/edit/" . $var->post->id));
            }
        } else {
            if ($var->get->action == "delete_link") {
                if (empty($var->get->id)) {
                    $error->add("No Link Selected!");
                }
                if ($error->is_empty()) {
                    $files = $db->select("files", array("link", "type", "source", "subtitle"), array("id" => $var->get->id));
                    if ($files->num_rows != "1") {
                        $error->add("Invalid Link Selected!");
                    } else {
                        $file = $files->fetch_object();
                    }
                }
                if ($error->is_empty()) {
                    $jc_preview_name = md5("preview_" . $var->get->id) . ".jpg";
                    $jc_preview_dir = ABSPATH . "assets/previews/" . $jc_preview_name;
                    if (file_exists($jc_preview_dir)) {
                        unlink($jc_preview_dir);
                    }
                    $link = IT\JuicyCodes::Decode($file->link, "stfu_ovi");
                    $db->delete("cache", array("uid" => IT\Cache::getUID($link, $file->source, "embed_player")));
                    $db->delete("links", array("uid" => IT\Cache::getUID($link, $file->source, "embed_player")));
                    $db->delete("cache", array("uid" => IT\Cache::getUID($link, $file->source, "video_download")));
                    $db->delete("links", array("uid" => IT\Cache::getUID($link, $file->source, "video_download")));
                    $subtitle = IT\JuicyCodes::Decode($file->subtitle, "stfu_ovi");
                    if (IT\JuicyCodes::isSubtitle($subtitle)) {
                        $subtitles = explode(",", $subtitle);
                        foreach ($subtitles as $subtitle) {
                            $subtitle_name = IT\Tools::Clean($subtitle);
                            $subtitle_path = ABSPATH . "assets/subtitle/" . $var->get->id . "_" . $subtitle_name . ".srt";
                            if (file_exists($subtitle_path)) {
                                unlink($subtitle_path);
                            }
                        }
                    }
                    $db->delete("files", array("id" => $var->get->id));
                    if ($db->rows != "1") {
                        $error->add("Link Delete Error!");
                    }
                }
                if ($error->is_empty()) {
                    $html->success("The link has been  deleted!")->Redirect($html->Url("manage/links"));
                } else {
                    $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("manage/links"));
                }
            } else {
                if ($var->get->action == "visit_link") {
                    if (empty($var->get->id)) {
                        $error->add("No Link Selected!");
                    }
                    if ($error->is_empty()) {
                        $files = $db->select("files", array("link", "source"), array("id" => $var->get->id));
                        if ($files->num_rows != "1") {
                            $error->add("Invalid Link Selected!");
                        } else {
                            $file = $files->fetch_object();
                        }
                    }
                    if ($error->is_empty()) {
                        $html->Redirect(IT\JuicyCodes::Link(IT\JuicyCodes::Decode($file->link, "stfu_ovi"), $file->source));
                    } else {
                        $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("manage/links"));
                    }
                } else {
                    if ($var->post->action == "add_user") {
                        $error->vaildate("post", array("jc_name" => array("error" => "Please write user's full name"), "jc_email" => array("error" => "Please write user's email address"), "jc_pass" => array("error" => "Please write user's password"), "jc_role" => array("error" => "Please select user role")))->vaildate("post", array("jc_role" => array("compare" => "not_in", "string" => array("1", "2"), "error" => "Please select valid user role")), true);
                        if ($error->is_empty() && !$user->isEmail($var->post->jc_email)) {
                            $error->add("Invalid email address!");
                        }
                        if ($error->is_empty() && $user->Exists($var->post->jc_email)) {
                            $error->add("Email address already exists!");
                        }
                        if ($error->is_empty()) {
                            $insert = $db->insert("users", array("email" => $var->post->jc_email, "pass" => $user->Password($var->post->jc_pass), "name" => $var->post->jc_name, "role" => $var->post->jc_role, "status" => "1", "date" => $var->timestamp()));
                            if ($insert) {
                                $msg = $html->success("User successfully Added!");
                            } else {
                                $msg = $html->success("Unknown Error!");
                            }
                            $msg->Redirect($html->Url("manage/users"));
                        } else {
                            $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("add/user"));
                        }
                    } else {
                        if ($var->post->action == "edit_user") {
                            if ($user->Info("role") != "1") {
                                if ($var->post->id != $user->info("id")) {
                                    $html->error("Access denied - You are not authorized to access this page!")->Redirect($html->Url(""), true);
                                }
                                $var->post->jc_role = "2";
                            }
                            $error->vaildate("post", array("id" => array("error" => "No User Selected!")), true)->vaildate("post", array("jc_name" => array("error" => "Please write user's full name"), "jc_email" => array("error" => "Please write user's email address"), "jc_role" => array("error" => "Please select user role")))->vaildate("post", array("jc_role" => array("compare" => "not_in", "string" => array("1", "2"), "error" => "Please select valid user role")), true);
                            if ($error->is_empty() && !$user->isEmail($var->post->jc_email)) {
                                $error->add("Invalid email address!");
                            }
                            if ($error->is_empty() && $user->info("email", $var->post->id) != $var->post->jc_email && $user->Exists($var->post->jc_email)) {
                                $error->add("Email address already exists!");
                            }
                            if ($error->is_empty()) {
                                $data = array("email" => $var->post->jc_email, "name" => $var->post->jc_name, "role" => $var->post->jc_role, "status" => "1");
                                if (!empty($var->post->jc_pass)) {
                                    $data["pass"] = $user->Password($var->post->jc_pass);
                                }
                                if ($db->update("users", $data, array("id" => $var->post->id))) {
                                    $msg = $html->success("The user has been updated!");
                                } else {
                                    $msg = $html->success("Unknown Error!");
                                }
                                $msg->Redirect($html->Url("manage/users"));
                            } else {
                                $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("user/edit/" . $var->post->id));
                            }
                        } else {
                            if ($var->get->action == "ban_user") {
                                if (empty($var->get->id)) {
                                    $error->add("No User Selected!");
                                }
                                if ($user->id == $var->get->id) {
                                    $error->add("You can't ban your own account!");
                                }
                                if ($error->is_empty()) {
                                    $update = $db->update("users", array("status" => "2"), array("id" => $var->get->id));
                                    if (!$update) {
                                        $error->add("User Ban Error!");
                                    }
                                }
                                if ($error->is_empty()) {
                                    $html->success("The user has been banned!")->Redirect($html->Url("manage/users"));
                                } else {
                                    $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("manage/users"));
                                }
                            } else {
                                if ($var->get->action == "unban_user") {
                                    if (empty($var->get->id)) {
                                        $error->add("No User Selected!");
                                    }
                                    if ($error->is_empty()) {
                                        $update = $db->update("users", array("status" => "1"), array("id" => $var->get->id));
                                        if (!$update) {
                                            $error->add("User Unban Error!");
                                        }
                                    }
                                    if ($error->is_empty()) {
                                        $html->success("The user has been unbanned!")->Redirect($html->Url("manage/users"));
                                    } else {
                                        $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("manage/users"));
                                    }
                                } else {
                                    if ($var->get->action == "delete_user") {
                                        if (empty($var->get->id)) {
                                            $error->add("No User Selected!");
                                        }
                                        if ($user->id == $var->get->id) {
                                            $error->add("You can't delete your own account!");
                                        }
                                        if ($error->is_empty()) {
                                            $db->delete("users", array("id" => $var->get->id));
                                            if ($db->rows != "1") {
                                                $error->add("User Delete Error!");
                                            }
                                        }
                                        if ($error->is_empty()) {
                                            $html->success("The user has been  deleted!")->Redirect($html->Url("manage/users"));
                                        } else {
                                            $html->error(implode("<br/>", $error->get()))->Redirect($html->Url("manage/users"));
                                        }
                                    } else {
                                        if ($var->get->action == "clear_all_cache") {
                                            $clear = $db->query("DELETE FROM cache");
                                            $html->success("All Cache Successfully Cleared!")->Redirect($html->Url(""));
                                        } else {
                                            if ($var->get->action == "clear_expired_cache") {
                                                $now = $var->time();
                                                $clear = $db->query("DELETE FROM cache WHERE expiry <= '" . $now . "'");
                                                $html->success("Expired Cache Successfully Cleared!")->Redirect($html->Url(""));
                                            } else {
                                                if ($var->get->action == "enable_log") {
                                                    $clear = $db->update("settings", array("value" => "enable"), array("name" => "login_log"));
                                                    $html->success("Admin Login Log Successfully Enabled!")->Redirect($html->Url("log/list"));
                                                } else {
                                                    if ($var->get->action == "disable_log") {
                                                        $clear = $db->update("settings", array("value" => "disable"), array("name" => "login_log"));
                                                        $html->success("Admin Login Log Successfully Disabled!")->Redirect($html->Url("log/list"));
                                                    } else {
                                                        if ($var->get->action == "clear_log") {
                                                            $clear = $db->query("DELETE FROM loginlog");
                                                            $html->success("All Login Log Successfully Cleared!")->Redirect($html->Url("log/list"));
                                                        } else {
                                                            if ($var->post->action == "save_settings") {
                                                                if (empty($var->post->blocked_countries)) {
                                                                    $var->post->blocked_countries = array();
                                                                }
                                                                foreach ($var->post as $key => $value) {
                                                                    if ($key == "url") {
                                                                        $value = rtrim($value, "/");
                                                                    } else {
                                                                        if ($key == "blocked_ips") {
                                                                            $value = $_POST["blocked_ips"];
                                                                        } else {
                                                                            if ($key == "pop_ad_code") {
                                                                                $value = base64_encode($_POST["pop_ad_code"]);
                                                                            } else {
                                                                                if ($key == "allowed_qualities") {
                                                                                    $value = implode(",", $value);
                                                                                } else {
                                                                                    if ($key == "blocked_countries") {
                                                                                        $value = implode(",", $value);
                                                                                    } else {
                                                                                        if ($key == "banner_ad_code") {
                                                                                            $value = base64_encode($_POST["banner_ad_code"]);
                                                                                        } else {
                                                                                            if ($key == "vast_ad_code") {
                                                                                                $value = base64_encode($_POST["vast_ad_code"]);
                                                                                            } else {
                                                                                                if ($key == "vpn_service_username") {
                                                                                                    $value = $_POST["vpn_service_username"];
                                                                                                } else {
                                                                                                    if ($key == "vpn_service_password") {
                                                                                                        $value = $_POST["vpn_service_password"];
                                                                                                    } else {
                                                                                                        if ($key == "vpn_service_port") {
                                                                                                            $value = $_POST["vpn_service_port"];
                                                                                                        } else {
                                                                                                            if ($key == "default_title") {
                                                                                                                if (empty($value)) {
                                                                                                                    $html->error("Website Title Can't be Empty!!")->GoBack(true);
                                                                                                                    exit;
                                                                                                                }
                                                                                                            } else {
                                                                                                                if ($key == "allowed_domains") {
                                                                                                                    $domains = explode(",", $value);
                                                                                                                    foreach ($domains as $domain) {
                                                                                                                        $doms[] = IT\Tools::GetHost($domain) ?: $domain;
                                                                                                                    }
                                                                                                                    $value = implode(",", $doms);
                                                                                                                }
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                    if (!in_array($key, array("__total", "action"))) {
                                                                        $db->update("settings", array("value" => $value), array("name" => $key));
                                                                    }
                                                                }
                                                                if (!empty(IT\File::Get("logo")->name) && IT\File::Upload(IT\File::Get("logo")->tmp_name, ABSPATH . "assets/images/logo.png", true)) {
                                                                    $db->update("settings", array("value" => IT\Data::Get("url") . "/assets/images/logo.png"), array("name" => "logo"));
                                                                }
                                                                if ($db->error) {
                                                                    $html->error("ERROR: " . $db->error)->GoBack();
                                                                } else {
                                                                    $html->success("Settings Successfully Updated!")->GoBack();
                                                                }
                                                            } else {
                                                                require_once ADMINPATH . "/404.php";
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}