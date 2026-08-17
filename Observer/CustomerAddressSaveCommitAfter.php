<?php

declare(strict_types=1);

namespace Exemptax\Integration\Observer;

use Exemptax\Integration\Model\Webhook\Publisher;
use Magento\Customer\Model\Address;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Address-only saves (AddressRepository / admin address UI).
 * CustomerRepository saves that include addresses also hit this; Publisher dedupes.
 */
class CustomerAddressSaveCommitAfter implements ObserverInterface
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

        $this->publisher->publishCustomerAddressSave($address);
    }
}
