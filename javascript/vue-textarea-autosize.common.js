/*!
 * vue-textarea-autosize v1.1.1 
 * (c) 2019 Saymon
 * Released under the MIT License.
 */
'use strict';

//
//
//
//
//
//
//
var script = {
  name: 'TextareaAutosize',
  props: {
    value: {
      type: [String, Number],
      default: ''
    },
    autosize: {
      type: Boolean,
      default: true
    },
    minHeight: {
      type: [Number],
      'default': null
    },
    maxHeight: {
      type: [Number],
      'default': null
    },

    /*
     * Force !important for style properties
     */
    important: {
      type: [Boolean, Array],
      default: false
    }
  },
  data: function data() {
    return {
      // data property for v-model binding with real textarea tag
      val: null,
      // works when content height becomes more then value of the maxHeight property
      maxHeightScroll: false,
      height: 'auto'
    };
  },
  computed: {
    computedStyles: function computedStyles() {
      if (!this.autosize) return {};
      return {
        resize: !this.isResizeImportant ? 'none' : 'none !important',
        height: this.height,
        overflow: this.maxHeightScroll ? 'auto' : !this.isOverflowImportant ? 'hidden' : 'hidden !important'
      };
    },
    isResizeImportant: function isResizeImportant() {
      var imp = this.important;
      return imp === true || Array.isArray(imp) && imp.includes('resize');
    },
    isOverflowImportant: function isOverflowImportant() {
      var imp = this.important;
      return imp === true || Array.isArray(imp) && imp.includes('overflow');
    },
    isHeightImportant: function isHeightImportant() {
      var imp = this.important;
      return imp === true || Array.isArray(imp) && imp.includes('height');
    }
  },
  watch: {
    value: function value(val) {
      this.val = val;
    },
    val: function val(_val) {
      this.$nextTick(this.resize);
      this.$emit('input', _val);
    },
    minHeight: function minHeight() {
      this.$nextTick(this.resize);
    },
    maxHeight: function maxHeight() {
      this.$nextTick(this.resize);
    },
    autosize: function autosize(val) {
      if (val) this.resize();
    }
  },
  methods: {
    resize: function resize() {
      var _this = this;

      var important = this.isHeightImportant ? 'important' : '';
      this.height = "auto".concat(important ? ' !important' : '');
      this.$nextTick(function () {
        var contentHeight = _this.$el.scrollHeight + 1;

        if (_this.minHeight) {
          contentHeight = contentHeight < _this.minHeight ? _this.minHeight : contentHeight;
        }

        if (_this.maxHeight) {
          if (contentHeight > _this.maxHeight) {
            contentHeight = _this.maxHeight;
            _this.maxHeightScroll = true;
          } else {
            _this.maxHeightScroll = false;
          }
        }

        var heightVal = contentHeight + 'px';
        _this.height = "".concat(heightVal).concat(important ? ' !important' : '');
      });
      return this;
    }
  },
  created: function created() {
    this.val = this.value;
  },
  mounted: function mounted() {
    this.resize();
  }
};

function normalizeComponent(template, style, script, scopeId, isFunctionalTemplate, moduleIdentifier
/* server only */
, shadowMode, createInjector, createInjectorSSR, createInjectorShadow) {
  if (typeof shadowMode !== 'boolean') {
    createInjectorSSR = createInjector;
    createInjector = shadowMode;
    shadowMode = false;
  } // Vue.extend constructor export interop.


  var options = typeof script === 'function' ? script.options : script; // render functions

  if (template && template.render) {
    options.render = template.render;
    options.staticRenderFns = template.staticRenderFns;
    options._compiled = true; // functional template

    if (isFunctionalTemplate) {
      options.functional = true;
    }
  } // scopedId


  if (scopeId) {
    options._scopeId = scopeId;
  }

  var hook;

  if (moduleIdentifier) {
    // server build
    hook = function hook(context) {
      // 2.3 injection
      context = context || // cached call
      this.$vnode && this.$vnode.ssrContext || // stateful
      this.parent && this.parent.$vnode && this.parent.$vnode.ssrContext; // functional
      // 2.2 with runInNewContext: true

      if (!context && typeof __VUE_SSR_CONTEXT__ !== 'undefined') {
        context = __VUE_SSR_CONTEXT__;
      } // inject component styles


      if (style) {
        style.call(this, createInjectorSSR(context));
      } // register component module identifier for async chunk inference


      if (context && context._registeredComponents) {
        context._registeredComponents.add(moduleIdentifier);
      }
    }; // used by ssr in case component is cached and beforeCreate
    // never gets called


    options._ssrRegister = hook;
  } else if (style) {
    hook = shadowMode ? function () {
      style.call(this, createInjectorShadow(this.$root.$options.shadowRoot));
    } : function (context) {
      style.call(this, createInjector(context));
    };
  }

  if (hook) {
    if (options.functional) {
      // register for functional component in vue file
      var originalRender = options.render;

      options.render = function renderWithStyleInjection(h, context) {
        hook.call(context);
        return originalRender(h, context);
      };
    } else {
      // inject component registration as beforeCreate hook
      var existing = options.beforeCreate;
      options.beforeCreate = existing ? [].concat(existing, hook) : [hook];
    }
  }

  return script;
}

var normalizeComponent_1 = normalizeComponent;

/* script */
const __vue_script__ = script;

/* template */
var __vue_render__ = function () {var _vm=this;var _h=_vm.$createElement;var _c=_vm._self._c||_h;return _c('textarea',{directives:[{name:"model",rawName:"v-model",value:(_vm.val),expression:"val"}],style:(_vm.computedStyles),domProps:{"value":(_vm.val)},on:{"focus":_vm.resize,"input":function($event){if($event.target.composing){ return; }_vm.val=$event.target.value;}}})};
var __vue_staticRenderFns__ = [];

  /* style */
  const __vue_inject_styles__ = undefined;
  /* scoped */
  const __vue_scope_id__ = undefined;
  /* module identifier */
  const __vue_module_identifier__ = undefined;
  /* functional template */
  const __vue_is_functional_template__ = false;
  /* style inject */
  
  /* style inject SSR */
  

  
  var TextareaAutosize = normalizeComponent_1(
    { render: __vue_render__, staticRenderFns: __vue_staticRenderFns__ },
    __vue_inject_styles__,
    __vue_script__,
    __vue_scope_id__,
    __vue_is_functional_template__,
    __vue_module_identifier__,
    undefined,
    undefined
  );

var version = '1.1.1';

var install = function install(Vue) {
  Vue.component('TextareaAutosize', TextareaAutosize);
};

var plugin = {
  install: install,
  version: version
};

if (typeof window !== 'undefined' && window.Vue) {
  window.Vue.use(plugin);
}

module.exports = plugin;