<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\TaxJar;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * Pushes Magento tj_* customer exemption attrs to TaxJar's customer API.
 *
 * TaxJar's own observer only runs on adminhtml customer prepare-save, so REST
 * updates from Exemptax never reach SmartCalcs without this bridge.
 */
class CustomerExemptionSynchronizer
{
    private const MODULE_TAXJAR = 'Taxjar_SalesTax';

    private const XML_PATH_CONNECTED = 'tax/taxjar/connected';

    private const XML_PATH_APIKEY = 'tax/taxjar/apikey';

    private bool $syncing = false;

    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly RegionFactory $regionFactory,
        private readonly ObjectManagerInterface $objectManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sync(CustomerInterface $customer): void
    {
        if ($this->syncing) {
            return;
        }

        if (!$customer->getId()) {
            return;
        }

        if (!$this->isTaxJarReady()) {
            return;
        }

        // Admin saves are already handled by Taxjar_SalesTax adminhtml observer.
        // This bridge is for REST / webapi / non-admin repository saves (Exemptax).
        try {
            $area = $this->objectManager->get(\Magento\Framework\App\State::class)->getAreaCode();
            if ($area === \Magento\Framework\App\Area::AREA_ADMINHTML) {
                return;
            }
        } catch (\Throwable $e) {
            // Area may be unset in some CLI contexts; continue and sync.
        }

        $exemptionTypeAttr = $customer->getCustomAttribute('tj_exemption_type');
        $regionsAttr = $customer->getCustomAttribute('tj_regions');

        // Only sync when TaxJar exemption attrs are present on the payload/customer.
        if ($exemptionTypeAttr === null && $regionsAttr === null) {
            return;
        }

        $data = $this->buildPayload($customer, $exemptionTypeAttr, $regionsAttr);
        $lastSync = (string) ($customer->getCustomAttribute('tj_last_sync')?->getValue() ?? '');

        $this->syncing = true;
        try {
            $response = $this->pushToTaxJar($lastSync, $data);
            if ($response !== null) {
                $this->logger->info('Exemptax synced Magento customer exemption to TaxJar', [
                    'customer_id' => (int) $customer->getId(),
                    'exemption_type' => $data['exemption_type'],
                    'exempt_regions' => $data['exempt_regions'],
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Exemptax failed to sync Magento customer exemption to TaxJar', [
                'customer_id' => (int) $customer->getId(),
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->syncing = false;
        }
    }

    private function isTaxJarReady(): bool
    {
        if (!$this->moduleManager->isEnabled(self::MODULE_TAXJAR)) {
            return false;
        }

        if (!class_exists(\Taxjar\SalesTax\Model\ClientFactory::class)) {
            return false;
        }

        $connected = $this->scopeConfig->isSetFlag(
            self::XML_PATH_CONNECTED,
            ScopeInterface::SCOPE_STORE
        );
        $apiKey = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_APIKEY,
            ScopeInterface::SCOPE_STORE
        ));

        return $connected && $apiKey !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        CustomerInterface $customer,
        $exemptionTypeAttr,
        $regionsAttr
    ): array {
        $exemptionType = $exemptionTypeAttr?->getValue();
        $regionsValue = $regionsAttr?->getValue();

        $data = [
            'customer_id' => (string) $customer->getId(),
            'exemption_type' => $exemptionType !== null && $exemptionType !== ''
                ? (string) $exemptionType
                : 'non_exempt',
            'name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'exempt_regions' => $this->mapRegions($regionsValue),
            'country' => null,
            'state' => null,
            'zip' => null,
            'city' => null,
            'street' => null,
        ];

        $address = null;
        $addresses = $customer->getAddresses() ?: [];
        if ($addresses !== []) {
            $address = reset($addresses);
        }

        try {
            $shippingId = $customer->getDefaultShipping();
            if (!empty($shippingId)) {
                $address = $this->addressRepository->getById((int) $shippingId);
            }
        } catch (\Throwable $e) {
            // keep fallback address
        }

        if ($address) {
            $data['country'] = $address->getCountryId();
            $data['zip'] = $address->getPostcode();
            $data['city'] = $address->getCity();
            $street = $address->getStreet();
            $data['street'] = is_array($street) ? implode(', ', $street) : (string) $street;

            if (method_exists($address, 'getRegionCode')) {
                $data['state'] = $address->getRegionCode();
            } else {
                $region = $address->getRegion();
                if (is_object($region) && method_exists($region, 'getRegionCode')) {
                    $data['state'] = $region->getRegionCode();
                }
            }
        }

        return $data;
    }

    /**
     * @param mixed $regions Magento tj_regions CSV of directory region entity IDs
     * @return list<array{country: string, state: string}>
     */
    private function mapRegions(mixed $regions): array
    {
        if ($regions === null || $regions === '') {
            return [];
        }

        if (is_array($regions)) {
            $regions = implode(',', $regions);
        }

        $out = [];
        $regionModel = $this->regionFactory->create();
        foreach (explode(',', (string) $regions) as $regionId) {
            $regionId = trim($regionId);
            if ($regionId === '') {
                continue;
            }
            $regionModel->load($regionId);
            $code = (string) $regionModel->getCode();
            if ($code !== '') {
                $out[] = [
                    'country' => 'US',
                    'state' => $code,
                ];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function pushToTaxJar(string $lastSync, array $data): ?array
    {
        /** @var \Taxjar\SalesTax\Model\ClientFactory $clientFactory */
        $clientFactory = $this->objectManager->get(\Taxjar\SalesTax\Model\ClientFactory::class);
        $client = $clientFactory->create();
        $client->showResponseErrors(true);

        $customerId = $data['customer_id'];
        $response = null;

        if ($lastSync === '') {
            try {
                $response = $client->postResource('customers', $data);
            } catch (\Throwable $e) {
                $status = $this->extractStatus($e->getMessage());
                if ($status === 422) {
                    $response = $client->putResource('customers', $customerId, $data);
                } else {
                    throw $e;
                }
            }
        } else {
            try {
                $response = $client->putResource('customers', $customerId, $data);
            } catch (\Throwable $e) {
                $status = $this->extractStatus($e->getMessage());
                if ($status === 404) {
                    $response = $client->postResource('customers', $data);
                } else {
                    throw $e;
                }
            }
        }

        return is_array($response) ? $response : null;
    }

    private function extractStatus(string $message): ?int
    {
        $decoded = json_decode($message);
        if (is_object($decoded) && isset($decoded->status)) {
            return (int) $decoded->status;
        }

        return null;
    }
}
