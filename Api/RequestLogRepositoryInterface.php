<?php

namespace Safecharge\Safecharge\Api;

use Safecharge\Safecharge\Api\Data\RequestLogInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Safecharge Safecharge request log repository interface.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
interface RequestLogRepositoryInterface
{
    /**
     * Create request log.
     *
     * @api
     *
     * @param RequestLogInterface $requestLogData Request log data object.
     *
     * @return RequestLogInterface
     */
    public function save(RequestLogInterface $requestLogData);

    /**
     * Retrieve request log by id.
     *
     * @api
     *
     * @param int $requestId Request id.
     *
     * @return RequestLogInterface
     * @throws LocalizedException
     */
    public function getById($requestId);

    /**
     * Delete request log.
     *
     * @api
     *
     * @param RequestLogInterface $requestLogData Request log data object.
     *
     * @return bool
     */
    public function delete(RequestLogInterface $requestLogData);

    /**
     * Delete request log by id.
     *
     * @api
     *
     * @param int $requestId Request id.
     *
     * @return bool
     */
    public function deleteById($requestId);
}
