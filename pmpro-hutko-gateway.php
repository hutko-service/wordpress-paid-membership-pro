<?php
/*
Plugin Name: PmP Hutko Payment
Plugin URI: https://hutko.org
Description: Hutko Gateway for Paid Memberships Pro
Version: 1.0.0
Domain Path: /languages
Text Domain: pmp-hutko-payment
Requires at least: 2.5
Requires PHP: 5.6
Author: HUTKO - Ukraine Payment Provider
Author URI: https://hutko.org/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


if (!class_exists('PMProGateway')) {
    return; // mb add dismissible error notice
}

define("PMPRO_HUTKO_DIR", dirname(__FILE__));
define("PMPRO_HUTKO_BASE_FILE", __FILE__);
define("PMPRO_HUTKO_VERSION", '1.0.0');

register_activation_hook(__FILE__, 'PMProGateway_hutko::install');
register_uninstall_hook(__FILE__, 'PMProGateway_hutko::uninstall');
add_action('init', array('PMProGateway_hutko', 'init'));

require_once(PMPRO_HUTKO_DIR . "/classes/class.pmprogateway_hutko.php");
require_once(PMPRO_HUTKO_DIR . "/services/services.php");
