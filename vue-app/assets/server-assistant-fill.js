/*
 * "Fill in form" for the server assistant (<pslm-server-chat>): the only way one of its proposals
 * reaches an editor field.
 *
 * Through the form bridge (assistant-form.js), the field's own `form-input` component emits `input`,
 * exactly as typing would, so the editor marks the form dirty and validates; nothing is saved.
 * Controlled lists accept only one of their live options. A field on another route is opened at its own
 * address (#/study/<dotted.key>) and filled once it renders. The request carries a respond(ok, message)
 * callback and the panel shows the outcome on the proposal card itself.
 */
(function () {
  'use strict';

  // Every template section is one element of the project form at #/study/<dotted key>; only
  // external resources have their own route. This covers every project type.
  function keyOf(pointer) {
    return String(pointer || '').replace(/^\/+|\/+$/g, '').split('/').join('.');
  }

  function routeFor(pointer) {
    var key = keyOf(pointer);
    return key.split('.')[0] === 'external_resources' ? '#/external-resources' : '#/study/' + key;
  }

  // The left pane's tree follows the filled field: it marks the field's node and opens the collapsed
  // section that holds it, so the curator sees where the draft landed without hunting for it.
  function revealInTree(key, el) {
    try {
      var appEl = document.querySelector('[data-app]') || document.querySelector('#app');
      var store = appEl && appEl.__vue__ && appEl.__vue__.$store;
      if (store && typeof store.commit === 'function') store.commit('tree_active_node_path', key);
    } catch (err) { /* the tree is cosmetic */ }
    var panel = el && el.closest ? el.closest('.v-expansion-panel') : null;
    if (panel && !panel.classList.contains('v-expansion-panel--active')) {
      var header = panel.querySelector('.v-expansion-panel-header');
      if (header) header.click();
    }
  }

  // A list field (producers, funders, keywords): the proposed rows are added after the saved ones, which
  // stay exactly as they are. The table's own component takes the new array through `input`.
  function applyRows(field, rows, label, respond, key) {
    var form = window.assistantEditorForm;
    var props = (field.field && field.field.props) || [];
    if (form.displayType(field) !== 'array' || !props.length) {
      return respond(false, '"' + label + '" is not a table the assistant can add rows to. Use Copy.');
    }
    var allowed = {};
    props.forEach(function (c) { if (c && c.key) allowed[c.key] = true; });
    var saved = Array.isArray(field.value) ? field.value : [];
    var added = [];
    (rows || []).forEach(function (row) {
      var clean = {};
      Object.keys(row || {}).forEach(function (k) {
        var v = row[k] == null ? '' : String(row[k]).trim();
        if (allowed[k] && v) clean[k] = v;
      });
      if (Object.keys(clean).length) added.push(clean);
    });
    if (!added.length) return respond(false, 'There was no row to add.');
    field.$emit('input', saved.concat(added));
    revealInTree(key || '', field.$el);
    field.$el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    var flash = field.$el;
    flash.style.transition = 'box-shadow .3s';
    flash.style.boxShadow = '0 0 0 3px rgba(21,101,192,.45)';
    setTimeout(function () { flash.style.boxShadow = ''; }, 1600);
    respond(true, 'Added ' + added.length + ' row' + (added.length === 1 ? '' : 's') + ' to "' + label + '". Not saved yet: review them, then Save.');
  }

  function apply(field, value, label, respond, key) {
    if (Array.isArray(value)) return applyRows(field, value, label, respond, key);
    var form = window.assistantEditorForm;
    var options = form.optionsOf(field);
    if (!form.offerable(field, options)) {
      return respond(false, '"' + label + '" is read-only, or a multi-value or table field: a single value cannot replace it. Use Copy.');
    }
    var text = String(value || '');
    if (options.length) {
      var wanted = text.trim().toLowerCase();
      var choice = null;
      for (var i = 0; i < options.length; i++) {
        if (options[i].value.toLowerCase() === wanted || options[i].label.toLowerCase() === wanted) { choice = options[i]; break; }
      }
      if (!choice) {
        var names = options.slice(0, 8).map(function (o) { return o.label; }).join(', ');
        return respond(false, '"' + label + '" only accepts one of its listed options (' + names + (options.length > 8 ? ', …' : '') + '). Pick one yourself.');
      }
      text = form.storedValue(field, choice);
    }
    field.$emit('input', text);
    revealInTree(key || '', field.$el);
    field.$el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    var flash = field.$el;
    flash.style.transition = 'box-shadow .3s';
    flash.style.boxShadow = '0 0 0 3px rgba(21,101,192,.45)';
    setTimeout(function () { flash.style.boxShadow = ''; }, 1600);
    respond(true, 'Typed into "' + label + '". Not saved yet: review it, then Save.');
  }

  document.addEventListener('assistant-fill-request', function (event) {
    var d = event.detail || {};
    var respond = typeof d.respond === 'function' ? d.respond : function () {};
    var panel = event.target;
    if (!panel || panel.tagName !== 'PSLM-SERVER-CHAT') return;
    if (String(d.recordId || '') !== String(panel.dataset.recordId || '') || !d.recordId) {
      return respond(false, 'This proposal belongs to another project.');
    }
    var form = window.assistantEditorForm;
    if (!form) return respond(false, 'The editor form is not available on this page. Use Copy.');
    var key = keyOf(d.pointer);
    var label = d.label || key;
    var field = form.component(key);
    if (field) return apply(field, d.suggestion, label, respond, key);

    var hash = routeFor(d.pointer);
    if (location.hash !== hash) location.hash = hash;
    var tries = 0;
    (function poll() {
      var vm = form.componentAnywhere(key);
      if (vm) revealInTree(key, vm.$el);
      if (vm && form.visible(vm.$el)) return apply(vm, d.suggestion, label, respond, key);
      if (++tries > 60) return respond(false, 'Opened the section, but "' + label + '" did not appear. Use Copy.');
      setTimeout(poll, 50);
    })();
  });
})();
