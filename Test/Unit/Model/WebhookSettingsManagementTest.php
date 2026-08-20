<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Model;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\WebhookSettingsManagement;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use PHPUnit\Framework\TestCase;

class WebhookSettingsManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(WriterInterface::class)
            || !interface_exists(TypeListInterface::class)
            || !interface_exists(ReinitableConfigInterface::class)
        ) {
            $this->markTestSkipped('Magento framework is not available in this PHPUnit bootstrap.');
        }
    }

    public function test_persist_native_magento_engine_disables_taxjar_checkout(): void
    {
        $saved = $this->persist(['tax_engine' => 'magento']);

        $this->assertSame('magento', $saved[Config::XML_PATH_TAX_ENGINE]);
        $this->assertSame('0', $saved[Config::XML_PATH_TAXJAR_CHECKOUT_ENABLED]);
    }

    public function test_persist_taxjar_engine_enables_taxjar_checkout(): void
    {
        $saved = $this->persist(['tax_engine' => 'taxjar']);

        $this->assertSame('taxjar', $saved[Config::XML_PATH_TAX_ENGINE]);
        $this->assertSame('1', $saved[Config::XML_PATH_TAXJAR_CHECKOUT_ENABLED]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function persist(array $data): array
    {
        $saved = [];
        $writer = $this->createMock(WriterInterface::class);
        $writer->method('save')->willReturnCallback(
            static function (string $path, $value) use (&$saved): void {
                $saved[$path] = $value;
            }
        );

        $cache = $this->createMock(TypeListInterface::class);
        $cache->expects($this->once())->method('cleanType')->with('config');
        $reinit = $this->createMock(ReinitableConfigInterface::class);
        $reinit->expects($this->once())->method('reinit');

        $mgmt = new WebhookSettingsManagement(
            $writer,
            $this->createMock(Config::class),
            $cache,
            $reinit
        );
        $mgmt->persistCompanySettings($data);

        return $saved;
    }
}
