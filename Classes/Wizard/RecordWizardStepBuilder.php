<?php

declare(strict_types=1);

namespace GeorgRinger\RecordWizard\Wizard;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Backend\Form\FormResultFactory;
use TYPO3\CMS\Backend\Form\NodeFactory;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Wizard\DTO\Step;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Schema\Field\FieldCollection;
use TYPO3\CMS\Core\Schema\Field\FieldTypeInterface;
use TYPO3\CMS\Core\Schema\Struct\WizardStep;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Builds FormEngine-based wizard steps for arbitrary TCA tables.
 * Generic counterpart to PageWizardStepBuilder.
 */
final readonly class RecordWizardStepBuilder
{
    public function __construct(
        private TcaSchemaFactory $tcaSchemaFactory,
        private UriBuilder $uriBuilder,
        private NodeFactory $nodeFactory,
        private FormResultFactory $formResultFactory,
        private FormDataCompiler $formDataCompiler,
    ) {}

    /**
     * @return Step[]
     */
    public function getStepsForRecord(string $tableName, string $typeValue, int $pid, ServerRequestInterface $serverRequest): array
    {
        $steps = [];
        $schema = $this->resolveSchema($tableName, $typeValue);
        $requiredFields = $schema->getFields(fn(FieldTypeInterface $field) => $field->isRequired())->getNames();
        $newId = StringUtility::getUniqueId('NEW');

        foreach ($schema->getWizardSteps() as $wizardStep) {
            $requiredFields = array_diff($requiredFields, $wizardStep->getFields()->getNames());
            $formData = $this->buildFormData($serverRequest, $tableName, $typeValue, $pid, $wizardStep, $newId);
            $steps[] = $this->buildStep($wizardStep, $formData, $tableName);
        }

        // Append fallback step for required fields not covered by any configured step
        if ($requiredFields !== []) {
            $fallbackStep = new WizardStep(
                'requiredFields',
                'Required Fields',
                $this->buildFieldCollection($requiredFields, $schema)
            );
            $formData = $this->buildFormData($serverRequest, $tableName, $typeValue, $pid, $fallbackStep, $newId);
            $steps[] = $this->buildStep($fallbackStep, $formData, $tableName);
        }

        return $steps;
    }

    private function buildStep(WizardStep $wizardStep, array $formData, string $tableName): Step
    {
        $formResult = $this->nodeFactory->create($formData)->render();
        $formResult = $this->formResultFactory->create($formResult);

        return Step::create('@georgringer/record-wizard/record-form-engine-step.js')
            ->withConfigurationData([
                'title' => $this->getLanguageService()->sL($wizardStep->getTitle()),
                'key' => $wizardStep->getIdentifier(),
                'tableName' => $tableName,
                'html' => '<form name="editform">'
                    . $formResult->html
                    . implode(LF, $formResult->hiddenFieldsHtml)
                    . '<input type="submit" hidden></form>',
                'modules' => [
                    JavaScriptModuleInstruction::create('@typo3/backend/form-engine.js')
                        ->invoke('initialize', (string)$this->uriBuilder->buildUriFromRoute('wizard_element_browser')),
                    ...$formResult->javaScriptModules,
                ],
                'labels' => $this->extractFieldLabels($formData),
            ]);
    }

    private function buildFormData(
        ServerRequestInterface $serverRequest,
        string $tableName,
        string $typeValue,
        int $pid,
        WizardStep $wizardStep,
        string $newId
    ): array {
        $fieldList = implode(',', $wizardStep->getFields()->getNames());

        $input = [
            'request' => $serverRequest,
            'tableName' => $tableName,
            'recordTypeValue' => $typeValue,
            'command' => 'new',
            'vanillaUid' => $pid,
            'processedTca' => $GLOBALS['TCA'][$tableName],
            'databaseRow' => ['uid' => $newId],
        ];
        $input['processedTca']['types'][$typeValue]['showitem'] = $fieldList;

        $formData = $this->formDataCompiler->compile($input, GeneralUtility::makeInstance(TcaDatabaseRecord::class));
        $formData['renderType'] = 'listOfFieldsContainer';
        $formData['fieldListToRender'] = $fieldList;
        return $formData;
    }

    private function buildFieldCollection(array $fieldNames, TcaSchema $schema): FieldCollection
    {
        $fields = [];
        foreach ($fieldNames as $fieldName) {
            $fields[$fieldName] = $schema->getField($fieldName);
        }
        return new FieldCollection($fields);
    }

    private function extractFieldLabels(array $formData): array
    {
        $labels = [];
        foreach ($formData['processedTca']['columns'] ?? [] as $fieldName => $fieldConfig) {
            $labels[$fieldName] = $fieldConfig['label'] ?? '';
        }
        return $labels;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    private function resolveSchema(string $tableName, string $typeValue): TcaSchema
    {
        $schema = $this->tcaSchemaFactory->get($tableName);
        if ($schema->hasSubSchema($typeValue)) {
            return $schema->getSubSchema($typeValue);
        }
        // Fall back to first available sub-schema (tables with a single type)
        foreach ($schema->getSubSchemata() as $subSchema) {
            return $subSchema;
        }
        throw new \RuntimeException(
            sprintf('No usable TCA schema for table "%s" type "%s".', $tableName, $typeValue),
            1748000010
        );
    }
}
