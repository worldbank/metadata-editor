///vue component for editing KEY field (delegates to schema-aware custom key field)
Vue.component('vue-key-field', {
    props:['value','field'],
    template: `
        <vue-custom-key-field
            :value="value"
            :field="field || { key: value }"
            @input="$emit('input', $event)"
        ></vue-custom-key-field>
    `
});
