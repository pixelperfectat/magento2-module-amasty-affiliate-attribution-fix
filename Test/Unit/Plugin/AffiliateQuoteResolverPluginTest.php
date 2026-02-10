<?php

declare(strict_types=1);

namespace PixelPerfect\AmastyAffiliateAttributionFix\Test\Unit\Plugin;

use Amasty\Affiliate\Api\AccountRepositoryInterface;
use Amasty\Affiliate\Api\Data\AccountInterface;
use Amasty\Affiliate\Model\RegistryConstants;
use Amasty\Affiliate\Model\Rule\AffiliateQuoteResolver;
use Amasty\Affiliate\Model\Source\Status;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\AmastyAffiliateAttributionFix\Plugin\AffiliateQuoteResolverPlugin;
use Psr\Log\LoggerInterface;

class AffiliateQuoteResolverPluginTest extends TestCase
{
    private CookieManagerInterface&MockObject $cookieManager;
    private AccountRepositoryInterface&MockObject $accountRepository;
    private State&MockObject $appState;
    private LoggerInterface&MockObject $logger;
    private AffiliateQuoteResolver&MockObject $subject;
    private AffiliateQuoteResolverPlugin $plugin;

    protected function setUp(): void
    {
        $this->cookieManager = $this->createMock(CookieManagerInterface::class);
        $this->accountRepository = $this->createMock(AccountRepositoryInterface::class);
        $this->appState = $this->createMock(State::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subject = $this->createMock(AffiliateQuoteResolver::class);

        $this->plugin = new AffiliateQuoteResolverPlugin(
            $this->cookieManager,
            $this->accountRepository,
            $this->appState,
            $this->logger,
        );
    }

    public function testReturnsOriginalResultWhenNotNull(): void
    {
        $account = $this->createMock(AccountInterface::class);

        $this->cookieManager->expects($this->never())->method('getCookie');

        $result = $this->plugin->afterResolveAffiliateAccount($this->subject, $account);
        $this->assertSame($account, $result);
    }

    public function testReturnsNullInAdminArea(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_ADMINHTML);
        $this->cookieManager->expects($this->never())->method('getCookie');

        $result = $this->plugin->afterResolveAffiliateAccount($this->subject, null);
        $this->assertNull($result);
    }

    /**
     * @dataProvider emptyCookieProvider
     */
    public function testReturnsNullWhenCookieIsEmpty(?string $cookieValue): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        $this->cookieManager->method('getCookie')
            ->with(RegistryConstants::CURRENT_AFFILIATE_ACCOUNT_CODE)
            ->willReturn($cookieValue);

        $this->accountRepository->expects($this->never())->method('getByReferringCode');

        $result = $this->plugin->afterResolveAffiliateAccount($this->subject, null);
        $this->assertNull($result);
    }

    public static function emptyCookieProvider(): array
    {
        return [
            'null cookie' => [null],
            'empty string cookie' => [''],
        ];
    }

    public function testReturnsAccountWhenCookiePresent(): void
    {
        $affiliateCode = 'AFFILIATE123';
        $account = $this->createMock(AccountInterface::class);
        $account->method('getStatus')->willReturn(Status::ENABLED);

        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        $this->cookieManager->method('getCookie')
            ->with(RegistryConstants::CURRENT_AFFILIATE_ACCOUNT_CODE)
            ->willReturn($affiliateCode);

        $this->accountRepository->method('getByReferringCode')
            ->with($affiliateCode)
            ->willReturn($account);

        $result = $this->plugin->afterResolveAffiliateAccount($this->subject, null);
        $this->assertSame($account, $result);
    }

    public function testReturnsNullWhenAccountDisabled(): void
    {
        $account = $this->createMock(AccountInterface::class);
        $account->method('getStatus')->willReturn(Status::DISABLED);

        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        $this->cookieManager->method('getCookie')
            ->with(RegistryConstants::CURRENT_AFFILIATE_ACCOUNT_CODE)
            ->willReturn('AFFILIATE123');

        $this->accountRepository->method('getByReferringCode')
            ->willReturn($account);

        $result = $this->plugin->afterResolveAffiliateAccount($this->subject, null);
        $this->assertNull($result);
    }

    public function testReturnsNullAndLogsWhenAccountNotFound(): void
    {
        $this->appState->method('getAreaCode')->willReturn(Area::AREA_FRONTEND);

        $this->cookieManager->method('getCookie')
            ->with(RegistryConstants::CURRENT_AFFILIATE_ACCOUNT_CODE)
            ->willReturn('NONEXISTENT');

        $this->accountRepository->method('getByReferringCode')
            ->willThrowException(new NoSuchEntityException());

        $this->logger->expects($this->once())->method('debug');

        $result = $this->plugin->afterResolveAffiliateAccount($this->subject, null);
        $this->assertNull($result);
    }
}
