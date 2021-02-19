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
class UpgradeData extends \Nuvei\Payments\Setup\InstallSchema implements UpgradeDataInterface
{
    /**
     * @var OrderStatusFactory
     */
    private $orderStatusFactory;
    
    private $resourceConnection;
    private $attributeSetFactory;
    private $install;

    /**
     * Object constructor.
     *
     * @param OrderStatusFactory $orderStatusFactory
     */
    public function __construct(
        OrderStatusFactory $orderStatusFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,
        \Magento\Framework\Setup\SchemaSetupInterface $install
    ) {
        $this->orderStatusFactory   = $orderStatusFactory;
        $this->resourceConnection    = $resourceConnection;
        $this->eavSetupFactory      = $eavSetupFactory;
        $this->attributeSetFactory    = $attributeSetFactory;
        $this->install                = $install;
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
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        // try to add main plugin table if not exists, and remove old plugin table if exists
        $this->install($this->install, $context);
        
        $setup->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        
//        $categorySetup = $this->categorySetupFactory->create(['setup' => $setup]);
        
//        $eavSetup->removeAttribute(
//            \Magento\Catalog\Model\Product::ENTITY,
//            \Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT
//        );
        
        // add few new Order States
        if (version_compare($context->getVersion(), '3.0.0', '<')) {
            $scVoided = $this->orderStatusFactory->create()
                ->setData('status', 'nuvei_voided')
                ->setData('label', 'Nuvei Voided')
                ->save();
            $scVoided->assignState(Order::STATE_PROCESSING, false, true);

            $scSettled = $this->orderStatusFactory->create()
                ->setData('status', 'nuvei_settled')
                ->setData('label', 'Nuvei Settled')
                ->save();
            $scSettled->assignState(Order::STATE_PROCESSING, false, true);

//            $scPartiallySettled = $this->orderStatusFactory->create()
//                ->setData('status', 'nuvei_partially_settled')
//                ->setData('label', 'Nuvei Partially Settled')
//                ->save();
//            $scPartiallySettled->assignState(Order::STATE_PROCESSING, false, true);

            $scAuth = $this->orderStatusFactory->create()
                ->setData('status', 'nuvei_auth')
                ->setData('label', 'Nuvei Auth')
                ->save();
            $scAuth->assignState(Order::STATE_PROCESSING, false, true);
            
            $scProcessing = $this->orderStatusFactory->create()
                ->setData('status', 'nuvei_processing')
                ->setData('label', 'Nuvei Processing')
                ->save();
            $scProcessing->assignState(Order::STATE_PROCESSING, false, true);
            
            $scRefunded = $this->orderStatusFactory->create()
                ->setData('status', 'nuvei_refunded')
                ->setData('label', 'Nuvei Refunded')
                ->save();
            $scRefunded->assignState(Order::STATE_PROCESSING, false, true);
        }
        
        /**
         * example for update
        if (version_compare($context->getVersion(), '3.0.1', '<')) {
            $this->resourceConnection->getConnection()->query(
                "UPDATE sales_order_status "
                . "SET status = 'nuvei_voided' "
                . "WHERE sales_order_status.status = 'vmes';"
            );
        }
         */
        
        if (version_compare($context->getVersion(), '3.0.2', '<')) {
            // Enable subscription
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_ENABLE_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'source'    => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'int',
                    'input'                     => 'boolean',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => true,
                    'default'                   => '',
                    'searchable'                => true,
                    'filterable'                => true,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => true,
                    'sort_order'                => 10,
                    'class'                     => 'sc_enable_subscr',
                    'note'                      => 'note',
                ]
            );

            // Plan IDs
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_PLANS_ATTR_NAME,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_PLANS_ATTR_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'source'    => 'Nuvei\Payments\Model\Config\Source\PaymentPlansOptions',
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'int',
                    'input'                     => 'select',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => false,
                    'default'                   => '',
                    'searchable'                => true,
                    'filterable'                => true,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => true,
                    'option'                    => ['values' => []],
                    'sort_order'                => 20,
                ]
            );

            // Initial Amount
//            $eavSetup->addAttribute(
//                \Magento\Catalog\Model\Product::ENTITY,
//                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT,
//                [
//                    'type' => 'decimal',
//                    'label' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_INTIT_AMOUNT_LABEL,
//                    'input' => 'price',
//                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
//                    'visible' => true,
//                    'required' => false,
//                    'user_defined' => true,
//                    'default' => '0',
//                    'searchable' => true,
//                    'filterable' => true,
//                    'visible_on_front' => false,
//                    'used_in_product_listing' => true,
//                    'group' => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
//                    'sort_order' => 30,
//                ]
//            );

            // Recurring Amount
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_REC_AMOUNT,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_REC_AMOUNT_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'decimal',
                    'input'                     => 'price',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => true,
                    'default'                   => '0',
                    'searchable'                => true,
                    'filterable'                => true,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => true,
                    'sort_order'                => 40,
                ]
            );

            // Recurring Units
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_UNITS,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_UNITS_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'source'    => 'Nuvei\Payments\Model\Config\Source\SubscriptionUnits',
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'text',
                    'input'                     => 'select',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => false,
                    'default'                   => 'day',
                    'searchable'                => false,
                    'filterable'                => false,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => false,
                    'option'                    => ['values' => []],
                    'sort_order'                => 50,
                ]
            );

            // Recurring Period
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_PERIOD,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_RECURR_PERIOD_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'int',
                    'input'                     => 'text',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => false,
                    'default'                   => '',
                    'searchable'                => false,
                    'filterable'                => false,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => false,
                    'sort_order'                => 60,
                ]
            );

            // Trial Units
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_UNITS,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_UNITS_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'source'    => 'Nuvei\Payments\Model\Config\Source\SubscriptionUnits',
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'text',
                    'input'                     => 'select',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => false,
                    'default'                   => 'day',
                    'searchable'                => false,
                    'filterable'                => false,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => false,
                    'option'                    => ['values' => []],
                    'sort_order'                => 70,
                ]
            );

            // Trial Period
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_PERIOD,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_TRIAL_PERIOD_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'int',
                    'input'                     => 'text',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => false,
                    'default'                   => '',
                    'searchable'                => false,
                    'filterable'                => false,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => false,
                    'sort_order'                => 80,
                ]
            );

            // End After Units
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_UNITS,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_UNITS_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'source'    => 'Nuvei\Payments\Model\Config\Source\SubscriptionUnits',
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'text',
                    'input'                     => 'select',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => false,
                    'default'                   => 'day',
                    'searchable'                => false,
                    'filterable'                => false,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => false,
                    'option'                    => ['values' => []],
                    'sort_order'                => 90,
                ]
            );

            // End After Period
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_PERIOD,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_END_AFTER_PERIOD_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'int',
                    'input'                     => 'text',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => false,
                    'default'                   => '',
                    'searchable'                => false,
                    'filterable'                => false,
                    'visible_on_front'          => false,
                    'used_in_product_listing'   => false,
                    'sort_order'                => 100,
                ]
            );

            // description of the Subscription for the store
            $eavSetup->addAttribute(
                \Magento\Catalog\Model\Product::ENTITY,
                \Nuvei\Payments\Model\Config::PAYMENT_SUBS_STORE_DESCR,
                [
                    'label'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_STORE_DESCR_LABEL,
                    'global'    => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'group'     => \Nuvei\Payments\Model\Config::PAYMENT_SUBS_GROUP,
                    
                    'type'                      => 'text',
                    'input'                     => 'textarea',
                    'visible'                   => true,
                    'required'                  => false,
                    'user_defined'              => true,
                    'default'                   => '0',
                    'searchable'                => false,
                    'filterable'                => true,
                    'visible_on_front'          => true,
                    'used_in_product_listing'   => true,
                    'sort_order'                => 110,
                ]
            );
        }
        
        $setup->endSetup();
    }
}
