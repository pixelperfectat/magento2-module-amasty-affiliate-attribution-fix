<?php

declare(strict_types=1);

namespace PixelPerfect\AmastyAffiliateAttributionFix\Plugin;

use Amasty\Affiliate\Model\Rule\AffiliateQuoteResolver;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Api\Data\RuleDiscountInterface;
use Magento\SalesRule\Model\Quote\Discount;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Magento\SalesRule\Model\Rule;

class DiscountMaxComparisonPlugin
{
    /** @var int[]|null */
    private ?array $couponRuleIdsCache = null;

    public function __construct(
        private readonly AffiliateQuoteResolver $affiliateQuoteResolver,
        private readonly RuleCollectionFactory $ruleCollectionFactory,
    ) {
    }

    /**
     * Compare affiliate and coupon discounts per item — keep only the larger.
     *
     * When both an affiliate rule and a coupon-specific rule apply to the same item,
     * the customer receives only the larger discount, not both stacked. Non-coupon
     * rules (auto/no-coupon-required) are unaffected and always apply.
     *
     * @param Discount $subject
     * @param Discount $result
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return Discount
     */
    public function afterCollect(
        Discount $subject,
        Discount $result,
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
    ): Discount {
        $affiliateRuleIds = $this->affiliateQuoteResolver->resolveRuleIds();
        if (empty($affiliateRuleIds)) {
            return $result;
        }

        $couponCode = $quote->getCouponCode();
        if ($couponCode === null || $couponCode === '') {
            return $result;
        }

        $items = $shippingAssignment->getItems();
        if (empty($items)) {
            return $result;
        }

        $allAppliedRuleIds = $this->collectAppliedRuleIds($items);
        $couponRuleIds = $this->identifyCouponRules($allAppliedRuleIds, $affiliateRuleIds);
        if (empty($couponRuleIds)) {
            return $result;
        }

        $totalAdjustment = 0.0;
        $baseTotalAdjustment = 0.0;

        foreach ($items as $item) {
            if ($item->getChildren() && $item->isChildrenCalculated()) {
                foreach ($item->getChildren() as $child) {
                    [$adj, $baseAdj] = $this->adjustItemDiscount(
                        $child,
                        $affiliateRuleIds,
                        $couponRuleIds,
                    );
                    $totalAdjustment += $adj;
                    $baseTotalAdjustment += $baseAdj;
                }
            } else {
                [$adj, $baseAdj] = $this->adjustItemDiscount(
                    $item,
                    $affiliateRuleIds,
                    $couponRuleIds,
                );
                $totalAdjustment += $adj;
                $baseTotalAdjustment += $baseAdj;
            }
        }

        if ($totalAdjustment > 0.0) {
            $this->adjustTotals($total, $totalAdjustment, $baseTotalAdjustment);
        }

        return $result;
    }

    /**
     * Collect all applied rule IDs from items' discount breakdowns.
     *
     * @param AbstractItem[] $items
     * @return int[]
     */
    private function collectAppliedRuleIds(array $items): array
    {
        $ruleIds = [];

        foreach ($items as $item) {
            $this->extractRuleIdsFromItem($item, $ruleIds);

            if ($item->getChildren() && $item->isChildrenCalculated()) {
                foreach ($item->getChildren() as $child) {
                    $this->extractRuleIdsFromItem($child, $ruleIds);
                }
            }
        }

        return array_keys($ruleIds);
    }

    /**
     * Extract rule IDs from a single item's discount breakdown.
     *
     * @param AbstractItem $item
     * @param array<int, true> $ruleIds
     * @return void
     */
    private function extractRuleIdsFromItem(AbstractItem $item, array &$ruleIds): void
    {
        $discounts = $item->getExtensionAttributes()?->getDiscounts();
        if (empty($discounts)) {
            return;
        }

        foreach ($discounts as $ruleDiscount) {
            $ruleIds[(int) $ruleDiscount->getRuleID()] = true;
        }
    }

    /**
     * Identify which non-affiliate rules are coupon-specific (not auto/no-coupon).
     *
     * @param int[] $allRuleIds
     * @param int[] $affiliateRuleIds
     * @return int[]
     */
    private function identifyCouponRules(array $allRuleIds, array $affiliateRuleIds): array
    {
        if ($this->couponRuleIdsCache !== null) {
            return $this->couponRuleIdsCache;
        }

        $nonAffiliateIds = array_values(array_diff($allRuleIds, $affiliateRuleIds));
        if (empty($nonAffiliateIds)) {
            $this->couponRuleIdsCache = [];
            return [];
        }

        $collection = $this->ruleCollectionFactory->create();
        $collection->addFieldToFilter('rule_id', ['in' => $nonAffiliateIds]);

        $couponRuleIds = [];
        foreach ($collection as $rule) {
            if ((int) $rule->getCouponType() !== Rule::COUPON_TYPE_NO_COUPON) {
                $couponRuleIds[] = (int) $rule->getId();
            }
        }

        $this->couponRuleIdsCache = $couponRuleIds;
        return $couponRuleIds;
    }

    /**
     * Adjust a single item's discount — keep max(affiliate, coupon), remove the smaller.
     *
     * @param AbstractItem $item
     * @param int[] $affiliateRuleIds
     * @param int[] $couponRuleIds
     * @return array{0: float, 1: float}
     */
    private function adjustItemDiscount(
        AbstractItem $item,
        array $affiliateRuleIds,
        array $couponRuleIds,
    ): array {
        $discounts = $item->getExtensionAttributes()?->getDiscounts();
        if (empty($discounts)) {
            return [0.0, 0.0];
        }

        $affiliateAmount = 0.0;
        $affiliateBaseAmount = 0.0;
        $couponAmount = 0.0;
        $couponBaseAmount = 0.0;

        foreach ($discounts as $ruleDiscount) {
            $ruleId = (int) $ruleDiscount->getRuleID();
            $amount = (float) $ruleDiscount->getDiscountData()->getAmount();
            $baseAmount = (float) $ruleDiscount->getDiscountData()->getBaseAmount();

            if (in_array($ruleId, $affiliateRuleIds, true)) {
                $affiliateAmount += $amount;
                $affiliateBaseAmount += $baseAmount;
            } elseif (in_array($ruleId, $couponRuleIds, true)) {
                $couponAmount += $amount;
                $couponBaseAmount += $baseAmount;
            }
        }

        if ($affiliateAmount <= 0.0 || $couponAmount <= 0.0) {
            return [0.0, 0.0];
        }

        $adjustment = min($affiliateAmount, $couponAmount);
        $baseAdjustment = min($affiliateBaseAmount, $couponBaseAmount);

        $item->setDiscountAmount($item->getDiscountAmount() - $adjustment);
        $item->setBaseDiscountAmount($item->getBaseDiscountAmount() - $baseAdjustment);

        $losingCategory = $affiliateAmount >= $couponAmount
            ? $couponRuleIds
            : $affiliateRuleIds;

        $filteredDiscounts = array_filter(
            $discounts,
            static fn (RuleDiscountInterface $rd) => !in_array(
                (int) $rd->getRuleID(),
                $losingCategory,
                true,
            ),
        );
        $item->getExtensionAttributes()->setDiscounts(array_values($filteredDiscounts));

        $appliedRuleIds = array_filter(explode(',', (string) $item->getAppliedRuleIds()));
        $filteredIds = array_diff($appliedRuleIds, array_map('strval', $losingCategory));
        $item->setAppliedRuleIds(implode(',', $filteredIds));

        return [$adjustment, $baseAdjustment];
    }

    /**
     * Adjust quote totals after discount reduction.
     *
     * @param Total $total
     * @param float $adjustment
     * @param float $baseAdjustment
     * @return void
     */
    private function adjustTotals(Total $total, float $adjustment, float $baseAdjustment): void
    {
        $total->addTotalAmount('discount', $adjustment);
        $total->addBaseTotalAmount('discount', $baseAdjustment);

        $total->setSubtotalWithDiscount(
            $total->getSubtotal() + $total->getTotalAmount('discount')
        );
        $total->setBaseSubtotalWithDiscount(
            $total->getBaseSubtotal() + $total->getBaseTotalAmount('discount')
        );
    }
}
