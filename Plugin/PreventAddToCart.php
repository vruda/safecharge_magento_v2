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

    public function __construct(
        \Nuvei\Payments\Model\Config $config,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Message\ManagerInterface $messanger,
        \Magento\Catalog\Model\Product $product_obj
    ) {
        $this->config       = $config;
        $this->request      = $request;
        $this->messanger    = $messanger;
        $this->product_obj  = $product_obj;
    }

    public function beforeAddProduct(\Magento\Checkout\Model\Cart $subject, $productInfo, $requestInfo = null)
    {
        try {
            $cartItemsCount = $subject->getQuote()->getItemsCount();
            $error_msg_1    = 'You can not add this product to product with a Payment Plan.';
            $error_msg_2    = 'You can not add a product with Payment Plan to another products.';
            
            if ($cartItemsCount > 0) {
                $sc_label       = \Nuvei\Payments\Model\Config::PAYMENT_PLANS_ATTR_LABEL;
                $main_product   = $this->request->getParam('product', false);
                $product_opt_id = $this->request->getParam('selected_configurable_option', false);
                
                # 1. first search for SC plan in the items in the cart
                $items = $subject->getItems();
                
                foreach ($items as $item) {
                    $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
                    
                    $this->config->createLog($options, '$options');
                    
                    /**
                     * This is the case when we have configurable product by some of our Subscription settings.
                     * For the moment we do not allow this, adding to the drop-downs user_defined = false
                     */
                    // 1.1 in case of configurable product
//                    if(!empty($options['attributes_info']) && is_array($options['attributes_info'])) {
//                        foreach($options['attributes_info'] as $data) {
//                            if(
//                                !empty($data['label'])
//                                && $sc_label == $data['label']
//                                && isset($data['option_value'])
//                                && intval($data['option_value']) > 1
//                            ) {
//                                throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_1));
//                            }
//                        }
//                    }
                    
                    // 1.2 in case of simple product
                    if (!empty($options['info_buyRequest']) && is_array($options['info_buyRequest'])) {
                        $product = $this->product_obj->load($options['info_buyRequest']['product']);
                        
                        $prod_custom_attr = $product
                            ->getCustomAttribute(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE);
                        
                        $this->config->createLog($prod_custom_attr, 'simple product $prod_custom_attr');
                        
                        if (!empty($prod_custom_attr) && $prod_custom_attr->getValue() > 0) {
                            throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_1));
                        }
                    }
                }
                # 1. first search for SC plan in the items in the cart END
                
                # 2. then search for SC plan in the incoming item
                // 2.1 when we have configurable product with option attribute
                if ($main_product && $product_opt_id && $product_opt_id !== $main_product) {
                    $payment_enabled = $productInfo
                        ->load($product_opt_id)
                        ->getData(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE);
                    
                    if (!empty($payment_enabled)
                        && is_numeric($payment_enabled)
                        && (int) $payment_enabled > 1
                    ) {
                        throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_2));
                        
                    }
                } elseif ($main_product) { // 2.2 when we have simple peoduct without options
                    $product = $this->product_obj->load($main_product);
                    $payment_enabled = $product->getCustomAttribute(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE);

                    if (!empty($payment_enabled) && $payment_enabled->getValue() > 0) {
                        throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_2));
                    }
                    
                    $this->config->createLog($prod_custom_attr, '$prod_custom_attr 2');
                }
                # 2. then search for SC plan in the incoming item END
            }
        } catch (Exception $e) {
            $this->config->createLog($e->getMessage(), 'Exception:');
        }
    }
}
