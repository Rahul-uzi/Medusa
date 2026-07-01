<?php
$geocodeApiKey = get_env_var('GOOGLE_MAPS_GEOCODING_API_KEY', '');
$mapsApiKey = get_env_var('GOOGLE_MAPS_API_KEY', '');
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'driver') {
    exit('Unauthorized access');
}
$driverName = htmlspecialchars($_SESSION['user_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver Partner Portal</title>
    <!-- Fonts and Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Maps -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapsApiKey); ?>&libraries=places&loading=async&callback=initGoogleMapsCallback" defer></script>
    <script>
        const originalWarn = console.warn;
        console.warn = function(...args) {
            if (args[0] && typeof args[0] === "string" && (
                args[0].includes("cdn.tailwindcss.com") ||
                args[0].includes("google.maps.Marker") ||
                args[0].includes("DirectionsService") ||
                args[0].includes("DirectionsRenderer") ||
                args[0].includes("deprecated")
            )) {
                return;
            }
            originalWarn.apply(console, args);
        };
    </script>

    <style>
        :root {
            --bg-color: #121212;
            --card-bg: #1e1e1e;
            --primary: #4CAF50;
            --primary-dark: #388E3C;
            --accent: #dfba86; /* Medusa Gold */
            --danger: #f44336;
            --text-main: #ffffff;
            --text-muted: #aaaaaa;
            --border: #333333;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Prevent body scroll, handle inside containers */
        }

        /* Top Header */
        header {
            background-color: var(--card-bg);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            z-index: 1000;
        }
        
        .header-logo {
            font-weight: 700;
            color: var(--accent);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: var(--danger);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }

        /* Screens */
        .screen {
            display: none;
            flex: 1;
            flex-direction: column;
            height: calc(100vh - 60px); /* Minus header */
        }
        .screen.active {
            display: flex;
        }

        /* Screen 1: Order Entry */
        .order-entry-container {
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
        }

        .welcome-text {
            font-size: 1.5rem;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .sub-text {
            color: var(--text-muted);
            margin-bottom: 40px;
            text-align: center;
        }

        .input-group {
            width: 100%;
            max-width: 400px;
            margin-bottom: 20px;
        }

        .order-input {
            width: 100%;
            padding: 18px 20px;
            font-size: 1.2rem;
            background-color: var(--card-bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            color: white;
            text-align: center;
            outline: none;
            transition: border-color 0.3s;
        }

        .order-input:focus {
            border-color: var(--accent);
        }

        .btn-large {
            width: 100%;
            max-width: 400px;
            padding: 18px;
            background-color: var(--accent);
            color: #000;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.1s, background-color 0.3s;
        }
        
        .btn-large:active {
            transform: scale(0.98);
        }

        /* Screen 2: Active Delivery */
        #map-container {
            height: 45vh;
            width: 100%;
            background-color: #222;
            position: absolute;
            top: 60px; /* Header height */
            left: 0;
            z-index: 1;
        }

        #nav-instruction-banner {
            position: absolute;
            top: 75px; /* Floats just below the main header, above the map */
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 480px;
            background-color: rgba(30, 30, 30, 0.95);
            border: 1px solid var(--accent);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .nav-icon-container {
            width: 40px;
            height: 40px;
            background-color: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000000;
            font-size: 1.25rem;
            flex-shrink: 0;
            box-shadow: 0 0 10px rgba(223, 186, 134, 0.4);
        }
        .nav-text-container {
            display: flex;
            flex-direction: column;
            gap: 2px;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            text-align: left;
        }
        .nav-instruction-dist {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent);
            font-weight: 700;
        }
        .nav-instruction-text {
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .delivery-details {
            background-color: var(--bg-color);
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            margin-top: calc(45vh - 20px); /* Position below map */
            z-index: 1000;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            overflow-y: auto;
            flex: 1;
            padding-bottom: 160px; /* Space for sticky buttons */
            position: relative;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background-color: rgba(76, 175, 80, 0.2);
            color: var(--primary);
        }
        
        .tracking-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--primary);
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(76, 175, 80, 0); }
            100% { box-shadow: 0 0 0 0 rgba(76, 175, 80, 0); }
        }

        .info-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            border: 1px solid var(--border);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .card-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .card-value {
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.4;
        }

        .btn-call {
            background-color: rgba(76, 175, 80, 0.15);
            color: var(--primary);
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
        }

        /* Sticky Bottom Actions */
        .action-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 15px 20px;
            background-color: var(--card-bg);
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            z-index: 2000;
        }

        .btn-action {
            flex: 1;
            padding: 15px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.05rem;
            border: none;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .btn-pickup { background-color: #2196F3; }
        .btn-deliver { background-color: var(--primary); }
        .btn-cancel { background-color: var(--danger); }
        .btn-sos { 
            background-color: transparent; 
            border: 2px solid var(--danger); 
            color: var(--danger); 
            flex: 0 0 auto; 
            width: 60px;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 3000;
            padding: 20px;
        }

        .modal-content {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 25px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            gap: 15px;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .modal-subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .modal-select {
            width: 100%;
            padding: 12px 16px;
            font-size: 1rem;
            background-color: var(--bg-color);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: white;
            outline: none;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .modal-select:focus {
            border-color: var(--accent);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-modal {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: opacity 0.2s;
        }

        .btn-modal:active {
            opacity: 0.8;
        }

        .btn-modal-close {
            background-color: transparent;
            border: 1px solid var(--border);
            color: var(--text-main);
        }

        .btn-modal-confirm {
            background-color: var(--danger);
            color: white;
        }
    </style>
</head>
<body>

    <header>
        <div class="header-logo">
            <i class="fa-solid fa-motorcycle"></i> Medusa Driver
        </div>
        <button class="logout-btn" onclick="logout()"><i class="fa-solid fa-right-from-bracket"></i></button>
    </header>

    <!-- Screen 1: Order Entry -->
    <div id="screen-entry" class="screen active">
        <div class="order-entry-container">
            <h1 class="welcome-text">Ready to Deliver, <?php echo $driverName; ?>?</h1>
            <p class="sub-text">Enter an Order ID to begin tracking.</p>

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

            <button class="btn-large" onclick="fetchOrder()">Start Delivery</button>
        </div>
    </div>

    <!-- Screen 2: Active Delivery -->
    <div id="screen-delivery" class="screen">
        <!-- Floating Navigation Turn-by-Turn Instruction Banner -->
        <div id="nav-instruction-banner" style="display: none;">
            <div class="nav-icon-container">
                <i class="fa-solid fa-arrow-up" id="navInstructionIcon"></i>
            </div>
            <div class="nav-text-container">
                <div class="nav-instruction-dist" id="navInstructionDist">---</div>
                <div class="nav-instruction-text" id="navInstructionText">Starting navigation...</div>
            </div>
        </div>
        <div id="map-container"></div>
        
        <div class="delivery-details">
            <div class="card-header">
                <div class="status-badge" id="uiStatus">Connecting...</div>
                <div class="tracking-indicator">
                    <div class="dot"></div> Live GPS
                </div>
            </div>

            <!-- Order Details -->
            <div class="info-card">
                <div class="card-header">
                    <span class="card-title">Order Details</span>
                    <span style="font-weight: 600; color: var(--accent);" id="uiOrderNumber">---</span>
                </div>
                <div style="display:flex; justify-content: space-between; margin-top: 10px;">
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Amount</div>
                        <div class="card-value" id="uiAmount">₹0.00</div>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Payment</div>
                        <div class="card-value" id="uiPayment">---</div>
                    </div>
                </div>
            </div>

            <!-- Restaurant Card -->
            <div class="info-card">
                <div class="card-header">
                    <span class="card-title">Pickup: Restaurant Medusa</span>
                    <a href="tel:+919427272798" class="btn-call"><i class="fa-solid fa-phone"></i> Call</a>
                </div>
                <div class="card-value" style="font-size: 0.95rem; font-weight: 400; color: var(--text-muted);">
                    SCO 44, 45, District One Market, Sector 67, Mohali
                </div>
            </div>

            <!-- Customer Card -->
            <div class="info-card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span class="card-title">Dropoff: <span id="uiCustomerName">---</span></span>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <a href="#" id="uiCustomerPhone" class="btn-call"><i class="fa-solid fa-phone"></i> Call</a>
                        <a href="#" id="uiMapsApp" target="_blank" class="btn-call" style="background-color: #2196F3; color: #fff; border: none; display: flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600;"><i class="fa-solid fa-map-location-dot"></i> Maps App</a>
                    </div>
                </div>
                <div class="card-value" id="uiCustomerAddress" style="font-size: 0.95rem; font-weight: 400; color: var(--text-muted);">
                    ---
                </div>
            </div>
        </div>

        <div class="action-panel">
            <button class="btn-action btn-sos" onclick="triggerSOS()"><i class="fa-solid fa-triangle-exclamation"></i></button>
            <div style="display: flex; flex-direction: column; gap: 10px; flex: 1;">
                <button class="btn-action btn-pickup" id="btnPickup" onclick="markPickedUp()"><i class="fa-solid fa-box"></i> Picked Up</button>
                <button class="btn-action btn-deliver" id="btnDeliver" onclick="markDelivered()" style="display:none;"><i class="fa-solid fa-check-circle"></i> Delivered</button>
                <button class="btn-action btn-cancel" id="btnCancel" onclick="cancelDelivery()"><i class="fa-solid fa-xmark"></i> Cancel Delivery</button>
            </div>
        </div>
    </div>

    <!-- Cancellation Reason Modal -->
    <div id="cancelModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <h3 class="modal-title">Reason for Cancellation</h3>
            <p class="modal-subtitle">Please select a reason for canceling this delivery:</p>
            
            <div class="reason-options">
                <select id="cancellationReason" class="modal-select">
                    <option value="" disabled selected>Select a reason...</option>
                    <option value="reason1">Customer Not Available</option>
                    <option value="reason2">Customer Requested Cancellation/Customer Refused Delivery</option>
                    <option value="reason3">Delivery Address Not Found</option>
                    <option value="reason4">Vehicle Breakdown / Emergency</option>
                    <option value="reason5">Unable to Contact Customer</option>
                </select>
            </div>
            
            <div class="modal-actions">
                <button class="btn-modal btn-modal-close" onclick="closeCancelModal()">Go Back</button>
                <button class="btn-modal btn-modal-confirm" id="btnConfirmCancel">Confirm Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Constants
        const DEFAULT_RESTAURANT_LAT = 30.680322;
        const DEFAULT_RESTAURANT_LNG = 76.719541;
        let RESTAURANT_LAT = 30.680322;
        let RESTAURANT_LNG = 76.719541;
        // State
        let currentOrder = null;
        let map = null;
        let directionsService = null;
        let directionsRenderer = null;
        let driverMarker = null;
        let restaurantMarker = null;
        let customerMarker = null;
        let customerLat = null;
        let customerLng = null;
        let lastRouteUpdate = 0;
        let watchId = null;
        let currentLat = null;
        let currentLng = null;
        let previousLat = null;
        let previousLng = null;
        let currentHeading = 0;

        function calculateHeading(lat1, lng1, lat2, lng2) {
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const lat1Rad = lat1 * Math.PI / 180;
            const lat2Rad = lat2 * Math.PI / 180;

            const y = Math.sin(dLng) * Math.cos(lat2Rad);
            const x = Math.cos(lat1Rad) * Math.sin(lat2Rad) -
                      Math.sin(lat1Rad) * Math.cos(lat2Rad) * Math.cos(dLng);
                      
            let brng = Math.atan2(y, x) * 180 / Math.PI;
            return (brng + 360) % 360;
        }

        let pendingMapInit = false;
        window.initGoogleMapsCallback = function() {
            if (pendingMapInit) {
                initMapAndFulfillRoute();
                pendingMapInit = false;
            }
        };

        // Initialize Map
        function initMap() {
            if (map) return;
            
            map = new google.maps.Map(document.getElementById('map-container'), {
                center: { lat: RESTAURANT_LAT, lng: RESTAURANT_LNG },
                zoom: 13,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });
            
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: true,
                polylineOptions: { strokeColor: '#4CAF50', strokeWeight: 5 }
            });

            restaurantMarker = new google.maps.Marker({
                position: { lat: RESTAURANT_LAT, lng: RESTAURANT_LNG },
                map: map,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 10,
                    fillColor: '#dfba86',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2
                },
                title: 'Restaurant'
            });
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
                    localStorage.setItem('active_delivery_order_number', currentOrder.order_number);
                    startDeliveryUI();
                } else {
                    alert(result.message);
                    localStorage.removeItem('active_delivery_order_number');
                }
            } catch (err) {
                alert("Network error fetching order.");
            }
        }

        // Setup Delivery UI
        async function startDeliveryUI() {
            document.getElementById('screen-entry').classList.remove('active');
            document.getElementById('screen-delivery').classList.add('active');

            RESTAURANT_LAT = parseFloat(document.getElementById('testPickupLat').value) || DEFAULT_RESTAURANT_LAT;
            RESTAURANT_LNG = parseFloat(document.getElementById('testPickupLng').value) || DEFAULT_RESTAURANT_LNG;

            // Populate UI
            document.getElementById('uiOrderNumber').textContent = currentOrder.order_number;
            document.getElementById('uiAmount').textContent = '₹' + currentOrder.total_amount;
            document.getElementById('uiPayment').textContent = currentOrder.payment_method;
            document.getElementById('uiCustomerName').textContent = currentOrder.customer_name;
            document.getElementById('uiCustomerAddress').textContent = currentOrder.delivery_address;
            document.getElementById('uiCustomerPhone').href = 'tel:' + currentOrder.customer_phone;
            updateStatusBadge(currentOrder.status);

            // Init Map and Tracking
            if (typeof google !== 'undefined' && google.maps && google.maps.Map) {
                initMapAndFulfillRoute();
            } else {
                pendingMapInit = true;
            }
            startGPSTracking();
        }

        function initMapAndFulfillRoute() {
            initMap();
            if (map && restaurantMarker) {
                const restPos = { lat: RESTAURANT_LAT, lng: RESTAURANT_LNG };
                map.setCenter(restPos);
                restaurantMarker.setPosition(restPos);
            }

            // Geocode Customer Address
            try {
                let cLat = RESTAURANT_LAT + 0.02;
                let cLng = RESTAURANT_LNG + 0.02;
                
                let forceDropLat = parseFloat(document.getElementById('testDropoffLat').value);
                let forceDropLng = parseFloat(document.getElementById('testDropoffLng').value);

                if (forceDropLat && forceDropLng) {
                    cLat = forceDropLat;
                    cLng = forceDropLng;
                    customerLat = cLat;
                    customerLng = cLng;
                    
                    customerMarker = new google.maps.Marker({
                        position: { lat: cLat, lng: cLng },
                        map: map,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 10,
                            fillColor: '#4CAF50',
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2
                        },
                        title: 'Customer'
                    });
                    setupRoute(cLat, cLng);
                } else {
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: currentOrder.delivery_address }, (results, status) => {
                        if (status === 'OK' && results[0]) {
                            cLat = results[0].geometry.location.lat();
                            cLng = results[0].geometry.location.lng();
                        } else {
                            console.warn('Google Maps Geocoding failed. Using fallback.');
                        }
                        customerLat = cLat;
                        customerLng = cLng;
                        
                        customerMarker = new google.maps.Marker({
                            position: { lat: cLat, lng: cLng },
                            map: map,
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 10,
                                fillColor: '#4CAF50',
                                fillOpacity: 1,
                                strokeColor: '#ffffff',
                                strokeWeight: 2
                            },
                            title: 'Customer'
                        });
                        setupRoute(cLat, cLng);
                    });
                }
            } catch (e) {
                console.error("Geocoding failed", e);
            }
        }

        // GPS Tracking
        function startGPSTracking() {
            if ("geolocation" in navigator) {
                const options = { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 };
                
                function successCallback(position) {
                    currentLat = position.coords.latitude;
                    currentLng = position.coords.longitude;
                    
                    // Prevent script errors if Google Maps API has not finished loading yet
                    if (typeof google === 'undefined' || !google.maps || !google.maps.SymbolPath || !map) {
                        return;
                    }
                    
                    let heading = position.coords.heading;
                    if (heading === null || heading === undefined || isNaN(heading)) {
                        if (previousLat !== null && previousLng !== null) {
                            const dLat = currentLat - previousLat;
                            const dLng = currentLng - previousLng;
                            const distSq = dLat*dLat + dLng*dLng;
                            if (distSq > 0.00000004) { // approx 2 meters threshold
                                currentHeading = calculateHeading(previousLat, previousLng, currentLat, currentLng);
                            }
                        }
                    } else {
                        currentHeading = heading;
                    }
                    
                    previousLat = currentLat;
                    previousLng = currentLng;
                    
                    const pos = { lat: currentLat, lng: currentLng };
                    const arrowIcon = {
                        path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW,
                        scale: 6,
                        fillColor: '#2196F3',
                        fillOpacity: 1,
                        strokeColor: '#ffffff',
                        strokeWeight: 2,
                        rotation: currentHeading || 0
                    };

                    if (!driverMarker) {
                        driverMarker = new google.maps.Marker({
                            position: pos,
                            map: map,
                            icon: arrowIcon,
                            title: 'Driver'
                        });
                    } else {
                        driverMarker.setPosition(pos);
                        driverMarker.setIcon(arrowIcon);
                    }

                    // Dynamically recalculate route from driver's current location to customer
                    if (customerLat && customerLng) {
                        const now = Date.now();
                        if (now - lastRouteUpdate > 10000) { // throttle updates to once every 10 seconds
                            setupRoute(customerLat, customerLng, currentLat, currentLng);
                            lastRouteUpdate = now;
                        }
                    }

                    // Send location to backend if an order is active
                    if (currentOrder && currentOrder.order_number) {
                        const payload = {
                            action: 'update_location',
                            order_number: currentOrder.order_number,
                            lat: currentLat,
                            lng: currentLng
                        };
                        if (remainingDurationSeconds !== null) {
                            payload.remaining_duration = remainingDurationSeconds;
                        }

                        fetch('../api/driver_api.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify(payload)
                        }).catch(err => console.error("Error updating location", err));
                    }
                }

                function errorCallback(error) {
                    console.log("GPS High Accuracy Error (falling back to standard accuracy):", error.message);
                    if (watchId) {
                        navigator.geolocation.clearWatch(watchId);
                    }
                    // Fallback to standard accuracy
                    watchId = navigator.geolocation.watchPosition(
                        successCallback,
                        (err) => { console.log("GPS Standard Accuracy Error:", err.message); },
                        { enableHighAccuracy: false, maximumAge: 10000, timeout: 15000 }
                    );
                }

                watchId = navigator.geolocation.watchPosition(successCallback, errorCallback, options);
            }
        }

        // Google Maps Routing Setup
        let remainingDurationSeconds = null;

        function setupRoute(custLat, custLng, originLat = null, originLng = null) {
            if (!directionsService || !directionsRenderer) return;
            
            const originCoord = (originLat !== null && originLng !== null)
                ? { lat: originLat, lng: originLng }
                : { lat: RESTAURANT_LAT, lng: RESTAURANT_LNG };

            const request = {
                origin: originCoord,
                destination: { lat: custLat, lng: custLng },
                travelMode: google.maps.TravelMode.DRIVING
            };
            
            directionsService.route(request, (result, status) => {
                if (status == 'OK') {
                    directionsRenderer.setDirections(result);
                    
                    // Turn-by-Turn Instruction Banner updates
                    try {
                        const route = result.routes[0];
                        if (route && route.legs && route.legs[0]) {
                            const leg = route.legs[0];
                            
                            // Save remaining travel duration in seconds
                            remainingDurationSeconds = leg.duration.value;
                            
                            // Update external maps app redirection link
                            const mapsAppBtn = document.getElementById('uiMapsApp');
                            if (mapsAppBtn) {
                                mapsAppBtn.style.display = 'flex';
                                mapsAppBtn.href = `https://www.google.com/maps/dir/?api=1&origin=${originCoord.lat},${originCoord.lng}&destination=${custLat},${custLng}&travelmode=driving`;
                            }

                            if (leg.steps && leg.steps.length > 0) {
                                const nextStep = leg.steps[0];
                                const banner = document.getElementById('nav-instruction-banner');
                                const distEl = document.getElementById('navInstructionDist');
                                const textEl = document.getElementById('navInstructionText');
                                const iconEl = document.getElementById('navInstructionIcon');
                                
                                banner.style.display = 'flex';
                                
                                // Display next step distance, total remaining distance, and ETA duration
                                distEl.textContent = `In ${nextStep.distance.text} · ${leg.distance.text} left (${leg.duration.text})`;
                                
                                // Strip HTML tags returned by Google Directions API
                                const cleanText = nextStep.instructions.replace(/<[^>]*>/g, '');
                                textEl.textContent = cleanText;
                                
                                // Map step directions to FontAwesome icons
                                const action = nextStep.maneuver || '';
                                let iconClass = 'fa-arrow-up'; // default straight
                                if (action.includes('turn-left') || cleanText.toLowerCase().includes('turn left')) {
                                    iconClass = 'fa-arrow-turn-up fa-flip-horizontal';
                                } else if (action.includes('turn-right') || cleanText.toLowerCase().includes('turn right')) {
                                    iconClass = 'fa-arrow-turn-up';
                                } else if (action.includes('merge') || action.includes('fork') || cleanText.toLowerCase().includes('keep')) {
                                    iconClass = 'fa-arrow-trend-up';
                                } else if (action.includes('roundabout') || cleanText.toLowerCase().includes('roundabout')) {
                                    iconClass = 'fa-arrows-spin';
                                } else if (cleanText.toLowerCase().includes('destination')) {
                                    iconClass = 'fa-flag-checkered';
                                }
                                
                                iconEl.className = `fa-solid ${iconClass}`;
                            }
                        }
                    } catch (e) {
                        console.error("Error setting turn-by-turn banner:", e);
                    }
                } else {
                    console.error("Routing error", status);
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
            if (driverMarker) { driverMarker.setMap(null); driverMarker = null; }
            if (restaurantMarker) { restaurantMarker.setMap(null); restaurantMarker = null; }
            if (customerMarker) { customerMarker.setMap(null); customerMarker = null; }
            
            document.getElementById('uiMapsApp').style.display = 'none';
            document.getElementById('nav-instruction-banner').style.display = 'none';
            localStorage.removeItem('active_delivery_order_number');
            
            document.getElementById('screen-delivery').classList.remove('active');
            document.getElementById('screen-entry').classList.add('active');
            document.getElementById('orderInput').value = '';
            document.getElementById('btnPickup').style.display = 'flex';
            document.getElementById('btnDeliver').style.display = 'none';
            currentOrder = null;
        }

        function cancelDelivery() {
            document.getElementById('cancelModal').style.display = 'flex';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Auto-fetch if there is an active order in localStorage
            const activeOrder = localStorage.getItem('active_delivery_order_number');
            if (activeOrder) {
                document.getElementById('orderInput').value = activeOrder;
                fetchOrder();
            }

            const confirmBtn = document.getElementById('btnConfirmCancel');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', async () => {
                    const reasonSelect = document.getElementById('cancellationReason');
                    const reasonVal = reasonSelect.value;
                    const reasonText = reasonSelect.options[reasonSelect.selectedIndex]?.text || '';
                    if (!reasonVal) {
                        alert("Please select a reason first.");
                        return;
                    }
                    
                    if (!currentOrder || !currentOrder.order_number) {
                        alert("No active order to cancel.");
                        return;
                    }
                    
                    try {
                        const res = await fetch('../api/driver_api.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'update_status',
                                order_number: currentOrder.order_number,
                                status: 'cancelled',
                                reason: reasonText
                            })
                        });
                        const data = await res.json();
                        
                        if (data.success) {
                            alert("Order cancelled successfully!");
                            closeCancelModal();
                            resetDashboard();
                        } else {
                            alert("Failed to cancel order: " + data.message);
                        }
                    } catch (err) {
                        alert("Network error updating status.");
                    }
                });
            }
        });

        async function logout() {
            if (!confirm("Log out of Driver Portal?")) return;
            localStorage.removeItem('active_delivery_order_number');
            try {
                await fetch('../api/logout.php');
                window.location.href = '../login.html';
            } catch(e) { window.location.href = '../login.html'; }
        }
    </script>
</body>
</html>

