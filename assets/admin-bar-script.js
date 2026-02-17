// Global function for theme switching (dashboard only)
function oomtsAdminBarSwitchTheme(themeSlug, nonce) {
    // Use the global ajaxurl if available, otherwise use the localized one
    var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : (typeof omtsAdminBar !== 'undefined' ? omtsAdminBar.ajaxurl : '/wp-admin/admin-ajax.php');

    // Send AJAX request
    jQuery.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
            action: 'omts_switch_admin_theme',
            nonce: nonce,
            theme: themeSlug
        },
        success: function(response) {
            if (response.success) {
                // Reload the page to apply the new theme
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            alert('An error occurred while switching themes. Please try again.');
        }
    });
}

jQuery(document).ready(function($) {
    'use strict';

    // Prevent parent menu click from doing anything
    $(document).on('click', '#wp-admin-bar-omts-admin-theme-switcher > .ab-item', function(e) {
        e.preventDefault();
    });
});
