# PixelPerfect_AmastyAffiliateAttributionFix

Fixes affiliate attribution loss in Amasty Affiliate when a non-affiliate coupon code is applied alongside an affiliate cookie.

## The Problem

When a customer arrives via an affiliate link, Amasty Affiliate sets a cookie (`current_affiliate_account_code`). During cart calculation, `AffiliateQuoteResolver::resolveAffiliateAccount()` uses this cookie to identify the affiliate.

However, the method uses a priority chain with a bug:

1. Check `quote->getCouponCode()` — if found, look it up in the affiliate coupon table
2. If the coupon is **not** an affiliate coupon → `NoSuchEntityException` → **return null immediately**
3. Only if no coupon code is present → read the cookie

When a customer enters a regular (non-affiliate) coupon, step 2 short-circuits and the affiliate cookie is never checked. This causes:

- Affiliate rules are no longer applied during cart calculation
- `SalesOrderAfterPlaceObserver` cannot find an affiliate account → no transaction → **attribution lost**

## The Fix

### 1. Cookie Fallback (AffiliateQuoteResolverPlugin)

An **after plugin** on `AffiliateQuoteResolver::resolveAffiliateAccount()` that falls back to the cookie when the original method returns null but the cookie is present.

### 2. Max-of-Two Discount (DiscountMaxComparisonPlugin)

An **after plugin** on `Magento\SalesRule\Model\Quote\Discount::collect()` that prevents affiliate and coupon discounts from stacking. When both apply to the same item, only the **larger** discount is kept.

- Coupon-specific rules (requiring a coupon code) are compared against affiliate rules
- Non-coupon rules (auto/site-wide sales) are **not** affected — they always apply
- Per-item comparison using Magento's built-in per-rule discount breakdown

## Installation

```bash
composer require pixelperfectat/magento2-module-amasty-affiliate-attribution-fix
bin/magento module:enable PixelPerfect_AmastyAffiliateAttributionFix
bin/magento setup:upgrade
```

## Configuration

None required — the module works automatically once installed.

## Discount Stacking Behaviour

| Scenario | Result |
|----------|--------|
| Affiliate only | Full affiliate discount applied |
| Coupon only | Full coupon discount applied |
| Both, affiliate larger | Affiliate discount applied, coupon removed |
| Both, coupon larger | Coupon discount applied, affiliate removed |
| Both + auto rule | Max(affiliate, coupon) + auto rule |

To control stacking per-rule via Magento's built-in mechanism, set `stop_rules_processing` on individual sales rules.

## Compatibility

- PHP 8.3+
- Magento 2.4.x
- Amasty Affiliate 2.3.0+

## Testing

```bash
vendor/bin/phpunit
```

### Manual Verification

1. Click an affiliate link → verify cookie is set
2. Add items to cart
3. Apply a regular (non-affiliate) coupon code
4. Place order
5. Check `amasty_affiliate_transaction` table → affiliate transaction exists
6. Check Admin → Affiliate → Transactions → order appears with correct affiliate
