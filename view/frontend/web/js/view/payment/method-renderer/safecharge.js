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
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Customer/js/customer-data',
        'jquery.redirect',
        'ko',
        'Magento_Checkout/js/model/quote',
        'mage/translate',
    ],
    function(
        $,
        Component,
        redirectOnSuccessAction,
        setPaymentMethodAction,
        customerData,
        jqueryRedirect,
        ko,
        quote,
        mage,
    ) {
        'use strict';

        var self = null;
        
        // for the WebSDK
        var sfc             = null;
        var card            = null;
        
        return Component.extend({

            defaults: {
                template: 'Safecharge_Safecharge/payment/safecharge',
                isCcFormShown			: true,
                creditCardToken			: '',
                ccNumber				: '',
                lastSessionToken		: '',
                creditCardOwner			: '',
                apmMethods				: [],
                chosenApmMethod			: '',
                countryId				: null
            },

            initObservable: function() {
                self = this;

                self._super()
                    .observe([
                        'creditCardToken',
                        'ccNumber',
                        'lastSessionToken',
                        'isCcFormShown',
                        'creditCardOwner',
                        'apmMethods',
                        'chosenApmMethod',
                        'countryId'
                    ]);
                    
                var apmMethods = self.getApmMethods();
				
                if (apmMethods.length > 0) {
                    self.apmMethods(apmMethods);
                    self.chosenApmMethod(apmMethods[0].paymentMethod);
                }

                self.reloadApmMethods();
                quote.billingAddress.subscribe(self.reloadApmMethods, this, 'change');
                
                return self;
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

            getData: function() {
				var pmData = {
					method			: self.item.method,
                    additional_data	: {
                        cc_token					: self.creditCardToken(),
                        cc_number					: self.ccNumber(),
                        last_session_token			: self.lastSessionToken(),
                        cc_owner					: self.creditCardOwner(),
                        chosen_apm_method			: self.chosenApmMethod(),
                    },
				};
				
                return pmData;
            },

            getAuthenticateUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].authenticateUrl;
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
                }
                else if (quote.billingAddress()) {
                    self.countryId(quote.billingAddress().countryId);
                }
                else if ($('input[name="billing-address-same-as-shipping"]:checked').length && quote.shippingAddress()) {
                    if (self.countryId() === quote.shippingAddress().countryId) {
                        return;
                    }
                    else {
                        self.countryId(quote.shippingAddress().countryId);
                    }
                }
                else {
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
                })
                .done(function(res) {
                    if (res && res.error == 0) {
                        self.apmMethods(res.apmMethods);
                        if (res.apmMethods.length > 0) {
                            self.chosenApmMethod(res.apmMethods[0].paymentMethod);
                        }
                    }
                    else {
                        console.error(res);
                    }
                })
                .fail(function(e) {
                    console.error(e);
                });
            },

            placeOrder: function(data, event) {
                if (event) {
                    event.preventDefault();
                }
                
                if(self.chosenApmMethod() === 'cc_card') {
                    $('.loading-mask').css('display', 'block');
                    
                    // create payment with WebSDK
                    sfc.createPayment({
                        sessionToken    : window.checkoutConfig.payment[self.getCode()].sessionToken,
                        currency        : window.checkoutConfig.payment[self.getCode()].currency,
                        amount          : window.checkoutConfig.payment[self.getCode()].total,
                        cardHolderName  : document.getElementById('safecharge_cc_owner').value,
                        paymentOption   : card
                    }, function(resp){
                        console.log(resp);
                        
                        if(typeof resp.result != 'undefined') {
                            if(resp.result == 'APPROVED' && resp.transactionId != 'undefined') {
                                console.log(resp.dsTransID)
                            
                                self.ccNumber(resp.ccCardNumber);
                                self.creditCardToken(resp.dsTransID);
                                self.continueWithOrder(resp.transactionId);
                            }
                            else if(resp.result == 'DECLINED') {
                                $('.loading-mask').css('display', 'none');
                                alert($.mage.__('Your Payment was DECLINED. Please try another payment method!'));
                            }
                            else {
                                $('.loading-mask').css('display', 'none');
                                
                                if('undefined' != resp.errorDescription && '' != resp.errorDescription) {
                                    alert($.mage.__(resp.errorDescription));
                                }
                                else {
                                    alert($.mage.__('Error with your Payment. Please try again later!'));
                                }
                            }
                        }
                        else {
                            $('.loading-mask').css('display', 'none');
                            alert($.mage.__('Unexpected error, please try again later!'));
                        }
                    });
                }
                else {
                    self.continueWithOrder();
                }
            },
            
            continueWithOrder: function(transactionId) {
                if (self.validate()) {
                    self.isPlaceOrderActionAllowed(false);

                    if (self.chosenApmMethod() !== 'cc_card') {
						var apmFields = {};
						var choosenMethod = self.chosenApmMethod();

						$('.fields-' + choosenMethod + ' input').each(function(){
							var _slef = $(this);
							apmFields[_slef.attr('name')] = _slef.val();
						});
						
                        self.selectPaymentMethod();
                        setPaymentMethodAction(self.messageContainer)
							.done(function() {
									$('body').trigger('processStart');

									$.ajax({
										dataType: "json",
										data: {
											chosen_apm_method	: choosenMethod,
											apm_method_fields	: apmFields
										},
										url: self.getPaymentApmUrl(),
										cache: false
									})
									.done(function(res) {
										if (res && res.error == 0 && res.redirectUrl) {
											window.location.href = res.redirectUrl;
										}
										else {
											console.error(res);
											window.location.reload();
										}
									})
									.fail(function(e) {
										console.error(e);
										window.location.reload();
									});
								}.bind(self)
							);

                        return;
                    }

                    var ajaxData = {
                        dataType: "json",
                        url: self.getRedirectUrl(),
                        cache: false
                    };

                    // in case we use Fields
                    if(self.chosenApmMethod() === 'cc_card' && transactionId != 'undefined') {
                        ajaxData.url += '?method=cc_card&transactionId=' + transactionId;
                    }

                    self.selectPaymentMethod();
                    setPaymentMethodAction(self.messageContainer)
                        .done(function() {
                            $('body').trigger('processStart');

                            $.ajax(ajaxData)
                            .done(function(postData) {
                                if (postData) {
                                    if(
                                        self.chosenApmMethod() === 'cc_card'
                                        && transactionId != 'undefined'
                                    ) {
                                        window.location.href = postData.url;
                                    }

                                    $.redirect(postData.url, postData.params, "POST");
                                }
                                else {
                                    window.location.reload();
                                }
                            })
                            .fail(function(e) {
                                window.location.reload();
                            });
                        }.bind(self));

                    return true;
                }

                return false;
            },
            
            initFields: function() {
                // for the Fields
                var scData = {
                    merchantSiteId  : window.checkoutConfig.payment[self.getCode()].merchantSiteId,
                    merchantId      : window.checkoutConfig.payment[self.getCode()].merchantId,
                    sessionToken    : window.checkoutConfig.payment[self.getCode()].sessionToken
                }
                
                if(window.checkoutConfig.payment[self.getCode()].isTestMode == true) {
                    scData.env = 'test';
                }
                
                sfc = SafeCharge(scData);

                // prepare fields
                var fields = sfc.fields({
                    locale: checkoutConfig.payment[self.getCode()].locale
                });

                // set some classes
                var elementClasses = {
                    focus: 'focus',
                    empty: 'empty',
                    invalid: 'invalid',
                };

                card = fields.create('card', {
                    iconStyle: 'solid',
                    style: {
                        base: {
                            iconColor: "#c4f0ff",
                            color: "#000",
                            fontWeight: 500,
                            fontFamily: "Roboto, Open Sans, Segoe UI, sans-serif",
                            fontSize: '15px',
                            fontSmoothing: "antialiased",
                            ":-webkit-autofill": {
                                color: "#fce883"
                            },
                            "::placeholder": {
                                color: "grey" 
                            }
                        },
                        invalid: {
                            iconColor: "#FFC7EE",
                            color: "#FFC7EE"
                        }
                    },
                    classes: elementClasses
                });
                    
                card.attach('#card-field-placeholder');
            }
        });
    }
);