jQuery(function ($) {
    $(document).ready(function ($) {
        $('#woocommerce-order-items').on('click', 'button.goopter-ppcp-order-capture', function (e) {
            $('.wc-order-data-row.wc-order-bulk-actions.wc-order-data-row-toggle').slideUp();
            $('div.wc-order-data-row.wc-order-add-item.wc-order-data-row-toggle button').not('.cancel-action').slideUp();
            $('.goopter_ppcp_capture_box input[name="ppcp_refund_amount"]').attr('name', 'refund_amount');
            $('.goopter_ppcp_capture_box input[id="ppcp_refund_amount"]').attr('id', 'refund_amount');
            $('div.wc-order-data-row.wc-order-add-item.wc-order-data-row-toggle').slideDown();
            $('.paypal-fee-tr').slideUp();
            $('.ppcp_auth_void_option').slideDown();
            $('.ppcp_auth_void_border').slideDown();
            $('#woocommerce-order-items').find('div.refund').slideDown();
            $('.goopter_ppcp_capture_box').slideDown();
            $('.goopter_ppcp_refund_box').slideUp();
            $('.goopter_ppcp_void_box').slideUp();
            $(".refund_order_item_qty:first").focus();
            $('.goopter-ppcp-order-action-submit').slideDown();
            $('#order_metabox_goopter_ppcp_payment_action').val('capture');
        });
        $('#woocommerce-order-items').on('click', 'button.goopter-ppcp-order-void', function (e) {
            $('.wc-order-data-row.wc-order-bulk-actions.wc-order-data-row-toggle').slideUp();
            $('div.wc-order-data-row.wc-order-add-item.wc-order-data-row-toggle button').not('.cancel-action').slideUp();
            $('.goopter_ppcp_capture_box input[name="ppcp_refund_amount"]').attr('name', 'refund_amount');
            $('.goopter_ppcp_capture_box input[id="ppcp_refund_amount"]').attr('id', 'refund_amount');
            $('div.wc-order-data-row.wc-order-add-item.wc-order-data-row-toggle').slideDown();
            $('.paypal-fee-tr').slideUp();
            $('.ppcp_auth_void_option').slideDown();
            $('.ppcp_auth_void_border').slideDown();
            $('.goopter_ppcp_capture_box').slideUp();
            $('.goopter_ppcp_refund_box').slideUp();
            $('.goopter_ppcp_void_box').slideDown();
            $('.goopter-ppcp-order-action-submit').slideDown();
            $('#order_metabox_goopter_ppcp_payment_action').val('void');
        });
        $('#woocommerce-order-items').on('click', 'button.cancel-action', function (e) {
            $('.ppcp_auth_void_border').slideUp();
            $('.ppcp_auth_void_option').slideUp();
            $('.ppcp_auth_void_amount').slideUp();
            $('.goopter_ppcp_capture_box input[name="refund_amount"]').attr('name', 'ppcp_refund_amount');
            $('.goopter_ppcp_capture_box input[id="refund_amount"]').attr('id', 'ppcp_refund_amount');
            $('.goopter_ppcp_refund_box').slideUp();
            $('.goopter_ppcp_capture_box').slideUp();
            $('.goopter_ppcp_void_box').slideUp();
            $('.paypal-fee-tr').slideUp();
            $('#goopter_ppcp_payment_submit_button').val('');
            $('#order_metabox_goopter_ppcp_payment_action').val('');
            
        });
        $('#woocommerce-order-items').on('click', 'button.goopter-ppcp-order-action-submit', function (e) {
            if ($('#is_ppcp_submited').val() === 'no') {
                $('.goopter_ppcp_capture_box input[name="refund_amount"]').attr('name', 'ppcp_refund_amount');
                $('.goopter_ppcp_capture_box input[id="refund_amount"]').attr('id', 'ppcp_refund_amount');
                if ( window.confirm( 'Are you sure you wish to process this? This action cannot be undone.' ) ) {
                    $('#is_ppcp_submited').val('yes');
                    $("#woocommerce-order-items").block({message: null, overlayCSS: {background: "#fff", opacity: .6}});
                    $('form#post, form#order').submit();
                } else {
                    e.preventDefault();
                    $('.goopter_ppcp_capture_box input[name="ppcp_refund_amount"]').attr('name', 'refund_amount');
                    $('.goopter_ppcp_capture_box input[id="ppcp_refund_amount"]').attr('id', 'refund_amount');
                    $('#is_ppcp_submited').val('no');
                    $("#woocommerce-order-items").unblock();
                }
            }
        });
    });
    $('#order_metabox_goopter_ppcp_payment_action').change(function (e) {
        e.preventDefault();
        if ($(this).val() === 'refund') {
            $('.goopter_ppcp_refund_box').slideDown();
            $('.goopter_ppcp_capture_box').slideUp();
            $('.goopter_ppcp_void_box').slideUp();
            $('#woocommerce-order-items').find('div.refund').slideUp();
        } else if ($(this).val() === 'capture') {
            $('#woocommerce-order-items').find('div.refund').slideDown();
            $('.goopter_ppcp_capture_box').slideDown();
            $('.goopter_ppcp_refund_box').slideUp();
            $('.goopter_ppcp_void_box').slideUp();
            $(".refund_order_item_qty:first").focus();
        } else if ($(this).val() === 'void') {
            $('.goopter_ppcp_capture_box').slideUp();
            $('.goopter_ppcp_refund_box').slideUp();
            $('.goopter_ppcp_void_box').slideDown();
            $('#woocommerce-order-items').find('div.refund').slideUp();
        } else {
            $('.goopter_ppcp_capture_box').slideUp();
            $('.goopter_ppcp_refund_box').slideUp();
            $('.goopter_ppcp_void_box').slideUp();
            $('#woocommerce-order-items').find('div.refund').slideUp();
        }
        if ($(this).val().length === 0) {
            $('.goopter-ppcp-order-action-submit').slideUp();
            return false;
        } else {

        }
    }).change();
});
