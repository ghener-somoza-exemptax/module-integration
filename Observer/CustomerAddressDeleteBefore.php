<?php

declare(strict_types=1);

namespace Exemptax\Integration\Observer;

use Exemptax\Integration\Model\Webhook\Publisher;
use Magento\Customer\Model\Address;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Capture customer_id before address resource delete clears model data.
 * Publisher posts after the DB commit.
 */
class CustomerAddressDeleteBefore implements ObserverInterface
{
    public function __construct(
        private readonly Publisher $publisher
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Address|null $address */
        $address = $observer->getEvent()->getCustomerAddress();
        if (!$address || !(int) $address->getCustomerId()) {
            return;
        }

        $this->publisher->publishCustomerAddressDelete($address);
    }
}
