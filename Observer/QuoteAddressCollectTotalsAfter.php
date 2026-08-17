<?php

declare(strict_types=1);

namespace Exemptax\Integration\Observer;

use Exemptax\Integration\Model\Tax\QuoteExemptionChecker;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Psr\Log\LoggerInterface;

/**
 * Zero address tax after collectors run when ship-to state is Exemptax-exempt.
 */
class QuoteAddressCollectTotalsAfter implements ObserverInterface
{
    public function __construct(
        private readonly QuoteExemptionChecker $exemptionChecker,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            /** @var Quote|null $quote */
            $quote = $observer->getEvent()->getQuote();
            /** @var ShippingAssignmentInterface|null $shippingAssignment */
            $shippingAssignment = $observer->getEvent()->getShippingAssignment();
            /** @var Total|null $total */
            $total = $observer->getEvent()->getTotal();

            if (!$quote || !$shippingAssignment || !$total) {
                return;
            }

            if (!$this->exemptionChecker->shouldExemptQuote($quote, $shippingAssignment)) {
                return;
            }

            $this->zeroTax($shippingAssignment, $total);

            $this->logger->info('Exemptax zeroed quote tax for exempt ship-to state', [
                'customer_id' => (int) $quote->getCustomerId(),
                'quote_id' => $quote->getId(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Exemptax tax exemption observer failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function zeroTax(ShippingAssignmentInterface $shippingAssignment, Total $total): void
    {
        foreach ($shippingAssignment->getItems() ?? [] as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            $item->setTaxAmount(0);
            $item->setBaseTaxAmount(0);
            $item->setTaxPercent(0);
            $item->setDiscountTaxCompensationAmount(0);
            $item->setBaseDiscountTaxCompensationAmount(0);
            $item->setPriceInclTax((float) $item->getPrice());
            $item->setBasePriceInclTax((float) $item->getBasePrice());
            $item->setRowTotalInclTax((float) $item->getRowTotal());
            $item->setBaseRowTotalInclTax((float) $item->getBaseRowTotal());
        }

        $total->setTotalAmount('tax', 0);
        $total->setBaseTotalAmount('tax', 0);
        $total->setTotalAmount('discount_tax_compensation', 0);
        $total->setBaseTotalAmount('discount_tax_compensation', 0);
        $total->setTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setBaseTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setTotalAmount('extra_tax', 0);
        $total->setBaseTotalAmount('extra_tax', 0);

        $total->setTaxAmount(0);
        $total->setBaseTaxAmount(0);
        $total->setShippingTaxAmount(0);
        $total->setBaseShippingTaxAmount(0);
        $total->setDiscountTaxCompensationAmount(0);
        $total->setBaseDiscountTaxCompensationAmount(0);
        $total->setShippingDiscountTaxCompensationAmount(0);
        $total->setBaseShippingDiscountTaxCompensationAmount(0);
        $total->setShippingInclTax((float) $total->getShippingAmount());
        $total->setBaseShippingInclTax((float) $total->getBaseShippingAmount());
        $total->setSubtotalInclTax((float) $total->getSubtotal());
        $total->setBaseSubtotalInclTax((float) $total->getBaseSubtotal());
        $total->setAppliedTaxes([]);

        // Grand total is usually sum of total amounts; keep it consistent after zeroing tax.
        $grand = (float) $total->getSubtotal()
            + (float) $total->getShippingAmount()
            - abs((float) $total->getDiscountAmount());
        $baseGrand = (float) $total->getBaseSubtotal()
            + (float) $total->getBaseShippingAmount()
            - abs((float) $total->getBaseDiscountAmount());
        $total->setGrandTotal($grand);
        $total->setBaseGrandTotal($baseGrand);
        $total->setTotalAmount('grand_total', $grand);
        $total->setBaseTotalAmount('grand_total', $baseGrand);
    }
}
