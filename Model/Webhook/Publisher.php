<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Webhook;

use Exemptax\Integration\Model\Config;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Single entry point for customer webhooks with per-request dedupe.
 *
 * Magento's customer_save_after runs before DB commit; posting there races
 * Exemptax REST GETs against uncommitted rows. This publisher:
 * - Prefers an immediate post from customer_save_after_data_object (after
 *   CustomerRepository finishes, including addresses)
 * - Defers model-only customer_save_commit_after until after the request
 *   (or until data_object cancels/replaces it), so we never POST mid-save
 * - Posts customer/address/save|delete from address-only repository/model paths
 * - Still uses addCommitCallback when a DB transaction is open
 * - Skips outbound webhooks when the save is from Exemptax REST (X-Exemptax-Origin: push)
 */
class Publisher
{
    /** @var array<int, array{payload: array, website_id: ?int, scope: string, email: ?string}> */
    private array $pending = [];

    /** @var array<int, true> */
    private array $publishedCustomerIds = [];

    private bool $shutdownRegistered = false;

    public function __construct(
        private readonly Config $config,
        private readonly Client $webhookClient,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly CustomerResource $customerResource,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Queue a webhook for after the request (model commit_after path).
     * Superseded if publishCustomerSaveImmediate() runs for the same id.
     */
    public function publishCustomerSaveDeferred(Customer|CustomerInterface $customer, ?string $scope = null): bool
    {
        return $this->queueCustomerSave($customer, $scope, false);
    }

    /**
     * Post as soon as the DB is committed (repository data_object path).
     */
    public function publishCustomerSaveImmediate(Customer|CustomerInterface $customer, ?string $scope = null): bool
    {
        return $this->queueCustomerSave($customer, $scope, true);
    }

    public function publishCustomerDelete(Customer|CustomerInterface $customer): bool
    {
        $customerId = (int) $customer->getId();
        if ($customerId <= 0 || isset($this->publishedCustomerIds[$customerId])) {
            return $customerId > 0 && isset($this->publishedCustomerIds[$customerId]);
        }

        if ($this->isExemptaxOriginPush()) {
            $this->logger->info('Exemptax webhook skipped (Exemptax origin push)', [
                'customer_id' => $customerId,
                'scope' => 'customer/deleted',
            ]);

            return false;
        }

        $websiteId = $customer->getWebsiteId() ? (int) $customer->getWebsiteId() : null;
        if (!$this->config->canSendWebhooks($websiteId)) {
            return false;
        }

        $payload = $this->payloadBuilder->buildCustomerEvent('customer/deleted', $customerId);
        unset($this->pending[$customerId]);

        $this->runAfterCommit(function () use ($payload, $websiteId, $customerId): void {
            $this->postPayload($payload, $websiteId, $customerId, 'customer/deleted', null);
        });

        return true;
    }

    /**
     * Address-only save (AddressRepository / admin address form). Dedupes with
     * customer save webhooks in the same request.
     */
    public function publishCustomerAddressSave(Address $address): bool
    {
        $customerId = (int) $address->getCustomerId();
        if ($customerId <= 0) {
            return false;
        }

        $customer = $address->getCustomer();
        $websiteId = ($customer && $customer->getWebsiteId()) ? (int) $customer->getWebsiteId() : null;
        $email = ($customer && method_exists($customer, 'getEmail')) ? $customer->getEmail() : null;

        return $this->queueCustomerIdEvent(
            $customerId,
            'customer/address/save',
            $websiteId,
            $email,
            true
        );
    }

    /**
     * Address delete: call from delete_before while customer_id is still on the model.
     * Resource delete clears address data before delete_commit_after.
     */
    public function publishCustomerAddressDelete(Address $address): bool
    {
        $customerId = (int) $address->getCustomerId();
        if ($customerId <= 0) {
            return false;
        }

        $customer = $address->getCustomer();
        $websiteId = ($customer && $customer->getWebsiteId()) ? (int) $customer->getWebsiteId() : null;
        $email = ($customer && method_exists($customer, 'getEmail')) ? $customer->getEmail() : null;

        return $this->queueCustomerIdEvent(
            $customerId,
            'customer/address/delete',
            $websiteId,
            $email,
            true
        );
    }

    /**
     * @param bool $immediate Post after commit (true) or shutdown flush (false)
     */
    private function queueCustomerIdEvent(
        int $customerId,
        string $scope,
        ?int $websiteId,
        ?string $email,
        bool $immediate
    ): bool {
        if ($customerId <= 0 || isset($this->publishedCustomerIds[$customerId])) {
            return $customerId > 0 && isset($this->publishedCustomerIds[$customerId]);
        }

        if ($this->isExemptaxOriginPush()) {
            $this->logger->info('Exemptax webhook skipped (Exemptax origin push)', [
                'customer_id' => $customerId,
                'scope' => $scope,
            ]);

            return false;
        }

        if (!$this->config->canSendWebhooks($websiteId)) {
            return false;
        }

        $entry = [
            'payload' => $this->payloadBuilder->buildCustomerEvent($scope, $customerId),
            'website_id' => $websiteId,
            'scope' => $scope,
            'email' => $email,
        ];

        if ($immediate) {
            unset($this->pending[$customerId]);
            $this->runAfterCommit(function () use ($entry, $customerId): void {
                $this->postPayload(
                    $entry['payload'],
                    $entry['website_id'],
                    $customerId,
                    $entry['scope'],
                    $entry['email']
                );
            });

            return true;
        }

        $this->pending[$customerId] = $entry;
        $this->registerShutdownFlush();

        return true;
    }

    private function queueCustomerSave(
        Customer|CustomerInterface $customer,
        ?string $scope,
        bool $immediate
    ): bool {
        $customerId = (int) $customer->getId();
        if ($customerId <= 0 || isset($this->publishedCustomerIds[$customerId])) {
            return $customerId > 0 && isset($this->publishedCustomerIds[$customerId]);
        }

        if ($this->isExemptaxOriginPush()) {
            if ($scope === null) {
                $scope = $this->payloadBuilder->resolveCustomerScope($customer);
            }
            $this->logger->info('Exemptax webhook skipped (Exemptax origin push)', [
                'customer_id' => $customerId,
                'scope' => $scope,
            ]);

            return false;
        }

        $websiteId = $customer->getWebsiteId() ? (int) $customer->getWebsiteId() : null;
        if (!$this->config->canSendWebhooks($websiteId)) {
            return false;
        }

        if ($scope === null) {
            $scope = $this->payloadBuilder->resolveCustomerScope($customer);
        }

        $entry = [
            'payload' => $this->payloadBuilder->buildCustomerEvent($scope, $customerId),
            'website_id' => $websiteId,
            'scope' => $scope,
            'email' => method_exists($customer, 'getEmail') ? $customer->getEmail() : null,
        ];

        if ($immediate) {
            unset($this->pending[$customerId]);
            $this->runAfterCommit(function () use ($entry, $customerId): void {
                $this->postPayload(
                    $entry['payload'],
                    $entry['website_id'],
                    $customerId,
                    $entry['scope'],
                    $entry['email']
                );
            });

            return true;
        }

        $this->pending[$customerId] = $entry;
        $this->registerShutdownFlush();

        return true;
    }

    private function runAfterCommit(callable $callback): void
    {
        $connection = $this->customerResource->getConnection();
        if ($connection->getTransactionLevel() > 0) {
            $this->customerResource->addCommitCallback($callback);
            return;
        }

        $callback();
    }

    /**
     * True when this Magento request was initiated by Exemptax BE (exemption / customer push).
     */
    private function isExemptaxOriginPush(): bool
    {
        $origin = trim((string) $this->request->getHeader(Config::HEADER_ORIGIN));

        return strcasecmp($origin, Config::ORIGIN_PUSH) === 0;
    }

    private function registerShutdownFlush(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;
        register_shutdown_function(function (): void {
            $this->flushPending();
        });
    }

    public function flushPending(): void
    {
        if ($this->pending === []) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $customerId => $entry) {
            if (isset($this->publishedCustomerIds[$customerId])) {
                continue;
            }
            $this->postPayload(
                $entry['payload'],
                $entry['website_id'],
                $customerId,
                $entry['scope'],
                $entry['email']
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postPayload(
        array $payload,
        ?int $websiteId,
        int $customerId,
        string $scope,
        ?string $email
    ): void {
        if (isset($this->publishedCustomerIds[$customerId])) {
            return;
        }

        try {
            $ok = $this->webhookClient->post($payload, $websiteId);
            if ($ok) {
                $this->publishedCustomerIds[$customerId] = true;
                $this->logger->info('Exemptax webhook sent', [
                    'customer_id' => $customerId,
                    'scope' => $scope,
                    'email' => $email,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Exemptax customer webhook failed', [
                'customer_id' => $customerId,
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
