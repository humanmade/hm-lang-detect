<?php namespace HMLanguageDetect;

class GeoCoder {

	protected $ip_address;

	protected $geoip_data = array();

	const API_DOMAIN = 'freegeoip.net';

	const RESPONSE_FORMAT = 'json';

	public function __construct( $ip_address ) {

		$this->ip_address = $ip_address;

	}

	public function get_api_url() {
		return 'http://'
		       . self::API_DOMAIN . '/'
		       . self::RESPONSE_FORMAT . '/'
		       . $this->ip_address;
	}

	/**
	 * Save visitor geolocation info for a day. It persists as a cookie anyway.
	 *
	 * @return bool|mixed
	 */
	public function get_visitor_geoip_data() {

		$response = wp_remote_get( $this->get_api_url() );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response = wp_remote_retrieve_body( $response );

		$response = json_decode( $response );

		update_option( 'visitor_geoip_' . $this->ip_address, $response );

	}
}
