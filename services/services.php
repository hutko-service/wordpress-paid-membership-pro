<?php
/*
	Loading a service?
*/
/*
	Note: The applydiscountcode goes through the site_url() instead of admin-ajax to avoid HTTP/HTTPS issues.
*/
function pmpro_wp_ajax_hutko_ins()
{
    require_once(dirname(__FILE__) . "/hutko-ins.php");
    exit;
}

add_action('wp_ajax_nopriv_hutko-ins', 'pmpro_wp_ajax_hutko_ins');
add_action('wp_ajax_hutko-ins', 'pmpro_wp_ajax_hutko_ins');