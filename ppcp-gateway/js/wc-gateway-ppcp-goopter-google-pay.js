
class GooglePayCheckoutButton {
    static googlePayConfig;
    static isConfigLoading;
    static configPromise;
    static googlePayObject;
    static baseRequest = {
        apiVersion: 2,
        apiVersionMinor: 0,
    }
    constructor() {

    }

    /**
     * This function makes sure google pay config is loaded once for all the render calls.
     * @returns {Promise<*>}
     */
    async initGooglePayConfig() {
        if (GooglePayCheckoutButton.isConfigLoading) {
            if (!GooglePayCheckoutButton.configPromise) {
                GooglePayCheckoutButton.configPromise = new Promise((resolve, reject) => {
                    let configLoop = setInterval(() => {
                        if (GooglePayCheckoutButton.googlePayConfig) {
                            resolve(GooglePayCheckoutButton.googlePayConfig);
                            clearInterval(configLoop);
                        }
                    }, 100);
                });
            }
            return GooglePayCheckoutButton.configPromise;
        }
        if (GooglePayCheckoutButton.googlePayConfig) {
            return GooglePayCheckoutButton.googlePayConfig;
        }
        GooglePayCheckoutButton.isConfigLoading = true;
        GooglePayCheckoutButton.googlePayConfig = await GooglePayCheckoutButton.googlePay().config();
        return GooglePayCheckoutButton.googlePayConfig;
    }

    static googlePay() {
        if (!GooglePayCheckoutButton.googlePayObject) {
            GooglePayCheckoutButton.googlePayObject = goopter_paypal_sdk.Googlepay();
        }
        return GooglePayCheckoutButton.googlePayObject;
    }

    render(containerSelector) {
        if (jQuery(containerSelector).length === 0) {
            return;
        }
        if (typeof goopter_paypal_sdk === 'undefined') {
            return;
        }
        this.initGooglePayConfig().then(() => {
            let thisObj = this;
            let paymentsClient = this.getGooglePaymentsClient();
            let allowedPaymentMethods = GooglePayCheckoutButton.googlePayConfig.allowedPaymentMethods;
            paymentsClient.isReadyToPay(this.getGoogleIsReadyToPayRequest(allowedPaymentMethods))
                .then((response) => {
                    if (response.result) {
                        this.showGooglePayPaymentMethod();
                        this.renderButton(containerSelector);
                    }
                })
                .catch((err) => {
                    console.error('errorcatch',err);
                    this.removeGooglePayPaymentMethod(containerSelector);
                });
        });
    }

    getGoogleIsReadyToPayRequest(allowedPaymentMethods) {
        return Object.assign({}, GooglePayCheckoutButton.baseRequest, {
            allowedPaymentMethods: allowedPaymentMethods,
        });
    }

    isShippingRequired() {
        const cartDetails = goopterOrder.getCartDetails();
        return !goopterOrder.isCheckoutPage() && cartDetails && typeof cartDetails.shippingRequired !== 'undefined' && cartDetails.shippingRequired;
    }

    getGooglePaymentsClient(data) {
        return new google.payments.api.PaymentsClient({
            environment: goopter_ppcp_manager.sandbox_mode === '1' ? "TEST" : "PRODUCTION",
            paymentDataCallbacks: {
                onPaymentDataChanged: (this.isShippingRequired() ? this.onPaymentDataChanged.bind(null, {
                    thisObject: this
                }) : null),
                onPaymentAuthorized: this.onPaymentAuthorized.bind(null, {
                    thisObject: this
                }),
            },
        });
    }

    showGooglePayPaymentMethod() {
        if (goopterOrder.isCheckoutPage()) {
            jQuery('.wc_payment_method.payment_method_goopter_ppcp_google_pay').show();
        }
    }

    removeGooglePayPaymentMethod(containerSelector) {
        if (goopterOrder.isCheckoutPage()) {
            jQuery('.wc_payment_method.payment_method_goopter_ppcp_google_pay').hide();
        }
        if (jQuery(containerSelector).length) {
            jQuery(containerSelector).remove();
        }
    }

    parseErrorMessage(errorObject) {
        if (errorObject.name === 'PayPalGooglePayError') {
            let debugID = errorObject.paypalDebugId;
            switch (errorObject.errorName) {
                case 'ERROR_VALIDATING_MERCHANT':
                    return localizedMessages.error_validating_merchant + ' [GooglePay DebugId:' + debugID + ']';
                //case 'UNPROCESSABLE_ENTITY':
                //    return JSON.stringify(errorObject);
                default:
                    return localizedMessages.general_error_message + ' [GooglePay DebugId:' + debugID + ']';
            }
        }
        return errorObject;
    }

    onPaymentAuthorized(additionalData, paymentData) {
        // let thisObj = this;

        return new Promise( (resolve, reject) => {
            goopterOrder.showProcessingSpinner();
            additionalData.thisObject.processPayment(additionalData, paymentData)
                .then(function (data) {
                    resolve({ transactionState: "SUCCESS" });
                })
                .catch(function (error) {
                    let errorMessage = additionalData.thisObject.parseErrorMessage(error);
                    goopterOrder.hideProcessingSpinner();
                    goopterOrder.showError(errorMessage);
                    resolve({ transactionState: "ERROR" });
                });
        });
    }

    onPaymentDataChanged(additionalData, intermediatePaymentData) {
        return new Promise(async function(resolve, reject) {
            let shippingAddress = intermediatePaymentData.shippingAddress;
            let paymentDataRequestUpdate = {};

            if (intermediatePaymentData.callbackTrigger == "INITIALIZE" || intermediatePaymentData.callbackTrigger == "SHIPPING_ADDRESS") {

                try {
                    let shippingDetails = {
                        administrativeArea: shippingAddress.administrativeArea,
                        countryCode: shippingAddress.countryCode,
                        locality: shippingAddress.locality,
                        postalCode: shippingAddress.postalCode
                    };
                    let response = await goopterOrder.shippingAddressUpdate({shippingDetails: shippingDetails});
                    if (typeof response.totalAmount !== 'undefined') {
                        goopterOrder.updateCartTotalsInEnvironment(response);
                        paymentDataRequestUpdate.newTransactionInfo = additionalData.thisObject.getGoogleTransactionInfo();
                    } else {
                        throw new Error(localizedMessages.shipping_amount_update_error);
                    }
                } catch (error) {
                    goopterOrder.handleCreateOrderError(additionalData.thisObject.parseErrorMessage(error), additionalData.thisObject.errorLogId);
                    paymentDataRequestUpdate.error = localizedMessages.shipping_amount_pull_error;
                    reject(localizedMessages.shipping_amount_pull_error);
                }
            }

            resolve(paymentDataRequestUpdate);
        });
    }

    async processPayment(additionalData, paymentData) {
        try {
            let thisObject = additionalData.thisObject;
            goopterOrder.showProcessingSpinner();
            /* Create Order */
            let orderID = await goopterOrder.createOrder({
                goopter_ppcp_button_selector: thisObject.containerSelector,
                errorLogId: additionalData.thisObject.errorLogId
            }).then((orderData) => {
                goopterOrder.updateCartTotalsInEnvironment(orderData);
                return orderData.orderID;
            });

            const { status } = await GooglePayCheckoutButton.googlePay().confirmOrder({
                orderId: orderID,
                paymentMethodData: paymentData.paymentMethodData,
            });
            if (status === "APPROVED") {
                // check if the billing address details are available
                // Do not run this service on checkout page as user already provides all details there
                if (!goopterOrder.isCheckoutPage()) {
                    let billingDetails;
                    let shippingDetails = paymentData.shippingAddress;
                    if (paymentData.paymentMethodData && paymentData.paymentMethodData.info && paymentData.paymentMethodData.info.billingAddress) {
                        billingDetails = paymentData.paymentMethodData.info.billingAddress;
                    }
                    if (paymentData.email) {
                        if (!billingDetails) {
                            billingDetails = {};
                        }
                        billingDetails.emailAddress = paymentData.email;
                    }
                    await goopterOrder.shippingAddressUpdate({shippingDetails}, {billingDetails}, additionalData.thisObject.errorLogId);
                }
                /* Capture the Order */
                await goopterOrder.approveOrder({orderID: orderID, payerID: ''});
                return { transactionState: "SUCCESS" };
            } else {
                return { transactionState: "ERROR" };
            }
        } catch (error) {
            console.log('processPaymentError', error);
            goopterOrder.handleCreateOrderError(additionalData.thisObject.parseErrorMessage(error), additionalData.thisObject.errorLogId);

            return {
                transactionState: "ERROR",
                error: {
                    message: error.message,
                },
            };
        }
    }

    renderButton(containerSelector) {
        this.containerSelector = containerSelector;
        let container = jQuery(containerSelector);
        container.html('');

        let buttonColor = 'default';
        let buttonType = 'plain';
        let containerStyle = '';
        if (typeof goopter_ppcp_manager.google_pay_button_props !== 'undefined') {
            buttonColor = goopter_ppcp_manager.google_pay_button_props.buttonColor;
            buttonType = goopter_ppcp_manager.google_pay_button_props.buttonType;
            let height = goopter_ppcp_manager.google_pay_button_props.height;
            height = height !== '' ? 'height: ' + height + 'px;' : '';
            containerStyle = height;
        }
        let googlePayContainer = jQuery('<div class="google-pay-container" style="'+(containerStyle != '' ? containerStyle  : '')+'"></div>');
        let thisObject = this;
        const paymentsClient = this.getGooglePaymentsClient();
        const button = paymentsClient.createButton({
            buttonColor: buttonColor,
            buttonType: buttonType,
            buttonSizeMode: 'fill',
            onClick: async (event) => {
                await thisObject.handleClickEvent(event, thisObject);
            }
        });
        googlePayContainer.append(button);

        // Remove the separator
        // if (!goopterOrder.isCheckoutPage()) {
        //     let separator = jQuery('<div class="goopter_ppcp-proceed-to-checkout-button-separator">&mdash; OR &mdash;</div><br>');
        //     container.html(separator);
        // }
        container.append(googlePayContainer);
    }

    getGoogleTransactionInfo() {
        let displayItems = [];
        const cartDetails = goopterOrder.getCartDetails();
        for (let i = 0; i < (cartDetails.lineItems).length; i++) {
            let type = "LINE_ITEM";
            let prodLabel = cartDetails.lineItems[i].label;
            if (prodLabel.toLowerCase() === 'tax') {
                type = 'TAX';
            }
            displayItems.push({
                'label': cartDetails.lineItems[i].label,
                'price': cartDetails.lineItems[i].amount,
                'type': type,
            })
        }

        return {
            displayItems: displayItems,
            currencyCode: cartDetails.currencyCode,
            totalPriceStatus: "FINAL",
            totalPrice: cartDetails.totalAmount,
            totalPriceLabel: "Total",
        };
    }

    getGooglePaymentDataRequest() {
        const paymentDataRequest = Object.assign({}, GooglePayCheckoutButton.baseRequest);
        paymentDataRequest.allowedPaymentMethods = GooglePayCheckoutButton.googlePayConfig.allowedPaymentMethods;
        paymentDataRequest.transactionInfo = this.getGoogleTransactionInfo();
        paymentDataRequest.merchantInfo = GooglePayCheckoutButton.googlePayConfig.merchantInfo;
        paymentDataRequest.emailRequired = true;
        paymentDataRequest.callbackIntents = ["PAYMENT_AUTHORIZATION"];
        if (this.isShippingRequired()) {
            paymentDataRequest.callbackIntents.push("SHIPPING_ADDRESS");
            paymentDataRequest.shippingAddressRequired = true;
        }
        return paymentDataRequest;
    }

    async handleClickEvent(event, thisObject) {
        thisObject.errorLogId = goopterJsErrorLogger.generateErrorId();
        const cartDetails = goopterOrder.getCartDetails();
        if (cartDetails.totalAmount <= 0) {
            goopterOrder.showError(localizedMessages.empty_cart_message);
        }
        goopterJsErrorLogger.addToLog(thisObject.errorLogId, 'Google Pay Payment Started');
        goopterOrder.setPaymentMethodSelector('google_pay');
        const paymentDataRequest = thisObject.getGooglePaymentDataRequest();
        const paymentsClient = thisObject.getGooglePaymentsClient({});

        paymentsClient.loadPaymentData(paymentDataRequest).then((success) => {
        }, (e) => {
            goopterOrder.triggerPaymentCancelEvent();
            goopterOrder.hideProcessingSpinner(thisObject.containerSelector);
            goopterOrder.hideProcessingSpinner();
            console.log('error handler click', e);
        });

    }
}
