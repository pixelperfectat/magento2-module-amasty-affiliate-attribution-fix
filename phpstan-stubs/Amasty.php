<?php

// PHPStan stubs for Amasty Affiliate (commercial package, not installed in CI).
// These provide enough type information for static analysis without the actual package.

declare(strict_types=1);

namespace Amasty\Affiliate\Api\Data;

interface AccountInterface
{
    public function getStatus(): int;
}

namespace Amasty\Affiliate\Api;

use Amasty\Affiliate\Api\Data\AccountInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface AccountRepositoryInterface
{
    /** @throws NoSuchEntityException */
    public function getByReferringCode(string $referringCode): AccountInterface;
}

namespace Amasty\Affiliate\Model;

class RegistryConstants
{
    public const CURRENT_AFFILIATE_ACCOUNT_CODE = 'amasty_affiliate_code';
}

namespace Amasty\Affiliate\Model\Source;

class Status
{
    public const ENABLED = 1;
    public const DISABLED = 0;
}

namespace Amasty\Affiliate\Model\Rule;

use Amasty\Affiliate\Api\Data\AccountInterface;

class AffiliateQuoteResolver
{
    public function resolveAffiliateAccount(): ?AccountInterface
    {
        return null;
    }

    /** @return int[] */
    public function resolveRuleIds(): array
    {
        return [];
    }
}
