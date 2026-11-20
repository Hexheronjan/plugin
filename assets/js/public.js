/**
 * Public JavaScript for Premium Membership frontend.
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Prevent double checkout submission
        $('.pm-checkout-form').on('submit', function() {
            var $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.prop('disabled', true).text('Memproses Pesanan...');
        });
    });
})(jQuery);
