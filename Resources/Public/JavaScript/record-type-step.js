/*
 * Record Wizard - Type selection step.
 * Shown as the first step when the target table has multiple TCA types
 * that each have wizardSteps configured.
 */
import { html } from 'lit';
import { live } from 'lit/directives/live.js';

class RecordTypeStep {
  autoAdvance = true;

  constructor(context, configuration) {
    this.context = context;
    this.key = configuration.key ?? 'type';
    this.title = configuration.title ?? 'Select Type';
    this.types = configuration.types ?? [];
    this._selectedType = null;
    this._hasAutoAdvanced = false;
  }

  isComplete() {
    return this._selectedType !== null;
  }

  getValue() {
    return this._selectedType;
  }

  setValue(value) {
    this._selectedType = value;
    this.context.wizard?.requestUpdate();
  }

  reset() {
    this._selectedType = null;
    this._hasAutoAdvanced = false;
    this.context.wizard?.requestUpdate();
  }

  beforeAdvance() {
    this.context.setStoreData('typeValue', this._selectedType);
  }

  getSummaryData() {
    if (!this._selectedType) {
      return [];
    }
    const type = this.types.find((t) => t.value === this._selectedType);
    return type ? [{ label: this.title, value: type.label }] : [];
  }

  render() {
    // Pre-select first type if nothing is selected yet; auto-advance when only one type
    if (this._selectedType === null && this.types.length > 0) {
      this._selectedType = this.types[0].value;
      if (this.types.length === 1 && !this._hasAutoAdvanced) {
        this._hasAutoAdvanced = true;
        queueMicrotask(() => this.context.wizard?.goToNextStep());
      }
    }

    return html`
      <div class="record-type-selection">
        <h2 class="h4">${this.title}</h2>
        <div class="form-check-card-container">
          ${this.types.map((type) => html`
            <div class="form-check form-check-type-card">
              <input
                class="form-check-input"
                type="radio"
                name="record-type"
                id="type-${type.value}"
                value=${type.value}
                .checked=${live(this._selectedType === type.value)}
                @change=${() => this.setValue(type.value)}
              >
              <label class="form-check-label" for="type-${type.value}">
                <span class="form-check-label-header form-check-label-header-inherit">
                  <typo3-backend-icon identifier="${type.icon}" size="small"></typo3-backend-icon>
                  ${type.label}
                </span>
              </label>
            </div>
          `)}
        </div>
      </div>
    `;
  }
}

export { RecordTypeStep as default };
