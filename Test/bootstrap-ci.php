<?php

declare(strict_types=1);

/**
 * CI bootstrap for unit tests.
 *
 * Stubs Amasty classes that are not installed in CI composer vendor
 * (Amasty is a commercial package). Magento classes are available because
 * magento/framework, magento/module-catalog, and magento/module-sales-rule
 * are all in composer require and are installed via repo.magento.com.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------------------------
// Amasty stubs — commercial package, never installed in CI
// ---------------------------------------------------------------------------

if (!interface_exists(\Amasty\Affiliate\Api\AccountRepositoryInterface::class)) {
    interface Amasty_Affiliate_Api_AccountRepositoryInterface
    {
        /** @throws \Magento\Framework\Exception\NoSuchEntityException */
        public function getByReferringCode(string $code): \Amasty\Affiliate\Api\Data\AccountInterface;
    }
    class_alias(
        Amasty_Affiliate_Api_AccountRepositoryInterface::class,
        \Amasty\Affiliate\Api\AccountRepositoryInterface::class
    );
}

if (!interface_exists(\Amasty\Affiliate\Api\Data\AccountInterface::class)) {
    interface Amasty_Affiliate_Api_Data_AccountInterface
    {
        public function getStatus(): int;
    }
    class_alias(
        Amasty_Affiliate_Api_Data_AccountInterface::class,
        \Amasty\Affiliate\Api\Data\AccountInterface::class
    );
}

if (!class_exists(\Amasty\Affiliate\Model\RegistryConstants::class)) {
    class Amasty_Affiliate_Model_RegistryConstants
    {
        public const CURRENT_AFFILIATE_ACCOUNT_CODE = 'amasty_affiliate_code';
    }
    class_alias(
        Amasty_Affiliate_Model_RegistryConstants::class,
        \Amasty\Affiliate\Model\RegistryConstants::class
    );
}

if (!class_exists(\Amasty\Affiliate\Model\Rule\AffiliateQuoteResolver::class)) {
    class Amasty_Affiliate_Model_Rule_AffiliateQuoteResolver
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
    class_alias(
        Amasty_Affiliate_Model_Rule_AffiliateQuoteResolver::class,
        \Amasty\Affiliate\Model\Rule\AffiliateQuoteResolver::class
    );
}

if (!class_exists(\Amasty\Affiliate\Model\Source\Status::class)) {
    class Amasty_Affiliate_Model_Source_Status
    {
        public const ENABLED = 1;
        public const DISABLED = 0;
    }
    class_alias(
        Amasty_Affiliate_Model_Source_Status::class,
        \Amasty\Affiliate\Model\Source\Status::class
    );
}
