<?php

/**
 * Description of PreventAddToCart
 *
 * @author Safecharge
 */

namespace Safecharge\Safecharge\Plugin;

class PreventAddToCart
{
	public function beforeAddProduct(\Magento\Checkout\Model\Cart $subject, $productInfo, $requestInfo = null)
    {
		/*
		try {
			$cartItemsCount	= $subject->getQuote()->getItemsCount();
			$error_msg_1	= 'You can not add product to product with a Payment Plan.';
			$error_msg_2	= 'You can not add product with a Payment Plan to another products.';
			
			if($cartItemsCount > 0) {
				$objectManager	= \Magento\Framework\App\ObjectManager::getInstance();
			
				$config			= $objectManager->create('Safecharge\Safecharge\Model\Config');
				$request		= $objectManager->create('Magento\Framework\App\Request\Http');
				$messanger		= $objectManager->create('Magento\Framework\Message\ManagerInterface');

				$sc_label		= \Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_LABEL;
				$main_product	= $request->getParam('product', false);
				$product_opt_id	= $request->getParam('selected_configurable_option', false);
				
				# 1. first search for SC plan in the items in the cart
				$items = $subject->getItems();
				
				foreach ($items as $item) {
					$options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
					
					// in case of configurable product
					if(!empty($options['attributes_info']) && is_array($options['attributes_info'])) {
						foreach($options['attributes_info'] as $data) {
							if(
								!empty($data['label'])
								&& $sc_label == $data['label']
								&& isset($data['option_value'])
								&& intval($data['option_value']) > 1
							) {
								$config->createLog($options, $error_msg_1);
								$messanger->addError(__($error_msg_1));
								throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_1));
							}
						}
					}
					
					// in case of simple product
					if(!empty($options['info_buyRequest']) && is_array($options['info_buyRequest'])) {
						$product = $objectManager->create('Magento\Catalog\Model\Product')->load($options['info_buyRequest']['product']);
						$prod_custom_attr = $product->getCustomAttribute(\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME);
						
						if($prod_custom_attr && $prod_custom_attr->getValue() > 1) {
							$config->createLog($options, $error_msg_1);
							$messanger->addError(__($error_msg_1));
							throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_1));
						}
					}

				}
				# 1. first search for SC plan in the items in the cart END
				
				
				# 2. then search for SC plan in the incoming item
				// when we have configurable product with option attribute
				if($main_product && $product_opt_id && $product_opt_id !== $main_product) {
					$payment_plan_id = $productInfo->load($product_opt_id)
						->getData(\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME);
					
					if(
						$payment_plan_id !== false
						&& $payment_plan_id != null
						&& is_numeric($payment_plan_id)
						&& intval($payment_plan_id) > 1
					) {
						$config->createLog(
							[
								'$product_opt_id' => $product_opt_id,
								'$payment_plan_id' => $payment_plan_id
							],
							$error_msg_2
						);
						
						$messanger->addError(__($error_msg_2));
						throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_2));
						
					}
				}
				// when we have simple peoduct without options
				elseif($main_product) {
					$product = $objectManager->create('Magento\Catalog\Model\Product')->load($main_product);
					$prod_custom_attr = $product->getCustomAttribute(\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME);

					if($prod_custom_attr && $prod_custom_attr->getValue() > 1) {
						$config->createLog($options, $error_msg_2);
						$messanger->addError(__($error_msg_2));
						throw new \Magento\Framework\Exception\LocalizedException(__($error_msg_2));
					}
				}
				# 2. then search for SC plan in the incoming item END
			}
		}
		catch(Exception $e) {
			$config->createLog($e->getMessage(), 'Exception:');
		}
		
//        return [$productInfo,$requestInfo];
		 */
    }
}
