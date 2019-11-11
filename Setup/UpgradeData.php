<?php

namespace Safecharge\Safecharge\Setup;

use Magento\Sales\Model\Order\StatusFactory as OrderStatusFactory;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Model\Order;

/**
 * Safecharge Safecharge upgrade data.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var OrderStatusFactory
     */
    private $orderStatusFactory;

    /**
     * Object constructor.
     *
     * @param OrderStatusFactory $orderStatusFactory
     */
    public function __construct(
        OrderStatusFactory $orderStatusFactory
    ) {
        $this->orderStatusFactory = $orderStatusFactory;
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
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1', '<')) {
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
            $scAuth->assignState(Order::STATE_PROCESSING, true, true);
        }

        $setup->endSetup();
    }
}
