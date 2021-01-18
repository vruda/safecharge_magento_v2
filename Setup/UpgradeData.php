<?php

namespace Nuvei\Payments\Setup;

use Magento\Sales\Model\Order\StatusFactory as OrderStatusFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order;

/**
 * Nuvei Payments upgrade data.
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var OrderStatusFactory
     */
    private $orderStatusFactory;
    
    private $resourceConnection;
	private $categorySetupFactory;
	private $attributeSetFactory;

    /**
     * Object constructor.
     *
     * @param OrderStatusFactory $orderStatusFactory
     */
    public function __construct(
        OrderStatusFactory $orderStatusFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
		\Magento\Catalog\Setup\CategorySetupFactory $categorySetupFactory,
		\Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory
    ) {
        $this->orderStatusFactory   = $orderStatusFactory;
        $this->resourceConnection	= $resourceConnection;
        $this->eavSetupFactory      = $eavSetupFactory;
        $this->categorySetupFactory	= $categorySetupFactory;
        $this->attributeSetFactory	= $attributeSetFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface   $context
     *
     * @return void
     * @throws \Exception
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context) {
        $setup->startSetup();
		$eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
		
		$categorySetup = $this->categorySetupFactory->create(['setup' => $setup]);
		
//		$eavSetup->removeAttribute(
//			\Magento\Catalog\Model\Product::ENTITY,
//			\Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT
//		);
		
		// add few new Order States
        if (version_compare($context->getVersion(), '2.0.2', '<')) {
            $scVoided = $this->orderStatusFactory->create()
                ->setData('status', 'sc_voided')
                ->setData('label', 'SC Voided')
                ->save();
            $scVoided->assignState(Order::STATE_PROCESSING, false, true);

            $scSettled = $this->orderStatusFactory->create()
                ->setData('status', 'sc_settled')
                ->setData('label', 'SC Settled')
                ->save();
            $scSettled->assignState(Order::STATE_PROCESSING, false, true);

            $scPartiallySettled = $this->orderStatusFactory->create()
                ->setData('status', 'sc_partially_settled')
                ->setData('label', 'SC Partially Settled')
                ->save();
            $scPartiallySettled->assignState(Order::STATE_PROCESSING, false, true);

            $scAuth = $this->orderStatusFactory->create()
                ->setData('status', 'sc_auth')
                ->setData('label', 'SC Auth')
                ->save();
            $scAuth->assignState(Order::STATE_PROCESSING, false, true);
            
            $scProcessing = $this->orderStatusFactory->create()
                ->setData('status', 'sc_processing')
                ->setData('label', 'SC Processing')
                ->save();
            $scProcessing->assignState(Order::STATE_PROCESSING, false, true);
            
            $scRefunded = $this->orderStatusFactory->create()
                ->setData('status', 'sc_refunded')
                ->setData('label', 'SC Refunded')
                ->save();
            $scRefunded->assignState(Order::STATE_PROCESSING, false, false);
        }
        // a patch for last three statuses above
        elseif (version_compare($context->getVersion(), '2.0.3', '<')) {
            $this->resourceConnection->getConnection()->query("UPDATE sales_order_status_state SET is_default = 0 WHERE sales_order_status_state.status = 'sc_refunded';");
            $this->resourceConnection->getConnection()->query("UPDATE sales_order_status_state SET is_default = 0 WHERE sales_order_status_state.status = 'sc_processing';");
            $this->resourceConnection->getConnection()->query("UPDATE sales_order_status_state SET is_default = 0 WHERE sales_order_status_state.status = 'sc_auth';");
        }
		
		if (version_compare($context->getVersion(), '2.2.0', '<')) {
			$eavSetup->removeAttribute(
				\Magento\Catalog\Model\Product::ENTITY,
				\Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE
			);
			
			/*
			// Enable subscription
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE,
                [
                    'type' => 'int',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE_LABEL,
                    'input' => 'boolean',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'source'   => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
					'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 10,
					'class' => 'sc_enable_subscr',
					'note' => 'note',
                ]
            );
			
			// Plan IDs
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_PLANS_ATTR_NAME,
                [
                    'type' => 'int',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_PLANS_ATTR_LABEL,
                    'input' => 'select',
                    'source' => 'Nuvei\Payments\Model\Config\Source\PaymentPlansOptions',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => '',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    'option' => [
                        'values' => [],
                    ],
					'sort_order' => 20,
                ]
            );
			
			// Initial Amount
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT,
                [
                    'type' => 'decimal',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT_LABEL,
                    'input' => 'price',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 30,
                ]
            );
			
			// Recurring Amount
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_REC_AMOUNT,
                [
                    'type' => 'decimal',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_REC_AMOUNT_LABEL,
                    'input' => 'price',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 40,
                ]
            );
			
			// Recurring Units
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_UNITS,
                [
                    'type' => 'text',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_UNITS_LABEL,
                    'input' => 'select',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
					'source' => 'Nuvei\Payments\Model\Config\Source\SubscriptionUnits',
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => 'day',
                    'searchable' => false,
                    'filterable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'option' => [
                        'values' => [],
                    ],
					'sort_order' => 50,
                ]
            );
			
			// Recurring Period
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_PERIOD,
                [
                    'type' => 'int',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_PERIOD_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => '',
                    'searchable' => false,
                    'filterable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 60,
                ]
            );
			
			// Trial Units
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_UNITS,
                [
                    'type' => 'text',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_UNITS_LABEL,
                    'input' => 'select',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
					'source' => 'Nuvei\Payments\Model\Config\Source\SubscriptionUnits',
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => 'day',
                    'searchable' => false,
                    'filterable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'option' => [
                        'values' => [],
                    ],
					'sort_order' => 70,
                ]
            );
			
			// Trial Period
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_PERIOD,
                [
                    'type' => 'int',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_PERIOD_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => '',
                    'searchable' => false,
                    'filterable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 80,
                ]
            );
			
			// End After Units
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_UNITS,
                [
                    'type' => 'text',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_UNITS_LABEL,
                    'input' => 'select',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
					'source' => 'Nuvei\Payments\Model\Config\Source\SubscriptionUnits',
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => 'day',
                    'searchable' => false,
                    'filterable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'option' => [
                        'values' => [],
                    ],
					'sort_order' => 90,
                ]
            );
			
			// End After Period
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_PERIOD,
                [
                    'type' => 'int',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_PERIOD_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'default' => '',
                    'searchable' => false,
                    'filterable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 100,
                ]
            );
			
			// description of the Subscription for the store
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_STORE_DESCR,
                [
                    'type' => 'text',
                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_STORE_DESCR_LABEL,
                    'input' => 'textarea',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => false,
                    'filterable' => true,
                    'visible_on_front' => true,
                    'used_in_product_listing' => true,
                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 110,
                ]
            );
			 */
			
			// Add two new statuses for the Subscriptions
			/*
			$scSubscrStarted = $this->orderStatusFactory->create()
                ->setData('status', 'sc_subscr_started')
                ->setData('label', 'Nuvei Subscription Started')
                ->save();
            $scSubscrStarted->assignState(Order::STATE_PROCESSING, false, true);
			
			$scSubscrEnded = $this->orderStatusFactory->create()
                ->setData('status', 'sc_subscr_ended')
                ->setData('label', 'Nuvei Subscription Ended')
                ->save();
            $scSubscrEnded->assignState(Order::STATE_PROCESSING, false, true);
			 */
			
			// modify old order States
			$scVoided = $this->orderStatusFactory->create()
                ->setData('status', 'sc_voided')
                ->setData('label', 'Nuvei Voided')
                ->save();
            $scVoided->assignState(Order::STATE_PROCESSING, false, true);

            $scSettled = $this->orderStatusFactory->create()
                ->setData('status', 'sc_settled')
                ->setData('label', 'Nuvei Settled')
                ->save();
            $scSettled->assignState(Order::STATE_PROCESSING, false, true);

            $scPartiallySettled = $this->orderStatusFactory->create()
                ->setData('status', 'sc_partially_settled')
                ->setData('label', 'Nuvei Partially Settled')
                ->save();
            $scPartiallySettled->assignState(Order::STATE_PROCESSING, false, true);

            $scAuth = $this->orderStatusFactory->create()
                ->setData('status', 'sc_auth')
                ->setData('label', 'Nuvei Auth')
                ->save();
            $scAuth->assignState(Order::STATE_PROCESSING, false, true);
            
            $scProcessing = $this->orderStatusFactory->create()
                ->setData('status', 'sc_processing')
                ->setData('label', 'Nuvei Processing')
                ->save();
            $scProcessing->assignState(Order::STATE_PROCESSING, false, true);
            
            $scRefunded = $this->orderStatusFactory->create()
                ->setData('status', 'sc_refunded')
                ->setData('label', 'Nuvei Refunded')
                ->save();
            $scRefunded->assignState(Order::STATE_PROCESSING, false, false);
		}
        
        $setup->endSetup();
    }
}
