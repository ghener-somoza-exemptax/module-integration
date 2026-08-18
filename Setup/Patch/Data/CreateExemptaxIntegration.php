<?php

declare(strict_types=1);

namespace Exemptax\Integration\Setup\Patch\Data;

use Exemptax\Integration\Model\Config;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;

/**
 * Creates System → Extensions → Integrations → EXEMPTAX on module install.
 * Pre-fills production Callback and Identity Link URLs; merchants on other
 * environments can replace them in Admin before Activate.
 */
class CreateExemptaxIntegration implements DataPatchInterface
{
    public const INTEGRATION_NAME = 'EXEMPTAX';

    public function __construct(
        private readonly IntegrationServiceInterface $integrationService
    ) {
    }

    public function apply(): self
    {
        $existing = $this->integrationService->findByName(self::INTEGRATION_NAME);
        if ($existing->getId()) {
            return $this;
        }

        $this->integrationService->create([
            Integration::NAME => self::INTEGRATION_NAME,
            Integration::EMAIL => 'support@exemptax.com',
            Integration::ENDPOINT => Config::DEFAULT_OAUTH_CALLBACK_URL,
            Integration::IDENTITY_LINK_URL => Config::DEFAULT_IDENTITY_LINK_URL,
            Integration::SETUP_TYPE => Integration::TYPE_MANUAL,
            Integration::STATUS => Integration::STATUS_INACTIVE,
            'resource' => [
                'Magento_Customer::customer',
                'Magento_Customer::manage',
                'Magento_Customer::group',
                'Magento_Directory::directory',
                'Exemptax_Integration::webhook_settings',
            ],
        ]);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
