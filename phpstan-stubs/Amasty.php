<?php

// PHPStan bootstrap stubs for Amasty Affiliate (commercial package, not installed in CI).
// Loaded via phpstan.neon.dist bootstrapFiles so PHPStan knows these types.

declare(strict_types=1);

namespace Amasty\Affiliate\Api\Data {
    interface AccountInterface
    {
        public function getStatus(): int;
    }
}

namespace Amasty\Affiliate\Api {
    interface AccountRepositoryInterface
    {
        public function getByReferringCode(string $referringCode): \Amasty\Affiliate\Api\Data\AccountInterface;
    }
}

namespace Amasty\Affiliate\Model {
    class RegistryConstants
    {
        public const CURRENT_AFFILIATE_ACCOUNT_CODE = 'amasty_affiliate_code';
    }
}

namespace Amasty\Affiliate\Model\Source {
    class Status
    {
        public const ENABLED = 1;
        public const DISABLED = 0;
    }
}

namespace Amasty\Affiliate\Model\Rule {
    class AffiliateQuoteResolver
    {
        public function resolveAffiliateAccount(): ?\Amasty\Affiliate\Api\Data\AccountInterface
        {
            return null;
        }

        /** @return int[] */
        public function resolveRuleIds(): array
        {
            return [];
        }
    }
}
