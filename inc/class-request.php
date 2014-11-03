<?php namespace HMLanguageDetect;

class Request {

	protected $api_url;
	protected $unique_key;

	public function __construct( $api_url, $unique_key ) {
		$this->api_url = $api_url;
		$this->unique_key = $unique_key;
	}

	public function get_data() {

		$t = tlc_transient( 'hm-visitor-geoipdata-' . $this->unique_key );

		if ( true ) {
			$t->updates_with( array( $this, 'remote_request' ) );
		}

		$t->expires_in( WEEK_IN_SECONDS * 2 );

		return tlc_transient( 'hm-visitor-geoipdata-' . $this->unique_key )
			->updates_with( array( $this, 'remote_request' ) )
			->expires_in( DAY_IN_SECONDS * 1 )
			->background_only()
			->get();
	}

	public function remote_request() {

		// Response sample
		//{
		//ip: "177.99.66.238",
		//country_code: "BR",
		//country_name: "Brazil",
		//region_code: "",
		//region_name: "",
		//city: "",
		//zipcode: "",
		//latitude: -10,
		//longitude: -55,
		//metro_code: "",
		//area_code: ""
		//}

		$response = wp_remote_get( $this->api_url, array( 'timeout'=> 30 ) );

		if ( ! is_wp_error( $response )  ) {
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response );
			return $response;
		}

		return false;
	}
}
