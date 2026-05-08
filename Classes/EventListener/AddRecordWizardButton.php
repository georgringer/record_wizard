<?php

declare(strict_types=1);

namespace GeorgRinger\RecordWizard\EventListener;

use GeorgRinger\RecordWizard\Configuration\ExtensionSettings;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListHeaderColumnsEvent;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * Injects a wizard button into the _CONTROL_ column header of configured tables.
 * Register tables via $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['record_wizard']['wizardTables']
 * as a comma-separated list (e.g. from ext_localconf.php of any extension).
 */
final class AddRecordWizardButton
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly ExtensionSettings $extensionSettings,
    ) {}

    public function __invoke(ModifyRecordListHeaderColumnsEvent $event): void
    {
        $table = $event->getTable();
        if (!$this->extensionSettings->tableEnabled($table)) {
            return;
        }

        $this->pageRenderer->loadJavaScriptModule('@georgringer/record-wizard/record-wizard-button.js');

        $pid = $event->getRecordList()->id;
        $config = htmlspecialchars(json_encode([
            'tableName' => $table,
            'pid' => $pid,
        ], JSON_THROW_ON_ERROR));

        $columns = $event->getColumns();
        if (array_key_exists('_CONTROL_', $columns)) {
            $columns['_CONTROL_'] = sprintf(
                '<georgringer-record-wizard-button configuration="%s"></georgringer-record-wizard-button>',
                $config
            );
            $event->setColumns($columns);
        }
    }

}
