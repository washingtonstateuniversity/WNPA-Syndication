<?php
/**
 * Plugin Name: WNPA Syndication
 * Plugin URI: https://github.com/washingtonstateuniversity/wnpa-syndication
 * Description: Manage and republish content from multiple external sources into a single consumable feed.
 * Author: washingtonstateuniversity, jeremyfelt
 * Version 0.1
 * Author URI: http://web.wsu.edu/
 * Text Domain: wnpa-syndication
 */

include( dirname( __FILE__ ) . '/includes/class-wnpa-feed-item.php' );
include( dirname( __FILE__ ) . '/includes/class-wnpa-access-key.php' );
include( dirname( __FILE__ ) . '/includes/class-wnpa-external-source.php' );