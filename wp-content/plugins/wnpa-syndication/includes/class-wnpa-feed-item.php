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
		add_action( 'wp', array( $this, 'feed_item_view' ), 10 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'rss2_item',        array( $this, 'rss_item_visibility'          ), 10    );
		add_action( 'rss2_item', array( $this, 'rss_item_media_thumbnail' ), 10 );
		add_action( 'pre_get_posts',    array( $this, 'modify_feed_query'            ), 10    );

		add_filter( 'the_category_rss', array( $this, 'rss_category_location'        ), 10, 1 );
		add_filter( 'wp_dropdown_cats', array( $this, 'selective_taxonomy_dropdown'  ), 10, 1 );
		add_filter( 'manage_wnpa_feed_item_posts_columns', array( $this, 'manage_posts_columns' ), 10, 1 );
		add_action( 'manage_wnpa_feed_item_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 10, 2 );
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
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'taxonomies'         => array( 'post_tag', 'category' )
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
			'hierarchical' => true,
			'labels'       => $labels,
			'show_ui'      => true,
			'query_var'    => true,
			'rewrite'      => array( 'slug' => 'location' ),
		);
		register_taxonomy( $this->item_location_taxonomy, array( $this->item_content_type, 'wnpa_external_source' ), $args );
	}

	/**
	 * Add meta boxes for feed items.
	 *
	 * @param string  $post_type The content type slug.
	 * @param WP_Post $post      Contains information about the current post.
	 */
	public function add_meta_boxes( $post_type, $post ) {
		if ( $this->item_content_type !== $post_type ) {
			return;
		}

		add_meta_box( 'wnpa_featured_item', 'Featured Article', array( $this, 'display_featured_item_meta_box' ), $this->item_content_type, 'normal' );
	}

	/**
	 * Display a meta box to assign the featured article status to a feed item.
	 * 
	 * @param WP_Post $post Current feed item object.
	 */
	public function display_featured_item_meta_box( $post ) {
		$featured_status = get_post_meta( $post->ID, '_wnpa_featured_article', true );

		if ( 'featured' !== $featured_status ) {
			$featured_status = 'normal';
		}
		?>
		<select name="feed_item_featured">
			<option value="featured" <?php selected( 'featured', $featured_status ); ?>>Featured</option>
			<option value="normal" <?php selected( 'normal', $featured_status ); ?>>Not Featured</option>
		</select>
		<?php
	}

	/**
	 * Save posted information from the featured item selection box.
	 *
	 * @param int     $post_id ID of the current feed item being edited.
	 * @param WP_Post $post    Post object of the current feed item being edited.
	 */
	public function save_post( $post_id, $post ) {
		if ( $this->item_content_type !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['feed_item_featured'] ) ) {
			return;
		}

		if ( ! in_array( $_POST['feed_item_featured'], array( 'normal', 'featured' ) ) ) {
			$featured_status = 'normal';
		} else {
			$featured_status = $_POST['feed_item_featured'];
		}

		update_post_meta( $post_id, '_wnpa_featured_article', $featured_status );
	}

	/**
	 * Output a field in the RSS feed indicating the visibility of each
	 * individual item. Uses the accessRights term available through the
	 * Dublin Core namespace.
	 */
	public function rss_item_visibility() {
		global $post;

		if ( $this->item_content_type !== $post->post_type ) {
			return;
		}

		$visibility_terms = wp_get_object_terms( $post->ID, $this->item_visibility_taxonomy );

		if ( empty( $visibility_terms ) ) {
			$visibility = 'public';
		} else {
			$visibility = $visibility_terms[0]->slug;
		}

		?>	<dc:accessRights><?php echo esc_html( $visibility ); ?></dc:accessRights><?php
	}

	/**
	 * Output a media:thumbnail element in the RSS feed if a featured image has been
	 * assigned to a feed item.
	 */
	public function rss_item_media_thumbnail() {
		global $post;

		if ( $this->item_content_type !== $post->post_type ) {
			return;
		}

		if ( has_post_thumbnail( $post->ID ) ) {
			$thumbnail_url = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ) );
			if ( is_array( $thumbnail_url ) ) {
				?> <media:thumbnail url="<?php echo esc_url( $thumbnail_url[0] ); ?>" /> <?php
			}
		}
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

	/**
	 * Redirect unauthenticated users to the home page when an attempt
	 * to view an individual feed item is made.
	 */
	public function feed_item_view() {
		if ( is_singular( 'wnpa_feed_item' ) && ! is_user_logged_in() ) {
			wp_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Alter post columns for the feed item type to include the source.
	 *
	 * @param $post_columns
	 *
	 * @return mixed
	 */
	public function manage_posts_columns( $post_columns ) {
		unset( $post_columns['tags'] );
		unset( $post_columns['date'] );
		$post_columns['item_source'] = 'Source';
		$post_columns['tags'] = 'Tags';
		$post_columns['date'] = 'Date';
		return $post_columns;
	}

	public function manage_posts_custom_column( $column_name, $post_id ) {
		if ( 'item_source' === $column_name ) {
			$source_id = get_post_meta( $post_id, '_feed_item_source', true );
			if ( $source_id ) {
				$source = get_post( absint( $source_id ) );
				echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $source_id . '&action=edit' ) ) . '">' . esc_html( $source->post_title ) . '</a>';
			} else {
				echo 'Manual entry';
			}

		}
	}
}
global $wnpa_feed_item;
$wnpa_feed_item = new WNPA_Feed_Item();