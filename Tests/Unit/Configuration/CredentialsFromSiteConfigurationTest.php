<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Unit\Configuration;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Configuration\CredentialsFromConfVars;
use Extcode\CartPaypal\Configuration\CredentialsFromSiteConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CredentialsFromSiteConfiguration::class)]
final class CredentialsFromSiteConfigurationTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function defaultPriorityIsHigherThanTheOneOfTheConfVars(): void
    {
        self::assertGreaterThan(
            CredentialsFromConfVars::getDefaultPriority(),
            CredentialsFromSiteConfiguration::getDefaultPriority()
        );
    }

    #[Test]
    public function returnsCredentialsOfTheSiteOfTheRequest(): void
    {
        $credentials = [
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'webhookId' => 'webhook-id',
            'sandbox' => '1',
        ];
        $this->setSiteConfiguration(['cartPaypal' => ['credentials' => $credentials]]);

        self::assertSame($credentials, (new CredentialsFromSiteConfiguration())->getCredentials());
    }

    public static function siteConfigurationWithoutCredentialsDataProvider(): \Generator
    {
        yield 'no configuration of cart_paypal' => [[]];
        yield 'configuration without credentials' => [['cartPaypal' => []]];
        yield 'credentials as string' => [['cartPaypal' => ['credentials' => 'secret']]];
    }

    #[Test]
    #[DataProvider('siteConfigurationWithoutCredentialsDataProvider')]
    public function returnsEmptyCredentialsWithoutCredentialsInTheSiteConfiguration(array $configuration): void
    {
        $this->setSiteConfiguration($configuration);

        self::assertSame([], (new CredentialsFromSiteConfiguration())->getCredentials());
    }

    #[Test]
    public function returnsEmptyCredentialsWithoutRequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        self::assertSame([], (new CredentialsFromSiteConfiguration())->getCredentials());
    }

    #[Test]
    public function returnsEmptyCredentialsWithoutSite(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = new ServerRequest('http://localhost/');

        self::assertSame([], (new CredentialsFromSiteConfiguration())->getCredentials());
    }

    private function setSiteConfiguration(array $configuration): void
    {
        $site = new Site('default', 1, $configuration + ['base' => 'http://localhost/']);
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
    }
}
