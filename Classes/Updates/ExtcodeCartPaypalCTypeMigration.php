<?php

declare(strict_types=1);

namespace Extcode\CartPaypal\Updates;

use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\AbstractListTypeToCTypeUpdate;

#[UpgradeWizard('extcodeCartPaypalCTypeMigration')]
final class ExtcodeCartPaypalCTypeMigration extends AbstractListTypeToCTypeUpdate
{
    public function getTitle(): string
    {
        return 'Migrate "Extcode CartPaypal" plugins to content elements.';
    }

    public function getDescription(): string
    {
        return 'The "Extcode CartPaypal" plugins are now registered as content elements. Existing records and backend user permissions will be migrated.';
    }

    /**
     * Liefert das Mapping von list_type → CType
     *
     * @return array<string, string>
     */
    protected function getListTypeToCTypeMapping(): array
    {
        return [
            // list_type => CType
            'cartpaypal_payment' => 'cartpaypal_payment',
        ];
    }
}
