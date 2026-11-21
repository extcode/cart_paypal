<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Configuration;

use Extcode\Cart\Event\Order\PaymentEvent;
use Extcode\Cart\EventListener\Order\Finish\Email;
use Extcode\CartPaypal\Event\Order\FinishEvent;
use Extcode\CartPaypal\EventListener\Order\Payment\ClearCart;
use Extcode\CartPaypal\EventListener\Order\Payment\ProviderRedirect;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator, ContainerBuilder $container): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services
        ->instanceof(CredentialLoaderInterface::class)
        ->tag('cartPaypal.credentialLoader');

    $services->load(
        'Extcode\\CartPaypal\\',
        '../Classes/*'
    );

    $services->set(CredentialLoaderRegistry::class)
        ->arg('$credentialLoaders', tagged_iterator('cartPaypal.credentialLoader'));

    $services->set(ClearCart::class)
        ->tag('event.listener', [
            'identifier' => 'cart-paypal--order--payment--clear-cart',
            'event' => PaymentEvent::class,
            'before' => 'cart-paypal--order--payment--provider-redirect',
        ]);

    $services->set(ProviderRedirect::class)
        ->tag('event.listener', [
            'identifier' => 'cart-paypal--order--payment--provider-redirect',
            'event' => PaymentEvent::class,
        ]);

    $services->set('cart-paypal--event-listener--order--finish--email', Email::class)
        ->tag('event.listener', [
            'identifier' => 'cart-paypal--order--finish--email',
            'event' => FinishEvent::class,
        ]);
};
