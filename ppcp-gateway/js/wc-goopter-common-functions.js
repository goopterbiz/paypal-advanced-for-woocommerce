const goopterOrder = {
    productAddToCart: true,
    lastApiResponse: null,
    ppcp_address: [],
    isCheckoutPage: () => {
        return 'checkout' === goopter_ppcp_manager.page;
    },
    isProductPage: () => {
        return 'product' === goopter_ppcp_manager.page;
    },
    isCartPage: () => {
        return 'cart' === goopter_ppcp_manager.page;
    },
    isSale: () => {
        return 'capture' === goopter_ppcp_manager.paymentaction;
    },
    isOrderPayPage: () => {
        const url = new URL(window.location.href);
        return url.searchParams.has('pay_for_order');
    },
    isOrderCompletePage: () => {
        const url = new URL(window.location.href);
        //  && url.searchParams.has('paypal_payer_id')
        return url.searchParams.has('paypal_order_id');
    },
    getSelectedPaymentMethod: () => {
        if (jQuery('input[name="payment_method"]').length) {
            return jQuery('input[name="payment_method"]:checked').val();
        } else if (jQuery('input[name="radio-control-wc-payment-method-options"]').length) {
            return jQuery('input[name="radio-control-wc-payment-method-options"]:checked').val();
        }
    },
    isApplePayPaymentMethodSelected: () => {
        return goopterOrder.getSelectedPaymentMethod() === 'goopter_ppcp_apple_pay';
    },
    isPpcpPaymentMethodSelected: () => {
        return goopterOrder.getSelectedPaymentMethod() === 'goopter_ppcp';
    },
    isCCPaymentMethodSelected: () => {
        return goopterOrder.getSelectedPaymentMethod() === 'goopter_ppcp_cc';
    },
    isGooglePayPaymentMethodSelected: () => {
        return goopterOrder.getSelectedPaymentMethod() === 'goopter_ppcp_google_pay';
    },
    isGoopterPpcpPaymentMethodSelected: () => {
        let paymentMethod = goopterOrder.getSelectedPaymentMethod();
        return paymentMethod === 'goopter_ppcp' || paymentMethod === 'goopter_ppcp_apple_pay' || paymentMethod === 'goopter_ppcp_google_pay';
    },
    isGoopterPpcpAdditionalPaymentMethodSelected: () => {
        let paymentMethod = goopterOrder.getSelectedPaymentMethod();
        return paymentMethod === 'goopter_ppcp_apple_pay' || paymentMethod === 'goopter_ppcp_google_pay';
    },
    isGoopterPaymentMethodSelected: () => {
        let paymentMethod = goopterOrder.getSelectedPaymentMethod();
        return paymentMethod === 'goopter_ppcp' || paymentMethod === 'goopter_ppcp_apple_pay' || paymentMethod === 'goopter_ppcp_google_pay';
    },
    isSavedPaymentMethodSelected: () => {
        let paymentMethod = goopterOrder.getSelectedPaymentMethod();
        let paymentToken = jQuery('input[name="wc-' + paymentMethod + '-payment-token"]:checked');
        if (paymentToken.length) {
            let val = paymentToken.val();
            if (typeof val !== 'undefined' && val !== 'new') {
                return true;
            }
        }
        return false;
    },
    isApplePayEnabled: () => {
        return goopter_ppcp_manager.apple_sdk_url !== "";
    },
    isGooglePayEnabled: () => {
        return goopter_ppcp_manager.google_sdk_url !== "";
    },
    getConstantValue: (constantName, defaultValue) => {
        return goopter_ppcp_manager.constants && goopter_ppcp_manager.constants[constantName] ? goopter_ppcp_manager.constants[constantName] : defaultValue;
    },
    getCheckoutSelectorCss: () => {
        let checkoutSelector = '.woocommerce';
        if (goopterOrder.isCheckoutPage()) {
            if (goopter_ppcp_manager.is_pay_page === 'yes') {
                checkoutSelector = 'form#order_review';
            } else {
                checkoutSelector = 'form.checkout';
            }
        } else if (goopter_ppcp_manager.page === 'add_payment_method') {
            checkoutSelector = 'form#add_payment_method';
        }
        if (jQuery(checkoutSelector).length === 0) {
            checkoutSelector = 'form.wc-block-checkout__form';
        }

        return checkoutSelector;
    },
    getWooNoticeAreaSelector: () => {
        let wooNoticeClass = '.woocommerce-notices-wrapper:first';
        // On some step checkout pages (e.g CheckoutWC) there are different notice wrappers under each form so this adds support to display in relevant section
        const checkoutFormSelector = goopterOrder.getCheckoutSelectorCss();
        if (jQuery(checkoutFormSelector).find(wooNoticeClass).length && jQuery(checkoutFormSelector).find(wooNoticeClass).is(':visible')) {
            return `${checkoutFormSelector} ${wooNoticeClass}`;
        }
        if (jQuery(wooNoticeClass).length) {
            return wooNoticeClass;
        }
        return goopterOrder.getCheckoutSelectorCss();
    },
    scrollToWooCommerceNoticesSection: () => {
        let scrollElement = jQuery('.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout');
        if (!scrollElement.length) {
            scrollElement = jQuery('form.checkout');
        }
        if (!scrollElement.length) {
            scrollElement = jQuery('form#order_review');
        }
        if (scrollElement.length) {
            jQuery('html, body').animate({
                scrollTop: (scrollElement.offset().top - 100)
            }, 1000);
        }
    },
    updateWooCheckoutFormNonce: (nonce) => {
        goopter_ppcp_manager.woocommerce_process_checkout = nonce;
        jQuery("#woocommerce-process-checkout-nonce").val(nonce);
    },
    createSmartButtonOrder: ({goopter_ppcp_button_selector, errorLogId}) => {
        return goopterOrder.createOrder({goopter_ppcp_button_selector, errorLogId}).then((data) => {
            goopterOrder.hideProcessingSpinner();
            return data.orderID;
        });
    },
    createOrder: ({goopter_ppcp_button_selector, billingDetails, shippingDetails, apiUrl, errorLogId, callback}) => {
        if (typeof apiUrl == 'undefined') {
            apiUrl = goopter_ppcp_manager.create_order_url;
        }
        goopterOrder.lastApiResponse = null;
        let formSelector = goopterOrder.getWooFormSelector();
        goopterOrder.removeError();
        let formData;
        let is_from_checkout = goopterOrder.isCheckoutPage();
        let is_from_product = goopterOrder.isProductPage();
        let billingField = null;
        let shippingField = null;
        if (billingDetails) {
            billingField = jQuery('<input>', {
                type: 'hidden',
                name: 'billing_address_source',
                value: JSON.stringify(billingDetails)
            });
        }
        if (shippingDetails) {
            shippingField = jQuery('<input>', {
                type: 'hidden',
                name: 'shipping_address_source',
                value: JSON.stringify(shippingDetails)
            });
        }
        let topCheckoutSelectors = ['#goopter_ppcp_checkout_top', '#goopter_ppcp_checkout_top_google_pay', '#goopter_ppcp_checkout_top_apple_pay'];
        if (is_from_checkout && topCheckoutSelectors.indexOf(goopter_ppcp_button_selector) > -1) {
            formData = '';
        } else {
            if (is_from_product) {
                jQuery(formSelector).find('input[name=goopter_ppcp-add-to-cart]').remove();
                if (goopterOrder.productAddToCart) {
                    jQuery('<input>', {
                        type: 'hidden',
                        name: 'goopter_ppcp-add-to-cart',
                        value: jQuery("[name='add-to-cart']").val()
                    }).appendTo(formSelector);
                    goopterOrder.productAddToCart = false;
                }
            }
            if (billingField) {
                jQuery(formSelector).find('input[name=billing_address_source]').remove();
                billingField.appendTo(formSelector);
            }
            if (shippingField) {
                jQuery(formSelector).find('input[name=shipping_address_source]').remove();
                shippingField.appendTo(formSelector);
            }
            formData = jQuery(formSelector).serialize();
            if (formData === '') {
                formData = 'goopter_ppcp_payment_method_title=' + jQuery('#goopter_ppcp_payment_method_title').val();
                if (goopterOrder.ppcp_address !== null && goopterOrder.ppcp_address !== undefined && goopterOrder.ppcp_address !== '') {
                    formData += "&woocommerce-process-checkout-nonce=" + goopter_ppcp_manager.woocommerce_process_checkout + "&address=" + JSON.stringify(goopterOrder.ppcp_address);
                }
            } else {
                if (goopterOrder.ppcp_address !== null && goopterOrder.ppcp_address !== undefined && goopterOrder.ppcp_address !== '') {
                    formData += "&woocommerce-process-checkout-nonce=" + goopter_ppcp_manager.woocommerce_process_checkout + "&address=" + JSON.stringify(goopterOrder.ppcp_address);
                }
            }
        }
        goopterJsErrorLogger.addToLog(errorLogId, {
            context: 'api_request',
            url: apiUrl,
            method: 'POST',
            body: formData,
            time: new Date()
        });
        return fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData
        }).then(async function (res) {
            goopterJsErrorLogger.addToLog(errorLogId, {
                context: 'api_response',
                response: res,
                redirected: res.redirected,
                status: res.status,
                time: new Date()
            });
            if (res.redirected) {
                window.location.href = res.url;
            } else {
                goopterOrder.lastApiResponse = await res.clone().text();
                return res.json();
            }
        }).then(function (data) {
            if (typeof callback === 'function') {
                callback(data);
                return;
            }
            if (typeof data.success !== 'undefined') {
                let messages = data.data.messages ? data.data.messages : data.data;
                if ('string' !== typeof messages) {
                    messages = messages.map(function (message) {
                        return '<li>' + message + '</li>';
                    }).join('');
                    if (localizedMessages.error_message_checkout_validation !== "") {
                        messages = '<li>' + localizedMessages.error_message_checkout_validation + '</li>' + messages;
                    }
                } else {
                    messages = '<li>' + messages + '</li>';
                }
                throw messages;
            } else {
                return data;
            }
        }).then((data) => {
            if (goopterOrder.isCheckoutPage() && typeof data.nonce !== 'undefined') {
                goopterOrder.updateWooCheckoutFormNonce(data.nonce);
            }
            return data;
        });
    },
    approveOrder: ({orderID, payerID, errorLogId}) => {
        if (goopterOrder.isCheckoutPage()) {
            goopterOrder.checkoutFormCapture({payPalOrderId: orderID, errorLogId})
        } else {
            if (goopter_ppcp_manager.is_skip_final_review === 'yes') {
                window.location.href = goopter_ppcp_manager.direct_capture + '&paypal_order_id=' + orderID + '&paypal_payer_id=' + payerID + '&from=' + goopter_ppcp_manager.page;
            } else {
                window.location.href = goopter_ppcp_manager.checkout_url + '&paypal_order_id=' + orderID + (payerID ? '&paypal_payer_id=' + payerID : '') + '&from=' + goopter_ppcp_manager.page;
            }
    }
    },
    shippingAddressUpdate: (shippingDetails, billingDetails, errorLogId) => {
        return goopterOrder.createOrder({apiUrl: goopter_ppcp_manager.shipping_update_url, shippingDetails, billingDetails, errorLogId});
    },
    triggerPaymentCancelEvent: () => {
        jQuery(document.body).trigger('goopter_paypal_oncancel');
    },
    onCancel: () => {
        goopterOrder.triggerPaymentCancelEvent();
        if (!goopterOrder.isCheckoutPage()) {
            goopterOrder.showProcessingSpinner();
            if (!goopterOrder.isProductPage()) {
                window.location.reload();
            }
        }
    },
    prepareWooErrorMessage: (messages) => {
        return '<ul class="woocommerce-error">' + messages + '</ul>'
    },
    removeError: () => {
        jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
    },
    showError: (errorMessage) => {
        errorMessage = goopterOrder.prepareWooErrorMessage(errorMessage);
        let errorMessageLocation = goopterOrder.getWooNoticeAreaSelector();
        jQuery(errorMessageLocation).prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + errorMessage + '</div>');
        jQuery(errorMessageLocation).removeClass('processing').unblock();
        if (!jQuery(errorMessageLocation).is(':visible'))
            jQuery(errorMessageLocation).css('display', 'block');
        jQuery(errorMessageLocation).find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
        goopterOrder.scrollToWooCommerceNoticesSection();
    },
    showProcessingSpinner: (containerSelector) => {
        if (typeof containerSelector === 'undefined') {
            containerSelector = '.woocommerce';
        }
        if (jQuery('.wp-block-woocommerce-checkout-fields-block').length) {
            jQuery('.wp-block-woocommerce-checkout-fields-block #contact-fields, .wp-block-woocommerce-checkout-fields-block #billing-fields, .wp-block-woocommerce-checkout-fields-block #payment-method').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
        } else if (jQuery('.wp-block-woocommerce-cart').length) {
            jQuery('.wp-block-woocommerce-cart').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
        } else if (jQuery(containerSelector).length) {
            jQuery(containerSelector).block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});
        }

    },
    hideProcessingSpinner: (containerSelector) => {
        if (typeof containerSelector === 'undefined') {
            containerSelector = '.woocommerce';
        }
        if (jQuery('.wp-block-woocommerce-checkout-fields-block').length) {
            jQuery('.wc-block-components-checkout-place-order-button, .wp-block-woocommerce-checkout-fields-block #contact-fields, .wp-block-woocommerce-checkout-fields-block #billing-fields, .wp-block-woocommerce-checkout-fields-block #payment-method').unblock();
            jQuery('.wc-block-components-spinner').remove();
        } else if (jQuery('.wp-block-woocommerce-cart').length) {
            jQuery('.wp-block-woocommerce-cart').unblock();
        } else if (jQuery(containerSelector).length) {
            jQuery(containerSelector).unblock();
        }

    },
    handleCreateOrderError: (error, errorLogId) => {
        goopterOrder.hideProcessingSpinner();
        jQuery(document.body).trigger('goopter_paypal_onerror');
        let errorMessage = error.message ? error.message : error;
        if ((errorMessage.toLowerCase()).indexOf('expected an order id to be passed') > -1) {
            if ((errorMessage.toLowerCase()).indexOf('required fields') < 0) {
                errorMessage = localizedMessages.create_order_error;
            }
        } else if ((errorMessage.toLowerCase()).indexOf('unexpected token') > -1) {
            let lastErrorHtmlEncoded = jQuery("<textarea/>").text(goopterOrder.lastApiResponse).html();
            goopterJsErrorLogger.logJsError('InvalidJSON, Received Response: ' + lastErrorHtmlEncoded, errorLogId);
            errorMessage = '<li>' + localizedMessages.create_order_error + '</li>';
        }
        if (errorMessage !== '') {
            goopterOrder.showError(errorMessage);
        }
        goopterOrder.scrollToWooCommerceNoticesSection();
        if (goopterOrder.isCheckoutPage() === false) {
            //  window.location.href = window.location.href;
        }
    },
    isCardFieldEligible: () => {
        if (goopter_ppcp_manager.advanced_card_payments === 'yes') {
            return typeof goopter_paypal_sdk !== 'undefined' && typeof goopter_paypal_sdk.CardFields !== 'undefined'
                    ? goopter_paypal_sdk.CardFields().isEligible() === true
                    : false;
        }
        return false;
    },

    showPpcpPaymentMethods: () => {
        jQuery('#goopter_ppcp_checkout, #goopter_ppcp_checkout_apple_pay, #goopter_ppcp_checkout_google_pay').hide();
        if (goopterOrder.isApplePayPaymentMethodSelected()) {
            jQuery('#goopter_ppcp_checkout_apple_pay').show();
        } else if (goopterOrder.isGooglePayPaymentMethodSelected()) {
            jQuery('#goopter_ppcp_checkout_google_pay').show();
        } else {
            jQuery('#goopter_ppcp_checkout').show();
        }
    },
    hidePpcpPaymentMethods: () => {
        jQuery('#goopter_ppcp_checkout, #goopter_ppcp_checkout_apple_pay, #goopter_ppcp_checkout_google_pay').hide();
    },
    hideShowPlaceOrderButton: () => {
        let selectedPaymentMethod = goopterOrder.getSelectedPaymentMethod();
        let isGtPpcpMethodSelected = goopterOrder.isGoopterPpcpPaymentMethodSelected();
        if (isGtPpcpMethodSelected === true) {
            jQuery('.wcf-pre-checkout-offer-action').val('');
        }
        if (goopterOrder.isCardFieldEligible() === false) {
            jQuery('.payment_method_goopter_ppcp_cc').hide();
        }
        if ((isGtPpcpMethodSelected === true && goopter_ppcp_manager.is_checkout_disable_smart_button === 'no') ||
                goopterOrder.isGoopterPpcpAdditionalPaymentMethodSelected()) {
            showHidePlaceOrderBtn();
            goopterOrder.showPpcpPaymentMethods();
        } else {
            goopterOrder.hidePpcpPaymentMethods();
            showHidePlaceOrderBtn();
        }
    },
    createHiddenInputField: ({fieldId, fieldName, fieldValue, fieldType, appendToSelector}) => {
        if (jQuery('#' + fieldId).length > 0) {
            jQuery('#' + fieldId).remove();
        }
        jQuery('<input>', {
            type: typeof fieldType == 'undefined' ? 'hidden' : fieldType,
            id: fieldId,
            name: fieldName,
            value: fieldValue
        }).appendTo(appendToSelector)
    },
    getWooFormSelector: () => {
        let payment_method_element_selector = '';
        if (goopterOrder.isProductPage()) {
            payment_method_element_selector = 'form.cart';
        } else if (goopterOrder.isCartPage()) {
            payment_method_element_selector = 'form.woocommerce-cart-form';
        } else if (goopterOrder.isCheckoutPage()) {
            payment_method_element_selector = goopterOrder.getCheckoutSelectorCss();
        }
        if (jQuery(payment_method_element_selector).length === 0) {
            payment_method_element_selector = 'form.wc-block-checkout__form';
        }
        return payment_method_element_selector;
    },
    setPaymentMethodSelector: (paymentMethod) => {
        let payment_method_element_selector = goopterOrder.getWooFormSelector();
        var element = document.querySelector(payment_method_element_selector);
        if (!element) {
            payment_method_element_selector = document.body; // Use body as the default if appendToSelector doesn't exist
        }
        goopterOrder.createHiddenInputField({
            fieldId: 'goopter_ppcp_payment_method_title',
            fieldName: 'goopter_ppcp_payment_method_title',
            fieldValue: paymentMethod,
            appendToSelector: payment_method_element_selector
        });
    },
    renderSmartButton: () => {
        jQuery.each(goopter_ppcp_manager.button_selector, function (key, goopter_ppcp_button_selector) {
            if (!jQuery(goopter_ppcp_button_selector).length || jQuery(goopter_ppcp_button_selector).children().length) {
                return;
            }
            if (typeof goopter_paypal_sdk === 'undefined') {
                return;
            }
            let goopter_ppcp_style = {
                layout: goopter_ppcp_manager.style_layout,
                color: goopter_ppcp_manager.style_color,
                shape: goopter_ppcp_manager.style_shape,
                label: goopter_ppcp_manager.style_label
            };
            if (goopter_ppcp_manager.style_height !== '') {
                goopter_ppcp_style['height'] = parseInt(goopter_ppcp_manager.style_height);
            }
            if (goopter_ppcp_manager.style_layout !== 'vertical') {
                goopter_ppcp_style['tagline'] = (goopter_ppcp_manager.style_tagline === 'yes') ? true : false;
            }
            let errorLogId = null;
            goopter_paypal_sdk.Buttons({
                style: goopter_ppcp_style,
                createOrder: function (data, actions) {
                    goopterOrder.showProcessingSpinner();
                    errorLogId = goopterJsErrorLogger.generateErrorId();
                    goopterJsErrorLogger.addToLog(errorLogId, 'PayPal Smart Button Payment Started');
                    return goopterOrder.createSmartButtonOrder({
                        goopter_ppcp_button_selector, errorLogId
                    })
                },
                onApprove: function (data, actions) {
                    goopterOrder.showProcessingSpinner();
                    goopterOrder.approveOrder({...data, errorLogId});
                },
                onCancel: function (data, actions) {
                    goopterOrder.hideProcessingSpinner();
                    goopterOrder.onCancel();
                },
                onClick: function (data, actions) {
                    goopterOrder.setPaymentMethodSelector(data.fundingSource);
                },
                onError: function (err) {
                    goopterOrder.handleCreateOrderError(err, errorLogId);
                }
            }).render(goopter_ppcp_button_selector);
        });
        if (goopterOrder.isApplePayEnabled()) {
            jQuery.each(goopter_ppcp_manager.apple_pay_btn_selector, function (key, goopter_ppcp_apple_button_selector) {
                (new ApplePayCheckoutButton()).render(goopter_ppcp_apple_button_selector);
            });
        }
        if (goopterOrder.isGooglePayEnabled()) {
            jQuery.each(goopter_ppcp_manager.google_pay_btn_selector, function (key, goopter_ppcp_google_button_selector) {
                (new GooglePayCheckoutButton()).render(goopter_ppcp_google_button_selector);
            });
        }
    },
    checkoutFormCapture: ({checkoutSelector, payPalOrderId, errorLogId}) => {
        if (typeof checkoutSelector === 'undefined') {
            checkoutSelector = goopterOrder.getCheckoutSelectorCss();
        }
        let captureUrl = goopter_ppcp_manager.cc_capture + "&paypal_order_id=" + payPalOrderId + "&woocommerce-process-checkout-nonce=" + goopter_ppcp_manager.woocommerce_process_checkout + "&is_pay_page=" + goopter_ppcp_manager.is_pay_page;
        let data;
        if (goopterOrder.isCheckoutPage()) {
            data = jQuery(checkoutSelector).serialize();
        }
        // Fluid-Checkout compatibility to stop showing the Leave popup on beforeunload event
        if (typeof window.can_update_checkout !== 'undefined') {
            jQuery(checkoutSelector).on('checkout_place_order_' + goopterOrder.getSelectedPaymentMethod(), function () {
                return false;
            });
            jQuery(checkoutSelector).submit();
        }
        fetch(captureUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
        }).then(function (res) {
            return res.json();
        }).then(function (response) {
            if (response?.data?.result == 'success') {
                window.location.href = response.data.redirect;
            } else {
                throw {message : response?.data?.message ?? 'This payment was unable to be processed successfully. Please try again with another payment method.'};
            }
        }).catch((error) => {
            jQuery(checkoutSelector).removeClass('processing paypal_cc_submiting CardFields createOrder');
            goopterOrder.handleCreateOrderError(error, errorLogId);
            goopterOrder.hideProcessingSpinner('#customer_details, .woocommerce-checkout-review-order');
        });
    },
    renderHostedButtons: () => {
        if (typeof goopter_paypal_sdk === 'undefined') {
            return;
        }
        let checkoutSelector = goopterOrder.getCheckoutSelectorCss();
        if (jQuery(checkoutSelector).is('.CardFields')) {
            return false;
        }
        let spinnerSelectors = checkoutSelector;
        jQuery(checkoutSelector).addClass('CardFields');
        let errorLogId = null;
        const cardFields = goopter_paypal_sdk.CardFields({
            createOrder: function (data, actions) {
                jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
                if (!jQuery(checkoutSelector).hasClass('createOrder')) {
                    errorLogId = goopterJsErrorLogger.generateErrorId();
                    goopterJsErrorLogger.addToLog(errorLogId, 'Advanced CC Payment Started');
                    jQuery(checkoutSelector).addClass('createOrder');
                    goopterOrder.setPaymentMethodSelector(data.paymentSource);
                    return goopterOrder.createOrder({errorLogId}).then(function (data) {
                        return data.orderID;
                    }).catch((error) => {
                        goopterOrder.showError(error);
                        return '';
                    });
                }
            },
            onApprove: function (data, actions) {
                if (data.orderID) {
                    goopterOrder.approveOrder({orderID: data.orderID, payerID: '', errorLogId});
                }
            },
            onError: function (err) {
                errorLogId = goopterJsErrorLogger.generateErrorId();
                err.message = 'This payment was unable to be processed successfully. Please try again with another payment method.'
                jQuery(checkoutSelector).removeClass('createOrder');
                goopterOrder.handleCreateOrderError(err, errorLogId);
            },
            style: {
                'input': {
                    'font-size': goopter_ppcp_manager.card_style_props.font_size,
                    'color': goopter_ppcp_manager.card_style_props.color,
                    'font-weight': goopter_ppcp_manager.card_style_props.font_weight,
                    'font-style': goopter_ppcp_manager.card_style_props.font_style,
                    'padding': goopter_ppcp_manager.card_style_props.padding,
                }
            },
            inputEvents: {
                onChange: function (data) {
                    if (data.cards && data.cards.length > 0) {
                        let cardname = data.cards[0].type.replace("master-card", "mastercard")
                                .replace("american-express", "amex")
                                .replace("diners-club", "dinersclub")
                                .replace("-", "");

                        if (jQuery.inArray(cardname, goopter_ppcp_manager.disable_cards) !== -1) {
                            jQuery('#goopter_ppcp_cc-card-number').addClass('ppcp-invalid-cart');
                            goopterOrder.showError(localizedMessages.card_not_supported);
                        } else {
                            jQuery('#goopter_ppcp_cc-card-number').removeClass().addClass(cardname);
                        }
                    }
                }
            }
        });
        if (cardFields.isEligible() && jQuery('#goopter_ppcp_cc-card-number').length > 0) {
            cardFields.NumberField().render("#goopter_ppcp_cc-card-number");
            cardFields.ExpiryField().render("#goopter_ppcp_cc-card-expiry");
            cardFields.CVVField().render("#goopter_ppcp_cc-card-cvc");
        } else {
            jQuery('.payment_method_goopter_ppcp_cc').hide();
        }
        jQuery(document.body).on('submit_paypal_cc_form', (event) => {
            event.preventDefault();
            cardFields.getState().then((data) => {
                if (data.isFormValid) {
                    goopterOrder.showProcessingSpinner(spinnerSelectors);
                    cardFields.submit().then(() => {
                    }).catch((error) => {
                        console.log(error);
                    });
                } else if (!data.isFormValid) {
                    jQuery(checkoutSelector).removeClass('processing paypal_cc_submiting CardFields createOrder');
                    goopterOrder.hideProcessingSpinner(spinnerSelectors);
                    goopterOrder.showError(localizedMessages.fields_not_valid);
                    return;
                } else if (data.errors) {
                    console.log(data);
                    data.errors.forEach(error => {
                        console.log(error);
                    });
                }
            });
        });

        jQuery(document.body).on('click', '#goopter_ppcp_cc_button', function() {
            jQuery('.goopter_ppcp_cc_container').removeClass('goopter_ppcp_cc_container_hide');
        });

        jQuery(document.body).on('click', '.goopter_ppcp_cc_close_button', function() {
            jQuery('.goopter_ppcp_cc_container').addClass('goopter_ppcp_cc_container_hide');
        });

        jQuery(document.body).on('click', '#goopter_ppcp_cc-card-submit-button', function() {
            jQuery(document.body).trigger('submit_paypal_cc_form');
        });

        jQuery(window).on('load resize', function(event) {
            setTimeout(function() {
                const width = jQuery('.paypal-buttons').width();
                let height = 55;
                let fontSize = 10;
                switch (true) {
                    case (width <= 199): {
                        height = 25;
                        fontSize = 10;
                        break;
                    }
                    case (width <= 299): {
                        height = 35;
                        fontSize = 13;
                        break;
                    }
                    case (width <= 499): {
                        height = 45;
                        fontSize = 16;
                        break;
                    }
                    default:
                        height = 55;
                        fontSize = 20;
                }
                jQuery('#goopter_ppcp_cc_button').width(width).height(height);
                jQuery('#goopter_ppcp_cc_button').css('display', 'flex');
                jQuery('.goopter_ppcp_cc_button_label').css('font-size', fontSize + 'px');
            }, event.type == "resize" ? 300 : 0)
        });

        // if the product has variations, then trigger the resize event on variation change
        jQuery('.variations_form').on('woocommerce_variation_has_changed input change', function() {
            jQuery(window).trigger('resize');
        });

        if (document.readyState === 'complete') {
            jQuery(window).trigger('resize');
        }
    },
    applePayDataInit: async () => {
        // This function is deprecated as we don't use it because its already loaded in environment
        if (goopterOrder.isApplePayEnabled()) {
            // block the apple pay button UI to make sure nobody can click it while its updating.
            goopterOrder.showProcessingSpinner('#goopter_ppcp_cart_apple_pay');
            // trigger an ajax call to update the total amount, in case there is no shipping required object
            let response = await goopterOrder.shippingAddressUpdate({});
            goopterOrder.hideProcessingSpinner('#goopter_ppcp_cart_apple_pay');
            if (typeof response.totalAmount !== 'undefined') {
                // successful response
                goopter_ppcp_manager.goopter_cart_totals = response;
            } else {
                // in case of unsuccessful response, refresh the page.
                window.location.reload();
            }
        }
    },
    getCartDetails: () => {
        return goopter_ppcp_manager.goopter_cart_totals;
    },
    updateCartTotalsInEnvironment: (data) => {
        let cartTotals;
        let response = {renderNeeded: true};
        if (data) {
            cartTotals = data;
        } else if (jQuery('#goopter_cart_totals').length) {
            cartTotals = JSON.parse(jQuery('#goopter_cart_totals').text().replace(/[“”″]/g, '"'));
        }
        if (cartTotals) {
            // Check if the currency changed then reload the JS SDK with latest currency
            const updateCartTotal = () => {
                goopter_ppcp_manager.goopter_cart_totals = cartTotals;
                jQuery(document.body).trigger('goopter_cart_total_updated');
            };
            const cartDetails = goopterOrder.getCartDetails();
            if (cartDetails.currencyCode !== cartTotals.currencyCode) {
                let checkoutSelector = goopterOrder.getCheckoutSelectorCss();
                goopterOrder.showProcessingSpinner(checkoutSelector);
                goopter_ppcp_manager.paypal_sdk_url = pfwUrlHelper.setQueryParam('currency', cartTotals.currencyCode, goopter_ppcp_manager.paypal_sdk_url);
                window.goopterLoadAsyncLibs(() => {
                    updateCartTotal();
                    goopterOrder.renderPaymentButtons();
                    goopterOrder.hideProcessingSpinner(checkoutSelector);
                }, () => {
                    console.log('Unable to refresh the PayPal Lib');
                    goopterOrder.showError('<li>' + localizedMessages.currency_change_js_load_error + '</li>');
                    goopterOrder.hideProcessingSpinner(checkoutSelector);
                });
                response.renderNeeded = false;
            } else {
                updateCartTotal();
            }
        }
        return response;
    },
    addPaymentMethodAdvancedCreditCard: () => {
        if (typeof goopter_paypal_sdk === 'undefined') {
            return;
        }
        let addPaymentMethodForm = goopterOrder.getCheckoutSelectorCss();
        const cardFields = goopter_paypal_sdk.CardFields({
            createVaultSetupToken: async () => {
                goopterOrder.showProcessingSpinner(addPaymentMethodForm);
                const result = await fetch(goopter_ppcp_manager.goopter_ppcp_cc_setup_tokens, {
                    method: "POST"
                });
                const {id} = await result.json();
                return id;
            },
            onApprove: async (data) => {
                const approvalTokenIdParamName = goopterOrder.getConstantValue('approval_token_id');
                const endpoint = goopter_ppcp_manager.advanced_credit_card_create_payment_token;
                const url = `${endpoint}&${approvalTokenIdParamName}=${data.vaultSetupToken}`;
                fetch(url, {method: "POST"}).then(response => {
                    return response.json();
                }).then(data => {
                    window.location.href = data.redirect;
                }).catch(error => {
                    goopterOrder.showError(error);
                    goopterOrder.hideProcessingSpinner(addPaymentMethodForm);
                    console.error('An error occurred:', error);
                });
            },
            onError: (error) => {
                goopterOrder.hideProcessingSpinner(addPaymentMethodForm);
                goopterOrder.showError(error);
                console.error('Something went wrong:', error)
            }
        });
        if (cardFields.isEligible()) {
            cardFields.NameField().render("#ppcp-my-account-card-holder-name");
            cardFields.NumberField().render("#ppcp-my-account-card-number");
            cardFields.ExpiryField().render("#ppcp-my-account-expiration-date");
            cardFields.CVVField().render("#ppcp-my-account-cvv");
        } else {
            jQuery('.payment_method_goopter_ppcp_cc').hide();
        }

        jQuery(addPaymentMethodForm).unbind('submit').on('submit', (event) => {
            goopterOrder.removeError();
            if (goopterOrder.isCCPaymentMethodSelected() || goopterOrder.isPpcpPaymentMethodSelected()) {
                goopterOrder.showProcessingSpinner(addPaymentMethodForm);
                if (goopterOrder.isCCPaymentMethodSelected() === true) {
                    event.preventDefault();
                    cardFields.submit().then((hf) => {
                    }).catch((error) => {
                        goopterOrder.hideProcessingSpinner(addPaymentMethodForm);
                        goopterOrder.showError(error);
                        console.error("add_payment_method_submit_error:", error);
                    });
                }
            }
        });
    },
    queuedEvents: {},
    addEventsForCallback: (eventType, event, data) => {
        goopterOrder.queuedEvents[eventType] = {event, data};
    },
    dequeueEvent: (eventType) => {
        if (eventType in goopterOrder.queuedEvents) {
            delete goopterOrder.queuedEvents[eventType];
        }
    },
    isPendingEventTriggering: false,
    triggerPendingEvents: () => {
        goopterOrder.isPendingEventTriggering = true;
        for (let event in goopterOrder.queuedEvents) {
            if (goopterOrder.queuedEvents[event].data) {
                jQuery(document.body).trigger(event, [goopterOrder.queuedEvents[event].data]);
            } else {
                jQuery(document.body).trigger(event);
            }
        }
    },
    renderPaymentButtons: () => {
        goopterOrder.hideShowPlaceOrderButton();
        goopterOrder.renderSmartButton();
        if (goopterOrder.isCardFieldEligible() === true) {
            jQuery('#goopter_ppcp_cc-card-number iframe').length === 0 ? jQuery(goopterOrder.getCheckoutSelectorCss()).removeClass('CardFields') : null;
            jQuery('.checkout_cc_separator').show();
            jQuery('#wc-goopter_ppcp-cc-form').show();
            goopterOrder.renderHostedButtons();
        }
    },
    hooks: {
        handleWooEvents: () => {
            jQuery(document.body).on('updated_cart_totals payment_method_selected updated_checkout', function (event, data) {
                goopterOrder.dequeueEvent(event.type);

                let response;
                if (typeof data !== 'undefined' && typeof data["fragments"] !== 'undefined' && typeof data["fragments"]["goopter_payments_data"] !== "undefined") {
                    response = goopterOrder.updateCartTotalsInEnvironment(JSON.parse(data["fragments"]["goopter_payments_data"]));
                } else if (event.type === 'updated_cart_totals') {
                    response = goopterOrder.updateCartTotalsInEnvironment();
                }

                if (!response || response.renderNeeded) {

                    goopterOrder.renderPaymentButtons();
                }
            });
            jQuery(document.body).on('trigger_goopter_ppcp_cc', function (event) {
                goopterOrder.renderPaymentButtons();
            });
        },
        handleRaceConditionOnWooHooks: () => {
            jQuery(document.body).on('updated_cart_totals payment_method_selected updated_checkout ppcp_block_ready', function (event, data) {
                if (!goopterOrder.isPendingEventTriggering) {
                    goopterOrder.addEventsForCallback(event.type, event, data);
                }
            });
        },
        onPaymentCancellation: () => {
            jQuery(document.body).on('goopter_paypal_oncancel', function (event) {
                event.preventDefault();
                if (goopterOrder.isProductPage() && goopterOrder.productAddToCart === false) {
                    fetch(goopter_ppcp_manager.update_cart_oncancel, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: jQuery(goopterOrder.getWooFormSelector()).serialize()
                    }).then(function (res) {
                        return res.json();
                    }).then(function (data) {
                        window.location.reload();
                    });
                }
            });
        }
    },
    addPPCPCheckoutUpdatedEventListener: (billing, shippingData) => {
        const currentEventListeners = jQuery._data(document.body, "events");
        if (currentEventListeners["ppcp_checkout_updated"] && currentEventListeners["ppcp_checkout_updated"].length > 0) {
            jQuery(document.body).off('ppcp_checkout_updated');
        }
        jQuery(document.body).on(
            "ppcp_checkout_updated",
            function () {
                let address = {
                    billing: billing.billingAddress,
                    shipping: shippingData.shippingAddress,
                };
                goopterOrder.ppcp_address = address;
                goopterOrder.renderPaymentButtons();
            }
        );
    },
}

__ = wp.i18n.__;
const localizedMessages = {
    card_not_supported: __('Unfortunately, we do not support this credit card type. Please try another card type.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    fields_not_valid: __('Unfortunately, your credit card details are not valid. Please review the card details and try again.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    error_message_checkout_validation: __('Unable to create the order due to the following errors.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    expiry_date_placeholder: __('MM / YY', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    cvc_placeholder: __('CVC', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    empty_cart_message: __('Your shopping cart seems to be empty.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    total_amount_placeholder: __('Total Amount', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    apple_pay_pay_error: __('An error occurred while initiating the ApplePay payment.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    error_validating_merchant: __('This merchant is not enabled to process requested payment method. please contact website owner.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    general_error_message: __('We are unable to process your request at the moment, please contact website owner.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    shipping_amount_update_error: __('Unable to update the shipping amount.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    shipping_amount_pull_error: __('Unable to pull the shipping amount details based on selected address', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    currency_change_js_load_error: __('We encountered an issue loading the updated currency. Please refresh the page or contact support for assistance.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    create_order_error: __('Unable to create the order, please contact the support.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce'),
    create_order_error_with_content: __('Unable to create the order, please contact the support with following error message.', 'goopter-advanced-integration-for-paypal-complete-payments-and-for-woocommerce')
};

const pfwUrlHelper = {
    getUrlObject: (url) => {
        if (!url) {
            url = window.location.href;
        }
        return new URL(url);
    },
    setQueryParam: (name, value, url) => {
        url = pfwUrlHelper.getUrlObject(url);
        let searchParams = url.searchParams;
        searchParams.set(name, value);
        url.search = searchParams.toString();
        return url.toString();
    },
    getQueryParams: (url) => {
        url = pfwUrlHelper.getUrlObject(url);
        return url.searchParams;
    },
    removeQueryParam: (name, url) => {
        url = pfwUrlHelper.getUrlObject(url);
        let searchParams = url.searchParams;
        searchParams.delete(name);
        url.search = searchParams.toString();
        return url.toString();
    },
    removeAllParams: (url) => {
        url = pfwUrlHelper.getUrlObject(url);
        url.search = '';
        return url.toString();
    }
}
const goopterJsErrorLogger = {
    errorStackMeta: {},
    generateErrorId: () => {
        return Date.now() + Math.floor(Math.random() * 101);
    },
    addToLog: (errorLogId, metaData) => {
        if (typeof goopterJsErrorLogger.errorStackMeta[errorLogId] === 'undefined') {
            goopterJsErrorLogger.errorStackMeta[errorLogId] = [];
        }
        goopterJsErrorLogger.errorStackMeta[errorLogId].push(metaData);
    },
    getLogTrace: (errorLogId) => {
        return typeof goopterJsErrorLogger.errorStackMeta[errorLogId] !== 'undefined' ?
                goopterJsErrorLogger.errorStackMeta[errorLogId] : [];
    },
    logJsError: (error, errorLogId) => {
        fetch(goopter_ppcp_manager.handle_js_errors, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({error, logTrace: goopterJsErrorLogger.getLogTrace(errorLogId)}),
        }).then(function (res) {
            //alert(res.json());
        }).then(function (data) {
            //alert(data);
        });
    }
}
