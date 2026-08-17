<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Model\Webhook;

use Exemptax\Integration\Model\Webhook\PayloadBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class PayloadBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(StoreManagerInterface::class) || !class_exists(HttpRequest::class)) {
            $this->markTestSkipped('Magento framework is not available in this PHPUnit bootstrap.');
        }
    }

    public function test_build_customer_event_includes_scope_id_and_store_url(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getBaseUrl')->willReturn('https://magento.test:9443/');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $request = $this->createMock(HttpRequest::class);
        $builder = new PayloadBuilder($storeManager, $request);

        $this->assertSame(
            [
                'scope' => 'customer/updated',
                'data' => ['id' => 42],
                'store_base_url' => 'https://magento.test:9443',
            ],
            $builder->buildCustomerEvent('customer/updated', 42)
        );
    }

    public function test_get_store_base_url_falls_back_to_request_host(): void
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));

        $request = $this->createMock(HttpRequest::class);
        $request->method('getHttpHost')->willReturn('example.test');
        $request->method('isSecure')->willReturn(true);

        $builder = new PayloadBuilder($storeManager, $request);

        $this->assertSame('https://example.test', $builder->getStoreBaseUrl());
    }
}
