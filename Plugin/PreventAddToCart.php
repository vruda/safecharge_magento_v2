<?php

/**
 * Description of PreventAddToCart
 *
 * A product with a rebilling plan must stay alone in a Cart and an Order.
 */

namespace Nuvei\Payments\Plugin;

class PreventAddToCart
{
    private $config;
    private $request;
    private $messanger;
    private $product_obj;
    private $productRepository;
    private $productTypeInstance ;

    public function __construct(
        \Nuvei\Payments\Model\Config $config,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Message\ManagerInterface $messanger,
        \Magento\Catalog\Model\Product $product_obj,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $productTypeInstance
    ) {
        $this->config               = $config;
        $this->request              = $request;
        $this->messanger            = $messanger;
        $this->product_obj          = $product_obj;
        $this->productRepository    = $productRepository;
        $this->productTypeInstance  = $productTypeInstance ;
    }

    public function beforeAddProduct(\Magento\Checkout\Model\Cart $subject, $productInfo, $requestInfo = null)
    {
        try {
            # 1. first search for SC plan in the items in the cart
            if (!empty($this->config->getProductPlanData())) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('You can not add this product to product with a Payment Plan.')
                );
            }
            
            $this->config->createLog($requestInfo, 'beforeAddProduct() $requestInfo');
            
            $cartItemsCount = $subject->getQuote()->getItemsCount();
            $error_msg_1    = __('You can not add this product to product with a Payment Plan.');
            $error_msg_2    = __('You can not add a product with Payment Plan to another products.');
            $error_msg_3    = __('Only Registered users can purchase Products with Plans.');
            
            # 2. then search for SC plan in the incoming item when there are products in the cart
            if ($cartItemsCount > 0) {
                // 2.1 when we have configurable product with option attribute
                if (!empty($requestInfo['selected_configurable_option'])) {
                    $payment_enabled = $productInfo
                        ->load($requestInfo['selected_configurable_option'])
                        ->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE);
                } else { // 2.2 when we have simple peoduct without options
                    $payment_enabled = $productInfo->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE);
                }
                
                if (!empty($payment_enabled)
                    && is_numeric($payment_enabled)
                    && (int) $payment_enabled == 1
                ) {
                    throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_2));
                }
            } elseif (!$this->config->allowGuestsSubscr()) { // when cart is empty
                throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_3));
            }
        } catch (Exception $e) {
            $this->config->createLog($e->getMessage(), 'Exception:');
        }
    }
}
