<?php
/**
 * Created by PhpStorm.
 * User: tungquach
 * Date: 30/03/2017
 * Time: 19:00
 */

namespace BeeketingBCBCommon\Manager;


use BeeketingBCBCommon\Data\Api;
use BeeketingBCBCommon\Libraries\Helper;

class CollectionManager
{
    private $collection_images = array();
    private $permalinks = array();

    /**
     * Count collections
     *
     * @return mixed
     */
    public function get_collections_count()
    {
        $result = wp_count_terms( 'product_cat', array(
            'hide_empty' => true,
        ) );

        return $result;
    }

    /**
     * Get collection by id
     *
     * @param $id
     * @return array
     */
    public function get_collection_by_id( $id )
    {
        $term = get_term( $id, 'product_cat' );

        // Traverse all terms
        if ( !is_wp_error( $term ) && $term ) {
            $this->permalinks = get_option( 'woocommerce_permalinks' );
            return $this->format_collection( $term );
        }

        return array();
    }

    /**
     * Get collections
     *
     * @param $title
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function get_collections( $title, $page = Api::PAGE, $limit = Api::ITEM_PER_PAGE )
    {
        $args = array(
            'hide_empty' => true,
        );

        // Limit
        if ( $limit ) {
            $args['number'] = $limit;
        }

        // Offset
        if ( $page ) {
            $page = $page - 1;
            $args['offset'] = $page * $limit;
        }

        // Title
        if ( $title ) {
            $args['search'] = $title;
        }

        $result = array();
        // Get terms
        $terms = get_terms( 'product_cat', $args );

        // Traverse all terms
        if ( !is_wp_error( $result ) ) {
            $terms_id = array();
            foreach ( $terms as $term ) {
                $terms_id[] = $term->term_id;
            }

            // Fill collection images
            $this->get_collection_images( $terms_id );

            $this->permalinks = get_option( 'woocommerce_permalinks' );

            foreach ( $terms as $term ) {
                $result[] = $this->format_collection( $term );
            }
        }

        return $result;
    }

    /**
     * Get collection images
     *
     * @param $collections_id
     */
    private function get_collection_images( $collections_id )
    {
        global $wpdb;

        // Get all images id
        $images_result = $wpdb->get_results(
            "
            SELECT meta_value, term_id
            FROM $wpdb->termmeta
            WHERE term_id IN (" . implode( ',', $collections_id ) . ") AND meta_key = 'thumbnail_id'
            "
        );

        $images_id = $term_images = array();
        foreach ( $images_result as $item ) {
            $images_id[] = $item->meta_value;
            $term_images[$item->meta_value] = $item->term_id;
        }

        $result = $wpdb->get_results(
            "
            SELECT p.ID, pm.meta_key, pm.meta_value
            FROM $wpdb->postmeta pm JOIN $wpdb->posts p ON pm.post_id = p.ID
            WHERE pm.meta_key IN ('_wp_attached_file', '_wp_attachment_metadata')
              AND p.post_type = 'attachment'
              AND p.ID IN (" . implode( ',', $images_id ) . ")
            "
        );

        $images_converted = array();
        foreach ( $result as $item ) {
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
            if ( function_exists( 'wp_get_upload_dir' ) && ( $uploads = wp_get_upload_dir() ) && false === $uploads['error'] ) {
                // Check that the upload base exists in the file location.
                if ( 0 === strpos( $file, $uploads['basedir'] ) ) {
                    // Replace file location with url location.
                    $url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
                } else {
                    // It's a newly-uploaded file, therefore $file is relative to the basedir.
                    $url = $uploads['baseurl'] . "/$file";
                }
            }

            // Ignore image
            if ( !$url ) {
                continue;
            }

            if ( isset( $term_images[$image_id] ) ) {
                $this->collection_images[$term_images[$image_id]] = $url;
            }
        }
    }

    /**
     * Format collection
     *
     * @param $collection
     * @return array
     */
    private function format_collection( $collection )
    {
        $category_base = isset( $this->permalinks['category_base'] ) && $this->permalinks['category_base'] ?
            ltrim($this->permalinks['category_base'], '/') : 'product-category';

        // Get category image
        $image = isset( $this->collection_images[$collection->term_id] ) ?
            $this->collection_images[$collection->term_id] : '';
        if (!$image && $image_id = get_woocommerce_term_meta( $collection->term_id, 'thumbnail_id') ) {
            $image = wp_get_attachment_url( $image_id );
        }

        return array(
            'id' => $collection->term_id,
            'title' => $collection->name,
            'handle' => $category_base . '/' . $collection->slug,
            'published_at' => Helper::format_date( new \DateTime() ),
            'image_url' => $image,
        );
    }
}