/*
 * Record Wizard - FormEngine step (generic, without pages-specific processed-value call)
 */
import { executeJavaScriptModuleInstruction } from '@typo3/core/java-script-item-processor.js';
import { html } from 'lit';
import { unsafeHTML } from 'lit/directives/unsafe-html.js';

class RecordFormEngineStep {
  autoAdvance = false;

  constructor(context, configuration) {
    this.context = context;
    this.key = configuration.key;
    this.title = configuration.title;
    this.html = configuration.html ?? '';
    this.modules = configuration.modules ?? [];
    this.labels = configuration.labels ?? {};
    this.tableName = configuration.tableName ?? '';
    this.summary = [];
    this._form = null;
  }

  getValue() {
    const form = this._form ?? this.context.wizard?.querySelector('form[name="editform"]');
    if (!form) {
      return null;
    }
    const fields = this.context.getStoreData('fields') ?? {};
    const formData = new FormData(form);
    fields[this.key] = Object.fromEntries(
      [...formData.entries()].filter(([, v]) => v !== '')
    );
    return fields;
  }

  setValue(fields) {
    const form = this._form ?? this.context.wizard?.querySelector('form[name="editform"]');
    const values = fields?.[this.key];
    if (!form || !values) {
      return;
    }
    for (const [key, val] of Object.entries(values)) {
      const input = form.elements.namedItem(key);
      if (input) {
        delete input.dataset.formengineInputInitialized;
        if (input.type === 'checkbox') {
          input.checked = Boolean(val);
        } else {
          input.value = val;
        }
      }
    }
  }

  render() {
    return html`${unsafeHTML(this.html)}`;
  }

  getSummaryData() {
    return this.summary;
  }

  isComplete() {
    if (TYPO3.FormEngine?.Validation) {
      return TYPO3.FormEngine.Validation.isValid();
    }
    return true;
  }

  async afterRender() {
    // Cache the form reference while we know it's in the DOM
    this._form = this.context.wizard?.querySelector('form[name="editform"]') ?? null;

    this.setValue(this.context.getStoreData('fields'));

    if (this.modules.length > 0) {
      await Promise.all(this.modules.map((m) => executeJavaScriptModuleInstruction(m)));
    }

    if (TYPO3.FormEngine) {
      TYPO3.FormEngine.reinitialize();

      if (this._form) {
        this._form.addEventListener('t3-formengine-postfieldvalidation', () => {
          this.context.wizard?.requestUpdate();
        });
        this._form.addEventListener('submit', (e) => {
          e.preventDefault();
          this.context.wizard?.goToNextStep();
        });
        this._form.querySelector('.has-error')?.focus();
      }
    }
  }

  async beforeAdvance() {
    const fields = this.getValue();
    this.context.setStoreData('fields', fields);

    const values = fields?.[this.key] ?? {};
    this.summary = Object.entries(values)
      .map(([rawKey, value]) => {
        const fieldName = this._extractFieldName(rawKey);
        const label = this.labels[fieldName] || fieldName;
        return { label, value: String(value) };
      })
      .filter(({ value }) => value !== '');
  }

  _extractFieldName(key) {
    const match = key.match(/\[([^\]]+)\]$/);
    return match ? match[1] : key;
  }
}

export { RecordFormEngineStep as default };
