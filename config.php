<?php
/**
 * The "config.php" file is the base configuration for this script
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * PHP Error Reporting
 * * Root Directory Path
 * * Template Directory Path
 */

/** MySQL hostname */
define("DB_HOST", 'localhost');

/** MySQL database username */
define("DB_USER", 'vpn');

/** MySQL database password */
define("DB_PASS", '5K57ykPhTpEEcpSC');

/** MySQL database name */
define("DB_NAME", 'vpn');

/** PHP Error Reporting */
define("DEBUG", false);

/** PHP Display Error */
define("SHOW_DEBUG", false);

/**
 * That's all! Dude, please stop editing !
 */

/** Absolute path of root directory */
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

/** Full Path of template directory */
if (!defined('TEMPLATES')) {
    define('TEMPLATES', dirname(__FILE__) . '/templates/');
}

if (!defined('JUICYCODES')) {
    define("JUICYCODES", true);
}

/** Initialize required components */
require_once ABSPATH . '/framework/init.php';
