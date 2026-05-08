<?php

declare(strict_types=1);

namespace GeorgRinger\RecordWizard\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class ExtensionSettings
{
    /** @var list<string> */
    public readonly array $wizardTables;

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        $raw = (string)($extensionConfiguration->get('record_wizard', 'wizardTables') ?? '');
        $this->wizardTables = array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function tableEnabled(string $table): bool
    {
        return in_array($table, $this->wizardTables, true);
    }
}
