/**
 * Merge template field rules into a VeeValidate object.
 * Template rules are stored as objects ({required: true, min: 5}) or pipe strings.
 * Never string-concatenate an object (that produced "[object Object]|data_type:text").
 */
var FieldValidationRulesUtil = (function () {
    var SIMPLE_DATA_TYPES = ['text', 'string', 'textarea', 'number', 'integer'];

    function isPlainObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    function assignRule(rules, name, param) {
        if (!name) {
            return;
        }
        if (param === false || param === null || param === undefined) {
            return;
        }
        rules[name] = (param === true || param === '') ? true : param;
    }

    function applySourceRules(rules, src) {
        if (!src) {
            return;
        }
        if (typeof src === 'string') {
            src.split('|').forEach(function (part) {
                part = part.trim();
                if (!part) {
                    return;
                }
                var colon = part.indexOf(':');
                if (colon === -1) {
                    assignRule(rules, part, true);
                } else {
                    assignRule(rules, part.slice(0, colon), part.slice(colon + 1));
                }
            });
            return;
        }
        if (Array.isArray(src)) {
            src.forEach(function (item) {
                if (typeof item === 'string') {
                    applySourceRules(rules, item);
                }
            });
            return;
        }
        if (isPlainObject(src)) {
            Object.keys(src).forEach(function (name) {
                assignRule(rules, name, src[name]);
            });
        }
    }

    /**
     * @param {Object} field Template field or grid column
     * @param {Object} [options]
     * @param {boolean} [options.addDataType=true] Append data_type for simple scalar types
     * @returns {Object} VeeValidate rules object
     */
    function normalize(field, options) {
        var rules = {};
        if (!field) {
            return rules;
        }

        applySourceRules(rules, field.rules);

        if (field.is_required || field.required) {
            rules.required = true;
        }

        var addDataType = !options || options.addDataType !== false;
        if (addDataType && SIMPLE_DATA_TYPES.indexOf(field.type) !== -1) {
            rules.data_type = field.type;
        }

        if (field.display_type === 'date' && !hasDateFormatRule(rules) && hasExplicitDateFormat(field)) {
            rules[dateFormatRuleName(resolveDateFormat(field))] = true;
        }

        return rules;
    }

    function hasDateFormatRule(rules) {
        return !!(rules.iso_date || rules.iso_date_partial || rules.iso_datetime || rules.iso_year || rules.iso_year_month);
    }

    function explicitDateFormat(field) {
        var fmt = field && field.display_options && field.display_options.format;
        if (fmt === 'datetime_iso') {
            return 'datetime';
        }
        if (['partial', 'date', 'year-month', 'year', 'datetime'].indexOf(fmt) !== -1) {
            return fmt;
        }
        return null;
    }

    function hasExplicitDateFormat(field) {
        return explicitDateFormat(field) !== null;
    }

    function resolveDateFormat(field) {
        return explicitDateFormat(field) || 'partial';
    }

    function dateFormatRuleName(format) {
        switch (format) {
            case 'date':
                return 'iso_date';
            case 'year-month':
                return 'iso_year_month';
            case 'year':
                return 'iso_year';
            case 'datetime':
                return 'iso_datetime';
            default:
                return 'iso_date_partial';
        }
    }

    function calendarDateFromValue(value) {
        if (typeof value !== 'string') {
            return null;
        }
        if (/^\d{4}-\d{2}-\d{2}T/.test(value)) {
            return value.slice(0, 10);
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return value;
        }
        return null;
    }

    function isIsoDateStrict(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return false;
        }
        var parts = value.split('-');
        var year = Number(parts[0]);
        var month = Number(parts[1]);
        var day = Number(parts[2]);
        if (year < 1 || month < 1 || month > 12 || day < 1 || day > 31) {
            return false;
        }
        var dt = new Date(Date.UTC(year, month - 1, day));
        return dt.getUTCFullYear() === year && dt.getUTCMonth() === month - 1 && dt.getUTCDate() === day;
    }

    function isIsoDate(value) {
        var date = calendarDateFromValue(value);
        return date !== null && isIsoDateStrict(date);
    }

    function isIsoYear(value) {
        return typeof value === 'string' && /^\d{4}$/.test(value) && Number(value) >= 1;
    }

    function isIsoYearMonth(value) {
        if (typeof value !== 'string' || !/^\d{4}-\d{2}$/.test(value)) {
            return false;
        }
        var year = Number(value.slice(0, 4));
        var month = Number(value.slice(5, 7));
        return year >= 1 && month >= 1 && month <= 12;
    }

    function isIsoDateTime(value) {
        if (typeof value !== 'string') {
            return false;
        }
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})?$/.test(value)) {
            return false;
        }
        return isIsoDateStrict(value.slice(0, 10));
    }

    function isIsoDatePartial(value) {
        if (typeof value !== 'string') {
            return false;
        }
        if (isIsoYear(value) || isIsoYearMonth(value)) {
            return true;
        }
        return isIsoDate(value);
    }

    return {
        normalize: normalize,
        resolveDateFormat: resolveDateFormat,
        dateFormatRuleName: dateFormatRuleName,
        isIsoDate: isIsoDate,
        isIsoDatePartial: isIsoDatePartial,
        isIsoYear: isIsoYear,
        isIsoYearMonth: isIsoYearMonth,
        isIsoDateTime: isIsoDateTime
    };
})();
