<?php
session_start();

/* ==============================
   FETCH FIREBASE DATA
============================== */

$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingPlantedReports.json";

$response = file_get_contents($firebase_url);

if ($response === FALSE) {
    die("Error connecting to Firebase.");
}

$data = json_decode($response, true);

$reports = [];

if (!empty($data)) {
    foreach ($data as $id => $record) {

        $municipality = trim($record['municipality'] ?? '');
        $barangay     = trim($record['barangay'] ?? '');
        $numSeedlings = (int)($record['numSeedlings'] ?? 0);
        $variety      = trim($record['variety'] ?? '');

        if ($municipality && $barangay) {

            $reports[] = [
                'municipality' => ucwords(strtolower($municipality)),
                'barangay'     => ucwords(strtolower($barangay)),
                'numSeedlings' => $numSeedlings,
                'variety'      => $variety
            ];
        }
    }
}

// Calculate statistics
$totalSeedlings = array_sum(array_column($reports, 'numSeedlings'));
$uniqueMunicipalities = count(array_unique(array_column($reports, 'municipality')));
$uniqueBarangays = count(array_unique(array_map(function($r) { 
    return $r['municipality'] . '-' . $r['barangay']; 
}, $reports)));
$uniqueVarieties = count(array_unique(array_column($reports, 'variety')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Geographic Seedling Location - DENR System</title>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Font Awesome & Google Fonts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

/* ===== MODERN SIDEBAR ===== */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, #1a1f2e 0%, #2d3748 100%);
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.2);
    overflow-y: auto;
    z-index: 1000;
}

.sidebar .logo {
    padding: 30px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar .logo img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 3px solid #4a90e2;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    transition: transform 0.3s ease;
}

.sidebar .logo img:hover {
    transform: scale(1.05);
}

.sidebar-title {
    font-size: 14px;
    margin-top: 15px;
    color: #a0aec0;
    line-height: 1.6;
}

.sidebar nav {
    padding: 20px 0;
}

.sidebar .nav-link {
    display: flex;
    align-items: center;
    padding: 12px 25px;
    color: #cbd5e0;
    text-decoration: none;
    transition: all 0.3s ease;
    margin: 4px 10px;
    border-radius: 8px;
}

.sidebar .nav-link i {
    width: 24px;
    margin-right: 12px;
    font-size: 18px;
}

.sidebar .nav-link:hover {
    background: rgba(74, 144, 226, 0.2);
    color: white;
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    background: linear-gradient(90deg, #4a90e2 0%, #357abd 100%);
    color: white;
    box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);
}

.logout-btn {
    display: flex;
    align-items: center;
    margin: 20px;
    padding: 12px 20px;
    background: linear-gradient(90deg, #e53e3e 0%, #c53030 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.logout-btn i {
    width: 24px;
    margin-right: 12px;
}

.logout-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(229, 62, 62, 0.4);
}

/* ===== MAIN CONTENT ===== */
.main-content {
    margin-left: 280px;
    padding: 20px;
    min-height: 100vh;
}

/* ===== STATS CARDS ===== */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 24px;
}

.stat-icon.green { background: #48bb78; color: white; }
.stat-icon.blue { background: #4299e1; color: white; }
.stat-icon.purple { background: #9f7aea; color: white; }
.stat-icon.orange { background: #ed8936; color: white; }

.stat-info h3 {
    font-size: 14px;
    color: #718096;
    font-weight: 500;
    margin-bottom: 5px;
}

.stat-info .stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
}

/* ===== HEADER ===== */
.header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.header h2 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 10px;
}

.header p {
    font-size: 16px;
    opacity: 0.9;
}

/* ===== MAP CONTAINER ===== */
.map-wrapper {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

#map {
    height: 550px;
    width: 100%;
    background-color: #a0a0a0;
}

/* ===== MAP CONTROLS ===== */
.map-controls {
    background: white;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.control-label {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #4a5568;
    font-weight: 500;
}

.control-label i {
    color: #4a90e2;
}

.muni-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.muni-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #edf2f7;
    color: #4a5568;
}

.muni-btn i {
    font-size: 14px;
}

.muni-btn:hover {
    background: #4a90e2;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);
}

.muni-btn.active {
    background: #4a90e2;
    color: white;
}

.muni-btn.all-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.muni-btn.all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
}

/* ===== LEGEND ===== */
.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    gap: 8px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

/* Pink palette for seedling density */
.legend-color.low { background: #f9b4d4; }
.legend-color.medium { background: #e84393; }
.legend-color.high { background: #b0005e; }
.legend-color.no-data { background: #e0e0e0; }

/* ===== LOADING OVERLAY ===== */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3;
    border-top: 5px solid #4a90e2;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.custom-popup .leaflet-popup-content-wrapper {
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.custom-popup .leaflet-popup-tip {
    background: white;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
        overflow: visible;
    }
    
    .sidebar .logo img {
        width: 50px;
        height: 50px;
    }
    
    .sidebar-title,
    .nav-link span,
    .logout-btn span {
        display: none;
    }
    
    .nav-link i,
    .logout-btn i {
        margin-right: 0;
        font-size: 20px;
    }
    
    .main-content {
        margin-left: 80px;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar">
    <div class="logo">
        <a href="dashboard.php">
            <img src="image/DENR.jpg" alt="DENR Logo">
        </a>
        <div class="sidebar-title">
            Department of Environment<br>and Natural Resources
        </div>
    </div>

    <nav>
        <a href="dashboard.php" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="Farmer_Receive.php" class="nav-link">
            <i class="fas fa-users"></i>
            <span>Land Owner Request</span>
        </a>
        <a href="mortality.php" class="nav-link">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Mortality</span>
        </a>
        <a href="SeedlingDistribution.php" class="nav-link">
            <i class="fas fa-seedling"></i>
            <span>Seedling Distribution</span>
        </a>
        <a href="seedlingplanted.php" class="nav-link">
            <i class="fas fa-tree"></i>
            <span>Seedling Planted</span>
        </a>
        <a href="thematicmapping.php" class="nav-link">
            <i class="fas fa-map"></i>
            <span>Thematic Mapping</span>
        </a>
        <a href="geographic_seedling_location.php" class="nav-link active">
            <i class="fas fa-globe-asia"></i>
            <span>Geographic Location</span>
        </a>
    </nav>

    <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-tree"></i>
            </div>
            <div class="stat-info">
                <h3>Total Seedlings</h3>
                <div class="stat-number" id="statTotalSeedlings">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-city"></i>
            </div>
            <div class="stat-info">
                <h3>Municipalities</h3>
                <div class="stat-number" id="statMunicipalities">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-map-pin"></i>
            </div>
            <div class="stat-info">
                <h3>Barangays</h3>
                <div class="stat-number" id="statBarangays">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stat-info">
                <h3>Varieties</h3>
                <div class="stat-number" id="statVarieties">0</div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="header">
        <h2><i class="fas fa-map-marked-alt" style="margin-right: 10px;"></i>Geographic Seedling Location</h2>
        <p>Interactive map showing ALL barangay boundaries with seedling density (Pink shades = with seedlings, Gray = no data)</p>
    </div>

    <!-- Map Container -->
    <div class="map-wrapper">
        <div class="map-controls">
            <div class="control-label">
                <i class="fas fa-mouse-pointer"></i>
                <span>Filter by Municipality:</span>
            </div>
            <div class="muni-buttons">
                <button class="muni-btn all-btn" data-muni="all">
                    <i class="fas fa-globe"></i>
                    All Municipalities
                </button>
                <button class="muni-btn" data-muni="Digos City">
                    <i class="fas fa-city"></i>
                    Digos City
                </button>
                <button class="muni-btn" data-muni="Bansalan">
                    <i class="fas fa-city"></i>
                    Bansalan
                </button>
            </div>
            
            <!-- Legend -->
            <div style="margin-left: auto; display: flex; gap: 15px;">
                <div class="legend-item">
                    <div class="legend-color low"></div>
                    <span>1-150 seedlings</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color medium"></div>
                    <span>151-300 seedlings</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color high"></div>
                    <span>300+ seedlings</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color no-data"></div>
                    <span>No data (0 seedlings)</span>
                </div>
            </div>
        </div>
        <div id="map"></div>
    </div>
</div>

<script>
/* ==============================
   NORMALIZE STRING
============================== */
function normalize(text) {
    if (!text) return '';
    return text.toString().trim().toLowerCase().replace(/\s+/g, '');
}

/* ==============================
   PHP DATA TO JS
============================== */
const seedlingReports = <?php echo json_encode($reports); ?>;

// Update stats from PHP
document.getElementById('statTotalSeedlings').innerText = <?php echo number_format($totalSeedlings); ?>;
document.getElementById('statMunicipalities').innerText = <?php echo $uniqueMunicipalities; ?>;
document.getElementById('statBarangays').innerText = <?php echo $uniqueBarangays; ?>;
document.getElementById('statVarieties').innerText = <?php echo $uniqueVarieties; ?>;

/* ==============================
   GROUP DATA BY MUNICIPALITY + BARANGAY
============================== */
let seedlingData = {};

seedlingReports.forEach(r => {
    let muniKey = normalize(r.municipality);
    let brgyKey = normalize(r.barangay);
    
    if (!seedlingData[muniKey]) {
        seedlingData[muniKey] = {};
    }
    if (!seedlingData[muniKey][brgyKey]) {
        seedlingData[muniKey][brgyKey] = { 
            total: 0, 
            varieties: [],
            originalMuni: r.municipality,
            originalBrgy: r.barangay
        };
    }
    seedlingData[muniKey][brgyKey].total += r.numSeedlings;
    if (r.variety && !seedlingData[muniKey][brgyKey].varieties.includes(r.variety)) {
        seedlingData[muniKey][brgyKey].varieties.push(r.variety);
    }
});

/* ==============================
   INITIALIZE MAP WITH PLAIN BASEMAP (NO LABELS)
   Using CartoDB Voyager without labels or plain OSM with no labels
============================== */
var map = L.map('map').setView([6.803, 125.36], 11);

// PLAIN BASEMAP - NO LABELS, NO TEXT, JUST GRAY BACKGROUND
// Using CartoDB Voyager (lite) - minimal labels, mostly just gray basemap
// Alternative: Use a completely blank tile layer
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
    minZoom: 8
}).addTo(map);

// Make background completely plain gray
map.getContainer().style.backgroundColor = "#808080";

// Remove scale control to keep map plain
// L.control.scale({ imperial: false, metric: true }).addTo(map);

let geoLayer;
let currentFilter = 'all';

function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
}

function setActiveButton(municipalityName) {
    const buttons = document.querySelectorAll('.muni-btn');
    buttons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-muni') === municipalityName) {
            btn.classList.add('active');
        }
    });
}

/* ==============================
   GET COLOR BASED ON SEEDLING COUNT
============================== */
function getColorBySeedlings(total) {
    if (total === 0 || !total) return "#e0e0e0";      // no data - gray
    if (total > 0 && total <= 150) return "#f9b4d4";  // light pink
    if (total > 150 && total <= 300) return "#e84393"; // medium pink
    if (total > 300) return "#b0005e";                 // deep pink
    return "#e0e0e0";
}

/* ==============================
   GET SEEDLING DATA FOR A BARANGAY
============================== */
function getBarangayData(municipality, barangay) {
    let muniKey = normalize(municipality);
    let brgyKey = normalize(barangay);
    
    if (seedlingData[muniKey] && seedlingData[muniKey][brgyKey]) {
        return seedlingData[muniKey][brgyKey];
    }
    return { total: 0, varieties: [] };
}

/* ==============================
   CREATE POPUP CONTENT
============================== */
function createPopupContent(brgyName, municipalityName, total, varieties) {
    let seedlingStatus = total === 0 ? '<span style="color: #e53e3e;">No seedlings recorded</span>' : 
                                       `<strong style="color: #d63384;">${total.toLocaleString()} seedlings</strong>`;
    
    return `
        <div style="font-family: 'Poppins', sans-serif; min-width: 220px;">
            <h4 style="margin: 0 0 10px 0; color: #2d3748; border-bottom: 2px solid #e84393; padding-bottom: 5px;">
                <i class="fas fa-map-pin" style="color: #e84393; margin-right: 5px;"></i>${brgyName}
            </h4>
            <div style="margin: 10px 0;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #718096;">Municipality:</span>
                    <strong style="color: #2d3748;">${municipalityName}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #718096;">Seedlings:</span>
                    ${seedlingStatus}
                </div>
                <div style="margin-top: 10px;">
                    <span style="color: #718096; display: block; margin-bottom: 5px;">Varieties:</span>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                        ${varieties && varieties.length ? varieties.map(v => 
                            `<span style="background: #ffe0f0; padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #b0005e;">${escapeHtml(v)}</span>`
                        ).join('') : '<span style="color: #a0aec0;">None recorded</span>'}
                    </div>
                </div>
            </div>
        </div>
    `;
}

/* ==============================
   GET BOUNDS FOR MUNICIPALITIES WITH DATA
   This function filters the GeoJSON to only include Digos City and Bansalan
   and returns the bounds for zooming
============================== */
function getBoundsForMunicipalitiesWithData(geoJsonData) {
    let targetMunicipalities = ['digos city', 'bansalan'];
    let filteredFeatures = {
        type: "FeatureCollection",
        features: geoJsonData.features.filter(feature => {
            let muniName = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || '';
            let normalizedMuni = normalize(muniName);
            return targetMunicipalities.includes(normalizedMuni);
        })
    };
    
    if (filteredFeatures.features.length > 0) {
        let tempLayer = L.geoJSON(filteredFeatures);
        return tempLayer.getBounds();
    }
    return null;
}

/* ==============================
   LOAD ALL MUNICIPALITIES BUT ZOOM TO TARGET AREAS
   Shows ALL polygons but zooms directly to Digos City and Bansalan
============================== */
function loadAllMunicipalities() {
    showLoading();
    setActiveButton('all');
    currentFilter = 'all';
    
    if (geoLayer) {
        map.removeLayer(geoLayer);
    }
    
    fetch("level3.json")
    .then(res => {
        if (!res.ok) throw new Error('GeoJSON file not found. Please make sure level3.json exists.');
        return res.json();
    })
    .then(data => {
        console.log("GeoJSON loaded successfully. Total features:", data.features ? data.features.length : 0);
        
        if (!data.features || data.features.length === 0) {
            throw new Error('No features found in GeoJSON file');
        }
        
        geoLayer = L.geoJSON(data, {
            filter: function(feature) {
                return true; // Show ALL polygons
            },
            style: function(feature) {
                let muniName = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || '';
                let brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || '';
                let barangayData = getBarangayData(muniName, brgyName);
                let total = barangayData.total || 0;
                let fillColor = getColorBySeedlings(total);
                
                return {
                    color: "#ffffff",
                    weight: 1.0,
                    fillColor: fillColor,
                    fillOpacity: 0.85,
                    opacity: 0.8
                };
            },
            onEachFeature: function(feature, layer) {
                let muniName = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || 'Unknown Municipality';
                let brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || 'Unknown Barangay';
                let barangayData = getBarangayData(muniName, brgyName);
                let total = barangayData.total || 0;
                let varieties = barangayData.varieties || [];
                
                let popupContent = createPopupContent(brgyName, muniName, total, varieties);
                layer.bindPopup(popupContent, { maxWidth: 300, className: 'custom-popup' });
                
                layer.on('mouseover', function() {
                    this.setStyle({ weight: 2.5, color: '#ff99cc', fillOpacity: 0.95 });
                });
                layer.on('mouseout', function() {
                    this.setStyle({ weight: 1.0, color: "#ffffff", fillOpacity: 0.85 });
                });
                
                if (total > 0) {
                    let blinkState = true;
                    let interval = setInterval(() => {
                        if (layer._map) {
                            layer.setStyle({
                                fillOpacity: blinkState ? 0.95 : 0.65
                            });
                            blinkState = !blinkState;
                        } else {
                            clearInterval(interval);
                        }
                    }, 1000);
                    layer.on('remove', () => clearInterval(interval));
                }
            }
        }).addTo(map);
        
        // ZOOM TO TARGET MUNICIPALITIES (Digos City and Bansalan)
        let targetBounds = getBoundsForMunicipalitiesWithData(data);
        
        if (targetBounds && targetBounds.isValid()) {
            map.fitBounds(targetBounds, { padding: [20, 20] });
            console.log("Zoomed to target municipalities: Digos City and Bansalan");
        } else {
            console.warn("Target bounds invalid, using default view");
            map.setView([6.803, 125.36], 11);
        }
        
        hideLoading();
        
        let totalPolygons = data.features.length;
        let polygonsWithData = 0;
        data.features.forEach(feature => {
            let muniName = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || '';
            let brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || '';
            let data = getBarangayData(muniName, brgyName);
            if (data.total > 0) polygonsWithData++;
        });
        
        console.log(`Total polygons: ${totalPolygons}, Polygons with seedling data: ${polygonsWithData}`);
    })
    .catch(error => {
        console.error('Error loading GeoJSON:', error);
        hideLoading();
        alert('Error loading map data: ' + error.message + '\n\nPlease ensure level3.json file exists in the same directory.');
    });
}

/* ==============================
   LOAD SPECIFIC MUNICIPALITY (FILTERED)
============================== */
function loadMunicipality(municipalityName) {
    showLoading();
    setActiveButton(municipalityName);
    currentFilter = municipalityName;
    
    if (geoLayer) {
        map.removeLayer(geoLayer);
    }
    
    let muniKey = normalize(municipalityName);
    
    fetch("level3.json")
    .then(res => {
        if (!res.ok) throw new Error('GeoJSON file not found');
        return res.json();
    })
    .then(data => {
        let filteredFeatures = {
            type: "FeatureCollection",
            features: data.features.filter(feature => {
                let featureMuni = feature.properties.NAME_2 || feature.properties.MUNICIPALITY || '';
                return normalize(featureMuni) === muniKey;
            })
        };
        
        console.log(`Found ${filteredFeatures.features.length} barangays for ${municipalityName}`);
        
        if (filteredFeatures.features.length === 0) {
            alert(`No barangay data found for ${municipalityName}`);
            hideLoading();
            return;
        }
        
        geoLayer = L.geoJSON(filteredFeatures, {
            style: function(feature) {
                let brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || '';
                let barangayData = getBarangayData(municipalityName, brgyName);
                let total = barangayData.total || 0;
                let fillColor = getColorBySeedlings(total);
                
                return {
                    color: "#ffffff",
                    weight: 1.2,
                    fillColor: fillColor,
                    fillOpacity: 0.85,
                    opacity: 0.8
                };
            },
            onEachFeature: function(feature, layer) {
                let brgyName = feature.properties.NAME_3 || feature.properties.BARANGAY || 'Unknown';
                let barangayData = getBarangayData(municipalityName, brgyName);
                let total = barangayData.total || 0;
                let varieties = barangayData.varieties || [];
                
                let popupContent = createPopupContent(brgyName, municipalityName, total, varieties);
                layer.bindPopup(popupContent, { maxWidth: 300, className: 'custom-popup' });
                
                layer.on('mouseover', function() {
                    this.setStyle({ weight: 2.5, color: '#ff99cc', fillOpacity: 0.95 });
                });
                layer.on('mouseout', function() {
                    this.setStyle({ weight: 1.2, color: "#ffffff", fillOpacity: 0.85 });
                });
            }
        }).addTo(map);
        
        if (geoLayer.getBounds().isValid()) {
            map.fitBounds(geoLayer.getBounds());
        } else {
            map.setView([6.803, 125.36], 13);
        }
        hideLoading();
    })
    .catch(error => {
        console.error('Error:', error);
        hideLoading();
        alert('Error loading map data for ' + municipalityName);
    });
}

// Helper to escape HTML
function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Attach click handlers to buttons
document.querySelectorAll('.muni-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
        const muni = btn.getAttribute('data-muni');
        if (muni === 'all') {
            loadAllMunicipalities();
        } else if (muni) {
            loadMunicipality(muni);
        }
    });
});

// Auto-load ALL Municipalities on page load - automatically zooms to Digos City and Bansalan
window.addEventListener('load', () => {
    console.log("Page loaded, loading all municipalities and zooming to Digos City & Bansalan...");
    setTimeout(() => {
        loadAllMunicipalities();
    }, 500);
});
</script>

</body>
</html>
