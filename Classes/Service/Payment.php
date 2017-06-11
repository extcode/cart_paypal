<?php

namespace Extcode\CartPaypal\Service;

/**
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Payment Service
 *
 * @author Daniel Lorenz <ext.cart@extco.de>
 */
class Payment
{
    /**
     * Object Manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * Persistence Manager
     *
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     * @inject
     */
    protected $persistenceManager;

    /**
     * Configuration Manager
     *
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * Cart Repository
     *
     * @var \Extcode\Cart\Domain\Repository\CartRepository
     */
    protected $cartRepository;

    /**
     * Cart Settings
     *
     * @var array
     */
    protected $cartConf = [];

    /**
     * Cart Paypal Settings
     *
     * @var array
     */
    protected $cartPaypalConf = [];

    /**
     * Payment Query Url
     *
     * @var string
     */
    protected $paymentQueryUrl = '';

    /**
     * Payment Query
     *
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * Order Item
     *
     * @var \Extcode\Cart\Domain\Model\Order\Item
     */
    protected $orderItem = null;

    /**
     * Cart
     *
     * @var \Extcode\Cart\Domain\Model\Cart\Cart
     */
    protected $cart = null;

    /**
     * CartFHash
     *
     * @var string
     */
    protected $cartFHash = '';

    /**
     * Intitialize
     */
    public function __construct()
    {
        $this->objectManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Extbase\Object\ObjectManager::class
        );

        $this->configurationManager = $this->objectManager->get(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class
        );

        $this->cartConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'Cart'
            );

        $this->cartPaypalConf =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartPaypal'
            );
    }

    /**
     * Handle Payment - Signal Slot Function
     *
     * @param array $params
     *
     * @return array
     */
    public function handlePayment($params)
    {
        $this->orderItem = $params['orderItem'];

        if ($this->orderItem->getPayment()->getProvider() == 'PAYPAL') {
            $params['providerUsed'] = true;

            $this->cart = $params['cart'];

            $cart = $this->objectManager->get(
                \Extcode\Cart\Domain\Model\Cart::class
            );
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($this->cart);
            $cart->setPid($this->cartConf['settings']['order']['pid']);

            $cartRepository = $this->objectManager->get(
                \Extcode\Cart\Domain\Repository\CartRepository::class
            );
            $cartRepository->add($cart);

            $this->persistenceManager->persistAll();

            $this->cartFHash = $cart->getFHash();

            $this->getQueryUrl();
            $this->getQuery();

            $paymentQueryString = http_build_query($this->paymentQuery);
            header('Location: ' . $this->paymentQueryUrl . $paymentQueryString);
        }

        return [$params];
    }

    /**
     * Returns Query Url
     */
    protected function getQueryUrl()
    {
        if ($this->cartPaypalConf['settings']['sandbox']) {
            $this->paymentQueryUrl = $this->cartPaypalConf['websrcUrl']['sandbox'];
        } else {
            $this->paymentQueryUrl = $this->cartPaypalConf['websrcUrl']['live'];
        }
    }

    /**
     * Get Query
     */
    protected function getQuery()
    {
        $this->getQueryFromSettings();
        $this->getQueryFromCart();
        $this->getQueryFromOrder();
    }

    /**
     * Get Query From Setting
     */
    protected function getQueryFromSettings()
    {
        $this->paymentQuery['business']      = $this->cartPaypalConf['settings']['business'];
        $this->paymentQuery['test_ipn']      = intval($this->cartPaypalConf['settings']['sandbox']);

        $this->paymentQuery['notify_url']    = $this->cartPaypalConf['settings']['notify_url'];
        $this->paymentQuery['return']        = $this->cartPaypalConf['settings']['return_url'];
        $cancelUrl = $this->cartPaypalConf['settings']['cancel_url'];
        if ($cancelUrl) {
            $controllerParam = '&tx_cart_cart[controller]=Order';
            $orderParam = '&tx_cart_cart[order]=' . $this->orderItem->getUid();

            $actionFParam = '&tx_cart_cart[action]=paymentCancel';
            $hashFParam = '&tx_cart_cart[hash]=' . $this->cartFHash;
            $fParams = $controllerParam . $actionFParam . $orderParam . $hashFParam;

            $cancelUrl = $cancelUrl . $fParams;
        }
        $this->paymentQuery['cancel_return']        = $cancelUrl;

        $this->paymentQuery['cmd']           = '_cart';
        $this->paymentQuery['upload']        = '1';

        $this->paymentQuery['currency_code'] = $this->cartPaypalConf['settings']['currency_code'];
    }

    /**
     * Get Query From Cart
     */
    protected function getQueryFromCart()
    {
        $this->paymentQuery['invoice'] = $this->cart->getOrderNumber();

        if ($this->cartPaypalConf['settings']['sendEachItemToPaypal']) {
            $this->addEachItemsFromCartToQuery();
        } else {
            $this->addEntireCartToQuery();
        }
    }

    /**
     * Get Query From Order
     */
    protected function getQueryFromOrder()
    {
        /** @var \Extcode\Cart\Domain\Model\Order\Address $billingAddress */
        $billingAddress = $this->orderItem->getBillingAddress();

        $this->paymentQuery['first_name'] = $billingAddress->getFirstName();
        $this->paymentQuery['last_name']  = $billingAddress->getLastName();
        $this->paymentQuery['email']      = $billingAddress->getEmail();
    }

    /**
     */
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

    /**
     * @retrun void
     */
    protected function addEachCouponFromCartToQuery()
    {
        if ($this->cart->getCoupons()) {
            $discount = 0;
            /**
             * @var $cartCoupon \Extcode\Cart\Domain\Model\Cart\CartCoupon
             */
            foreach ($this->cart->getCoupons() as $cartCoupon) {
                if ($cartCoupon->getIsUseable()) {
                    $discount += $cartCoupon->getDiscount();
                }
            }

            $this->paymentQuery['discount_amount_cart'] = $discount;
        }
    }

    /**
     */
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

    /**
     */
    protected function addEntireCartToQuery()
    {
        $this->paymentQuery['quantity'] = 1;
        $this->paymentQuery['mc_gross'] = number_format(
            $this->cart->getGross() + $this->cart->getServiceGross(),
            2,
            '.',
            ''
        );

        $this->paymentQuery['item_name_1'] = $this->cartPaypalConf['settings']['sendEachItemToPaypalTitle'];
        $this->paymentQuery['quantity_1'] = 1;
        $this->paymentQuery['amount_1'] = $this->paymentQuery['mc_gross'];
    }
}
