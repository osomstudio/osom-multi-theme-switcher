// Non-blocking message, so a failed switch never traps the admin bar behind a dialog.
function omtsAdminBarError(message) {
    var text = message || (typeof omtsAdminBar !== 'undefined' ? omtsAdminBar.error : 'Could not switch the dashboard theme.');
    var box = document.createElement('div');

    box.className = 'omts-adminbar-error';
    box.textContent = text;
    box.setAttribute('role', 'status');
    box.style.cssText = 'position:fixed;top:40px;right:16px;z-index:100000;background:#8a2424;color:#fff;padding:10px 14px;border-radius:3px;font-size:13px;max-width:320px';

    document.body.appendChild(box);
    window.setTimeout(function () {
        box.remove();
    }, 6000);
}

// Global function for theme switching (dashboard only)
function omtsAdminBarSwitchTheme(themeSlug, nonce) {
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
                omtsAdminBarError(response.data);
            }
        },
        error: function() {
            omtsAdminBarError();
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
