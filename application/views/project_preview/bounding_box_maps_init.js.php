<script>
(function () {
	function initPreviewBoundingBoxMaps() {
		if (typeof L === 'undefined' || typeof BoundingBoxUtil === 'undefined') {
			return;
		}
		var nodes = document.querySelectorAll('[data-preview-bbox]');
		for (var i = 0; i < nodes.length; i++) {
			var el = nodes[i];
			if (el.getAttribute('data-preview-bbox-initialized') === '1') {
				continue;
			}
			var west = parseFloat(el.getAttribute('data-west'));
			var east = parseFloat(el.getAttribute('data-east'));
			var south = parseFloat(el.getAttribute('data-south'));
			var north = parseFloat(el.getAttribute('data-north'));
			if ([west, east, south, north].some(function (v) { return isNaN(v); }) || south >= north) {
				continue;
			}
			el.setAttribute('data-preview-bbox-initialized', '1');
			var map = L.map(el, {
				center: [20, 0],
				zoom: 2,
				minZoom: 1,
				maxZoom: 10,
				scrollWheelZoom: false,
				worldCopyJump: true
			});
			var tiles = BoundingBoxUtil.previewMapTileLayer(10);
			L.tileLayer(tiles.url, tiles.options).addTo(map);
			var bounds = BoundingBoxUtil.leafletBoundsFromIso(west, east, south, north);
			L.rectangle(bounds, { color: '#3388ff', weight: 2, fillOpacity: 0.15 }).addTo(map);
			map.whenReady(function () {
				map.invalidateSize();
				map.fitBounds(bounds, { padding: [12, 12], maxZoom: 10 });
			});
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPreviewBoundingBoxMaps);
	} else {
		initPreviewBoundingBoxMaps();
	}
})();
</script>
