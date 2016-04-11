<?php

class WSUWP_Publish_Feed_Items {
	/**
	 * @var WSUWP_Publish_Feed_Items
	 */
	private static $instance;

	/**
	 * Maintain and return the one instance. Initiate hooks when
	 * called the first time.
	 *
	 * @since 1.0.0
	 *
	 * @return \WSUWP_Publish_Feed_Items
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			include dirname( dirname( __FILE__ ) ) . '/includes/class-pfi-feed-item.php';
			include dirname( dirname( __FILE__ ) ) . '/includes/class-pfi-access-key.php';
			include dirname( dirname( __FILE__ ) ) . '/includes/class-pfi-external-source.php';

			self::$instance = new WSUWP_Publish_Feed_Items();
		}
		return self::$instance;
	}
}
