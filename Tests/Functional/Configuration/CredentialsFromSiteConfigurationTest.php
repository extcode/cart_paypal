<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional\Configuration;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use Extcode\PaypalTest\Http\PayPalApiMock;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * The site configuration contains credentials, partly read from an
 * environment variable. They replace the credentials of TYPO3_CONF_VARS.
 */
#[CoversNothing]
final class CredentialsFromSiteConfigurationTest extends AbstractPayPalTestCase
{
    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/cart_paypal/Tests/Functional/Fixtures/SitesWithCredentials/' => 'typo3conf/sites',
    ];

    protected function setUp(): void
    {
        putenv('CART_PAYPAL_TEST_CLIENT_SECRET=client-secret-from-env');

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('CART_PAYPAL_TEST_CLIENT_SECRET');

        parent::tearDown();
    }

    #[Test]
    public function requestsTheAccessTokenWithTheCredentialsOfTheSite(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 200, ['id' => self::PAYPAL_ORDER_ID]);

        $this->callApi('createOrder', 'POST');

        $requests = PayPalApiMock::getRequests('/v1/oauth2/token');
        self::assertCount(1, $requests);
        self::assertSame(
            'Basic ' . base64_encode('client-id-of-the-site:client-secret-from-env'),
            $requests[0]->getHeaderLine('Authorization')
        );
    }
}
