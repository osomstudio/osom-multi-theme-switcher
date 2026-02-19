jQuery(document).ready(function($) {
    'use strict';

    // Types that need a two-step object → item selection
    const TWO_STEP_TYPES = ['custom_post_type', 'taxonomy'];

    // Types that directly load items (no intermediate object step)
    const DIRECT_ITEM_TYPES = ['page', 'post'];

    // Handle rule type change
    $('#omts-rule-type').on('change', function() {
        const ruleType = $(this).val();

        // Hide all rule-specific rows and reset selects
        $('.omts-rule-row').hide();
        $('#omts-rule-object').html('<option value="">' + omtsAjax.selectLabel + '</option>');
        $('#omts-rule-item').html('<option value="">' + omtsAjax.selectLabel + '</option>');
        $('#omts-rule-item-row').hide();

        if (!ruleType) {
            return;
        }

        if (ruleType === 'url') {
            $('#omts-url-row').show();
            return;
        }

        if (TWO_STEP_TYPES.indexOf(ruleType) !== -1) {
            // Show object row and fetch objects via AJAX
            $('#omts-rule-object-row').show();
            loadRuleObjects(ruleType);
            return;
        }

        if (DIRECT_ITEM_TYPES.indexOf(ruleType) !== -1) {
            // Directly load items (pages or posts)
            $('#omts-rule-item-row').show();
            loadRuleItems(ruleType, '');
        }
    });

    // Handle object selection change (for custom_post_type / taxonomy)
    $('#omts-rule-object').on('change', function() {
        const objectType = $(this).val();
        const ruleType = $('#omts-rule-type').val();

        $('#omts-rule-item').html('<option value="">' + omtsAjax.selectLabel + '</option>');
        $('#omts-rule-item-row').hide();

        if (!objectType) {
            return;
        }

        $('#omts-rule-item-row').show();
        loadRuleItems(ruleType, objectType);
    });

    function loadRuleObjects(ruleType) {
        const spinner = $('#omts-object-spinner');
        const select = $('#omts-rule-object');

        spinner.addClass('is-active');
        select.prop('disabled', true);

        $.ajax({
            url: omtsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'omts_get_rule_objects',
                nonce: omtsAjax.nonce,
                rule_type: ruleType
            },
            success: function(response) {
                if (response.success && response.data.objects) {
                    const objects = response.data.objects;
                    let html = '<option value="">' + omtsAjax.selectLabel + '</option>';
                    objects.forEach(function(obj) {
                        html += '<option value="' + escapeAttr(obj.value) + '">' + escapeHtml(obj.label) + '</option>';
                    });
                    select.html(html);
                }
            },
            error: function() {
                alert('Error loading objects. Please try again.');
            },
            complete: function() {
                spinner.removeClass('is-active');
                select.prop('disabled', false);
            }
        });
    }

    function loadRuleItems(ruleType, objectType) {
        const spinner = $('#omts-item-spinner');
        const select = $('#omts-rule-item');

        spinner.addClass('is-active');
        select.prop('disabled', true);

        $.ajax({
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
                    const items = response.data.items;
                    let html = '<option value="">' + omtsAjax.selectLabel + '</option>';
                    items.forEach(function(item) {
                        html += '<option value="' + escapeAttr(item.value) + '">' + escapeHtml(item.label) + '</option>';
                    });
                    select.html(html);
                }
            },
            error: function() {
                alert('Error loading items. Please try again.');
            },
            complete: function() {
                spinner.removeClass('is-active');
                select.prop('disabled', false);
            }
        });
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

        if (!$('#omts-theme-select').val()) {
            alert('Please select a theme');
            return;
        }

        // Collect form data based on rule type
        const formData = {
            action: 'omts_save_rules',
            nonce: omtsAjax.nonce,
            rule_type: ruleType,
            theme: $('#omts-theme-select').val()
        };

        if (ruleType === 'url') {
            formData.custom_url = $('#omts-url-input').val().trim();
            if (!formData.custom_url) {
                alert('Please enter a URL or slug');
                return;
            }
        } else if (TWO_STEP_TYPES.indexOf(ruleType) !== -1) {
            formData.object_type = $('#omts-rule-object').val();
            formData.item_id = $('#omts-rule-item').val();
            if (!formData.object_type) {
                alert('Please select an object type');
                return;
            }
            if (!formData.item_id) {
                alert('Please select an item');
                return;
            }
        } else if (DIRECT_ITEM_TYPES.indexOf(ruleType) !== -1) {
            formData.item_id = $('#omts-rule-item').val();
            if (!formData.item_id) {
                alert('Please select an item');
                return;
            }
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
                            <td>${escapeHtml(response.data.type_display)}</td>
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
                    $('#omts-rule-type').trigger('change');

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
                console.log('Response Text:', xhr.responseText);
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
    function escapeHtml(text) {
        if (typeof text !== 'string') { text = String(text); }
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function escapeAttr(text) {
        if (typeof text !== 'string') { text = String(text); }
        return text.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
});
