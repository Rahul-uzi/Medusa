<?php
/**
 * driver.php — Admin Live Fleet Tracking
 *
 * All modern APIs, zero deprecated warnings:
 *  • loading=async + late-binding callback
 *  • google.maps.importLibrary() for all libs
 *  • AdvancedMarkerElement (mapId required)
 *  • gmp-click event (not deprecated 'click')
 *  • Routes API v2 via compute_route.php (no DirectionsService)
 *  • geometry.spherical.computeHeading() for scooter rotation
 *  • geometry.encoding.decodePath() for polyline decode
 *  • ETA cache → no "Computing route..." flicker on poll
 */
require_once '../api/config.php';
$mapsApiKey = get_env_var('GOOGLE_MAPS_API_KEY');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Tracking – Medusa Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Late-binding wrapper: initFleetMap resolved at call-time, not parse-time -->
    <script>window.__fleetMapReady = function () { initFleetMap(); };</script>
    <script async
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapsApiKey); ?>&loading=async&callback=__fleetMapReady">
    </script>

    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0; padding: 0;
            font-family: 'Inter', sans-serif;
            background: #0d1117; color: #e2e8f0;
            display: flex; flex-direction: column;
            height: 100vh; overflow: hidden;
        }

        /* ── Header ─────────────────────────────────────── */
        .header {
            padding: 11px 20px;
            background: #161b22;
            display: flex; align-items: center; gap: 14px;
            border-bottom: 1px solid #30363d;
            z-index: 100; flex-shrink: 0;
        }
        .header-title {
            font-size: 16px; font-weight: 700;
            color: #e2e8f0; display: flex; align-items: center; gap: 9px;
            margin-right: auto;
        }
        .header-title i { color: #38bdf8; }
        .hdr-select {
            background: #21262d; color: #c9d1d9;
            border: 1px solid #30363d; border-radius: 6px;
            padding: 5px 10px; font-size: 12px;
            font-family: 'Inter', sans-serif;
            outline: none; cursor: pointer; transition: border-color .2s;
        }
        .hdr-select:hover { border-color: #58a6ff; }
        .traffic-label {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: #8b949e;
            cursor: pointer; user-select: none; white-space: nowrap;
        }
        .traffic-label input { accent-color: #38bdf8; width: 14px; height: 14px; cursor: pointer; }
        #last-updated { font-size: 11px; color: #6e7681; white-space: nowrap; }

        /* ── Layout ──────────────────────────────────────── */
        .main-content {
            display: flex; flex: 1;
            height: calc(100vh - 47px); overflow: hidden;
        }
        #map { flex: 1; height: 100%; }

        /* ── Sidebar ─────────────────────────────────────── */
        .sidebar {
            width: 330px; flex-shrink: 0;
            background: #0d1117;
            border-left: 1px solid #21262d;
            display: flex; flex-direction: column; overflow: hidden;
        }
        .sidebar-header {
            padding: 12px 16px;
            border-bottom: 1px solid #21262d;
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .7px; color: #6e7681;
            display: flex; align-items: center; justify-content: space-between;
        }
        .sidebar-count {
            background: #21262d; color: #58a6ff;
            font-size: 11px; font-weight: 700;
            padding: 2px 8px; border-radius: 10px;
        }
        .sidebar-body {
            flex: 1; overflow-y: auto;
            padding: 10px; display: flex; flex-direction: column; gap: 9px;
        }
        .sidebar-body::-webkit-scrollbar { width: 4px; }
        .sidebar-body::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }

        /* ── Driver Card ─────────────────────────────────── */
        .driver-card {
            background: #161b22; border: 1px solid #21262d;
            border-radius: 10px; padding: 13px; cursor: pointer;
            transition: border-color .2s, box-shadow .2s;
        }
        .driver-card:hover { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56,189,248,.1); }
        .driver-card.active {
            border-color: #38bdf8; box-shadow: 0 0 0 3px rgba(56,189,248,.15);
            background: #0d1f2e;
        }

        .dc-top { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
        .dc-avatar {
            width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #06b6d4, #0284c7);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 700; color: white;
            box-shadow: 0 2px 8px rgba(6,182,212,.3); position: relative;
        }
        .dc-avatar-dot {
            position: absolute; bottom: 1px; right: 1px;
            width: 10px; height: 10px; border-radius: 50%;
            background: #10b981; border: 2px solid #161b22;
        }
        .dc-info { flex: 1; min-width: 0; }
        .dc-customer {
            font-size: 13px; font-weight: 700; color: #f0f6fc;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .dc-order-num { font-size: 11px; color: #6e7681; margin-top: 1px; }

        .dc-row {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: #8b949e;
            margin-bottom: 5px; overflow: hidden;
        }
        .dc-row i { flex-shrink: 0; width: 14px; text-align: center; }
        .dc-row span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dc-driver-name { color: #cbd5e1; font-weight: 600; }
        .dc-unassigned {
            display: inline-flex; align-items: center; gap: 4px;
            color: #fbbf24; font-weight: 600;
            background: rgba(251,191,36,.1);
            border: 1px solid rgba(251,191,36,.25);
            border-radius: 4px; padding: 1px 7px; font-size: 11px;
        }

        /* ── ETA row ─────────────────────────────────────── */
        .dc-eta {
            display: flex; align-items: center; gap: 6px;
            font-size: 12px; color: #10b981;
            background: rgba(16,185,129,.08);
            border: 1px solid rgba(16,185,129,.15);
            border-radius: 5px; padding: 5px 8px; margin: 7px 0;
        }
        .dc-eta.calculating { color: #6e7681; background: rgba(255,255,255,.03); border-color: #21262d; }
        .dc-eta.error { color: #f59e0b; background: rgba(245,158,11,.07); border-color: rgba(245,158,11,.2); }

        /* ── Status badges ───────────────────────────────── */
        .dc-status {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 3px 9px; border-radius: 20px;
            font-size: 11px; font-weight: 600;
            border: 1px solid transparent;
        }
        /* Out for Delivery / Picked Up → cyan */
        .dc-status-delivery { background: rgba(56,189,248,.1); color: #38bdf8; border-color: rgba(56,189,248,.25); }
        /* Picked Up → orange */
        .dc-status-pickup   { background: rgba(251,146,60,.1);  color: #fb923c; border-color: rgba(251,146,60,.25); }
        /* Preparing / In Kitchen → amber */
        .dc-status-preparing{ background: rgba(251,191,36,.1);  color: #fbbf24; border-color: rgba(251,191,36,.25); }
        /* Delivered / Completed → green */
        .dc-status-delivered{ background: rgba(16,185,129,.1);  color: #10b981; border-color: rgba(16,185,129,.25); }
        /* Cancelled → red */
        .dc-status-cancelled{ background: rgba(239,68,68,.1);   color: #ef4444; border-color: rgba(239,68,68,.25); }
        /* Default → slate */
        .dc-status-default  { background: rgba(148,163,184,.08);color: #94a3b8; border-color: rgba(148,163,184,.2); }

        .dc-footer { display: flex; justify-content: space-between; align-items: center; }
        .call-btn {
            display: inline-flex; align-items: center; gap: 5px;
            background: rgba(16,185,129,.1); color: #10b981;
            border: 1px solid rgba(16,185,129,.3);
            border-radius: 6px; padding: 4px 10px;
            font-size: 11px; font-weight: 600; font-family: 'Inter', sans-serif;
            cursor: pointer; transition: all .2s;
        }
        .call-btn:hover { background: #10b981; color: #0d1117; }

        /* ── Empty state ─────────────────────────────────── */
        .empty-state { text-align: center; padding: 50px 20px; color: #6e7681; }
        .empty-state i { font-size: 30px; display: block; margin-bottom: 12px; opacity: .4; }
        .empty-state p { margin: 0; font-size: 13px; line-height: 1.6; }

        /* ── Restaurant marker ───────────────────────────── */
        /* No CSS animations — avoids content-visibility rendering warnings */
        .rest-marker-el { display: flex; flex-direction: column; align-items: center; pointer-events: none; }
        .rest-marker-pin {
            width: 42px; height: 42px; border-radius: 50%;
            background: linear-gradient(135deg, #dfba86, #b48530);
            border: 3px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,.5);
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .rest-marker-label {
            margin-top: 4px; background: rgba(15,15,15,.85);
            color: #dfba86; font-size: 10px; font-weight: 700;
            padding: 2px 7px; border-radius: 4px; white-space: nowrap;
            box-shadow: 0 1px 4px rgba(0,0,0,.4);
        }

        /*
         * ⚠ IMPORTANT: No CSS @keyframes / animation inside marker elements!
         * AdvancedMarkerElement wraps content in content-visibility:hidden when
         * off-screen. Any animation running inside triggers the browser warning:
         * "Rendering was performed in a subtree hidden by content-visibility"
         * We use static box-shadow rings instead of animated rings.
         */
        .drv-marker-wrap {
            position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .drv-scooter-icon {
            width: 48px; height: 48px;
            object-fit: contain;
            filter: drop-shadow(0 3px 5px rgba(0,0,0,.4));
            transition: transform 0.5s ease;
        }
        .drv-marker-label {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 4px; background: #10b981;
            color: #fff; font-size: 9px; font-weight: 700;
            padding: 2px 8px; border-radius: 10px;
            white-space: nowrap; max-width: 90px;
            overflow: hidden; text-overflow: ellipsis;
            box-shadow: 0 1px 4px rgba(0,0,0,.35);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">
            <i class="fa-solid fa-satellite-dish fa-beat-fade" style="--fa-beat-fade-opacity:.4;"></i>
            Live Fleet Tracking
        </div>

        <select class="hdr-select" id="map-type-select" onchange="changeMapType(this.value)">
            <option value="roadmap">🗺 Road Map</option>
            <option value="hybrid">🛰 Satellite</option>
            <option value="terrain">🏔 Terrain</option>
        </select>

        <label class="traffic-label">
            <input type="checkbox" id="traffic-toggle" onchange="toggleTraffic(this.checked)">
            <i class="fa-solid fa-traffic-light" style="color:#ef4444;"></i> Live Traffic
        </label>

        <span id="last-updated">Connecting...</span>
    </div>

    <div class="main-content">
        <div id="map"></div>
        <div class="sidebar">
            <div class="sidebar-header">
                <span><i class="fa-solid fa-motorcycle" style="margin-right:6px;"></i>Active Deliveries</span>
                <span class="sidebar-count" id="driver-count">0</span>
            </div>
            <div class="sidebar-body" id="sidebar-body">
                <div class="empty-state">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <p>Connecting to fleet...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ── Restaurant constants ──────────────────────────────────────
        const REST_LAT  = 30.681159778278612;
        const REST_LNG  = 76.72327041475536;



        // ── Global state ──────────────────────────────────────────────
        let map               = null;
        let AdvancedMarker    = null;   // AdvancedMarkerElement class
        let encoding          = null;   // geometry.encoding
        let spherical         = null;   // geometry.spherical (for computeHeading)
        let restaurantPin     = null;
        let trafficLayer      = null;

        // Per-driver state (keyed by order_number)
        let driverMarkers         = {};  // AdvancedMarkerElement instances
        let driverCircleEls       = {};  // The inner div whose transform we rotate
        let lastDriverPositions   = {};  // { lat, lng } — for heading calculation
        let routePolylines        = {};  // google.maps.Polyline instances
        let routeCache            = {};  // Cached {distance_km, duration_mins}
        let driverPingCount       = {};  // Tracking pings to set route heading on first render
        //   ↑ ETA cache is the KEY fix for "Computing route..." flickering.
        //     On every 5-second poll the sidebar is rebuilt, but we read
        //     from this cache instead of showing the spinner again.

        let activeOrder = null;
        let Polyline    = null;  // cached after first importLibrary

        // ── initFleetMap — called by Maps SDK after async load ────────
        async function initFleetMap() {
            const mapsLib    = await google.maps.importLibrary('maps');
            const markerLib  = await google.maps.importLibrary('marker');
            const geoLib     = await google.maps.importLibrary('geometry');

            Polyline       = mapsLib.Polyline;
            AdvancedMarker = markerLib.AdvancedMarkerElement;
            encoding       = geoLib.encoding;
            spherical      = geoLib.spherical;

            // mapId 'DEMO_MAP_ID' is Google's official testing ID for AdvancedMarkerElement.
            // Replace with a Cloud-based Map ID in Google Cloud Console for custom styling.
            map = new mapsLib.Map(document.getElementById('map'), {
                zoom:               13,
                center:             { lat: REST_LAT, lng: REST_LNG },
                mapId:              'DEMO_MAP_ID',
                mapTypeControl:     false,
                streetViewControl:  false,
                fullscreenControl:  false,
                zoomControlOptions: { position: google.maps.ControlPosition.RIGHT_BOTTOM },
            });

            // Restaurant pin
            const restEl = document.createElement('div');
            restEl.className = 'rest-marker-el';
            restEl.innerHTML = `<div class="rest-marker-pin">🍽️</div>
                                 <div class="rest-marker-label">Medusa Hub</div>`;
            restaurantPin = new AdvancedMarker({
                position: { lat: REST_LAT, lng: REST_LNG },
                map,
                title:   'Medusa Restaurant Hub',
                content: restEl,
            });

            await fetchActiveDrivers();
            setInterval(fetchActiveDrivers, 5000);
        }

        // ── Create or update the rotating scooter marker ──────────────
        // Adapts the Swiggy/Zomato pattern from user's code for multi-driver.
        function upsertDriverMarker(driver) {
            const orderNum = driver.order_number;
            driverPingCount[orderNum] = (driverPingCount[orderNum] || 0) + 1;

            const lat     = parseFloat(driver.driver_lat);
            const lng     = parseFloat(driver.driver_lng);
            const newPos  = { lat, lng };

            // Compute heading from last known position → new position
            const lastPos = lastDriverPositions[orderNum];
            const heading = (lastPos && spherical)
                ? spherical.computeHeading(
                    new google.maps.LatLng(lastPos.lat, lastPos.lng),
                    new google.maps.LatLng(lat, lng)
                  )
                : 0;

            if (!driverMarkers[orderNum]) {
                // ── First time: build marker DOM ──
                const outerRing = document.createElement('div');
                outerRing.className = 'drv-marker-wrap';

                const circleEl = document.createElement('img');
                circleEl.src = '../assets/icons/driver-scooter.svg';
                circleEl.alt = 'Delivery partner';
                circleEl.className = 'drv-scooter-icon';
                circleEl.style.transform = `rotate(${heading - 90}deg)`;

                const label = document.createElement('div');
                label.className = 'drv-marker-label';
                label.textContent = driver.driver_name || 'Driver';

                outerRing.appendChild(circleEl);
                outerRing.appendChild(label);

                driverCircleEls[orderNum] = circleEl;

                const marker = new AdvancedMarker({
                    position: newPos,
                    map,
                    title:   driver.driver_name || 'Delivery Partner',
                    content: outerRing,
                });

                // ✅ gmp-click — correct event for AdvancedMarkerElement
                marker.addListener('gmp-click', () => selectDriver(orderNum, lat, lng));

                driverMarkers[orderNum] = marker;
            } else {
                // ── Subsequent polls: update position and rotate ──
                driverMarkers[orderNum].position = newPos;

                const circleEl = driverCircleEls[orderNum];
                if (circleEl && lastPos && (lastPos.lat !== lat || lastPos.lng !== lng)) {
                    // Only rotate when the driver actually moved
                    circleEl.style.transform = `rotate(${heading - 90}deg)`;
                }
            }

            lastDriverPositions[orderNum] = { lat, lng };
        }

        // ── Determine status badge CSS class and icon ─────────────────
        function getStatusBadge(status) {
            const s     = (status || '').trim();
            const lower = s.toLowerCase();

            let cls  = 'dc-status-default';
            let icon = 'fa-circle-dot';

            if (lower.includes('out for delivery'))        { cls = 'dc-status-delivery'; icon = 'fa-truck-fast'; }
            else if (lower.includes('picked up'))          { cls = 'dc-status-pickup';   icon = 'fa-bag-shopping'; }
            else if (lower.includes('preparing') ||
                     lower.includes('kitchen'))            { cls = 'dc-status-preparing'; icon = 'fa-fire-burner'; }
            else if (lower.includes('delivered') ||
                     lower.includes('completed'))          { cls = 'dc-status-delivered'; icon = 'fa-circle-check'; }
            else if (lower.includes('cancelled') ||
                     lower.includes('canceled'))           { cls = 'dc-status-cancelled'; icon = 'fa-xmark'; }
            else if (lower.includes('ready') ||
                     lower.includes('packed'))             { cls = 'dc-status-pickup';   icon = 'fa-box-open'; }

            return `<span class="dc-status ${cls}"><i class="fa-solid ${icon}"></i> ${escHtml(s)}</span>`;
        }

        // ── Fetch live driver data from DB ────────────────────────────
        async function fetchActiveDrivers() {
            try {
                const res  = await fetch('../api/admin_tracker_api.php');
                const data = await res.json();

                document.getElementById('last-updated').textContent =
                    'Updated ' + new Date().toLocaleTimeString();

                if (!data.success) return;

                const drivers     = data.drivers;
                const sidebarBody = document.getElementById('sidebar-body');

                document.getElementById('driver-count').textContent = drivers.length;

                // ── No active drivers ──────────────────────────────────
                if (drivers.length === 0) {
                    sidebarBody.innerHTML = `
                        <div class="empty-state">
                            <i class="fa-solid fa-motorcycle"></i>
                            <p>No active deliveries right now.<br>
                               <span style="font-size:11px;opacity:.55;">Polling every 5 s...</span></p>
                        </div>`;
                    clearAllMarkers();
                    return;
                }

                // ── Prune stale markers (driver finished / off-duty) ───
                const activeNums = new Set(drivers.map(d => d.order_number));
                for (const num in driverMarkers) {
                    if (!activeNums.has(num)) {
                        driverMarkers[num].map = null;
                        delete driverMarkers[num];
                        delete driverCircleEls[num];
                        delete lastDriverPositions[num];
                        if (routePolylines[num]) {
                            routePolylines[num].setMap(null);
                            delete routePolylines[num];
                        }
                        delete routeCache[num];  // clear ETA cache for removed driver
                        delete driverPingCount[num]; // clear ping count
                        if (activeOrder === num) activeOrder = null;
                    }
                }

                // ── Build sidebar cards ────────────────────────────────
                let html = '';
                drivers.forEach(driver => {
                    const lat      = parseFloat(driver.driver_lat);
                    const lng      = parseFloat(driver.driver_lng);
                    const isActive = activeOrder === driver.order_number;
                    const initial  = (driver.driver_name || 'D').charAt(0).toUpperCase();
                    const hasPhone = !!driver.driver_phone;

                    // ✅ KEY FIX: read from cache — no flicker on every poll
                    const cached = routeCache[driver.order_number];
                    const etaClass = cached ? 'dc-eta' : 'dc-eta calculating';
                    const etaHtml  = cached
                        ? `<i class="fa-solid fa-route"></i>
                           <strong>${cached.distance_km} km</strong>
                           &nbsp;·&nbsp; ~${cached.duration_mins} min ETA`
                        : `<i class="fa-solid fa-spinner fa-spin"></i> Computing route...`;

                    html += `
                    <div class="driver-card ${isActive ? 'active' : ''}"
                         id="card-${escAttr(driver.order_number)}"
                         onclick="selectDriver('${escAttr(driver.order_number)}', ${lat}, ${lng})">

                        <div class="dc-top">
                            <div class="dc-avatar">
                                ${initial}
                                <div class="dc-avatar-dot"></div>
                            </div>
                            <div class="dc-info">
                                <div class="dc-customer">${escHtml(driver.customer_name)}</div>
                                <div class="dc-order-num"># ${escHtml(driver.order_number)}</div>
                            </div>
                        </div>

                        <div class="dc-row">
                            <i class="fa-solid fa-motorcycle" style="color:#38bdf8;"></i>
                            ${driver.driver_name
                                ? `<span class="dc-driver-name">${escHtml(driver.driver_name)}</span>`
                                : `<span class="dc-unassigned">
                                       <i class="fa-solid fa-triangle-exclamation"></i>
                                       No delivery partner assigned
                                   </span>`}
                        </div>

                        <div class="dc-row">
                            <i class="fa-solid fa-location-dot" style="color:#ef4444;"></i>
                            <span>${escHtml(driver.delivery_address)}</span>
                        </div>

                        <div id="eta-${escAttr(driver.order_number)}" class="${etaClass}">
                            ${etaHtml}
                        </div>

                        <div class="dc-footer">
                            ${getStatusBadge(driver.status)}
                            ${hasPhone
                                ? `<button class="call-btn"
                                       onclick="event.stopPropagation();window.location.href='tel:${escAttr(driver.driver_phone)}'">
                                       <i class="fa-solid fa-phone"></i> Call
                                   </button>`
                                : ''}
                        </div>
                    </div>`;

                    // Create / update rotating scooter marker on map
                    upsertDriverMarker(driver);
                });

                sidebarBody.innerHTML = html;

                // Fetch routes (will update routeCache + polylines)
                drivers.forEach(driver => computeAndDrawRoute(driver));

            } catch (err) {
                console.error('[FleetTracker] fetchActiveDrivers error:', err);
                document.getElementById('last-updated').textContent = '⚠ Connection lost – retrying...';
            }
        }

        // ── Compute route via PHP → Routes API v2 backend ─────────────
        async function computeAndDrawRoute(driver) {
            const params = new URLSearchParams({
                dlat: driver.driver_lat,
                dlng: driver.driver_lng,
                dest: driver.delivery_address,
            });

            try {
                const res  = await fetch(`../api/compute_route.php?${params}`);
                const data = await res.json();

                if (data.success && data.polyline) {
                    // Cache the result — prevents "Computing route..." on next poll
                    routeCache[driver.order_number] = {
                        distance_km:   data.distance_km,
                        duration_mins: data.duration_mins,
                    };

                    // Update the ETA element directly (sidebar may still be visible)
                    const etaEl = document.getElementById(`eta-${driver.order_number}`);
                    if (etaEl) {
                        etaEl.className = 'dc-eta';
                        etaEl.innerHTML = `<i class="fa-solid fa-route"></i>
                            <strong>${data.distance_km} km</strong>
                            &nbsp;·&nbsp; ~${data.duration_mins} min ETA`;
                    }

                    // Decode polyline using geometry.encoding (not deprecated)
                    const path = encoding.decodePath(data.polyline);

                    // Set start rotation guess using route direction on first load
                    if (path.length > 1 && driverCircleEls[driver.order_number] && driverPingCount[driver.order_number] <= 1) {
                        const routeHeading = spherical.computeHeading(path[0], path[1]);
                        driverCircleEls[driver.order_number].style.transform = `rotate(${routeHeading - 90}deg)`;
                    }

                    if (routePolylines[driver.order_number]) {
                        routePolylines[driver.order_number].setPath(path);
                    } else {
                        const line = new Polyline({
                            path,
                            geodesic:      true,
                            strokeColor:   '#06b6d4',
                            strokeOpacity: 0.85,
                            strokeWeight:  4,
                            map,
                        });
                        routePolylines[driver.order_number] = line;
                    }
                } else {
                    const etaEl = document.getElementById(`eta-${driver.order_number}`);
                    if (etaEl) {
                        etaEl.className = 'dc-eta error';
                        etaEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Route unavailable';
                    }
                }
            } catch (e) {
                console.error('[FleetTracker] computeAndDrawRoute error:', e);
            }
        }

        // ── Focus map on a selected driver ────────────────────────────
        function selectDriver(orderNumber, lat, lng) {
            activeOrder = orderNumber;
            document.querySelectorAll('.driver-card').forEach(c => c.classList.remove('active'));
            const card = document.getElementById(`card-${orderNumber}`);
            if (card) {
                card.classList.add('active');
                card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            map.panTo({ lat, lng });
            map.setZoom(15);
        }

        // ── Map layer controls ────────────────────────────────────────
        function changeMapType(type) { if (map) map.setMapTypeId(type); }

        function toggleTraffic(show) {
            if (!trafficLayer) {
                google.maps.importLibrary('maps').then(({ TrafficLayer }) => {
                    trafficLayer = new TrafficLayer();
                    trafficLayer.setMap(show ? map : null);
                });
            } else {
                trafficLayer.setMap(show ? map : null);
            }
        }

        // ── Clean up all markers and routes ──────────────────────────
        function clearAllMarkers() {
            Object.values(driverMarkers).forEach(m => { m.map = null; });
            driverMarkers       = {};
            driverCircleEls     = {};
            lastDriverPositions = {};
            Object.values(routePolylines).forEach(p => p.setMap(null));
            routePolylines      = {};
            routeCache          = {};
            driverPingCount     = {};
            activeOrder         = null;
        }

        // ── HTML escape helpers ───────────────────────────────────────
        function escHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
        function escAttr(str) {
            return String(str || '').replace(/['"<>&]/g, '');
        }
    </script>
</body>
</html>
