function initSmartButtons() {
    let $ = jQuery;
    if (typeof goopter_ppcp_manager === 'undefined') {
        return false;
    }
    
    let checkoutSelector = goopterOrder.getCheckoutSelectorCss();
    if ($('.variations_form').length) {
            let div_to_hide_show = '#goopter_ppcp_product, #goopter_ppcp_product_google_pay, #goopter_ppcp_product_apple_pay, #goopter_ppcp_cc_product';
            
            // check if all variations are selected
            function areAllVariationsPicked() {
                let allSelected = true;
                $('.variations_form [name^="attribute_"]').each(function(){
                if (! this.value) {
                    allSelected = false;
                    return false; // break
                }
                });
                return allSelected;
            }

            $(div_to_hide_show).hide();
            var variationForm = $('.variations_form');
            var formEl        = variationForm[0]; 
            $('.variations_form').on('show_variation', function () {
                // additional check for custom variation fields
                if(formEl.checkValidity()) {
                    $(div_to_hide_show).show();
                }
            }).on('hide_variation', function () {
                if(!formEl.checkValidity()) {
                    $(div_to_hide_show).hide();
                }
            });
            // add event listener for input and change events to show/hide the buttons
            $('.variations_form').on('input change', function(){
                if(formEl.checkValidity() && areAllVariationsPicked()) {
                    $(div_to_hide_show).show();
                } else {
                    $(div_to_hide_show).hide();
                }
            });
    }

    if ($(document.body).hasClass('woocommerce-order-pay')) {
        $('#order_review').on('submit', function (event) {
            if (goopterOrder.isCardFieldEligible() === true) {
                event.preventDefault();
                if ($('input[name="wc-goopter_ppcp_cc-payment-token"]').length) {
                    if ('new' !== $('input[name="wc-goopter_ppcp_cc-payment-token"]:checked').val()) {
                        return true;
                    }
                }
                if ($(checkoutSelector).is('.paypal_cc_submiting')) {
                    return false;
                } else {
                    $(checkoutSelector).addClass('paypal_cc_submiting');
                    $(document.body).trigger('submit_paypal_cc_form');
                }
                return false;
            }
            return true;
        });
    }
    
    $(checkoutSelector).on('checkout_place_order_goopter_ppcp_cc', function (event) {
        if (goopterOrder.isCardFieldEligible() === true) {
            event.preventDefault();
            if ($('input[name="wc-goopter_ppcp_cc-payment-token"]').length) {
                if ('new' !== $('input[name="wc-goopter_ppcp_cc-payment-token"]:checked').val()) {
                    return true;
                }
            }
            if ($(checkoutSelector).is('.paypal_cc_submiting')) {
                return false;
            } else {
                $(checkoutSelector).addClass('paypal_cc_submiting');
                $(document.body).trigger('submit_paypal_cc_form');
            }
            return false;
        }
        return true;
    });

    setTimeout(function () {
        goopterOrder.hideShowPlaceOrderButton();
        goopterOrder.renderSmartButton();
        if (goopterOrder.isCardFieldEligible() === true) {
            if ($('#goopter_ppcp_cc-card-number iframe').length === 0) {
                $(goopterOrder.getCheckoutSelectorCss()).removeClass('CardFields');
            }
            $('.checkout_cc_separator').show();
            $('#wc-goopter_ppcp-cc-form').show();
            goopterOrder.renderHostedButtons();
        }
    }, 300);

    goopterOrder.updateCartTotalsInEnvironment();
    goopterOrder.hooks.onPaymentCancellation();
    goopterOrder.hooks.handleWooEvents();

    goopterOrder.triggerPendingEvents();

    $(document.body).on('removed_coupon_in_checkout', function () {
        window.location.href = window.location.href;
    });
}

(function () {
    'use strict';

    goopterOrder.hooks.handleRaceConditionOnWooHooks();

    const paypalSdkLoadCallback = () => {
        console.log('PayPal lib loaded, initialize buttons.');
        let scriptsToLoad = [];
        if (goopterOrder.isApplePayEnabled()) {
            let appleResolveOnLoad = new Promise((resolve) => {
                console.log('apple sdk loaded');
                resolve();
            });
            scriptsToLoad.push({
                url: goopter_ppcp_manager.apple_sdk_url,
                callback: appleResolveOnLoad
            });
        }

        if (goopterOrder.isGooglePayEnabled()) {
            let googleResolveOnLoad = new Promise((resolve) => {
                console.log('google sdk loaded');
                resolve();
            });
            scriptsToLoad.push({
                url: goopter_ppcp_manager.google_sdk_url,
                callback: googleResolveOnLoad
            });
        }

        if (scriptsToLoad.length === 0) {
            initSmartButtons();
        } else {
            let allPromises = [];
            for (let i = 0; i < scriptsToLoad.length; i++) {
                allPromises.push(scriptsToLoad[i].callback);
            }
            Promise.all(allPromises).then((success) => {
                console.log('all libs loaded');
                initSmartButtons();
            }, (error) => {
                console.log('An error occurred in loading the SDKs.');
            });
            for (let i = 0; i < scriptsToLoad.length; i++) {
                goopterLoadPayPalScript(scriptsToLoad[i], scriptsToLoad[i].callback);
            }
        }
    };

    window.goopterLoadAsyncLibs = (callback, errorCallback) => {
        goopterLoadPayPalScript({
            url: goopter_ppcp_manager.paypal_sdk_url,
            script_attributes: goopter_ppcp_manager.paypal_sdk_attributes
        }, callback, errorCallback);
    };

    window.goopterLoadAsyncLibs(paypalSdkLoadCallback);



    
})(jQuery);

window.onerror = function (msg, source, lineNo) {
	goopterJsErrorLogger.logJsError({
		'msg': msg,
		'source': source,
		'line': lineNo,
	});
}
