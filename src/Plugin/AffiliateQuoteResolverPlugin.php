<?php

declare(strict_types=1);

namespace PixelPerfect\AmastyAffiliateAttributionFix\Plugin;

use Amasty\Affiliate\Api\AccountRepositoryInterface;
use Amasty\Affiliate\Api\Data\AccountInterface;
use Amasty\Affiliate\Model\RegistryConstants;
use Amasty\Affiliate\Model\Rule\AffiliateQuoteResolver;
use Amasty\Affiliate\Model\Source\Status;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Psr\Log\LoggerInterface;

class AffiliateQuoteResolverPlugin
{
    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly State $appState,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Fall back to affiliate cookie when original resolution returns null.
     *
     * Amasty Affiliate's resolveAffiliateAccount() returns null when a non-affiliate
     * coupon is applied, because the coupon lookup throws NoSuchEntityException and
     * short-circuits before checking the cookie. This plugin catches that case and
     * falls back to the cookie.
     *
     * @param AffiliateQuoteResolver $subject
     * @param AccountInterface|null $result
     * @return AccountInterface|null
     */
    public function afterResolveAffiliateAccount(
        AffiliateQuoteResolver $subject,
        ?AccountInterface $result,
    ): ?AccountInterface {
        if ($result !== null) {
            return $result;
        }

        if ($this->appState->getAreaCode() === Area::AREA_ADMINHTML) {
            return null;
        }

        $affiliateCode = $this->cookieManager->getCookie(
            RegistryConstants::CURRENT_AFFILIATE_ACCOUNT_CODE
        );

        if ($affiliateCode === null || $affiliateCode === '') {
            return null;
        }

        try {
            $account = $this->accountRepository->getByReferringCode($affiliateCode);

            if ($account->getStatus() === Status::ENABLED) {
                return $account;
            }
        } catch (NoSuchEntityException) {
            $this->logger->debug(
                sprintf('Affiliate cookie references non-existent account code: %s', $affiliateCode)
            );
        }

        return null;
    }
}
