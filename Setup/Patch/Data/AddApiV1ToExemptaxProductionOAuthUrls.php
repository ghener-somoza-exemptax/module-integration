<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Exemptax\Integration\Model\Config;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;

/**
 * Moves production Callback / Identity Link URLs onto /api/v1.
 * Leaves DEV, Herd, and any other custom URLs unchanged.
 */
class AddApiV1ToExemptaxProductionOAuthUrls implements DataPatchInterface
{
    public function __construct(
        private readonly IntegrationServiceInterface $integrationService
    ) {
    }

    public function apply(): self
    {
        $integration = $this->integrationService->findByName(CreateExemptaxIntegration::INTEGRATION_NAME);
        if (!$integration->getId()) {
            return $this;
        }

        $updates = [
            Integration::ID => (int) $integration->getId(),
            Integration::NAME => CreateExemptaxIntegration::INTEGRATION_NAME,
        ];
        $changed = false;

        if (trim((string) $integration->getEndpoint()) === Config::LEGACY_OAUTH_CALLBACK_URL) {
            $updates[Integration::ENDPOINT] = Config::DEFAULT_OAUTH_CALLBACK_URL;
            $changed = true;
        }

        if (trim((string) $integration->getIdentityLinkUrl()) === Config::LEGACY_IDENTITY_LINK_URL) {
            $updates[Integration::IDENTITY_LINK_URL] = Config::DEFAULT_IDENTITY_LINK_URL;
            $changed = true;
        }

        if ($changed) {
            $this->integrationService->update($updates);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [EnsureExemptaxIntegrationOAuthUrls::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
