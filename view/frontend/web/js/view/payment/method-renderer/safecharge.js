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
        'Magento_Paypal/js/action/set-payment-method',
        'jquery.redirect',
        'ko',
        'Magento_Checkout/js/model/quote',
        'mage/translate',
		'mage/validation'
    ],
    function(
        $,
        Component,
        setPaymentMethodAction,
        jqueryRedirect,
        ko,
        quote,
        mage
    ) {
        'use strict';

        var self = null;
        
        // for the WebSDK
        var sfc					= null;
		var cardNumber			= null;
		var cardExpiry			= null;
		var cardCvc				= null;
		var sfcFirstField		= null;
		var scFields			= null;
		var scData				= {};
//		var isCardAttached		= false;
		
		var isCCNumEmpty		= true;
		var isCCNumComplete		= false;
		
		var isCVVEmpty			= true;
		var isCVVComplete		= false;
		
		var isCCDateEmpty		= true;
		var isCCDateComplete	= false;
		
//		var scGetAPMsAgain		= false;
		var scOOTotal			= 0;

		var fieldsStyle	= {
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
		};
		
		var elementClasses = {
			focus	: 'focus',
			empty	: 'empty',
			invalid	: 'invalid'
		};
		
//		var discountSent	= false;
//		var discountElemId	= 'discount-code';
		
		var checkoutConfig = window.checkoutConfig,
			agreementsConfig = checkoutConfig ? checkoutConfig.checkoutAgreements : {},
			agreementsInputPath = '.payment-method._active div.checkout-agreements input';
		
		$(function() {
			console.log('document ready')
			
			$('body').on('change', '#safecharge_cc_owner', function(){
				$('#safecharge_cc_owner').css('box-shadow', 'inherit');
				$('#cc_name_error_msg').hide();
			});
			
			$('body').on('change', 'input[name="safecharge_apm_payment_method"]', function() {
				self.scCleanCard();
				
				if($(this).val() == 'cc_card') {
					self.initFields();
				}
			});
		});
		
        return Component.extend({
            defaults: {
                template				: 'Safecharge_Safecharge/payment/safecharge',
                isCcFormShown			: true,
                creditCardToken			: '',
                ccNumber				: '',
                creditCardOwner			: '',
                apmMethods				: [],
                chosenApmMethod			: '',
                countryId				: ''
            },
			
			scOrderTotal: quote.totals().base_grand_total.toFixed(2),
			
			scBillingCountry: quote.billingAddress().countryId,
			
			scPaymentMethod: '',
			
            initObservable: function() {
				console.log('initObservable()')
				
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
                    
//                self.getApmMethods();
//                quote.billingAddress.subscribe(self.getApmMethods, this, 'change');

				if(quote.paymentMethod._latestValue != null) {
					self.scPaymentMethod = quote.paymentMethod._latestValue.method;
				}

                quote.billingAddress.subscribe(self.scBillingAddrChange, this, 'change');
                quote.totals.subscribe(self.scTotalsChange, this, 'change');
                quote.paymentMethod.subscribe(self.scPaymentMethodChange, this, 'change');
				
				self.getApmMethods();
				
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
				console.log('getApmMethods()');
				
				if('safecharge' != self.scPaymentMethod) {
					console.log('getApmMethods() - slected payment method is not Safecharge');
					return;
				}
				
				/*
				if(!scGetAPMsAgain) {
					if (quote.billingAddress() && self.countryId() === quote.billingAddress().countryId) {
						if(typeof scData.merchantSiteId == 'undefined') {
							self.initFields();
						}
						
						return;
					}
					else if (quote.billingAddress()) {
						self.countryId(quote.billingAddress().countryId);
					}
					else if ($('input[name="billing-address-same-as-shipping"]:checked').length && quote.shippingAddress()) {
						if (self.countryId() === quote.shippingAddress().countryId) {
							if(typeof scData.merchantSiteId == 'undefined') {
								self.initFields();
							}
							
							return;
						}
						else {
							self.countryId(quote.shippingAddress().countryId);
						}
					}
					else {
						if(typeof scData.merchantSiteId == 'undefined') {
							self.initFields();
						}
						
						return;
					}
				}
				else { // clean card and container
					self.scCleanCard();
				}
                */
                
				$.ajax({
                    dataType: "json",
                    url: self.getMerchantPaymentMethodsUrl(),
                    data: {
                        countryCode	: self.scBillingCountry,
						grandTotal	: self.scOrderTotal
                    },
                    cache: false,
                    showLoader: true
                })
                .done(function(res) {
					console.log(res);
					
                    if (res && res.error == 0) {
                        self.apmMethods(res.apmMethods);
                        
						if (res.apmMethods.length > 0) {
                            self.chosenApmMethod(res.apmMethods[0].paymentMethod);
							
							for(var i in res.apmMethods) {
								if('cc_card' == res.apmMethods[i].paymentMethod) {
									scData.sessionToken	= res.sessionToken;
									scOOTotal			= res.ooAmount;
									
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

//					if(typeof scData.merchantSiteId == 'undefined') {
//						self.initFields();
//					}

					$('.loading-mask').css('display', 'none');
                })
                .fail(function(e) {
                    console.error(e.responseText);
					self.isPlaceOrderActionAllowed(false);
                });
				
//				scGetAPMsAgain = false;
            },

            placeOrder: function(data, event) {
				console.log('placeOrder()');
				
                if (event) {
                    event.preventDefault();
                }
				
                if(self.chosenApmMethod() === 'cc_card') {
					if($('#safecharge_cc_owner').val() == '') {
						$('#safecharge_cc_owner').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_name_error_msg').show();
						
						document.getElementById("safecharge_cc_owner").scrollIntoView();
						return;
					}
					
					if( (!isCCNumEmpty && !isCCNumComplete) || isCCNumEmpty ) {
						$('#sc_card_number').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_num_error_msg').show();
						
						document.getElementById("safecharge_cc_owner").scrollIntoView();
						return;
					}
					
					if( (!isCVVEmpty && !isCVVComplete) || isCVVEmpty ) {
						$('#sc_card_cvc').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_error_msg').show();
						
						document.getElementById("safecharge_cc_owner").scrollIntoView();
						return;
					}
					
					if( (!isCCDateEmpty&& !isCCDateComplete) || isCCDateEmpty ) {
						$('#sc_card_expiry').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_error_msg').show();
						
						document.getElementById("safecharge_cc_owner").scrollIntoView();
						return;
					}
					
					if(! self.validate()) {
						return;
					}
					
					$('.loading-mask').css('display', 'block');
					
					// we use variable just for debug
					var payParams = {
                        sessionToken		: scData.sessionToken,
                        currency			: window.checkoutConfig.payment[self.getCode()].currency,
                        amount				: quote.totals().base_grand_total.toFixed(2),
                        cardHolderName		: document.getElementById('safecharge_cc_owner').value,
                        paymentOption		: sfcFirstField,
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
									self.scCleanCard();
									self.initFields();
									$('.loading-mask').css('display', 'none');
								}
                            }
                            else {
                                var respError = 'Error with your Payment. Please try again later!';
								
								if(resp.hasOwnProperty('errorDescription') && '' != resp.errorDescription) {
									respError = resp.errorDescription;
								}
								else if(resp.hasOwnProperty('reason') && '' != resp.reason) {
									respError = resp.reason;
								}
								
								console.error(resp);
								
								if(!alert($.mage.__(respError))) {
									self.scCleanCard();
//									scGetAPMsAgain = true;
									self.getApmMethods();
								}
                            }
                        }
                        else {
							if(!alert($.mage.__('Unexpected error, please try again later!'))) {
//								scGetAPMsAgain = true;
								self.scCleanCard();
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
				console.log('continueWithOrder()');
				
				debugger;
				
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
											&& typeof transactionId != 'undefined'
											&& !isNaN(transactionId)
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
				console.log('initFields()')
				
				if('safecharge' != self.scPaymentMethod) {
					console.log('initFields() - slected payment method is not Safecharge');
					return;
				}
				
                // for the Fields
				scData.merchantSiteId		= window.checkoutConfig.payment[self.getCode()].merchantSiteId;
				scData.merchantId			= window.checkoutConfig.payment[self.getCode()].merchantId;
				scData.sourceApplication	= window.checkoutConfig.payment[self.getCode()].sourceApplication;
				
                if(window.checkoutConfig.payment[self.getCode()].isTestMode == true) {
                    scData.env = 'int';
                }
				
                sfc = SafeCharge(scData);

                // prepare fields
                scFields = sfc.fields({
                    locale: checkoutConfig.payment[self.getCode()].locale
                });

				if(
					$('#sc_card_number').html() == ''
					&& $('#sc_card_expiry').html() == ''
					&& $('#sc_card_cvc').html() == ''
				) {
					self.attachFields();
				}
            },
			
			attachFields: function() {
				console.log('attachFields()')
				console.log('scFields', scFields)
				
				if(null === scFields) {
					console.log('scFields is null');
					return;
				}
				
				if(null === cardNumber) {
					cardNumber = sfcFirstField = scFields.create('ccNumber', {
						classes: elementClasses
						,style: fieldsStyle
					});
					cardNumber.attach('#sc_card_number');
					
					// attach events listeners
					cardNumber.on('focus', function (e) {
						$('#sc_card_number').css('box-shadow', '0px 0 3px 1px #00699d');
						$('#cc_num_error_msg').hide();
					});

					cardNumber.on('change', function (e) {
						console.log('on focus', e);

						$('#sc_card_number').css('box-shadow', '0px 0 3px 1px #00699d');
						$('#cc_num_error_msg').hide();
						
						if(e.hasOwnProperty('empty')) {
							isCCNumEmpty = e.empty;
						}
						
						if(e.hasOwnProperty('complete')) {
							isCCNumComplete = e.complete;
						}
					});
				}
				
				if(null === cardExpiry) {
					cardExpiry = scFields.create('ccExpiration', {
						classes: elementClasses
						,style: fieldsStyle
					});
					cardExpiry.attach('#sc_card_expiry');
					
					cardExpiry.on('focus', function (e) {
						$('#sc_card_expiry').css('box-shadow', '0px 0 3px 1px #00699d');
						$('#cc_error_msg').hide();
					});

					cardExpiry.on('change', function (e) {
						console.log('on focus', e);

						$('#sc_card_expiry').css('box-shadow', '0px 0 3px 1px #00699d');
						$('#cc_error_msg').hide();
						
						if(e.hasOwnProperty('empty')) {
							isCCDateEmpty = e.empty;
						}
						
						if(e.hasOwnProperty('complete')) {
							isCCDateComplete = e.complete;
						}
					});
				}
				
				if(null === cardCvc) {
					cardCvc = scFields.create('ccCvc', {
						classes: elementClasses
						,style: fieldsStyle
					});
					cardCvc.attach('#sc_card_cvc');
					
					cardCvc.on('focus', function (e) {
						$('#sc_card_cvc').css('box-shadow', '0px 0 3px 1px #00699d');
						$('#cc_error_msg').hide();
					});

					cardCvc.on('change', function (e) {
						console.log('on focus', e);

						$('#sc_card_cvc').css('box-shadow', '0px 0 3px 1px #00699d');
						$('#cc_error_msg').hide();
						
						if(e.hasOwnProperty('empty')) {
							isCVVEmpty = e.empty;
						}
						
						if(e.hasOwnProperty('complete')) {
							isCVVComplete = e.complete;
						}
					});
				}
			},
			
			/**
			  * Validate checkout agreements
			 *
			 * @returns {Boolean}
			*/
			validate: function (hideError) {
				console.log('validate()');
				
				var isValid = true;

				if (!agreementsConfig.isEnabled || $(agreementsInputPath).length === 0) {
				   return true;
				}

				$(agreementsInputPath).each(function (index, element) {
				   if (!$.validator.validateSingleElement(element, {
					   errorElement: 'div',
					   hideError: hideError || false
				   })) {
					   isValid = false;
				   }
				});

				return isValid;
		   },
		   
			scCleanCard: function () {
				console.log('scCleanCard()');
				
				cardNumber = cardExpiry = cardCvc = sfcFirstField = null;
				$('#sc_card_number, #sc_card_expiry, #sc_card_cvc').html('');
			},
			
			scBillingAddrChange: function() {
				console.log('scBillingAddrChange()', quote.billingAddress());
				
				if(quote.billingAddress() == null) {
					console.log('scBillingAddrChange() - the BillingAddr is null. Stop here.');
					return;
				}
				
				if(quote.billingAddress().countryId == self.scBillingCountry) {
					console.log('scBillingAddrChange() - the country is same. Stop here.');
					return;
				}
				
				console.log('scBillingAddrChange() - the country was changed to', quote.billingAddress().countryId);
				self.scBillingCountry = quote.billingAddress().countryId;
				
				self.scCleanCard();
				self.getApmMethods();
			},
			
			scTotalsChange: function() {
				console.log('scTotalsChange()');
				
				var currentTotal = quote.totals().base_grand_total.toFixed(2);
				
				if(currentTotal == self.scOrderTotal) {
					console.log('scTotalsChange() - the total is same. Stop here.');
					return;
				}
				
				console.log('scTotalsChange() - the total was changed to', currentTotal);
				self.scOrderTotal = currentTotal;
				
				self.scCleanCard();
				self.getApmMethods();
			},
			
			scPaymentMethodChange: function() {
				console.log('scPaymentMethodChange()', quote.paymentMethod);
				
				if(
					quote.paymentMethod._latestValue != null
					&& self.scPaymentMethod != quote.paymentMethod._latestValue.method
				) {
					console.log('new paymentMethod is', quote.paymentMethod._latestValue.method)
					
					self.scPaymentMethod = quote.paymentMethod._latestValue.method;
					
					if('safecharge' == self.scPaymentMethod) {
						console.log('sfc', sfc);
						
						if(null == sfc) {
							self.getApmMethods();
						}
						
						if(jQuery('input[name="safecharge_apm_payment_method"]:checked').val() == 'cc_card') {
							self.initFields();
						}
					}
					else {
						self.scCleanCard();
					}
				}
			}
        });
    }
);