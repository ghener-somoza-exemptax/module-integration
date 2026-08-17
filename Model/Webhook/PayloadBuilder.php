<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Webhook;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Model\StoreManagerInterface;

class PayloadBuilder
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpRequest $request
    ) {
    }

    /**
     * @return array{scope: string, data: array{id: int}, store_base_url: string}
     */
    public function buildCustomerEvent(string $scope, int $customerId): array
    {
        return [
            'scope' => $scope,
            'data' => [
                'id' => $customerId,
            ],
            'store_base_url' => $this->getStoreBaseUrl(),
        ];
    }

    public function resolveCustomerScope(Customer|CustomerInterface $customer): string
    {
        // After save, isObjectNew is usually false; treat brand-new rows as created.
        if ($customer instanceof Customer) {
            $created = (string) $customer->getCreatedAt();
            $updated = (string) $customer->getUpdatedAt();
            if ($created !== '' && $created === $updated) {
                return 'customer/created';
            }
        }

        return 'customer/updated';
    }

    public function getStoreBaseUrl(): string
    {
        try {
            $store = $this->storeManager->getStore();
            $base = rtrim((string) $store->getBaseUrl(), '/');
            if ($base !== '') {
                return $base;
            }
        } catch (\Throwable) {
            // fall through
        }

        $host = (string) $this->request->getHttpHost();
        if ($host !== '') {
            $scheme = $this->request->isSecure() ? 'https' : 'http';

            return $scheme . '://' . $host;
        }

        return 'https://magento.test:9443';
    }
}
