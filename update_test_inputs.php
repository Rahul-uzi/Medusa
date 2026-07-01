<?php
$content = file_get_contents('d:/New folder/htdocs/restaurant_medusa/admintest/driver_dashboard.php');

// Insert HTML inputs
$htmlInput = <<<HTML
            <div class="input-group">
                <input type="text" id="orderInput" class="order-input" placeholder="e.g. ORD-123456" autocomplete="off">
            </div>
            
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <input type="text" id="testPickupLat" class="order-input" placeholder="Test Pickup Lat" style="padding: 10px; font-size: 14px;">
                <input type="text" id="testPickupLng" class="order-input" placeholder="Test Pickup Lng" style="padding: 10px; font-size: 14px;">
            </div>
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <input type="text" id="testDropoffLat" class="order-input" placeholder="Test Dropoff Lat" style="padding: 10px; font-size: 14px;">
                <input type="text" id="testDropoffLng" class="order-input" placeholder="Test Dropoff Lng" style="padding: 10px; font-size: 14px;">
            </div>
HTML;

$content = str_replace('<div class="input-group">
                <input type="text" id="orderInput" class="order-input" placeholder="e.g. ORD-123456" autocomplete="off">
            </div>', $htmlInput, $content);

// Update JS Constants
$jsConstOld = <<<JS
        // Constants
        const RESTAURANT_LAT = 30.680322;
        const RESTAURANT_LNG = 76.719541;
JS;

$jsConstNew = <<<JS
        // Constants
        const DEFAULT_RESTAURANT_LAT = 30.680322;
        const DEFAULT_RESTAURANT_LNG = 76.719541;
        let RESTAURANT_LAT = 30.680322;
        let RESTAURANT_LNG = 76.719541;
JS;

$content = str_replace($jsConstOld, $jsConstNew, $content);

// Update startDeliveryUI
$jsInitOld = <<<JS
            // Populate UI
            document.getElementById('uiOrderNumber').textContent = currentOrder.order_number;
JS;

$jsInitNew = <<<JS
            RESTAURANT_LAT = parseFloat(document.getElementById('testPickupLat').value) || DEFAULT_RESTAURANT_LAT;
            RESTAURANT_LNG = parseFloat(document.getElementById('testPickupLng').value) || DEFAULT_RESTAURANT_LNG;

            // Populate UI
            document.getElementById('uiOrderNumber').textContent = currentOrder.order_number;
JS;
$content = str_replace($jsInitOld, $jsInitNew, $content);

$jsUpdateMapOld = <<<JS
            // Init Map and Tracking
            initMap();
            setTimeout(() => { map.updateSize(); }, 500); 
            startGPSTracking();

            // Geocode Customer Address (using TomTom API)
            try {
                let cLat = RESTAURANT_LAT + 0.02;
                let cLng = RESTAURANT_LNG + 0.02;
                
                const geoRes = await fetch(`https://api.tomtom.com/search/2/geocode/\${encodeURIComponent(currentOrder.delivery_address)}.json?key=\${TOMTOM_API_KEY}`);
                const geoData = await geoRes.json();
                
                if (geoData.results && geoData.results.length > 0) {
                    cLat = geoData.results[0].position.lat;
                    cLng = geoData.results[0].position.lon;
                } else {
                    console.warn('TomTom Geocoding failed. Using fallback.', geoData);
                }
JS;

$jsUpdateMapNew = <<<JS
            // Init Map and Tracking
            initMap();
            if (map && restaurantFeature) {
                map.getView().setCenter(ol.proj.fromLonLat([RESTAURANT_LNG, RESTAURANT_LAT]));
                restaurantFeature.getGeometry().setCoordinates(ol.proj.fromLonLat([RESTAURANT_LNG, RESTAURANT_LAT]));
            }
            setTimeout(() => { map.updateSize(); }, 500); 
            startGPSTracking();

            // Geocode Customer Address
            try {
                let cLat = RESTAURANT_LAT + 0.02;
                let cLng = RESTAURANT_LNG + 0.02;
                
                let forceDropLat = parseFloat(document.getElementById('testDropoffLat').value);
                let forceDropLng = parseFloat(document.getElementById('testDropoffLng').value);

                if (forceDropLat && forceDropLng) {
                    cLat = forceDropLat;
                    cLng = forceDropLng;
                } else {
                    const geoRes = await fetch(`https://api.tomtom.com/search/2/geocode/\${encodeURIComponent(currentOrder.delivery_address)}.json?key=\${TOMTOM_API_KEY}`);
                    const geoData = await geoRes.json();
                    
                    if (geoData.results && geoData.results.length > 0) {
                        cLat = geoData.results[0].position.lat;
                        cLng = geoData.results[0].position.lon;
                    } else {
                        console.warn('TomTom Geocoding failed. Using fallback.', geoData);
                    }
                }
JS;

$content = str_replace($jsUpdateMapOld, $jsUpdateMapNew, $content);

file_put_contents('d:/New folder/htdocs/restaurant_medusa/admintest/driver_dashboard.php', $content);
echo "Replaced!";
?>
