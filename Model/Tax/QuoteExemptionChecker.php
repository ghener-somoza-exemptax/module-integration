<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Tax;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Customer\Attribute\Source\ExemptionStatus;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Psr\Log\LoggerInterface;

/**
 * Decide whether a quote shipping assignment should be tax-exempt via Exemptax states.
 */
class QuoteExemptionChecker
{
    /** @var array<int, array{states: list<string>, status: string}> */
    private array $customerExemptionCache = [];

    public function __construct(
        private readonly Config $config,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ExemptionStates $exemptionStates,
        private readonly RegionFactory $regionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function shouldExemptQuote(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment
    ): bool {
        $store = $quote->getStore();
        $websiteId = $store ? (int) $store->getWebsiteId() : null;
        if (!$this->config->isStateExemptionEnabled($websiteId)) {
            return false;
        }

        $customerId = (int) $quote->getCustomerId();
        if ($customerId <= 0) {
            return false;
        }

        $address = $shippingAssignment->getShipping()?->getAddress();
        if (!$address instanceof Address) {
            return false;
        }

        $countryId = strtoupper((string) $address->getCountryId());
        if ($countryId !== '' && $countryId !== 'US') {
            // Phase 2: US state codes only.
            return false;
        }

        $exemption = $this->getCustomerExemption($customerId);

        if ($this->config->isGrandfatheredEntireExemptionEnabled($websiteId)) {
            return $exemption['status'] === ExemptionStatus::VALUE_ACTIVE;
        }

        $regionCode = $this->resolveRegionCode($address);

        return $this->exemptionStates->contains($exemption['states'], $regionCode);
    }

    /**
     * @return array{states: list<string>, status: string}
     */
    private function getCustomerExemption(int $customerId): array
    {
        if (isset($this->customerExemptionCache[$customerId])) {
            return $this->customerExemptionCache[$customerId];
        }

        $states = [];
        $status = '';
        try {
            $customer = $this->customerRepository->getById($customerId);
            $statesAttribute = $customer->getCustomAttribute(ExemptionStates::ATTRIBUTE_CODE);
            $raw = $statesAttribute?->getValue();
            $states = $this->exemptionStates->parse(
                is_array($raw) || is_string($raw) || $raw === null ? $raw : (string) $raw
            );
            $statusAttribute = $customer->getCustomAttribute(ExemptionStatus::ATTRIBUTE_CODE);
            $status = strtolower(trim((string) ($statusAttribute?->getValue() ?? '')));
        } catch (\Throwable $e) {
            $this->logger->error('Exemptax failed loading exemption states', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->customerExemptionCache[$customerId] = [
            'states' => $states,
            'status' => $status,
        ];

        return $this->customerExemptionCache[$customerId];
    }

    private function resolveRegionCode(Address $address): ?string
    {
        $code = trim((string) $address->getRegionCode());
        if ($code !== '') {
            return strtoupper($code);
        }

        $regionId = (int) $address->getRegionId();
        if ($regionId <= 0) {
            return null;
        }

        $region = $this->regionFactory->create()->load($regionId);
        $code = trim((string) $region->getCode());

        return $code !== '' ? strtoupper($code) : null;
    }
}
