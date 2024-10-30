<?php
/**
 * Plugin Name: Better Coupon Box
 * Plugin URI: https://beeketing.com
 * Description: A WordPress popup plugin to create 100% customizable & responsive coupon popups for websites and online stores. No coding skills required, you can build custom popups to collect email subscribers & social followers, effortlessly in less than 60 seconds. Features include exit-intent technology, 20+ popup templates, image upload, specific audience/page target, and many more.
 * Version: 1.1.5
 * Author: Beeketing
 * Author URI: https://beeketing.com
 */

use BCB\Api\App;
use BeeketingBCBCommon\Api\BridgeApi;
use BCB\Data\Constant;
use BCB\PageManager\AdminPage;
use BeeketingBCBCommon\Data\Setting;
use BeeketingBCBCommon\Libraries\Helper;
use BeeketingBCBCommon\Libraries\SettingHelper;


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Define plugin constants
define( 'BCB_VERSION', '1.1.5' );
define( 'BCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BCB_PLUGIN_DIRNAME', __FILE__ );

// Require plugin autoload
require_once( BCB_PLUGIN_DIR . 'vendor/autoload.php' );

// Get environment
$env = Helper::get_local_file_contents( BCB_PLUGIN_DIR . 'env' );
$env = trim( $env );

if ( !$env ) {
    throw new Exception( 'Can not get env' );
}

define( 'BCB_ENVIRONMENT', $env );

if ( ! class_exists( 'BetterCouponBox' ) ):

    class BetterCouponBox {
        /**
         * @var AdminPage $admin_page;
         *
         * @since 1.0.0
         */
        private $admin_page;

        /**
         * @var App $api_app
         *
         * @since 1.0.0
         */
        private $api_app;

        /**
         * @var BridgeApi
         *
         * @since 1.0.0
         */
        private $bridge_api;

        /**
         * @var SettingHelper
         *
         * @since 1.0.0
         */
        private $setting_helper;

        /**
         * @var string
         */
        private $api_key;

        /**
         * The single instance of the class
         *
         * @since 1.0.0
         */
        private static $_instance = null;

        /**
         * Get instance
         *
         * @return BetterCouponBox
         * @since 1.0.0
         */
        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * Constructor
         *
         * @since 1.0.0
         */
        public function __construct()
        {
            $this->setting_helper = new SettingHelper();
            $this->setting_helper->set_app_setting_key( Constant::APP_SETTING_KEY );

            $this->api_key = $this->setting_helper->get_settings( Setting::SETTING_API_KEY );

            // Init api app
            $this->api_app = new App( $this->api_key );

            // Bridge api
            $this->bridge_api = new BridgeApi( Constant::APP_SETTING_KEY, $this->api_key );

            // Plugin hooks
            $this->hooks();
        }

        /**
         * Hooks
         *
         * @since 1.0.0
         */
        private function hooks()
        {
            // Initialize plugin parts
            add_action( 'plugins_loaded', array( $this, 'init' ) );

            // Plugin updates
            add_action( 'admin_init', array( $this, 'admin_init' ) );

            if ( is_admin() ) {
                // Plugin activation
                add_action( 'activated_plugin', array( $this, 'plugin_activation' ) );
            }
        }

        /**
         * Init
         *
         * @since 1.0.0
         */
        public function init()
        {
            if ( is_admin() ) {
                $this->admin_page = new AdminPage();
            }
        }

        /**
         * Admin init
         */
        public function admin_init()
        {
            // Check plugin version
            $this->check_version();

            // Listen ajax
            $this->ajax();

            // Add the plugin page Settings and Docs links
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );

            // Screen action
            add_action( 'current_screen', array( $this, 'screen_action' ) );

            // Register plugin deactivation hook
            register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );

            // Enqueue scripts
            add_action( 'admin_enqueue_scripts', array( $this, 'register_script' ) );

            // Enqueue styles
            add_action( 'admin_enqueue_scripts', array( $this, 'register_style' ) );
        }

        /**
         * Screen action
         */
        public function screen_action() {
            $current_screen = get_current_screen();
            if( $current_screen->id == 'toplevel_page_' . Constant::PLUGIN_ADMIN_URL ) {
                $this->api_app->detect_domain_change();
                $this->api_app->check_migrate_after_update();
            }
        }

        /**
         * Enqueue and localize js
         *
         * @since 1.0.0
         * @param $hook
         */
        public function register_script($hook)
        {
            // Load only on plugin page
            if ($hook != 'toplevel_page_' . Constant::PLUGIN_ADMIN_URL) {
                return;
            }

            $app_name = BCB_ENVIRONMENT == 'local' ? 'app' : 'app.min';

            // Enqueue script
            wp_register_script('bk_bcb_script', plugins_url( 'dist/js/' . $app_name . '.js', __FILE__ ) , array( 'jquery' ), null, false);
            wp_enqueue_script('bk_bcb_script');

            $current_user = wp_get_current_user();
            $settings = $this->api_app->get_settings();
            $routers = $this->api_app->get_routers();
            $more_apps = $this->api_app->count_more_apps();

            $beeketing_email = false;
            if ( !$this->api_key ) {
                $beeketing_email = $this->api_app->get_user_email();
            }

            wp_localize_script( 'bk_bcb_script', 'bk_bcb_vars', array(
                'plugin_url' => plugins_url( '/', __FILE__ ),
                'settings' => $settings,
                'routers' => $routers,
                'api_urls' => $this->api_app->get_api_urls(),
                'api_key' => $this->api_key,
                'more_apps_count' => $more_apps,
                'user_display_name' => $current_user->display_name,
                'user_email' => $current_user->user_email,
                'site_url' => site_url(),
                'domain' => Helper::beeketing_get_shop_domain(),
                'beeketing_email' => $beeketing_email,
            ));
        }

        /**
         * Enqueue style
         *
         * @since 1.0.0
         * @param $hook
         */
        public function register_style($hook)
        {
            // Load only on plugin page
            if ($hook != 'toplevel_page_' . Constant::PLUGIN_ADMIN_URL) {
                return;
            }

            wp_register_style( 'bk_bcb_style', plugins_url( 'dist/css/app.css', __FILE__ ), array(), null, 'all' );
            wp_enqueue_style( 'bk_bcb_style' );
        }

        /**
         * Ajax
         *
         * @since 1.0.0
         */
        public function ajax()
        {
            add_action( 'wp_ajax_bcb_verify_account_callback', array( $this, 'verify_account_callback' ) );
            add_action( 'wp_ajax_bcb_app_tracking_callback', array( $this, 'app_tracking_callback' ) );
        }

        /**
         * App tracking callback
         */
        public function app_tracking_callback() {
            if ( !isset( $_POST['params'] ) ) {
                wp_send_json_error();
                wp_die();
            }

            $result = $this->api_app->send_tracking_event( $_POST['params'] );
            if ( $result ) {
                wp_send_json_success();
            } else {
                wp_send_json_error();
            }
            wp_die();
        }

        /**
         * Verify account callback
         *
         * @since 1.0.0
         */
        public function verify_account_callback() {
            $api_key = $this->api_app->register_shop();

            wp_send_json_success( array(
                'api_key' => $api_key,
            ) );
            wp_die();
        }

        /**
         * Plugin links
         *
         * @param $links
         * @return array
         * @since 1.0.0
         */
        public function plugin_links( $links )
        {
            $more_links = array();
            $more_links['settings'] = '<a href="' . admin_url( 'admin.php?page=' . Constant::PLUGIN_ADMIN_URL ) . '">' . __( 'Settings', 'beeketing' ) . '</a>';

            return array_merge( $more_links, $links );
        }

        /**
         * Check version
         *
         * @since 1.0.0
         */
        public function check_version()
        {
            // Update version number if its not the same
            if ( BCB_VERSION != $this->setting_helper->get_settings( Setting::SETTING_PLUGIN_VERSION ) ) {
                $this->setting_helper->update_settings( Setting::SETTING_PLUGIN_VERSION, BCB_VERSION );
            }
        }

        /**
         * Plugin activation
         *
         * @param $plugin
         * @since 1.0.0
         */
        public function plugin_activation( $plugin )
        {
            if ( $plugin == plugin_basename( __FILE__ ) ) {
                // Send tracking
                $event = \BCB\Data\Event::PLUGIN_FIRST_ACTIVATE;
                if ( $this->api_key ) {
                    $event = \BCB\Data\Event::PLUGIN_ACTIVATION;
                }
                $this->api_app->send_tracking_event( array(
                    'event' => $event,
                    'plugin' => 'coupon_box',
                ) );

                exit( wp_redirect( admin_url( 'admin.php?page=' . Constant::PLUGIN_ADMIN_URL ) ) );
            }
        }

        /**
         * Plugin deactivation
         */
        public function plugin_deactivation()
        {
            // Send tracking
            $this->api_app->send_tracking_event( array(
                'event' => \BCB\Data\Event::PLUGIN_DEACTIVATION,
                'plugin' => 'coupon_box',
            ) );
        }

        /**
         * Plugin uninstall
         *
         * @since 1.0.0
         */
        public function plugin_uninstall()
        {
            // Send tracking
            $this->api_app->send_tracking_event( array(
                'event' => \BCB\Data\Event::PLUGIN_UNINSTALL,
                'plugin' => 'coupon_box',
            ) );

            $this->api_app->uninstall_app();
        }
    }

    // Run plugin
    new BetterCouponBox();

endif;