<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\EventListener\Order\Payment;

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Cart\Cart as CartCart;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class ProviderRedirect
{
    protected ConfigurationManager $configurationManager;
    protected PersistenceManager $persistenceManager;
    protected TypoScriptService $typoScriptService;
    protected CartRepository $cartRepository;
    protected RequestFactory $requestFactory;
    protected OrderItem $orderItem;
    protected CartCart $cart;
    protected string $cartSHash = '';
    protected string $cartFHash = '';
    protected array $cartPaypalConf = [];
    protected array $cartConf = [];

    public function __construct(
        ConfigurationManager $configurationManager,
        PersistenceManager $persistenceManager,
        TypoScriptService $typoScriptService,
        CartRepository $cartRepository,
        RequestFactory $requestFactory
    ) {
        $this->configurationManager = $configurationManager;
        $this->persistenceManager = $persistenceManager;
        $this->typoScriptService = $typoScriptService;
        $this->cartRepository = $cartRepository;
        $this->requestFactory = $requestFactory;

        $this->cartConf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );

        $this->cartPaypalConf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'CartPaypal'
        );
    }

    public function __invoke(PaymentEvent $event): void
    {
        $this->orderItem = $event->getOrderItem();

        if ($this->orderItem->getPayment()->getProvider() !== 'PAYPAL') {
            return;
        }

        $this->cart = $event->getCart();
        $cart = $this->saveCurrentCartToDatabase();

        $this->cartSHash = $cart->getSHash();
        $this->cartFHash = $cart->getFHash();

        try {
            $paypalOrder = $this->createPayPalOrder();

            // Save PayPal order ID to cart
            $cart->setPaypalOrderId($paypalOrder['id']);
            $this->cartRepository->update($cart);
            $this->persistenceManager->persistAll();

            // Redirect to PayPal checkout
            $approvalUrl = $this->getApprovalUrl($paypalOrder);

            header('Location: ' . $approvalUrl);
            exit;

        } catch (\Exception $e) {
            // Log error and redirect to cart with error message
            error_log('PayPal order creation failed: ' . $e->getMessage());
            header('Location: /cart?payment_error=1');
            exit;
        }
    }

    protected function createPayPalOrder(): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new \Exception('Could not get PayPal access token');
        }

        $orderData = $this->buildOrderData();

        $apiUrl = $this->getApiBaseUrl() . '/v2/checkout/orders';

        $response = $this->requestFactory->request($apiUrl, 'POST', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'Prefer' => 'return=representation'
            ],
            'body' => json_encode($orderData)
        ]);

        if ($response->getStatusCode() !== 201) {
            throw new \Exception('PayPal order creation failed with status: ' . $response->getStatusCode());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function buildOrderData(): array
    {
        $returnUrl = $this->getUrl('success', $this->cartSHash);
        $cancelUrl = $this->getUrl('cancel', $this->cartFHash);

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                $this->buildPurchaseUnit()
            ],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => $this->cartPaypalConf['brandName'] ?? 'Your Store',
                'locale' => 'de-DE',
                'landing_page' => 'LOGIN',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW'
            ]
        ];
    }

    protected function buildPurchaseUnit(): array
    {
        $purchaseUnit = [
            'reference_id' => $this->orderItem->getOrderNumber(),
            'custom_id' => $this->cartSHash,
            'invoice_id' => $this->cart->getOrderNumber(),
            'amount' => [
                'currency_code' => $this->orderItem->getCurrencyCode(),
                'value' => number_format($this->cart->getTotalGross(), 2, '.', ''),
                'breakdown' => $this->buildAmountBreakdown()
            ]
        ];

        // Add items if configured
        if ($this->cartPaypalConf['sendEachItemToPaypal'] ?? true) {
            $purchaseUnit['items'] = $this->buildItems();
        } else {
            // Single item for entire cart
            $purchaseUnit['items'] = [
                [
                    'name' => $this->cartPaypalConf['sendEachItemToPaypalTitle'] ?? 'Order',
                    'description' => 'Your order from ' . ($this->cartPaypalConf['brandName'] ?? 'Our Store'),
                    'quantity' => '1',
                    'unit_amount' => [
                        'currency_code' => $this->orderItem->getCurrencyCode(),
                        'value' => number_format($this->cart->getTotalGross(), 2, '.', '')
                    ],
                    'category' => 'DIGITAL_GOODS'
                ]
            ];
        }

        return $purchaseUnit;
    }

    protected function buildAmountBreakdown(): array
    {
        // Berechne Gesamtsteuer durch Summierung der Produktsteuern
        $totalTax = 0.0;
        foreach ($this->cart->getProducts() as $product) {
            $totalTax += $product->getTax();
        }

        $breakdown = [
            'item_total' => [
                'currency_code' => $this->orderItem->getCurrencyCode(),
                'value' => number_format($this->cart->getGross(), 2, '.', '')
            ],
            'shipping' => [
                'currency_code' => $this->orderItem->getCurrencyCode(),
                'value' => number_format($this->cart->getShipping()->getGross(), 2, '.', '')
            ],
            'tax_total' => [
                'currency_code' => $this->orderItem->getCurrencyCode(),
                'value' => number_format($totalTax, 2, '.', '')
            ],
            'discount' => [
                'currency_code' => $this->orderItem->getCurrencyCode(),
                'value' => number_format($this->calculateTotalDiscount(), 2, '.', '')
            ]
        ];

        return $breakdown;
    }

    protected function buildItems(): array
    {
        $items = [];

        foreach ($this->cart->getProducts() as $product) {
            // Verwende nur verfügbare Methoden
            $title = $product->getTitle();
            $quantity = $product->getQuantity();

            // Für PayPal verwenden wir einfach den Titel als Beschreibung
            // da getDescription() und getShortDescription() nicht verfügbar sind
            $description = $title;

            $items[] = [
                'name' => substr($title, 0, 127),
                'description' => substr($description, 0, 127),
                'sku' => $product->getSku() ?? (string)$product->getId(),
                'unit_amount' => [
                    'currency_code' => $this->orderItem->getCurrencyCode(),
                    'value' => number_format($product->getGross() / $quantity, 2, '.', '')
                ],
                'tax' => [
                    'currency_code' => $this->orderItem->getCurrencyCode(),
                    'value' => number_format($product->getTax() / $quantity, 2, '.', '')
                ],
                'quantity' => (string)$quantity,
                'category' => 'PHYSICAL_GOODS'
            ];
        }

        return $items;
    }

    protected function calculateTotalDiscount(): float
    {
        $discount = 0.0;

        if ($this->cart->getCoupons()) {
            foreach ($this->cart->getCoupons() as $coupon) {
                if ($coupon->getIsUseable()) {
                    $discount += $coupon->getDiscount();
                }
            }
        }

        return $discount;
    }

    protected function getApprovalUrl(array $paypalOrder): string
    {
        foreach ($paypalOrder['links'] as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        throw new \Exception('No approval URL found in PayPal order response');
    }

    protected function getAccessToken(): ?string
    {
        try {
            $authUrl = $this->getApiBaseUrl() . '/v1/oauth2/token';
            $clientId = $this->cartPaypalConf['clientId'] ?? '';
            $clientSecret = $this->cartPaypalConf['clientSecret'] ?? '';

            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception('PayPal client ID or secret not configured');
            }

            $response = $this->requestFactory->request($authUrl, 'POST', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ],
                'body' => 'grant_type=client_credentials'
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $data['access_token'] ?? null;

        } catch (\Exception $e) {
            error_log('PayPal access token error: ' . $e->getMessage());
            return null;
        }
    }

    protected function getApiBaseUrl(): string
    {
        return $this->cartPaypalConf['sandbox'] ?
            'https://api.sandbox.paypal.com' :
            'https://api.paypal.com';
    }

    protected function saveCurrentCartToDatabase(): Cart
    {
        $cart = GeneralUtility::makeInstance(Cart::class);

        $cart->setOrderItem($this->orderItem);
        $cart->setCart($this->cart);
        $cart->setPid((int)$this->cartConf['settings']['order']['pid']);

        $this->cartRepository->add($cart);
        $this->persistenceManager->persistAll();

        return $cart;
    }

    protected function getUrl(string $action, string $hash): string
    {
        $pid = (int)$this->cartConf['settings']['cart']['pid'];
        $arguments = [
            'tx_cartpaypal_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pid);

        return (string)$site->getRouter()->generateUri(
            $pid,
            [
                'type' => (int)$this->cartPaypalConf['redirectTypeNum'],
                '_language' => $GLOBALS['TYPO3_REQUEST']->getAttribute('language')->getLanguageId(),
            ] + $arguments
        );
    }
}
