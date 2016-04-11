<?php
/**
 * Class PFI_External_Source
 *
 * Track external feeds to parse on a regular basis for news items to be added as
 * feed items to be consumed by others.
 */
class PFI_External_Source {

	/**
	 * @var string slug to be used for the external source content type.
	 */
	var $source_content_type = 'wnpa_external_source';

	/**
	 * @var string key to be used in the storage of the feed source URL meta data
	 */
	var $source_url_meta_key = '_wnpa_source_url';

	/**
	 * Add hooks as the class is initialized.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		// Use the custom hook setup to handle our cron action.
		add_action( 'publish_feed_items_consume_sources', array( $this, 'batch_external_sources' ) );

		add_filter( 'bulk_post_updated_messages', array( $this, 'bulk_post_updated_message' ), 10, 2 );
	}

	/**
	 * Register a post type for external source feeds. This post type is
	 * not accessible on the front end at this time.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => 'External Sources',
			'singular_name'      => 'External Source',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New External Source',
			'edit_item'          => 'Edit External Source',
			'new_item'           => 'New External Source',
			'all_items'          => 'All External Sources',
			'view_item'          => 'View External Source',
			'search_items'       => 'Search External Sources',
			'not_found'          => 'No external sources found',
			'not_found_in_trash' => 'No external sources found in Trash',
			'menu_name'          => 'External Sources',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'supports'           => false,
		);

		register_post_type( $this->source_content_type, $args );
	}

	/**
	 * Modify the default `x posts ` bulk action response text to display `external source`
	 * instead of `post` for the external sources custom content type.
	 *
	 * @param array $bulk_message Messages displayed when performing bulk actions.
	 *
	 * @return array Contains modified strings.
	 */
	public function bulk_post_updated_message( $bulk_messages, $count ) {
		if ( 'edit-wnpa_external_source' === get_current_screen()->id ) {
			$bulk_messages['post']['updated']   = _n( '%s external source updated.', '%s external sources updated.', $count['updated'], 'wnpa-syndication' );
			$bulk_messages['post']['locked']    = _n( '%s external source not updated, somebody is editing it.', '%s external sources not updated, somebody is editing them.', $count['locked'], 'wnpa-syndication' );
			$bulk_messages['post']['deleted']   = _n( '%s external source permanently deleted.', '%s external sources permanently deleted.', $count['deleted'], 'wnpa-syndication' );
			$bulk_messages['post']['trashed']   = _n( '%s external source moved to the Trash.', '%s external sources moved to the Trash.', $count['trashed'], 'wnpa-syndication' );
			$bulk_messages['post']['untrashed'] = _n( '%s external source restored from the Trash.', '%s external sources restored from the Trash.', $count['untrashed'], 'wnpa-syndication' );
		}

		return $bulk_messages;
	}

	/**
	 * Add meta boxes used to track data about external sources.
	 *
	 * @param string  $post_type The content type slug.
	 * @param WP_Post $post      Contains information about the current post.
	 */
	public function add_meta_boxes( $post_type, $post ) {
		if ( $this->source_content_type !== $post_type ) {
			return;
		}

		if ( empty( $post->post_title ) ) {
			$meta_title = 'New External Source';
		} else {
			$meta_title = $post->post_title;
		}
		add_meta_box( 'wnpa_external_source_url', $meta_title, array( $this, 'display_source_url_meta_box' ), $this->source_content_type, 'normal' );
	}

	/**
	 * Display the meta box for external source URL.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function display_source_url_meta_box( $post ) {
		$external_source  = get_post_meta( $post->ID, $this->source_url_meta_key, true );
		$source_status    = get_post_meta( $post->ID, '_wnpa_source_status',      true );
		$feed_response    = get_post_meta( $post->ID, '_wnpa_feed_response',      true );
		$feed_last_total  = get_post_meta( $post->ID, '_wnpa_feed_last_total',    true );
		$feed_last_count  = get_post_meta( $post->ID, '_wnpa_feed_last_count',    true );
		$feed_last_status = get_post_meta( $post->ID, '_wnpa_feed_last_status',   true );

		wp_nonce_field( 'save_external_source_url', '_wsuwp_pfi_external_source_nonce' );
		?>
		<h2>Feed URL:</h2>
		<input type="text" value="<?php echo esc_attr( $external_source ); ?>" name="wnpa_source_url" class="widefat" />
		<span class="description">Enter the URL of an RSS feed for the external source.</span>
	    <ul>
			<?php if ( $source_status ) : ?><li><strong>URL Check:</strong> <?php echo esc_html( $source_status ); ?></li><?php endif; ?>
			<?php if ( $feed_response ) : ?><li><strong>Feed Response:</strong> <?php echo esc_html( $feed_response ); ?></li><?php endif; ?>
			<?php if ( $feed_last_status ) : ?><li><strong>Feed Status:</strong> Last checked on <?php echo date( 'D, d M Y h:i:s', $feed_last_status + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ); ?></li><?php endif; ?>
			<?php if ( false !== $feed_last_total ) : ?><li><strong>Feed Items:</strong> Pulled <?php echo absint( $feed_last_total ); ?> items, <?php echo absint( $feed_last_count ); ?> were new.</li><?php endif; ?>
		</ul>
		<?php
	}

	/**
	 * Save posted information from the source URL meta data input.
	 *
	 * @param int     $post_id ID of the current source being edited.
	 * @param WP_Post $post    Post object of the current source being edited.
	 */
	public function save_post( $post_id, $post ) {
		if ( $this->source_content_type !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['_wsuwp_pfi_external_source_nonce'] ) || false === wp_verify_nonce( $_POST['_wsuwp_pfi_external_source_nonce'], 'save_external_source_url' ) ) {
			return;
		}

		if ( ! isset( $_POST['wnpa_source_url'] ) ) {
			return;
		}

		/**
		 * If we've lost the cron event scheduled to consume external sources, then start
		 * it up again 30 seconds in the future to account for the immediate consumption
		 * of this new external source.
		 */
		if ( false === wp_next_scheduled( 'publish_feed_items_consume_sources' ) ) {
			wp_schedule_event( time() + 30, 'hourly', 'publish_feed_items_consume_sources' );
		}

		$external_source_url = $_POST['wnpa_source_url'];

		// Attempt a HEAD request to the specified URL for current status info.
		$head_response = wp_remote_head( esc_url( $external_source_url ) );

		if ( is_wp_error( $head_response ) ) {
			$response_meta = $head_response->get_error_message();
		} else {
			$response_code = absint( wp_remote_retrieve_response_code( $head_response ) );
			if ( in_array( $response_code, array( 301, 302 ), true ) ) {
				$response_meta = 'OK, but a redirect was made. Suggested change: ' . esc_url( $head_response['headers']['location'] );
			} else {
				$response_meta = wp_remote_retrieve_response_message( $head_response );
			}
		}

		update_post_meta( $post_id, '_wnpa_source_status', sanitize_text_field( $response_meta ) );
		update_post_meta( $post_id, $this->source_url_meta_key, esc_url_raw( $external_source_url ) );

		// When an external source is published, immediately consume the feed.
		if ( 'publish' === $post->post_status ) {
			$this->_consume_external_source( esc_url( $external_source_url ), $post_id );
		} elseif ( in_array( $post->post_status, array( 'draft', 'future' ), true ) ) {
			$this->_consume_external_source( esc_url( $external_source_url ), $post_id, false );
		}
	}

	/**
	 * Grab a subset of external sources and loop through them to consume each individual
	 * feed's data for import into WordPress.
	 */
	function batch_external_sources() {
		// @todo meta query to get a subset
		$query_args = array(
			'post_type'      => $this->source_content_type,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 5,
		);
		$query = new WP_Query( $query_args );

		// If our query didn't return any items, we can bail.
		if ( ! isset( $query->posts ) || empty( $query->posts ) ) {
			return;
		}

		// Loop through each of the returned items and consume its source feed.
		foreach ( $query->posts as $post_id ) {
			$feed_url = get_post_meta( $post_id, $this->source_url_meta_key, true );

			$this->_consume_external_source( $feed_url, $post_id );
		}
	}

	/**
	 * Consume the specified external source and parse the feed data into individual
	 * feed items in WordPress.
	 *
	 * @param string $feed_url URL of a feed to be consumed.
	 * @param int    $post_id  ID of external source responsible for the feed.
	 */
	private function _consume_external_source( $feed_url, $post_id, $include_items = true ) {
		/* @type WPDB $wpdb */
		global $wpdb, $pfi_feed_item;

		update_post_meta( $post_id, '_wnpa_feed_last_status', time() );

		$default_source_location = wp_get_object_terms( $post_id, 'wnpa_item_location', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $default_source_location ) ) {
			$default_source_location = array();
		}

		// Apply a filter to the default feed cache lifetime.
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'modify_feed_cache' ) );

		// Fetch the passed feed URL.
		$feed_response = fetch_feed( esc_url( $feed_url ) );

		// Remove the filter we added to avoid breaking expectations set elsewhere.
		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'modify_feed_cache' ) );

		// check for a valid feed response
		if ( ! is_wp_error( $feed_response ) ) {

			$feed_title = $feed_response->get_title();
			remove_action( 'save_post', array( $this, 'save_post' ), 10 );
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $feed_title ) );
			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

			update_post_meta( $post_id, '_wnpa_feed_response', 'Success', true );

			if ( false === $include_items ) {
				return;
			}

			$feed_items = $feed_response->get_items();
			update_post_meta( $post_id, '_wnpa_feed_last_total', count( $feed_items ) );
			$new_items = 0;

			foreach ( $feed_items as $feed_item ) {
				/* @type SimplePie_Item $feed_item */

				// Use a hashed version of the item ID to see if it is unique.
				$id = md5( $feed_item->get_id() );
				$existing_item_id = $wpdb->get_var( $wpdb->prepare( "SELECT $wpdb->postmeta.post_id FROM $wpdb->postmeta WHERE $wpdb->postmeta.meta_key = '_feed_item_unique_hash' AND $wpdb->postmeta.meta_value = %s", $id ) );

				// Avoid creating duplicate feed items.
				if ( $existing_item_id ) {
					continue;
				}

				$link       = $feed_item->get_link();
				$title      = $feed_item->get_title();
				$content    = $feed_item->get_content();
				$date       = $feed_item->get_date( 'Y-m-d H:i:s' );
				$author     = $feed_item->get_author();
				$visibility = $feed_item->get_item_tags( SIMPLEPIE_NAMESPACE_DC_11, 'accessRights' );
				$categories = $feed_item->get_categories();

				// SimplePie returns `null` rather than an empty array if no categories are found.
				if ( ! $categories ) {
					$categories = array();
				}

				$locations = $default_source_location;
				$tags      = array();
				// Split the provided categories into tags and locations.
				foreach ( $categories as $category ) {

					// If no domain attribute is attached to category, assume a post tag
					if ( empty( $category->scheme ) ) {
						$tag = get_term_by( 'name', $category->term, 'post_tag' );

						// get_term_by and wp_insert_term respond differently, so the logic here is weird.
						if ( ! empty( $tag->term_id ) ) {
							$tag_id = $tag->term_id;
						} else {
							$tag = wp_insert_term( $category->term, 'post_tag' );

							if ( isset( $tag['term_id'] ) ) {
								$tag_id = $tag['term_id'];
							}
						}

						if ( ! empty( $tag_id ) ) {
							$tags[] = $tag_id;
						}
					} elseif ( 'wnpalocation' === $category->scheme ) {
						$location = get_term_by( 'name', $category->term, 'wnpa_item_location' );

						// get_term_by and wp_insert_term respond differently, so the logic here is weird.
						if ( ! empty( $location->term_id ) ) {
							$location_id = $location->term_id;
						} else {
							$location = wp_insert_term( $category->term, 'wnpa_item_location' );

							if ( isset( $location['term_id'] ) ) {
								$location_id = $location['term_id'];
							}
						}

						if ( ! empty( $location_id ) ) {
							$locations[] = $location_id;
						}
					}
				}

				if ( isset( $author->name ) ) {
					$author = sanitize_text_field( $author->name );
				} else {
					$author = 'No Author';
				}

				/**
				 * If visibility is not specified, assume public. If visibility is specified with
				 * an incorrect value, assume private.
				 */
				if ( empty( $visibility[0]['data'] ) ) {
					$visibility = 'public';
				} else if ( ! in_array( $visibility[0]['data'], array( 'public', 'private' ), true ) ) {
					$visibility = 'private';
				} else {
					$visibility = $visibility[0]['data'];
				}

				$post_args = array(
					'post_title'   => sanitize_text_field( $title ),
					'post_date'    => $date,
					'post_content' => wp_kses_post( $content ),
					'post_status'  => 'publish',
					'post_type'    => $pfi_feed_item->item_content_type,
				);
				$item_post_id = wp_insert_post( $post_args );

				add_post_meta( $item_post_id, '_feed_item_unique_hash', $id );
				add_post_meta( $item_post_id, '_feed_item_link_url', esc_url_raw( $link ) );
				add_post_meta( $item_post_id, '_feed_item_source', $post_id );
				add_post_meta( $item_post_id, '_feed_item_created', current_time( 'mysql' ) );
				add_post_meta( $item_post_id, '_feed_item_author', $author );

				wp_set_object_terms( $item_post_id, $tags,       'post_tag',             false );
				$locations = array_unique( $locations );
				wp_set_object_terms( $item_post_id, $locations,  'wnpa_item_location',   false );
				wp_set_object_terms( $item_post_id, $visibility, 'wnpa_item_visibility', false );

				$new_items++;
				update_post_meta( $post_id, '_wnpa_feed_last_count', $new_items );
			}
			// save items to a new feed item content type
		} else {
			update_post_meta( $post_id, '_wnpa_feed_response', $feed_response->get_error_message(), true );
		}
	}

	/**
	 * The default cache time for SimplePie is higher than we'd like. The results we are bringing
	 * in will be updated frequently and we'll be controlling the request time through a cron
	 * event. We're save to set a very low value here at the moment.
	 *
	 * @return int Time in seconds to cache the feed request.
	 */
	public function modify_feed_cache() {
		return 30;
	}
}
global $pfi_external_source;
$pfi_external_source = new PFI_External_Source();
