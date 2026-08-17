<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Setup\Patch\Data;

use Exemptax\Integration\Setup\Patch\Data\CreateExemptaxIntegration;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;
use PHPUnit\Framework\TestCase;

class CreateExemptaxIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(IntegrationServiceInterface::class) || !class_exists(Integration::class)) {
            $this->markTestSkipped('Magento Integration module is not available in this PHPUnit bootstrap.');
        }
    }

    public function test_creates_inactive_manual_integration_when_missing(): void
    {
        $existing = $this->createMock(Integration::class);
        $existing->method('getId')->willReturn(null);

        $integrationService = $this->createMock(IntegrationServiceInterface::class);
        $integrationService->expects($this->once())
            ->method('findByName')
            ->with('EXEMPTAX')
            ->willReturn($existing);
        $integrationService->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                return $data[Integration::NAME] === 'EXEMPTAX'
                    && $data[Integration::EMAIL] === 'support@exemptax.com'
                    && (int) $data[Integration::SETUP_TYPE] === Integration::TYPE_MANUAL
                    && (int) $data[Integration::STATUS] === Integration::STATUS_INACTIVE
                    && in_array('Magento_Customer::customer', $data['resource'], true);
            }))
            ->willReturn($this->createMock(Integration::class));

        $patch = new CreateExemptaxIntegration($integrationService);
        $this->assertInstanceOf(CreateExemptaxIntegration::class, $patch->apply());
    }

    public function test_skips_create_when_integration_already_exists(): void
    {
        $existing = $this->createMock(Integration::class);
        $existing->method('getId')->willReturn(12);

        $integrationService = $this->createMock(IntegrationServiceInterface::class);
        $integrationService->method('findByName')->willReturn($existing);
        $integrationService->expects($this->never())->method('create');

        (new CreateExemptaxIntegration($integrationService))->apply();
    }
}
