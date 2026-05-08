/*
 * Record Wizard - main wizard web component.
 * Wraps <typo3-backend-wizard> and loads steps dynamically via wizard_config.
 */
import { LitElement, html } from 'lit';
import { loadDynamicSteps } from '@typo3/backend/wizard/helper/dynamic-steps-loader.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import labels from '~labels/record_wizard.messages';

/**
 * Submission service that posts collected form data to wizard_submit?mode=record_wizard.
 * After success it dispatches a custom event so the record list can refresh.
 */
class RecordWizardSubmissionService {
  constructor(context) {
    this.context = context;
  }

  async execute() {
    const { fields, ...storeData } = this.context.getDataStore();
    const payload = Object.assign(
      {},
      storeData,
      ...Object.values(fields ?? {})
    );

    const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.wizard_submit)
      .withQueryArguments({ mode: 'record_wizard' })
      .post(payload);

    const result = await response.resolve();

    // Signal the record list to reload (analogue to pagetree:refresh for pages)
    document.dispatchEvent(new CustomEvent('typo3:recordlist:refresh'));

    return result;
  }
}

/**
 * <georgringer-record-wizard configuration='{"tableName":"...","pid":1}'>
 *
 * Renders a <typo3-backend-wizard> after fetching step configuration from the server.
 * When the first step is a type selection step (key "type"), listens for
 * wizard-before-next-step to reload the FormEngine steps for the selected type.
 */
class RecordWizard extends LitElement {
  static properties = {
    configuration: { type: Object },
    _steps: { type: Array, state: true },
    _submissionService: { type: Object, state: true },
    _error: { type: String, state: true },
  };

  constructor() {
    super();
    this.configuration = null;
    this._steps = [];
    this._submissionService = null;
    this._error = null;
    this._typeStep = null;
    this._context = null;
  }

  // Disable shadow DOM so FormEngine styles apply
  createRenderRoot() {
    return this;
  }

  get _wizard() {
    return this.querySelector('typo3-backend-wizard');
  }

  firstUpdated() {
    this._init();
  }

  async _init() {
    if (!this.configuration) {
      this._error = 'Missing configuration.';
      return;
    }

    const store = { ...this.configuration };

    const context = {
      wizard: null,
      getStoreData: (key) => store[key],
      setStoreData: (key, val) => { store[key] = val; },
      clearStoreData: (key) => { delete store[key]; },
      getDataStore: () => ({ ...store }),
    };
    this._context = context;

    try {
      await import('@typo3/backend/wizard/wizard.js');

      this._submissionService = new RecordWizardSubmissionService(context);

      const steps = await loadDynamicSteps('record_wizard', context);

      // If the first step is a type selection step, keep it as a fixed step
      if (steps.length > 0 && steps[0].key === 'type') {
        this._typeStep = steps[0];
      }

      this._steps = steps;

      await this.updateComplete;
      const wizardEl = this._wizard;
      context.wizard = wizardEl;

      for (const step of steps) {
        if (step.context) {
          step.context.wizard = wizardEl;
        }
      }
    } catch (err) {
      this._error = String(err?.message ?? err);
    }
  }

  async _handleBeforeNextStep(event) {
    if (event.detail.currentStepKey !== 'type') {
      return;
    }

    const context = this._context;
    const typeStep = this._typeStep;

    event.detail.result = loadDynamicSteps('record_wizard', context).then(async (newSteps) => {
      this._steps = typeStep ? [typeStep, ...newSteps] : newSteps;

      await this.updateComplete;

      // Update wizard reference in newly created step instances
      const wizardEl = this._wizard;
      for (const step of newSteps) {
        if (step.context) {
          step.context.wizard = wizardEl;
        }
      }
    });
  }

  render() {
    if (this._error) {
      return html`<div class="alert alert-danger">${this._error}</div>`;
    }

    if (!this._steps.length) {
      return html`<div class="text-center p-3">
        <typo3-backend-spinner></typo3-backend-spinner>
      </div>`;
    }

    return html`
      <typo3-backend-wizard
        .steps=${this._steps}
        .submissionService=${this._submissionService}
        confirm-button-label=${labels.get('wizard.confirmButton')}
        @wizard-before-next-step=${(e) => this._handleBeforeNextStep(e)}
      ></typo3-backend-wizard>
    `;
  }
}

customElements.define('georgringer-record-wizard', RecordWizard);
export { RecordWizard };
