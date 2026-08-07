/**
 * Walk form templates and apply or count field defaults (Template Manager "default" on items).
 */
var TemplateDefaultsUtil = (function () {
    function isEmptyScalar(value) {
        if (value === 0 || value === false) {
            return false;
        }
        if (value === null || value === undefined) {
            return true;
        }
        if (typeof value === 'string') {
            return value.trim() === '';
        }
        return !value;
    }

    function isEmptyValue(value) {
        if (value === null || value === undefined) {
            return true;
        }
        if (Array.isArray(value)) {
            if (value.length === 0) {
                return true;
            }
            for (var i = 0; i < value.length; i++) {
                if (!isEmptyScalar(value[i])) {
                    return false;
                }
            }
            return true;
        }
        if (typeof value === 'object') {
            return Object.keys(value).length === 0;
        }
        return isEmptyScalar(value);
    }

    function cloneDefault(defaultVal) {
        if (defaultVal === null || defaultVal === undefined) {
            return defaultVal;
        }
        if (typeof defaultVal !== 'object') {
            return defaultVal;
        }
        try {
            return JSON.parse(JSON.stringify(defaultVal));
        } catch (e) {
            return defaultVal;
        }
    }

    function normalizeDefaultForField(item, defaultVal) {
        var d = cloneDefault(defaultVal);
        if (!item || !item.type) {
            return d;
        }
        if (item.type === 'simple_array') {
            if (Array.isArray(d)) {
                return d;
            }
            if (d === null || d === undefined || (typeof d === 'string' && d.trim() === '')) {
                return [];
            }
            return [d];
        }
        return d;
    }

    function getTemplateRoot(formTemplate) {
        if (typeof EditorProjectModulesUtil !== 'undefined') {
            return EditorProjectModulesUtil.getTemplateRootFromFormTemplate(formTemplate);
        }
        if (!formTemplate || !formTemplate.template) {
            return null;
        }
        var t = formTemplate.template;
        if (typeof t === 'string') {
            try {
                t = JSON.parse(t);
            } catch (e) {
                return null;
            }
        }
        return t;
    }

    function shouldApplyField(mode, value) {
        return mode === 'all' || (mode === 'empty' && isEmptyValue(value));
    }

    function fieldHasDefault(item) {
        if (!item || !item.hasOwnProperty('default')) {
            return false;
        }
        var d = item.default;
        if (d === null || d === undefined) {
            return false;
        }
        if (typeof d === 'string' && d.trim() === '' && item.type !== 'simple_array') {
            return false;
        }
        if (Array.isArray(d) && d.length === 0) {
            return false;
        }
        return true;
    }

    /**
     * @param {object} formTemplate
     * @param {object} metadata
     * @param {string} mode "empty" | "all"
     * @param {{apply?: boolean}} opts When apply is true, metadata is mutated in place
     * @returns {Array<{key:string, item:object, value:*, default:*, willOverwrite:boolean}>}
     */
    function collectTemplateDefaults(formTemplate, metadata, mode, opts) {
        opts = opts || {};
        var apply = opts.apply === true;
        var templateRoot = getTemplateRoot(formTemplate);
        var entries = [];
        if (!templateRoot || !metadata) {
            return entries;
        }

        function pushEntry(item_key, item, value, defaultVal) {
            entries.push({
                key: item_key,
                item: item,
                value: value,
                default: defaultVal,
                willOverwrite: !isEmptyValue(value)
            });
        }

        function applyDefaultAtKey(meta, key, item) {
            _.set(meta, key, normalizeDefaultForField(item, item.default));
        }

        function walkTemplateProp(item, propMeta, item_path, metadataRoot) {
            if (fieldHasDefault(item)) {
                var value = propMeta;
                var item_key = item_path + '.' + item.key;

                if (shouldApplyField(mode, value)) {
                    if (apply) {
                        applyDefaultAtKey(metadataRoot, item_key, item);
                    }
                    pushEntry(item.prop_key || item_key, item, value, item.default);
                }
            }

            if (item.hasOwnProperty('props') && Array.isArray(propMeta) && propMeta.length) {
                for (var k = 0; k < propMeta.length; k++) {
                    for (var p = 0; p < item.props.length; p++) {
                        var nested = _.get(propMeta[k], item.props[p].key, null);
                        walkTemplateProp(
                            item.props[p],
                            nested,
                            item_path + '.' + item.key + '.' + k,
                            metadataRoot
                        );
                    }
                }
            }
        }

        function walkTemplate(item, meta) {
            var skipSelf = item.hasOwnProperty('is_custom');

            if (!skipSelf && fieldHasDefault(item)) {
                var value = _.get(meta, item.key, null);
                var item_key = item.hasOwnProperty('prop_key') ? item.prop_key : item.key;

                if (shouldApplyField(mode, value)) {
                    if (apply) {
                        applyDefaultAtKey(meta, item.key, item);
                    }
                    pushEntry(item_key, item, value, item.default);
                }
            }

            if (item.hasOwnProperty('items')) {
                for (var i = 0; i < item.items.length; i++) {
                    walkTemplate(item.items[i], meta);
                }
            }

            if (item.hasOwnProperty('props')) {
                var itemMetadata = _.get(meta, item.key, null);
                if (Array.isArray(itemMetadata) && itemMetadata.length > 0) {
                    for (var k = 0; k < itemMetadata.length; k++) {
                        for (var pi = 0; pi < item.props.length; pi++) {
                            var propMetadata = _.get(itemMetadata[k], item.props[pi].key, null);
                            walkTemplateProp(
                                item.props[pi],
                                propMetadata,
                                item.key + '.' + k,
                                meta
                            );
                        }
                    }
                } else {
                    for (var pj = 0; pj < item.props.length; pj++) {
                        walkTemplateProp(
                            item.props[pj],
                            null,
                            item.key + '.0',
                            meta
                        );
                    }
                }
            }
        }

        walkTemplate(templateRoot, metadata);
        return entries;
    }

    function listTemplateDefaultsToApply(formTemplate, metadata, mode) {
        return collectTemplateDefaults(formTemplate, metadata, mode, { apply: false });
    }

    function countPendingEmptyDefaults(formTemplate, metadata) {
        return listTemplateDefaultsToApply(formTemplate, metadata, 'empty').length;
    }

    function applyTemplateDefaults(formTemplate, metadata, mode) {
        return collectTemplateDefaults(formTemplate, metadata, mode, { apply: true });
    }

    return {
        countPendingEmptyDefaults: countPendingEmptyDefaults,
        listTemplateDefaultsToApply: listTemplateDefaultsToApply,
        applyTemplateDefaults: applyTemplateDefaults
    };
})();
