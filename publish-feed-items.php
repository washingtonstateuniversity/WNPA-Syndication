<?php
/**
 * Plugin Name: Publish Feed Items
 * Plugin URI: https://github.com/washingtonstateuniversity/wnpa-syndication
 * Description: Manage and republish content from multiple external RSS feeds.
 * Author: washingtonstateuniversity, jeremyfelt
 * Version 0.3.2
 * Author URI: https://web.wsu.edu/
 * Text Domain: wsuwp-publish-feed-items
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Code that runs during activation of the plugin on an individual site.
 *
 * If the plugin is network activated, we can't rely on this activate code to properly
 * setup cron events for all sites on the network and instead rely on other code.
 *
 * @since 1.0.0
 *
 * @param bool $network_wide True if the plugin is network activated. False if not.
 */
function activate_publish_feed_items( $network_wide ) {
	if ( true === $network_wide ) {
		return;
	}

	require_once dirname( __FILE__ ) . '/includes/class-publish-feed-items-activator.php';
	Publish_Feed_Items_Activator::activate();
}

/**
 * Code that runs during deactivation of the plugin on an individual site.
 *
 * @since 1.0.0
 *
 * @param bool $network_wide True if the plugin is network deactivated. False if not.
 */
function deactivate_publish_feed_items( $network_wide ) {
	if ( true === $network_wide ) {
		return;
	}

	require_once dirname( __FILE__ ) . '/includes/class-publish-feed-items-deactivator.php';
	Publish_Feed_Items_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_publish_feed_items' );
register_deactivation_hook( __FILE__, 'deactivate_publish_feed_items' );

// The core plugin class.
require dirname( __FILE__ ) . '/includes/class-wsuwp-publish-feed-items.php';

add_action( 'after_setup_theme', 'WSUWP_Publish_Feed_Items' );
/**
 * Start things up.
 *
 * @return \WSUWP_Publish_Feed_Items
 */
function WSUWP_Publish_Feed_Items() {
	return WSUWP_Publish_Feed_Items::get_instance();
}
