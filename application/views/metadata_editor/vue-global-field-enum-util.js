// Helpers for template fields using registry codelists (scalar + object-array rows).

function globalCodelistsApiRootForEnums() {
    if (typeof globalCodelistsApiBase === 'function') {
        return globalCodelistsApiBase();
    }
    if (typeof globalCodelistCodesApiRoot === 'function') {
        return globalCodelistCodesApiRoot().replace(/\/codes\/\d+$/, '');
    }
    var base = '';
    if (typeof CI !== 'undefined') {
        if (CI.base_url) {
            base = String(CI.base_url).replace(/\/$/, '');
        } else if (CI.site_url) {
            base = String(CI.site_url).replace(/\/$/, '');
        }
    }
    return base + '/api/codelists';
}

function fieldVocabularySourceGlobal(field) {
    if (!field) {
        return false;
    }
    return String(field.vocabulary_source || '').toLowerCase() === 'global';
}

function fieldGlobalCodelistRegistryId(field) {
    if (!field) {
        return null;
    }
    var id = field.global_codelist_id;
    if (id === null || id === undefined || id === '') {
        return null;
    }
    var n = parseInt(id, 10);
    return (!isNaN(n) && n > 0) ? n : null;
}

function isFlatObjectArrayField(field) {
    if (!field || field.type !== 'array' || !Array.isArray(field.props) || field.props.length === 0) {
        return false;
    }
    for (var i = 0; i < field.props.length; i++) {
        var p = field.props[i];
        if (!p || typeof p !== 'object') {
            continue;
        }
        if (p.props && Array.isArray(p.props) && p.props.length > 0) {
            return false;
        }
    }
    return true;
}

var GLOBAL_CODELIST_CODE_PROP_CANDIDATES = ['abbreviation', 'code', 'iso', 'iso3', 'iso3166', 'id', 'country_code'];
var GLOBAL_CODELIST_LABEL_PROP_CANDIDATES = ['name', 'label', 'title', 'country_name'];

function guessGlobalCodelistPropMap(field) {
    var keys = [];
    if (!field || !Array.isArray(field.props)) {
        return { code: '', label: '' };
    }
    field.props.forEach(function (p) {
        if (p && p.key) {
            keys.push(String(p.key));
        }
    });
    var codeKey = '';
    var labelKey = '';
    keys.forEach(function (k) {
        var lower = k.toLowerCase();
        if (!codeKey && GLOBAL_CODELIST_CODE_PROP_CANDIDATES.indexOf(lower) >= 0) {
            codeKey = k;
        }
    });
    keys.forEach(function (k) {
        var lower = k.toLowerCase();
        if (!labelKey && GLOBAL_CODELIST_LABEL_PROP_CANDIDATES.indexOf(lower) >= 0) {
            labelKey = k;
        }
    });
    if (!codeKey && keys.length >= 2) {
        codeKey = keys[1];
    }
    if (!labelKey && keys.length >= 1) {
        labelKey = keys[0];
    }
    if (codeKey === labelKey && keys.length >= 2) {
        codeKey = keys[1];
        labelKey = keys[0];
    }
    return { code: codeKey, label: labelKey };
}

function getGlobalCodelistPropMap(field) {
    var map = { code: '', label: '' };
    if (!field) {
        return map;
    }
    if (field.global_codelist_map_code) {
        map.code = String(field.global_codelist_map_code);
    }
    if (field.global_codelist_map_label) {
        map.label = String(field.global_codelist_map_label);
    }
    if (!map.code || !map.label) {
        var guessed = guessGlobalCodelistPropMap(field);
        if (!map.code) {
            map.code = guessed.code;
        }
        if (!map.label) {
            map.label = guessed.label;
        }
    }
    return map;
}

function mapRegistryCodesToEnumRows(codes, map) {
    var rows = [];
    if (!Array.isArray(codes) || !map || !map.code || !map.label) {
        return rows;
    }
    codes.forEach(function (cr) {
        if (!cr || typeof cr !== 'object') {
            return;
        }
        var codeVal = cr.code != null ? String(cr.code).trim() : '';
        if (codeVal === '') {
            return;
        }
        var labelVal = cr.label != null ? String(cr.label).trim() : codeVal;
        var row = {};
        row[map.code] = codeVal;
        row[map.label] = labelVal;
        if (cr.id != null && cr.id !== '') {
            row.__rowKey = String(cr.id);
        } else {
            row.__rowKey = codeVal;
        }
        rows.push(row);
    });
    return rows;
}

function stripEnumPickerMeta(row) {
    if (!row || typeof row !== 'object') {
        return row;
    }
    var copy = JSON.parse(JSON.stringify(row));
    delete copy.__rowKey;
    delete copy.index__;
    return copy;
}

/**
 * Paginated registry codes for enum picker (metadata editor).
 * @returns {Promise<{total: number, rows: array}>}
 */
function fetchGlobalEnumRowsPage(field, options) {
    options = options || {};
    var registryId = fieldGlobalCodelistRegistryId(field);
    if (!fieldVocabularySourceGlobal(field) || !registryId) {
        return Promise.resolve({ total: 0, rows: [] });
    }
    var map = getGlobalCodelistPropMap(field);
    if (!map.code || !map.label) {
        return Promise.resolve({ total: 0, rows: [] });
    }
    var offset = options.offset != null ? Math.max(0, parseInt(options.offset, 10) || 0) : 0;
    var limit = options.limit != null ? parseInt(options.limit, 10) : 25;
    if (isNaN(limit) || limit <= 0) {
        limit = 25;
    }
    limit = Math.min(limit, 200);
    var params = { compact: 1, limit: limit, offset: offset };
    var search = options.search != null ? String(options.search).trim() : '';
    if (search.length >= 1) {
        params.search = search;
    }
    var url = globalCodelistsApiRootForEnums() + '/codes/' + registryId;
    return axios.get(url, { params: params, timeout: 60000 }).then(function (res) {
        var payload = res && res.data;
        if (!payload || payload.status === 'failed') {
            return { total: 0, rows: [] };
        }
        var raw = payload.codes;
        if (!Array.isArray(raw)) {
            raw = [];
        }
        var total = payload.total != null ? parseInt(payload.total, 10) : raw.length;
        if (isNaN(total)) {
            total = raw.length;
        }
        return { total: total, rows: mapRegistryCodesToEnumRows(raw, map) };
    }).catch(function () {
        return { total: 0, rows: [] };
    });
}

function fetchGlobalEnumRowsForField(field) {
    return fetchGlobalEnumRowsPage(field, { offset: 0, limit: 200, search: '' }).then(function (p) {
        return p.rows || [];
    });
}

function mapRegistryCodesToScalarEnum(codes) {
    var items = [];
    if (!Array.isArray(codes)) {
        return items;
    }
    codes.forEach(function (cr) {
        if (!cr || typeof cr !== 'object') {
            return;
        }
        var codeVal = cr.code != null ? String(cr.code).trim() : '';
        if (codeVal === '') {
            return;
        }
        var labelVal = cr.label != null ? String(cr.label).trim() : codeVal;
        var rowKey = cr.id != null && cr.id !== '' ? String(cr.id) : codeVal;
        items.push({ code: codeVal, label: labelVal, __rowKey: rowKey });
    });
    return items;
}

/**
 * Paginated registry codes as scalar { code, label } items (dropdown fields).
 * @returns {Promise<{total: number, items: array}>}
 */
function fetchGlobalScalarEnumPage(field, options) {
    options = options || {};
    var registryId = fieldGlobalCodelistRegistryId(field);
    if (!fieldVocabularySourceGlobal(field) || !registryId) {
        return Promise.resolve({ total: 0, items: [] });
    }
    var offset = options.offset != null ? Math.max(0, parseInt(options.offset, 10) || 0) : 0;
    var limit = options.limit != null ? parseInt(options.limit, 10) : 50;
    if (isNaN(limit) || limit <= 0) {
        limit = 50;
    }
    limit = Math.min(limit, 200);
    var params = { compact: 1, limit: limit, offset: offset };
    var search = options.search != null ? String(options.search).trim() : '';
    if (search.length >= 1) {
        params.search = search;
    }
    var url = globalCodelistsApiRootForEnums() + '/codes/' + registryId;
    return axios.get(url, { params: params, timeout: 60000 }).then(function (res) {
        var payload = res && res.data;
        if (!payload || payload.status === 'failed') {
            return { total: 0, items: [] };
        }
        var raw = payload.codes;
        if (!Array.isArray(raw)) {
            raw = [];
        }
        var total = payload.total != null ? parseInt(payload.total, 10) : raw.length;
        if (isNaN(total)) {
            total = raw.length;
        }
        return { total: total, items: mapRegistryCodesToScalarEnum(raw) };
    }).catch(function () {
        return { total: 0, items: [] };
    });
}

function fieldUsesGlobalScalarCodelist(field) {
    if (!field || field.type === 'array' || field.type === 'nested_array') {
        return false;
    }
    if (typeof fieldVocabularySourceGlobal !== 'function' || !fieldVocabularySourceGlobal(field)) {
        return false;
    }
    var registryId = fieldGlobalCodelistRegistryId(field);
    return !!registryId;
}

function parseStoredEnumCodeFromValue(stored) {
    if (stored === null || stored === undefined || stored === '') {
        return '';
    }
    if (typeof stored !== 'string') {
        return String(stored);
    }
    var match = stored.match(/\[(.*?)\]\s*$/);
    if (match && match.length > 1) {
        return match[1];
    }
    return stored;
}

function parseStoredEnumLabelFromValue(stored, code) {
    if (stored === null || stored === undefined || typeof stored !== 'string') {
        return code || '';
    }
    var match = stored.match(/^(.*)\s*\[(.*?)\]\s*$/);
    if (match && match.length > 2) {
        return match[1].trim();
    }
    return stored;
}

function formatScalarEnumStoredValue(field, enumItem, rawInput) {
    var mode = field && field.enum_store_column ? field.enum_store_column : 'both';
    if (!enumItem || typeof enumItem !== 'object') {
        return rawInput;
    }
    if (mode === 'code') {
        return enumItem.code;
    }
    if (mode === 'label') {
        return enumItem.label;
    }
    return enumItem.label + ' [' + enumItem.code + ']';
}

function fieldHasInlineEnumContent(field) {
    if (!field || !Array.isArray(field.enum) || field.enum.length === 0) {
        return false;
    }
    if (isFlatObjectArrayField(field)) {
        var map = getGlobalCodelistPropMap(field);
        var keys = field.props.map(function (p) { return p.key; });
        for (var i = 0; i < field.enum.length; i++) {
            var row = field.enum[i];
            if (!row || typeof row !== 'object') {
                continue;
            }
            for (var j = 0; j < keys.length; j++) {
                var k = keys[j];
                if (row[k] !== undefined && row[k] !== null && String(row[k]).trim() !== '') {
                    return true;
                }
            }
        }
        return false;
    }
    for (var n = 0; n < field.enum.length; n++) {
        var item = field.enum[n];
        if (typeof item === 'string' && String(item).trim() !== '') {
            return true;
        }
        if (item && typeof item === 'object') {
            if (String(item.code || '').trim() !== '' || String(item.label || '').trim() !== '') {
                return true;
            }
        }
    }
    return false;
}

function ensureGlobalCodelistPropMapOnField(field) {
    if (!field || !isFlatObjectArrayField(field)) {
        return;
    }
    var guessed = guessGlobalCodelistPropMap(field);
    if (!field.global_codelist_map_code && guessed.code) {
        Vue.set(field, 'global_codelist_map_code', guessed.code);
    }
    if (!field.global_codelist_map_label && guessed.label) {
        Vue.set(field, 'global_codelist_map_label', guessed.label);
    }
}

function clearGlobalCodelistPropMapOnField(field) {
    if (!field) {
        return;
    }
    Vue.delete(field, 'global_codelist_map_code');
    Vue.delete(field, 'global_codelist_map_label');
}
