/**
 * Safecharge Safecharge js component.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'safecharge',
                component: 'Safecharge_Safecharge/js/view/payment/method-renderer/safecharge'
            }
        );

        return Component.extend({});
    }
);