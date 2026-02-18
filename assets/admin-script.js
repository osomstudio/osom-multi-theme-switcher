jQuery(document).ready(function($) {
    'use strict';

    // Handle rule type change — cascading selectors
    $('#omts-rule-type').on('change', function() {
        const ruleType = $(this).val();

        // Hide all dynamic rows and reset
        $('.omts-rule-row').hide();
        $('#omts-rule-object').html('<option value="">-- Select --</option>');
        $('#omts-rule-item').html('<option value="">-- Select --</option>');
        $('#omts-url-input').val('');

        if (!ruleType) {
            return;
        }

        switch (ruleType) {
            case 'page':
            case 'post':
                // Load items directly (no object step)
                $('#omts-rule-item-row').show();
                loadRuleItems(ruleType, '');
                break;

            case 'custom_post_type':
            case 'taxonomy':
                // Load objects first, then items on object selection
                $('#omts-rule-object-row').show();
                loadRuleObjects(ruleType);
                break;

            case 'url':
                $('#omts-url-row').show();
                break;
        }
    });

    // Handle rule object change — load items
    $('#omts-rule-object').on('change', function() {
        const objectType = $(this).val();
        const ruleType = $('#omts-rule-type').val();

        $('#omts-rule-item').html('<option value="">-- Select --</option>');

        if (!objectType) {
            $('#omts-rule-item-row').hide();
            return;
        }

        $('#omts-rule-item-row').show();
        loadRuleItems(ruleType, objectType);
    });

    // Track pending AJAX requests to abort stale responses
    var pendingObjectsRequest = null;
    var pendingItemsRequest = null;

    // Load rule objects via AJAX
    function loadRuleObjects(ruleType) {
        if (pendingObjectsRequest) {
            pendingObjectsRequest.abort();
        }

        const spinner = $('#omts-object-spinner');
        spinner.addClass('is-active');

        var thisRequest = $.ajax({
            url: omtsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'omts_get_rule_objects',
                nonce: omtsAjax.nonce,
                rule_type: ruleType
            },
            success: function(response) {
                if (response.success && response.data.objects) {
                    const select = $('#omts-rule-object');
                    select.html('<option value="">-- Select --</option>');
                    response.data.objects.forEach(function(obj) {
                        select.append(
                            $('<option></option>')
                                .val(obj.value)
                                .text(obj.label)
                        );
                    });
                }
            },
            error: function(xhr, status) {
                if (status !== 'abort') {
                    console.error('OMTS: Failed to load rule objects', status);
                    $('#omts-rule-object').html('<option value="" disabled>Error loading objects</option>');
                }
            },
            complete: function() {
                if (pendingObjectsRequest === thisRequest) {
                    pendingObjectsRequest = null;
                    spinner.removeClass('is-active');
                }
            }
        });
        pendingObjectsRequest = thisRequest;
    }

    // Load rule items via AJAX
    function loadRuleItems(ruleType, objectType) {
        if (pendingItemsRequest) {
            pendingItemsRequest.abort();
        }

        const spinner = $('#omts-item-spinner');
        spinner.addClass('is-active');

        var thisRequest = $.ajax({
            url: omtsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'omts_get_rule_items',
                nonce: omtsAjax.nonce,
                rule_type: ruleType,
                object_type: objectType
            },
            success: function(response) {
                if (response.success && response.data.items) {
                    const select = $('#omts-rule-item');
                    select.html('<option value="">-- Select --</option>');
                    response.data.items.forEach(function(item) {
                        select.append(
                            $('<option></option>')
                                .val(item.value)
                                .text(item.label)
                        );
                    });
                }
            },
            error: function(xhr, status) {
                if (status !== 'abort') {
                    console.error('OMTS: Failed to load rule items', status);
                    $('#omts-rule-item').html('<option value="" disabled>Error loading items</option>');
                }
            },
            complete: function() {
                if (pendingItemsRequest === thisRequest) {
                    pendingItemsRequest = null;
                    spinner.removeClass('is-active');
                }
            }
        });
        pendingItemsRequest = thisRequest;
    }

    // Handle form submission
    $('#omts-add-rule-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const ruleType = $('#omts-rule-type').val();

        if (!ruleType) {
            alert('Please select a rule type');
            return;
        }

        // Collect form data
        const formData = {
            action: 'omts_save_rules',
            nonce: omtsAjax.nonce,
            rule_type: ruleType,
            theme: $('#omts-theme-select').val()
        };

        // Add type-specific data
        switch (ruleType) {
            case 'page':
            case 'post':
                formData.item_id = $('#omts-rule-item').val();
                if (!formData.item_id) {
                    alert('Please select an item');
                    return;
                }
                break;

            case 'custom_post_type':
                formData.object_type = $('#omts-rule-object').val();
                formData.item_id = $('#omts-rule-item').val();
                if (!formData.object_type) {
                    alert('Please select a post type');
                    return;
                }
                if (!formData.item_id) {
                    alert('Please select an item');
                    return;
                }
                break;

            case 'taxonomy':
                formData.object_type = $('#omts-rule-object').val();
                formData.item_id = $('#omts-rule-item').val();
                if (!formData.object_type) {
                    alert('Please select a taxonomy');
                    return;
                }
                if (!formData.item_id) {
                    alert('Please select a term');
                    return;
                }
                break;

            case 'url':
                formData.custom_url = $('#omts-url-input').val();
                if (!formData.custom_url) {
                    alert('Please enter a URL or slug');
                    return;
                }
                break;
        }

        if (!formData.theme) {
            alert('Please select a theme');
            return;
        }

        // Disable submit button
        submitButton.prop('disabled', true).text('Adding...');

        // Send AJAX request
        $.ajax({
            url: omtsAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Remove "no rules" message if it exists
                    $('.omts-no-rules').remove();

                    // Add new row to table
                    const newRow = `
                        <tr data-index="${response.data.index}">
                            <td>${escapeHtml(response.data.type_display || capitalizeFirstLetter(response.data.rule.type))}</td>
                            <td>${escapeHtml(response.data.target_display)}</td>
                            <td>${escapeHtml(response.data.theme_name)}</td>
                            <td>
                                <button class="button omts-delete-rule" data-index="${response.data.index}">Delete</button>
                            </td>
                        </tr>
                    `;
                    $('#omts-rules-tbody').append(newRow);

                    // Reset form
                    form[0].reset();
                    $('.omts-rule-row').hide();

                    // Show success message
                    const successMsg = $('<span class="omts-success-message">Rule added successfully!</span>');
                    submitButton.after(successMsg);
                    setTimeout(function() {
                        successMsg.remove();
                    }, 2000);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while saving the rule. Please try again.');
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Add Rule');
            }
        });
    });

    // Handle delete button click
    $(document).on('click', '.omts-delete-rule', function() {
        if (!confirm('Are you sure you want to delete this rule?')) {
            return;
        }

        const button = $(this);
        const row = button.closest('tr');
        const index = button.data('index');

        button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: omtsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'omts_delete_rule',
                nonce: omtsAjax.nonce,
                index: index
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();

                        // Update indices for remaining rows
                        $('#omts-rules-tbody tr').each(function(newIndex) {
                            $(this).attr('data-index', newIndex);
                            $(this).find('.omts-delete-rule').attr('data-index', newIndex);
                        });

                        // Show "no rules" message if table is empty
                        if ($('#omts-rules-tbody tr').length === 0) {
                            $('#omts-rules-tbody').html('<tr class="omts-no-rules"><td colspan="4">No rules configured yet. Add your first rule above.</td></tr>');
                        }
                    });
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Delete');
                }
            },
            error: function() {
                alert('An error occurred while deleting the rule. Please try again.');
                button.prop('disabled', false).text('Delete');
            }
        });
    });

    // Handle REST prefix form submission
    $('#omts-add-prefix-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const theme = $('#omts-prefix-theme-select').val();
        const prefix = $('#omts-rest-prefix-input').val().trim();

        if (!theme) {
            alert('Please select a theme');
            return;
        }

        // Disable submit button
        submitButton.prop('disabled', true).text('Saving...');

        // Send AJAX request
        $.ajax({
            url: omtsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'omts_save_rest_prefix',
                nonce: omtsAjax.nonce,
                theme: theme,
                prefix: prefix
            },
            success: function(response) {
                if (response.success) {
                    // Rebuild the entire table with updated data
                    rebuildPrefixTable(response.data.prefixes);

                    // Reset form
                    form[0].reset();

                    // Show success message
                    const successMsg = $('<span class="omts-success-message">REST prefix configured successfully!</span>');
                    submitButton.after(successMsg);
                    setTimeout(function() {
                        successMsg.remove();
                    }, 2000);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                alert('An error occurred while saving the REST prefix. Check console for details.');
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Set REST Prefix');
            }
        });
    });

    // Function to rebuild prefix table
    function rebuildPrefixTable(prefixes) {
        const tbody = $('#omts-prefixes-tbody');
        tbody.empty();

        if (!prefixes || prefixes.length === 0) {
            tbody.html('<tr class="omts-no-prefixes"><td colspan="4">No custom REST prefixes configured. All themes use the default wp-json prefix.</td></tr>');
            return;
        }

        prefixes.forEach(function(mapping, index) {
            const displayPrefix = mapping.prefix || 'wp-json';
            const exampleUrl = window.location.origin + '/' + displayPrefix + '/namespace/endpoint';

            // Get theme name from the select option
            const themeName = $('#omts-prefix-theme-select option[value="' + mapping.theme + '"]').text() || mapping.theme;

            const row = `
                <tr data-index="${index}">
                    <td>${escapeHtml(themeName)}</td>
                    <td><code>${escapeHtml(displayPrefix)}</code></td>
                    <td><code>${escapeHtml(exampleUrl)}</code></td>
                    <td>
                        <button class="button omts-delete-prefix" data-index="${index}">Delete</button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    // Handle delete prefix button click
    $(document).on('click', '.omts-delete-prefix', function() {
        if (!confirm('Are you sure you want to delete this REST prefix mapping? The theme will revert to using the default wp-json prefix.')) {
            return;
        }

        const button = $(this);
        const row = button.closest('tr');
        const index = button.data('index');

        button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: omtsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'omts_delete_rest_prefix',
                nonce: omtsAjax.nonce,
                index: index
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();

                        // Update indices for remaining rows
                        $('#omts-prefixes-tbody tr').each(function(newIndex) {
                            $(this).attr('data-index', newIndex);
                            $(this).find('.omts-delete-prefix').attr('data-index', newIndex);
                        });

                        // Show "no prefixes" message if table is empty
                        if ($('#omts-prefixes-tbody tr').length === 0) {
                            $('#omts-prefixes-tbody').html('<tr class="omts-no-prefixes"><td colspan="4">No custom REST prefixes configured. All themes use the default wp-json prefix.</td></tr>');
                        }
                    });
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Delete');
                }
            },
            error: function() {
                alert('An error occurred while deleting the REST prefix mapping. Please try again.');
                button.prop('disabled', false).text('Delete');
            }
        });
    });

    // Helper functions
    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function escapeHtml(text) {
        if (text == null) {
            return '';
        }
        text = String(text);
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
