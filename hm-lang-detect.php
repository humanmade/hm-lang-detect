<?php
/*
Plugin Name: Hm-lang-detect
Version: 0.1-alpha
Description: PLUGIN DESCRIPTION HERE
Author: YOUR NAME HERE
Author URI: YOUR SITE HERE
Plugin URI: PLUGIN SITE HERE
Text Domain: hm-lang-detect
Domain Path: /languages
*/

class HM_Lang_Detect {

	static protected $instance;

	protected $geocoder;

	protected function __construct() {

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

	}

	public function plugins_loaded() {

		add_action( 'admin_post_no_priv_switch_language', array( $this, 'switch_language' ) );
		add_action( 'admin_post_switch_language', array( $this, 'switch_language' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		add_filter( 'heartbeat_received', array( $this, 'heartbeat_receive' ), 10, 2 );
		add_filter( 'heartbeat_received_no_priv', array( $this, 'heartbeat_receive' ), 10, 2 );

		add_action( 'wp_ajax_render_notice', array( $this, 'ajax_render_notice' ) );
	}

	public static function get_instance() {

		if ( ! ( self::$instance instanceof HM_Lang_Detect ) ) {
			self::$instance = new HM_Lang_Detect();
		}
		return self::$instance;
	}

	public function detect() {

		require_once plugin_dir_path( __FILE__ ) . 'inc/lib/autoload.php';
		require_once plugin_dir_path( __FILE__ ) . 'inc/class-geocoder.php';

		//$ip_address = $_SERVER['REMOTE_ADDR'];
		$ip_address = '5.39.127.35';

		$this->geocoder = new \HMLanguageDetect\GeoCoder( $ip_address );
		$suggested_lang = $this->geocoder->get_country_lang();

		global $wp;
		$current_lang = ( 0 < strlen( $wp->request ) ) ? $wp->request : 'en';

		// If we're not on the home page already and not on a lang page, redirect to home
		if (  ! in_array( $current_lang, array( 'en', 'fr', 'de' ) ) ) {
			wp_redirect( home_url() );exit;
		}

		if ( $current_lang !== key( $suggested_lang ) ) {
			$this->prompt_language( $suggested_lang );
		}
	}

	public function prompt_language( $lang ) {
		// Display a dismissable notice with URL to detected lang page
		ob_start(); ?>
		<div class="hm-lang-switcher">
			<p>Based on your location, we suggest viewing this page in <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'switch_language', 'hm_lang' => key( $lang ) ), admin_url( 'admin-post.php' ) ), 'hm_switch_lang_action', 'hm_switch_lang_nonce') ); ?>"><?php echo esc_html( current( $lang ) ); ?></a></p>
		</div>

		<?php echo ob_get_clean();
	}

	public function switch_language() {

		check_admin_referer( 'hm_switch_lang_action', 'hm_switch_lang_nonce' );

		$lang = sanitize_text_field( $_GET['hm_lang'] );

		$this->set_visitor_lang( $lang );

		wp_redirect( home_url( $lang . '/' ) );exit;
	}

	public function set_visitor_lang( $lang ) {

		setcookie( 'hm_visitor_lang', $lang, time() + ( WEEK_IN_SECONDS * 2 ), COOKIEPATH, COOKIE_DOMAIN );

	}

	public function get_visitor_lang() {

		return ( isset( $_COOKIE['hm_visitor_lang'] ) ) ? $_COOKIE['hm_visitor_lang'] : '';
	}

	public function scripts() {
		wp_register_script( 'cookies', plugins_url( 'js/cookies.js', __FILE__ ), array(), filemtime( plugin_dir_path( __FILE__ ) . 'js/cookies.js' ) );

		wp_register_script( 'hm-lang-detect', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery', 'cookies' ), filemtime( plugin_dir_path( __FILE__ ) . 'js/script.js' ) );

		wp_localize_script( 'hm-lang-detect', 'hm_lang_data', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'hm_lang_nonce' => wp_create_nonce( 'display_switcher' )
		) );

		wp_enqueue_script( 'hm-lang-detect' );
	}

	public function heartbeat_receive( $response, $data ) {

		if ( 'hm_request_geoip_status' === $data['client'] ) {
			// is geoip data ready?
			if ( ! $this->get_visitor_lang() ) {
				if ( ! is_null( $this->geocoder->get_visitor_geoip_data() ) ) {
					$data['server'] = 'ready';
				}
			}
		}
		return $response;
	}

	public function ajax_render_notice() {

		check_ajax_referer( 'display_switcher' );

		$lang = sanitize_text_field( $_POST['hm_lang'] );
		$this->prompt_language( $lang ); die;
	}
}
HM_Lang_Detect::get_instance();

function hm_language_detector() {

	$hm_lang_detect = HM_Lang_Detect::get_instance();

	if ( ! $hm_lang_detect->get_visitor_lang() ) {
		$hm_lang_detect->detect();
	}
}

function hm_get_visitor_lang() {
	$hm_lang_detect = HM_Lang_Detect::get_instance();
	return $hm_lang_detect->get_visitor_lang();
}
