var config = {
    paths: {
        'jquery.redirect': "Safecharge_Safecharge/js/jquery.redirect"
    },
    shim: {
        'jquery.redirect': {
            deps: ['jquery']
        },
    },
	'config': {
		'mixins': {
			'Magento_Checkout/js/action/set-shipping-information': {
                'Safecharge_Safecharge/js/scShippingHook': true
            }
		}
	}
};