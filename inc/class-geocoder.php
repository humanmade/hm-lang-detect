<?php namespace HMLanguageDetect;

class GeoCoder {

	protected $ip_address;

	const API_DOMAIN = 'freegeoip.net';

	const RESPONSE_FORMAT = 'json';

	protected $geoip_data = array();

	public function __construct( $ip_address ) {

		if ( false === ( $this->ip_address = filter_var( $ip_address, FILTER_VALIDATE_IP ) ) ) {
			throw new \Exception( 'Invalid IP address' );
		}

	}

	/**
	 * Save visitor geolocation info for a day. It persists as a cookie anyway.
	 *
	 * @return bool|mixed
	 */
	public function get_visitor_geoip_data() {

		$url = 'http://';
		$url .= implode( '/', array( self::API_DOMAIN, self::RESPONSE_FORMAT, $this->ip_address ) );

		require_once plugin_dir_path( __FILE__ ) . 'class-request.php';
		$request = new Request( $url, $this->ip_address );

		return $request->get_data();
		//return $request->remote_request(); // use this to test without transients

	}

	public function get_visitor_country() {

		$current_visitor_data = $this->get_visitor_geoip_data();
		if ( ! empty( $current_visitor_data ) ) {
			return $current_visitor_data->country_name;
		}

		return '';
	}

	public function get_country_lang() {

		$lang = 'en';

		switch ( $this->get_visitor_country() ) {
			case 'Belgium':
			case 'France':
				$lang = array( 'fr' => 'French' );
				break;

			case 'Germany':
				$lang = array( 'de' => 'Deutsch' );
				break;

			default:
				$lang = array( 'en' => 'English' );
				break;
		}

		return $lang;
	}
}