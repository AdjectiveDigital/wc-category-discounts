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
    }

    public static function apply_discount($price, $product)
    {
        $product_id = $product->get_id();

        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $categories = wp_get_post_terms($parent_id, 'product_cat');
        } else {
            $categories = wp_get_post_terms($product_id, 'product_cat');
        }

        $max_discount = 0;

        foreach ($categories as $category) {
            $discount_type = get_option("wc_category_discounts_discount_type_{$category->term_id}");
            $discount_value = get_option("wc_category_discounts_discount_value_{$category->term_id}");

            if ($discount_type && $discount_value) {
                $discount = 0;

                if ($discount_type === 'percentage') {
                    $discount = $price * ($discount_value / 100);
                } elseif ($discount_type === 'fixed') {
                    $discount = $discount_value;
                }

                if ($discount > $max_discount) {
                    $max_discount = $discount;
                }
            }
        }

        if ($max_discount > 0) {
            $price = $price - $max_discount;
        }

        return $price;
    }

    public static function wc_category_discounts_sale_price_html($price_html, $product) {
        if ($product->is_type('simple')) {
            $regular_price = $product->get_regular_price();
            $discounted_price = WC_Category_Discounts::apply_discount($regular_price, $product);
    
            if ($discounted_price != $regular_price) {
                $price_html = '<del>' . wc_price($regular_price) . '</del> <ins>' . wc_price($discounted_price) . '</ins>';
            }
        }
    
        return $price_html;
    }
    

    public static function apply_discount_simple($price, $product) {
        if ($product->is_type('simple')) {
            return self::apply_discount($price, $product);
        }
        return $price;
    }
    
    public static function apply_discount_variable($price, $product) {
        if ($product->is_type('variation')) {
            return self::apply_discount($price, $product);
        }
        return $price;
    }
    


}

// add_filter('woocommerce_product_variation_get_price', array('WC_Category_Discounts', 'apply_discount_variable'), 10, 2);
add_filter('woocommerce_get_price_html', array('WC_Category_Discounts', 'wc_category_discounts_sale_price_html'), 10, 2);


