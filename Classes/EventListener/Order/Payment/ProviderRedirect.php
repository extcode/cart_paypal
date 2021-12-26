<?php
declare(strict_types=1);
namespace Extcode\CartPaypal\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Cart\Cart as CartCart;
use Extcode\Cart\Domain\Model\Cart\CartCoupon;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Core\Log\LogManager;

class ProviderRedirect
{
    const PAYPAL_API_SANDBOX = 'https://www.sandbox.paypal.com/cgi-bin/webscr?';
    const PAYPAL_API_LIVE = 'https://www.paypal.com/cgi-bin/webscr?';

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var array
     */
    protected $paymentQuery = [];

    /**
     * @var OrderItem
     */
    protected $orderItem;

    /**
     * @var CartCart
     */
    protected $cart;

    /**
     * @var string
     */
    protected $cartSHash = '';

    /**
     * @var string
     */
    protected $cartFHash = '';

    /**
     * @var array
     */
    protected $cartPaypalConf = [];

    /**
     * @var array
     */
    protected $cartConf = [];
    
    /**
     * @var logger
     */
    protected $logger;

    public function __construct(
        ConfigurationManager $configurationManager,
        PersistenceManager $persistenceManager,
        TypoScriptService $typoScriptService,
        UriBuilder $uriBuilder,
        CartRepository $cartRepository
    ) {
        $this->configurationManager = $configurationManager;
        $this->persistenceManager = $persistenceManager;
        $this->typoScriptService = $typoScriptService;
        $this->uriBuilder = $uriBuilder;
        $this->cartRepository = $cartRepository;

        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

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
        if ($this->orderItem->getPayment()->getProvider() !== 'PAYPAL' &&
            $this->orderItem->getPayment()->getProvider() !== 'PAYPAL_CREDIT_CARD') {
            return;
        }

        $this->cart = $event->getCart();

        $cart = $this->saveCurrentCartToDatabase();

        $this->cartSHash = $cart->getSHash();
        $this->cartFHash = $cart->getFHash();

        $this->getQuery();

        $paymentQueryString = http_build_query($this->paymentQuery);
        header('Location: ' . $this->getQueryUrl() . $paymentQueryString);

        $event->setPropagationStopped(true);
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

    protected function getQueryUrl(): string
    {
        if ($this->cartPaypalConf['sandbox']) {
            return self::PAYPAL_API_SANDBOX;
        }

        return self::PAYPAL_API_LIVE;
    }

    protected function getQuery(): void
    {
        $this->getQueryFromSettings();
        $this->getQueryFromCart();
        $this->getQueryFromOrder();
    }

    protected function getQueryFromSettings(): void
    {
        $this->paymentQuery['business'] = $this->cartPaypalConf['business'];
        $this->paymentQuery['test_ipn'] = intval($this->cartPaypalConf['sandbox']);

        $this->paymentQuery['custom'] = $this->cartSHash;
        $this->paymentQuery['notify_url'] = $this->getUrl('notify', $this->cartSHash);
        $this->paymentQuery['return'] = $this->getUrl('success', $this->cartSHash);
        $this->paymentQuery['cancel_return'] = $this->getUrl('cancel', $this->cartFHash);

        $this->paymentQuery['cmd'] = '_cart';
        $this->paymentQuery['upload'] = '1';

        $this->paymentQuery['currency_code'] = $this->orderItem->getCurrencyCode();

        if (strpos($this->orderItem->getPayment()->getProvider(), 'PAYPAL_CREDIT_CARD') === 0)
           $this->paymentQuery['landing_page'] = 'billing';
        
        $selectedCountry = $this->orderItem->getBillingAddress()->getCountry();
        foreach($this->cartPaypalConf['regionMappings'] as $locale => $countryMapping)
        {
            if (strpos($selectedCountry, $locale) === 0)
            {
                $localeAndCountryArray = explode(',', $countryMapping);
                
                $this->paymentQuery['lc'] = $localeAndCountryArray[1];
                $this->paymentQuery['country_code'] = $localeAndCountryArray[0];
                $this->logger->info("selected locale: " . $locale . " --> lc: " . $localeAndCountryArray[1] . ", country_code: " . $localeAndCountryArray[0]);
            }
        }
        
        if ($this->cartPaypalConf['logo'])
        {
            $site = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Site\SiteFinder::class)->getSiteByPageId((int)$this->cartConf['settings']['order']['pid']);
            $logoUrl =  $site->getBase() . "/" . $this->cartPaypalConf['logo'];
            $this->logger->info($logoUrl);
            $this->paymentQuery['image_url'] = $logoUrl;
        }
        
        if ($this->cartPaypalConf['displayShippingAddress'])
            $this->paymentQuery['no_shipping'] = '0';
        else
            $this->paymentQuery['no_shipping'] = '1';
    }

    protected function getQueryFromCart(): void
    {
        $this->paymentQuery['invoice'] = $this->cart->getOrderNumber();

        if ($this->cartPaypalConf['sendEachItemToPaypal']) {
            $this->addEachItemsFromCartToQuery();
        } else {
            $this->addEntireCartToQuery();
        }
    }

    protected function getQueryFromOrder(): void
    {
        $billingAddress = $this->orderItem->getBillingAddress();

        $this->paymentQuery['first_name'] = $billingAddress->getFirstName();
        $this->paymentQuery['last_name'] = $billingAddress->getLastName();
        $this->paymentQuery['email'] = $billingAddress->getEmail();
    }

    protected function addEachItemsFromCartToQuery(): void
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

    protected function addEachCouponFromCartToQuery(): void
    {
        if ($this->cart->getCoupons()) {
            $discount = 0;
            /**
             * @var CartCoupon $cartCoupon
             */
            foreach ($this->cart->getCoupons() as $cartCoupon) {
                if ($cartCoupon->getIsUseable()) {
                    $discount += $cartCoupon->getDiscount();
                }
            }

            $this->paymentQuery['discount_amount_cart'] = $discount;
        }
    }

    protected function addEachProductFromCartToQuery(): void
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

    protected function addEntireCartToQuery(): void
    {
        $this->paymentQuery['quantity'] = 1;
        $this->paymentQuery['mc_gross'] = number_format(
            $this->cart->getGross() + $this->cart->getServiceGross(),
            2,
            '.',
            ''
        );

        $this->paymentQuery['item_name_1'] = $this->cartPaypalConf['sendEachItemToPaypalTitle'];
        $this->paymentQuery['quantity_1'] = 1;
        $this->paymentQuery['amount_1'] = $this->paymentQuery['mc_gross'];
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

        return $this->uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType((int)$this->cartPaypalConf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setArguments($arguments)
            ->build();
    }
}
