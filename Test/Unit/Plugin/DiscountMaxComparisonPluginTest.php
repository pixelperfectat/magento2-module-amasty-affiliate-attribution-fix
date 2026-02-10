<?php

declare(strict_types=1);

namespace PixelPerfect\AmastyAffiliateAttributionFix\Test\Unit\Plugin;

use Amasty\Affiliate\Model\Rule\AffiliateQuoteResolver;
use Magento\Framework\Api\ExtensionAttributesInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\SalesRule\Api\Data\RuleDiscountInterface;
use Magento\SalesRule\Model\Data\RuleDiscount;
use Magento\SalesRule\Model\Quote\Discount;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection as RuleCollection;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\AmastyAffiliateAttributionFix\Plugin\DiscountMaxComparisonPlugin;

class DiscountMaxComparisonPluginTest extends TestCase
{
    private AffiliateQuoteResolver&MockObject $affiliateQuoteResolver;
    private RuleCollectionFactory&MockObject $ruleCollectionFactory;
    private Discount&MockObject $subject;
    private Quote&MockObject $quote;
    private ShippingAssignmentInterface&MockObject $shippingAssignment;
    private Total&MockObject $total;
    private DiscountMaxComparisonPlugin $plugin;

    protected function setUp(): void
    {
        $this->affiliateQuoteResolver = $this->createMock(AffiliateQuoteResolver::class);
        $this->ruleCollectionFactory = $this->createMock(RuleCollectionFactory::class);
        $this->subject = $this->createMock(Discount::class);
        $this->quote = $this->createMock(Quote::class);
        $this->shippingAssignment = $this->createMock(ShippingAssignmentInterface::class);
        $this->total = $this->getMockBuilder(Total::class)
            ->addMethods(['getSubtotal', 'getBaseSubtotal', 'setSubtotalWithDiscount', 'setBaseSubtotalWithDiscount'])
            ->onlyMethods(['addTotalAmount', 'addBaseTotalAmount', 'getTotalAmount', 'getBaseTotalAmount'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new DiscountMaxComparisonPlugin(
            $this->affiliateQuoteResolver,
            $this->ruleCollectionFactory,
        );
    }

    public function testSkipsWhenNoAffiliateRules(): void
    {
        $this->affiliateQuoteResolver->method('resolveRuleIds')->willReturn([]);
        $this->quote->expects($this->never())->method('getCouponCode');

        $result = $this->plugin->afterCollect(
            $this->subject,
            $this->subject,
            $this->quote,
            $this->shippingAssignment,
            $this->total,
        );

        $this->assertSame($this->subject, $result);
    }

    public function testSkipsWhenNoCouponCode(): void
    {
        $this->affiliateQuoteResolver->method('resolveRuleIds')->willReturn([10]);
        $this->quote->method('getCouponCode')->willReturn(null);
        $this->shippingAssignment->expects($this->never())->method('getItems');

        $result = $this->plugin->afterCollect(
            $this->subject,
            $this->subject,
            $this->quote,
            $this->shippingAssignment,
            $this->total,
        );

        $this->assertSame($this->subject, $result);
    }

    public function testSkipsWhenNoItems(): void
    {
        $this->affiliateQuoteResolver->method('resolveRuleIds')->willReturn([10]);
        $this->quote->method('getCouponCode')->willReturn('COUPON10');
        $this->shippingAssignment->method('getItems')->willReturn([]);

        $result = $this->plugin->afterCollect(
            $this->subject,
            $this->subject,
            $this->quote,
            $this->shippingAssignment,
            $this->total,
        );

        $this->assertSame($this->subject, $result);
    }

    public function testSkipsWhenOnlyAffiliateDiscountApplied(): void
    {
        $affiliateRuleId = 10;
        $this->affiliateQuoteResolver->method('resolveRuleIds')->willReturn([$affiliateRuleId]);
        $this->quote->method('getCouponCode')->willReturn('COUPON10');

        $item = $this->createItemWithDiscounts([
            $affiliateRuleId => [8.00, 8.00],
        ]);
        $this->shippingAssignment->method('getItems')->willReturn([$item]);

        $this->mockRuleCollection([]);

        $this->total->expects($this->never())->method('addTotalAmount');

        $this->plugin->afterCollect(
            $this->subject,
            $this->subject,
            $this->quote,
            $this->shippingAssignment,
            $this->total,
        );
    }

    /**
     * @dataProvider maxOfTwoProvider
     */
    public function testKeepsLargerDiscount(
        float $affiliateDiscount,
        float $couponDiscount,
        float $expectedAdjustment,
    ): void {
        $affiliateRuleId = 10;
        $couponRuleId = 20;

        $this->affiliateQuoteResolver->method('resolveRuleIds')->willReturn([$affiliateRuleId]);
        $this->quote->method('getCouponCode')->willReturn('COUPON10');

        $item = $this->createItemWithDiscounts([
            $affiliateRuleId => [$affiliateDiscount, $affiliateDiscount],
            $couponRuleId => [$couponDiscount, $couponDiscount],
        ]);
        $item->method('getDiscountAmount')->willReturn($affiliateDiscount + $couponDiscount);
        $item->method('getBaseDiscountAmount')->willReturn($affiliateDiscount + $couponDiscount);

        $this->shippingAssignment->method('getItems')->willReturn([$item]);
        $this->mockRuleCollection([$couponRuleId => Rule::COUPON_TYPE_SPECIFIC]);

        if ($expectedAdjustment > 0.0) {
            $this->total->expects($this->once())
                ->method('addTotalAmount')
                ->with('discount', $expectedAdjustment);

            $this->total->expects($this->once())
                ->method('addBaseTotalAmount')
                ->with('discount', $expectedAdjustment);

            $this->total->method('getSubtotal')->willReturn(100.0);
            $this->total->method('getBaseSubtotal')->willReturn(100.0);
            $this->total->method('getTotalAmount')->willReturn(-max($affiliateDiscount, $couponDiscount));
            $this->total->method('getBaseTotalAmount')->willReturn(-max($affiliateDiscount, $couponDiscount));
        }

        $this->plugin->afterCollect(
            $this->subject,
            $this->subject,
            $this->quote,
            $this->shippingAssignment,
            $this->total,
        );
    }

    public static function maxOfTwoProvider(): array
    {
        return [
            'affiliate larger' => [
                'affiliateDiscount' => 10.00,
                'couponDiscount' => 7.00,
                'expectedAdjustment' => 7.00,
            ],
            'coupon larger' => [
                'affiliateDiscount' => 5.00,
                'couponDiscount' => 8.00,
                'expectedAdjustment' => 5.00,
            ],
            'equal discounts' => [
                'affiliateDiscount' => 6.00,
                'couponDiscount' => 6.00,
                'expectedAdjustment' => 6.00,
            ],
        ];
    }

    public function testSkipsNoCouponAutoRules(): void
    {
        $affiliateRuleId = 10;
        $autoRuleId = 30;

        $this->affiliateQuoteResolver->method('resolveRuleIds')->willReturn([$affiliateRuleId]);
        $this->quote->method('getCouponCode')->willReturn('COUPON10');

        $item = $this->createItemWithDiscounts([
            $affiliateRuleId => [10.00, 10.00],
            $autoRuleId => [5.00, 5.00],
        ]);
        $this->shippingAssignment->method('getItems')->willReturn([$item]);

        $this->mockRuleCollection([$autoRuleId => Rule::COUPON_TYPE_NO_COUPON]);

        $this->total->expects($this->never())->method('addTotalAmount');

        $this->plugin->afterCollect(
            $this->subject,
            $this->subject,
            $this->quote,
            $this->shippingAssignment,
            $this->total,
        );
    }

    /**
     * @param array<int, array{0: float, 1: float}> $ruleDiscounts [ruleId => [amount, baseAmount]]
     */
    private function createItemWithDiscounts(array $ruleDiscounts): AbstractItem&MockObject
    {
        $discounts = [];
        foreach ($ruleDiscounts as $ruleId => [$amount, $baseAmount]) {
            $discountData = $this->getMockBuilder(\Magento\SalesRule\Model\Rule\Action\Discount\Data::class)
                ->addMethods(['getAmount', 'getBaseAmount'])
                ->disableOriginalConstructor()
                ->getMock();
            $discountData->method('getAmount')->willReturn($amount);
            $discountData->method('getBaseAmount')->willReturn($baseAmount);

            $ruleDiscount = $this->createMock(RuleDiscountInterface::class);
            $ruleDiscount->method('getRuleID')->willReturn($ruleId);
            $ruleDiscount->method('getDiscountData')->willReturn($discountData);

            $discounts[] = $ruleDiscount;
        }

        $extensionAttributes = $this->getMockBuilder(ExtensionAttributesInterface::class)
            ->addMethods(['getDiscounts', 'setDiscounts'])
            ->getMock();
        $extensionAttributes->method('getDiscounts')->willReturn($discounts);

        $item = $this->getMockBuilder(AbstractItem::class)
            ->addMethods(['isChildrenCalculated'])
            ->onlyMethods([
                'getExtensionAttributes',
                'getChildren',
                'getDiscountAmount',
                'setDiscountAmount',
                'getBaseDiscountAmount',
                'setBaseDiscountAmount',
                'getAppliedRuleIds',
                'setAppliedRuleIds',
            ])
            ->disableOriginalConstructor()
            ->getMock();

        $item->method('getExtensionAttributes')->willReturn($extensionAttributes);
        $item->method('getChildren')->willReturn([]);
        $item->method('isChildrenCalculated')->willReturn(false);
        $item->method('getAppliedRuleIds')->willReturn(implode(',', array_keys($ruleDiscounts)));

        return $item;
    }

    /**
     * @param array<int, int> $ruleIdToCouponType [ruleId => couponType]
     */
    private function mockRuleCollection(array $ruleIdToCouponType): void
    {
        $rules = [];
        foreach ($ruleIdToCouponType as $ruleId => $couponType) {
            $rule = $this->getMockBuilder(Rule::class)
                ->addMethods(['getCouponType'])
                ->onlyMethods(['getId'])
                ->disableOriginalConstructor()
                ->getMock();
            $rule->method('getId')->willReturn($ruleId);
            $rule->method('getCouponType')->willReturn($couponType);
            $rules[] = $rule;
        }

        $collection = $this->createMock(RuleCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($rules));

        $this->ruleCollectionFactory->method('create')->willReturn($collection);
    }
}
