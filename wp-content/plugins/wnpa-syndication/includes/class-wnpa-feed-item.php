<?php
/**
 * Class WNPA_Feed_Item
 *
 * Manage the feed item content type used by the WNPA Syndication plugin.
 */
class WNPA_Feed_Item {

	/**
	 * @var string Slug used for the visibility taxonomy.
	 */
	var $item_visibility_taxonomy = 'wnpa_item_visibility';

	/**
	 * @var string Slug used for the location taxonomy.
	 */
	var $item_location_taxonomy = 'wnpa_item_location';

	/**
	 * @var string Slug used for the feed item content type.
	 */
	var $item_content_type = 'wnpa_feed_item';

	public function __construct() {
		add_action( 'init',             array( $this, 'register_post_type'           ), 10    );
		add_action( 'init',             array( $this, 'register_taxonomy_visibility' ), 10    );
		add_action( 'init',             array( $this, 'register_taxonomy_location'   ), 10    );
		add_action( 'rss2_item',        array( $this, 'rss_item_visibility'          ), 10    );
		add_action( 'pre_get_posts',    array( $this, 'modify_feed_query'            ), 10    );

		add_filter( 'the_category_rss', array( $this, 'rss_category_location'        ), 10, 1 );
		add_filter( 'wp_dropdown_cats', array( $this, 'selective_taxonomy_dropdown'  ), 10, 1 );
	}

	/**
	 * Don't display a parent taxonomy selection drop down when dealing with the
	 * visibility taxonomy.
	 *
	 * @param string $output Current output for dropndown taxonomy list.
	 *
	 * @return string Modified output for dropdown taxonomy list.
	 */
	public function selective_taxonomy_dropdown( $output ) {
		if ( $this->item_content_type !== get_current_screen()->id ) {
			return $output;
		}

		return '';
	}

	/**
	 * Register the feed item post type used to track incoming and outgoing
	 * feed items across multiple publishers.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => 'Feed Items',
			'singular_name'      => 'Feed Item',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Feed Item',
			'edit_item'          => 'Edit Feed Item',
			'new_item'           => 'New Feed Item',
			'all_items'          => 'All Feed Items',
			'view_item'          => 'View Feed Item',
			'search_items'       => 'Search Feed Items',
			'not_found'          => 'No feed items found',
			'not_found_in_trash' => 'No feed items found in Trash',
			'menu_name'          => 'Feed Items'
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'feed-item' ),
			'capability_type'    => 'post',
			'has_archive'        => 'feed-items',
			'hierarchical'       => false,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' ),
			'taxonomies'         => array( 'post_tag' )
		);

		register_post_type( $this->item_content_type, $args );

	}

	/**
	 * Register the taxonomy controlling the visibility of a feed item.
	 */
	public function register_taxonomy_visibility() {
		$labels = array(
			'name'              => 'Visibility',
			'search_items'      => 'Search Visibility',
			'all_items'         => 'All Visibilities',
			'edit_item'         => 'Edit Visibility',
			'update_item'       => 'Update Visibility',
			'add_new_item'      => 'Add New Visibility',
			'new_item_name'     => 'New Visibility Name',
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'visibility' ),
		);
		register_taxonomy( $this->item_visibility_taxonomy, array( $this->item_content_type ), $args );
	}

	/**
	 * Register the taxonomy used for feed item location.
	 */
	public function register_taxonomy_location() {
		$labels = array(
			'name'          => 'Location',
			'search_items'  => 'Search Locations',
			'all_items'     => 'All Locations',
			'edit_item'     => 'Edit Location',
			'update_item'   => 'Update Location',
			'add_new_item'  => 'Add New Location',
			'new_item_name' => 'New Location Name',
		);

		$args = array(
			'hierarchical' => false,
			'labels'       => $labels,
			'show_ui'      => true,
			'query_var'    => true,
			'rewrite'      => array( 'slug' => 'location' ),
		);
		register_taxonomy( $this->item_location_taxonomy, array( $this->item_content_type ), $args );
	}

	/**
	 * Output a field in the RSS feed indicating the visibility of each
	 * individual item. Uses the accessRights term available through the
	 * Dublin Core namespace.
	 */
	public function rss_item_visibility() {
		global $post;

		$visibility_terms = wp_get_object_terms( $post->ID, $this->item_visibility_taxonomy );

		if ( empty( $visibility_terms ) ) {
			$visibility = 'public';
		} else {
			$visibility = $visibility_terms[0]->slug;
		}

		?>	<dc:accessRights><?php echo esc_html( $visibility ); ?></dc:accessRights><?php
	}

	/**
	 * Output fields in an RSS feed indicating one or more locations for each
	 * individual items. Uses the category field with a wnpalocation domain
	 * to indicate the taxonomy type.
	 */
	public function rss_category_location( $rss_category_list ) {
		global $post;

		$locations = get_the_terms( $post->ID, $this->item_location_taxonomy );

		if ( empty( $locations) || is_wp_error( $locations ) ) {
			return $rss_category_list;
		}

		foreach ( $locations as $location ) {
			$location_name = sanitize_term_field( 'name', $location->name, $location->term_id, $this->item_location_taxonomy, 'rss' );
			$rss_category_list .= '<category domain="wnpalocation"><![CDATA[' . @html_entity_decode( $location_name, ENT_COMPAT, get_option( 'blog_charset' ) ) . ']]></category>';
		}

		return $rss_category_list;
	}

	/**
	 * Modify the query object to include a taxonomy query for public items only if a valid
	 * access key is not provided.
	 *
	 * @param WP_Query $query Current query object being processed.
	 *
	 * @return WP_Query Modified query object.
	 */
	public function modify_feed_query( $query ) {
		if ( $query->is_feed() && $this->item_content_type === $query->query_vars['post_type'] ) {

			if ( isset( $query->query_vars['access_key'] ) ) {
				// Look for a user matching the requested access key
				$meta_query = array(
					'meta_key' => '_wnpa_access_key',
					'meta_value' => $query->query_vars['access_key'],
				);
				$user = get_users( $meta_query );

				// If a matching user is found, return with the query unmodified.
				if ( ! is_wp_error( $user ) && ! empty( $user ) ) {
					return;
				}
			}

			// No user has been found, so modify the query to only include public items.
			$public_query = array(
				array(
					'taxonomy' => 'wnpa_item_visibility',
					'field' => 'name',
					'terms' => 'Public',
				)
			);
			$query->set( 'tax_query', $public_query );
		}

		return;
	}
}
global $wnpa_feed_item;
$wnpa_feed_item = new WNPA_Feed_Item();