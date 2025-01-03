jQuery(function () {
    var el_notice = jQuery(".goopter-notice");
    jQuery('[id^=goopter_notification]').each(function (i) {
        jQuery('[id="' + this.id + '"]').slice(1).remove();
    });
    el_notice.fadeIn(750);
    jQuery(".goopter-notice-dismiss").click(function (e) {
        e.preventDefault();
        jQuery(this).parent().parent(".goopter-notice").fadeOut(600, function () {
            jQuery(this).parent().parent(".goopter-notice").remove();
        });
        notify_wordpress(jQuery(this).data("msg"));
    });
    function notify_wordpress(message) {
        var param = {
            action: 'goopter_dismiss_notice',
            data: message
        };
        jQuery.post(ajaxurl, param);
    }
    var opt_in_logging = jQuery("#goopter_send_opt_in_logging_details");
    jQuery('#goopter_send_opt_in_logging_details').each(function (i) {
        jQuery('[id="' + this.id + '"]').slice(1).remove();
    });
    opt_in_logging.fadeIn(750);
});
jQuery(document).ready(function ($) {
    jQuery('#woocommerce_paypal_express_disallowed_funding_methods').closest('table').addClass('goopter_smart_button_setting_left');
    if (goopter_admin.is_paypal_credit_enable == "no") {
        jQuery("#woocommerce_paypal_express_show_paypal_credit").attr("disabled", true);
        jQuery("label[for='woocommerce_paypal_express_show_paypal_credit']").css('color', '#666');
    }
    jQuery("#woocommerce_paypal_express_enable_in_context_checkout_flow").change(function () {
        if (jQuery(this).is(':checked')) {
            jQuery('.in_context_checkout_part_other').show();
            jQuery('.in_context_checkout_part_other').next('p').show();
            jQuery('.in_context_checkout_part_other').parents('table').show();
            jQuery('.display_smart_button_previews').show();
            jQuery('.goopter_button_settings_selector').show();
            jQuery('#woocommerce_paypal_express_show_paypal_credit').closest('tr').hide();
            jQuery('woocommerce_paypal_express_enable_google_analytics_click').closest('tr').hide();
            jQuery('#woocommerce_paypal_express_checkout_with_pp_button_type').closest('tr').hide();
            jQuery('.in_context_checkout_part').show();
            jQuery('.in_context_checkout_part').next('p').show();
            jQuery('.in_context_checkout_part').parents('tr').show();
        } else {
            jQuery('.in_context_checkout_part_other').hide();
            jQuery('.in_context_checkout_part_other').next('p').hide();
            jQuery('.in_context_checkout_part_other').parents('table').hide();
            jQuery('.display_smart_button_previews').hide();
            jQuery('.goopter_button_settings_selector').hide();
            jQuery('#woocommerce_paypal_express_show_paypal_credit').closest('tr').show();
            jQuery('woocommerce_paypal_express_enable_google_analytics_click').closest('tr').show();
            jQuery('#woocommerce_paypal_express_checkout_with_pp_button_type').closest('tr').show();
            jQuery('.in_context_checkout_part').show();
            jQuery('.in_context_checkout_part').next('p').hide();
            jQuery('.in_context_checkout_part').parents('tr').hide();
        }
    }).change();
    $("#woocommerce_paypal_express_customer_service_number").attr("maxlength", "16");
    if ($("#woocommerce_paypal_express_checkout_with_pp_button_type").val() == "customimage") {
        jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_my_custom').each(function (i, el) {
            jQuery(el).closest('tr').show();
        });
    } else {
        jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_my_custom').each(function (i, el) {
            jQuery(el).closest('tr').hide();
        });
    }
    if ($("#woocommerce_paypal_express_checkout_with_pp_button_type").val() == "textbutton") {
        jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_text_button').each(function (i, el) {
            jQuery(el).closest('tr').show();
        });
    } else {
        jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_text_button').each(function (i, el) {
            jQuery(el).closest('tr').hide();
        });
    }
    $("#woocommerce_paypal_express_checkout_with_pp_button_type").change(function () {
        if ($(this).val() == "customimage") {
            jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_my_custom').each(function (i, el) {
                jQuery(el).closest('tr').show();
            });
            jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_text_button').each(function (i, el) {
                jQuery(el).closest('tr').hide();
            });
        } else if ($(this).val() == "textbutton") {
            jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_text_button').each(function (i, el) {
                jQuery(el).closest('tr').show();
            });
            jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_my_custom').each(function (i, el) {
                jQuery(el).closest('tr').hide();
            });
        } else {
            jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_my_custom').each(function (i, el) {
                jQuery(el).closest('tr').hide();
            });
            jQuery('.form-table tr td #woocommerce_paypal_express_pp_button_type_text_button').each(function (i, el) {
                jQuery(el).closest('tr').hide();
            });
        }
    });
    if (goopter_admin.is_ssl == "yes") {
        jQuery("#woocommerce_paypal_express_checkout_logo").after('<a href="#" id="checkout_logo" class="button_upload button">Upload</a>');
        jQuery("#woocommerce_paypal_express_checkout_logo_hdrimg").after('<a href="#" id="checkout_logo_hdrimg" class="button_upload button">Upload</a>');
        jQuery("#woocommerce_paypal_plus_checkout_logo").after('<a href="#" id="checkout_logo" class="button_upload button">Upload</a>');
    }
    jQuery("#woocommerce_paypal_express_pp_button_type_my_custom, #woocommerce_paypal_pro_card_icon, #woocommerce_paypal_pro_payflow_card_icon, #woocommerce_paypal_advanced_card_icon, #woocommerce_paypal_credit_card_rest_card_icon, #woocommerce_braintree_card_icon").css({float: "left"});
    jQuery("#woocommerce_paypal_express_pp_button_type_my_custom, #woocommerce_paypal_pro_card_icon, #woocommerce_paypal_pro_payflow_card_icon, #woocommerce_paypal_advanced_card_icon, #woocommerce_paypal_credit_card_rest_card_icon, #woocommerce_braintree_card_icon").after('<a href="#" id="upload" class="button_upload button">Upload</a>');
    var custom_uploader;
    $('.button_upload').click(function (e) {
        var BTthis = jQuery(this);
        e.preventDefault();
        //Extend the wp.media object
        custom_uploader = wp.media.frames.file_frame = wp.media({
            title: goopter_admin.choose_image,
            button: {
                text: goopter_admin.choose_image
            },
            multiple: false
        });
        //When a file is selected, grab the URL and set it as the text field's value
        custom_uploader.on('select', function () {
            var attachment = custom_uploader.state().get('selection').first().toJSON();
            var pre_input = BTthis.prev();
            var url = attachment.url;
            if (BTthis.attr('id') != 'upload') {
                if (attachment.url.indexOf('http:') > -1) {
                    url = url.replace('http', 'https');
                }
            }
            pre_input.val(url);
        });
        //Open the uploader dialog
        custom_uploader.open();
    });
    // change target type -- toggle where input
    $('#pfw-bulk-action-target-type').change(function () {
        $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-category').hide();
        $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-product-type').hide();
        $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-price-value').hide();
        $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-stock-value').hide();

        $('#pfw-bulk-action-target-where-category').removeAttr('required');
        $('#pfw-bulk-action-target-where-product-type').removeAttr('required');
        $('#pfw-bulk-action-target-where-price-value').removeAttr('required');
        $('#pfw-bulk-action-target-where-stock-value').removeAttr('required');
        if ($(this).val() == 'where')
        {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-type').show();
            $('#pfw-bulk-action-target-where-type').attr('required', 'required');
        } else
        {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-type').hide();
            $('#pfw-bulk-action-target-where-type').removeAttr('required');
        }
    });
    // change target where type -- toggle categories/value inputs
    $('#pfw-bulk-action-type').change(function () {
        $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-payment-action-type').hide();
        $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-payment-authorization-type').hide();
        if ($(this).val() == 'enable_payment_action') {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-payment-action-type').show();
        }
    }).change();
    $('#woo_product_payment_action').change(function () {
        if ($(this).val() === 'Authorization') {
            $('#woo_product_payment_action_authorization').closest('p').show();
        } else {
            $('#woo_product_payment_action_authorization').closest('p').hide();
        }
    }).change();
    $('#enable_payment_action').change(function () {
        if ($(this).is(':checked')) {
            $('.woo_product_payment_action_field').show();
            if ($('#woo_product_payment_action').val() == 'Authorization') {
                $('.woo_product_payment_action_authorization_field').show();
            }
        } else {
            $('.woo_product_payment_action_field').hide();
            $('.woo_product_payment_action_authorization_field').hide();
        }
    }).change();
    $('#pfw-bulk-action-payment-action-type').change(function () {
        if ($(this).val() == 'Authorization') {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-payment-authorization-type').show();
        } else {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-payment-authorization-type').hide();
        }
    }).change();


    $('#pfw-bulk-action-target-where-type').change(function () {
        if ($(this).val() == 'category')
        {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-category').show();
            $('#pfw-bulk-action-target-where-category').attr('required', 'required');
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-product-type').hide();
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-price-value').hide();
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-stock-value').hide();
            $('#pfw-bulk-action-target-where-product-type').removeAttr('required');
            $('#pfw-bulk-action-target-where-price-value').removeAttr('required');
            $('#pfw-bulk-action-target-where-stock-value').removeAttr('required');
        } else if ($(this).val() == 'product_type')
        {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-product-type').show();
            $('#pfw-bulk-action-target-where-product-type').attr('required', 'required');
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-category').hide();
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-price-value').hide();
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-stock-value').hide();
            $('#pfw-bulk-action-target-where-category').removeAttr('required');
            $('#pfw-bulk-action-target-where-price-value').removeAttr('required');
            $('#pfw-bulk-action-target-where-stock-value').removeAttr('required');
        } else
        {
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-category').hide();
            $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-product-type').hide();
            $('#pfw-bulk-action-target-where-category').removeAttr('required');
            $('#pfw-bulk-action-target-where-product-type').removeAttr('required');
            if ($(this).val() == 'price_greater' || $(this).val() == 'price_less')
            {
                $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-price-value').show();
                $('#pfw-bulk-action-target-where-price-value').attr('required', 'required');
                $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-stock-value').hide();
                $('#pfw-bulk-action-target-where-stock-value').removeAttr('required');
            } else if ($(this).val() == 'stock_greater' || $(this).val() == 'stock_less')
            {
                $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-price-value').hide();
                $('#pfw-bulk-action-target-where-price-value').removeAttr('required');
                $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-stock-value').show();
                $('#pfw-bulk-action-target-where-stock-value').attr('required', 'required');
            } else
            {
                $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-price-value').hide();
                $('#pfw-bulk-action-target-where-price-value').removeAttr('required');
                $('.goopter-advanced-paypal-complete-payments-for-woocommerce-shipping-tools-bulk-action-section.pfw-bulk-action-target-where-stock-value').hide();
                $('#pfw-bulk-action-target-where-stock-value').removeAttr('required');
            }
        }
    });

    jQuery('.goopter_enable_notifyurl').change(function () {
        var express_notifyurl = jQuery('.goopter_notifyurl').closest('tr');
        if (jQuery(this).is(':checked')) {
            express_notifyurl.show();
        } else {
            express_notifyurl.hide();
        }
    }).change();

    jQuery('.order_cancellations').change(function () {
        var email_notify_order_cancellations = jQuery('.email_notify_order_cancellations').closest('tr');
        if (jQuery(this).val() !== 'disabled') {
            email_notify_order_cancellations.show();
        } else {
            email_notify_order_cancellations.hide();
        }
    }).change();

    jQuery('#goopter_payment_action').change(function () {
        if (jQuery(this).val() == 'DoCapture') {
            if (goopter_admin.payment_action == 'Order') {
                jQuery("#goopter_paypal_capture_transaction_dropdown").show();
            }
        } else {
            jQuery("#goopter_paypal_capture_transaction_dropdown").hide();
        }

        if (jQuery(this).val() == 'DoAuthorization') {
            jQuery(".goopter_authorization_box").show();
        } else {
            jQuery(".goopter_authorization_box").hide();
        }

        if (jQuery(this).val() == 'DoCapture') {
            if (goopter_admin.payment_action != 'Order') {
                jQuery(".goopter_authorization_box").show();
            }
        }

        if (jQuery(this).val() == 'DoVoid') {
            jQuery("#goopter_paypal_dovoid_transaction_dropdown").show();
        } else {
            jQuery("#goopter_paypal_dovoid_transaction_dropdown").hide();
        }

        if (jQuery(this).val() == 'DoReauthorization') {
            jQuery("#goopter_paypal_doreauthorization_transaction_dropdown").show();
        } else {
            jQuery("#goopter_paypal_doreauthorization_transaction_dropdown").hide();
        }
        if (jQuery(this).val().length === 0) {
            jQuery('#goopter_payment_submit_button').hide();
            return false;
        } else {
            jQuery('#goopter_payment_submit_button').show();
        }
    });

    jQuery('#goopter_ppcp_payment_action').change(function () {
        if (jQuery(this).val() === 'refund') {
            jQuery('.goopter_ppcp_refund_box').show();
            jQuery('.goopter_ppcp_capture_box').hide();
            jQuery('.goopter_ppcp_void_box').hide();
        } else if (jQuery(this).val() === 'capture') {
            jQuery('.goopter_ppcp_capture_box').show();
            jQuery('.goopter_ppcp_refund_box').hide();
            jQuery('.goopter_ppcp_void_box').hide();
        } else if (jQuery(this).val() === 'void') {
            jQuery('.goopter_ppcp_capture_box').hide();
            jQuery('.goopter_ppcp_refund_box').hide();
            jQuery('.goopter_ppcp_void_box').show();
        } else {
            jQuery('.goopter_ppcp_capture_box').hide();
            jQuery('.goopter_ppcp_refund_box').hide();
            jQuery('.goopter_ppcp_void_box').hide();
        }
        if (jQuery(this).val().length === 0) {
            jQuery('#goopter_ppcp_payment_submit_button').hide();
            return false;
        } else {
            jQuery('#goopter_ppcp_payment_submit_button').show();
        }
    }).change();
    jQuery("#goopter_ppcp_payment_submit_button").click(function (event) {
        if (jQuery('#is_ppcp_submited').val() === 'no') {
            jQuery('#is_ppcp_submited').val('yes');
            var r = confirm('Are you sure?');
            if (r === true) {
                jQuery("#goopter-ppcp-order-action").block({message: null, overlayCSS: {background: "#fff", opacity: .6}});
                return r;
            } else {
                jQuery('#is_ppcp_submited').val('no');
                jQuery("#goopter-ppcp-order-action").unblock();
                event.preventDefault();
                return r;
            }
        }
    });

    jQuery('.admin_smart_button_preview').change(function () {
        display_goopter_smart_button();
    });

    display_goopter_smart_button();

    function display_goopter_smart_button() {
        if ($('#woocommerce_paypal_express_testmode').length) {
            if (jQuery('#woocommerce_paypal_express_testmode').is(':checked')) {
                var api_username = ($('#woocommerce_paypal_express_sandbox_api_username').val().length > 0) ? $('#woocommerce_paypal_express_sandbox_api_username').val() : $('#woocommerce_paypal_express_sandbox_api_username').text();
                var api_password = ($('#woocommerce_paypal_express_sandbox_api_password').val().length > 0) ? $('#woocommerce_paypal_express_sandbox_api_password').val() : $('#woocommerce_paypal_express_sandbox_api_password').text();
                var api_signature = ($('#woocommerce_paypal_express_sandbox_api_signature').val().length > 0) ? $('#woocommerce_paypal_express_sandbox_api_signature').val() : $('#woocommerce_paypal_express_sandbox_api_signature').text();
            } else {
                var api_username = ($('#woocommerce_paypal_express_api_username').val().length > 0) ? $('#woocommerce_paypal_express_api_username').val() : $('#woocommerce_paypal_express_api_username').text();
                var api_password = ($('#woocommerce_paypal_express_api_password').val().length > 0) ? $('#woocommerce_paypal_express_api_password').val() : $('#woocommerce_paypal_express_api_password').text();
                var api_signature = ($('#woocommerce_paypal_express_api_signature').val().length > 0) ? $('#woocommerce_paypal_express_api_signature').val() : $('#woocommerce_paypal_express_api_signature').text();
            }
        } else {
            return false;
        }
        if (api_username.length === 0 || api_password.length === 0 || api_signature.length === 0) {
            return false;
        }

        jQuery(".display_smart_button_previews").html('');
        var goopter_height = jQuery("#woocommerce_paypal_express_button_height").val();
        var goopter_color = jQuery("#woocommerce_paypal_express_button_color").val();
        var goopter_shape = jQuery("#woocommerce_paypal_express_button_shape").val();
        var goopter_label = jQuery("#woocommerce_paypal_express_button_label").val();
        var goopter_layout = jQuery("#woocommerce_paypal_express_button_layout").val();
        var goopter_tagline = jQuery("#woocommerce_paypal_express_button_tagline").val();
        var button_size = $('#woocommerce_paypal_express_button_size').val();
        if (goopter_layout === 'vertical') {
            goopter_tagline = '';
        }
        var style_object = {
            color: goopter_color,
            shape: goopter_shape,
            label: goopter_label,
            layout: goopter_layout,
            tagline: (goopter_tagline === "true") ? true : false
        };
        if (goopter_height !== '') {
            style_object['height'] = parseInt(goopter_height);
        }

        $(".display_smart_button_previews").removeClass("goopter_horizontal_small goopter_horizontal_medium goopter_horizontal_large goopter_vertical_small goopter_vertical_medium goopter_vertical_large");
        $('.display_smart_button_previews').addClass('goopter_' + goopter_layout + '_' + button_size);

        if (typeof paypal !== 'undefined') {
            paypal.Buttons({
                style: style_object
            }).render('.display_smart_button_previews');
        }
    }

    jQuery('.show-on-product-page').change(function () {
        var express_default_enable = jQuery('.enable-newly-products-bydefault').closest('tr');
        if (jQuery(this).is(':checked')) {
            express_default_enable.show();
        } else {
            express_default_enable.hide();
        }
    }).change();
});