<?php
$content = file_get_contents('d:/New folder/htdocs/restaurant_medusa/admintest/driver_dashboard.php');

// Inject the PHP variable assignment at the top
$content = preg_replace(
    '/(<\?php\s+)/',
    "$1\$geocodeApiKey = get_env_var('GOOGLE_MAPS_GEOCODING_API_KEY', '');\n",
    $content,
    1
);

// Replace the Geocoding JS block
$oldGeo = <<<'JS'
            // Geocode Customer Address (using Nominatim for demo)
            try {
                const geoRes = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(currentOrder.delivery_address)}`);
                const geoData = await geoRes.json();
                
                let cLat = RESTAURANT_LAT + 0.02;
                let cLng = RESTAURANT_LNG + 0.02;

                if (geoData && geoData.length > 0) {
                    cLat = parseFloat(geoData[0].lat);
                    cLng = parseFloat(geoData[0].lon);
                } else {
                    console.warn('Geocoding failed. Using fallback.');
                }
JS;

$newGeo = <<<'JS'
            // Geocode Customer Address using Google Geocoding API
            try {
                const apiKey = "<?php echo htmlspecialchars($geocodeApiKey); ?>";
                let cLat = RESTAURANT_LAT + 0.02;
                let cLng = RESTAURANT_LNG + 0.02;
                
                if (apiKey) {
                    const geoRes = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(currentOrder.delivery_address)}&key=${apiKey}`);
                    const geoData = await geoRes.json();
                    
                    if (geoData.status === 'OK' && geoData.results.length > 0) {
                        cLat = geoData.results[0].geometry.location.lat;
                        cLng = geoData.results[0].geometry.location.lng;
                    } else {
                        console.warn('Google Geocoding failed. Using fallback.', geoData);
                    }
                } else {
                    console.warn('No Google Geocoding API Key found. Using fallback.');
                }
JS;

$content = str_replace($oldGeo, $newGeo, $content);

file_put_contents('d:/New folder/htdocs/restaurant_medusa/admintest/driver_dashboard.php', $content);
echo "Replaced!";
?>
