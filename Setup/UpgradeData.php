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

    /**
     * Object constructor.
     *
     * @param OrderStatusFactory $orderStatusFactory
     */
    public function __construct(
        OrderStatusFactory $orderStatusFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory
    ) {
        $this->orderStatusFactory    = $orderStatusFactory;
        $this->resourceConnection    = $resourceConnection;
        $this->eavSetupFactory        = $eavSetupFactory;
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
    public function upgrade(
        ModuleDataSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        

        // add custom Order Attribute
        if (version_compare($context->getVersion(), '2.2.0', '<')) {
            $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
            
//            $eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'sc_subscription_plans');
            
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
                    'visible_on_front' => true,
                    'used_in_product_listing' => true,
                    'group' => 'General',
                    'option' => [
                        'values' => [],
                    ],
                ]
            );
        }
        
        $setup->startSetup();
            
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
