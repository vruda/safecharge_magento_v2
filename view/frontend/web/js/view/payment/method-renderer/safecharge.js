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
        'mage/translate'
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
        mage
    ) {
        'use strict';

        var self = null;
        
        // for the WebSDK
        var sfc				= null;
        var card			= null;
		var scData			= {};
		var isCardAttached	= false;
		var scGetAPMsAgain	= false;
		var scCCEmpty		= true;
		var scCCCompleted	= false;
		
		$('body').on('change', '#safecharge_cc_owner', function(){
			$('#safecharge_cc_owner').css('box-shadow', 'inherit');
			$('#cc_name_error_msg').hide();
		});
		
        return Component.extend({
            defaults: {
                template: 'Safecharge_Safecharge/payment/safecharge',
                isCcFormShown			: true,
                creditCardToken			: '',
                ccNumber				: '',
                creditCardOwner			: '',
                apmMethods				: [],
                chosenApmMethod			: '',
                countryId				: null
            },
			
			totals: quote.getTotals(),
			
            initObservable: function() {
                self = this;
				
                self._super()
                    .observe([
                        'creditCardToken',
                        'ccNumber',
                        'isCcFormShown',
                        'creditCardOwner',
                        'apmMethods',
                        'chosenApmMethod',
                        'countryId'
                    ]);
                    
                self.getApmMethods();
                quote.billingAddress.subscribe(self.getApmMethods, this, 'change');
				
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
                        cc_token			: self.creditCardToken(),
                        cc_number			: self.ccNumber(),
                        cc_owner			: self.creditCardOwner(),
                        chosen_apm_method	: self.chosenApmMethod(),
                    },
				};
				
                return pmData;
            },

            getRedirectUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].redirectUrl;
            },

            getPaymentApmUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].paymentApmUrl;
            },

            getMerchantPaymentMethodsUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].getMerchantPaymentMethodsUrl;
            },

            getApmMethods: function() {
				if(!scGetAPMsAgain) {
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
				}
				else { // clean card and container
					card = null;
					$('#card-field-placeholder').html('');
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
							
							for(var i in res.apmMethods) {
								if('cc_card' == res.apmMethods[i].paymentMethod) {
									scData.sessionToken = res.sessionToken;
									self.initFields();
									
									break;
								}
							}
                        }
						else {
							self.isPlaceOrderActionAllowed(false);
						}
                    }
                    else {
                        console.error(res);
						self.isPlaceOrderActionAllowed(false);
                    }
					
					$('.loading-mask').css('display', 'none');
                })
                .fail(function(e) {
                    console.error(e.responseText);
					self.isPlaceOrderActionAllowed(false);
                });
				
				scGetAPMsAgain = false;
            },

            placeOrder: function(data, event) {
				console.log('placeOrder');
				
                if (event) {
                    event.preventDefault();
                }
				
                if(self.chosenApmMethod() === 'cc_card') {
					if($('#safecharge_cc_owner').val() == '') {
						$('#safecharge_cc_owner').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_name_error_msg').show();
						return;
					}
					
					if( (!scCCEmpty && !scCCCompleted) || scCCEmpty ) {
						$('#card-field-placeholder').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_error_msg').show();
						return;
					}
					
					$('.loading-mask').css('display', 'block');
					
					// we use variable just for debug
					var payParams = {
                        sessionToken		: scData.sessionToken,
                        currency			: window.checkoutConfig.payment[self.getCode()].currency,
                        amount				: quote.totals().grand_total.toFixed(2),
                        cardHolderName		: document.getElementById('safecharge_cc_owner').value,
                        paymentOption		: card,
						webMasterId			: window.checkoutConfig.payment[self.getCode()].webMasterId,
                    };
					
                    // create payment with WebSDK
                    sfc.createPayment(payParams, function(resp){
						console.log('create payment');
						
                        if(typeof resp.result != 'undefined') {
                            if(resp.result == 'APPROVED' && resp.transactionId != 'undefined') {
                                self.ccNumber(resp.ccCardNumber);
                                self.creditCardToken(resp.dsTransID);
                                self.continueWithOrder(resp.transactionId);
                            }
                            else if(resp.result == 'DECLINED') {
								if(!alert($.mage.__('Your Payment was DECLINED. Please try another payment method!'))) {
									scGetAPMsAgain = true;
									self.getApmMethods();
								}
                            }
                            else {
                                if('undefined' != resp.errorDescription && '' != resp.errorDescription) {
                                    if(!alert($.mage.__(resp.errorDescription))) {
										scGetAPMsAgain = true;
										self.getApmMethods();
									}
                                }
                                else {
									console.log(resp);
									
                                    if(!alert($.mage.__('Error with your Payment. Please try again later!'))) {
										scGetAPMsAgain = true;
										self.getApmMethods();
									}
                                }
                            }
                        }
                        else {
							if(!alert($.mage.__('Unexpected error, please try again later!'))) {
								scGetAPMsAgain = true;
								self.getApmMethods();
							}
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

                    // in case we use WebSDK
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
                            .fail(function() {
                                window.location.reload();
                            });
                        }.bind(self));

                    return true;
                }

                return false;
            },
            
            initFields: function() {
                // for the Fields
				scData.merchantSiteId		= window.checkoutConfig.payment[self.getCode()].merchantSiteId;
				scData.merchantId			= window.checkoutConfig.payment[self.getCode()].merchantId;
				scData.sourceApplication	= window.checkoutConfig.payment[self.getCode()].sourceApplication;
				
                if(window.checkoutConfig.payment[self.getCode()].isTestMode == true) {
                    scData.env = 'test';
                }
				
                sfc = SafeCharge(scData);

                // prepare fields
                var fields = sfc.fields({
                    locale: checkoutConfig.payment[self.getCode()].locale,
					fonts : [{
						cssUrl: 'https://fonts.googleapis.com/css?family=Nunito+Sans:400&display=swap'
					}]
                });

                card = fields.create('card', {
                    iconStyle: 'solid',
                    style: {
                        base: {
                            iconColor			: "#c4f0ff",
                            color				: "#000",
                            fontWeight			: 400,
                            fontFamily			: "arial",
                            fontSize			: '15px',
                            fontSmoothing		: "antialiased",
                            ":-webkit-autofill"	: {
                                color: "#fce883"
                            },
                            "::placeholder"		: {
                                color		: "grey",
								fontFamily	: "arial"
                            }
                        },
                        invalid: {
                            iconColor	: "#ff0000",
                            color		: "#ff0000"
                        }
                    },
                    classes: {
						focus	: 'focus',
						empty	: 'empty',
						invalid	: 'invalid'
					}
                });
				
				card.on('focus', function (e) {
					console.log('on focus', e);
					
					$('#card-field-placeholder').css('box-shadow', '0px 0 3px 1px #00699d');
					$('#cc_error_msg').hide();
				});
				
				card.on('change', function (event) {
					$('#card-field-placeholder').css('box-shadow', '0px 0 3px 1px #00699d');
					$('#cc_error_msg').hide();
					
					if(event.hasOwnProperty('empty')) {
						scCCEmpty = event.empty;
					}
					
					if(event.hasOwnProperty('complete')) {
						scCCCompleted = event.complete;
					}
				});
                    
				if(!isCardAttached && $('#card-field-placeholder').length > 0) {
					card.attach('#card-field-placeholder');
				}
            },
			
			attachFields: function() {
				if(null !== card) {
					card.attach('#card-field-placeholder');
					isCardAttached = true;
				}
				else {
					console.log('card is null')
				}
			}
        });
    }
);
