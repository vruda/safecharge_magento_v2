<?php

namespace Safecharge\Safecharge\Setup;

use Magento\Sales\Model\Order\StatusFactory as OrderStatusFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order;

/**
 * Safecharge Safecharge upgrade data.
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
//			\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT
//		);
//		$eavSetup->removeAttribute(
//			\Magento\Catalog\Model\Product::ENTITY,
//			\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_AMOUNT
//		);
//		$eavSetup->removeAttribute(
//			\Magento\Catalog\Model\Product::ENTITY,
//			\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_DAY
//		);
//		$eavSetup->removeAttribute(
//			\Magento\Catalog\Model\Product::ENTITY,
//			\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_MONTH
//		);
//		$eavSetup->removeAttribute(
//			\Magento\Catalog\Model\Product::ENTITY,
//			\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_YEAR
//		);
//		$eavSetup->removeAttribute(
//			\Magento\Catalog\Model\Product::ENTITY,
//			\Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME
//		);
		
        // add custom Order Attribute
//        if (version_compare($context->getVersion(), '2.2.0', '<')) {
//            $eavSetup->addAttribute(
//                \Magento\Catalog\Model\Product::ENTITY,
//                \Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME,
//                [
//                    'type' => 'int',
//                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_LABEL,
//                    'input' => 'select',
//                    'source' => 'Safecharge\Safecharge\Model\Config\Source\PaymentPlansOptions',
//                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
//                    'visible' => true,
//                    'required' => false,
//                    'user_defined' => true,
//                    'default' => '',
//                    'searchable' => true,
//                    'filterable' => true,
//                    'visible_on_front' => true,
//                    'used_in_product_listing' => true,
//                    'group' => 'General',
//                    'option' => [
//                        'values' => [],
//                    ],
//                ]
//            );
//        }
		
		if (version_compare($context->getVersion(), '2.3.0', '<')) {
			$eavSetup->removeAttribute(
				\Magento\Catalog\Model\Product::ENTITY,
				\Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_ENABLE
			);
			
			// Enable subscription
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_ENABLE,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_ENABLE_LABEL,
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
					'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 10,
					'class' => 'sc_enable_subscr',
					'description' => 'description',
					'note' => 'note',
                ]
            );
			
			// Plan IDs
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_NAME,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_PLANS_ATTR_LABEL,
                    'input' => 'select',
                    'source' => 'Safecharge\Safecharge\Model\Config\Source\PaymentPlansOptions',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
                    'option' => [
                        'values' => [],
                    ],
					'sort_order' => 20,
                ]
            );
			
			// Initial Amount
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT,
                [
                    'type' => 'decimal',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT_LABEL,
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
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 30,
                ]
            );
			
			// Recurring Amount
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_AMOUNT,
                [
                    'type' => 'decimal',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_AMOUNT_LABEL,
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
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 40,
                ]
            );
			
			// Subscription start after day
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_DAY,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_DAY_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 50,
                ]
            );
			
			// Subscription start after month
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_MONTH,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_MONTH_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 60,
                ]
            );
			
			// Subscription start after year
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_YEAR,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_AFTER_YEAR_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 70,
                ]
            );
			
			// Recurring period day
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_DAY,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_DAY_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 80,
                ]
            );
			
			// Recurring period month
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_MONTH,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_MONTH_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 90,
                ]
            );
			
			// Recurring period year
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_YEAR,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_REC_YEAR_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 100,
                ]
            );
			
			// Subscription end after days
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_DAY,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_DAY_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 110,
                ]
            );
			
			// Subscription end after months
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_MONTHS,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_MONTHS_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 120,
                ]
            );
			
			// Subscription end after years
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_YEARS,
                [
                    'type' => 'int',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_END_YEARS_LABEL,
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 130,
                ]
            );
			
			// description of the Subscription for the store
			$eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_STORE_DESCR,
                [
                    'type' => 'text',
                    'label' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_STORE_DESCR_LABEL,
                    'input' => 'textarea',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => true,
                    'default' => '0',
                    'searchable' => true,
                    'filterable' => true,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'group' => \Safecharge\Safecharge\Model\Config::PAYMENT_SUBS_GROUP,
					'sort_order' => 140,
                ]
            );
		}
        
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

        $setup->endSetup();
    }
}
