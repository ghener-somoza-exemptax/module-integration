<?php

declare(strict_types=1);

namespace Exemptax\Integration\Observer;

use Exemptax\Integration\Model\Webhook\Publisher;
use Magento\Customer\Model\Customer;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Fires after the customer delete transaction commits.
 */
class CustomerDeleteCommitAfter implements ObserverInterface
{
    public function __construct(
        private readonly Publisher $publisher
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Customer|null $customer */
        $customer = $observer->getEvent()->getCustomer();
        if (!$customer || !$customer->getId()) {
            return;
        }

        $this->publisher->publishCustomerDelete($customer);
    }
}
