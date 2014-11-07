<?php
/*
Plugin Name: Hm-lang-detect
Version: 0.1-alpha
Description: Detect and suggest language
Author: Human Made Limited
Author URI: http://hmn.md
Plugin URI: https://github.com/humanmade/hm-lang-detect
Text Domain: hm-lang-detect
Domain Path: /languages
*/

/**
 * Class HM_Lang_Detect
 */
class HM_Lang_Detect {

	/**
	 * @var
	 */
	static protected $instance;

	/**
	 * @var
	 */
	protected $geocoder;

	/**
	 * @var array|mixed|void
	 */
	protected $supported_languages = array();

	/**
	 * Creates an instance of HM_Lang_Detect
	 */
	protected function __construct() {

		$this->supported_languages = apply_filters( 'hm_supported_languages', array(
			'en' => 'English',
			'fr' => 'Français',
			'de' => 'Deutsch',
		) );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

	}

	/**
	 * Hook into WordPress
	 */
	public function plugins_loaded() {

		add_action( 'admin_post_no_priv_switch_language', array( $this, 'switch_language' ) );
		add_action( 'admin_post_switch_language', array( $this, 'switch_language' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );

		add_action( 'init', array( $this, 'schedule_backdrop_task' ) );

		add_filter( 'body_classes', 'body_classes' );

		add_filter( 'heartbeat_received', array( $this, 'heartbeat_receive' ), 10, 2 );
		add_filter( 'heartbeat_received_no_priv', array( $this, 'heartbeat_receive' ), 10, 2 );

	}

	/**
	 * Schedule the geocoding task.
	 */
	public function schedule_backdrop_task() {

		require_once plugin_dir_path( __FILE__ ) . 'inc/class-geocoder.php';

		require_once plugin_dir_path( __FILE__ ) . 'inc/lib/backdrop/hm-backdrop.php';

		$this->geocoder = new \HMLanguageDetect\GeoCoder( $this->get_ip_address() );

		if ( ! get_option( 'visitor_geoip_' . $this->get_ip_address() ) ) {
			$task = new \HM\Backdrop\Task( array( $this->geocoder, 'get_visitor_geoip_data' ) );
			$task->schedule();
		}

	}

	/**
	 * Add language class to body
	 *
	 * @param $classes
	 *
	 * @return array
	 */
	public function body_classes( $classes ) {

		$classes[] = $this->get_visitor_lang();
		return $classes;
	}

	/**
	 * @return HM_Lang_Detect
	 */
	public static function get_instance() {

		if ( ! ( self::$instance instanceof HM_Lang_Detect ) ) {
			self::$instance = new HM_Lang_Detect();
		}
		return self::$instance;
	}

	/**
	 * @param $lang
	 */
	public function prompt_language( $lang ) {

		switch ( key( $lang ) ) {
			case 'en':
				$notice = 'Based on your location, we suggest viewing this page in ';
				break;
			case 'fr':
				$notice = 'Compte tenu de votre région, nous vous suggérons de visualiser cette page en ';
				break;
			case 'de':
				$notice = 'Basierend auf Ihren Standort, empfehlen wir Ihnen, die Ansicht dieser Seite in ';
				break;
			default:
				$notice = 'Based on your location, we suggest viewing this page in ';
				break;
		}

		// Display a dismissable notice with URL to detected lang page
		ob_start(); ?>
		<div class="hm-lang-switcher">
			<p><?php echo $notice; ?> <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'switch_language', 'hm_lang' => key( $lang ) ), admin_url( 'admin-post.php' ) ), 'hm_switch_lang_action', 'hm_switch_lang_nonce' ) ); ?>"><?php echo esc_html( current( $lang ) ); ?></a> <a href="#" id="dismiss">Dismiss</a></p>
		</div>

		<?php echo ob_get_clean();
	}



	/**
	 * Handle the language switcher interaction
	 */
	public function switch_language() {

		$redirect = home_url();

		if ( wp_verify_nonce( $_GET['hm_switch_lang_nonce'], 'hm_switch_lang_action' ) ) {

			$lang = sanitize_text_field( $_GET['hm_lang'] );

			$this->set_visitor_lang( $lang );

			$redirect = home_url( $lang . '/' );

		}

		wp_redirect( $redirect, 303 );

		exit;

	}

	/**
	 * Set the visitor lang preference
	 * @param $lang
	 */
	public function set_visitor_lang( $lang ) {

		setcookie( 'hm_visitor_lang', $lang, time() + ( WEEK_IN_SECONDS * 2 ), COOKIEPATH, COOKIE_DOMAIN );
		$_COOKIE['hm_visitor_lang'] = $lang;
	}

	/**
	 *
	 */
	public function get_visitor_lang() {

		if ( isset( $_COOKIE['hm_visitor_lang'] ) ) {
			return $_COOKIE['hm_visitor_lang'];
		} elseif ( $geoip_data = get_option( 'visitor_geoip_' . $this->get_ip_address() ) ) {

			global $wp;
			$current_lang = ( 0 < strlen( $wp->request ) ) ? $wp->request : 'en';

			// If we're not on the home page already and not on a lang page, redirect to home
			if ( ! is_404() && ! in_array( $current_lang, array_keys( $this->supported_languages ) ) ) {
				wp_redirect( home_url() );exit;
			}

			if ( $current_lang !== key( $this->get_country_lang() ) ) {
				$this->prompt_language( $this->get_country_lang() );
			}
		}
	}

	/**
	 *
	 */
	public function scripts() {

		wp_register_script( 'hm-lang-detect', plugins_url( 'js/script.js', __FILE__ ), array( 'jquery', 'heartbeat' ), filemtime( plugin_dir_path( __FILE__ ) . 'js/script.js' ) );

		wp_localize_script( 'hm-lang-detect', 'hm_lang_data', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'hm_lang_nonce' => wp_create_nonce( 'display_switcher' )
		) );

		wp_enqueue_script( 'hm-lang-detect' );

	}

	/**
	 *
	 */
	public function ajax_render_notice() {

		check_ajax_referer( 'display_switcher' );

		$lang = sanitize_text_field( $_POST['hm_lang'] );
		$this->prompt_language( $lang ); die;
	}

	/**
	 * @return string
	 */
	public function get_ip_address() {

		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) || false === ( $ip_address = filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ) ) {
			return;
		}

		return apply_filters( 'hm_ip_address', $ip_address );

	}

	/**
	 * @return string
	 */
	public function get_visitor_country() {

		if ( $geoip_data = get_option( 'visitor_geoip_' . $this->get_ip_address() ) ) {
			return $geoip_data->country_name;
		}

		return '';
	}

	/**
	 * @return array|string
	 */
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

	/**
	 * @param $response
	 * @param $data
	 *
	 * @return mixed
	 */
	public function heartbeat_receive( $response, $data ) {

		if ( ! is_admin() ) {

			if ( 'hm_request_geoip_status' === $data['client'] ) {

				// if visitor hasnt already set lang pref and suggesed lang is available
				if ( ! ( isset( $_COOKIE['hm_visitor_lang'] ) ) && $data = get_option( 'visitor_geoip_' . $this->ip_address ) ) {
					$response['server'] = 'ready';
				}
			}
		}

		return $response;
	}
}

HM_Lang_Detect::get_instance();

/**
 *
 */
function hm_get_visitor_lang() {

	$hm_lang_detect = HM_Lang_Detect::get_instance();

	return $hm_lang_detect->get_visitor_lang();
}
