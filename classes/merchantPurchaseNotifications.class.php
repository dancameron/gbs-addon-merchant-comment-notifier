<?php

class GBS_Purchase_Notification extends Group_Buying_Notifications {

	const NOTIFICATION_TYPE = 'merchant_purchase_notification';
	const NOTIFICATION_SENT_META_KEY = 'gb_purchase_notification_sent';
	
	/** @var GBS_Purchase_Notification */
	private static $instance;

	private function add_hooks() {
		
		add_action( 'purchase_completed', array( get_class(), 'purchase_notification' ), 10, 1 );

		// Register Notifications
		add_filter( 'gb_notification_types', array( get_class(), 'register_notification_type' ), 10, 1 );
		add_filter( 'gb_notification_shortcodes', array( get_class(), 'register_notification_shortcodes' ), 10, 1 );
	}

	public function register_notification_type( $notifications ) {
		$notifications[self::NOTIFICATION_TYPE] = array(
			'name' => self::__( 'Purchase Notification to Merchant' ),
			'description' => self::__( "Customize the notification sent to the merchant after a purchase." ),
			'shortcodes' => array( 'date', 'name', 'username', 'purchase_details_for_merchant', 'transid', 'site_title', 'site_url', 'billing_address', 'shipping_address', 'purchaser_email' ),
			'default_title' => self::__( 'New Purchase at ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'A purchase was just made at %s with a product that you manage.', get_bloginfo( 'name' ) ),
			'allow_preference' => FALSE
		);
		return $notifications;
	}

	public function register_notification_shortcodes( $default_shortcodes ) {
		$default_shortcodes['purchase_details_for_merchant'] = array(
			'description' => self::__( 'Used to display the purchase information that relates to the merchant.' ),
			'callback' => array( get_class(), 'purchase_details_for_merchant_shortcode' )
		);
		$default_shortcodes['purchaser_email'] = array(
			'description' => self::__( 'Used to display the purchasers email. The email should not be provided to merchants without the customer explicitly allowing it, or if you have it in your TOS of the site.' ),
			'callback' => array( get_class(), 'purchaser_email_shortcode' )
		);
		return $default_shortcodes;
	}

	public static function purchase_details_for_merchant_shortcode( $atts, $content, $code, $data ) {
		$output = '';
		foreach ( $data['products'] as $product ) {
			$deal_id = (int) $product['deal_id'];
			$deal = Group_Buying_Deal::get_instance( $deal_id );
			$title = $deal->get_title( $product['data'] );
			$url = get_permalink( $deal_id );

			$output .= self::__( 'Deal: ' ) . ": $title\n";
			$output .= self::__( 'URL: ' ) . ": $url\n\n";
		}
		return apply_filters( 'gb_shortcode_merchant_purchase_details', $output, $purchase, $products, $atts, $content, $code, $data );
	}

	public function purchaser_email_shortcode( $atts, $content, $code, $data ) {
		$purchase = $data['purchase'];
		$user_id = $purchase->get_user();
		if ( $user_id == -1 ) { // purchase will be set to -1 if it's a gift.
			$user_id = $purchase->get_original_user();
		}
		return self::get_user_email( $user_id );
	}

	function purchase_notification( $purchase ) {
		$products = $purchase->get_products();
		$merchant_ids = array();
		$purchased_items_by_merchant = array();
		foreach ( $products as $product ) {
			$item = Group_Buying_Deal::get_instance( $product['deal_id'] );
			$merchant_id = $item->get_merchant_id();
			if ( $merchant_id ) {
				$merchant_ids[] = $merchant_id;
				$purchased_items_by_merchant[$merchant_id][] = $product;
			}
		}
		foreach ( $merchant_ids as $merchant_id ) {
			$merchant = Group_Buying_Merchant::get_instance( $merchant_id );
			$authorized_users = $merchant->get_authorized_users();

			foreach ( $authorized_users as $user_id ) {
				$user = get_userdata( $user_id );
				$recipient = self::get_user_email( $user );
				$data = array(
					'user_id' => $user_id,
					'merchant_id' => $merchant_id,
					'purchase' => $purchase,
					'products' => $purchased_items_by_merchant[$merchant_id]
				);
				self::send_notification( self::NOTIFICATION_TYPE, $data, $recipient );	
			}
		}
	}

	/********** Singleton *************/

	/**
	 * Create the instance of the class
	 *
	 * @static
	 * @return void
	 */
	public static function init() {
		self::$instance = self::get_instance();
	}

	/**
	 * Get (and instantiate, if necessary) the instance of the class
	 * @static
	 * @return GBS_Purchase_Notification
	 */
	public static function get_instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	final public function __clone() {
		trigger_error( "Singleton. No cloning allowed!", E_USER_ERROR );
	}

	final public function __wakeup() {
		trigger_error( "Singleton. No serialization allowed!", E_USER_ERROR );
	}

	protected function __construct() {
		$this->add_hooks();
	}
}
