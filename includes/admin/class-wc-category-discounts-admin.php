<?php
class Wc_Category_Discounts_Admin
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->init();

        // Add AJAX actions for loading and saving discounts.
        add_action('wp_ajax_wc_category_discounts_load_discounts', array($this, 'ajax_load_discounts'));
        add_action('wp_ajax_wc_category_discounts_save_discount', array($this, 'ajax_save_discount'));
        add_action('wp_ajax_wc_category_discounts_delete_discount', array($this, 'ajax_delete_discount'));
    }

    private function init()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_plugin_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Adjective Digital - Woo Category Discounts', 'wc-category-discounts'),
            __('Category Discounts', 'wc-category-discounts'),
            'manage_options',
            'wc-category-discounts',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page()
    {
        // Check if the user has the required capability.
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $categories = get_terms(
            array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            )
        );

        $categories_options = '';

        foreach ($categories as $category) {
            $categories_options .= sprintf('<option value="%d">%s</option>', $category->term_id, esc_html($category->name));
        }

        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>

            <h2>
                <?php _e('Add Category Discount', 'wc-category-discounts'); ?>
            </h2>
            <form id="add-category-discount-form">
                <input type="hidden" id="edit-category-id" name="edit_category_id" value="">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th>
                                <?php _e('Category', 'wc-category-discounts'); ?>
                            </th>
                            <td>
                                <select id="category-id" required>
                                    <option value="">
                                        <?php _e('Select a category', 'wc-category-discounts'); ?>
                                    </option>
                                    <?php echo $categories_options; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('Discount Type', 'wc-category-discounts'); ?>
                            </th>
                            <td>
                                <select id="discount-type" required>
                                    <option value="">
                                        <?php _e('Select a discount type', 'wc-category-discounts'); ?>
                                    </option>
                                    <option value="percentage">
                                        <?php _e('Percentage', 'wc-category-discounts'); ?>
                                    </option>
                                    <option value="fixed">
                                        <?php _e('Fixed Amount', 'wc-category-discounts'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php _e('Discount Value', 'wc-category-discounts'); ?>
                            </th>
                            <td>
                                <input type="number" id="discount-value" step="0.01" min="0" required />
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button type="submit" class="button button-primary">
                    <?php _e('Add Discount', 'wc-category-discounts'); ?>
                </button>
            </form>

            <h2>
                <?php _e('Existing Category Discounts', 'wc-category-discounts'); ?>
            </h2>
            <table class="wp-list-table widefat fixed striped table-view-list" id="category-discounts-table">
                <thead>
                    <tr>
                        <th>
                            <?php _e('Category', 'wc-category-discounts'); ?>
                        </th>
                        <th>
                            <?php _e('Discount Type', 'wc-category-discounts'); ?>
                        </th>
                        <th>
                            <?php _e('Discount Value', 'wc-category-discounts'); ?>
                        </th>
                        <th>
                            <?php _e('Apply to Items with a Sale Price', 'wc-category-discounts'); ?>
                        </th>
                        <th>
                            <?php _e('Actions', 'wc-category-discounts'); ?>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <!-- The table rows will be dynamically generated using JavaScript. -->
                </tbody>
            </table>
        </div>

        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                // Variables for the form elements.
                var addCategoryDiscountForm = document.getElementById('add-category-discount-form');
                var categoryIdSelect = document.getElementById('category-id');
                var discountTypeSelect = document.getElementById('discount-type');
                var discountValueInput = document.getElementById('discount-value');
                var categoryDiscountsTable = document.getElementById('category-discounts-table');

                // Load existing discounts.
                loadExistingDiscounts();

                // Add submit event listener to the form.
                addCategoryDiscountForm.addEventListener('submit', function (event) {
                    event.preventDefault();

                    var editCategoryId = document.getElementById('edit-category-id');
                    var isEditing = editCategoryId.value !== '';

                    var categoryId = isEditing ? editCategoryId.value : categoryIdSelect.value;
                    var discountType = discountTypeSelect.value;
                    var discountValue = discountValueInput.value;

                    if (!categoryId || !discountType || !discountValue) {
                        alert('Please fill in all fields.');
                        return;
                    }

                    var formData = new FormData();
                    formData.append('action', 'wc_category_discounts_save_discount');
                    formData.append('_wpnonce', '<?php echo wp_create_nonce('wc-category-discounts-save-discount'); ?>');
                    formData.append('category_id', categoryId);
                    formData.append('discount_type', discountType);
                    formData.append('discount_value', discountValue);

                    if (isEditing) {
                        formData.append('edit_category_id', editCategoryId.value);
                    }

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(function (response) {
                            if (response.success) {
                                if (isEditing) {
                                    // Update the row content.
                                    var row = document.querySelector('tr[data-category-id="' + editCategoryId.value + '"]');
                                    row.querySelector('td:nth-child(1)').innerText = categoryIdSelect.options[categoryIdSelect.selectedIndex].text;
                                    row.querySelector('td:nth-child(2)').innerText = discountType;
                                    row.querySelector('td:nth-child(3)').innerText = discountValue;

                                    // Reset the edit category ID.
                                    editCategoryId.value = '';
                                } else {
                                    // Add the new discount to the table.
                                    addDiscountToTable(categoryId, discountType, discountValue);
                                }
                                // Reset the form.
                                addCategoryDiscountForm.reset();
                            } else {
                                alert(response.data.message);
                            }
                        });
                });

                function loadExistingDiscounts() {
                    var formData = new FormData();
                    formData.append('action', 'wc_category_discounts_load_discounts');
                    formData.append('_wpnonce', '<?php echo wp_create_nonce('wc-category-discounts-load-discounts'); ?>');

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(function (discounts) {
                            discounts.forEach(function (discount) {
                                addDiscountToTable(discount.category_id, discount.discount_type, discount.discount_value);
                            });
                        });
                }

                function addDiscountToTable(categoryId, discountType, discountValue, applyToDiscountedItems) {
                    var category = document.querySelector('option[value="' + categoryId + '"]').innerText;

                    var row = document.createElement('tr');
                    row.innerHTML = `
                                <td>${category}</td>
                                <td>
                                    <select class="row-discount-type">
                                        <option value="percentage" ${discountType === 'percentage' ? 'selected' : ''}>Percentage</option>
                                        <option value="fixed" ${discountType === 'fixed' ? 'selected' : ''}>Fixed</option>
                                    </select>
                                </td>
                                <td><input type="number" class="row-discount-value" value="${discountValue}" min="0" step="0.01"></td>
                                <td><input type="checkbox" class="row-apply-to-discounted-items" ${applyToDiscountedItems ? 'checked' : ''}></td>
                                <td>
                                    <button class="button button-small"><?php _e('Update', 'wc-category-discounts'); ?></button>
                                    <button class="button button-small"><?php _e('Delete', 'wc-category-discounts'); ?></button>
                                </td>
                            `;

                    // Add click event listeners for the Update and Delete buttons.
                    row.querySelector('button:nth-child(1)').addEventListener('click', function () {
                        updateDiscount(categoryId, row);
                    });
                    row.querySelector('button:nth-child(2)').addEventListener('click', function () {
                        deleteDiscount(categoryId, row);
                    });

                    categoryDiscountsTable.querySelector('tbody').appendChild(row);
                }



                function editDiscount(categoryId) {
                    var discountType = document.querySelector('input[name="wc_category_discounts_discount_type_' + categoryId + '"]:checked').value;
                    var discountValue = document.querySelector('input[name="wc_category_discounts_discount_value_' + categoryId + '"]').value;

                    categoryIdSelect.value = categoryId;
                    discountTypeSelect.value = discountType;
                    discountValueInput.value = discountValue;

                    // Scroll to the form.
                    window.scrollTo({ top: 0, behavior: 'smooth' });

                    // Set the value of the hidden input field for the edited category ID.
                    document.getElementById('edit-category-id').value = categoryId;
                }

                function updateDiscount(categoryId, row) {
                    var discountType = row.querySelector('.row-discount-type').value;
                    var discountValue = row.querySelector('.row-discount-value').value;
                    var applyToDiscountedItems = row.querySelector('.row-apply-to-discounted-items').checked;

                    if (!discountType || !discountValue) {
                        alert('Please fill in all fields.');
                        return;
                    }

                    var formData = new FormData();
                    formData.append('action', 'wc_category_discounts_save_discount');
                    formData.append('_wpnonce', '<?php echo wp_create_nonce('wc-category-discounts-save-discount'); ?>');
                    formData.append('category_id', categoryId);
                    formData.append('discount_type', discountType);
                    formData.append('discount_value', discountValue);
                    formData.append('apply_to_discounted_items', applyToDiscountedItems ? '1' : '0');

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(function (response) {
                            if (response.success) {
                                alert('Discount updated successfully.');
                            } else {
                                alert(response.data.message);
                            }
                        });
                }




                function deleteDiscount(categoryId, row) {
                    if (confirm('Are you sure you want to delete this discount?')) {
                        // Delete the discount using AJAX.
                        var formData = new FormData();
                        formData.append('action', 'wc_category_discounts_delete_discount');
                        formData.append('_wpnonce', '<?php echo wp_create_nonce('wc-category-discounts-delete-discount'); ?>');
                        formData.append('category_id', categoryId);

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(function (response) {
                                if (response.success) {
                                    // Remove the row from the table.
                                    row.remove();
                                } else {
                                    alert(response.data.message);
                                }
                            });
                    }
                }


            });

        </script>
        <?php
    }


    public function register_settings()
    {
        register_setting('wc-category-discounts-settings-group', 'wc_category_discounts_options', array($this, 'sanitize_options'));

        $categories = get_terms(
            array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            )
        );

        foreach ($categories as $category) {
            $discount_type_option_name = "wc_category_discounts_discount_type_{$category->term_id}";
            $discount_value_option_name = "wc_category_discounts_discount_value_{$category->term_id}";

            register_setting('wc-category-discounts-settings-group', $discount_type_option_name);
            register_setting('wc-category-discounts-settings-group', $discount_value_option_name);
        }
    }

    public function sanitize_options($input)
    {
        $new_input = array();

        foreach ($input as $key => $value) {
            $new_input[$key] = sanitize_text_field($value);
        }

        return $new_input;
    }

    public function ajax_load_discounts()
    {
        check_ajax_referer('wc-category-discounts-load-discounts');

        $categories = get_terms(
            array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            )
        );

        $discounts = array();

        foreach ($categories as $category) {
            $discount_type = get_option("wc_category_discounts_discount_type_{$category->term_id}");
            $discount_value = get_option("wc_category_discounts_discount_value_{$category->term_id}");

            if ($discount_type && $discount_value) {
                $discounts[] = array(
                    'category_id' => $category->term_id,
                    'discount_type' => $discount_type,
                    'discount_value' => $discount_value,
                );
            }
        }

        wp_send_json($discounts);
    }

    public function ajax_save_discount()
    {
        check_ajax_referer('wc-category-discounts-save-discount');

        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : '';
        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        $apply_to_discounted_items = isset($_POST['apply_to_discounted_items']) ? boolval($_POST['apply_to_discounted_items']) : false;

        if ($category_id && $discount_type && $discount_value) {
            update_option("wc_category_discounts_discount_type_{$category_id}", $discount_type);
            update_option("wc_category_discounts_discount_value_{$category_id}", $discount_value);
            update_option("wc_category_discounts_apply_to_discounted_items_{$category_id}", $apply_to_discounted_items);

            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => __('Invalid data provided.', 'wc-category-discounts')));
        }
    }



    public function ajax_delete_discount()
    {
        check_ajax_referer('wc-category-discounts-delete-discount');

        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;

        if ($category_id) {
            delete_option("wc_category_discounts_discount_type_{$category_id}");
            delete_option("wc_category_discounts_discount_value_{$category_id}");

            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => __('Invalid data provided.', 'wc-category-discounts')));
        }
    }


}