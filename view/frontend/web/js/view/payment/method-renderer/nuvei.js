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
		var lastCvcHolder		= ''; // the id of the last used CVC container
		var scFields			= null;
		var scData				= {};
		
		var isCCNumEmpty		= true;
		var isCCNumComplete		= false;
		
		var isCVVEmpty			= true;
		var isCVVComplete		= false;
		
		var isCCDateEmpty		= true;
		var isCCDateComplete	= false;
		
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
			
			$('body').on('change', 'input[name="nuvei_payment_method"]', function() {
				console.log('change nuvei_payment_method', $(this).val());
				
				var _self = $(this);
				self.scCleanCard();
				
				// CC
				if(_self.val() == 'cc_card') {
					lastCvcHolder = '#sc_card_cvc';
					self.initFields();
				}
				// UPO CC
				else if ('cc_card' == _self.attr('data-upo-name')) {
					lastCvcHolder = '#sc_upo_'+ _self.val() +'_cvc';
					self.initFields();
				}
			});
			
			$('body').on('change', '#nuvei_save_upo_cont input', function() {
				var _self = $(this);
				_self.val(_self.is(':checked') ? 1 : 0);
			});
		});
		
        return Component.extend({
            defaults: {
                template				: 'Nuvei_Payments/payment/nuvei',
				apmMethods				: [],
				upos					: [],
                chosenApmMethod			: '',
                typeOfChosenPayMethod	: '',
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
						'upos',
                        'chosenApmMethod',
                        'typeOfChosenPayMethod',
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
				// set observer when change the payment method
				self.chosenApmMethod.subscribe(self.setChosenApmMethod, this, 'change');
				
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
			
			setChosenApmMethod: function() {
				console.log('setChosenApmMethod()');
				
				// CC
				if(self.chosenApmMethod() == 'cc_card') {
					self.typeOfChosenPayMethod('cc_card');
					
					if(window.checkoutConfig.payment[self.getCode()].useUPOs == 1) {
						$('body').find('#nuvei_save_upo_cont').show();
					}
				}
				// APM
				else if(isNaN(self.chosenApmMethod())) {
					self.typeOfChosenPayMethod('apm');
					
					console.log('show checkbox');
					
					if(window.checkoutConfig.payment[self.getCode()].useUPOs == 1) {
						$('body').find('#nuvei_save_upo_cont').show();
					}
				}
				// UPOs
				else {
					console.log('hide checkbox');
					
					$('body').find('#nuvei_save_upo_cont').hide();
					
					var selectedOption = $('body').find('#nuvei_' + self.chosenApmMethod());
					
					if(selectedOption.attr('data-upo-name') == 'cc_card') {
						self.typeOfChosenPayMethod('upo_cc');
					}
					else {
						self.typeOfChosenPayMethod('upo_apm');
					}
				}
				
				console.log(self.typeOfChosenPayMethod());
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
			
			getRemoveUpoUrl: function() {
				return window.checkoutConfig.payment[self.getCode()].getRemoveUpoUrl;
			},
			
			getUpdateQuotePM: function() {
                return window.checkoutConfig.payment[self.getCode()].updateQuotePM;
            },
			
            getMerchantPaymentMethodsUrl: function() {
                return window.checkoutConfig.payment[self.getCode()].getMerchantPaymentMethodsUrl;
            },
			
			getNuveiIconUrl: function() {
				return window.checkoutConfig.payment[self.getCode()].checkoutLogoUrl
			},
			
			removeUpo: function(_upoId) {
				console.log('removeUpo', _upoId);
				
				if(confirm($.mage.__('Are you sure, you want to delete this Preferred payment method?'))) {
					$.ajax({
	                    dataType: "json",
						type: 'post',
	                    url: self.getRemoveUpoUrl(),
	                    data: { upoId: _upoId },
	                    cache: false,
	                    showLoader: true
	                })
	                .done(function(res) {
						console.log(res);
						
	                    if (res && res.hasOwnProperty('success') && res.success == 1) {
							console.log('success');
							
							$('body')
								.find('#nuvei_upos input#nuvei_' + _upoId)
								.closest('.nuvei-apm-method-container')
								.remove();
	                    }
	                    else {
	                        console.error(res);
							self.isPlaceOrderActionAllowed(false);
	                    }
	
						$('.loading-mask').css('display', 'none');
	                })
	                .fail(function(e) {
	                    console.error(e.responseText);
				
						alert($.mage.__('Unexpected error, please try again later!'));
				
						$('.loading-mask').css('display', 'none');
	                });
				} 
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
                        self.upos(res.upos);
                        
						if (res.upos.length > 0) {
							$('#nuvei_upos_title').show();
						}
						
						if (res.apmMethods.length > 0) {
							$('#nuvei_apms_title').show();
							
							for(var i in res.apmMethods) {
								if('cc_card' == res.apmMethods[i].paymentMethod) {
									scData.sessionToken	= res.sessionToken;
									
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
				
				var payParams = {
					sessionToken	: scData.sessionToken,
					webMasterId		: window.checkoutConfig.payment[self.getCode()].webMasterId,
				};
				
				// CC
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
					
					if(!self.validate()) {
						$('body').trigger('processStop');
						return;
					}
					
					if(null == cardNumber) {
						alert($.mage.__('Unexpected error! If the fields of the selected payment method do not reload in few seconds, please reload the page!'));
						$('body').trigger('processStop');
						
						return;
					}
					
					payParams.paymentOption		= cardNumber;
					payParams.cardHolderName	= document.getElementById('nuvei_cc_owner').value;
					
					if (
						window.checkoutConfig.payment[self.getCode()].useUPOs == 1
						&& ( self.typeOfChosenPayMethod() == 'cc_card' || self.typeOfChosenPayMethod() == 'apm' )
						&& $('body').find('#nuvei_save_upo_cont input').val() == 1
					) {
						payParams.userTokenId = window.checkoutConfig.payment[self.getCode()].userTokenId;
					}
					
                    // create payment with WebSDK
                    self.createPayment(payParams);
                }
				// in case of CC UPO
				else if(self.typeOfChosenPayMethod() === 'upo_cc') {
					// checks
					if( (!isCVVEmpty && !isCVVComplete) || isCVVEmpty ) {
						$('#sc_upo_'+ self.chosenApmMethod() +'_cvc').css('box-shadow', 'red 0px 0px 3px 1px');
						
						$('#sc_upo_'+ self.chosenApmMethod() +'_cvc')
							.closest('.nuvei-apm-method-container')
							.find('fieldset .sc_error')
							.show();
						
						document.getElementById('sc_upo_'+ self.chosenApmMethod() +'_cvc').scrollIntoView();
						$('body').trigger('processStop');
						
						return;
					}
					
					if(!self.validate()) {
						$('body').trigger('processStop');
						return;
					}
					// checks END
					
					payParams.userTokenId	= window.checkoutConfig.payment[self.getCode()].userTokenId;
					payParams.paymentOption	= {
						userPaymentOptionId: self.chosenApmMethod(),
						card: {
							CVV: cardCvc
						}
					};

					// create payment with WebSDK
                    self.createPayment(payParams);
				}
                else {
                    self.continueWithOrder();
                }
			},
			
			// a repeating part of the code
			createPayment: function(payParams) {
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
			},
			
            continueWithOrder: function(transactionId) {
				console.log('continueWithOrder()');
				//debugger;
				
                if (self.validate()) {
                    self.isPlaceOrderActionAllowed(false);

					// APMs and UPO APMs payments
                    if (
						self.typeOfChosenPayMethod() === 'apm'
						|| self.typeOfChosenPayMethod() === 'upo_apm'
					) {
						console.log('continueWithOrder() apm or upo_apm');
				
						var choosenMethod	= self.chosenApmMethod();
						var postData		= {
							chosen_apm_method	: choosenMethod,
							apm_method_fields	: {}
						};

						// for APMs only
						if(self.typeOfChosenPayMethod() === 'apm') {
							$('.fields-' + choosenMethod + ' input').each(function(){
								var _slef = $(this);
								postData.apm_method_fields[_slef.attr('name')] = _slef.val();
							});
							
							if(window.checkoutConfig.payment[self.getCode()].useUPOs == 1) {
								postData.save_payment_method = $('body').find('#nuvei_save_upo_cont input').val();
							}
						}
						
                        self.selectPaymentMethod();
						
                        setPaymentMethodAction(self.messageContainer)
							.done(function() {
									$('body').trigger('processStart');

									$.ajax({
										dataType: "json",
										type: 'post',
										data: postData,
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
                    if(self.typeOfChosenPayMethod() !== 'apm' && transactionId != 'undefined') {
                        ajaxData.url += '?method=web_sdk&transactionId=' + transactionId;
                    }

                    self.selectPaymentMethod();
					
					setPaymentMethodAction(self.messageContainer)
                        .done(function() {
                            $.ajax(ajaxData)
								.done(function(postData) {
									if (postData) {
										if(
											self.typeOfChosenPayMethod() !== 'apm'
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
					( $('#sc_card_number').html() == '' || typeof $('#sc_card_number').html() == 'undefined' )
					&& ( $('#sc_card_expiry').html() == '' || typeof $('#sc_card_expiry').html() == 'undefined' )
					&& ( $('#sc_card_cvc').html() == '' || typeof $('#sc_card_cvc').html() == 'undefined' )
					&& lastCvcHolder !== ''
				) {
					self.attachFields();
				}
            },
			
			attachFields: function() {
				console.log('attachFields()');
				console.log('scFields', scFields);
				console.log('lastCvcHolder', lastCvcHolder);
				
				if(null === scFields) {
					console.log('scFields is null');
					$('body').trigger('processStop');
					
					return;
				}
				
				// CC fields only
				if('#sc_card_cvc' === lastCvcHolder) {
					// CC number
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
					// CC number END
					
					// CC Expiry
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
					// // CC Expiry END

					// CC CVC
					cardCvc = scFields.create('ccCvc', {
						classes: elementClasses
						,style: fieldsStyle
					});
					cardCvc.attach(lastCvcHolder);

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
					// CC CVC END
					
					$('body').trigger('processStop');
				}
				// UPO CC
				else if('' !== lastCvcHolder) {
					cardCvc = scFields.create('ccCvc', {
						classes: elementClasses
						,style: fieldsStyle
					});
					cardCvc.attach(lastCvcHolder);

					cardCvc.on('focus', function (e) {
						$('#sc_upo_'+ self.chosenApmMethod() +'_cvc').css('box-shadow', '0px 0 3px 1px #00699d');
						
						$('#sc_upo_'+ self.chosenApmMethod() +'_cvc')
							.closest('.nuvei-apm-method-container')
							.find('fieldset .sc_error')
							.hide();
					});

					cardCvc.on('change', function (e) {
						$('#sc_upo_'+ self.chosenApmMethod() +'_cvc').css('box-shadow', '0px 0 3px 1px #00699d');
						
						$('#sc_upo_'+ self.chosenApmMethod() +'_cvc')
							.closest('.nuvei-apm-method-container')
							.find('fieldset .sc_error')
							.hide();

						if(e.hasOwnProperty('empty')) {
							isCVVEmpty = e.empty;
						}

						if(e.hasOwnProperty('complete')) {
							isCVVComplete = e.complete;
						}
					});
					
					$('body').trigger('processStop');
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
				
				cardNumber = cardExpiry = cardCvc = null;
				$('#sc_card_number, #sc_card_expiry, #sc_card_cvc').html('');
				
				if(lastCvcHolder !== '') {
					$(lastCvcHolder).html('');
				}
			},
			
			scBillingAddrChange: function() {
				console.log('scBillingAddrChange()');
//				console.log('scBillingAddrChange()', quote.billingAddress());
				
				if(quote.billingAddress() == null) {
					console.log('scBillingAddrChange() - the BillingAddr is null. Stop here.');
					return;
				}
				
				if(quote.billingAddress().countryId == self.scBillingCountry) {
					console.log('scBillingAddrChange() - the country is same. Stop here.');
					return;
				}
				
//				console.log('scBillingAddrChange()', JSON.stringify(quote.billingAddress()));
				
				console.log('scBillingAddrChange() - the country was changed to', quote.billingAddress().countryId);
				self.scBillingCountry = quote.billingAddress().countryId;
				
				self.scCleanCard();
				self.getApmMethods(JSON.stringify(quote.billingAddress()));
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
			},
			
			scPaymentMethodChange: function() {
//				console.log('scPaymentMethodChange()', quote.paymentMethod);
				console.log('scPaymentMethodChange()');
				
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
						}
						
						if(jQuery('input[name="nuvei_payment_method"]:checked').val() == 'cc_card') {
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
//				console.log('self.scPaymentMethod', self.scPaymentMethod);
//				console.log('quote.paymentMethod._latestValue.method', quote.paymentMethod._latestValue.method);
				
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
