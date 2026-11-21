<?php

declare(strict_types=1);

use Extcode\CartPaypal\Event\Order\CancelEvent;
use Extcode\CartPaypal\Event\Order\FinishEvent;
use Extcode\PaypalTest\Controller\PaymentEventController;
use Extcode\PaypalTest\EventListener\EventRecorder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->set(PaymentEventController::class)
        ->public();

    $services->set(EventRecorder::class)
        ->tag('event.listener', [
            'identifier' => 'paypal-test--order--finish--event-recorder',
            'event' => FinishEvent::class,
        ])
        ->tag('event.listener', [
            'identifier' => 'paypal-test--order--cancel--event-recorder',
            'event' => CancelEvent::class,
        ]);
};
