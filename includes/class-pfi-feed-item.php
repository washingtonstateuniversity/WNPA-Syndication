<?php
/**
 * Class PFI_Feed_Item
 *
 * Manage the feed item content type.
 */
class PFI_Feed_Item {

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
		add_action( 'init', array( $this, 'register_post_type' ), 10 );
		add_action( 'init', array( $this, 'register_taxonomy_visibility' ), 10 );
		add_action( 'init', array( $this, 'register_taxonomy_location' ), 10 );
		add_action( 'wp', array( $this, 'feed_item_view' ), 10 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		add_action( 'rss2_item', array( $this, 'rss_item_visibility' ), 10 );
		add_action( 'rss2_item', array( $this, 'rss_item_media_thumbnail' ), 10 );
		add_action( 'pre_get_posts', array( $this, 'modify_feed_query' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10 );

		add_filter( 'the_category_rss', array( $this, 'rss_category_location' ), 10, 1 );
		add_filter( 'wp_dropdown_cats', array( $this, 'selective_taxonomy_dropdown' ), 10, 1 );
		add_filter( 'manage_wnpa_feed_item_posts_columns', array( $this, 'manage_posts_columns' ), 10, 1 );
		add_action( 'manage_wnpa_feed_item_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 10, 2 );

		add_action( 'post_submitbox_misc_actions', array( $this, 'submitbox_publish_item' ) );
		add_action( 'save_post', array( $this, 'save_post_submitbox_actions' ), 10, 2 );
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
		if ( get_current_screen()->id !== $this->item_content_type ) {
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
			'menu_name'          => 'Feed Items',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'feed-item' ),
			'capability_type'    => 'post',
			'has_archive'        => 'feed-items',
			'hierarchical'       => false,
			'supports'           => array( 'thumbnail' ),
			'taxonomies'         => array( 'post_tag', 'category' ),
		);

		/**
		 * Filter whether feed items should be public.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $public Default false. True if feed items should be exposed at /feed-items/.
		 */
		if ( apply_filters( 'wsuwp_pfi_public_feed_items', false ) ) {
			$args['public'] = true;
			$args['publicly_queryable'] = true;
		}

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

		add_meta_box( 'wsuwp_pfi_feed_item_content', esc_html( $post->post_title ), array( $this, 'display_feed_item_content_meta_box' ), $this->item_content_type, 'normal' );
		add_meta_box( 'wnpa_featured_item', 'Featured Article', array( $this, 'display_featured_item_meta_box' ), $this->item_content_type, 'normal' );
		add_meta_box( 'wnpa_byline', 'Byline Information', array( $this, 'display_byline_meta_box' ), $this->item_content_type, 'normal' );
	}

	/**
	 * Display the title and content from a feed item. This replaces the standard title
	 * and editor meta boxes.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post
	 */
	public function display_feed_item_content_meta_box( $post ) {
		?>
		<div class="feed_item_content">
			<?php echo apply_filters( 'the_content', wp_kses_post( $post->post_content ) ); ?>
		</div>
		<?php
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

		if ( ! current_user_can( 'administrator' ) ) {
			$disabled = 'disabled="disabled"';
		} else {
			$disabled = '';
		}

		wp_nonce_field( 'save-feed-item-featured', '_wnpa_featured_nonce' );
		?>
		<select name="feed_item_featured" <?php echo $disabled; ?>>
			<option value="featured" <?php selected( 'featured', $featured_status ); ?>>Featured</option>
			<option value="normal" <?php selected( 'normal', $featured_status ); ?>>Not Featured</option>
		</select>
		<?php
	}

	/**
	 * Display a meta box to capture manual entry information for a feed item.
	 *
	 * @param WP_Post $post Current post being edited.
	 */
	public function display_byline_meta_box( $post ) {
		$item_source = get_post_meta( $post->ID, '_feed_item_source_manual', true );
		$item_author = get_post_meta( $post->ID, '_feed_item_author', true );

		wp_nonce_field( 'save-feed-item-byline', '_wnpa_byline_nonce' );
		?>
		<label for="feed_item_author">Feed Item Author:</label>
		<input name="feed_item_author" type="text" id="feed_item_author" value="<?php echo esc_attr( $item_author ); ?>" />
		<br />
		<label for="feed_item_source">Feed Item Source:</label>
		<input name="feed_item_source" type="text" id="feed_item_source" value="<?php echo esc_attr( $item_source ); ?>" />
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

		if ( isset( $_POST['_wnpa_featured_nonce'] ) && wp_verify_nonce( $_POST['_wnpa_featured_nonce'], 'save-feed-item-featured' ) && isset( $_POST['feed_item_featured'] ) ) {
			if ( ! in_array( $_POST['feed_item_featured'], array( 'normal', 'featured' ), true ) ) {
				$featured_status = 'normal';
			} else {
				$featured_status = $_POST['feed_item_featured'];
			}

			if ( current_user_can( 'administrator' ) ) {
				update_post_meta( $post_id, '_wnpa_featured_article', $featured_status );
			}
		}

		if ( 'publish' === $post->post_status ) {
			$terms = wp_get_post_terms( $post_id, $this->item_visibility_taxonomy );
			if ( empty( $terms ) ) {
				wp_set_object_terms( $post_id, 'Public', $this->item_visibility_taxonomy );
			}
		}

		if ( isset( $_POST['_wnpa_byline_nonce'] ) && wp_verify_nonce( $_POST['_wnpa_byline_nonce'], 'save-feed-item-byline' ) ) {
			if ( isset( $_POST['feed_item_source'] ) ) {
				$source = esc_html( $_POST['feed_item_source'] );
				if ( ! empty( $source ) ) {
					update_post_meta( $post_id, '_feed_item_source_manual', $source );
				}
			}

			if ( isset( $_POST['feed_item_author'] ) ) {
				$author = esc_html( $_POST['feed_item_author'] );
				if ( ! empty( $author ) ) {
					update_post_meta( $post_id, '_feed_item_author', $author );
				}
			}
		}
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

		if ( empty( $locations ) || is_wp_error( $locations ) ) {
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
				),
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
		$post_columns['item_location'] = 'Location';
		$post_columns['item_image'] = 'Featured Image';
		$post_columns['date'] = 'Date';
		return $post_columns;
	}

	public function manage_posts_custom_column( $column_name, $post_id ) {
		if ( 'item_source' === $column_name ) {
			$source_id = get_post_meta( $post_id, '_feed_item_source', true );
			if ( absint( $source_id ) > 0 ) {
				$source = get_post( $source_id );
				echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $source_id . '&action=edit' ) ) . '">' . esc_html( $source->post_title ) . '</a>';
			} else {
				$source = get_post_meta( $post_id, '_feed_item_source_manual', true );
				if ( ! empty( $source ) ) {
					echo esc_html( $source );
				} else {
					echo 'Manual entry';
				}
			}
		}

		if ( 'item_location' === $column_name ) {
			$item_locations = wp_get_object_terms( $post_id, $this->item_location_taxonomy );
			$locations = array();
			foreach ( $item_locations as $item_location ) {
				$locations[] = '<a href="' . esc_url( admin_url( 'edit.php?wnpa_item_location=' . $item_location->slug . '&post_type=wnpa_feed_item' ) ) . '">'. $item_location->name . '</a>';
			}

			if ( ! empty( $locations ) ) {
				echo implode( ', ', $locations );
			} else {
				echo 'Not assigned';
			}
		}

		if ( 'item_image' === $column_name ) {
			if ( has_post_thumbnail( $post_id ) ) {
				$image_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ) );
				if ( isset( $image_src[0] ) ) {
					echo '<img src="' . esc_url( $image_src[0] ) . '" style="height: 60px;" />';
				} else {
					echo 'Invalid image';
				}
			} else {
				echo 'No image';
			}
		}
	}

	public function admin_enqueue_scripts() {
		if ( in_array( get_current_screen()->id, array( 'wnpa_feed_item', 'edit-wnpa_feed_item' ), true ) ) {
			wp_enqueue_style( 'wnpa-feed-item-list', plugins_url( '../css/feed-item.css', __FILE__ ) );
		}
	}

	/**
	 * Retrieve the corresponding post ID of a feed item or post associated with a feed item.
	 *
	 * @since 1.0.0
	 * @global wpdb $wpdb
	 *
	 * @param int $post_id ID of the feed item or post.
	 *
	 * @return int|bool The ID of the corresponding post or feed item if available. False if not.
	 */
	public static function get_feed_item_post_id( $post_id ) {
		global $wpdb;

		$feed_item_unique_hash = get_post_meta( $post_id, '_feed_item_unique_hash', true );
		$existing_item_id = $wpdb->get_var( $wpdb->prepare( "SELECT $wpdb->postmeta.post_id FROM $wpdb->postmeta WHERE $wpdb->postmeta.meta_key = '_feed_item_unique_hash' AND $wpdb->postmeta.meta_value = %s AND $wpdb->postmeta.post_id <> %d", $feed_item_unique_hash, $post_id ) );

		if ( 0 < absint( $existing_item_id ) ) {
			return $existing_item_id;
		}

		return false;
	}

	/**
	 * Display an option to publish a feed item as a post on the site. If the item is already
	 * published as a post, show a link to edit that post instead.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post $post
	 */
	public function submitbox_publish_item( $post ) {
		$existing_item_id = self::get_feed_item_post_id( $post->ID );

		if ( empty( $existing_item_id ) && $this->item_content_type === $post->post_type ) {
			wp_nonce_field( 'wsuwp_publish_feed_item', '_wsuwp_publish_feed_item_nonce' );
			?>
			<div class="misc-pub-section misc-pub-visibility">
				<input type="checkbox" id="publish-feed-item" name="publish_feed_item" value="yes"><label for="publish-feed-item">Publish feed item as post</label>
			</div>
			<?php
		} elseif ( $this->item_content_type === $post->post_type ) {
			$edit_url = get_edit_post_link( $existing_item_id );
			?>
			<div class="misc-pub-section misc-pub-visibility">
				<a href="<?php echo esc_url( $edit_url ); ?>">Edit corresponding post.</a>
			</div>
			<?php
		} elseif ( 'post' === $post->post_type ) {
			$edit_url = get_edit_post_link( $existing_item_id );
			?>
			<div class="misc-pub-section misc-pub-visibility">
				<a href="<?php echo esc_url( $edit_url ); ?>">Edit corresponding feed item.</a>
			</div>
			<?php
		}

	}

	/**
	 * When a feed item is updated, save any associated data we've captured in the submit box.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function save_post_submitbox_actions( $post_id, $post ) {
		if ( $this->item_content_type !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['_wsuwp_publish_feed_item_nonce'] ) || ! wp_verify_nonce( $_POST['_wsuwp_publish_feed_item_nonce'], 'wsuwp_publish_feed_item' ) ) {
			return;
		}

		if ( ! isset( $_POST['publish_feed_item'] ) || 'yes' !== $_POST['publish_feed_item'] ) {
			return;
		}

		$feed_item_id = get_post_meta( $post_id, '_feed_item_unique_hash', true );

		if ( strlen( $feed_item_id ) !== 32 ) {
			return; // @todo an error should show or the hash should be recalculated.
		}

		$new_post = (array) $post;
		unset( $new_post['ID'] );
		$new_post['post_type'] = 'post';

		/**
		 * Filter the default post_status of the corresponding post for a feed item.
		 *
		 * @since 1.0.0
		 *
		 * @param string $post_status Default 'draft'.
		 */
		$new_post['post_status'] = apply_filters( 'wsuwp_pfi_default_post_status', 'draft' );

		$new_post_id = wp_insert_post( $new_post );

		if ( is_wp_error( $new_post_id ) ) {
			return; // @todo an error should show here.
		}

		update_post_meta( $new_post_id, '_feed_item_unique_hash', $feed_item_id );

		return;
	}
}
global $pfi_feed_item;
$pfi_feed_item = new PFI_Feed_Item();
