<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class WC_Gateway_Mondido_HW extends WC_Gateway_Mondido_Abstract {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id                 = 'mondido_hw';
		$this->has_fields         = TRUE;
		$this->method_title       = __( 'Mondido', 'woocommerce-gateway-mondido' );
		$this->method_description = '';

		$this->icon     = apply_filters( 'woocommerce_mondido_hw_icon', plugins_url( '/assets/images/mondido.png', dirname( __FILE__ ) ) );
		$this->supports = array(
			'products',
			'refunds',
			// @todo Implement WC Subscription support
			//'subscriptions',
			//'subscription_cancellation',
			//'subscription_suspension',
			//'subscription_reactivation',
			//'subscription_amount_changes',
			//'subscription_date_changes',
			//'subscription_payment_method_change',
			//'subscription_payment_method_change_customer',
			//'subscription_payment_method_change_admin',
			//'multiple_subscriptions',
		);

		// URL to view a transaction
		$this->view_transaction_url = 'https://admin.mondido.com/transactions/%s';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define variables
		$this->enabled     = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title       = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Mondido Payments', 'woocommerce-gateway-mondido' );
		$this->description = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->merchant_id = isset( $this->settings['merchant_id'] ) ? $this->settings['merchant_id'] : '';
		$this->secret      = isset( $this->settings['secret'] ) ? $this->settings['secret'] : '';
		$this->password    = isset( $this->settings['password'] ) ? $this->settings['password'] : '';
		$this->testmode    = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'no';
		$this->authorize   = isset( $this->settings['authorize'] ) ? $this->settings['authorize'] : 'no';
		$this->tax_status  = isset( $this->settings['tax_status'] ) ? $this->settings['tax_status'] : 'none';
		$this->tax_class   = isset( $this->settings['tax_class'] ) ? $this->settings['tax_class'] : 'standard';
		//$this->store_cards       = isset( $this->settings['store_cards'] ) ? $this->settings['store_cards'] : 'no';
		$this->logos             = isset( $this->settings['logos'] ) ? $this->settings['logos'] : array();
		$this->order_button_text = isset( $this->settings['order_button_text'] ) ? $this->settings['order_button_text'] : __( 'Pay with Mondido', 'woocommerce-gateway-mondido' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array(
			$this,
			'notification_callback'
		) );

		// Receipt hook
		add_action( 'woocommerce_receipt_' . $this->id, array(
			$this,
			'receipt_page'
		) );

		// Payment confirmation
		add_action( 'the_post', array( &$this, 'payment_confirm' ) );

		// Add form hash
		add_filter( 'woocommerce_mondido_form_fields', array(
			$this,
			'add_form_hash_value'
		), 10, 3 );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'           => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-mondido' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Mondido Payment Module', 'woocommerce-gateway-mondido' ),
				'default' => 'no'
			),
			'title'             => array(
				'title'       => __( 'Title', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-mondido' ),
				'default'     => __( 'Mondido Payments', 'woocommerce-gateway-mondido' )
			),
			'description'       => array(
				'title'       => __( 'Description', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-mondido' ),
				'default'     => '',
			),
			'merchant_id'       => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'Merchant ID for Mondido', 'woocommerce-gateway-mondido' ),
				'default'     => ''
			),
			'secret'            => array(
				'title'       => __( 'Secret', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'Given secret code from Mondido', 'woocommerce-gateway-mondido' ),
				'default'     => ''
			),
			'password'          => array(
				'title'       => __( 'API Password', 'woocommerce-gateway-mondido' ),
				'type'        => 'text',
				'description' => __( 'API Password from Mondido', 'woocommerce-gateway-mondido' ) . ' (<a href="https://admin.mondido.com/settings">https://admin.mondido.com/settings</a>)',
				'default'     => ''
			),
			'testmode'          => array(
				'title'   => __( 'Test Mode', 'woocommerce-gateway-mondido' ),
				'type'    => 'checkbox',
				'label'   => __( 'Set in testmode', 'woocommerce-gateway-mondido' ),
				'default' => 'no'
			),
			'authorize'         => array(
				'title'   => __( 'Authorize', 'woocommerce-gateway-mondido' ),
				'type'    => 'checkbox',
				'label'   => __( 'Reserve money, do not auto-capture', 'woocommerce-gateway-mondido' ),
				'default' => 'no'
			),
			'tax_status'        => array(
				'title'       => __( 'Tax status for payment fees', 'woocommerce-gateway-mondido' ),
				'type'        => 'select',
				'options'     => array(
					'none'    => __( 'None', 'woocommerce-gateway-mondido' ),
					'taxable' => __( 'Taxable', 'woocommerce-gateway-mondido' )
				),
				'description' => __( 'If any payment fee should be taxable', 'woocommerce-gateway-mondido' ),
				'default'     => 'none'
			),
			'tax_class'         => array(
				'title'       => __( 'Tax class for payment fees', 'woocommerce-gateway-mondido' ),
				'type'        => 'select',
				'options'     => self::getTaxClasses(),
				'description' => __( 'If you have a fee for invoice payments, what tax class should be applied to that fee', 'woocommerce-gateway-mondido' ),
				'default'     => 'standard'
			),
			//'store_cards'       => array(
			//	'title'       => __( 'Allow Stored Cards', 'woocommerce-gateway-mondido' ),
			//	'label'       => __( 'Allow logged in customers to save credit card profiles to use for future purchases', 'woocommerce-gateway-mondido' ),
			//	'type'        => 'checkbox',
			//	'description' => '',
			//	'default'     => 'no',
			//),
			'logos'             => array(
				'title'          => __( 'Logos', 'woocommerce-gateway-mondido' ),
				'description'    => __( 'Logos on checkout', 'woocommerce-gateway-mondido' ),
				'type'           => 'multiselect',
				'options'        => array(
					'visa'       => __( 'Visa', 'woocommerce-gateway-mondido' ),
					'mastercard' => __( 'MasterCard', 'woocommerce-gateway-mondido' ),
					'amex'       => __( 'American Express', 'woocommerce-gateway-mondido' ),
					'diners'     => __( 'Diners Club', 'woocommerce-gateway-mondido' ),
					'bank'       => __( 'Direktbank', 'woocommerce-gateway-mondido' ),
					'invoice'    => __( 'Invoice/PartPayment', 'woocommerce-gateway-mondido' ),
					'paypal'     => __( 'PayPal', 'woocommerce-gateway-mondido' ),
					'mp'         => __( 'MasterPass', 'woocommerce-gateway-mondido' ),
					'swish'      => __( 'Swish', 'woocommerce-gateway-mondido' ),
				),
				'select_buttons' => TRUE,
			),
			'order_button_text' => array(
				'title'   => __( 'Text for "Place Order" button', 'woocommerce-gateway-mondido' ),
				'type'    => 'text',
				'default' => __( 'Pay with Mondido', 'woocommerce-gateway-mondido' ),
			),
		);
	}

	/**
	 * Output the gateway settings screen
	 * @return void
	 */
	public function admin_options() {
		wc_get_template(
			'admin/admin-options.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * If There are no payment fields show the description if set.
	 * @return void
	 */
	public function payment_fields() {
		// @todo Store Cards

		wc_get_template(
			'checkout/payment-fields.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( TRUE )
		);
	}

	/**
	 * Validate Frontend Fields
	 * @return bool|void
	 */
	public function validate_fields() {
		//
	}

	/**
	 * Receipt Page
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );

		// Prepare Order Items
		$items = array();

		// Add Products
		foreach ( $order->get_items() as $order_item ) {
			$product_id = $this->is_wc3() ? $order_item->get_product_id() : $order_item['product_id'];
			$product      = wc_get_product( $product_id );
			$sku          = $product->get_sku();
			$price        = $order->get_line_subtotal( $order_item, FALSE, FALSE );
			$priceWithTax = $order->get_line_subtotal( $order_item, TRUE, FALSE );
			$tax          = $priceWithTax - $price;
			$taxPercent   = ( $tax > 0 ) ? round( 100 / ( $price / $tax ) ) : 0;

			$items[] = array(
				'artno'       => empty( $sku ) ? 'product_id' . $product->get_id() : $sku,
				'description' => $this->is_wc3() ? $order_item->get_name() : $order_item['name'],
				'amount'      => number_format( $priceWithTax, 2, '.', '' ),
				'qty'         => $this->is_wc3() ? $order_item->get_quantity() : $order_item['qty'],
				'vat'         => number_format( $taxPercent, 2, '.', '' ),
				'discount'    => 0
			);
		}

		// Add Shipping
		if ( (float) $order->get_shipping_total() > 0 ) {
			$taxPercent = ( $order->get_shipping_tax() > 0 ) ? round( 100 / ( $order->get_shipping_total() / $order->get_shipping_tax() ) ) : 0;

			$items[] = array(
				'artno'       => 'shipping',
				'description' => $order->get_shipping_method(),
				'amount'      => number_format( $order->get_shipping_total() + $order->get_shipping_tax(), 2, '.', '' ),
				'qty'         => 1,
				'vat'         => number_format( $taxPercent, 2, '.', '' ),
				'discount'    => 0
			);
		}

		// Add Discount
		if ( $order->get_total_discount( FALSE ) > 0 ) {
			$items[] = array(
				'artno'       => 'discount',
				'description' => __( 'Discount', 'woocommerce-gateway-mondido' ),
				'amount'      => number_format( - 1 * $order->get_total_discount( FALSE ), 2, '.', '' ),
				'qty'         => 1,
				'vat'         => 0,
				'discount'    => 0
			);
		}

		// Add Fees
		foreach ( $order->get_fees() as $fee ) {
			if ($this->is_wc3()) {
				/** @var WC_Order_Item_Fee $fee */
				$fee_name = $fee->get_name();
				$fee_total = $fee->get_total();
				$fee_tax = $fee->get_total_tax();
			} else {
				$fee_name = $fee['name'];
				$fee_total = $fee['line_total'];
				$fee_tax = $fee['line_tax'];
			}

			$taxPercent = ( $fee_tax > 0 ) ? round( 100 / ( $fee_total / $fee_tax ) ) : 0;

			$items[] = array(
				'artno'       => 'fee',
				'description' => $fee_name,
				'amount'      => number_format( $fee['line_total'] + $fee_tax, 2, '.', '' ),
				'qty'         => 1,
				'vat'         => number_format( $taxPercent, 2, '.', '' ),
				'discount'    => 0
			);
		}

		// Prepare Metadata
		$metadata = array(
			'products'  => $order->get_items(),
			'customer'  => array(
				'user_id'   => $order->get_user_id(),
				'firstname' => $order->get_billing_first_name(),
				'lastname'  => $order->get_billing_last_name(),
				'address1'  => $order->get_billing_address_1(),
				'address2'  => $order->get_billing_address_2(),
				'postcode'  => $order->get_billing_postcode(),
				'phone'     => $order->get_billing_phone(),
				'city'      => $order->get_billing_city(),
				'country'   => $order->get_billing_country(),
				'state'     => $order->get_billing_state(),
				'email'     => $order->get_billing_email()
			),
			'analytics' => array(),
			'platform'  => array(
				'type'             => 'wocoomerce',
				'version'          => WC()->version,
				'language_version' => phpversion(),
				'plugin_version'   => $this->getPluginVersion()
			)
		);

		// Prepare Analytics
		if ( isset( $_COOKIE['m_ref_str'] ) ) {
			$metadata['analytics']['referrer'] = $_COOKIE['m_ref_str'];
		}
		if ( isset( $_COOKIE['m_ad_code'] ) ) {
			$metadata['analytics']['google']            = array();
			$metadata['analytics']['google']['ad_code'] = $_COOKIE['m_ad_code'];
		}

		// Prepare WebHook
		$webhook = array(
			'url'         => WC()->api_request_url( __CLASS__ ),
			'trigger'     => 'payment',
			'http_method' => 'post',
			'data_format' => 'json',
			'type'        => 'CustomHttp'
		);

		// Prepare fields
		$fields = array(
			'amount'       => number_format( $order->get_total(), 2, '.', '' ),
			'vat_amount'   => number_format( $order->get_total_tax(), 2, '.', '' ),
			'merchant_id'  => $this->merchant_id,
			'currency'     => $order->get_currency(),
			'customer_ref' => $order->get_user_id() != '0' ? $order->get_user_id() : '',
			'payment_ref'  => $order->get_id(),
			'success_url'  => $this->get_return_url( $order ),
			'error_url'    => $order->get_cancel_order_url(),
			'metadata'     => $metadata,
			'test'         => $this->testmode === 'yes' ? 'true' : 'false',
			'authorize'    => $this->authorize === 'yes' ? 'true' : '',
			'items'        => $items,
			'webhook'      => $webhook
		);

		wc_get_template(
			'checkout/mondido-form.php',
			array(
				'fields'  => apply_filters( 'woocommerce_mondido_form_fields', $fields, $order, $this ),
				'order'   => $order,
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		//
	}

	/**
	 * Payment confirm action
	 * @return void
	 */
	public function payment_confirm() {
		// Check Transaction ID is exists
		if ( empty( $_GET['transaction_id'] ) ) {
			return;
		}

		if ( empty( $_GET['key'] ) ) {
			return;
		}

		// Validate Payment Method
		$order_id = wc_get_order_id_by_order_key( $_GET['key'] );
		if ( ! $order_id ) {
			return;
		}

		/** @var WC_Order $order */
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_payment_method() !== $this->id ) {
			return;
		}

		// Check is Transaction already processed
		$id = get_post_meta( $order->get_id(), '_transaction_id', TRUE );
		if ( ! empty( $id ) ) {
			return;
		}

		$transaction_id = wc_clean( $_GET['transaction_id'] );
		$payment_ref    = wc_clean( $_GET['payment_ref'] );
		$status         = wc_clean( $_GET['status'] );

		// Verify Payment Reference
		if ( $payment_ref !== $order_id ) {
			wc_add_notice( __( 'Payment Reference verification failed', 'woocommerce-gateway-mondido' ), 'error' );

			return;
		}

		// Lookup transaction
		$transaction_data = $this->lookupTransaction( $transaction_id );
		if ( ! $transaction_data ) {
			wc_add_notice( __( 'Failed to verify transaction', 'woocommerce-gateway-mondido' ), 'error' );

			return;
		}

		// Verify hash
		$hash = md5( sprintf( '%s%s%s%s%s%s%s',
			$this->merchant_id,
			$payment_ref,
			$order->get_user_id() != '0' ? $order->get_user_id() : '',
			number_format( $transaction_data['amount'], 2, '.', '' ), // instead $order->get_total()
			strtolower( $order->get_currency() ),
			$status,
			$this->secret
		) );
		if ( $hash !== wc_clean( $_GET['hash'] ) ) {
			wc_add_notice( __( 'Hash verification failed', 'woocommerce-gateway-mondido' ), 'error' );

			return;
		}

		// Save Transaction
		update_post_meta( $order->get_id(), '_transaction_id', $transaction_id );
		update_post_meta( $order->get_id(), '_mondido_transaction_status', $status );
		update_post_meta( $order->get_id(), '_mondido_transaction_data', $transaction_data );

		switch ( $status ) {
			case 'pending':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->update_status( 'on-hold', sprintf( __( 'Payment pending. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				WC()->cart->empty_cart();
				break;
			case 'approved':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->add_order_note( sprintf( __( 'Payment completed. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				$order->payment_complete( $transaction_id );
				WC()->cart->empty_cart();
				break;
			case 'authorized':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->update_status( 'on-hold', sprintf( __( 'Payment authorized. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				WC()->cart->empty_cart();
				break;
			case 'declined':
				$order->update_status( 'failed', __( 'Payment declined.', 'woocommerce-gateway-mondido' ) );
				break;
			case 'failed':
				$order->update_status( 'failed', __( 'Payment failed.', 'woocommerce-gateway-mondido' ) );
				break;
		}

		// Save invoice address
		if ( $transaction_data['transaction_type'] === 'invoice' ) {
			$details = $transaction_data['payment_details'];
			$address = array(
				'first_name' => $details['first_name'],
				'last_name'  => $details['last_name'],
				'company'    => '',
				'email'      => $details['email'],
				'phone'      => $details['phone'],
				'address_1'  => $details['address_1'],
				'address_2'  => $details['address_2'],
				'city'       => $details['city'],
				'state'      => '',
				'postcode'   => $details['zip'],
				'country'    => $details['country_code']
			);
			update_post_meta( $order->get_id(), '_mondido_invoice_address', $address );

			// Format address
			$formatted = '';
			$fields    = WC()->countries->get_default_address_fields();
			foreach ( $address as $key => $value ) {
				if ( ! isset( $fields[ $key ] ) || empty( $value ) ) {
					continue;
				}
				$formatted .= $fields[ $key ]['label'] . ': ' . $value . "\n";
			}

			$order->add_order_note( sprintf( __( 'Invoice Address: %s', 'woocommerce-gateway-mondido' ), "\n" . $formatted ) );
		}
	}

	/**
	 * Notification Callback
	 * ?wc-api=WC_Gateway_Mondido_HW
	 */
	public function notification_callback() {
		@ob_clean();

		$logger   = new WC_Logger();
		$raw_body = file_get_contents( 'php://input' );
		$data     = @json_decode( $raw_body, TRUE );
		if ( ! $data ) {
			header( sprintf( '%s %s %s', 'HTTP/1.1', '400', 'FAILURE' ), TRUE, '400' );
			$logger->add( $this->id, 'Invalid data' );
			exit( 'Invalid data' );
		}

		if ( empty( $data['id'] ) ) {
			header( sprintf( '%s %s %s', 'HTTP/1.1', '400', 'FAILURE' ), TRUE, '400' );
			$logger->add( $this->id, 'Invalid transaction ID' );
			exit( 'Invalid transaction ID' );
		}

		// Lookup transaction
		$transaction_data = $this->lookupTransaction( $data['id'] );
		if ( ! $transaction_data ) {
			header( sprintf( '%s %s %s', 'HTTP/1.1', '400', 'FAILURE' ), TRUE, '400' );
			$logger->add( $this->id, 'Failed to verify transaction' );
			exit( 'Failed to verify transaction' );
		}

		// Check is Recurring Transaction
		if ( $transaction_data['transaction_type'] == 'recurring' ) {
			// Please note:
			// Configure permanent webhook http://yourshop.local/?wc-api=WC_Gateway_Mondido_HW

			// Create Order
			$order = wc_create_order( array(
				'status'        => $transaction_data['status'] === 'approved' ? 'completed' : 'failed',
				'customer_id'   => isset( $transaction_data['metadata']['customer']['user_id'] ) ? $transaction_data['metadata']['customer']['user_id'] : '',
				'customer_note' => '',
				'total'         => $transaction_data['amount'],
				'created_via'   => 'mondido',
			) );
			add_post_meta( $order->get_id(), '_payment_method', $this->id );
			update_post_meta( $order->get_id(), '_transaction_id', $transaction_data['id'] );
			update_post_meta( $order->get_id(), '_mondido_transaction_status', $transaction_data['status'] );
			update_post_meta( $order->get_id(), '_mondido_transaction_data', $transaction_data );
			update_post_meta( $order->get_id(), '_mondido_subscription_id', $transaction_data['subscription']['id'] );

			// Add address
			$order->set_address( $transaction_data['metadata']['customer'], 'billing' );
			$order->set_address( $transaction_data['metadata']['customer'], 'shipping' );

			// Add note
			$order->add_order_note( sprintf( __( 'Created recurring order by WebHook. Transaction Id %s', 'woocommerce-gateway-mondido' ), $transaction_data['id'] ) );

			// Add Recurring product as Payment Fee
			$fee            = new stdClass();
			$fee->name      = sprintf( __( 'Subscription #%s ', 'woocommerce-gateway-mondido' ), $transaction_data['subscription']['id'] );
			$fee->amount    = $transaction_data['amount'];
			$fee->taxable   = FALSE;
			$fee->tax_class = '';
			$fee->tax       = 0;
			$fee->tax_data  = array();
			$this->add_order_fee($fee, $order);

			// Calculate totals
			$order->calculate_totals();

			// Force to set total
			$order->set_total( $transaction_data['amount'] );

			// Set Transaction ID
			if ( $transaction_data['status'] === 'approved' ) {
				$order->payment_complete( $transaction_data['id'] );
			}

			header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), TRUE, '200' );
			$logger->add( $this->id, "Recurring Order was placed by WebHook. Order ID: {$order->get_id()}. Transaction Id: {$transaction_data['id']}" );
			exit( "Recurring Order was placed by WebHook. Order ID: {$order->get_id()}. Transaction Id: {$transaction_data['id']}" );
		}

		$order = wc_get_order( $transaction_data['payment_ref'] );
		if ( ! $order ) {
			header( sprintf( '%s %s %s', 'HTTP/1.1', '400', 'FAILURE' ), TRUE, '400' );
			$logger->add( $this->id, "Failed to find order {$transaction_data['payment_ref']}" );
			exit( "Failed to find order {$transaction_data['payment_ref']}" );
		}

		$transaction_id = $data['id'];
		$payment_ref    = $data['payment_ref'];
		$status         = $data['status'];

		// Verify hash
		$hash = md5( sprintf( '%s%s%s%s%s%s%s',
			$this->merchant_id,
			$payment_ref,
			$order->get_user_id() != '0' ? $order->get_user_id() : '',
			number_format( $transaction_data['amount'], 2, '.', '' ), // instead $order->get_total()
			strtolower( $order->get_currency() ),
			$status,
			$this->secret
		) );
		if ( $hash !== wc_clean( $data['response_hash'] ) ) {
			header( sprintf( '%s %s %s', 'HTTP/1.1', '400', 'FAILURE' ), TRUE, '400' );
			$logger->add( $this->id, 'Hash verification failed' );
			exit( 'Hash verification failed' );
		}

		// Wait for order confirmation by customer
		set_time_limit( 0 );
		$times = 0;

		// Lookup state
		$state = get_post_meta( $order->get_id(), '_mondido_transaction_status', TRUE );
		while ( empty( $state ) ) {
			$times ++;
			if ( $times > 6 ) {
				break;
			}
			sleep( 10 );

			// Lookup state
			$state = get_post_meta( $order->get_id(), '_mondido_transaction_status', TRUE );
		}

		// Check is Order was confirmed
		if ( ! empty( $state ) ) {
			header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), TRUE, '200' );
			$logger->add( $this->id, "Order {$order->get_id()} already confirmed. Transaction ID: {$transaction_id}" );
			exit( "Order {$order->get_id()} already confirmed. Transaction ID: {$transaction_id}" );
		}

		// Confirm order
		// Save Transaction
		update_post_meta( $order->get_id(), '_transaction_id', $transaction_id );
		update_post_meta( $order->get_id(), '_mondido_transaction_status', $status );
		update_post_meta( $order->get_id(), '_mondido_transaction_data', $transaction_data );

		switch ( $status ) {
			case 'pending':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->update_status( 'on-hold', sprintf( __( 'Payment pending. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				WC()->cart->empty_cart();
				break;
			case 'approved':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->add_order_note( sprintf( __( 'Payment completed. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				$order->payment_complete( $transaction_id );
				WC()->cart->empty_cart();
				break;
			case 'authorized':
				$this->updateOrderWithIncomingProducts( $order, $transaction_data );
				$order->update_status( 'on-hold', sprintf( __( 'Payment authorized. Transaction Id: %s', 'woocommerce-gateway-mondido' ), $transaction_id ) );
				WC()->cart->empty_cart();
				break;
			case 'declined':
				$order->update_status( 'failed', __( 'Payment declined.', 'woocommerce-gateway-mondido' ) );
				break;
			case 'failed':
				$order->update_status( 'failed', __( 'Payment failed.', 'woocommerce-gateway-mondido' ) );
				break;
		}

		// Save invoice address
		if ( $transaction_data['transaction_type'] === 'invoice' ) {
			$details = $transaction_data['payment_details'];
			$address = array(
				'first_name' => $details['first_name'],
				'last_name'  => $details['last_name'],
				'company'    => '',
				'email'      => $details['email'],
				'phone'      => $details['phone'],
				'address_1'  => $details['address_1'],
				'address_2'  => $details['address_2'],
				'city'       => $details['city'],
				'state'      => '',
				'postcode'   => $details['zip'],
				'country'    => $details['country_code']
			);
			update_post_meta( $order->get_id(), '_mondido_invoice_address', $address );

			// Format address
			$formatted = '';
			$fields    = WC()->countries->get_default_address_fields();
			foreach ( $address as $key => $value ) {
				if ( ! isset( $fields[ $key ] ) || empty( $value ) ) {
					continue;
				}
				$formatted .= $fields[ $key ]['label'] . ': ' . $value . "\n";
			}

			$order->add_order_note( sprintf( __( 'Invoice Address: %s', 'woocommerce-gateway-mondido' ), "\n" . $formatted ) );
		}

		header( sprintf( '%s %s %s', 'HTTP/1.1', '200', 'OK' ), TRUE, '200' );
		$logger->add( $this->id, "Order was placed by WebHook. Order ID: {$order->get_id()}. Transaction status: {$status}" );
		exit( "Order was placed by WebHook. Order ID: {$order->get_id()}. Transaction status: {$status}" );
	}

	/**
	 * Add hash
	 *
	 * @param array              $fields
	 * @param WC_Order           $order
	 * @param WC_Payment_Gateway $gateway
	 *
	 * @return array
	 */
	public function add_form_hash_value( $fields, $order, $gateway ) {
		// Make hash
		$fields['hash'] = md5( sprintf(
			'%s%s%s%s%s%s%s',
			$fields['merchant_id'],
			$fields['payment_ref'],
			$fields['customer_ref'],
			$fields['amount'],
			strtolower( $fields['currency'] ),
			$gateway->testmode === 'yes' ? 'test' : '',
			$gateway->secret
		) );

		return $fields;
	}
}