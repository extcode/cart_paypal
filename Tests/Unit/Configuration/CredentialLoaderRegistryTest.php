<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Unit\Configuration;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Configuration\CredentialLoaderInterface;
use Extcode\CartPaypal\Configuration\CredentialLoaderRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(CredentialLoaderRegistry::class)]
final class CredentialLoaderRegistryTest extends UnitTestCase
{
    #[Test]
    public function returnsEmptyCredentialsWithoutLoaders(): void
    {
        $subject = new CredentialLoaderRegistry([]);

        self::assertSame([], $subject->getCredentials());
    }

    #[Test]
    public function returnsCredentialsOfASingleLoader(): void
    {
        $credentials = [
            'clientId' => 'client-id',
            'clientSecret' => 'client-secret',
            'webhookId' => 'webhook-id',
            'sandbox' => '1',
        ];

        $subject = new CredentialLoaderRegistry([
            $this->createLoader($credentials),
        ]);

        self::assertSame($credentials, $subject->getCredentials());
    }

    #[Test]
    public function ignoresLoadersWithoutCredentials(): void
    {
        $credentials = [
            'clientSecret' => 'client-secret',
        ];

        $subject = new CredentialLoaderRegistry([
            $this->createLoader([]),
            $this->createLoader($credentials),
            $this->createLoader([]),
        ]);

        self::assertSame($credentials, $subject->getCredentials());
    }

    /**
     * The loaders are passed ordered by priority, the loader with the highest
     * priority comes first. Its values win, the following loaders only add
     * values which are missing so far.
     */
    #[Test]
    public function valuesOfEarlierLoadersOverrideValuesOfLaterLoaders(): void
    {
        $subject = new CredentialLoaderRegistry([
            $this->createLoader([
                'clientSecret' => 'secret-from-first-loader',
            ]),
            $this->createLoader([
                'clientId' => 'client-id',
                'clientSecret' => 'secret-from-second-loader',
                'webhookId' => 'webhook-id',
            ]),
        ]);

        self::assertSame(
            [
                'clientId' => 'client-id',
                'clientSecret' => 'secret-from-first-loader',
                'webhookId' => 'webhook-id',
            ],
            $subject->getCredentials()
        );
    }

    #[Test]
    public function acceptsTraversableOfLoaders(): void
    {
        $credentials = [
            'webhookId' => 'webhook-id',
        ];

        $subject = new CredentialLoaderRegistry(
            new \ArrayIterator([$this->createLoader($credentials)])
        );

        self::assertSame($credentials, $subject->getCredentials());
    }

    private function createLoader(array $credentials): CredentialLoaderInterface
    {
        $loader = self::createStub(CredentialLoaderInterface::class);
        $loader->method('getCredentials')->willReturn($credentials);

        return $loader;
    }
}
