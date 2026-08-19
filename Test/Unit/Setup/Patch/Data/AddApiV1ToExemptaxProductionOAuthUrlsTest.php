<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Setup\Patch\Data;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Setup\Patch\Data\AddApiV1ToExemptaxProductionOAuthUrls;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;
use PHPUnit\Framework\TestCase;

class AddApiV1ToExemptaxProductionOAuthUrlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(IntegrationServiceInterface::class) || !class_exists(Integration::class)) {
            $this->markTestSkipped('Magento Integration module is not available in this PHPUnit bootstrap.');
        }
    }

    public function test_rewrites_legacy_production_urls(): void
    {
        $existing = new class () {
            public function getId(): int
            {
                return 12;
            }

            public function getEndpoint(): string
            {
                return Config::LEGACY_OAUTH_CALLBACK_URL;
            }

            public function getIdentityLinkUrl(): string
            {
                return Config::LEGACY_IDENTITY_LINK_URL;
            }
        };

        $integrationService = $this->createMock(IntegrationServiceInterface::class);
        $integrationService->method('findByName')->willReturn($existing);
        $integrationService->expects($this->once())
            ->method('update')
            ->with([
                Integration::ID => 12,
                Integration::NAME => 'EXEMPTAX',
                Integration::ENDPOINT => Config::DEFAULT_OAUTH_CALLBACK_URL,
                Integration::IDENTITY_LINK_URL => Config::DEFAULT_IDENTITY_LINK_URL,
            ])
            ->willReturn($this->createMock(Integration::class));

        (new AddApiV1ToExemptaxProductionOAuthUrls($integrationService))->apply();
    }

    public function test_skips_dev_and_custom_urls(): void
    {
        $existing = new class () {
            public function getId(): int
            {
                return 12;
            }

            public function getEndpoint(): string
            {
                return 'https://a-dvlp-01.exemptax.com/api/v1/adobe_commerce/oauth/callback';
            }

            public function getIdentityLinkUrl(): string
            {
                return 'https://a-dvlp-01.exemptax.com/api/v1/adobe_commerce/app';
            }
        };

        $integrationService = $this->createMock(IntegrationServiceInterface::class);
        $integrationService->method('findByName')->willReturn($existing);
        $integrationService->expects($this->never())->method('update');

        (new AddApiV1ToExemptaxProductionOAuthUrls($integrationService))->apply();
    }
}
