jQuery(document).ready(function($) {
    $('.seokar-refresh-gsc-data').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var postId = $button.data('post-id');
        var $statusSpan = $button.next('.seokar-refresh-gsc-status');

        $statusSpan.html('<span style="color: blue; font-size: 0.9em; margin-right: 5px;">در حال دریافت...</span>');
        $button.prop('disabled', true);

        $.ajax({
            url: seokar_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'seokar_refresh_gsc_data',
                post_id: postId,
                _wpnonce: seokar_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $statusSpan.html('<span style="color: green; font-size: 0.9em; margin-right: 5px;">موفقیت‌آمیز! صفحه را رفرش کنید.</span>');
                    // Optionally, update the data dynamically without a full page refresh
                    // For now, simpler to ask user to refresh page
                } else {
                    $statusSpan.html('<span style="color: red; font-size: 0.9em; margin-right: 5px;">خطا: ' + response.data + '</span>');
                }
            },
            error: function() {
                $statusSpan.html('<span style="color: red; font-size: 0.9em; margin-right: 5px;">خطا در ارتباط با سرور.</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
