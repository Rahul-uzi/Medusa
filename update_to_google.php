<?php
$content = file_get_contents('d:/New folder/htdocs/restaurant_medusa/admintest/driver_dashboard.php');

// 1. Remove OpenLayers scripts and add Google Maps API script
$content = preg_replace(
    '/<script src="https:\/\/cdn\.jsdelivr\.net\/npm\/ol@v10\.3\.1\/dist\/ol\.js"><\/script>\s*<link rel="stylesheet" href="https:\/\/cdn\.jsdelivr\.net\/npm\/ol@v10\.3\.1\/ol\.css">/',
    '<!-- Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($mapsApiKey) ?>&callback=initMap" async defer></script>',
    $content
);

// Remove the OpenLayers CSS override if present
$content = str_replace('.ol-zoom { display: none; } .leaflet-routing-container {', '', $content);
$content = str_replace('<!-- Leaflet & Routing -->', '', $content);

// 2. Rewrite the javascript block
$scriptStart = strpos($content, '<script>', strpos($content, '<!-- Screen 2: Active Delivery -->'));
$scriptEnd = strrpos($content, '</script>');

$newScript = <<<'HTML'
<script>
        // Constants
        const RESTAURANT_LAT = 30.680322;
        const RESTAURANT_LNG = 76.719541;

        // State
        let currentOrder = null;
        let map = null;
        let directionsService = null;
        let directionsRenderer = null;
        let driverMarker = null;
        let restaurantMarker = null;
        let customerMarker = null;
        let watchId = null;
        let currentLat = null;
        let currentLng = null;

        // Initialize Map (called by Google Maps API callback, or manually if API loads before initMap is needed)
        function initMap() {
            // Wait for map container to be visible before fully initializing
            const mapContainer = document.getElementById('map-container');
            if (mapContainer && mapContainer.offsetParent !== null && typeof google !== 'undefined' && !map) {
                map = new google.maps.Map(mapContainer, {
                    center: { lat: RESTAURANT_LAT, lng: RESTAURANT_LNG },
                    zoom: 13,
                    disableDefaultUI: true,
                    styles: [
                        { elementType: "geometry", stylers: [{ color: "#242f3e" }] },
                        { elementType: "labels.text.stroke", stylers: [{ color: "#242f3e" }] },
                        { elementType: "labels.text.fill", stylers: [{ color: "#746855" }] },
                        {
                            featureType: "road",
                            elementType: "geometry",
                            stylers: [{ color: "#38414e" }],
                        },
                        {
                            featureType: "road",
                            elementType: "geometry.stroke",
                            stylers: [{ color: "#212a37" }],
                        },
                        {
                            featureType: "road.highway",
                            elementType: "geometry",
                            stylers: [{ color: "#746855" }],
                        },
                        {
                            featureType: "water",
                            elementType: "geometry",
                            stylers: [{ color: "#17263c" }],
                        }
                    ],
                });

                directionsService = new google.maps.DirectionsService();
                directionsRenderer = new google.maps.DirectionsRenderer({
                    map: map,
                    suppressMarkers: true,
                    polylineOptions: { strokeColor: "#4CAF50", strokeWeight: 5 }
                });

                restaurantMarker = new google.maps.Marker({
                    position: { lat: RESTAURANT_LAT, lng: RESTAURANT_LNG },
                    map: map,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 8,
                        fillColor: "#dfba86",
                        fillOpacity: 1,
                        strokeColor: "#ffffff",
                        strokeWeight: 2,
                    },
                    title: "Restaurant Medusa"
                });
            }
        }

        // Make initMap globally available for the Google Maps callback
        window.initMap = initMap;

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
            if (typeof google !== 'undefined') {
                initMap();
                // Trigger a resize event to ensure Google Maps renders correctly
                if (map) google.maps.event.trigger(map, 'resize');
            }
            startGPSTracking();

            // Geocode Customer Address
            try {
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
                
                if (map) {
                    customerMarker = new google.maps.Marker({
                        position: { lat: cLat, lng: cLng },
                        map: map,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 8,
                            fillColor: "#4CAF50",
                            fillOpacity: 1,
                            strokeColor: "#ffffff",
                            strokeWeight: 2,
                        },
                        title: "Customer"
                    });
                    
                    // Setup Route
                    setupRoute(cLat, cLng);
                }
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

                        if (map) {
                            if (!driverMarker) {
                                driverMarker = new google.maps.Marker({
                                    position: { lat: currentLat, lng: currentLng },
                                    map: map,
                                    icon: {
                                        path: google.maps.SymbolPath.CIRCLE,
                                        scale: 10,
                                        fillColor: "#2196F3",
                                        fillOpacity: 1,
                                        strokeColor: "#ffffff",
                                        strokeWeight: 2,
                                    },
                                    title: "Driver"
                                });
                            } else {
                                driverMarker.setPosition({ lat: currentLat, lng: currentLng });
                            }
                        }
                    },
                    (error) => { console.warn("GPS Error", error); },
                    { enableHighAccuracy: true, maximumAge: 10000, timeout: 5000 }
                );
            }
        }

        // Google Maps Routing Setup
        function setupRoute(custLat, custLng) {
            if (!directionsService || !directionsRenderer) return;

            const request = {
                origin: { lat: RESTAURANT_LAT, lng: RESTAURANT_LNG },
                destination: { lat: custLat, lng: custLng },
                travelMode: google.maps.TravelMode.DRIVING
            };

            directionsService.route(request, function(response, status) {
                if (status === google.maps.DirectionsStatus.OK) {
                    directionsRenderer.setDirections(response);
                } else {
                    console.error("Directions request failed due to " + status);
                }
            });
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
            if (directionsRenderer) directionsRenderer.setDirections({routes: []});
            if (customerMarker) customerMarker.setMap(null);
            
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
echo "Replaced!";
?>
