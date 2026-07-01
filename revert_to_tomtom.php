<?php
$content = file_get_contents('d:/New folder/htdocs/restaurant_medusa/admintest/driver_dashboard.php');

// 1. Remove Google Maps API script and add OpenLayers scripts
$content = preg_replace(
    '/<!-- Google Maps API -->\s*<script src="https:\/\/maps\.googleapis\.com\/maps\/api\/js\?key=.*?<\/script>/s',
    '<script src="https://cdn.jsdelivr.net/npm/ol@v10.3.1/dist/ol.js"></script><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v10.3.1/ol.css">',
    $content
);

// 2. Rewrite the javascript block
$scriptStart = strpos($content, '<script>', strpos($content, '<!-- Screen 2: Active Delivery -->'));
$scriptEnd = strrpos($content, '</script>');

$newScript = <<<'HTML'
<script>
        // Constants
        const RESTAURANT_LAT = 30.680322;
        const RESTAURANT_LNG = 76.719541;
        const TOMTOM_API_KEY = "qI82bUxco20qcXu2avJFVppor79rrqzM";

        // State
        let currentOrder = null;
        let map = null;
        let vectorSource = null;
        let routeSource = null;
        let driverFeature = null;
        let restaurantFeature = null;
        let customerFeature = null;
        let watchId = null;
        let currentLat = null;
        let currentLng = null;

        function createMarkerStyle(color, size) {
            return new ol.style.Style({
                image: new ol.style.Circle({
                    radius: size,
                    fill: new ol.style.Fill({ color: color }),
                    stroke: new ol.style.Stroke({ color: '#fff', width: 2 })
                })
            });
        }

        // Initialize Map
        function initMap() {
            if (map) return;
            
            routeSource = new ol.source.Vector();
            vectorSource = new ol.source.Vector();
            
            map = new ol.Map({
                target: 'map-container',
                layers: [
                    new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: `https://api.tomtom.com/map/1/tile/basic/main/{z}/{x}/{y}.png?key=${TOMTOM_API_KEY}`,
                            attributions: '&copy; TomTom'
                        })
                    }),
                    new ol.layer.Vector({
                        source: routeSource,
                        style: new ol.style.Style({
                            stroke: new ol.style.Stroke({
                                color: 'rgba(76, 175, 80, 0.8)',
                                width: 5
                            })
                        })
                    }),
                    new ol.layer.Vector({
                        source: vectorSource
                    })
                ],
                view: new ol.View({
                    center: ol.proj.fromLonLat([RESTAURANT_LNG, RESTAURANT_LAT]),
                    zoom: 13
                }),
                controls: []
            });

            restaurantFeature = new ol.Feature({
                geometry: new ol.geom.Point(ol.proj.fromLonLat([RESTAURANT_LNG, RESTAURANT_LAT]))
            });
            restaurantFeature.setStyle(createMarkerStyle('#dfba86', 10));
            vectorSource.addFeature(restaurantFeature);
        }

        // Fetch Order API
        async function fetchOrder() {
            const orderId = document.getElementById('orderInput').value.trim();
            if (!orderId) { alert("Please enter an Order ID"); return; }

            try {
                const response = await fetch(`../api/driver_api.php?action=fetch_order&order_number=${orderId}`);
                const result = await response.json();

                if (result.success) {
                    currentOrder = result.order;
                    startDeliveryUI();
                } else {
                    alert(result.message);
                }
            } catch (err) {
                alert("Network error fetching order.");
            }
        }

        // Setup Delivery UI
        async function startDeliveryUI() {
            document.getElementById('screen-entry').classList.remove('active');
            document.getElementById('screen-delivery').classList.add('active');

            // Populate UI
            document.getElementById('uiOrderNumber').textContent = currentOrder.order_number;
            document.getElementById('uiAmount').textContent = '₹' + currentOrder.total_amount;
            document.getElementById('uiPayment').textContent = currentOrder.payment_method;
            document.getElementById('uiCustomerName').textContent = currentOrder.customer_name;
            document.getElementById('uiCustomerAddress').textContent = currentOrder.delivery_address;
            document.getElementById('uiCustomerPhone').href = 'tel:' + currentOrder.customer_phone;
            updateStatusBadge(currentOrder.status);

            // Init Map and Tracking
            initMap();
            setTimeout(() => { map.updateSize(); }, 500); 
            startGPSTracking();

            // Geocode Customer Address
            try {
                // Keep the existing geocoding key injection at top of page, use it here
                const apiKey = "<?= htmlspecialchars($geocodeApiKey ?? $mapsApiKey) ?>";
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
                }
                
                customerFeature = new ol.Feature({
                    geometry: new ol.geom.Point(ol.proj.fromLonLat([cLng, cLat]))
                });
                customerFeature.setStyle(createMarkerStyle('#4CAF50', 10));
                vectorSource.addFeature(customerFeature);

                // Setup Route
                setupRoute(cLat, cLng);
            } catch (e) {
                console.error("Geocoding failed", e);
            }
        }

        // GPS Tracking
        function startGPSTracking() {
            if ("geolocation" in navigator) {
                watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        currentLat = position.coords.latitude;
                        currentLng = position.coords.longitude;

                        const coord = ol.proj.fromLonLat([currentLng, currentLat]);

                        if (!driverFeature) {
                            driverFeature = new ol.Feature({
                                geometry: new ol.geom.Point(coord)
                            });
                            driverFeature.setStyle(createMarkerStyle('#2196F3', 12));
                            vectorSource.addFeature(driverFeature);
                        } else {
                            driverFeature.getGeometry().setCoordinates(coord);
                        }
                    },
                    (error) => { console.warn("GPS Error", error); },
                    { enableHighAccuracy: true, maximumAge: 10000, timeout: 5000 }
                );
            }
        }

        // OpenLayers Routing Setup (using OSRM API)
        async function setupRoute(custLat, custLng) {
            routeSource.clear();
            const start = `${RESTAURANT_LNG},${RESTAURANT_LAT}`;
            const end = `${custLng},${custLat}`;
            
            try {
                const res = await fetch(`https://router.project-osrm.org/route/v1/driving/${start};${end}?overview=full&geometries=geojson`);
                const data = await res.json();
                
                if(data.routes && data.routes.length > 0) {
                    const route = data.routes[0].geometry;
                    const format = new ol.format.GeoJSON();
                    const routeFeature = format.readFeature({
                        type: 'Feature',
                        geometry: route
                    }, {
                        dataProjection: 'EPSG:4326',
                        featureProjection: 'EPSG:3857'
                    });
                    routeSource.addFeature(routeFeature);
                    
                    const extent = routeSource.getExtent();
                    map.getView().fit(extent, { padding: [50, 50, 50, 50], maxZoom: 16 });
                }
            } catch(e) {
                console.error("Routing error", e);
            }
        }

        // Action: Picked Up
        async function markPickedUp() {
            try {
                const res = await fetch('../api/driver_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'update_status', order_number: currentOrder.order_number, status: 'Picked Up' })
                });
                const data = await res.json();
                
                if (data.success) {
                    updateStatusBadge('Picked Up');
                    document.getElementById('btnPickup').style.display = 'none';
                    document.getElementById('btnDeliver').style.display = 'flex';
                } else {
                    alert(data.message);
                }
            } catch (err) { alert("Error updating status"); }
        }

        // Action: Delivered
        async function markDelivered() {
            try {
                const res = await fetch('../api/driver_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'update_status', order_number: currentOrder.order_number, status: 'Delivered', lat: currentLat, lng: currentLng })
                });
                const data = await res.json();
                
                if (data.success) {
                    alert("Delivery completed successfully!");
                    resetDashboard();
                } else {
                    alert(data.message);
                }
            } catch (err) { alert("Error updating status"); }
        }

        // Action: SOS
        async function triggerSOS() {
            if (!confirm("CRITICAL: Send Emergency SOS Alert to Admin?")) return;
            try {
                const res = await fetch('../api/driver_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        action: 'sos_alert', 
                        order_number: currentOrder ? currentOrder.order_number : 'N/A',
                        lat: currentLat,
                        lng: currentLng
                    })
                });
                const data = await res.json();
                if (data.success) {
                    alert("SOS Alert Sent! The restaurant has been notified of your location.");
                } else {
                    alert("Error sending SOS: " + data.message);
                }
            } catch (err) { alert("Error sending SOS."); }
        }

        function updateStatusBadge(status) {
            const badge = document.getElementById('uiStatus');
            badge.textContent = status;
            if (status === 'Picked Up' || status === 'Out for Delivery') {
                badge.style.backgroundColor = 'rgba(33,150,243,0.2)';
                badge.style.color = '#2196F3';
            } else {
                badge.style.backgroundColor = 'rgba(76, 175, 80, 0.2)';
                badge.style.color = 'var(--primary)';
            }
        }

        function resetDashboard() {
            if (watchId) navigator.geolocation.clearWatch(watchId);
            if (vectorSource) vectorSource.clear();
            if (routeSource) routeSource.clear();
            
            document.getElementById('screen-delivery').classList.remove('active');
            document.getElementById('screen-entry').classList.add('active');
            document.getElementById('orderInput').value = '';
            document.getElementById('btnPickup').style.display = 'flex';
            document.getElementById('btnDeliver').style.display = 'none';
            currentOrder = null;
        }

        async function logout() {
            if (!confirm("Log out of Driver Portal?")) return;
            try {
                await fetch('../api/logout.php');
                window.location.href = '../login.html';
            } catch(e) { window.location.href = '../login.html'; }
        }
    </script>
HTML;

$finalContent = substr($content, 0, $scriptStart) . $newScript . substr($content, $scriptEnd + 9);
file_put_contents('d:/New folder/htdocs/restaurant_medusa/admintest/driver_dashboard.php', $finalContent);
echo "Reverted!";
?>
