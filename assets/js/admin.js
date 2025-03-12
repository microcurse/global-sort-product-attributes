jQuery(document).ready(function($) {
    // Initialize variables
    let selectedProducts = new Set();
    let currentPage = 1;
    let totalPages = 1;
    let currentCategory = 0;
    let batchSize = 10;
    let globalAttributes = [];

    // Initialize autocomplete for category
    $('#gspa-category-autocomplete').autocomplete({
        source: gspaAdmin.categories,
        minLength: 0,
        select: function(event, ui) {
            $('#gspa-category').val(ui.item.id);
            currentCategory = ui.item.id;
            return true;
        }
    }).focus(function() {
        // Show all options on focus
        $(this).autocomplete('search', '');
    });

    // Set default value for autocomplete
    if (gspaAdmin.categories.length > 0) {
        $('#gspa-category-autocomplete').val(gspaAdmin.categories[0].label);
    }

    // Initialize sortable
    $('#gspa-attribute-list').sortable({
        update: function(event, ui) {
            updatePreview();
        }
    });

    // Load products button click handler
    $('#gspa-load-products').on('click', function() {
        currentCategory = $('#gspa-category').val();
        batchSize = $('#gspa-batch-size').val();
        currentPage = 1;
        loadProducts();
    });

    // Apply order button click handler
    $('#gspa-apply-order').on('click', function() {
        if (selectedProducts.size === 0) {
            alert(gspaAdmin.strings.no_products);
            return;
        }

        if (!confirm(gspaAdmin.strings.confirm_batch)) {
            return;
        }

        const attributeOrder = getAttributeOrder();
        processBatch(Array.from(selectedProducts), attributeOrder);
    });

    // Preview changes button click handler
    $('#gspa-preview-changes').on('click', function() {
        showPreviewModal();
    });

    // Close modal handler
    $('.gspa-close').on('click', function() {
        $('#gspa-preview-modal').hide();
    });

    // Load products from server
    function loadProducts() {
        $.ajax({
            url: gspaAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'gspa_get_category_products',
                nonce: gspaAdmin.nonce,
                category_id: currentCategory,
                page: currentPage,
                per_page: batchSize
            },
            beforeSend: function() {
                $('.gspa-products-grid').html('<p>' + gspaAdmin.strings.processing + '</p>');
            },
            success: function(response) {
                if (response.success) {
                    displayProducts(response.data.products);
                    updatePagination(response.data.total, response.data.pages);
                    updateAttributeList(response.data.products);
                } else {
                    $('.gspa-products-grid').html('<p class="error">' + gspaAdmin.strings.error + '</p>');
                }
            },
            error: function() {
                $('.gspa-products-grid').html('<p class="error">' + gspaAdmin.strings.error + '</p>');
            }
        });
    }

    // Display products in grid
    function displayProducts(products) {
        if (products.length === 0) {
            $('.gspa-products-grid').html('<p>' + gspaAdmin.strings.no_products + '</p>');
            return;
        }

        let html = '<div class="products-table">';
        html += '<div class="table-header">';
        html += '<div class="checkbox-cell"><input type="checkbox" id="select-all-products"></div>';
        html += '<div class="name-cell">Product Name</div>';
        html += '<div class="attributes-cell">Attributes</div>';
        html += '</div>';

        products.forEach(function(product) {
            html += '<div class="table-row">';
            html += '<div class="checkbox-cell"><input type="checkbox" class="product-checkbox" value="' + product.id + '"></div>';
            html += '<div class="name-cell">' + product.name + '</div>';
            html += '<div class="attributes-cell">' + product.attributes.map(attr => attr.name).join(', ') + '</div>';
            html += '</div>';
        });

        html += '</div>';
        $('.gspa-products-grid').html(html);

        // Update checkboxes based on selected products
        $('.product-checkbox').each(function() {
            $(this).prop('checked', selectedProducts.has(parseInt($(this).val())));
        });

        // Select all checkbox handler
        $('#select-all-products').on('change', function() {
            const isChecked = $(this).prop('checked');
            $('.product-checkbox').each(function() {
                const productId = parseInt($(this).val());
                $(this).prop('checked', isChecked);
                if (isChecked) {
                    selectedProducts.add(productId);
                } else {
                    selectedProducts.delete(productId);
                }
            });
        });

        // Individual checkbox handler
        $('.product-checkbox').on('change', function() {
            const productId = parseInt($(this).val());
            if ($(this).prop('checked')) {
                selectedProducts.add(productId);
            } else {
                selectedProducts.delete(productId);
            }
        });
    }

    // Update pagination
    function updatePagination(total, pages) {
        totalPages = pages;
        let html = '<div class="pagination">';
        
        if (currentPage > 1) {
            html += '<button class="button page-button" data-page="' + (currentPage - 1) + '">Previous</button>';
        }

        for (let i = 1; i <= pages; i++) {
            html += '<button class="button page-button' + (i === currentPage ? ' current' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        if (currentPage < pages) {
            html += '<button class="button page-button" data-page="' + (currentPage + 1) + '">Next</button>';
        }

        html += '</div>';
        $('.gspa-pagination').html(html);

        // Pagination click handler
        $('.page-button').on('click', function() {
            currentPage = parseInt($(this).data('page'));
            loadProducts();
        });
    }

    // Update attribute list
    function updateAttributeList(products) {
        // Collect all unique attributes
        const attributes = new Map();
        products.forEach(function(product) {
            product.attributes.forEach(function(attr) {
                if (!attributes.has(attr.taxonomy)) {
                    attributes.set(attr.taxonomy, attr);
                }
            });
        });

        globalAttributes = Array.from(attributes.values());
        
        // Display attributes in sortable list
        let html = '';
        globalAttributes.forEach(function(attr) {
            html += '<li class="attribute-item" data-taxonomy="' + attr.taxonomy + '">';
            html += '<span class="dashicons dashicons-menu"></span>';
            html += '<span class="attribute-name">' + attr.name + '</span>';
            html += '</li>';
        });

        $('#gspa-attribute-list').html(html);
        $('.gspa-attributes-container').show();
    }

    // Get current attribute order
    function getAttributeOrder() {
        return $('#gspa-attribute-list li').map(function() {
            return $(this).data('taxonomy');
        }).get();
    }

    // Process batch of products
    function processBatch(productIds, attributeOrder) {
        const progress = $('#gspa-progress');
        const progressBar = progress.find('.progress-bar');
        const progressStatus = progress.find('.progress-status');
        let processed = 0;

        progress.show();
        progressBar.progressbar({
            value: 0,
            max: productIds.length
        });

        function updateProgress(current, total, message) {
            const percentage = (current / total) * 100;
            progressBar.progressbar('value', current);
            progressStatus.text(message || `Processing ${current} of ${total} products...`);
        }

        // Process products in chunks
        const chunkSize = 10;
        const chunks = [];
        for (let i = 0; i < productIds.length; i += chunkSize) {
            chunks.push(productIds.slice(i, i + chunkSize));
        }

        function processChunk(index) {
            if (index >= chunks.length) {
                updateProgress(productIds.length, productIds.length, 'Processing complete!');
                setTimeout(function() {
                    progress.hide();
                    loadProducts(); // Reload the current page
                }, 2000);
                return;
            }

            $.ajax({
                url: gspaAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'gspa_process_batch',
                    nonce: gspaAdmin.nonce,
                    product_ids: chunks[index],
                    attribute_order: attributeOrder,
                    category_id: currentCategory
                },
                success: function(response) {
                    if (response.success) {
                        processed += response.data.processed;
                        updateProgress(processed, productIds.length);
                        processChunk(index + 1);
                    } else {
                        progressStatus.html('<p class="error">' + gspaAdmin.strings.error + '</p>');
                    }
                },
                error: function() {
                    progressStatus.html('<p class="error">' + gspaAdmin.strings.error + '</p>');
                }
            });
        }

        processChunk(0);
    }

    // Show preview modal
    function showPreviewModal() {
        const attributeOrder = getAttributeOrder();
        let html = '<table class="preview-table">';
        html += '<tr><th>Current Order</th><th>New Order</th></tr>';
        
        const currentOrder = globalAttributes.map(attr => attr.name);
        const newOrder = attributeOrder.map(taxonomy => {
            const attr = globalAttributes.find(a => a.taxonomy === taxonomy);
            return attr ? attr.name : taxonomy;
        });

        html += '<tr><td>' + currentOrder.join('<br>') + '</td><td>' + newOrder.join('<br>') + '</td></tr>';
        html += '</table>';

        $('.gspa-preview-content').html(html);
        $('#gspa-preview-modal').show();
    }
}); 