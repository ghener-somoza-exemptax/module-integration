<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Customer;

use Exemptax\Integration\Model\Config;
use Magento\Customer\Model\Customer;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the per-customer EXEMPTAX ecommerce-drop URL used on storefront and Admin.
 */
class EcommerceDropUrlBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function build(Customer $customer, bool $requireStorefrontEnabled = true): string
    {
        if (!(int) $customer->getId()) {
            return '';
        }

        $websiteId = (int) $customer->getWebsiteId() ?: null;
        if ($requireStorefrontEnabled && !$this->config->isEcommerceDropEnabled($websiteId)) {
            return '';
        }

        $base = $this->config->getEcommerceDropUrl($websiteId);
        if ($base === '') {
            return '';
        }

        try {
            $storeId = (int) $customer->getStoreId();
            $store = $storeId > 0
                ? $this->storeManager->getStore($storeId)
                : $this->storeManager->getWebsite((int) $customer->getWebsiteId())->getDefaultStore();
            $storeBaseUrl = rtrim((string) $store->getBaseUrl(), '/');

            $params = [
                'integration_type' => 'adobe_commerce',
                'store_base_url' => $storeBaseUrl,
                'customer_id' => (string) $customer->getId(),
                'email' => (string) $customer->getEmail(),
                'first_name' => (string) $customer->getFirstname(),
                'last_name' => (string) $customer->getLastname(),
            ];

            $billing = $customer->getDefaultBillingAddress();
            if ($billing && $billing->getId()) {
                $street = $billing->getStreet();
                $params['phone'] = (string) $billing->getTelephone();
                $params['address1'] = (string) ($street[0] ?? '');
                $params['address2'] = (string) ($street[1] ?? '');
                $params['city'] = (string) $billing->getCity();
                $params['zip'] = (string) $billing->getPostcode();
                $params['company'] = (string) $billing->getCompany();
                $params['country'] = (string) $billing->getCountryId();
                $params['address_id'] = (string) $billing->getId();
            }

            return $base . '?' . http_build_query($params);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
