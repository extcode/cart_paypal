<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Tests\Functional\Checkout\Payment;

/*
 * This file is part of the package extcode/cart-paypal.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\CartPaypal\Checkout\Payment\PaymentStatusUpdater;
use Extcode\CartPaypal\Tests\Functional\AbstractPayPalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

#[CoversClass(PaymentStatusUpdater::class)]
class PaymentStatusUpdaterTest extends AbstractPayPalTestCase
{
    private PaymentStatusUpdater $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $subject = $this->get(PaymentStatusUpdater::class);
        self::assertInstanceOf(PaymentStatusUpdater::class, $subject);
        $this->subject = $subject;
    }

    #[Test]
    public function changeChangesTheStatus(): void
    {
        $payment = $this->getPayment();

        self::assertTrue($this->subject->change($payment, 'paid'));
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertTrue($payment->isPaid());
    }

    #[Test]
    public function changeTriesAgainWithTheStoredStatusIfThePaymentWasChangedInTheMeantime(): void
    {
        $payment = $this->getPayment();
        // e.g. by a webhook of PayPal while the customer returns to the shop
        $this->setPaymentStatus('pending');

        self::assertTrue($this->subject->change($payment, 'paid'));
        self::assertSame('paid', $this->getPaymentStatus());
        self::assertTrue($payment->isPaid());
    }

    #[Test]
    public function changeReturnsFalseIfThePaymentGotTheStatusInTheMeantime(): void
    {
        $payment = $this->getPayment();
        $this->setPaymentStatus('paid');

        self::assertFalse($this->subject->change($payment, 'paid'), 'Only the request which changed the status may dispatch the events.');
        self::assertSame('paid', $this->getPaymentStatus());
    }

    private function getPayment(): Payment
    {
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
        $persistenceManager->clearState();
        $payment = $persistenceManager->getObjectByIdentifier(1, Payment::class);
        self::assertInstanceOf(Payment::class, $payment);
        self::assertTrue($payment->isOpen());

        return $payment;
    }
}
