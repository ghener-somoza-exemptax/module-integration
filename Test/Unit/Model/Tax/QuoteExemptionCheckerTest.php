<?php

declare(strict_types=1);

namespace Exemptax\Integration\Test\Unit\Model\Tax;

use Exemptax\Integration\Model\Config;
use Exemptax\Integration\Model\Tax\ExemptionStates;
use Exemptax\Integration\Model\Tax\QuoteExemptionChecker;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\AttributeValueInterface;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class QuoteExemptionCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!interface_exists(CustomerRepositoryInterface::class)
            || !class_exists(Quote::class)
            || !class_exists(Address::class)
        ) {
            $this->markTestSkipped('Magento framework is not available in this PHPUnit bootstrap.');
        }
    }

    public function test_should_exempt_when_ship_to_state_is_in_customer_list(): void
    {
        $checker = $this->makeChecker('CA,NY', enabled: true);
        $quote = $this->quoteWithCustomer(7);
        $assignment = $this->shippingAssignment('US', 'CA');

        $this->assertTrue($checker->shouldExemptQuote($quote, $assignment));
    }

    public function test_does_not_exempt_when_ship_to_state_is_not_listed(): void
    {
        $checker = $this->makeChecker('CA', enabled: true);
        $quote = $this->quoteWithCustomer(7);
        $assignment = $this->shippingAssignment('US', 'TX');

        $this->assertFalse($checker->shouldExemptQuote($quote, $assignment));
    }

    public function test_does_not_exempt_when_feature_disabled(): void
    {
        $checker = $this->makeChecker('CA', enabled: false);
        $quote = $this->quoteWithCustomer(7);
        $assignment = $this->shippingAssignment('US', 'CA');

        $this->assertFalse($checker->shouldExemptQuote($quote, $assignment));
    }

    public function test_does_not_exempt_guest_quote(): void
    {
        $checker = $this->makeChecker('CA', enabled: true);
        $quote = $this->quoteWithCustomer(0);
        $assignment = $this->shippingAssignment('US', 'CA');

        $this->assertFalse($checker->shouldExemptQuote($quote, $assignment));
    }

    public function test_does_not_exempt_non_us_shipments(): void
    {
        $checker = $this->makeChecker('CA', enabled: true);
        $quote = $this->quoteWithCustomer(7);
        $assignment = $this->shippingAssignment('CA', 'ON');

        $this->assertFalse($checker->shouldExemptQuote($quote, $assignment));
    }

    private function makeChecker(string $statesCsv, bool $enabled): QuoteExemptionChecker
    {
        $config = $this->createMock(Config::class);
        $config->method('isStateExemptionEnabled')->willReturn($enabled);

        $attribute = $this->createMock(AttributeValueInterface::class);
        $attribute->method('getValue')->willReturn($statesCsv);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getCustomAttribute')->with(ExemptionStates::ATTRIBUTE_CODE)->willReturn($attribute);

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('getById')->willReturn($customer);

        $regionFactory = $this->createMock(RegionFactory::class);
        $regionFactory->method('create')->willReturn($this->createMock(Region::class));

        return new QuoteExemptionChecker(
            $config,
            $customerRepository,
            new ExemptionStates(),
            $regionFactory,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function quoteWithCustomer(int $customerId): Quote
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);

        $quote = $this->createMock(Quote::class);
        $quote->method('getStore')->willReturn($store);
        $quote->method('getCustomerId')->willReturn($customerId);

        return $quote;
    }

    private function shippingAssignment(string $countryId, string $regionCode): ShippingAssignmentInterface
    {
        $address = $this->createMock(Address::class);
        $address->method('getCountryId')->willReturn($countryId);
        $address->method('getRegionCode')->willReturn($regionCode);
        $address->method('getRegionId')->willReturn(0);

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);

        $assignment = $this->createMock(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($shipping);

        return $assignment;
    }
}
