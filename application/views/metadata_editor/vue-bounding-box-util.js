/**
 * Geographic bounding-box helpers.
 *
 * Leaflet can return longitudes outside [-180, 180] after panning past the
 * dateline. ISO 19115 stores west/east in that range and uses west > east to
 * mean the box crosses the antimeridian.
 */
var BoundingBoxUtil = (function () {
    function isFiniteNumber(value) {
        return typeof value === 'number' && isFinite(value);
    }

    /**
     * Wrap longitude to [-180, 180], keeping 180 as 180 (Leaflet wrapNum includeMax).
     */
    function wrapLongitude(lng) {
        if (!isFiniteNumber(lng)) {
            return NaN;
        }
        if (lng === 180) {
            return 180;
        }
        return ((lng + 180) % 360 + 360) % 360 - 180;
    }

    /**
     * Convert Leaflet unwrapped west/east (west <= east in raw space) to ISO pair.
     * west > east after wrap means the box crosses the antimeridian.
     */
    function isoLongitudesFromUnwrapped(west, east) {
        return {
            west: wrapLongitude(west),
            east: wrapLongitude(east)
        };
    }

    /**
     * Convert an ISO pair to Leaflet-unwrapped longitudes with east >= west.
     */
    function unwrappedLongitudesFromIso(west, east) {
        west = wrapLongitude(west);
        east = wrapLongitude(east);
        if (west > east) {
            return { west: west, east: east + 360 };
        }
        return { west: west, east: east };
    }

    /**
     * Leaflet LatLngBounds corners: [[south, west], [north, east]].
     */
    function leafletBoundsFromIso(west, east, south, north) {
        var lng = unwrappedLongitudesFromIso(west, east);
        return [[south, lng.west], [north, lng.east]];
    }

    function longitudeIntervalsFromIso(west, east) {
        west = wrapLongitude(west);
        east = wrapLongitude(east);
        if (!isFiniteNumber(west) || !isFiniteNumber(east)) {
            return [];
        }
        if (west <= east) {
            return [[west, east]];
        }
        return [[west, 180], [-180, east]];
    }

    function mergeLongitudeIntervals(intervals) {
        if (!intervals.length) {
            return [];
        }
        var sorted = intervals.slice().sort(function (a, b) {
            return a[0] - b[0];
        });
        var merged = [sorted[0].slice()];
        for (var i = 1; i < sorted.length; i++) {
            var last = merged[merged.length - 1];
            if (sorted[i][0] <= last[1]) {
                last[1] = Math.max(last[1], sorted[i][1]);
            } else {
                merged.push(sorted[i].slice());
            }
        }
        return merged;
    }

    function readBox(box) {
        if (!box || typeof box !== 'object') {
            return null;
        }
        var west = Number(box.west != null ? box.west : box.westBoundLongitude);
        var east = Number(box.east != null ? box.east : box.eastBoundLongitude);
        var south = Number(box.south != null ? box.south : box.southBoundLatitude);
        var north = Number(box.north != null ? box.north : box.northBoundLatitude);
        if (!isFiniteNumber(west) || !isFiniteNumber(east) || !isFiniteNumber(south) || !isFiniteNumber(north)) {
            return null;
        }
        west = wrapLongitude(west);
        east = wrapLongitude(east);
        if (south < -90 || south > 90 || north < -90 || north > 90 || south > north) {
            return null;
        }
        return { west: west, east: east, south: south, north: north };
    }

    /**
     * Smallest-arc union of ISO bounding boxes. west > east means antimeridian.
     */
    function unionIsoBoxes(boxes) {
        var intervals = [];
        var souths = [];
        var norths = [];
        if (!Array.isArray(boxes)) {
            return null;
        }
        for (var i = 0; i < boxes.length; i++) {
            var box = readBox(boxes[i]);
            if (!box) {
                continue;
            }
            var parts = longitudeIntervalsFromIso(box.west, box.east);
            for (var j = 0; j < parts.length; j++) {
                intervals.push(parts[j]);
            }
            souths.push(box.south);
            norths.push(box.north);
        }
        if (!intervals.length) {
            return null;
        }

        var merged = mergeLongitudeIntervals(intervals);
        if (merged.length === 1 && merged[0][0] <= -180 && merged[0][1] >= 180) {
            return {
                west: -180,
                east: 180,
                south: Math.min.apply(null, souths),
                north: Math.max.apply(null, norths)
            };
        }

        var largestGap = -1;
        var largestGapAfterIndex = merged.length - 1;
        for (var k = 0; k < merged.length - 1; k++) {
            var gap = merged[k + 1][0] - merged[k][1];
            if (gap > largestGap) {
                largestGap = gap;
                largestGapAfterIndex = k;
            }
        }
        var wrapGap = (180 - merged[merged.length - 1][1]) + (merged[0][0] - (-180));
        if (wrapGap > largestGap) {
            largestGapAfterIndex = merged.length - 1;
        }

        var westOut;
        var eastOut;
        if (largestGapAfterIndex === merged.length - 1) {
            westOut = merged[0][0];
            eastOut = merged[merged.length - 1][1];
        } else {
            westOut = merged[largestGapAfterIndex + 1][0];
            eastOut = merged[largestGapAfterIndex][1];
        }

        return {
            west: westOut,
            east: eastOut,
            south: Math.min.apply(null, souths),
            north: Math.max.apply(null, norths)
        };
    }

    return {
        wrapLongitude: wrapLongitude,
        isoLongitudesFromUnwrapped: isoLongitudesFromUnwrapped,
        unwrappedLongitudesFromIso: unwrappedLongitudesFromIso,
        leafletBoundsFromIso: leafletBoundsFromIso,
        longitudeIntervalsFromIso: longitudeIntervalsFromIso,
        unionIsoBoxes: unionIsoBoxes
    };
})();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = BoundingBoxUtil;
}
