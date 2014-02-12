(function( window, $ ) {
	/**
	 * Capture the nonce generated and passed as an object
	 * in the HTML document.
	 *
	 * @type string
	 */
	var nonce = window.wnpa_admin.nonce;

	/**
	 * Make an Ajax request to generate an access key for use in
	 * the initiating user's profile.
	 *
	 * @param e The event associated with the 'Generate' click.
	 */
	function generate_access_key( e ) {
		e.preventDefault();

		var data = {
			action: 'wnpa_generate_access_key',
			_ajax_nonce: nonce
		};

		$.post( ajaxurl, data, handle_response );
	}

	/**
	 * Handle the response received from WordPress. Ensure that the
	 * response is alphanumeric (MD5 string) before transferring it
	 * to the DOM.
	 *
	 * @param response An MD5 string to become the user's access key.
	 */
	function handle_response( response ) {
		if ( response.match( /^[0-9a-z]+$/ ) ) {
			$( '#wnpa-access-key' ).val( response );
		}
	}

	$( '#wnpa-generate-key' ).on( 'click', generate_access_key );
})( window, jQuery );