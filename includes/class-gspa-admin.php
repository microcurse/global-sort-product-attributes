<?php
if (!defined('ABSPATH')) {
    exit;
}

class GSPA_Admin {
    /**
     * Initialize the admin functionality
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add menu item to WordPress admin under Products
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=product', // Parent slug for Products menu
            __('Global Sort Product Attributes', 'global-sort-product-attributes'),
            __('Sort Attributes', 'global-sort-product-attributes'),
            'manage_woocommerce',
            'global-sort-product-attributes',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Update the hook check for the new menu location
        if ('product_page_global-sort-product-attributes' !== $hook) {
            return;
        }

        // Enqueue jQuery UI
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-progressbar');
        wp_enqueue_script('jquery-ui-autocomplete'); // Add autocomplete

        // Enqueue jQuery UI CSS
        wp_enqueue_style(
            'jquery-ui-style',
            'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css',
            array(),
            '1.12.1'
        );

        // Enqueue our custom scripts
        wp_enqueue_script(
            'gspa-admin',
            GSPA_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable', 'jquery-ui-progressbar', 'jquery-ui-autocomplete'),
            GSPA_VERSION,
            true
        );

        // Enqueue our custom styles
        wp_enqueue_style(
            'gspa-admin',
            GSPA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GSPA_VERSION
        );

        // Get all product categories for autocomplete
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true
        ));

        $category_data = array();
        foreach ($categories as $category) {
            $category_data[] = array(
                'id' => $category->term_id,
                'label' => $category->name,
                'value' => $category->name
            );
        }

        // Add "All Categories" option
        array_unshift($category_data, array(
            'id' => 0,
            'label' => __('All Categories', 'global-sort-product-attributes'),
            'value' => __('All Categories', 'global-sort-product-attributes')
        ));

        // Localize script
        wp_localize_script('gspa-admin', 'gspaAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gspa_nonce'),
            'categories' => $category_data,
            'strings' => array(
                'confirm_batch' => __('Are you sure you want to apply these changes to all selected products?', 'global-sort-product-attributes'),
                'processing' => __('Processing...', 'global-sort-product-attributes'),
                'success' => __('Changes saved successfully!', 'global-sort-product-attributes'),
                'error' => __('An error occurred. Please try again.', 'global-sort-product-attributes'),
                'no_products' => __('No variable products found in this category.', 'global-sort-product-attributes'),
                'select_category' => __('Select or type a category name...', 'global-sort-product-attributes')
            )
        ));
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Global Sort Product Attributes', 'global-sort-product-attributes'); ?></h1>

            <div class="gspa-container">
                <div class="gspa-filters">
                    <div class="gspa-category-container">
                        <label for="gspa-category-autocomplete"><?php _e('Category:', 'global-sort-product-attributes'); ?></label>
                        <input type="text" id="gspa-category-autocomplete" class="gspa-category-autocomplete" placeholder="<?php _e('Select or type a category name...', 'global-sort-product-attributes'); ?>">
                        <input type="hidden" id="gspa-category" value="0">
                    </div>

                    <select id="gspa-batch-size">
                        <option value="10">10 <?php _e('products per batch', 'global-sort-product-attributes'); ?></option>
                        <option value="25">25 <?php _e('products per batch', 'global-sort-product-attributes'); ?></option>
                        <option value="50">50 <?php _e('products per batch', 'global-sort-product-attributes'); ?></option>
                    </select>

                    <button id="gspa-load-products" class="button button-primary">
                        <?php _e('Load Products', 'global-sort-product-attributes'); ?>
                    </button>
                </div>

                <div class="gspa-attributes-container" style="display: none;">
                    <h2><?php _e('Global Attribute Order', 'global-sort-product-attributes'); ?></h2>
                    <p class="description">
                        <?php _e('Drag and drop attributes to set the global order. This order will be applied to all selected products.', 'global-sort-product-attributes'); ?>
                    </p>
                    <ul id="gspa-attribute-list" class="gspa-sortable"></ul>
                    
                    <div class="gspa-actions">
                        <button id="gspa-apply-order" class="button button-primary">
                            <?php _e('Apply Order to Selected Products', 'global-sort-product-attributes'); ?>
                        </button>
                        <button id="gspa-preview-changes" class="button">
                            <?php _e('Preview Changes', 'global-sort-product-attributes'); ?>
                        </button>
                    </div>
                </div>

                <div id="gspa-progress" style="display: none;">
                    <div class="progress-bar"></div>
                    <div class="progress-status"></div>
                </div>

                <div id="gspa-products-list">
                    <h3><?php _e('Products', 'global-sort-product-attributes'); ?></h3>
                    <div class="gspa-products-grid"></div>
                    <div class="gspa-pagination"></div>
                </div>

                <div id="gspa-preview-modal" class="gspa-modal" style="display: none;">
                    <div class="gspa-modal-content">
                        <span class="gspa-close">&times;</span>
                        <h3><?php _e('Preview Changes', 'global-sort-product-attributes'); ?></h3>
                        <div class="gspa-preview-content"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
} 