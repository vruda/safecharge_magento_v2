/**
 * Nuvei Payments js component.
 *
 * @category Nuvei
 * @package  Nuvei_Payments
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
		var scFields			= null;
		var scData				= {};
		
		var isCCNumEmpty		= true;
		var isCCNumComplete		= false;
		
		var isCVVEmpty			= true;
		var isCVVComplete		= false;
		
		var isCCDateEmpty		= true;
		var isCCDateComplete	= false;
		
//		var scOOTotal			= 0;

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
		
		var checkoutConfig		= window.checkoutConfig,
			agreementsConfig	= checkoutConfig ? checkoutConfig.checkoutAgreements : {},
			agreementsInputPath	= '.payment-method._active div.checkout-agreements input';
		
		$(function() {
			console.log('document ready')
			
			$('body').on('change', '#nuvei_cc_owner', function(){
				$('#nuvei_cc_owner').css('box-shadow', 'inherit');
				$('#cc_name_error_msg').hide();
			});
			
			$('body').on('change', 'input[name="nuvei_apm_payment_method"]', function() {
				console.log('change nuvei_apm_payment_method');
				
				self.scCleanCard();
				
				if($(this).val() == 'cc_card') {
					self.initFields();
				}
			});
		});
		
        return Component.extend({
            defaults: {
                template				: 'Nuvei_Payments/payment/nuvei',
				apmMethods				: [],
				UPOs					: [],
                chosenApmMethod			: '',
                countryId				: ''
            },
			
			scOrderTotal: 0,
			
			scBillingCountry: '',
			
			scPaymentMethod: '',
			
            initObservable: function() {
				console.log('initObservable()')
				
                self = this;
				
                self._super()
                    .observe([
						'apmMethods',
						'UPOs',
                        'chosenApmMethod',
                        'countryId'
                    ]);
                    
				if(quote.paymentMethod._latestValue != null) {
					self.scPaymentMethod = quote.paymentMethod._latestValue.method;

					self.scUpdateQuotePM();
				}

				self.scOrderTotal = parseFloat(quote.totals().base_grand_total).toFixed(2);
				self.scBillingCountry = quote.billingAddress().countryId;

                quote.billingAddress.subscribe(self.scBillingAddrChange, this, 'change');
                quote.totals.subscribe(self.scTotalsChange, this, 'change');
                quote.paymentMethod.subscribe(self.scPaymentMethodChange, this, 'change');
				
				self.getApmMethods();
//				self.getUPOs();
				
                return self;
            },
            
            context: function() {
                return self;
            },

            isShowLegend: function() {
                return true;
            },

            getCode: function() {
                return 'nuvei';
            },

            isActive: function() {
                return true;
            },

            getData: function() {
				var pmData = {
					method : self.item.method,
					additional_data	: {
                        chosen_apm_method : self.chosenApmMethod(),
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
			
			getUPOsUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].getUPOsUrl;
            },
			
			getUpdateOrderUrl: function() {
				return window.checkoutConfig.payment[self.getCode()].getUpdateOrderUrl;
			},
			
			getUpdateQuotePM: function() {
                return window.checkoutConfig.payment[self.getCode()].updateQuotePM;
            },
			
            getMerchantPaymentMethodsUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].getMerchantPaymentMethodsUrl;
            },
			
			getUPOs: function() {
				console.log('getUPOs()');
				
				if('nuvei' != self.scPaymentMethod) {
					console.log('getUPOs() - slected payment method is not Nuvei');
					return;
				}
				
				if(
					self.apmMethods.length == 0
					|| window.checkoutConfig.payment[self.getCode()].useUPOs == 0
				) {
					return;
				}
				
				$.ajax({
                    dataType	: "json",
                    url			: self.getUPOsUrl(),
					cache		: false,
                    showLoader	: true,
                    data		: { apms: JSON.stringify(self.apmMethods) }
                })
					.done(function(resp) {
						console.log(resp);
					})
					.fail(function(e) {
						console.error(e.responseText);
					});
			},
			
            getApmMethods: function(billingAddress) {
				console.log('getApmMethods()');
				
				if('nuvei' != self.scPaymentMethod) {
					console.log('getApmMethods() - slected payment method is not Nuvei');
					return;
				}
				
				$.ajax({
                    dataType: "json",
					type: 'post',
                    url: self.getMerchantPaymentMethodsUrl(),
                    data: {
						billingAddress: billingAddress
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
//									scOOTotal			= res.ooAmount;
									
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
            },
			
            placeOrder: function(data, event) {
				console.log('placeOrder()');
				
				$('body').trigger('processStart'); // show loader
				
                if (event) {
                    event.preventDefault();
                }
				
				jQuery.ajax({
					dataType: 'json',
					url: self.getUpdateOrderUrl()
				})
					.fail(function(){
						self.validateOrderData();
					})
					.done(function(resp) {
						console.log(resp);

						if(
							resp.hasOwnProperty('sessionToken')
							&& '' != resp.sessionToken
							&& resp.sessionToken != scData.sessionToken
						) {
							scData.sessionToken = resp.sessionToken;

							sfc			= SafeCharge(scData);
							scFields	= sfc.fields({
								locale: checkoutConfig.payment[self.getCode()].locale
							});
						}

						self.validateOrderData();
					});
            },
            
			validateOrderData: function() {
				console.log('validateOrderData()');
				
				if(self.chosenApmMethod() === 'cc_card') {
					if($('#nuvei_cc_owner').val() == '') {
						$('#nuvei_cc_owner').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_name_error_msg').show();
						
						document.getElementById("nuvei_cc_owner").scrollIntoView();
						$('body').trigger('processStop');
						
						return;
					}
					
					if( (!isCCNumEmpty && !isCCNumComplete) || isCCNumEmpty ) {
						$('#sc_card_number').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_num_error_msg').show();
						
						document.getElementById("nuvei_cc_owner").scrollIntoView();
						$('body').trigger('processStop');
						
						return;
					}
					
					if( (!isCVVEmpty && !isCVVComplete) || isCVVEmpty ) {
						$('#sc_card_cvc').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_error_msg').show();
						
						document.getElementById("nuvei_cc_owner").scrollIntoView();
						$('body').trigger('processStop');
						
						return;
					}
					
					if( (!isCCDateEmpty&& !isCCDateComplete) || isCCDateEmpty ) {
						$('#sc_card_expiry').css('box-shadow', 'red 0px 0px 3px 1px');
						$('#cc_error_msg').show();
						
						document.getElementById("nuvei_cc_owner").scrollIntoView();
						$('body').trigger('processStop');
						
						return;
					}
					
					if(! self.validate()) {
						$('body').trigger('processStop');
						return;
					}
					
					if(null == cardNumber) {
						alert($.mage.__('Unexpected error! If the fields of the selected payment method do not reload in few seconds, please reload the page!'));
						$('body').trigger('processStop');
						
						return;
					}
					
					// we use variable just for debug
					var payParams = {
						sessionToken		: scData.sessionToken,
						cardHolderName		: document.getElementById('nuvei_cc_owner').value,
						paymentOption		: cardNumber,
						webMasterId			: window.checkoutConfig.payment[self.getCode()].webMasterId,
                    };
					
                    // create payment with WebSDK
                    sfc.createPayment(payParams, function(resp){
						console.log('create payment');
						
                        if(typeof resp != 'undefined' && resp.hasOwnProperty('result')) {
                            if(resp.result == 'APPROVED' && resp.transactionId != 'undefined') {
                                self.continueWithOrder(resp.transactionId);
                            }
                            else if(resp.result == 'DECLINED') {
								if(!alert($.mage.__('Your Payment was DECLINED. Please try another payment method!'))) {
									$('body').trigger('processStop');
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
									self.getApmMethods();
									self.getUPOs();
									$('body').trigger('processStop');
									
									return;
								}
                            }
                        }
                        else {
							if(!alert($.mage.__('Unexpected error, please try again later!'))) {
								window.location.reload();
								return;
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
											return;
										}
										else {
											console.error(res);
											window.location.reload();
											return;
										}
									})
									.fail(function(e) {
										console.error(e);
										window.location.reload();
										return;
									});
								}.bind(self)
							);

						$('body').trigger('processStop');
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
                            $.ajax(ajaxData)
								.done(function(postData) {
									if (postData) {
										if(
											self.chosenApmMethod() === 'cc_card'
											&& typeof transactionId != 'undefined'
											&& !isNaN(transactionId)
										) {
											window.location.href = postData.url;
											return;
										}

										$.redirect(postData.url, postData.params, "POST");
										return;
									}
									else {
										window.location.reload();
										return;
									}
								})
								.fail(function() {
									window.location.reload();
									return;
								});
                        }.bind(self));

                    return true;
                }

				$('body').trigger('processStop');
                return false;
            },
            
            initFields: function() {
				console.log('initFields()')
				
				if('nuvei' != self.scPaymentMethod) {
					console.log('initFields() - slected payment method is not Nuvei');
					$('body').trigger('processStop');
					
					return;
				}
				
                // for the Fields
				scData.merchantSiteId		= window.checkoutConfig.payment[self.getCode()].merchantSiteId;
				scData.merchantId			= window.checkoutConfig.payment[self.getCode()].merchantId;
				scData.sourceApplication	= window.checkoutConfig.payment[self.getCode()].sourceApplication;
				
                if(window.checkoutConfig.payment[self.getCode()].isTestMode == true) {
                    scData.env = 'int';
                }
				else {
					scData.env = 'prod';
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
					$('body').trigger('processStop');
					
					return;
				}
				
				if(null === cardNumber) {
					cardNumber = scFields.create('ccNumber', {
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
				
				$('body').trigger('processStop');
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
				
				cardNumber = cardExpiry = cardCvc = null;
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
				
				console.log('scBillingAddrChange()', JSON.stringify(quote.billingAddress()));
				
				console.log('scBillingAddrChange() - the country was changed to', quote.billingAddress().countryId);
				self.scBillingCountry = quote.billingAddress().countryId;
				
				self.scCleanCard();
				self.getApmMethods(JSON.stringify(quote.billingAddress()));
//				self.getUPOs();
			},
			
			scTotalsChange: function() {
				console.log('scTotalsChange()');
				
				var currentTotal = parseFloat(quote.totals().base_grand_total).toFixed(2);
				
				if(currentTotal == self.scOrderTotal) {
					console.log('scTotalsChange() - the total is same. Stop here.');
					return;
				}
				
				console.log('scTotalsChange() - the total was changed to', currentTotal);
				self.scOrderTotal = currentTotal;
				
				self.scCleanCard();
				self.getApmMethods();
//				self.getUPOs();
			},
			
			scPaymentMethodChange: function() {
				console.log('scPaymentMethodChange()', quote.paymentMethod);
				
				if(
					quote.paymentMethod._latestValue != null
					&& self.scPaymentMethod != quote.paymentMethod._latestValue.method
				) {
					console.log('new paymentMethod is', quote.paymentMethod._latestValue.method);
					
					self.scUpdateQuotePM();
					
					self.scPaymentMethod = quote.paymentMethod._latestValue.method;
					
					if('nuvei' == self.scPaymentMethod) {
						console.log('sfc', sfc);
						
						if(null == sfc) {
							self.getApmMethods();
//							self.getUPOs();
						}
						
						if(jQuery('input[name="nuvei_apm_payment_method"]:checked').val() == 'cc_card') {
							self.initFields();
						}
					}
					else {
						self.scCleanCard();
					}
				}
			},
			
			scUpdateQuotePM: function() {
				console.log('scUpdateQuotePM()');
				console.log('self.scPaymentMethod', self.scPaymentMethod);
				console.log('quote.paymentMethod._latestValue.method', quote.paymentMethod._latestValue.method);
				
				var scAjaxQuoteUpdateParams = {
					dataType	: "json",
					url			: self.getUpdateQuotePM(),
					cache		: false,
					showLoader	: true,
					data		: { paymentMethod: quote.paymentMethod._latestValue.method }
				};

				// update new payment method
				if('' != self.scPaymentMethod || quote.paymentMethod._latestValue.method != self.scPaymentMethod) {
					console.log('update quote payment method', quote.paymentMethod._latestValue.method);

					$.ajax(scAjaxQuoteUpdateParams)
						.done(function(resp) {})
						.fail(function(e) {
							console.error(e.responseText);
						});
				}
			}
        });
    }
);
