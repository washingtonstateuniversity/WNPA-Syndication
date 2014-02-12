<?php
/**
 * Class WNPA_Access_Key
 *
 * Provides functionality for creating and verifying the access keys
 * used for basic authentication with the WNPA system.
 */
class WNPA_Access_Key {

	/**
	 * @var string The meta key used in user meta for the access key.
	 */
	var $access_key_meta = '_wnpa_access_key';

	/**
	 * @var string The public query vary used to retrieve private items.
	 */
	var $query_var = 'access_key';

	public function __construct() {
		add_action( 'show_user_profile',                array( $this, 'user_profile_show_key' ), 10    );
		add_action( 'admin_enqueue_scripts',            array( $this, 'admin_enqueue_scripts' ), 10    );
		add_action( 'wp_ajax_wnpa_generate_access_key', array( $this, 'generate_access_key'   ), 10    );
		add_action( 'personal_options_update',          array( $this, 'update_profile'        ), 10, 1 );

		add_filter( 'query_vars',                       array( $this, 'filter_query_vars'  ), 10, 1 );
	}

	/**
	 * Enqueue the Javascript required to handle key generation on the
	 * user's profile page.
	 */
	public function admin_enqueue_scripts() {
		if ( 'profile' !== get_current_screen()->base ) {
			return;
		}

		wp_enqueue_script( 'wnpa-admin', plugins_url( '/wnpa-syndication/js/wnpa-admin.js' ), array( 'jquery' ), false, true );
		// Make wnpa_admin.nonce available to the wnpa-admin.js file
		wp_localize_script( 'wnpa-admin', 'wnpa_admin', array( 'nonce' => wp_create_nonce( 'generate-access-key' ) ) );
	}

	/**
	 * Generate an MD5 hash to be used as a unique access key for the
	 * requesting user.
	 */
	public function generate_access_key() {
		check_ajax_referer( 'generate-access-key' );

		echo md5( 'wnpa' . time() . get_current_user_id() );
		exit;
	}

	/**
	 * Show a section on the user's profile page for the WNPA Accees Key
	 * and allow that key to be generated for future use.
	 */
	public function user_profile_show_key() {
		if ( ! IS_PROFILE_PAGE ) {
			return;
		}

		$access_key = get_user_meta( get_current_user_id(), $this->access_key_meta, true );
		$feed_url = home_url( 'feed-items/feed/' );

		if ( empty( $access_key ) ) {
			$access_key = '';
		} else {
			$access_key = sanitize_key( $access_key );
			$feed_url = add_query_arg( $this->query_var, $access_key, $feed_url );
		}

		?>
		<h3>WNPA Access Information:</h3>
		<table class="form-table">
			<tr>
				<th><label for="access_key">Access Key</label></th>
				<td>
					<input readonly="readonly" type="text" id="wnpa-access-key" class="regular-text" name="access_key" size="24" value="<?php echo esc_attr( $access_key ); ?>" \><span id="wnpa-generate-key" class="button button-large">Generate</span>
					<br />
					<span class="description">This key can be used to access both public and private items in the RSS feeds offered through the WNPA site.<br />
						Use the generate button to create a new key and click <em>Update Profile</em> below to begin using it.</span>
				</td>
			</tr>
			<tr>
				<th><label for="access-url">Feed URL</label></th>
				<td>
					<a href="<?php echo esc_url( $feed_url ); ?>"><?php echo esc_url( $feed_url ); ?></a>
				</td>
			</tr>
		</table><?php
	}

	/**
	 * Capture custom user meta information that we've added to the user's profile page.
	 *
	 * @param int $user_id User ID of the profile being updated.
	 */
	public function update_profile( $user_id ) {
		if ( empty( $_POST['access_key'] ) ) {
			return;
		}

		$access_key = sanitize_key( $_POST['access_key'] );
		update_user_meta( $user_id, $this->access_key_meta, $access_key );
	}

	/**
	 * Filter public query vars to include the access key required for private feed items.
	 *
	 * @param array $query_vars Existing public query vars.
	 *
	 * @return array Modified public query vars.
	 */
	public function filter_query_vars( $query_vars ) {
		$query_vars[] = $this->query_var;

		return $query_vars;
	}
}
global $wnpa_access_key;
$wnpa_access_key = new WNPA_Access_Key();