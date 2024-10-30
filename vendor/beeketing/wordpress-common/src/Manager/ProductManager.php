<?php
/**
 * Created by PhpStorm.
 * User: tungquach
 * Date: 31/03/2017
 * Time: 13:40
 */

namespace BeeketingBCBCommon\Manager;


use BeeketingBCBCommon\Data\Api;
use BeeketingBCBCommon\Libraries\Helper;
use BeeketingBCBCommon\Libraries\QueryHelper;

class ProductManager
{
    private $image_manager;
    private $variant_manager;
    private $wc_products = array();
    private $pre_populate_data = false;
    private $wc_product_images = array();
    private $wc_image_by_products = array();
    private $wc_product_tags = array();
    private $permalinks = array();

    public function __construct()
    {
        $this->image_manager = new ImageManager();
        $this->variant_manager = new VariantManager();
    }

    /**
     * Count products
     *
     * @return mixed
     */
    public function get_products_count()
    {
        global $wpdb;
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(ID)
                FROM $wpdb->posts
                WHERE post_type = %s
                  AND post_status = %s
                  AND post_password = %s
                  AND ID NOT IN ( " . QueryHelper::get_exclude_products_id() . " )
                ",
                "product",
                "publish",
                null
            )
        );

        return $count;
    }

    /**
     * Get product by id
     *
     * @param $id
     * @return array
     */
    public function get_product_by_id( $id )
    {
        global $wpdb;
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "
                SELECT *
                FROM $wpdb->posts
                WHERE post_type = %s
                  AND post_status = %s
                  AND post_password = %s
                  AND ID = %d
                ",
                "product",
                "publish",
                null,
                $id
            )
        );

        $product = $result ? $this->format_product( $result ) : array();

        return $product;
    }

    /**
     * Get products
     *
     * @param $title
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function get_products( $title = null, $page = Api::PAGE, $limit = Api::ITEM_PER_PAGE )
    {
        $offset = ( $page - 1 ) * $limit;

        global $wpdb;
        $result = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT *
                FROM $wpdb->posts
                WHERE post_type = %s
                  AND post_status = %s
                  AND post_password = %s
                  AND post_title LIKE %s
                  AND ID NOT IN ( " . QueryHelper::get_exclude_products_id() . " )
                LIMIT %d
                OFFSET %d
                ",
                "product",
                "publish",
                null,
                "%" . $title . "%",
                $limit,
                $offset
            )
        );

        $products = $ids = array();
        // Traverse all result
        foreach ( $result as $item ) {
            $ids[] = $item->ID;
        }

        // Fill wc products
        $this->get_wc_products( $ids );

        // Fill product images
        $this->wc_product_images = $this->get_product_images( $ids );

        // Fill product tags
        $this->get_product_tags( $ids );

        // Fill option1
        $this->get_option1s();

        // Fill permalinks
        $this->permalinks = get_option( 'woocommerce_permalinks' );

        // Mark pre populate data
        $this->pre_populate_data = true;

        // Traverse all result
        foreach ( $result as $item ) {
            $products[] = $this->format_product( $item );
        }

        return $products;
    }

    /**
     * Get options1s
     */
    private function get_option1s()
    {
        global $wpdb;
        $results = $wpdb->get_results(
            "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_beeketing_option1'"
        );

        $option1s = array();
        foreach ( $results as $result ) {
            $option1s[$result->post_id] = $result->meta_value;
        }
        $this->variant_manager->set_option1s( $option1s );
    }

    /**
     * Get product tags
     *
     * @param $posts_id
     */
    private function get_product_tags( $posts_id )
    {
        global $wpdb;

        // Get all images id
        $tag_result = $wpdb->get_results(
            "
            SELECT t.name, tr.object_id
            FROM $wpdb->terms t JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id
            JOIN $wpdb->term_relationships tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tr.object_id IN (" . implode( ',', $posts_id ) . ") AND tt.taxonomy = 'product_tag'
            "
        );

        foreach ( $tag_result as $item ) {
            $this->wc_product_tags[$item->object_id][] = $item->name;
        }
    }

    /**
     * Get product images
     *
     * @param $posts_id
     * @return array
     */
    private function get_product_images( $posts_id )
    {
        global $wpdb;

        // Get all images id
        $image_result = $wpdb->get_results(
            "
            SELECT post_id, meta_key, meta_value
            FROM $wpdb->postmeta
            WHERE post_id IN (" . implode(',', $posts_id) . ") AND meta_key IN ('_thumbnail_id', '_product_image_gallery')
            "
        );

        $images_relation = array();
        $images_id = array();
        foreach ( $image_result as $item ) {
            if ( $item->meta_key == '_product_image_gallery' ) {
                $images_id_list = explode( ',', $item->meta_value );
                $this->wc_image_by_products[$item->post_id][1] = $images_id_list;
                foreach ( $images_id_list as $image_id ) {
                    if ( $image_id ) {
                        $images_id[] = $image_id;
                        $images_relation[$image_id] = $item->post_id;
                    }
                }
            } else {
                $this->wc_image_by_products[$item->post_id][0] = $item->meta_value;
                $images_id[] = $item->meta_value;
                $images_relation[$item->meta_value] = $item->post_id;
            }
        }

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

        $data = array();
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
                if ( function_exists( 'wp_get_upload_dir' ) && ($uploads = wp_get_upload_dir()) && false === $uploads['error'] ) {
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
            $data[$post_parent][] = array(
                'id' => (int)$image_id,
                'src' => $url,
            );
        }

        return $data;
    }

    /**
     * Get wc products
     *
     * @param $posts_id
     */
    private function get_wc_products( $posts_id )
    {
        if ( !Helper::is_wc3() ) {
            return;
        }

        $wc_products = wc_get_products( array(
            'include' => $posts_id,
            'limit' => -1,
        ) );

        $parent_product_ids = array();
        foreach ( $wc_products as $wc_product ) {
            $product_id = $wc_product->get_id();
            $this->wc_products[$product_id] = $wc_product;

            if ( !$wc_product->is_type( 'simple' ) ) {
                $parent_product_ids[] = $wc_product->get_id();
            }
        }

        if ( $parent_product_ids ) {
            $args = array(
                'post_parent__in' => $parent_product_ids,
                'post_type' => 'product_variation',
                'orderby' => 'menu_order',
                'order' => 'ASC',
                'fields' => 'ids',
                'post_status' => 'publish',
                'numberposts' => -1
            );
            $variant_ids = get_posts( $args );

            if ( $variant_ids ) {
                $wc_variants = wc_get_products( array(
                    'type' => 'variation',
                    'orderby' => 'menu_order',
                    'order' => 'ASC',
                    'include' => $variant_ids,
                    'limit' => -1,
                ) );

                $wc_products_variants = array();
                foreach ( $wc_variants as $wc_variant ) {
                    $wc_products_variants[$wc_variant->get_parent_id()][] = $wc_variant;
                }

                $this->variant_manager->set_wc_products_variants( $wc_products_variants );

                $this->variant_manager->set_wc_products_variants_images( $this->get_product_images( $variant_ids ) );
            }
        }
    }

    /**
     * Format product
     *
     * @param $product
     * @return array
     */
    private function format_product( $product )
    {
        $product_base = isset( $this->permalinks['product_base'] ) && $this->permalinks['product_base'] ?
            ltrim($this->permalinks['product_base'], '/') : 'product';

        $product_id = $product->ID;
        $post = $product;
        $product = isset( $this->wc_products[$product_id] ) ? $this->wc_products[$product_id] : wc_get_product( $product_id );
        if ( $this->pre_populate_data ) {
            $tags = isset( $this->wc_product_tags[$product_id] ) ? $this->wc_product_tags[$product_id] : array();
        } else {
            $tags = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
        }

        if ( isset( $this->wc_product_images[$product_id] ) && $this->wc_product_images[$product_id] ) {
            $images = $this->wc_product_images[$product_id];

            // Sorting image
            $images_in_order = array();
            if ( isset($this->wc_image_by_products[$product_id]) ) {
                $images_list = $this->wc_image_by_products[$product_id];
                sort( $images_list );
                foreach ( $images_list as $image ) {
                    if ( is_array( $image ) ) {
                        foreach ( $image as $img ) {
                            $images_in_order[] = $img;
                        }
                    } else {
                        $images_in_order[] = $image;
                    }
                }
            }

            if ( $images_in_order ) {
                usort( $images, function ( $img_a, $img_b ) use ( $images_in_order ) {
                    if ( isset( $img_a['id'], $img_b['id'] ) ) {
                        $a_order = array_search($img_a['id'], $images_in_order);
                        $b_order = array_search($img_b['id'], $images_in_order);

                        return $a_order > $b_order ? 1 : -1;
                    }

                    return 0;
                } );
            }
        } else {
            $images = $this->image_manager->get_product_images_by_product( $product );
        }

        // Get variants
        $variants = $this->variant_manager->get_variants_by_product( $product );
        if ( preg_match('/%.*%/', $product_base) ) {
            $product_handle = ltrim( str_replace( get_site_url(), '', $product->get_permalink() ), '/' );
        } else {
            $product_base = preg_replace('/%.*%/', '', $product_base);
            $product_base = rtrim($product_base, '/');
            $product_handle = $product_base . '/' . $post->post_name;
        }

        return array(
            'id' => $product_id,
            'published_at' => $post->post_date_gmt,
            'handle' => $product_handle,
            'title' => $post->post_title,
            'vendor' => '',
            'tags' => $tags ? implode( ', ', $tags ) : '',
            'description' => $post->post_excerpt,
            'images' => $images,
            'image' => isset($images[0]['src']) ? $images[0]['src'] : '',
            'variants' => $variants,
        );
    }

    /**
     * Update product
     *
     * @param $id
     * @param $content
     * @return array
     */
    public function update_product( $id, $content )
    {
        $tags = isset( $content['tags'] ) ? explode(',', $content['tags']) : array();

        $product_tags = array();
        foreach ( $tags as $tag ) {
            if ( $tag ) {
                $args = array(
                    'hide_empty' => false,
                    'fields' => 'ids',
                    'name' => $tag
                );

                $tag_ids = get_terms( 'product_tag', $args );

                if ( !$tag_ids ) {
                    $defaults = array(
                        'name' => $tag,
                        'slug' => sanitize_title( $tag ),
                    );

                    $insert = wp_insert_term( $defaults['name'], 'product_tag', $defaults );
                    $id = $insert['term_id'];
                    $product_tags[] = $id;

                } else {
                    $product_tags = array_merge( $product_tags, $tag_ids );

                }
            }
        }

        // Update tag
        if ( $product_tags ) {
            wp_set_object_terms($id, $product_tags, 'product_tag');
        }

        return $this->get_product_by_id( $id );
    }
}