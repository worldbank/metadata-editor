/**
 * Shared helpers for external resource dctype codes and sidebar grouping.
 */
var ResourceDctypeUtils = (function() {
    var DCTYPE_LABELS = {
        'doc/adm': 'Document, Administrative',
        'doc/anl': 'Document, Analytical',
        'doc/qst': 'Document, Questionnaire',
        'doc/ref': 'Document, Reference',
        'doc/rep': 'Document, Report',
        'doc/tec': 'Document, Technical',
        'doc/oth': 'Document, Other',
        'dat': 'Database',
        'dat/micro': 'Microdata File',
        'dat/geo': 'Data, Geospatial',
        'dat/table': 'Data, Table',
        'dat/doc': 'Data, Document',
        'map': 'Map',
        'prg': 'Program',
        'tbl': 'Table',
        'pic': 'Photo',
        'vid': 'Video',
        'aud': 'Audio',
        'web': 'Web Site',
        'unknown': 'Other'
    };

    var SORT_ORDER = [
        'doc/qst',
        'doc/rep',
        'doc/tec',
        'doc/anl',
        'doc/adm',
        'doc/ref',
        'doc/oth',
        'dat/micro',
        'dat',
        'dat/geo',
        'dat/table',
        'dat/doc',
        'map',
        'tbl',
        'prg',
        'pic',
        'vid',
        'aud',
        'web',
        'unknown'
    ];

    function dctypeCode(dctype) {
        if (dctype === null || dctype === undefined) {
            return 'unknown';
        }
        var text = String(dctype).trim();
        if (text === '') {
            return 'unknown';
        }
        var match = text.match(/\[([^\]]+)\]/);
        if (match && match[1]) {
            return match[1].trim().toLowerCase();
        }
        return text.toLowerCase();
    }

    function stripDctypeCodeSuffix(label) {
        return String(label).replace(/\s*\[[^\]]+\]\s*$/, '').trim();
    }

    function dctypeLabel(code, fallbackLabel) {
        if (code && DCTYPE_LABELS[code]) {
            return DCTYPE_LABELS[code];
        }
        if (fallbackLabel && String(fallbackLabel).trim() !== '') {
            return stripDctypeCodeSuffix(fallbackLabel);
        }
        return DCTYPE_LABELS.unknown;
    }

    function dctypeGroupKey(code) {
        var safe = dctypeCode(code) || 'unknown';
        return 'resource-dctype-' + safe.replace(/\//g, '-');
    }

    function compareDctypeCodes(a, b) {
        var ia = SORT_ORDER.indexOf(a);
        var ib = SORT_ORDER.indexOf(b);
        if (ia === -1) {
            ia = SORT_ORDER.length;
        }
        if (ib === -1) {
            ib = SORT_ORDER.length;
        }
        if (ia !== ib) {
            return ia - ib;
        }
        return String(a).localeCompare(String(b));
    }

    function buildResourceLeafNode(resource) {
        return {
            title: resource.title,
            type: 'resource',
            index: resource.id,
            file: 'file',
            key: 'resource-' + resource.id,
            resource: resource
        };
    }

    /**
     * Build v-treeview group nodes keyed by dctype for the project sidebar.
     *
     * @param {Array} resources
     * @return {Array}
     */
    function groupResourcesForTree(resources) {
        if (!resources || !resources.length) {
            return [];
        }

        var groups = {};
        var sampleLabels = {};

        resources.forEach(function(resource) {
            var code = dctypeCode(resource.dctype);
            if (!groups[code]) {
                groups[code] = [];
            }
            if (!sampleLabels[code] && resource.dctype) {
                sampleLabels[code] = resource.dctype;
            }
            groups[code].push(buildResourceLeafNode(resource));
        });

        return Object.keys(groups)
            .sort(compareDctypeCodes)
            .map(function(code) {
                var items = groups[code].slice().sort(function(a, b) {
                    return String(a.title).localeCompare(String(b.title));
                });
                return {
                    title: dctypeLabel(code, sampleLabels[code]),
                    type: 'resource-dctype-group',
                    file: 'folder',
                    key: dctypeGroupKey(code),
                    dctype_code: code,
                    items: items
                };
            });
    }

    function findResourceById(resources, resourceId) {
        if (!resources || resourceId === null || resourceId === undefined) {
            return null;
        }
        return resources.find(function(resource) {
            return String(resource.id) === String(resourceId);
        }) || null;
    }

    return {
        dctypeCode: dctypeCode,
        dctypeLabel: dctypeLabel,
        dctypeGroupKey: dctypeGroupKey,
        compareDctypeCodes: compareDctypeCodes,
        groupResourcesForTree: groupResourcesForTree,
        findResourceById: findResourceById
    };
})();
