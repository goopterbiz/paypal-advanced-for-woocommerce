(function () {
    'use strict';
    function initializePayPal() {
        console.log('PayPal lib loaded, initialize add payment method.');
        goopterOrder.addPaymentMethodAdvancedCreditCard();
    }
    goopterLoadPayPalScript({
        url: goopter_ppcp_manager.paypal_sdk_url,
        script_attributes: goopter_ppcp_manager.paypal_sdk_attributes
    }, initializePayPal);
})(jQuery);
