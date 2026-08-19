<?php

declare(strict_types=1);

namespace Exemptax\Integration\Model\Integration;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Settings\Client as SettingsClient;
use Exemptax\Integration\Model\WebhookSettingsManagement;
use Exemptax\Integration\Setup\Patch\Data\CreateExemptaxIntegration;
use Magento\Framework\App\State as AppState;
use Magento\Integration\Model\Integration;
use Psr\Log\LoggerInterface;

/**
 * Notify EXEMPTAX when the merchant deletes the EXEMPTAX OAuth integration in Magento Admin.
 * Uses the same HMAC settings API as Magento Admin save — not the customer webhook.
 */
class DeletedNotifier
{
    public function __construct(
        private readonly Config $config,
        private readonly SettingsClient $settingsClient,
        private readonly WebhookSettingsManagement $webhookSettingsManagement,
        private readonly AppState $appState,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $integrationData IntegrationService::get() row
     */
    public function notifyBeforeDelete(array $integrationData): void
    {
        if (($integrationData[Integration::NAME] ?? '') !== CreateExemptaxIntegration::INTEGRATION_NAME) {
            return;
        }

        if (!$this->shouldNotifyExemptax()) {
            $this->logger->info('Exemptax integration delete skipped (setup mode)');

            return;
        }

        if ($this->config->canManageCompanySettings()) {
            try {
                $this->settingsClient->deleteSettings();
                $this->logger->info('Exemptax disconnected via Magento settings API');
            } catch (\Throwable $e) {
                $this->logger->warning('Exemptax disconnect on integration delete failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $this->logger->info('Exemptax integration delete skipped (settings URL/ex-key not configured)');
        }

        $this->webhookSettingsManagement->disableLocalIntegration();
    }

    private function shouldNotifyExemptax(): bool
    {
        try {
            return $this->appState->getMode() !== AppState::MODE_SETUP;
        } catch (\Throwable) {
            return true;
        }
    }
}
