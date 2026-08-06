/**
 * Project editor module visibility (mirrors Editor_project_modules.php).
 */
var EditorProjectModulesUtil = (function () {
    /**
     * @param {object|null} templateRoot Decoded template (type, items, editor_modules, …)
     * @param {string} moduleId
     * @returns {boolean}
     */
    function isModuleVisible(templateRoot, moduleId) {
        if (!moduleId) {
            return true;
        }
        if (!templateRoot || typeof templateRoot !== 'object') {
            return true;
        }

        var mods = templateRoot.editor_modules;
        if (!mods || !mods[moduleId] || typeof mods[moduleId] !== 'object') {
            return true;
        }
        return mods[moduleId].show_in_editor !== false;
    }

    function getTemplateRootFromFormTemplate(formTemplate) {
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

    return {
        isModuleVisible: isModuleVisible,
        getTemplateRootFromFormTemplate: getTemplateRootFromFormTemplate,
    };
})();
