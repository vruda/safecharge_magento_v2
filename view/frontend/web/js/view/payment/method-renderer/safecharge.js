/**
 * Safecharge Safecharge js component.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Customer/js/customer-data',
        'jquery.redirect',
        'ko',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/create-billing-address'
    ],
    function(
        $,
        Component,
        additionalValidators,
        redirectOnSuccessAction,
        setPaymentMethodAction,
        customerData,
        jqueryRedirect,
        ko,
        quote,
        billingAddress
    ) {
        'use strict';

        var self = null;

        return Component.extend({

            defaults: {
                template: 'Safecharge_Safecharge/payment/safecharge',
                isCcFormShown: true,
                creditCardToken: '',
                creditCardSave: 0,
                creditCardOwner: '',
                apmMethods: [],
                chosenApmMethod: '',
                countryId: null
            },

            initObservable: function() {
                self = this;

                self._super()
                    .observe([
                        'creditCardToken',
                        'creditCardSave',
                        'isCcFormShown',
                        'creditCardOwner',
                        'apmMethods',
                        'chosenApmMethod',
                        'countryId'
                    ]);

                var savedCards = self.getCardTokens();
                if (savedCards.length > 0) {
                    self.creditCardToken(savedCards[0]['value']);
                }

                var apmMethods = self.getApmMethods();
                if (apmMethods.length > 0) {
                    self.apmMethods(apmMethods);
                    self.chosenApmMethod(apmMethods[0].paymentMethod);
                }

                self.reloadApmMethods();
                quote.billingAddress.subscribe(self.reloadApmMethods, this, 'change');

                return self;
            },

            initCcNumberFormatting: function() {
                $('#' + self.getCode() + '_form_cc input[name="payment[cc_number]"]')
                    .bind('input', function(e) {
                        e.target.value = e.target.value.replace(/[^\dA-Z]/g, '').replace(/(.{4})/g, '$1 ').trim();
                    });
            },

            initCcCvvFormatting: function() {
                $('#' + self.getCode() + '_form_cc input[name="payment[cc_cid]"]')
                    .bind('input', function(e) {
                        e.target.value = e.target.value.replace(/[^\dA-Z]/g, '').replace(/(.{4})/g, '$1 ').trim();
                    });
            },

            context: function() {
                return self;
            },

            isShowLegend: function() {
                return true;
            },

            getCode: function() {
                return 'safecharge';
            },

            isActive: function() {
                return true;
            },

            useVault: function() {
                var useVault = window.checkoutConfig.payment[self.getCode()].useVault;
                self.creditCardSave(useVault ? 1 : 0);

                return useVault;
            },

            isCcDetectionEnabled: function() {
                return window.checkoutConfig.payment[self.getCode()].isCcDetectionEnabled;
            },

            getCssClass: function() {
                return self.isCcDetectionEnabled() ? 'field type detection' : 'field type required';
            },

            canSaveCard: function() {
                return window.checkoutConfig.payment[self.getCode()].canSaveCard;
            },

            getCardTokens: function() {
                var savedCards = window.checkoutConfig
                    .payment[self.getCode()]
                    .savedCards;

                return _.map(savedCards, function(value, key) {
                    return {
                        'value': key,
                        'label': value
                    };
                });
            },

            getData: function() {
                return {
                    'method': self.item.method,
                    'additional_data': {
                        'cc_token': self.creditCardToken(),
                        'cc_save': self.creditCardSave(),
                        'cc_cid': self.creditCardVerificationNumber(),
                        'cc_type': self.creditCardType(),
                        'cc_exp_year': self.creditCardExpYear(),
                        'cc_exp_month': self.creditCardExpMonth(),
                        'cc_number': self.creditCardNumber(),
                        'cc_owner': self.creditCardOwner(),
                        'chosen_apm_method': self.chosenApmMethod()
                    }
                };
            },

            savedCardSelected: function(token) {
                if (token === undefined) {
                    self.isCcFormShown(true);
                } else {
                    self.isCcFormShown(false);
                }
            },

            is3dSecureEnabled: function() {
                return window.checkoutConfig.payment[self.getCode()].is3dSecureEnabled;
            },

            getAuthenticateUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].authenticateUrl;
            },

            useExternalSolution: function() {
                return window.checkoutConfig.payment[self.getCode()].externalSolution;
            },

            getRedirectUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].redirectUrl;
            },

            getPaymentApmUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].paymentApmUrl;
            },

            getApmMethods: function() {
                return window.checkoutConfig.payment[self.getCode()].apmMethods;
            },

            getMerchantPaymentMethodsUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].getMerchantPaymentMethodsUrl;
            },

            reloadApmMethods: function() {
                if (quote.billingAddress() && self.countryId() === quote.billingAddress().countryId) {
                    return;
                } else if (quote.billingAddress()) {
                    self.countryId(quote.billingAddress().countryId);
                } else if ($('input[name="billing-address-same-as-shipping"]:checked').length && quote.shippingAddress()) {
                    if (self.countryId() === quote.shippingAddress().countryId) {
                        return;
                    } else {
                        self.countryId(quote.shippingAddress().countryId);
                    }
                } else {
                    //self.countryId(null)
                    //self.apmMethods([]);
                    return;
                }
                if (self.useExternalSolution()) {
                    return;
                }
                $.ajax({
                    dataType: "json",
                    url: self.getMerchantPaymentMethodsUrl(),
                    data: {
                        countryCode: self.countryId()
                    },
                    cache: false,
                    showLoader: true
                }).done(function(res) {
                    if (res && res.error == 0) {
                        self.apmMethods(res.apmMethods);
                        if (res.apmMethods.length > 0) {
                            self.chosenApmMethod(res.apmMethods[0].paymentMethod);
                        }
                    } else {
                        console.error(res);
                    }
                }).fail(function(e) {
                    console.error(e);
                });
            },

            placeOrder: function(data, event) {
                if (event) {
                    event.preventDefault();
                }

                if (self.validate() && additionalValidators.validate()) {
                    self.isPlaceOrderActionAllowed(false);

                    if (!self.useExternalSolution() && self.chosenApmMethod() !== 'cc_card') {
                        self.selectPaymentMethod();
                        setPaymentMethodAction(self.messageContainer).done(
                            function() {
                                $('body').trigger('processStart');
                                $.ajax({
                                    dataType: "json",
                                    data: {
                                        chosen_apm_method: self.chosenApmMethod()
                                    },
                                    url: self.getPaymentApmUrl(),
                                    cache: false
                                }).done(function(res) {
                                    if (res && res.error == 0 && res.redirectUrl) {
                                        window.location.href = res.redirectUrl;
                                    } else {
                                        console.error(res);
                                        window.location.reload();
                                    }
                                }).fail(function(e) {
                                    console.error(e);
                                    window.location.reload();
                                });
                            }.bind(self)
                        );

                        return;
                    }

                    if (self.useExternalSolution()) {
                        self.selectPaymentMethod();
                        setPaymentMethodAction(self.messageContainer).done(
                            function() {
                                $('body').trigger('processStart');
                                $.ajax({
                                    dataType: "json",
                                    url: self.getRedirectUrl(),
                                    cache: false
                                }).done(function(postData) {
                                    if (postData) {
                                        $.redirect(postData.url, postData.params, "POST");
                                    } else {
                                        window.location.reload();
                                    }
                                }).fail(function(e) {
                                    window.location.reload();
                                });

                                //$('body').trigger('processStop');
                                //customerData.invalidate(['cart']);
                            }.bind(self)
                        );

                        return true;
                    }

                    self.getPlaceOrderDeferredObject()
                        .fail(
                            function() {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                            function() {
                                self.afterPlaceOrder();

                                if (self.is3dSecureEnabled()) {
                                    $.ajax({
                                        url: self.getAuthenticateUrl(),
                                        cache: false
                                    }).done(function(html) {
                                        if (html !== '') {
                                            $('body').append(html);
                                            $('#safecharge_authenticate').submit();
                                        } else if (self.redirectAfterPlaceOrder) {
                                            redirectOnSuccessAction.execute();
                                        }
                                    });
                                } else if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }
                            }
                        );

                    return true;
                }

                return false;
            }
        });
    }
);