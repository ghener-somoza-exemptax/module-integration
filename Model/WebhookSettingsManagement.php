<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model;

use Exemptax\Integration\Api\WebhookSettingsManagementInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;

class WebhookSettingsManagement implements WebhookSettingsManagementInterface
{
    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly Config $config,
        private readonly TypeListInterface $cacheTypeList,
        private readonly ReinitableConfigInterface $reinitableConfig
    ) {
    }

    public function save(
        string $webhookUrl,
        string $exKey,
        bool $enabled = true,
        ?bool $verifySsl = null,
        ?bool $applyStateExemptions = null,
        ?string $ecommerceDropUrl = null,
        ?bool $ecommerceDropEnabled = null,
        ?string $taxEngine = null,
        ?int $taxExemptFlag = null,
        ?string $acCustomerGroups = null,
        ?int $syncCustomerTags = null,
        ?string $lastSyncAt = null,
        ?bool $settingsLocked = null,
        ?string $settingsUrl = null
    ): array {
        $webhookUrl = trim($webhookUrl);
        $exKey = trim($exKey);

        if ($webhookUrl === '' || $exKey === '') {
            throw new LocalizedException(__('webhookUrl and exKey are required.'));
        }

        if (!preg_match('#^https?://#i', $webhookUrl)) {
            throw new LocalizedException(__('webhookUrl must be an http(s) URL.'));
        }

        $this->configWriter->save(Config::XML_PATH_WEBHOOK_URL, $webhookUrl);
        $this->configWriter->save(Config::XML_PATH_EX_KEY, $exKey);
        $this->configWriter->save(Config::XML_PATH_ENABLED, $enabled ? '1' : '0');

        if ($verifySsl !== null) {
            $this->configWriter->save(Config::XML_PATH_VERIFY_SSL, $verifySsl ? '1' : '0');
        }

        if ($applyStateExemptions !== null) {
            $this->configWriter->save(
                Config::XML_PATH_STATE_EXEMPTION,
                $applyStateExemptions ? '1' : '0'
            );
        }

        if ($ecommerceDropUrl !== null) {
            $ecommerceDropUrl = rtrim(trim($ecommerceDropUrl), '/');
            if ($ecommerceDropUrl !== '' && !preg_match('#^https?://#i', $ecommerceDropUrl)) {
                throw new LocalizedException(__('ecommerceDropUrl must be an http(s) URL.'));
            }
            $this->configWriter->save(Config::XML_PATH_ECOMMERCE_DROP_URL, $ecommerceDropUrl);
        }

        if ($ecommerceDropEnabled !== null) {
            $this->configWriter->save(
                Config::XML_PATH_ECOMMERCE_DROP_ENABLED,
                $ecommerceDropEnabled ? '1' : '0'
            );
        }

        if ($taxEngine !== null) {
            $engine = strtolower(trim($taxEngine));
            if (!in_array($engine, ['magento', 'taxjar'], true)) {
                throw new LocalizedException(__('taxEngine must be magento or taxjar.'));
            }
            $this->configWriter->save(Config::XML_PATH_TAX_ENGINE, $engine);
        }

        if ($taxExemptFlag !== null) {
            if (!in_array($taxExemptFlag, [0, 1, 2, 3], true)) {
                throw new LocalizedException(__('taxExemptFlag must be 0, 1, 2, or 3.'));
            }
            $this->configWriter->save(Config::XML_PATH_TAX_EXEMPT_FLAG, (string) $taxExemptFlag);
        }

        if ($acCustomerGroups !== null) {
            $this->configWriter->save(
                Config::XML_PATH_AC_CUSTOMER_GROUPS,
                $this->normalizeGroupList($acCustomerGroups)
            );
        }

        if ($syncCustomerTags !== null) {
            $this->configWriter->save(
                Config::XML_PATH_SYNC_CUSTOMER_TAGS,
                $syncCustomerTags ? '1' : '0'
            );
        }

        if ($lastSyncAt !== null) {
            $this->configWriter->save(Config::XML_PATH_LAST_SYNC_AT, trim($lastSyncAt));
        }

        if ($settingsLocked !== null) {
            $this->configWriter->save(
                Config::XML_PATH_SETTINGS_LOCKED,
                $settingsLocked ? '1' : '0'
            );
        }

        if ($settingsUrl !== null) {
            $settingsUrl = trim($settingsUrl);
            if ($settingsUrl !== '' && !preg_match('#^https?://#i', $settingsUrl)) {
                throw new LocalizedException(__('settingsUrl must be an http(s) URL.'));
            }
            $this->configWriter->save(Config::XML_PATH_SETTINGS_URL, $settingsUrl);
        }

        $this->cacheTypeList->cleanType('config');
        $this->reinitableConfig->reinit();

        return $this->get();
    }

    public function get(): array
    {
        return array_merge(
            [
                'webhook_url' => $this->config->getWebhookUrl(),
                'settings_url' => $this->config->getSettingsUrl(),
                'ex_key' => $this->config->getExKey(),
                'enabled' => $this->config->isEnabled(),
                'verify_ssl' => $this->config->shouldVerifySsl(),
                'apply_state_exemptions' => $this->config->isStateExemptionEnabled(),
                'ecommerce_drop_url' => $this->config->getEcommerceDropUrl(),
                'ecommerce_drop_enabled' => $this->config->isEcommerceDropEnabled(),
            ],
            $this->config->getCompanySettings()
        );
    }

    private function normalizeGroupList(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '[]';
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = preg_split('/\s*,\s*/', $raw) ?: [];
        }

        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            $decoded
        ), static fn (string $id): bool => $id !== '')));

        return json_encode($ids, JSON_THROW_ON_ERROR);
    }

    /**
     * Mirror a live EXEMPTAX settings payload into Magento config (cache + UI fallback).
     *
     * @param array<string, mixed> $data
     */
    public function persistCompanySettings(array $data): void
    {
        if (isset($data['tax_engine'])) {
            $engine = strtolower(trim((string) $data['tax_engine']));
            if (in_array($engine, ['magento', 'taxjar'], true)) {
                $this->configWriter->save(Config::XML_PATH_TAX_ENGINE, $engine);
            }
        }
        if (array_key_exists('tax_exempt_flag', $data)) {
            $flag = (int) $data['tax_exempt_flag'];
            if (in_array($flag, [0, 1, 2, 3], true)) {
                $this->configWriter->save(Config::XML_PATH_TAX_EXEMPT_FLAG, (string) $flag);
            }
        }
        if (array_key_exists('ac_customer_groups', $data)) {
            $groups = $data['ac_customer_groups'];
            $raw = is_array($groups)
                ? json_encode(array_values(array_map('strval', $groups)))
                : (string) $groups;
            $this->configWriter->save(Config::XML_PATH_AC_CUSTOMER_GROUPS, $this->normalizeGroupList($raw));
        }
        if (array_key_exists('sync_customer_tags', $data)) {
            $this->configWriter->save(
                Config::XML_PATH_SYNC_CUSTOMER_TAGS,
                ((int) $data['sync_customer_tags']) ? '1' : '0'
            );
        }
        if (array_key_exists('last_sync_at', $data)) {
            $this->configWriter->save(Config::XML_PATH_LAST_SYNC_AT, (string) ($data['last_sync_at'] ?? ''));
        }
        if (array_key_exists('locked', $data)) {
            $this->configWriter->save(
                Config::XML_PATH_SETTINGS_LOCKED,
                !empty($data['locked']) ? '1' : '0'
            );
        }

        $this->cacheTypeList->cleanType('config');
        $this->reinitableConfig->reinit();
    }
}
