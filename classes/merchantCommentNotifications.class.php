<?php

class GBS_Comment_Notifications extends Group_Buying_Notifications {
	const NOTIFICATION_TYPE = 'merchant_comment_notification';
	const NOTIFICATION_TYPE_REPLY = 'merchant_reply_notification';

	/** @var GBS_Comment_Notifications */
	private static $instance;

	private function add_hooks() {
		// Hook
		add_action( 'comment_post', array( get_class(), 'comment_notification' ), 10, 1 );

		// Register Notifications
		add_filter( 'gb_notification_types', array( get_class(), 'register_notification_type' ), 10, 1 );
		add_filter( 'gb_notification_shortcodes', array( get_class(), 'register_notification_shortcodes' ), 10, 1 );
	}

	public function register_notification_type( $notifications ) {
		$notifications[self::NOTIFICATION_TYPE] = array(
			'name' => self::__( 'Comment Notification to Merchant' ),
			'description' => self::__( "Customize the notification sent to the merchant after a comment is made on their associated items." ),
			'shortcodes' => array( 'date', 'comment', 'comment_author', 'deal_url', 'deal_title' ),
			'default_title' => self::__( 'New Comment at ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'A comment was just made at %s on a product that you manage.', get_bloginfo( 'name' ) ),
			'allow_preference' => FALSE
		);
		$notifications[self::NOTIFICATION_TYPE_REPLY] = array(
			'name' => self::__( 'Comment Reply Notification to User' ),
			'description' => self::__( "Customize the notification sent to the user whom just received a reply from the merchant." ),
			'shortcodes' => array( 'date', 'comment', 'comment_reply', 'comment_author', 'deal_url', 'deal_title' ),
			'default_title' => self::__( 'New Comment Reply at ' . get_bloginfo( 'name' ) ),
			'default_content' => sprintf( 'An important reply was made to your comment at %s.', get_bloginfo( 'name' ) ),
			'allow_preference' => TRUE
		);
		return $notifications;
	}

	public function register_notification_shortcodes( $default_shortcodes ) {
		$default_shortcodes['comment'] = array(
			'description' => self::__( 'Used to display the comment content.' ),
			'callback' => array( get_class(), 'comment_shortcode' )
		);
		$default_shortcodes['comment_reply'] = array(
			'description' => self::__( 'Used to display the content of the replied to comment.' ),
			'callback' => array( get_class(), 'comment_reply_shortcode' )
		);
		$default_shortcodes['comment_author'] = array(
			'description' => self::__( 'Used to display the comment author.' ),
			'callback' => array( get_class(), 'comment_author_shortcode' )
		);
		return $default_shortcodes;
	}

	public static function comment_shortcode( $atts, $content, $code, $data ) {
		$comment_id = $data['comment_id'];
		$comment = get_comment( $comment_id ); 
		$content = $comment->comment_content;
		return $content;
	}

	public static function comment_reply_shortcode( $atts, $content, $code, $data ) {
		$comment_id = $data['comment_replied_to_id'];
		$comment = get_comment( $comment_id ); 
		$content = $comment->comment_content;
		return $content;
	}

	public static function comment_author_shortcode( $atts, $content, $code, $data ) {
		$comment_id = $data['comment_id'];
		$comment = get_comment( $comment_id ); 
		$name = $comment->comment_author;
		return $name;
	}

	function comment_notification( $comment_id ) {
		$comment = get_comment( $comment_id );
		$post_id = $comment->comment_post_ID;

		if ( get_post_type( $post_id ) != Group_Buying_Deal::POST_TYPE )
			return;

		$deal = Group_Buying_Deal::get_instance( $post_id );
		$merchant_id = $deal->get_merchant_id();

		// Don't continue if the deal doesn't have a merchant
		if ( !$merchant_id )
			return;

		if ( $merchant_id ) {
			self::maybe_send_merchant_notfication( $comment, $merchant_id, $deal );
		}

		// If a reply maybe send a notification
		if ( $comment_parent ) {
			self::maybe_send_merchant_notfication( $comment, $merchant_id, $deal );
		}
	}

	public function maybe_send_merchant_notfication( $comment, $merchant_id, Group_Buying_Deal $deal ) {
		$commenter_id = $comment->user_id;
		$merchant = Group_Buying_Merchant::get_instance( $merchant_id );
		$authorized_users = $merchant->get_authorized_users();

		// Don't send a notification to the merchant whom made the comment
		if ( !in_array( $commenter_id, $authorized_users ) ) {

			foreach ( $authorized_users as $user_id ) {
				$recipient = self::get_user_email( $user_id );
				$data = array(
					'user_id' => $user_id,
					'merchant_id' => $merchant_id,
					'comment_id' => $comment->comment_ID,
					'deal' => $deal
				);
				self::send_notification( self::NOTIFICATION_TYPE, $data, $recipient );	
			}
		}
	}

	public function maybe_send_reply_notfication( $comment, $merchant_id, Group_Buying_Deal $deal ) {
		$merchant = Group_Buying_Merchant::get_instance( $merchant_id );
		$authorized_users = $merchant->get_authorized_users();
		$commenter_id = $comment->user_id;

		// Is the comment from the merchant of this deal
		if ( in_array( $commenter_id, $authorized_users ) ) {
			// Get the comment the merchant replied to
			$comment_parent = $comment->comment_parent;
			$parent_comment = get_comment( $comment_parent );
			$parent_user_id = $parent_comment->user_id;
			// Send notification to the replied to user
			$recipient = self::get_user_email( $parent_user_id );
			// Setup data
			$data = array(
				'user_id' => $parent_user_id,
				'merchant_id' => $merchant_id,
				'comment_replied_to_id' => $comment_parent,
				'comment_reply_id' => $comment->comment_ID,
				'deal' => $deal
			);
			self::send_notification( self::NOTIFICATION_TYPE_REPLY, $data, $recipient );
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
	 * @return GBS_Comment_Notifications
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
