<?php
/**
 * App api
 *
 * @since      1.0.0
 * @author     Beeketing
 *
 */

namespace BCB\Api;


use BCB\Data\Constant;
use BeeketingBCBCommon\Api\CommonApi;
use BeeketingBCBCommon\Data\Setting;
use BeeketingBCBCommon\Libraries\Helper;
use BeeketingBCBCommon\Libraries\SettingHelper;

class App extends CommonApi
{
    private $api_key;

    /**
     * App constructor.
     *
     * @param $api_key
     */
    public function __construct( $api_key )
    {
        $this->api_key = $api_key;
        $setting_helper = new SettingHelper();
        $setting_helper->set_app_setting_key( Constant::APP_SETTING_KEY );
        $setting_helper->set_plugin_version( BCB_VERSION );

        parent::__construct(
            $setting_helper,
            BCB_PATH,
            BCB_API,
            $api_key,
            Constant::APP_CODE,
            Constant::PLUGIN_ADMIN_URL
        );
    }

    /**
     * Get settings
     *
     * @return array
     */
    public function get_settings()
    {
        $result = $this->get( 'coupon_box/settings' );

        if ( isset( $result['settings'] ) && !isset( $result['errors'] ) ) {
            return $result['settings'];
        }

        return array();
    }

    /**
     * Get routers
     *
     * @return array
     */
    public function get_routers()
    {
        $result = $this->get( 'coupon_box/routers' );

        if ( $result && !isset( $result['errors'] ) ) {
            foreach ( $result as &$item ) {
                if ( strpos( $item, 'http' ) === false ) {
                    $end_point = BCB_PATH;
                    if ( BCB_ENVIRONMENT == 'local' ) {
                        $end_point = str_replace( '/app_dev.php', '', $end_point );
                    }
                    $item = $end_point . $item;
                }
            }

            return $result;
        }

        return array();
    }

    /**
     * Get api urls
     *
     * @return array
     */
    public function get_api_urls()
    {
        return array_merge( array(
            'setting_update' => $this->get_url( 'coupon_box/settings/{id}' ),
            'setting_add' => $this->get_url( 'coupon_box/settings' ),
            'add_coupon_url' => $this->get_add_coupon_url(),
        ), parent::get_api_urls() );
    }

    /**
     * Get add coupon url
     * @return null
     */
    private function get_add_coupon_url()
    {
        if ( Helper::is_woocommerce_active() ) {
            return admin_url( 'post-new.php?post_type=shop_coupon' );
        }

        return null;
    }

    /**
     * Check migrate after update
     */
    public function check_migrate_after_update()
    {
        $old_settings = get_option( 'bcb_settings' );

        // If detect old setting
        if ( $old_settings ) {
            // Clean old settings.
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    'DELETE FROM ' . $wpdb->options . ' WHERE option_name = %s',
                    'bcb_settings'
                )
            );

            // Update settings
            $access_token = $this->setting_helper->get_settings( Setting::SETTING_ACCESS_TOKEN );
            update_option( Constant::APP_SETTING_KEY, $old_settings );
            if ( $access_token ) {
                $this->setting_helper->update_settings(Setting::SETTING_ACCESS_TOKEN, $access_token);
            }

            // Redirect to plugin page
            exit( wp_redirect( admin_url( 'admin.php?page=' . Constant::PLUGIN_ADMIN_URL ) ) );
        }
    }
}