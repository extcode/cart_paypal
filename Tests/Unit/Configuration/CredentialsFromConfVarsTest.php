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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CredentialsFromConfVars::class)]
final class CredentialsFromConfVarsTest extends UnitTestCase
{
    #[Test]
    public function defaultPriorityIsLowerThanTheOneOfTheSiteConfiguration(): void
    {
        self::assertSame(30, CredentialsFromConfVars::getDefaultPriority());
    }

    #[Test]
    public function readsTheConfigurationOfTheExtension(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration
            ->expects(self::once())
            ->method('get')
            ->with('cart_paypal')
            ->willReturn([]);

        new CredentialsFromConfVars($extensionConfiguration);
    }

    #[Test]
    public function returnsCredentialsOfTheExtensionConfiguration(): void
    {
        $credentials = [
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'webhookId' => 'webhook-id',
            'sandbox' => '1',
        ];

        $subject = new CredentialsFromConfVars(
            $this->createExtensionConfiguration(['credentials' => $credentials])
        );

        self::assertSame($credentials, $subject->getCredentials());
    }

    public static function configurationWithoutCredentialsDataProvider(): \Generator
    {
        yield 'empty configuration' => [[]];
        yield 'configuration without credentials' => [['someOtherSetting' => '1']];
        yield 'credentials as string' => [['credentials' => 'secret']];
    }

    #[Test]
    #[DataProvider('configurationWithoutCredentialsDataProvider')]
    public function returnsEmptyCredentialsWithoutCredentialsInConfiguration(array $configuration): void
    {
        $subject = new CredentialsFromConfVars(
            $this->createExtensionConfiguration($configuration)
        );

        self::assertSame([], $subject->getCredentials());
    }

    #[Test]
    public function returnsEmptyCredentialsIfTheExtensionIsNotConfigured(): void
    {
        $extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $extensionConfiguration
            ->method('get')
            ->willThrowException(new ExtensionConfigurationExtensionNotConfiguredException('not configured', 1509654728));

        $subject = new CredentialsFromConfVars($extensionConfiguration);

        self::assertSame([], $subject->getCredentials());
    }

    private function createExtensionConfiguration(array $configuration): ExtensionConfiguration
    {
        $extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configuration);

        return $extensionConfiguration;
    }
}
