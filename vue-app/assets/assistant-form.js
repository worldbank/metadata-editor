/*
 * Which fields this editor form actually has, what each may hold, and what the form stores for one of
 * them: the Metadata Editor's own answer, used by the server assistant's "Fill in form"
 * (server-assistant-fill.js).
 *
 * Every editable field on an editor form is one `form-input` Vue component: it holds the template's
 * field definition (key, title, help text, controlled list), the curator's current value, and the rule
 * for how a chosen option is written to the record. Vue hangs the instance on its own root element, so
 * the page's nodes *are* the component tree; no global to reach for.
 *
 * Reading the DOM instead cannot work: only text fields put a `field-<key>` class on their wrapper. A
 * dropdown renders `form-field-dropdown` with a Vuetify widget and a hidden input, a textarea
 * `form-field-textarea`, a date `form-field-date`. So a scraper would offer Title and silently omit
 * Resource type, Resource format and Description. The component describes all of them the same way.
 *
 * Loaded by application/views/metadata_editor/assistant_panel.php before server-assistant-fill.js.
 * Needs no build step.
 */
(function () {
  'use strict';

  // A field one suggested string can replace. Anything else (array, nested_array, simple_array,
  // coordinate_pairs, bounding_box) holds a list or a structure, and replacing it with one string is
  // not a draft, it is data loss.
  var SINGLE = { 'text': 1, 'textarea': 1, 'number': 1, 'integer': 1, 'date': 1, 'dropdown': 1, 'dropdown-custom': 1 };
  var CONTROLLED = { 'dropdown': 1, 'dropdown-custom': 1 };

  function visible(element) {
    return !!element && element.offsetParent !== null;
  }

  // The editor's own rule for which widget a template field renders — the same one its template uses.
  function displayType(vm) {
    if (typeof vm.fieldDisplayType === 'function') return String(vm.fieldDisplayType(vm.field) || '');
    return String((vm.field && (vm.field.display_type || vm.field.type)) || '');
  }

  // Only what the curator can see and edit. The project page keeps the study description in the DOM
  // while the resource form is on screen, and offering both would let a questionnaire draft itself
  // into a study field nobody is looking at.
  function components(scope) {
    var out = [];
    var nodes = (scope || document).querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      var vm = nodes[i].__vue__;
      if (vm && vm.$options && vm.$options.name === 'form-input' && vm.field && vm.field.key
        && visible(vm.$el) && !vm.isFieldReadOnly) out.push(vm);
    }
    return out;
  }

  function component(key) {
    var all = components(document);
    for (var i = 0; i < all.length; i++) {
      if (String(all[i].field.key) === String(key)) return all[i];
    }
    return null;
  }

  // The field's component even when its section is collapsed or another route owns the page. The
  // fill bridge needs the distinction — a field that exists but is hidden wants its section expanded
  // (or the route navigated), not a refusal. Still skips read-only fields: invisibility is not an
  // invitation to write into a field the curator cannot edit.
  function componentAnywhere(key) {
    var nodes = document.querySelectorAll('*');
    for (var i = 0; i < nodes.length; i++) {
      var vm = nodes[i].__vue__;
      if (vm && vm.$options && vm.$options.name === 'form-input' && vm.field && vm.field.key
        && String(vm.field.key) === String(key) && !vm.isFieldReadOnly) return vm;
    }
    return null;
  }

  // The template's vocabulary, in the two forms the assistant needs: `value` is the code the model
  // picks and the SDK validates against, `label` is what a curator reads. What the row *stores* is a
  // third thing, decided by the field itself, and stays in this file (see storedValue).
  function optionsOf(vm) {
    var items = vm.dropdownEnumList || [];
    var out = [];
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var code = item && typeof item === 'object' ? (item.code != null ? item.code : item.value) : item;
      var label = item && typeof item === 'object' ? (item.label != null ? item.label : item.text) : item;
      code = String(code == null ? '' : code).trim();
      if (!code) continue;
      out.push({ value: code, label: String(label == null ? code : label).trim() || code });
    }
    return out;
  }

  // Which string a chosen option becomes in the record: the code, the label, or `label [code]`,
  // decided by the field's own enum_store_column — or, where the editor has a custom mapping, by its
  // own storeEnumValue. Never reimplemented here: one rule, in the component that owns it.
  function storedValue(vm, option) {
    var items = vm.dropdownEnumList || [];
    for (var i = 0; i < items.length; i++) {
      var item = items[i];
      var code = String((item && item.code != null) ? item.code : item || '').trim();
      if (code !== String(option.value).trim()) continue;
      if (typeof vm.storeEnumValue === 'function') {
        try {
          var stored = vm.storeEnumValue(item);
          if (stored != null && String(stored).trim()) return String(stored);
        } catch (err) { /* the field's own mapping failed; its code is still a truthful value */ }
      }
      break;
    }
    return String(option.value);
  }

  // What the curator already has, in the form a curator reads: the label, not the stored string.
  // The assistant tells the model this so it does not restate what is already typed.
  function currentOf(vm, options) {
    var raw = vm.value;
    if (raw == null || typeof raw === 'object') return '';
    var text = String(raw).trim();
    if (!text || !options.length) return text;
    var code = typeof vm.getEnumCodeFromLabel === 'function' ? String(vm.getEnumCodeFromLabel(text) || '') : text;
    for (var i = 0; i < options.length; i++) {
      if (options[i].value === code || options[i].label === text) return options[i].label;
    }
    return text;
  }

  // A controlled field whose list we cannot read — a global codelist owned by another component, or
  // still loading — is not offered at all. Sent with no options it would look like free text, and a
  // invented value the editor's own vocabulary rejects is worse than a field nobody drafted.
  function offerable(vm, options) {
    var display = displayType(vm);
    if (!SINGLE[display]) return false;
    if (CONTROLLED[display] && !options.length) return false;
    return true;
  }

  window.assistantEditorForm = {
    visible: visible,
    components: components,
    component: component,
    componentAnywhere: componentAnywhere,
    displayType: displayType,
    optionsOf: optionsOf,
    storedValue: storedValue,
    currentOf: currentOf,
    offerable: offerable
  };
})();
