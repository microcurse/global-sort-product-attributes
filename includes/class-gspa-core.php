<?php
if (!defined('ABSPATH')) {
    exit;
}

class GSPA_Core {
    /**
     * Initialize the core functionality
     */
    public function init() {
        add_action('wp_ajax_gspa_save_attribute_order', array($this, 'save_attribute_order'));
        add_action('wp_ajax_gspa_get_category_products', array($this, 'get_category_products'));
        add_action('wp_ajax_gspa_process_batch', array($this, 'process_batch'));
    }

    /**
     * Get products for a specific category
     */
    public function get_category_products() {
        check_ajax_referer('gspa_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish'
        );

        if ($category_id > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id
                )
            );
        }

        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                
                if ($product && $product->is_type('variable')) {
                    $attributes = $this->get_product_attributes($product);
                    if (!empty($attributes)) {
                        $products[] = array(
                            'id' => $product->get_id(),
                            'name' => $product->get_name(),
                            'attributes' => $attributes
                        );
                    }
                }
            }
        }
        wp_reset_postdata();

        wp_send_json_success(array(
            'products' => $products,
            'total' => $query->found_posts,
            'pages' => ceil($query->found_posts / $per_page)
        ));
    }

    /**
     * Get product attributes
     */
    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();

        foreach ($product_attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $attributes[] = array(
                    'id' => $attribute->get_id(),
                    'name' => wc_attribute_label($attribute->get_name()),
                    'taxonomy' => $attribute->get_name(),
                    'position' => $attribute->get_position()
                );
            }
        }

        return $attributes;
    }

    /**
     * Save attribute order for products
     */
    public function save_attribute_order() {
        check_ajax_referer('gspa_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $attribute_order = isset($_POST['attribute_order']) ? array_map('sanitize_text_field', $_POST['attribute_order']) : array();
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        if (!$product_id || empty($attribute_order)) {
            wp_send_json_error('Invalid data');
        }

        $this->update_product_attribute_order($product_id, $attribute_order, $category_id);
        wp_send_json_success('Attribute order updated successfully');
    }

    /**
     * Process a batch of products
     */
    public function process_batch() {
        check_ajax_referer('gspa_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        $attribute_order = isset($_POST['attribute_order']) ? array_map('sanitize_text_field', $_POST['attribute_order']) : array();
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        if (empty($product_ids) || empty($attribute_order)) {
            wp_send_json_error('Invalid data');
        }

        $processed = 0;
        $failed = array();

        foreach ($product_ids as $product_id) {
            $result = $this->update_product_attribute_order($product_id, $attribute_order, $category_id);
            if ($result) {
                $processed++;
            } else {
                $failed[] = $product_id;
            }
        }

        wp_send_json_success(array(
            'processed' => $processed,
            'failed' => $failed
        ));
    }

    /**
     * Update product attribute order
     */
    private function update_product_attribute_order($product_id, $attribute_order, $category_id) {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return false;
        }

        $product_attributes = $product->get_attributes();
        $old_order = array();
        $new_attributes = array();

        // Store old order for logging
        foreach ($product_attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $old_order[] = $attribute->get_name();
            }
        }

        // Reorder attributes
        foreach ($attribute_order as $position => $taxonomy) {
            if (isset($product_attributes[$taxonomy])) {
                $attribute = $product_attributes[$taxonomy];
                $attribute->set_position($position);
                $new_attributes[$taxonomy] = $attribute;
            }
        }

        // Add any remaining attributes that weren't in the order
        foreach ($product_attributes as $taxonomy => $attribute) {
            if (!isset($new_attributes[$taxonomy])) {
                $new_attributes[$taxonomy] = $attribute;
            }
        }

        // Update product
        $product->set_attributes($new_attributes);
        $product->save();

        // Log the change
        $this->log_attribute_change($product_id, $category_id, $old_order, array_values($attribute_order));

        return true;
    }

    /**
     * Log attribute order changes
     */
    private function log_attribute_change($product_id, $category_id, $old_order, $new_order) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gspa_logs';

        $wpdb->insert(
            $table_name,
            array(
                'product_id' => $product_id,
                'category_id' => $category_id,
                'old_order' => json_encode($old_order),
                'new_order' => json_encode($new_order),
                'user_id' => get_current_user_id()
            ),
            array('%d', '%d', '%s', '%s', '%d')
        );
    }
} 