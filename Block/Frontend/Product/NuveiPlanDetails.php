<?php

namespace Nuvei\Payments\Block\Frontend\Product;

use Nuvei\Payments\Model\Config;

class NuveiPlanDetails extends \Magento\Catalog\Block\Product\View
{
    private $urlBuilder;
    private $configurable;
    
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context, 
        \Magento\Framework\Url\EncoderInterface $urlEncoder, 
        \Magento\Framework\Json\EncoderInterface $jsonEncoder, 
        \Magento\Framework\Stdlib\StringUtils $string, 
        \Magento\Catalog\Helper\Product $productHelper, 
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypeConfig, 
        \Magento\Framework\Locale\FormatInterface $localeFormat, 
        \Magento\Customer\Model\Session $customerSession, 
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository, 
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency, 
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurable,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $data = array()
    ) {
        parent::__construct(
            $context, 
            $urlEncoder, 
            $jsonEncoder, 
            $string, 
            $productHelper, 
            $productTypeConfig, 
            $localeFormat, 
            $customerSession, 
            $productRepository, 
            $priceCurrency, 
            $data
        );
        
        $this->configurable = $configurable;
        $this->urlBuilder   = $urlBuilder;
    }

    /**
     * Function isProductWithPlan
     * 
     * Checks if the product has Nuvei Plan options
     * 
     * @return bool
     */
    public function isProductWithPlan()
    {
        $product                        = $this->getProduct();
        $is_product_with_nuvei_options  = 0;
        $nuvei_subscr_enable            = $product->getCustomAttribute(Config::PAYMENT_SUBS_ENABLE);
        
        if(null !== $nuvei_subscr_enable && 1 == $nuvei_subscr_enable->getValue()) {
            $is_product_with_nuvei_options = 1;
        }
        
        $productAttributeOptions = $this->configurable->getConfigurableAttributesAsArray($product);
        
        foreach($productAttributeOptions as $key => $data) {
            if(isset($data['attribute_code'])
                && false !== strpos($data['attribute_code'], 'nuvei')
            ) {
                $is_product_with_nuvei_options = 1;
                break;
            }
        }
        
        return $is_product_with_nuvei_options;
    }
    
    /**
     * Function getTexts
     * 
     * Get translated texts.
     * 
     * @return array
     */
    public function getTexts() {
        return [
            'table_title'       => __('Nuvei Plan Details'),
            'rec_length'        => __('Recurring length'),
            'rec_period'        => __('Recurring period'),
            'rec_amount'        => __('Recurring amount'),
            'trial_period'      => __('Trial period'),
            'nuvei_ajax_url'    => $this->urlBuilder->getUrl('nuvei_payments/frontend/order_subscriptionsHistory'),
            'nuvei_prod_id'     => $this->getProduct()->getId(),
        ];
    }
    
    public function testCall($options_string) {
        return $options_string;
    }
    
    
    
    public function isProductWithPlanCopy()
    {
        $product                        = $this->getProduct();
        $is_product_with_nuvei_options  = 0;
        $nuvei_subscr_enable            = $product->getCustomAttribute(Config::PAYMENT_SUBS_ENABLE);
        $nuvei_rec_amount               = $product->getCustomAttribute(Config::PAYMENT_SUBS_REC_AMOUNT);
        $nuvei_rec_units                = $product->getCustomAttribute(Config::PAYMENT_SUBS_RECURR_UNITS);
        $nuvei_rec_period               = $product->getCustomAttribute(Config::PAYMENT_SUBS_RECURR_PERIOD);
        $nuvei_rec_trail_units          = $product->getCustomAttribute(Config::PAYMENT_SUBS_TRIAL_UNITS);
        $nuvei_rec_trail_period         = $product->getCustomAttribute(Config::PAYMENT_SUBS_TRIAL_PERIOD);
        $nuvei_rec_end_after_units      = $product->getCustomAttribute(Config::PAYMENT_SUBS_END_AFTER_UNITS);
        $nuvei_rec_end_after_period     = $product->getCustomAttribute(Config::PAYMENT_SUBS_END_AFTER_PERIOD);
        
//        echo '<pre>'.print_r([
//            '$nuvei_subscr_enable' => $nuvei_subscr_enable,
//            '$nuvei_rec_amount' => $nuvei_rec_amount,
//            '$nuvei_rec_units' => $nuvei_rec_units,
//            '$nuvei_rec_period' => $nuvei_rec_period,
//            '$nuvei_rec_trail_units' => $nuvei_rec_trail_units,
//            '$nuvei_rec_trail_period' => $nuvei_rec_trail_period,
//            '$nuvei_rec_end_after_units' => $nuvei_rec_end_after_units,
//            '$nuvei_rec_end_after_period' => $nuvei_rec_end_after_period,
//        ],true).'</pre>';
        
        if(null !== $nuvei_subscr_enable && 1 == $nuvei_subscr_enable->getValue()) {
            $is_product_with_nuvei_options = 1;
        }
        
//        $options = (array)$product->getOptions();
//        echo '<pre>options '.print_r($options,true).'</pre>';
        
//        foreach($options as $option) {
//            $optionValues = $option->getValues() ? $option->getValues() : [];
//            
//            echo '<pre>getOptionId '.print_r($option->getOptionId(),true).'</pre>';
//        }
        
        $options = $product->getTypeInstance()->getConfigurableOptions($product);
        //echo '<pre>options '.print_r($options,true).'</pre>';
        
        
        $productAttributeOptions = $this->configurable->getConfigurableAttributesAsArray($product);
        
        echo '<pre>$productAttributeOptions '.print_r($productAttributeOptions,true).'</pre>';
        echo '<pre>product id '.print_r($product->getId(),true).'</pre>';
        
        foreach($productAttributeOptions as $key => $data) {
//            $attributeId = (int)$data->getAttributeId();
//            echo '<pre>getAttributeId '.print_r($attributeId,true).'</pre>';
            
//            $attributeCode = $this->getAttributeCode($attributeId);
//            echo '<pre>$attributeCode '.print_r($attributeCode,true).'</pre>';
            
            
            
        
            
//            if(
//                isset($data['attribute_code'])
//                && false !== strpos($data['attribute_code'], 'nuvei')
//            ) {
//                $is_product_with_nuvei_options = 1;
//                break;
//            }
        }
        
        
        $children = $product->getTypeInstance()->getUsedProducts($product);
        foreach($children as $child) {
            echo '<pre>$child id '.print_r($child->getId(),true).'</pre>';
            echo '<pre>$child id '.print_r($child->getSku(),true).'</pre>';
        }
        
        
        // $configId => $superAttribute
        // 195 => 3568
//        $_configProduct = $this->productRepository->getById(195);
        $usedChild = $this->configurable->getProductByAttributes([195 => 3568] ,$product);
        $childProductId = $usedChild->getId();
        
        echo '<pre>$childProductId '.print_r($childProductId,true).'</pre>';
//        $childProduct  = $this->configurable->getProductByAttributes(3568, $product);
        
        
        return $is_product_with_nuvei_options;
    }
}
