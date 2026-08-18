<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Exemptax\Integration\Model\Config;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;

/**
 * Backfills production OAuth URLs on integrations created before defaults existed.
 * Only writes fields that are still empty so dev/staging URLs are not overwritten.
 */
class EnsureExemptaxIntegrationOAuthUrls implements DataPatchInterface
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

        if (trim((string) $integration->getEndpoint()) === '') {
            $updates[Integration::ENDPOINT] = Config::DEFAULT_OAUTH_CALLBACK_URL;
            $changed = true;
        }

        if (trim((string) $integration->getIdentityLinkUrl()) === '') {
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
        return [CreateExemptaxIntegration::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
