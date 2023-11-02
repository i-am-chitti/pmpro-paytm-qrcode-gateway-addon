<?php
/**
 * Plugin Name: Paytm Gateway for Paid Memberships Pro
 * Description: Paytm Gateway for Paid Memberships Pro plugin
 * Version: 1.0.0
 * License:     GPL2
 * Text Domain: pmpro
 *
 * @package paytm-gateway-pmpro
 */

define( 'PMPRO_PAYTMGATEWAY_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'PMPRO_PAYTMGATEWAY_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

// load payment gateway class.
require_once PMPRO_PAYTMGATEWAY_PATH . '/classes/class-pmprogateway-paytm.php';

if ( ! function_exists( 'pmpro_restrict_content' ) ) {
	/**
	 * Restrict Content and redirect.
	 *
	 * @return void
	 */
	function pmpro_restrict_content() {
		$has_access = pmpro_has_membership_access( null, null, false );
		if ( ! $has_access ) {
			wp_safe_redirect( pmpro_url( 'levels' ) );
			exit;
		}
	}
}

add_action( 'template_redirect', 'pmpro_restrict_content', 1 );
