var {createElement} = wp.element;
var {registerPlugin} = wp.plugins;
var {ExperimentalOrderMeta} = wc.blocksCheckout;
var {registerExpressPaymentMethod, registerPaymentMethod} = wc.wcBlocksRegistry;
var {addAction, removeAction} = wp.hooks;

(function (e) {
    var t = {};

    function n(o) {
        if (t[o])
            return t[o].exports;
        var r = (t[o] = {
            i: o,
            l: !1,
            exports: {},
        });
        return (
                e[o].call(r.exports, r, r.exports, n),
                (r.l = !0),
                r.exports
                );
    }

    n.m = e;
    n.c = t;
    n.d = function (e, t, o) {
        n.o(e, t) ||
                Object.defineProperty(e, t, {
                    enumerable: !0,
                    get: o,
                });
    };
    n.r = function (e) {
        "undefined" != typeof Symbol &&
                Symbol.toStringTag &&
                Object.defineProperty(e, Symbol.toStringTag, {
                    value: "Module",
                });
        Object.defineProperty(e, "__esModule", {
            value: !0,
        });
    };
    n.t = function (e, t) {
        if (1 & t && (e = n(e)), 8 & t)
            return e;
        if (
                4 & t &&
                "object" == typeof e &&
                e &&
                e.__esModule
                )
            return e;
        var o = Object.create(null);
        if (
                (n.r(o),
                        Object.defineProperty(o, "default", {
                            enumerable: !0,
                            value: e,
                        }),
                        2 & t && "string" != typeof e)
                )
            for (var r in e)
                n.d(
                        o,
                        r,
                        ((t) => {
                            return e[t];
                        }).bind(null, r)
                        );
        return o;
    };
    n.n = function (e) {
        var t = e && e.__esModule ? () => e.default : () => e;
        return n.d(t, "a", t), t;
    };
    n.o = function (e, t) {
        return Object.prototype.hasOwnProperty.call(e, t);
    };
    n.p = "";
    n(n.s = 6);
})([
    function (e, t) {
        e.exports = window.wp.element;
    },
    function (e, t) {
        e.exports = window.wp.htmlEntities;
    },
    function (e, t) {
        e.exports = window.wp.i18n;
    },
    function (e, t) {
        e.exports = window.wc.wcSettings;
    },
    function (e, t) {
        e.exports = window.wc.wcBlocksRegistry;
    },
    ,
            function (e, t, n) {
                "use strict";
                n.r(t);
                var o,
                        r = n(0),
                        c = n(4),
                        i = n(2),
                        u = n(3),
                        a = n(1);

                const l = Object(u.getSetting)("goopter_ppcp_apple_pay_data", {});
                const iconsElements = createElement("img", {key: l.icon, src: l.icon, style: {float: "right", marginRight: "10px"}});
                const p = () => Object(a.decodeEntities)(l.description || "");
                const {useEffect} = window.wp.element;
                const isApplePaySupported = typeof ApplePaySession !== 'undefined' && ApplePaySession?.supportsVersion(4) && ApplePaySession?.canMakePayments()
                const Content_PPCP_CC = (props) => {
                    const {billing, shippingData} = props;
                    goopterOrder.addPPCPCheckoutUpdatedEventListener(billing, shippingData);
                    useEffect(() => {
                        goopterOrder.renderPaymentButtons();
                    }, []);
                    
                    let renderComponents = [createElement("div", {key: "default", id: "goopter_ppcp_checkout_top"})];
                    jQuery.each(goopter_ppcp_manager.apple_pay_btn_selector, function (key) {
                        renderComponents.push(createElement("div", {key, id: key}));
                    });
                    return renderComponents;
                };
                const s = {
                    name: "goopter_ppcp_apple_pay",
                    label: createElement(
                            "span",
                            {style: {width: "100%"}},
                            l.title,
                            iconsElements
                            ),
                    placeOrderButtonLabel: "Object(i.__)(goopter_ppcp_apple_pay_manager_block.placeOrderButtonLabel)",
                    content: createElement(Content_PPCP_CC, null),
                    edit: Object(r.createElement)(p, null),
                    canMakePayment: () => isApplePaySupported,
                    ariaLabel: Object(a.decodeEntities)(l.cc_title || Object(i.__)("Payment via PayPal", "woo-gutenberg-products-block")),
                    supports: {
                        features: null !== (o = l.supports) && void 0 !== o ? o : [],
                        showSavedCards: false,
                        showSaveOption: false
                    }
                };
                Object(c.registerPaymentMethod)(s);
            }
]);

document.addEventListener('DOMContentLoaded', function () {
    setTimeout(function () {
        jQuery(document.body).trigger('ppcp_block_ready');
    }, 2000);
});

const ppcp_apple_uniqueEvents = new Set([
    'experimental__woocommerce_blocks-checkout-set-shipping-address',
    'experimental__woocommerce_blocks-checkout-set-billing-address',
    'experimental__woocommerce_blocks-checkout-set-email-address',
    'experimental__woocommerce_blocks-checkout-render-checkout-form',
    'experimental__woocommerce_blocks-checkout-set-active-payment-method'
]);

ppcp_uniqueEvents.forEach(function (action) {
    removeAction(action, 'c');
    addAction(action, 'c', function () {
        setTimeout(function () {
            jQuery(document.body).trigger("ppcp_checkout_updated");
        }, 500);
    });
});