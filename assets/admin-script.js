jQuery(document).ready(function($) {
    'use strict';

    // Handle rule type change
    $('#omts-rule-type').on('change', function() {
        const ruleType = $(this).val();

        // Hide all rule-specific rows
        $('.omts-rule-row').hide();

        // Show the appropriate row based on rule type
        switch(ruleType) {
            case 'page':
                $('#omts-page-row').show();
                break;
            case 'post':
                $('#omts-post-row').show();
                break;
            case 'post_type':
                $('#omts-post-type-row').show();
                break;
            case 'draft_page':
                $('#omts-draft-page-row').show();
                break;
            case 'draft_post':
                $('#omts-draft-post-row').show();
                break;
            case 'pending_page':
                $('#omts-pending-page-row').show();
                break;
            case 'pending_post':
                $('#omts-pending-post-row').show();
                break;
            case 'private_page':
                $('#omts-private-page-row').show();
                break;
            case 'private_post':
                $('#omts-private-post-row').show();
                break;
            case 'future_page':
                $('#omts-future-page-row').show();
                break;
            case 'future_post':
                $('#omts-future-post-row').show();
                break;
            case 'url':
                $('#omts-url-row').show();
                break;
            case 'category':
                $('#omts-category-row').show();
                break;
            case 'tag':
                $('#omts-tag-row').show();
                break;
        }
    });

    // Handle form submission
    $('#omts-add-rule-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const ruleType = $('#omts-rule-type').val();

        // Collect form data based on rule type
        const formData = {
            action: 'omts_save_rules',
            nonce: omtsAjax.nonce,
            rule_type: ruleType,
            theme: $('#omts-theme-select').val()
        };

        // Add type-specific data
        switch(ruleType) {
            case 'page':
                formData.page_id = $('#omts-page-select').val();
                if (!formData.page_id) {
                    alert('Please select a page');
                    return;
                }
                break;
            case 'post':
                formData.post_id = $('#omts-post-select').val();
                if (!formData.post_id) {
                    alert('Please select a post');
                    return;
                }
                break;
            case 'post_type':
                formData.post_type = $('#omts-post-type-select').val();
                if (!formData.post_type) {
                    alert('Please select a post type');
                    return;
                }
                break;
            case 'draft_page':
                formData.page_id = $('#omts-draft-page-select').val();
                if (!formData.page_id) {
                    alert('Please select a draft page');
                    return;
                }
                break;
            case 'draft_post':
                formData.post_id = $('#omts-draft-post-select').val();
                if (!formData.post_id) {
                    alert('Please select a draft post');
                    return;
                }
                break;
            case 'pending_page':
                formData.page_id = $('#omts-pending-page-select').val();
                if (!formData.page_id) {
                    alert('Please select a pending page');
                    return;
                }
                break;
            case 'pending_post':
                formData.post_id = $('#omts-pending-post-select').val();
                if (!formData.post_id) {
                    alert('Please select a pending post');
                    return;
                }
                break;
            case 'private_page':
                formData.page_id = $('#omts-private-page-select').val();
                if (!formData.page_id) {
                    alert('Please select a private page');
                    return;
                }
                break;
            case 'private_post':
                formData.post_id = $('#omts-private-post-select').val();
                if (!formData.post_id) {
                    alert('Please select a private post');
                    return;
                }
                break;
            case 'future_page':
                formData.page_id = $('#omts-future-page-select').val();
                if (!formData.page_id) {
                    alert('Please select a scheduled page');
                    return;
                }
                break;
            case 'future_post':
                formData.post_id = $('#omts-future-post-select').val();
                if (!formData.post_id) {
                    alert('Please select a scheduled post');
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
            case 'category':
                formData.category_id = $('#omts-category-select').val();
                if (!formData.category_id) {
                    alert('Please select a category');
                    return;
                }
                break;
            case 'tag':
                formData.tag_id = $('#omts-tag-select').val();
                if (!formData.tag_id) {
                    alert('Please select a tag');
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
                            <td>${capitalizeFirstLetter(response.data.rule.type)}</td>
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
                console.log('Response:', response);
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
    function capitalizeFirstLetter(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function escapeHtml(text) {
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
