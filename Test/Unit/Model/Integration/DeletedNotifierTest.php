<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Model\Integration;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Integration\DeletedNotifier;
use Exemptax\Integration\Model\Settings\Client as SettingsClient;
use Exemptax\Integration\Model\WebhookSettingsManagement;
use Magento\Framework\App\State as AppState;
use Magento\Integration\Model\Integration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DeletedNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(Integration::class) || !class_exists(AppState::class)) {
            $this->markTestSkipped('Magento framework is not available in this PHPUnit bootstrap.');
        }
    }

    public function test_notifies_exemptax_settings_api_then_disables_local_config(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('canManageCompanySettings')->willReturn(true);

        $settingsClient = $this->createMock(SettingsClient::class);
        $settingsClient->expects($this->once())
            ->method('deleteSettings')
            ->willReturn(['disconnected' => true]);

        $webhookSettings = $this->createMock(WebhookSettingsManagement::class);
        $webhookSettings->expects($this->once())->method('disableLocalIntegration');

        $appState = $this->createMock(AppState::class);
        $appState->method('getMode')->willReturn(AppState::MODE_PRODUCTION);

        $notifier = new DeletedNotifier(
            $config,
            $settingsClient,
            $webhookSettings,
            $appState,
            $this->createMock(LoggerInterface::class)
        );

        $notifier->notifyBeforeDelete([Integration::NAME => 'EXEMPTAX']);
    }

    public function test_ignores_other_integrations(): void
    {
        $settingsClient = $this->createMock(SettingsClient::class);
        $settingsClient->expects($this->never())->method('deleteSettings');

        $webhookSettings = $this->createMock(WebhookSettingsManagement::class);
        $webhookSettings->expects($this->never())->method('disableLocalIntegration');

        $notifier = new DeletedNotifier(
            $this->createMock(Config::class),
            $settingsClient,
            $webhookSettings,
            $this->createMock(AppState::class),
            $this->createMock(LoggerInterface::class)
        );

        $notifier->notifyBeforeDelete([Integration::NAME => 'Some Other App']);
    }

    public function test_skips_exemptax_call_in_setup_mode(): void
    {
        $settingsClient = $this->createMock(SettingsClient::class);
        $settingsClient->expects($this->never())->method('deleteSettings');

        $webhookSettings = $this->createMock(WebhookSettingsManagement::class);
        $webhookSettings->expects($this->never())->method('disableLocalIntegration');

        $appState = $this->createMock(AppState::class);
        $appState->method('getMode')->willReturn(AppState::MODE_SETUP);

        $notifier = new DeletedNotifier(
            $this->createMock(Config::class),
            $settingsClient,
            $webhookSettings,
            $appState,
            $this->createMock(LoggerInterface::class)
        );

        $notifier->notifyBeforeDelete([Integration::NAME => 'EXEMPTAX']);
    }

    public function test_still_disables_local_config_when_settings_api_fails(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('canManageCompanySettings')->willReturn(true);

        $settingsClient = $this->createMock(SettingsClient::class);
        $settingsClient->method('deleteSettings')->willThrowException(new \RuntimeException('down'));

        $webhookSettings = $this->createMock(WebhookSettingsManagement::class);
        $webhookSettings->expects($this->once())->method('disableLocalIntegration');

        $appState = $this->createMock(AppState::class);
        $appState->method('getMode')->willReturn(AppState::MODE_PRODUCTION);

        $notifier = new DeletedNotifier(
            $config,
            $settingsClient,
            $webhookSettings,
            $appState,
            $this->createMock(LoggerInterface::class)
        );

        $notifier->notifyBeforeDelete([Integration::NAME => 'EXEMPTAX']);
    }
}
