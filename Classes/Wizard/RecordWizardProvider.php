<?php

declare(strict_types=1);

namespace GeorgRinger\RecordWizard\Wizard;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Wizard\DTO\Configuration;
use TYPO3\CMS\Backend\Wizard\DTO\Finisher;
use TYPO3\CMS\Backend\Wizard\DTO\Step;
use TYPO3\CMS\Backend\Wizard\DTO\SubmissionResult;
use TYPO3\CMS\Backend\Wizard\WizardProviderInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Schema\SchemaLabelResolver;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Generic wizard provider for creating TCA records step by step.
 * Registered under the identifier "record_wizard" for use with
 * ?mode=record_wizard on the wizard_config / wizard_submit AJAX endpoints.
 */
#[AsTaggedItem(index: 'record_wizard')]
final class RecordWizardProvider implements WizardProviderInterface
{
    public function __construct(
        private readonly UriBuilder $uriBuilder,
        private readonly RecordWizardStepBuilder $stepBuilder,
        private readonly TcaSchemaFactory $tcaSchemaFactory,
        private readonly SchemaLabelResolver $schemaLabelResolver,
    ) {}

    public function getConfiguration(ServerRequestInterface $serverRequest): Configuration
    {
        $data = $serverRequest->getQueryParams()['data'] ?? [];
        $tableName = (string)($data['tableName'] ?? '');
        $typeValue = $data['typeValue'] ?? null;
        $pid = (int)($data['pid'] ?? 0);

        if ($tableName === '' || !$this->tcaSchemaFactory->has($tableName)) {
            return Configuration::create([]);
        }

        // No typeValue supplied — offer type selection if the table has multiple types with wizard steps
        if ($typeValue === null || $typeValue === '') {
            $types = $this->getTypesWithWizardSteps($tableName);

            if (count($types) > 1) {
                return Configuration::create([
                    Step::create('@georgringer/record-wizard/record-type-step.js')
                        ->withConfigurationData([
                            'key' => 'type',
                            'title' => $this->getLanguageService()->sL('LLL:EXT:record_wizard/Resources/Private/Language/locallang.xlf:wizard.selectType'),
                            'types' => array_values($types),
                        ]),
                ]);
            }

            // Single type: use it directly
            $typeValue = (string)(array_key_first($types) ?? '0');
        }

        $steps = $this->stepBuilder->getStepsForRecord($tableName, (string)$typeValue, $pid, $serverRequest);
        return Configuration::create($steps);
    }

    public function handleSubmit(ServerRequestInterface $serverRequest): SubmissionResult
    {
        $params = $serverRequest->getParsedBody();

        try {
            $tableName = (string)($params['tableName'] ?? throw new \InvalidArgumentException('No table name submitted.', 1748000001));
            $pid = (int)($params['pid'] ?? throw new \InvalidArgumentException('No PID submitted.', 1748000002));
            $typeValue = (string)($params['typeValue'] ?? '0');

            if (!$this->tcaSchemaFactory->has($tableName)) {
                throw new \InvalidArgumentException(sprintf('Unknown table "%s".', $tableName), 1748000004);
            }

            $recordData = $params['data'][$tableName] ?? throw new \InvalidArgumentException('No record data submitted.', 1748000003);
            $placeholder = (string)key($recordData);

            $dataMap = [$tableName => $recordData];
            $dataMap[$tableName][$placeholder]['pid'] = $pid;

            $typeField = $GLOBALS['TCA'][$tableName]['ctrl']['type'] ?? '';
            if ($typeField !== '' && !isset($dataMap[$tableName][$placeholder][$typeField])) {
                $dataMap[$tableName][$placeholder][$typeField] = $typeValue;
            }
        } catch (\InvalidArgumentException $e) {
            return SubmissionResult::createErrorResult([$e->getMessage()]);
        }

        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($dataMap, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            return SubmissionResult::createErrorResult($dataHandler->errorLog);
        }

        $newUid = $dataHandler->substNEWwithIDs[$placeholder] ?? null;

        $redirectUrl = (string)$this->uriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [$tableName => [$newUid => 'edit']],
            'returnUrl' => (string)$this->uriBuilder->buildUriFromRoute('web_list', ['id' => $pid]),
        ]);

        $lang = $this->getLanguageService();
        return SubmissionResult::createSuccessResult(
            Finisher::createRedirectFinisher(
                $redirectUrl,
                $lang->sL('LLL:EXT:record_wizard/Resources/Private/Language/locallang.xlf:wizard.success.title'),
                $lang->sL('LLL:EXT:record_wizard/Resources/Private/Language/locallang.xlf:wizard.success.message'),
            )
        );
    }

    /**
     * Returns type information for all TCA types that have wizardSteps configured.
     *
     * @return array<string, array{value: string, label: string, icon: string}>
     */
    private function getTypesWithWizardSteps(string $tableName): array
    {
        $schema = $this->tcaSchemaFactory->get($tableName);
        $types = [];

        foreach ($schema->getSubSchemata() as $typeValue => $subSchema) {
            if ($subSchema->getWizardSteps() !== []) {
                $types[(string)$typeValue] = $this->buildTypeInfo($schema, (string)$typeValue);
            }
        }

        return $types;
    }

    private function buildTypeInfo(TcaSchema $schema, string $typeValue): array
    {
        $ctrlConfig = $schema->getRawConfiguration();
        $icon = $ctrlConfig['typeicon_classes'][$typeValue]
            ?? $ctrlConfig['typeicon_classes']['default']
            ?? $ctrlConfig['typeicon_classes']['0']
            ?? 'content-text';

        $label = $typeValue;
        $typeFieldName = $ctrlConfig['type'] ?? '';
        if ($typeFieldName !== '') {
            $rawLabel = $this->schemaLabelResolver->getLabelForFieldValue($schema->getName(), $typeFieldName, $typeValue);
            $label = $this->getLanguageService()->sL($rawLabel) ?: $rawLabel ?: $typeValue;
        }

        return [
            'value' => $typeValue,
            'label' => $label,
            'icon' => $icon,
        ];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
