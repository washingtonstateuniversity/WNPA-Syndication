<?php
/**
 * Class WNPA_External_Source
 *
 * Track external feeds to parse on a regular basis for news items to be added as
 * feed items to be consumed by others.
 */
class WNPA_External_Source {

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
		add_action( 'init',           array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes'     ) );
		add_action( 'save_post',      array( $this, 'save_post'          ), 10, 2 );

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
			'menu_name'          => 'External Sources'
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
			'supports'           => array( 'title', 'author', ),
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
	public function bulk_post_updated_message( $bulk_messages ) {
		if ( 'edit-wnpa_external_source' === get_current_screen()->id ) {
			foreach ( $bulk_messages['post'] as $key => $value ) {
				$bulk_messages['post'][ $key ] = str_replace( 'post', 'external source', $value );
			}
		}

		return $bulk_messages;
	}

	/**
	 * Add meta boxes used to track data about external sources.
	 */
	public function add_meta_boxes() {
		add_meta_box( 'wnpa_external_source_url', 'External Source URL', array( $this, 'display_source_url_meta_box' ), $this->source_content_type, 'normal' );
	}

	/**
	 * Display the meta box for external source URL.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function display_source_url_meta_box( $post ) {
		$external_source = get_post_meta( $post->ID, $this->source_url_meta_key, true );
		$source_status = get_post_meta( $post->ID, '_wnpa_source_status', true );

		?><input type="text" value="<?php echo esc_attr( $external_source ); ?>" name="wnpa_source_url" class="widefat" />
		<span class="description">Enter the URL of the RSS feed for the external source to be added to the syndicate item feed.</span>
	    <?php if ( $source_status ) {
			?><p><strong>URL Status:</strong> <?php echo esc_html( $source_status ); ?></p><?php
		}
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

		if ( ! isset( $_POST['wnpa_source_url'] ) ) {
			return;
		}

		// Attempt a HEAD request to the specified URL for current status info.
		$head_response = wp_remote_head( esc_url( $_POST['wnpa_source_url'] ) );

		if ( is_wp_error( $head_response ) ) {
			$response_meta = $head_response->get_error_message();
		} else {
			$response_code = wp_remote_retrieve_response_code( $head_response );
			if ( in_array( $response_code, array( 301, 302 ) ) ) {
				$response_meta = 'OK, but a redirect was made. Suggested change: ' . esc_url( $head_response['headers']['location'] );
			} else {
				$response_meta = wp_remote_retrieve_response_message( $head_response );
			}
		}

		update_post_meta( $post_id, '_wnpa_source_status', sanitize_text_field( $response_meta ) );
		update_post_meta( $post_id, $this->source_url_meta_key, esc_url_raw( $_POST['wnpa_source_url'] ) );
	}
}
global $wnpa_external_source;
$wnpa_external_source = new WNPA_External_Source();
