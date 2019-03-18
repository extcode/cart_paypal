<?php

namespace Extcode\CartPaypal\Utility;

use Extcode\Cart\Domain\Repository\CartRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class PaymentUtility
{
    const PAYPAL_API_SANDBOX = 'https://www.sandbox.paypal.com/webscr?';
    const PAYPAL_API_LIVE = 'https://www.paypal.com/webscr?';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * @var \Extcode\Cart\Domain\Model\Cart\Cart
     */
    protected $cart = null;

    /**
     * @var string
     */
    protected $cartSHash = '';

    /**
     * @var string
     */
    protected $cartFHash = '';

    /**
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = GeneralUtility::makeInstance(
            ObjectManager::class
        );
        $this->persistenceManager = $this->objectManager->get(
            PersistenceManager::class
        );
        $this->configurationManager = $this->objectManager->get(
            ConfigurationManager::class
        );

        $this->conf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'CartPaypal'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function handlePayment(array $params): array
    {
        $this->orderItem = $params['orderItem'];

        if ($this->orderItem->getPayment()->getProvider() === 'PAYPAL') {
            $params['providerUsed'] = true;

            $this->cart = $params['cart'];

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($this->cart);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                CartRepository::class
            );
            $cartRepository->add($cart);

            $this->persistenceManager->persistAll();

            $this->cartSHash = $cart->getSHash();
            $this->cartFHash = $cart->getFHash();

            $this->getQuery();

            $paymentQueryString = http_build_query($this->paymentQuery);
            header('Location: ' . $this->getQueryUrl() . $paymentQueryString);
        }

        return [$params];
    }

    /**
     * @return string
     */
    protected function getQueryUrl(): string
    {
        if ($this->conf['sandbox']) {
            return self::PAYPAL_API_SANDBOX;
        }

        return self::PAYPAL_API_LIVE;
    }

    protected function getQuery()
    {
        $this->getQueryFromSettings();
        $this->getQueryFromCart();
        $this->getQueryFromOrder();
    }

    protected function getQueryFromSettings()
    {
        $this->paymentQuery['business'] = $this->conf['business'];
        $this->paymentQuery['test_ipn'] = intval($this->conf['sandbox']);

        $this->paymentQuery['notify_url'] = $this->getNotifyUrl();
        $this->paymentQuery['return'] = $this->getUrl('success', $this->cartSHash);
        $this->paymentQuery['cancel_return'] = $this->getUrl('cancel', $this->cartFHash);

        $this->paymentQuery['cmd'] = '_cart';
        $this->paymentQuery['upload'] = '1';

        $this->paymentQuery['currency_code'] = $this->orderItem->getCurrencyCode();
    }

    protected function getQueryFromCart()
    {
        $this->paymentQuery['invoice'] = $this->cart->getOrderNumber();

        if ($this->conf['sendEachItemToPaypal']) {
            $this->addEachItemsFromCartToQuery();
        } else {
            $this->addEntireCartToQuery();
        }
    }

    protected function getQueryFromOrder()
    {
        $billingAddress = $this->orderItem->getBillingAddress();

        $this->paymentQuery['first_name'] = $billingAddress->getFirstName();
        $this->paymentQuery['last_name'] = $billingAddress->getLastName();
        $this->paymentQuery['email'] = $billingAddress->getEmail();
    }

    protected function addEachItemsFromCartToQuery()
    {
        $shippingGross = $this->cart->getShipping()->getGross();
        $this->paymentQuery['handling_cart'] = number_format(
            $shippingGross,
            2,
            '.',
            ''
        );

        $this->addEachCouponFromCartToQuery();
        $this->addEachProductFromCartToQuery();

        $this->paymentQuery['mc_gross'] = number_format(
            $this->cart->getTotalGross(),
            2,
            '.',
            ''
        );
    }

    protected function addEachCouponFromCartToQuery()
    {
        if ($this->cart->getCoupons()) {
            $discount = 0;
            /**
             * @var \Extcode\Cart\Domain\Model\Cart\CartCoupon $cartCoupon
             */
            foreach ($this->cart->getCoupons() as $cartCoupon) {
                if ($cartCoupon->getIsUseable()) {
                    $discount += $cartCoupon->getDiscount();
                }
            }

            $this->paymentQuery['discount_amount_cart'] = $discount;
        }
    }

    protected function addEachProductFromCartToQuery()
    {
        if ($this->orderItem->getProducts()) {
            $count = 0;
            foreach ($this->orderItem->getProducts() as $productKey => $product) {
                $count += 1;

                $this->paymentQuery['item_name_' . $count] = $product->getTitle();
                $this->paymentQuery['quantity_' . $count] = $product->getCount();
                $this->paymentQuery['amount_' . $count] = number_format(
                    $product->getGross() / $product->getCount(),
                    2,
                    '.',
                    ''
                );
            }
        }
    }

    protected function addEntireCartToQuery()
    {
        $this->paymentQuery['quantity'] = 1;
        $this->paymentQuery['mc_gross'] = number_format(
            $this->cart->getGross() + $this->cart->getServiceGross(),
            2,
            '.',
            ''
        );

        $this->paymentQuery['item_name_1'] = $this->conf['sendEachItemToPaypalTitle'];
        $this->paymentQuery['quantity_1'] = 1;
        $this->paymentQuery['amount_1'] = $this->paymentQuery['mc_gross'];
    }

    /**
     * @return string
     */
    protected function getNotifyUrl(): string
    {
        $arguments = [
            'eID' => 'paypal-payment-api',
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @param string $action
     * @param string $hash
     * @return string
     */
    protected function getUrl(string $action, string $hash): string
    {
        $pid = $this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartpaypal_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->getUriBuilder();

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType($this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setUseCacheHash(false)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @return UriBuilder
     */
    protected function getUriBuilder(): UriBuilder
    {
        $request = $this->objectManager->get(Request::class);
        $request->setRequestURI(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));
        $request->setBaseURI(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'));
        $uriBuilder = $this->objectManager->get(UriBuilder::class);
        $uriBuilder->setRequest($request);
        return $uriBuilder;
    }
}
