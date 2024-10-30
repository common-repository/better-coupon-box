<?php
/**
 * Created by PhpStorm.
 * User: tungquach
 * Date: 31/03/2017
 * Time: 19:40
 */

namespace BeeketingBCBCommon\Manager;


use BeeketingBCBCommon\Data\Api;

class CustomerManager
{
    private static $pre_loaded = false;
    private static $wc_user_order_counts = array();
    private static $wc_user_total_spents = array();

    /**
     * Get customers count
     *
     * @return int
     */
    public function get_customers_count()
    {
        $result = count_users();
        $count = 0;
        foreach( $result['avail_roles'] as $role => $total ) {
            if ( $role == 'customer' ) {
                $count = $total;
            }
        }

        return $count;
    }

    /**
     * Get customer by id
     *
     * @param $id
     * @return array
     */
    public function get_customer_by_id( $id )
    {
        $user = get_user_by( 'id', $id );

        if ( $user ) {
            return $this->format_user( $user );
        }

        return array();
    }

    /**
     * Get customers
     *
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function get_customers( $page = Api::PAGE, $limit = Api::ITEM_PER_PAGE )
    {
        $offset = ( $page - 1 ) * $limit;

        $args = array(
            'role' => 'customer',
            'offset' => $offset,
            'number' => $limit,
        );

        $result = get_users( $args );

        global $wpdb;
        // Get order count
        $count_results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = '_order_count'"
        );
        foreach ( $count_results as $count_result ) {
            self::$wc_user_order_counts[$count_result->user_id] = $count_result->meta_value;
        }

        // Get total spent
        $spent_results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = '_money_spent'"
        );
        foreach ( $spent_results as $spent_result ) {
            self::$wc_user_total_spents[$spent_result->user_id] = $spent_result->meta_value;
        }

        // Mark pre loaded data
        self::$pre_loaded = true;

        $customers = array();
        foreach ( $result as $item ) {
            $customers[] = $this->format_user( $item );
        }

        return $customers;
    }

    /**
     * Get customers by ids
     *
     * @param array $ids
     * @return array
     */
    public function get_customers_by_ids( $ids = array() )
    {
        if ( !$ids ) {
            return array();
        }

        $args = array(
            'role' => 'customer',
            'include' => $ids,
            'number' => -1,
        );

        $result = get_users( $args );

        global $wpdb;
        // Get order count
        $count_results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = '_order_count'"
        );
        foreach ( $count_results as $count_result ) {
            self::$wc_user_order_counts[$count_result->user_id] = $count_result->meta_value;
        }

        // Get total spent
        $spent_results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM $wpdb->usermeta WHERE meta_key = '_money_spent'"
        );
        foreach ( $spent_results as $spent_result ) {
            self::$wc_user_total_spents[$spent_result->user_id] = $spent_result->meta_value;
        }

        // Mark pre loaded data
        self::$pre_loaded = true;

        $customers = array();
        foreach ( $result as $item ) {
            $customers[$item->ID] = $this->format_user( $item );
        }

        return $customers;
    }

    /**
     * Format user
     *
     * @param $user
     * @return array
     */
    private function format_user( $user )
    {
        if ( self::$pre_loaded ) {
            $order_count = isset( self::$wc_user_order_counts[$user->ID] ) ? self::$wc_user_order_counts[$user->ID] : 0;
            $total_spent = isset( self::$wc_user_total_spents[$user->ID] ) ? self::$wc_user_total_spents[$user->ID] : 0;
        } else {
            $order_count = wc_get_customer_order_count( $user->ID );
            $total_spent = wc_get_customer_total_spent( $user->ID );
        }

        return array(
            'id'  => $user->ID,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'accepts_marketing' => true,
            'verified_email' => !$user->user_activation_key,
            'signed_up_at' => $user->user_registered,
            'address1' => $user->billing_address_1,
            'address2' => $user->billing_address_2,
            'city' => $user->billing_city,
            'company' => $user->billing_company,
            'province' => $user->billing_state,
            'zip' => $user->billing_postcode,
            'country' => $user->billing_country,
            'country_code' => $user->billing_country,
            'orders_count' => (int) $order_count,
            'total_spent' => wc_format_decimal( $total_spent, 2 ),
        );
    }
}