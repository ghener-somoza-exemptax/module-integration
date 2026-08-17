<?php

declare(strict_types=1);

namespace Exemptax\Integration\Plugin\Customer;

use Exemptax\Integration\Model\TaxJar\CustomerExemptionSynchronizer;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * After CustomerRepository::save, sync tj_* exemption attrs to TaxJar (REST / non-admin).
 */
class SyncTaxJarCustomerExemption
{
    public function __construct(
        private readonly CustomerExemptionSynchronizer $synchronizer
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSave(
        CustomerRepositoryInterface $subject,
        CustomerInterface $result,
        CustomerInterface $customer,
        $passwordHash = null
    ): CustomerInterface {
        $this->synchronizer->sync($result);

        return $result;
    }
}
