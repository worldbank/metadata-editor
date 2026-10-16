/**
 * Issue apply helper
 *
 * Applies an issue's suggested value to the project metadata loaded in the editor
 * ($store.state.formData). Changes stay in the browser; the user saves the project as usual.
 */
const IssueApplyHelper = {
    /**
     * Suggested value for the issue's field. suggested_metadata is normally keyed by field_path;
     * otherwise the whole object is the value.
     */
    getSuggestedValue(issue) {
        if (!issue || issue.suggested_metadata == null) return undefined;
        const metadata = issue.suggested_metadata;
        const fieldPath = issue.field_path;
        if (fieldPath && typeof metadata === 'object' && !Array.isArray(metadata)
            && Object.prototype.hasOwnProperty.call(metadata, fieldPath)) {
            return metadata[fieldPath];
        }
        return metadata;
    },

    /**
     * Convert text edited by the user back to a value, keeping string fields as strings.
     */
    parseEditedValue(text, originalValue) {
        const str = text == null ? '' : String(text);
        if (typeof originalValue === 'string') return str;
        try {
            return JSON.parse(str);
        } catch (e) {
            return str;
        }
    },

    /**
     * Write value into formData at fieldPath (dot or bracket notation).
     * @returns {boolean} false when the form data or field path is not available
     */
    applyToForm(vm, fieldPath, value) {
        const formData = vm.$store && vm.$store.state.formData;
        if (!fieldPath || !formData || typeof formData !== 'object') return false;
        const copy = value !== null && typeof value === 'object' ? JSON.parse(JSON.stringify(value)) : value;
        vm.$vueSet(formData, fieldPath, copy);
        return true;
    }
};
