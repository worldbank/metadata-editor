// Cell-level data notes (footnotes) for indicator chart/table views.
//
// Chart-data records carry `notes: { FOOTNOTE_COLUMN: [ids] }`; the response carries the text once in
// `notes: { id: { note, column? } }` and unknown ids in `metadata.notes_missing`.
// Markers are numbered 1..n per view in order of first appearance; each note is listed once.

var IndicatorNotesUtil = {

    /** Split a footnote cell ("N03; N04") into unique trimmed ids. */
    splitIds: function (value) {
        var out = [];
        if (value === null || value === undefined) {
            return out;
        }
        String(value).split(';').forEach(function (part) {
            var id = part.trim();
            if (id !== '' && out.indexOf(id) === -1) {
                out.push(id);
            }
        });
        return out;
    },

    /** Note ids of one record, unique, in footnote-column order then id order. */
    cellNoteIds: function (record) {
        var out = [];
        if (!record || !record.notes || typeof record.notes !== 'object') {
            return out;
        }
        Object.keys(record.notes).forEach(function (col) {
            var ids = record.notes[col];
            if (!Array.isArray(ids)) {
                return;
            }
            ids.forEach(function (id) {
                id = String(id).trim();
                if (id !== '' && out.indexOf(id) === -1) {
                    out.push(id);
                }
            });
        });
        return out;
    },

    /**
     * Numbering for one view.
     * @param {Object} dictionary  id => { note, column? } from the chart-data response
     * @returns {{ markers: function(string[]): number[], list: function(): Array }}
     */
    createNumbering: function (dictionary) {
        dictionary = dictionary && typeof dictionary === 'object' ? dictionary : {};
        var numberById = {};
        var order = [];
        return {
            /** Marker numbers for a cell's note ids (assigns new numbers on first use), ascending. */
            markers: function (ids) {
                var nums = [];
                (ids || []).forEach(function (id) {
                    if (!numberById[id]) {
                        order.push(id);
                        numberById[id] = order.length;
                    }
                    if (nums.indexOf(numberById[id]) === -1) {
                        nums.push(numberById[id]);
                    }
                });
                return nums.sort(function (a, b) { return a - b; });
            },
            /** Notes used in this view: [{ number, id, text, column, missing }] in marker order. */
            list: function () {
                return order.map(function (id, i) {
                    var entry = dictionary[id];
                    var missing = !entry || entry.note === undefined || entry.note === null || String(entry.note).trim() === '';
                    return {
                        number: i + 1,
                        id: id,
                        text: missing ? '' : String(entry.note),
                        column: entry && entry.column ? String(entry.column) : '',
                        missing: missing
                    };
                });
            }
        };
    },

    /** "1,3" for superscripts. */
    formatMarkers: function (numbers) {
        return (numbers || []).join(',');
    },

    /**
     * "n. text" lines for marker numbers, using a view's notes list (from numbering.list()).
     * @param {number[]} numbers
     * @param {Array} notesList
     * @param {string} missingText shown for ids without text
     * @param {number} [maxLength] truncate each text (0 = no limit)
     */
    noteLines: function (numbers, notesList, missingText, maxLength) {
        var byNumber = {};
        (notesList || []).forEach(function (n) {
            byNumber[n.number] = n;
        });
        return (numbers || []).map(function (num) {
            var note = byNumber[num];
            var text = note ? (note.missing ? missingText : note.text) : '';
            if (maxLength && text.length > maxLength) {
                text = text.slice(0, maxLength - 3) + '...';
            }
            return num + '. ' + text;
        });
    }
};
