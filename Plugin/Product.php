<?php

/**
 * Class Product
 * 
 * At the moment only modify the visible price in the store.
 * The ne price depends of activated Rebilling Plan for the product.
 */

namespace Nuvei\Payments\Plugin;

class Product {
	private $config;
	private $request;

	public function __construct(
		\Nuvei\Payments\Model\Config $config,
		\Magento\Framework\App\RequestInterface $request
	) {
		$this->config = $config;
		$this->request = $request;
	}
	
	public function afterGetPrice(\Magento\Catalog\Model\Product $product, $result) {
		try {
			$enabled_nuvei_subscr = $product->getCustomAttribute(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE);
			$nuvei_subscr_init_amount = $product->getCustomAttribute(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT);
		
			if(
				!empty($enabled_nuvei_subscr)
				&& !empty($nuvei_subscr_init_amount)
				&& $enabled_nuvei_subscr->getValue() == 1
			) {
				return $nuvei_subscr_init_amount->getValue();
			}
			
			return $result;
		}
		catch(Exception $e) {
			$this->config->createLog($e->getMessage(), 'Product Plugin Exception');
			return $result;
		}
	}
	
//	public function beforeSave(\Magento\Catalog\Model\Product $product) {
////		$this->config->createLog($product->getPrice(), 'Product Plugin beforeSave');
////		$this->config->createLog(@$_POST, 'Product Plugin beforeSave');
//		
//		try {
//			$post_data = $this->request->getPostValue();
//			
//			if(
//				!empty($post_data['product']['price'])
//				&& floatval($post_data['product']['price']) > 0
//				&& !empty($post_data['product'][\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE])
//				&& intval($post_data['product'][\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE]) == 1
//			) {
//				$product->setPrice(0);
//				
////				$post_data['product']['price'] = 0;
////				return $post_data;
//			}
//			
////			$enabled_nuvei_subscr = $product->getCustomAttribute(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE);
////			$nuvei_subscr_init_amount = $product->getCustomAttribute(\Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT);
////		
////			if(
////				!empty($enabled_nuvei_subscr)
////				&& !empty($nuvei_subscr_init_amount)
////				&& $enabled_nuvei_subscr->getValue() == 1
////			) {
//////				return $nuvei_subscr_init_amount->getValue();
////			}
//			
////			return $result;
//		}
//		catch(Exception $e) {
//			$this->config->createLog($e->getMessage(), 'Product Plugin Exception');
//		}
//	}
}
