<?php
/**
 * Plugin Name: Paytm Gateway for Paid Memberships Pro
 * Description: Paytm Gateway for Paid Memberships Pro plugin
 * Version: 1.0.0
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pmpro
 *
 * @package paytm-gateway-pmpro-addon
 */

define( 'PMPRO_PAYTMGATEWAY_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'PMPRO_PAYTMGATEWAY_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

// load payment gateway class.
require_once PMPRO_PAYTMGATEWAY_PATH . '/classes/class-pmprogateway-paytm.php';
