<?php

namespace Safecharge\Safecharge\Model\Plugin\Service\CreditmemoService;

use Safecharge\Safecharge\Api\Data\RequestLogInterface;
use Safecharge\Safecharge\Model\RequestLogFactory;
use Safecharge\Safecharge\Model\Logger;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry as CoreRegistry;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Service\CreditmemoService;

/**
 * Safecharge Safecharge credit memo service plugin model.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class Plugin
{
    /**
     * @var CoreRegistry
     */
    private $coreRegistry;

    /**
     * @var RequestLogFactory
     */
    private $requestLogFactory;

    /**
     * Object constructor.
     *
     * @param CoreRegistry                  $coreRegistry
     * @param RequestLogFactory $requestLogFactory
     */
    public function __construct(
        CoreRegistry $coreRegistry,
        RequestLogFactory $requestLogFactory
    ) {
        $this->coreRegistry = $coreRegistry;
        $this->requestLogFactory = $requestLogFactory;
    }

    /**
     * @param CreditmemoService $creditmemoService
     * @param \Closure          $closure
     * @param Creditmemo        $creditmemo
     * @param bool              $offlineRequested
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundRefund(
        CreditmemoService $creditmemoService,
        \Closure $closure,
        Creditmemo $creditmemo,
        $offlineRequested
    ) {
        try {
            $closure($creditmemo, $offlineRequested);
        } catch (LocalizedException $e) {
            /** @var RequestLogInterface $currentRequestLog */
            $currentRequestLog = $this->coreRegistry->registry(Logger::CURRENT_REQUEST_LOG);
            if ($currentRequestLog !== null) {
                $requestLog = $this->requestLogFactory->create();
                $requestLog->updateData($currentRequestLog);
                $requestLog->forceSave();
            }

            throw $e;
        }
    }
}
