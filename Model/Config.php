<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_PATH_ENABLED = 'exemptax_integration/general/enabled';
    public const XML_PATH_WEBHOOK_URL = 'exemptax_integration/general/webhook_url';
    public const XML_PATH_SETTINGS_URL = 'exemptax_integration/general/settings_url';
    public const XML_PATH_EX_KEY = 'exemptax_integration/general/ex_key';
    public const XML_PATH_VERIFY_SSL = 'exemptax_integration/general/verify_ssl';
    public const XML_PATH_STATE_EXEMPTION = 'exemptax_integration/general/apply_state_exemptions';
    public const XML_PATH_ECOMMERCE_DROP_ENABLED = 'exemptax_integration/general/ecommerce_drop_enabled';
    public const XML_PATH_ECOMMERCE_DROP_URL = 'exemptax_integration/general/ecommerce_drop_url';
    public const XML_PATH_CERTIFICATES_PAGE_IDENTIFIER = 'exemptax_integration/general/certificates_page_identifier';
    public const XML_PATH_TAX_ENGINE = 'exemptax_integration/general/tax_engine';
    /** TaxJar Sales Tax "Enabled for Checkout" (`tax/taxjar/enabled`). */
    public const XML_PATH_TAXJAR_CHECKOUT_ENABLED = 'tax/taxjar/enabled';
    public const XML_PATH_TAX_EXEMPT_FLAG = 'exemptax_integration/general/tax_exempt_flag';
    public const XML_PATH_AC_CUSTOMER_GROUPS = 'exemptax_integration/general/ac_customer_groups';
    public const XML_PATH_SYNC_CUSTOMER_TAGS = 'exemptax_integration/general/sync_customer_tags';
    public const XML_PATH_GRANDFATHERED_ENTIRE_EXEMPTION = 'exemptax_integration/general/grandfathered_entire_exemption';
    public const XML_PATH_LAST_SYNC_AT = 'exemptax_integration/general/last_sync_at';
    public const XML_PATH_SETTINGS_LOCKED = 'exemptax_integration/general/settings_locked';

    /** Fallback when the merchant blanks out the storefront page URL key. */
    public const DEFAULT_CERTIFICATES_PAGE_IDENTIFIER = 'tax-exempt-certificates';

    /**
     * Production OAuth URLs pre-filled on the Magento Integration record.
     * Same /api/v1 gateway prefix as DEV (a-dvlp-01) and Magento webhooks.
     */
    public const DEFAULT_OAUTH_CALLBACK_URL = 'https://app.exemptax.com/api/v1/adobe_commerce/oauth/callback';

    public const DEFAULT_IDENTITY_LINK_URL = 'https://app.exemptax.com/api/v1/adobe_commerce/app';

    /** Pre-1.0.6 production URLs (no gateway prefix). Migrated by data patch. */
    public const LEGACY_OAUTH_CALLBACK_URL = 'https://app.exemptax.com/adobe_commerce/oauth/callback';

    public const LEGACY_IDENTITY_LINK_URL = 'https://app.exemptax.com/adobe_commerce/app';

    /** Header Exemptax BE sends on Magento REST writes so webhooks are not echoed. */
    public const HEADER_ORIGIN = 'X-Exemptax-Origin';

    public const ORIGIN_PUSH = 'push';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function isStateExemptionEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_STATE_EXEMPTION,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function isEcommerceDropEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ECOMMERCE_DROP_ENABLED,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function getEcommerceDropUrl(?int $websiteId = null): string
    {
        return rtrim(trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_ECOMMERCE_DROP_URL,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        )), '/');
    }

    /**
     * URL key of the storefront CMS page that embeds the ecommerce drop.
     * Merchants may rename it in Magento admin, so this stays the single source
     * shared by the data patch and the footer link.
     */
    public function getCertificatesPageIdentifier(?int $websiteId = null): string
    {
        $identifier = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_CERTIFICATES_PAGE_IDENTIFIER,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ), " \t\n\r\0\x0B/");

        return $identifier !== '' ? $identifier : self::DEFAULT_CERTIFICATES_PAGE_IDENTIFIER;
    }

    public function getWebhookUrl(?int $websiteId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_WEBHOOK_URL,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ));
    }

    /**
     * Laravel HMAC settings API (GET/POST), not API Gateway /event.
     * Explicit config wins; otherwise derive from a non-Gateway webhook URL.
     */
    public function getSettingsUrl(?int $websiteId = null): string
    {
        $explicit = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS_URL,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ));
        if ($explicit !== '') {
            return $explicit;
        }

        $webhook = $this->getWebhookUrl($websiteId);
        if (
            $webhook !== ''
            && str_contains($webhook, '/wbhk/adbcmmrc/event')
            && !str_contains($webhook, 'execute-api')
        ) {
            return (string) preg_replace('#/wbhk/adbcmmrc/event$#', '/wbhk/adbcmmrc/settings', $webhook);
        }

        return '';
    }

    /**
     * Same HMAC settings API as Admin save; Magento Curl POSTs here on OAuth integration delete.
     */
    public function getDisconnectUrl(?int $websiteId = null): string
    {
        $settings = rtrim($this->getSettingsUrl($websiteId), '/');
        if ($settings === '') {
            return '';
        }

        return $settings . '/disconnect';
    }

    public function getExKey(?int $websiteId = null): string
    {
        // Must be the exact Laravel encrypt(company_id) string sent as the ex-key header.
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_EX_KEY,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ));
    }

    public function shouldVerifySsl(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_VERIFY_SSL,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function canSendWebhooks(?int $websiteId = null): bool
    {
        return $this->isEnabled($websiteId)
            && $this->getWebhookUrl($websiteId) !== ''
            && $this->getExKey($websiteId) !== '';
    }

    public function getTaxEngine(?int $websiteId = null): string
    {
        $engine = strtolower(trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_TAX_ENGINE,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        )));

        return in_array($engine, ['magento', 'taxjar'], true) ? $engine : 'magento';
    }

    public function getTaxExemptFlag(?int $websiteId = null): int
    {
        $flag = (int) $this->scopeConfig->getValue(
            self::XML_PATH_TAX_EXEMPT_FLAG,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
        if ($flag === 3) {
            return 2;
        }

        return in_array($flag, [0, 1, 2], true) ? $flag : 0;
    }

    /**
     * @return list<string>
     */
    public function getAcCustomerGroups(?int $websiteId = null): array
    {
        $raw = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_AC_CUSTOMER_GROUPS,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded), static fn (string $id): bool => $id !== ''));
        }

        return array_values(array_filter(array_map(
            static fn (string $id): string => trim($id),
            explode(',', $raw)
        ), static fn (string $id): bool => $id !== ''));
    }

    public function getSyncCustomerTags(?int $websiteId = null): int
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SYNC_CUSTOMER_TAGS,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ) ? 1 : 0;
    }

    public function isGrandfatheredEntireExemptionEnabled(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GRANDFATHERED_ENTIRE_EXEMPTION,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    public function getGrandfatheredEntireExemption(?int $websiteId = null): int
    {
        return $this->isGrandfatheredEntireExemptionEnabled($websiteId) ? 1 : 0;
    }

    public function getLastSyncAt(?int $websiteId = null): ?string
    {
        $value = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_LAST_SYNC_AT,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        ));

        return $value !== '' ? $value : null;
    }

    public function isSettingsLocked(?int $websiteId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SETTINGS_LOCKED,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    /**
     * Company settings load/save require the Laravel settings URL + ex-key.
     */
    public function canManageCompanySettings(?int $websiteId = null): bool
    {
        return $this->getSettingsUrl($websiteId) !== ''
            && $this->getExKey($websiteId) !== '';
    }

    /**
     * Local mirror of EXEMPTAX company settings (pushed via webhook-settings REST).
     *
     * @return array<string, mixed>
     */
    public function getCompanySettings(?int $websiteId = null): array
    {
        $locked = $this->isSettingsLocked($websiteId);
        $lastSync = $this->getLastSyncAt($websiteId);

        return [
            'tax_engine' => $this->getTaxEngine($websiteId),
            'tax_exempt_flag' => $this->getTaxExemptFlag($websiteId),
            'ac_customer_groups' => $this->getAcCustomerGroups($websiteId),
            'sync_customer_tags' => $this->getSyncCustomerTags($websiteId),
            'grandfathered_entire_exemption' => $this->getGrandfatheredEntireExemption($websiteId),
            'last_sync_at' => $locked ? null : $lastSync,
            'locked' => $locked,
            'needs_reauth' => false,
        ];
    }
}
