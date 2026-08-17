<?php

declare(strict_types=1);

namespace Exemptax\Integration\Observer;

use Exemptax\Integration\Model\Webhook\Publisher;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Covers Admin grid inline edit and other CustomerRepository::save() paths.
 * Runs after the repository save (and address writes) completes.
 */
class CustomerSaveAfterDataObject implements ObserverInterface
{
    public function __construct(
        private readonly Publisher $publisher
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var CustomerInterface|null $customer */
        $customer = $observer->getEvent()->getCustomerDataObject();
        if (!$customer || !$customer->getId()) {
            return;
        }

        $this->publisher->publishCustomerSaveImmediate($customer, 'customer/updated');
    }
}
