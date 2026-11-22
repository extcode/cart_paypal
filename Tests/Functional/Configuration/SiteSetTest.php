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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The site depends on the site set of cart_paypal instead of including its
 * static TypoScript, the settings of the site replace the constants.
 */
#[CoversNothing]
final class SiteSetTest extends AbstractPayPalTestCase
{
    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/cart_paypal/Tests/Functional/Fixtures/SitesWithSet/' => 'typo3conf/sites',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('sys_template');
        $template = $connection->select(['include_static_file', 'constants'], 'sys_template', ['uid' => 1])->fetchAssociative();
        self::assertIsArray($template);
        $connection->update(
            'sys_template',
            [
                'include_static_file' => str_replace(',EXT:cart_paypal/Configuration/TypoScript', '', (string)$template['include_static_file']),
                'constants' => str_replace('plugin.tx_cartpaypal.paymentPid = 3', '', (string)$template['constants']),
                // the TypoScript of the site sets is kept
                'clear' => 0,
            ],
            ['uid' => 1]
        );
    }

    #[Test]
    public function paymentPageOfTheSiteSettingsIsUsed(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withPageId(1)
                ->withQueryParameters(['type' => 4711])
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertStringStartsWith('http://localhost/payment?', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function endpointsAreAvailableOnThePageTypeOfTheSiteSet(): void
    {
        $response = $this->callApi('createOrder');

        self::assertSame(405, $response->getStatusCode());
    }

    #[Test]
    public function brandNameOfTheSiteSettingsIsSentToPayPal(): void
    {
        PayPalApiMock::addAccessToken();
        PayPalApiMock::addResponse('POST', '/v2/checkout/orders', 200, ['id' => self::PAYPAL_ORDER_ID]);

        $this->callApi('createOrder', 'POST');

        $order = json_decode((string)PayPalApiMock::getRequests('/v2/checkout/orders')[0]->getBody(), true);
        self::assertSame('Shop from site settings', $order['payment_source']['paypal']['experience_context']['brand_name']);
    }
}
