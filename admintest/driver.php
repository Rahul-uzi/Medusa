<?php
require_once '../api/config.php';
$tomtomApiKey = "qI82bUxco20qcXu2avJFVppor79rrqzM";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Tracking</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- OpenLayers -->
    <script src="https://cdn.jsdelivr.net/npm/ol@v10.3.1/dist/ol.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v10.3.1/ol.css">

    <style>
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background: #0f172a; color: white; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .header { padding: 15px 20px; background: rgba(30, 41, 59, 0.9); display: flex; justify-content: space-between; align-items: center; z-index: 10; border-bottom: 1px solid #334155; }
        .header h2 { margin: 0; font-size: 18px; color: #e2e8f0; display: flex; align-items: center; gap: 10px; }
        .header h2 i { color: #38bdf8; }
        .main-content { display: flex; flex: 1; position: relative; height: calc(100vh - 55px); }
        #map { flex: 1; height: 100%; width: 100%; background: #0f172a; }
        
        .sidebar { width: 320px; background: rgba(15, 23, 42, 0.95); border-left: 1px solid #334155; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        .driver-card { background: #1e293b; padding: 15px; border-radius: 8px; border: 1px solid #334155; transition: all 0.2s; }
        .driver-card:hover { border-color: #38bdf8; }
        .driver-name { font-weight: 600; color: #f8fafc; margin-bottom: 5px; font-size: 15px; }
        .driver-order { font-size: 13px; color: #94a3b8; margin-bottom: 8px; }
        .driver-status { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: rgba(56, 189, 248, 0.1); color: #38bdf8; }
        
        .call-driver-btn { background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid #38bdf8; border-radius: 4px; padding: 4px 10px; font-size: 12px; cursor: pointer; transition: all 0.2s; font-weight: 500; }
        .call-driver-btn:hover { background: #38bdf8; color: #0f172a; }

        .ol-control button { background-color: rgba(30, 41, 59, 0.9) !important; color: #f8fafc !important; }
        .ol-control button:hover { background-color: rgba(56, 189, 248, 0.9) !important; }
        .ol-zoom { top: unset !important; bottom: 20px !important; left: 20px !important; }
    </style>
</head>
<body>
    <div class="header">
        <h2><i class="fa-solid fa-satellite-dish"></i> Live Fleet Tracking</h2>
        <div id="last-updated" style="font-size: 12px; color: #94a3b8;">Updating...</div>
    </div>

    <div class="main-content">
        <div id="map"></div>
        <div class="sidebar" id="sidebar">
            <!-- Active drivers will be populated here -->
            <div style="text-align: center; color: #64748b; margin-top: 50px;">
                <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i>
                <p>Loading Active Drivers...</p>
            </div>
        </div>
    </div>

    <script>
        const RESTAURANT_LAT = 30.680322;
        const RESTAURANT_LNG = 76.719541;
        const TOMTOM_API_KEY = "<?php echo $tomtomApiKey; ?>";

        let map;
        let driverSource = new ol.source.Vector();
        let driverFeatures = {}; 

        function initMap() {
            map = new ol.Map({
                target: 'map',
                layers: [
                    new ol.layer.Tile({
                        source: new ol.source.XYZ({
                            url: `https://api.tomtom.com/map/1/tile/basic/main/{z}/{x}/{y}.png?key=${TOMTOM_API_KEY}`,
                            attributions: '&copy; TomTom'
                        })
                    }),
                    new ol.layer.Vector({
                        source: driverSource,
                        zIndex: 10
                    })
                ],
                view: new ol.View({
                    center: ol.proj.fromLonLat([RESTAURANT_LNG, RESTAURANT_LAT]),
                    zoom: 12
                })
            });

            // Add Restaurant Marker
            const restaurantFeature = new ol.Feature({
                geometry: new ol.geom.Point(ol.proj.fromLonLat([RESTAURANT_LNG, RESTAURANT_LAT]))
            });
            restaurantFeature.setStyle(new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 10,
                    fill: new ol.style.Fill({ color: '#dfba86' }), // Medusa Gold
                    stroke: new ol.style.Stroke({ color: '#fff', width: 2 })
                })
            }));
            driverSource.addFeature(restaurantFeature);
        }

        function createDriverMarker() {
            return new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 8,
                    fill: new ol.style.Fill({ color: '#38bdf8' }), // Blue
                    stroke: new ol.style.Stroke({ color: '#fff', width: 2 })
                })
            });
        }

        async function fetchActiveDrivers() {
            try {
                const res = await fetch('../api/admin_tracker_api.php');
                const data = await res.json();
                
                const sidebar = document.getElementById('sidebar');
                document.getElementById('last-updated').textContent = "Last updated: " + new Date().toLocaleTimeString();

                if (data.success) {
                    const drivers = data.drivers;
                    let html = '';
                    
                    if (drivers.length === 0) {
                        html = `<div style="text-align: center; color: #64748b; margin-top: 50px;">
                                    <i class="fa-solid fa-motorcycle" style="font-size: 24px; margin-bottom: 10px;"></i>
                                    <p>No active deliveries right now.</p>
                                </div>`;
                    }

                    // Keep track of active order numbers to remove stale markers
                    const activeOrderNumbers = new Set(drivers.map(d => d.order_number));

                    // Remove stale markers
                    for (let orderNum in driverFeatures) {
                        if (!activeOrderNumbers.has(orderNum)) {
                            driverSource.removeFeature(driverFeatures[orderNum]);
                            delete driverFeatures[orderNum];
                        }
                    }

                    // Add/Update markers and sidebar
                    drivers.forEach(driver => {
                        const lat = parseFloat(driver.driver_lat);
                        const lng = parseFloat(driver.driver_lng);
                        
                        html += `
                            <div class="driver-card" onclick="focusMap(${lat}, ${lng})">
                                <div class="driver-name">${driver.customer_name} (Order: ${driver.order_number})</div>
                                <div class="driver-order" style="margin-bottom: 4px; color: #cbd5e1;"><i class="fa-solid fa-user-ninja"></i> Driver: ${driver.driver_name || 'Unassigned'}</div>
                                <div class="driver-order"><i class="fa-solid fa-location-dot"></i> ${driver.delivery_address}</div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 8px;">
                                    <div class="driver-status"><i class="fa-solid fa-truck-fast"></i> ${driver.status}</div>
                                    <button class="call-driver-btn" onclick="event.stopPropagation(); window.location.href='tel:${driver.driver_phone || '+919876543210'}'"><i class="fa-solid fa-phone"></i> Call Driver</button>
                                </div>
                            </div>
                        `;

                        const coord = ol.proj.fromLonLat([lng, lat]);
                        
                        if (driverFeatures[driver.order_number]) {
                            // Update existing marker
                            driverFeatures[driver.order_number].getGeometry().setCoordinates(coord);
                        } else {
                            // Create new marker
                            const feature = new ol.Feature({
                                geometry: new ol.geom.Point(coord)
                            });
                            feature.setStyle(createDriverMarker());
                            driverSource.addFeature(feature);
                            driverFeatures[driver.order_number] = feature;
                        }
                    });

                    sidebar.innerHTML = html;
                }
            } catch (err) {
                console.error("Error fetching drivers", err);
                document.getElementById('last-updated').textContent = "Connection lost. Retrying...";
            }
        }

        function focusMap(lat, lng) {
            map.getView().animate({
                center: ol.proj.fromLonLat([lng, lat]),
                zoom: 15,
                duration: 800
            });
        }

        window.onload = () => {
            initMap();
            fetchActiveDrivers();
            setInterval(fetchActiveDrivers, 5000); // Poll every 5 seconds
        };
    </script>
</body>
</html>
