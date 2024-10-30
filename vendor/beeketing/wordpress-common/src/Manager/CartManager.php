<?php
/**
 * Created by PhpStorm.
 * User: tungquach
 * Date: 30/08/2017
 * Time: 09:34
 */

namespace BeeketingBCBCommon\Manager;


use BeeketingBCBCommon\Data\Constant;
use BeeketingBCBCommon\Libraries\Helper;

class CartManager
{
    private static $cart_option1s = array();
    private static $cart_images = array();
    private static $pre_loaded = false;
    private static $ignore_cookie_domains = array( 'bestpokercoaching.com' );

    /**
     * CartManager constructor.
     */
    public function __construct()
    {
        add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'filter_add_to_cart_fragment' ), 10, 1 );
    }

    /**
     * Add to cart fragment
     *
     * @since 2.0.0
     * @param $fragments
     * @return mixed
     */
    public function filter_add_to_cart_fragment( $fragments )
    {
        $cookie_key = 'beeketing_cart_fragments_init';
        if ( isset( $_COOKIE[$cookie_key] ) ) {
            $fragments['beeketing_cart'] = $this->get_cart();
        } else {
            setcookie( $cookie_key, 1, 0, '/' );
        }
        return $fragments;
    }

    /**
     * Get cart
     *
     * @param bool $set_cookie
     * @return array
     */
    public function get_cart( $set_cookie = true )
    {
        // Init cart data
        global $woocommerce;
        $cart = $woocommerce->cart;
        $cart_items = $cart->get_cart();
        $cart_token = isset( $_COOKIE[Constant::CART_TOKEN_KEY] ) ? $_COOKIE[Constant::CART_TOKEN_KEY] : '';

        // Get cart cookie uid
        $items_key = '';
        if ( $cart_items ) {
            foreach ( $cart_items as $id => $item ) {
                $items_key .= $id . $item['quantity'];
            }
        }
        $id_key = md5( $cart_token . $cart->subtotal . $cart->cart_total . $cart->total . $items_key );

        // Return cart from cookie
        if ( isset( $_COOKIE[Constant::COOKIE_BEEKETING_CART_DATA] ) ) {
            $cart_data = json_decode( stripslashes( $_COOKIE[Constant::COOKIE_BEEKETING_CART_DATA] ), true );
            if ( isset( $cart_data[$id_key] ) ) {
                return $cart_data[$id_key];
            }
        }

        // Base result
        $item_count = count( $cart_items );
        $result = array(
            'token' => $cart_token,
            'item_count' => $item_count,
            'subtotal_price' => $cart->subtotal,
            'total_price' => (float)$cart->total,
            'items' => array(),
        );

        // Set ignore cookie
        if ( $item_count > 3 ) {
            $set_cookie = false;
        }

        // Traverse cart items
        if ( $cart_items ) {
            // Get option1s
            $variation_ids = array();
            foreach ( $cart_items as $item ) {
                $variation_ids[] = Helper::is_wc3() ? $item['data']->get_id() : $item['data']->variation_id; // Check wc version
            }
            global $wpdb;
            $option1_results = $wpdb->get_results(
                "SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE meta_key = '_beeketing_option1' AND post_id IN (" . implode(',', $variation_ids) . ")"
            );
            foreach ( $option1_results as $option1_result ) {
                self::$cart_option1s[$option1_result->post_id] = $option1_result->meta_value;
            }

            $this->get_cart_images( $variation_ids );

            self::$pre_loaded = true;

            // Format cart items
            foreach ( $cart_items as $id => $item ) {
                $format_item = $this->format_item( $id, $item );
                $result['items'][] = $format_item;

                // Check ignore set cookie
                if ( $set_cookie && isset( $format_item['url'] ) ) {
                    foreach ( self::$ignore_cookie_domains as $domain ) {
                        if ( strpos( $format_item['url'], $domain ) !== false ) {
                            $set_cookie = false;
                            break;
                        }
                    }
                }
            }
        }

        // Set cart cookie
        if ( $set_cookie ) {
            $cookie_data[$id_key] = $result;
            setcookie( Constant::COOKIE_BEEKETING_CART_DATA, json_encode( $cookie_data ), time() + 300, '/' );
        }

        return $result;
    }

    /**
     * Get cart images
     * @param $posts_id
     */
    public function get_cart_images( $posts_id )
    {
        global $wpdb;

        // Get all images id
        $image_result = $wpdb->get_results(
            "
            SELECT post_id, meta_key, meta_value
            FROM $wpdb->postmeta
            WHERE post_id IN (" . implode(',', $posts_id) . ") AND meta_key IN ('_thumbnail_id')
            "
        );

        $images_relation = array();
        $images_id = array();
        foreach ( $image_result as $item ) {
            $images_id[] = $item->meta_value;
            $images_relation[$item->meta_value] = $item->post_id;
        }

        if ( $images_id ) {
            $images_id = array_unique( $images_id );
            $result = $wpdb->get_results(
                "
                SELECT p.ID, p.post_parent, pm.meta_key, pm.meta_value
                FROM $wpdb->postmeta pm JOIN $wpdb->posts p ON pm.post_id = p.ID
                WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_metadata')
                  AND p.post_type = 'attachment'
                  AND p.ID IN (" . implode( ',', $images_id ) . ")
                "
            );

            $images_converted = array();
            foreach ( $result as $item ) {
                $images_converted[$item->ID]['post_parent'] = $item->post_parent;
                $images_converted[$item->ID][$item->meta_key] = $item->meta_value;
            }

            foreach ( $images_converted as $image_id => $image_converted ) {
                // Get medium image
                $file = null;
                if (isset($image_converted['_wp_attachment_metadata'])) {
                    $image = $image_converted['_wp_attachment_metadata'];
                    $image = unserialize($image);
                    $sizes = array('medium', 'shop_catalog', 'thumbnail', 'shop_thumbnail');
                    foreach ($sizes as $size) {
                        if (isset($image['sizes'][$size]['file'])) {
                            $file = $image['sizes'][$size]['file'];
                            $image = $image['file'];
                            $file = preg_replace('/[^\/]+$/', $file, $image);
                            break;
                        }
                    }
                }

                // Fall back to main image
                if (!$file) {
                    $file = $image_converted['_wp_attached_file'];
                }

                // Get upload directory.
                $url = null;
                if ( preg_match_all( '/^http(s)?:\/\//', $file ) == 1 ) { // If image use cdn
                    $url = $file;
                } else { // Local image
                    if ( function_exists('wp_get_upload_dir' ) && ( $uploads = wp_get_upload_dir() ) && false === $uploads['error'] ) {
                        // Check that the upload base exists in the file location.
                        if ( 0 === strpos( $file, $uploads['basedir'] ) ) {
                            // Replace file location with url location.
                            $url = str_replace( $uploads['basedir'], $uploads['baseurl'], $file );
                        } else {
                            // It's a newly-uploaded file, therefore $file is relative to the basedir.
                            $url = $uploads['baseurl'] . "/$file";
                        }
                    }
                }

                // Ignore image
                if ( !$url ) {
                    continue;
                }

                $post_parent = isset( $images_relation[$image_id] ) ? $images_relation[$image_id] : $image_converted['post_parent'];
                self::$cart_images[$post_parent] = $url;
            }
        }
    }

    /**
     * Add cart
     *
     * @param $product_id
     * @param $variant_id
     * @param $quantity
     * @param $params
     * @return array
     */
    public function add_cart( $product_id, $variant_id, $quantity, $params )
    {
        global $woocommerce;
        $woocommerce->session->set_customer_session_cookie( true );
        $cart_item_key = $woocommerce->cart->add_to_cart( $product_id, $quantity, $variant_id, $params );

        $cart = $woocommerce->cart;
        $cart_items = $cart->get_cart();

        // Traverse cart items
        foreach ( $cart_items as $id => $item ) {
            if ( $cart_item_key == $id ) {
                return $this->format_item( $id, $item );
            }
        }

        return array();
    }

    /**
     * Update cart
     *
     * @param $item_id
     * @param $quantity
     * @return bool
     */
    public function update_cart( $item_id, $quantity )
    {
        global $woocommerce;
        $cart_item_key = sanitize_text_field( $item_id );
        if ( $cart_item = $woocommerce->cart->get_cart_item( $cart_item_key ) ) {
            $woocommerce->cart->set_quantity( $cart_item_key, $quantity );
            return true;
        }

        return false;
    }

    /**
     * Format item
     *
     * @param $id
     * @param $item
     * @return array
     */
    private function format_item( $id, $item )
    {
        if ( Helper::is_wc3() ) {
            $product = $item['data'];
            $variation_id = $item['data']->get_id();
            $price = $item['data']->get_price();
            $sku = $item['data']->get_sku();
            $image_id = $item['data']->get_image_id();
            $product_permalink = $product->get_permalink();
            $post_title = $product->get_title();
        } else {
            $product = $item['data']->post;
            $variation_id = $item['data']->variation_id;
            $price = $item['data']->price;
            $sku = $item['data']->sku;
            $image_id = get_post_thumbnail_id( $product->ID );
            $product_permalink = get_permalink( $product );
            $post_title = $product->post_title;
        }

        $title = html_entity_decode( $post_title );
        if ( self::$pre_loaded ) {
            $option1 = isset( self::$cart_option1s[$variation_id] ) ? self::$cart_option1s[$variation_id] : '';
        } else {
            $option1 = get_post_meta( $variation_id, '_beeketing_option1', true );
        }
        $variant_title = $option1 ?: $title;

        if ( isset( self::$cart_images[$variation_id] ) ) {
            $image = self::$cart_images[$variation_id];
        } else {
            $image_data = wp_get_attachment_image_src( $image_id, 'thumbnail' );
            $image = isset( $image_data[0] ) ? $image_data[0] : '';
        }

        return array(
            'id' => $id,
            'variant_id' => (int)($item['variation_id'] ?: $item['product_id']),
            'variant_title' => $variant_title,
            'product_id' => (int)$item['product_id'],
            'title' => $variant_title,
            'product_title' => $title,
            'price' => (float)$price,
            'line_price' => (float)$item['line_total'],
            'quantity' => (int)$item['quantity'],
            'sku' => $sku,
            'handle' => Helper::get_url_handle( $product_permalink ),
            'image' => $image,
            'url' => $product_permalink,
        );
    }
}