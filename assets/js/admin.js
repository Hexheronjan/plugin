/**
 * Admin JavaScript for Premium Membership.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Confirmation for delete actions
        $('.pm-btn-delete').on('click', function(e) {
            if (!confirm(pm_admin_vars.confirm_delete || 'Apakah Anda yakin?')) {
                e.preventDefault();
            }
        });

        // Copy Webhook URL to clipboard with visual feedback
        $('.code[readonly]').on('click', function() {
            var $this = $(this);
            $this.select();
            if (navigator.clipboard) {
                navigator.clipboard.writeText($this.val()).then(function() {
                    // Visual feedback
                    var originalBg = $this.css('background-color');
                    $this.css('background-color', '#dcfce7');
                    setTimeout(function() {
                        $this.css('background-color', originalBg);
                    }, 600);
                });
            }
        });
    });
})(jQuery);
