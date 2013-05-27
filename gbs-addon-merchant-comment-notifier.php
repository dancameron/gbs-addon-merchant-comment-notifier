<?php
/*
Plugin Name: Group Buying Addon - Merchant Notifier
Version: 0.1
Description: Notify the merchant about new comments, send notifications to the commenter the merchant replies to and send a purchase notification.
Plugin URI: http://groupbuyingsite.com/marketplace/
Author: Sprout Venture
Author URI: http://sproutventure.com/
Plugin Author: Dan Cameron
Plugin Author URI: http://sproutventure.com/
Contributors: Dan Cameron
Text Domain: group-buying
*/


/**
 * Load all the plugin files and initialize appropriately
 *
 * @return void
 */
if ( !function_exists( 'gbs_merchant_comment_notifications' ) ) { // play nice
	function gbs_merchant_comment_notifications( $addons ) {
		$addons['merchant_comment_notifications'] = array(
			'label' => __( 'Comment Notifications' ),
			'description' => __( 'Notify the merchant about new comments and send notifications to the commenter the merchant replies to.' ),
			'files' => array(
				dirname( __FILE__ ).'/classes/merchantCommentNotifications.class.php'
			),
			'callbacks' => array(
				array( 'GBS_Comment_Notifications', 'init' )
			),
		);
		return $addons;
	}

	add_filter( 'gb_addons', 'gbs_merchant_comment_notifications', 10, 1 );
}

if ( !function_exists( 'gbs_merchant_purchase_notifications' ) ) { // play nice
	function gbs_merchant_purchase_notifications( $addons ) {
		$addons['merchant_purchase_notifications'] = array(
			'label' => __( 'Purchase Notification to Merchant' ),
			'description' => __( 'Notify the merchant about new purchases.' ),
			'files' => array(
				dirname( __FILE__ ).'/classes/merchantPurchaseNotifications.class.php'
			),
			'callbacks' => array(
				array( 'GBS_Purchase_Notification', 'init' )
			),
		);
		return $addons;
	}

	add_filter( 'gb_addons', 'gbs_merchant_purchase_notifications', 10, 1 );
}
