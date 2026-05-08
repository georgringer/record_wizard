/*
 * Record Wizard - trigger button web component.
 *
 * Usage (PHP, e.g. in an EventListener):
 *   <georgringer-record-wizard-button
 *     configuration='{"tableName":"tx_news_domain_model_news","typeValue":"0","pid":1}'
 *   ></georgringer-record-wizard-button>
 */
import { LitElement, html } from 'lit';
import { topLevelModuleImport } from '@typo3/backend/utility/top-level-module-import.js';
import Modal from '@typo3/backend/modal.js';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import labels from '~labels/record_wizard.messages';

class RecordWizardButton extends LitElement {
  static properties = {
    configuration: { type: Object },
  };

  constructor() {
    super();
    this.configuration = null;
  }

  // No shadow DOM — inherit backend button styles
  createRenderRoot() {
    return this;
  }

  async _openWizard() {
    // Load the wizard element in the top-level frame so it has full access
    // to TYPO3 globals and module registry.
    await topLevelModuleImport('@georgringer/record-wizard/record-wizard.js');

    Modal.advanced({
      title: labels.get('button.label'),
      content: html`<georgringer-record-wizard .configuration=${this.configuration ?? {}}></georgringer-record-wizard>`,
      severity: SeverityEnum.notice,
      size: Modal.sizes.medium,
      staticBackdrop: true,
      buttons: [],
    });
  }

  render() {
    return html`
      <button
        type="button"
        class="btn btn-default btn-sm"
        @click=${() => this._openWizard()}
        title=${labels.get('button.label')}
      >
        <typo3-backend-icon identifier="actions-plus" size="small"></typo3-backend-icon>
        ${labels.get('button.label')}
      </button>
    `;
  }
}

customElements.define('georgringer-record-wizard-button', RecordWizardButton);
export { RecordWizardButton };
