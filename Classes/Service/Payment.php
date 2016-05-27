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

use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Payment Service
 *
 * @package cart_paypal
 * @author Daniel Lorenz <ext.cart.paypal@extco.de>
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
     * Cart Settings
     *
     * @var array
     */
    protected $cartSettings = [];

    /**
     * Cart Paypal Settings
     *
     * @var array
     */
    protected $cartPaypalSettings = [];

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
     * Intitialize
     *
     * @return void
     */
    public function __construct()
    {
        $this->objectManager =
            \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');

        $this->configurationManager =
            $this->objectManager->get('TYPO3\\CMS\\Extbase\\Configuration\\ConfigurationManager');

        $this->cartSettings =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK
            );

        $this->cartPaypalSettings =
            $this->configurationManager->getConfiguration(
                \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK,
                'CartPaypal'
            );
    }

    /**
     * @param $params
     */
    public function handlePayment($params)
    {
        $this->orderItem = $params['orderItem'];

        if ($this->orderItem->getPayment()->getProvider() == 'PAYPAL') {
            $this->cart = $params['cart'];

            $cart = $this->objectManager->get('Extcode\\Cart\\Domain\\Model\\Cart');
            $cart->setOrderItem($this->orderItem);
            $cart->setCart($this->cart);

            $cartRepository = $this->objectManager->get('Extcode\\Cart\\Domain\\Repository\\CartRepository');
            $cartRepository->add($cart);

            $this->persistenceManager->persistAll();

            $this->getQueryUrl();
            $this->getQuery();

            $paymentQueryString = http_build_query($this->paymentQuery);
            header('Location: ' . $this->paymentQueryUrl . $paymentQueryString);
        }
    }

    /**
     * Returns Query Url
     *
     * @return void
     */
    protected function getQueryUrl()
    {
        if ($this->cartPaypalSettings['settings']['sandbox']) {
            $this->paymentQueryUrl = 'https://www.sandbox.paypal.com/webscr?';
        } else {
            $this->paymentQueryUrl = 'https://www.paypal.com/webscr?';
        }
    }

    /**
     * Get Query
     *
     * @return void
     */
    protected function getQuery()
    {
        $this->getQueryFromSettings();
        $this->getQueryFromCart();
        $this->getQueryFromOrder();
    }

    /**
     * Get Query From Setting
     *
     * @return void
     */
    protected function getQueryFromSettings()
    {
        $this->paymentQuery['business']      = $this->cartPaypalSettings['settings']['business'];
        $this->paymentQuery['test_ipn']      = intval($this->cartPaypalSettings['settings']['sandbox']);

        $this->paymentQuery['notify_url']    = $this->cartPaypalSettings['settings']['notify_url'];
        $this->paymentQuery['return']        = $this->cartPaypalSettings['settings']['return_url'];
        $this->paymentQuery['cancel_return'] = $this->cartPaypalSettings['settings']['cancel_url'];

        $this->paymentQuery['cmd']           = '_cart';
        $this->paymentQuery['upload']        = '1';

        $this->paymentQuery['currency_code'] = $this->cartPaypalSettings['settings']['currency_code'];
    }

    /**
     * Get Query From Cart
     *
     * @return void
     */
    protected function getQueryFromCart()
    {
        $this->paymentQuery['invoice'] = $this->cart->getOrderNumber();

        if ($this->cartPaypalSettings['settings']['sendEachItemToPaypal']) {
            $this->addEachItemsFromCartToQuery();
        } else {
            $this->addEntireCartToQuery();
        }
    }

    /**
     * Get Query From Order
     *
     * @return void
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
     * @return void
     */
    protected function addEachItemsFromCartToQuery()
    {
        $shippingGross = $this->cart->getShipping()->getGross();
        $this->paymentQuery['handling_cart'] = number_format($shippingGross, 2);

        $this->addEachCouponFromCartToQuery();
        $this->addEachProductFromCartToQuery();

        $this->paymentQuery['mc_gross'] = number_format($this->cart->getTotalGross(), 2);
    }

    /**
     * @retrun void
     */
    protected function addEachCouponFromCartToQuery()
    {
        if ($this->cart->getCoupons()) {
            $count = 0;
            /**
             * @var $cartCoupon \Extcode\Cart\Domain\Model\Cart\CartCoupon
             */
            foreach ($this->cart->getCoupons() as $cartCoupon) {
                if ($cartCoupon->getIsUseable()) {
                    $this->paymentQuery['discount_amount_' . $count] = $cartCoupon->getDiscount();
                    $count++;
                }
            }
        }
    }

    /**
     * @return void
     */
    protected function addEachProductFromCartToQuery()
    {
        if ($this->orderItem->getProducts()) {
            $count = 0;
            foreach ($this->orderItem->getProducts() as $productKey => $product) {
                $count += 1;

                $this->paymentQuery['item_name_' . $count] = $product->getTitle();
                $this->paymentQuery['quantity_' . $count] = $product->getCount();
                $this->paymentQuery['amount_' . $count] = number_format($product->getGross() / $product->getCount(), 2);
            }
        }
    }

    /**
     * @return void
     */
    protected function addEntireCartToQuery()
    {
        $this->paymentQuery['quantity'] = 1;
        $this->paymentQuery['mc_gross'] = number_format($this->cart->getGross() + $this->cart->getServiceGross(), 2);

        $this->paymentQuery['item_name_1'] = $this->cartPaypalSettings['settings']['sendEachItemToPaypalTitle'];
        $this->paymentQuery['quantity_1'] = 1;
        $this->paymentQuery['amount_1'] = $this->paymentQuery['mc_gross'];
    }
}
