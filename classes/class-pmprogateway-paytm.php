<?php
/**
 * Paytm Payment Gateway supporting only QR code method.
 *
 * @package paytm-gateway-pmpro-addon
 */

	//phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	//phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
	//phpcs:disable Squiz.PHP.CommentedOutCode.Found

	// load classes init method.
	add_action( 'init', array( 'PMProGateway_Paytm', 'init' ) );

	/**
	 * PMProGateway_Paytm Class
	 *
	 * Handles paytm integration.
	 */
class PMProGateway_Paytm extends PMProGateway {

	/**
	 * Gateway Object.
	 *
	 * @var PMProGateway
	 */
	public $gateway;

	/**
	 * Set gateway
	 *
	 * @param PMProGateway $gateway Gateway name.
	 * @return PMProGateway
	 */
	public function PMProGateway( $gateway = null ) {
		$this->gateway = $gateway;
		return $this->gateway;
	}

	/**
	 * Run on WP init
	 *
	 * @since 1.8
	 */
	public static function init() {
		// make sure paytm is a gateway option.
		add_filter( 'pmpro_gateways', array( 'PMProGateway_Paytm', 'pmpro_gateways' ) );

		// add fields to payment settings.
		add_filter( 'pmpro_payment_options', array( 'PMProGateway_Paytm', 'pmpro_payment_options' ) );
		add_filter( 'pmpro_payment_option_fields', array( 'PMProGateway_Paytm', 'pmpro_payment_option_fields' ), 10, 2 );

		// add some fields to edit user page (Updates).
		add_action( 'pmpro_after_membership_level_profile_fields', array( 'PMProGateway_Paytm', 'user_profile_fields' ) );
		add_action( 'profile_update', array( 'PMProGateway_Paytm', 'user_profile_fields_save' ) );

		// updates cron.
		add_action( 'pmpro_activation', array( 'PMProGateway_Paytm', 'pmpro_activation' ) );
		add_action( 'pmpro_deactivation', array( 'PMProGateway_Paytm', 'pmpro_deactivation' ) );
		add_action( 'pmpro_cron_paytm_subscription_updates', array( 'PMProGateway_Paytm', 'pmpro_cron_paytm_subscription_updates' ) );

		// code to add at checkout if paytm is the current gateway.
		$gateway = pmpro_getOption( 'gateway' );
		if ( 'paytm' === $gateway ) {
			add_action( 'pmpro_checkout_preheader', array( 'PMProGateway_Paytm', 'pmpro_checkout_preheader' ) );
			add_action( 'pmpro_checkout_before_submit_button', array( 'PMProGateway_Paytm', 'pmpro_checkout_before_submit_button' ) );
			// add_filter( 'pmpro_checkout_order', array( 'PMProGateway_Paytm', 'pmpro_checkout_order' ) );
			add_filter( 'pmpro_include_billing_address_fields', '__return_false' );
			add_filter( 'pmpro_required_billing_fields', array( 'PMProGateway_Paytm', 'pmpro_required_billing_fields' ) );

			/**
			 * Modify submit button.
			 */
			// add_filter( 'pmpro_checkout_default_submit_button', array( 'PMProGateway_Paytm', 'pmpro_checkout_default_submit_button' ) );

			add_filter( 'pmpro_include_cardtype_field', '__return_false' );
			add_filter( 'pmpro_include_payment_information_fields', '__return_false' );
		}
	}

	/**
	 * Add some hidden fields and JavaScript to checkout.
	 */
	public static function pmpro_checkout_before_submit_button() {
		global $pmpro_level;

		if ( empty( $_SESSION['order_id'] ) ) {
			$new_order            = new MemberOrder();
			$order_id             = $new_order->getRandomCode();
			$_SESSION['order_id'] = $order_id;
		} else {
			$order_id = sanitize_text_field( wp_unslash( $_SESSION['order_id'] ) );
		}

		$amount = intval( $pmpro_level->initial_payment );

		$_SESSION['amount'] = $amount;
		?>
		<div style="display: flex;">
		<div id="paytm-qrcode" style="background-color: white; padding: 10px;"></div>
		</div>
		<input type='hidden' name='order_id' id="order-id" value="<?php echo esc_attr( $order_id ); ?>" />
		<input type="hidden" name="amount" id="amount" value="<?php echo esc_attr( $amount ); ?>" />
		<input type="hidden" name="merchant_upi" id="merchant-upi" value="<?php echo esc_attr( pmpro_getOption( 'merchant_upi' ) ); ?>" />
		<?php
	}

	/**
	 * Swap in our submit buttons.
	 *
	 * @param mixed $show Show.
	 *
	 * @since 1.8
	 */
	public static function pmpro_checkout_default_submit_button( $show ) {
		global $gateway, $pmpro_requirebilling;

		// show our submit buttons
		?>
			<span id="pmpro_submit_span" 
			<?php
			if ( 'paytm' !== $gateway ) {
				?>
				style="display: none;"<?php } ?>>
				<input type="hidden" name="submit-checkout" value="1" />
				<input type="submit" id="pmpro_btn-submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-submit-checkout', 'pmpro_btn-submit-checkout' ) ); ?>" value="
																			<?php
																			if ( $pmpro_requirebilling ) {
																				esc_attr__( 'Submit and Check Out', 'paid-memberships-pro' );
																			} else {
																				esc_attr__( 'Submit and Confirm', 'paid-memberships-pro' );}
																			?>
				" />
			</span>
			<?php

			// don't show the default
			return false;
	}

	/**
	 * Remove required billing fields
	 *
	 * @param array $fields Fields.
	 */
	public static function pmpro_required_billing_fields( $fields ) {
		unset( $fields['bfirstname'] );
		unset( $fields['blastname'] );
		unset( $fields['baddress1'] );
		unset( $fields['bcity'] );
		unset( $fields['bzipcode'] );
		unset( $fields['bphone'] );
		unset( $fields['bemail'] );
		unset( $fields['bcountry'] );
		unset( $fields['CardType'] );
		unset( $fields['AccountNumber'] );
		unset( $fields['ExpirationMonth'] );
		unset( $fields['ExpirationYear'] );
		unset( $fields['CVV'] );
		unset( $fields['bstate'] );

		return $fields;
	}

	/**
	 * Code added to checkout preheader.
	 *
	 * @since 2.1
	 */
	public static function pmpro_checkout_preheader() {
		global $gateway, $pmpro_level;

		$default_gateway = pmpro_getOption( 'gateway' );

		// Enqueue any script if required.

		if ( ( 'paytm' === $gateway || 'paytm' === $default_gateway ) && ! pmpro_isLevelFree( $pmpro_level ) ) {
			wp_register_script(
				'pmpro_qrcode_lib',
				PMPRO_PAYTMGATEWAY_URL . '/assets/js/qrcode.min.js',
				array(),
				PMPRO_VERSION,
				true
			);
			wp_register_script(
				'pmpro_paytm_qrcode',
				PMPRO_PAYTMGATEWAY_URL . '/assets/js/pmpro-paytm.js',
				array( 'pmpro_qrcode_lib' ),
				PMPRO_VERSION,
				true
			);
			wp_enqueue_script( 'pmpro_paytm_qrcode' );
		}
	}

	/**
	 * Make sure paytm is in the gateways list
	 *
	 * @param array $gateways Gateways list.
	 *
	 * @since 1.8
	 */
	public static function pmpro_gateways( $gateways ) {
		if ( empty( $gateways['paytm'] ) ) {
			$gateways['paytm'] = __( 'Paytm', 'pmpro' );
		}

		return $gateways;
	}

	/**
	 * Get a list of payment options that the paytm gateway needs/supports.
	 *
	 * @since 1.8
	 */
	public static function getGatewayOptions() {
		$options = array(
			'sslseal',
			'nuclear_HTTPS',
			'gateway_environment',
			'currency',
			'use_ssl',
			'tax_state',
			'tax_rate',
			'accepted_credit_cards',
			'merchant_id',
			'merchant_upi',
		);

		return $options;
	}

	/**
	 * Set payment options for payment settings page.
	 *
	 * @param array $options Array of options.
	 *
	 * @since 1.8
	 */
	public static function pmpro_payment_options( $options ) {
		// get paytm options
		$paytm_options = self::getGatewayOptions();

		// merge with others.
		$options = array_merge( $paytm_options, $options );

		return $options;
	}

	/**
	 * Display fields for paytm options on settings page.
	 * Added Merchant UPI and merchant id.
	 *
	 * @param array  $values Values.
	 * @param string $gateway Gateway name.
	 */
	public static function pmpro_payment_option_fields( $values, $gateway ) {
		?>
		<tr class="pmpro_settings_divider gateway gateway_paytm" 
		<?php
		if ( 'paytm' !== $gateway ) {
			?>
			style="display: none;"<?php } ?>>
			<td colspan="2">
				<hr />
				<h2 class="title"><?php esc_html_e( 'Paytm Settings', 'pmpro' ); ?></h2>
			</td>
		</tr>
		<tr class="gateway gateway_paytm" 
		<?php
		if ( 'paytm' !== $gateway ) {
			?>
			style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="merchant_id"><?php esc_html_e( 'Merchant ID', 'pmpro' ); ?></label>
			</th>
			<td>
				<input type="text" id="merchant_id" name="merchant_id" value="<?php echo esc_attr( $values['merchant_id'] ); ?>" class="regular-text code" />
			</td>
		</tr>
		<tr class="gateway gateway_paytm" 
		<?php
		if ( 'paytm' !== $gateway ) {
			?>
			style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="merchant_upi"><?php esc_html_e( 'Merchant UPI', 'pmpro' ); ?></label>
			</th>
			<td>
				<input type="text" id="merchant_upi" name="merchant_upi" value="<?php echo esc_attr( $values['merchant_upi'] ); ?>" class="regular-text code" />
			</td>
		</tr>
		<?php
	}

	/**
	 * Filtering orders at checkout.
	 *
	 * @param MemberOrder $morder Member Order.
	 *
	 * @since 1.8
	 */
	public static function pmpro_checkout_order( $morder ) {
		return $morder;
	}

	/**
	 * Code to run after checkout
	 *
	 * @param int         $user_id User ID.
	 * @param MemberOrder $morder Member Order.
	 *
	 * @since 1.8
	 */
	public static function pmpro_after_checkout( $user_id, $morder ) {
	}

	/**
	 * Use our own payment fields at checkout. (Remove the name attributes.)
	 *
	 * @param mixed $include Include.
	 *
	 * @since 1.8
	 */
	public static function pmpro_include_payment_information_fields( $include ) {
	}

	/**
	 * Fields shown on edit user page
	 *
	 * @param User $user User.
	 *
	 * @since 1.8
	 */
	public static function user_profile_fields( $user ) {
	}

	/**
	 * Process fields from the edit user page
	 *
	 * @param int $user_id ID of user.
	 *
	 * @since 1.8
	 */
	public static function user_profile_fields_save( $user_id ) {
	}

	/**
	 * Cron activation for subscription updates.
	 *
	 * @since 1.8
	 */
	public static function pmpro_activation() {
		wp_schedule_event( time(), 'daily', 'pmpro_cron_paytm_subscription_updates' );
	}

	/**
	 * Cron deactivation for subscription updates.
	 *
	 * @since 1.8
	 */
	public static function pmpro_deactivation() {
		wp_clear_scheduled_hook( 'pmpro_cron_paytm_subscription_updates' );
	}

	/**
	 * Cron job for subscription updates.
	 *
	 * @since 1.8
	 */
	public static function pmpro_cron_paytm_subscription_updates() {
	}


	/**
	 * Process payment
	 *
	 * @param MemberOrder $order Order object.
	 * @return boolean
	 */
	public function process( &$order ) {

		if ( 0 === intval( $order->InitialPayment ) ) {
			return false;
		}

		$amount          = $order->InitialPayment;
		$order->subtotal = $amount;
		$amount_tax      = 0;
		// No tax calculation for now.
		// $amount_tax = $order->getTaxForPrice($amount);
		$order->total = $amount;

		$order_id = '';
		if ( empty( $_SESSION['order_id'] ) ) {
			$order->error = __( 'Invalid Order ID', 'pmpro' );
			return false;
		}

		if ( empty( $_SESSION['amount'] ) ) {
			$order->error = __( 'Invalid Amount', 'pmpro' );
			return false;
		}

		$merchant_upi = pmpro_getOption( 'merchant_upi' );
		$merchant_id  = pmpro_getOption( 'merchant_id' );

		if ( empty( $merchant_upi ) ) {
			$order->error = __( 'Invalid Merchant UPI', 'pmpro' );
			return false;
		}

		if ( empty( $merchant_id ) ) {
			$order->error = __( 'Invalid Merchant ID', 'pmpro' );
			return false;
		}

		$order_id        = sanitize_text_field( wp_unslash( $_SESSION['order_id'] ) );
		$incoming_amount = floatval( sanitize_text_field( $_SESSION['amount'] ) );

		if ( abs( $amount - $incoming_amount ) >= PHP_FLOAT_EPSILON ) {
			$order->error = __( 'Invalid amount', 'pmpro' );
			return false;
		}

		$json_data = wp_json_encode(
			array(
				'MID'     => $merchant_id,
				'ORDERID' => $order_id,
			)
		);

		$paytm_api_url = 'https://securegw.paytm.in/order/status?JsonData=' . $json_data;

		$response = wp_remote_get( $paytm_api_url );

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $response_body ) && $response_body['STATUS'] ) {
			if ( 'TXN_SUCCESS' === $response_body['STATUS'] ) {
				// payment successful.
				$order->payment_transaction_id = $response_body['TXNID'];
				$order->code                   = $order_id;
				$order->status                 = 'success';
				$order->payment_type           = $response_body['BANKNAME'] . ' ' . $response_body['PAYMENTMODE'];
				$bank_txn_id                   = $response_body['BANKTXNID'];
				$merc_unq_ref                  = $response_body['MERC_UNQ_REF'];
				$order->notes                  = "Bank TXN ID - $bank_txn_id\nMerchant Unique Ref - $merc_unq_ref";
				return true;

			} else {
				$order->error = __( 'Payment pending. Try Again. If payment made, please contact webmaster with the payment proof.', 'pmpro' );
				return false;
			}
		} else {
			$order->error = __( 'Invalid response from gateway', 'pmpro' );
			return false;
		}

		return false;
	}

	/**
	 * Run an authorization at the gateway.
	 * Required if supporting recurring subscriptions
	 * since we'll authorize $1 for subscriptions
	 * with a $0 initial payment.
	 *
	 * @param MemberOrder $order Member order.
	 * @return boolean
	 */
	public function authorize( &$order ) {
		// create a code for the order.
		return false;
	}

	/**
	 * Void a transaction at the gateway.

	 * Required if supporting recurring transactions
	 * as we void the authorization test on subs
	 * with a $0 initial payment and void the initial
	 * payment if subscription setup fails.
	 *
	 * @param MemberOrder $order member order.
	 */
	public function void( &$order ) {
		// need a transaction id.
		if ( empty( $order->payment_transaction_id ) ) {
			return false;
		}

		// code to void an order at the gateway and test results would go here.

		// simulate a successful void.
		$order->payment_transaction_id = 'TEST' . $order->code;
		$order->updateStatus( 'voided' );
		return true;
	}

	/**
	 * Setup a subscription at the gateway.
	 * Required if supporting recurring subscriptions.
	 *
	 * @param MemberOrder $order Member order.
	 * @return boolean
	 */
	public function subscribe( &$order ) {
		// create a code for the order.
		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		// filter order before subscription. use with care.
		$order = apply_filters( 'pmpro_subscribe_order', $order, $this );

		// code to setup a recurring subscription with the gateway and test results would go here.

		// simulate a successful subscription processing.
		$order->status                      = 'success';
		$order->subscription_transaction_id = 'TEST' . $order->code;
		return true;
	}

	/**
	 * Update billing at the gateway.
	 * Required if supporting recurring subscriptions and
	 * processing credit cards on site.
	 *
	 * @param MemberOrder $order Member order.
	 * @return boolean
	 */
	public function update( &$order ) {
		// code to update billing info on a recurring subscription at the gateway and test results would go here.

		// simulate a successful billing update.
		return true;
	}

	/**
	 * Cancel a subscription at the gateway.
	 * Required if supporting recurring subscriptions.
	 *
	 * @param MemberOrder $order Member order.
	 * @return boolean
	 */
	public function cancel( &$order ) {
		// require a subscription id.
		if ( empty( $order->subscription_transaction_id ) ) {
			return false;
		}

		// code to cancel a subscription at the gateway and test results would go here.

		// simulate a successful cancel.
		$order->updateStatus( 'cancelled' );
		return true;
	}

	/**
	 * Get subscription status at the gateway.
	 *
	 * Optional if you have code that needs this or
	 * want to support addons that use this.
	 *
	 * @param MemberOrder $order Member order.
	 * @return mixed
	 */
	public function getSubscriptionStatus( &$order ) {
		// require a subscription id.
		if ( empty( $order->subscription_transaction_id ) ) {
			return false;
		}

		// code to get subscription status at the gateway and test results would go here.

		// this looks different for each gateway, but generally an array of some sort.
		return array();
	}

	/**
	 * Get transaction status at the gateway.
	 * Optional if you have code that needs this or
	 * want to support addons that use this.
	 *
	 * @param MemberOrder $order Member Order.
	 * @return mixed
	 */
	public function getTransactionStatus( &$order ) {
		// code to get transaction status at the gateway and test results would go here.

		// this looks different for each gateway, but generally an array of some sort.
		return array();
	}
}
