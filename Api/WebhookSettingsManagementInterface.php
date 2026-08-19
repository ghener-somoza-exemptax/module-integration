<?php

declare(strict_types=1);

namespace Exemptax\Integration\Api;

/**
 * Magento REST API for Exemptax inbound webhook configuration.
 */
interface WebhookSettingsManagementInterface
{
    /**
     * Save Exemptax webhook settings (URL, ex-key, enable flag).
     *
     * @param string $webhookUrl Exemptax webhook endpoint
     * @param string $exKey Laravel encrypt(company_id) value for ex-key + HMAC
     * @param bool $enabled Enable Magento → Exemptax webhooks
     * @param bool|null $verifySsl Optional SSL verify flag for outbound Magento posts
     * @param bool|null $applyStateExemptions Optional native Magento state-exemption toggle
     * @param string|null $ecommerceDropUrl Optional EXEMPTAX FE ecommerce-drop base URL
     * @param bool|null $ecommerceDropEnabled Optional storefront certificate link toggle
     * @param string|null $taxEngine Optional magento|taxjar company setting
     * @param int|null $taxExemptFlag Optional 0–3 exemption automation
     * @param string|null $acCustomerGroups Optional JSON/CSV of Magento group ids
     * @param int|null $syncCustomerTags Optional 0/1
     * @param string|null $lastSyncAt Optional ISO timestamp from EXEMPTAX
     * @param bool|null $settingsLocked Optional sync-lock flag
     * @param string|null $settingsUrl Optional Laravel HMAC settings API URL
     * @param bool|null $grandfatheredEntireExemption Optional all-US vs certificate-state exemption
     * @return mixed[]
     */
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
        ?string $settingsUrl = null,
        ?bool $grandfatheredEntireExemption = null
    ): array;

    /**
     * Read current Exemptax webhook settings.
     *
     * @return mixed[]
     */
    public function get(): array;
}
