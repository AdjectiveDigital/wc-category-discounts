<?php
class Wc_Category_Discounts
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init();
    }

    private function init()
    {
        // Load text domain for translations.
        load_plugin_textdomain('wc-category-discounts', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Load admin settings if in the admin area.
        if (is_admin()) {
            require_once WC_CATEGORY_DISCOUNTS_PLUGIN_DIR . 'includes/admin/class-wc-category-discounts-admin.php';
            Wc_Category_Discounts_Admin::get_instance();
        }

        // Apply the discount to product prices.
        add_filter('woocommerce_product_get_price', array($this, 'apply_discount'), 10, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'apply_discount'), 10, 2);
        add_filter('woocommerce_get_price_html', array($this, 'wc_category_discounts_sale_price_html'), 10, 2);
        add_filter('woocommerce_variable_sale_price_html', array($this, 'wc_category_discounts_variable_price_html'), 10, 2);
        add_filter('woocommerce_variable_price_html', array($this, 'wc_category_discounts_variable_price_html'), 10, 2);

    }

    public static function apply_discount($price, $product)
    {
        $product_id = $product->get_id();
        $regular_price = (float) $product->get_regular_price();
        $sale_price = $product->get_sale_price();

        if (!empty($sale_price)) {
            $sale_price = (float) $sale_price;
        }

        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $categories = wp_get_post_terms($parent_id, 'product_cat');
        } else {
            $categories = wp_get_post_terms($product_id, 'product_cat');
        }

        $max_discount = 0;

        foreach ($categories as $category) {
            $discount_data = self::get_discount_for_category($category);
            $discount_type = $discount_data['type'];
            $discount_value = $discount_data['value'];
            $apply_to_sale_items = get_option("wc_category_discounts_apply_to_discounted_items_{$category->term_id}") === '1';

            if ($discount_type && $discount_value) {
                $discount = 0;

                if ($discount_type === 'percentage') {
                    $discount = $regular_price * ($discount_value / 100);
                } elseif ($discount_type === 'fixed') {
                    $discount = $discount_value;
                }

                if ($discount > $max_discount) {
                    $max_discount = $discount;
                }
            }

        }

        if ($max_discount > 0) {
            $discounted_price = $regular_price - $max_discount;

            if ($sale_price === '' || $apply_to_sale_items && $discounted_price < $sale_price) {
                $price = $discounted_price;
            } elseif ($sale_price !== '' && !$apply_to_sale_items) {
                $price = $sale_price;
            }
        }

        return $price;
    }



    public static function wc_category_discounts_sale_price_html($price_html, $product)
    {
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $discounted_price = self::apply_discount($regular_price, $product);

        if ($discounted_price != $regular_price && ($sale_price === '' || $discounted_price < $sale_price)) {
            $price_html = '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($discounted_price) . '</ins>';
        }

        return $price_html;
    }


    public static function get_discount_for_category($category)
    {
        $cache_key = "wc_category_discounts_discount_data_{$category->term_id}";
        $discount_data = wp_cache_get($cache_key);

        if (false === $discount_data) {
            $discount_type = get_option("wc_category_discounts_discount_type_{$category->term_id}");
            $discount_value = get_option("wc_category_discounts_discount_value_{$category->term_id}");
            $discount_data = array(
                'type' => $discount_type,
                'value' => $discount_value,
            );
            wp_cache_set($cache_key, $discount_data);
        }

        return $discount_data;
    }

    public static function wc_category_discounts_variable_price_html($price_html, $product)
    {
        $variations = $product->get_available_variations();
        $min_discounted_price = null;
        $max_discounted_price = null;

        foreach ($variations as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            $regular_price = $variation->get_regular_price();
            $discounted_price = self::apply_discount($regular_price, $variation);

            if ($min_discounted_price === null || $discounted_price < $min_discounted_price) {
                $min_discounted_price = $discounted_price;
            }

            if ($max_discounted_price === null || $discounted_price > $max_discounted_price) {
                $max_discounted_price = $discounted_price;
            }
        }

        if ($min_discounted_price !== null && $max_discounted_price !== null) {
            if ($min_discounted_price !== $max_discounted_price) {
                $price_html = wc_format_price_range($min_discounted_price, $max_discounted_price);
            } else {
                $price_html = wc_price($min_discounted_price);
            }
        }

        return $price_html;
    }

}