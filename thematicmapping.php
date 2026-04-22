<?php
session_start();

// Check login
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Firebase Realtime Database URL
$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingPlantedReports.json";

// Fetch data from Firebase
$response = @file_get_contents($firebase_url);
if ($response === FALSE) {
    $data = [];
} else {
    $data = json_decode($response, true);
}

// Collect only the needed fields
$reports = [];
if (!empty($data)) {
    foreach ($data as $id => $record) {
        if (!is_array($record)) continue;

        $reports[] = [
            'location'        => $record['location'] ?? 'Unknown Location',
            'numSeedlings'    => $record['numSeedlings'] ?? 0,
            'seedlingVariety' => $record['seedlingVariety'] ?? 'Unknown Variety'
        ];
    }
}

// Calculate statistics
$totalSeedlings = array_sum(array_column($reports, 'numSeedlings'));
$totalLocations = count(array_unique(array_column($reports, 'location')));
$totalVarieties = count(array_unique(array_column($reports, 'seedlingVariety')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Thematic Mapping - Seedling Planted | DENR System</title>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@turf/turf@6/turf.min.js"></script>

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
      border: 3px solid #9f7aea;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
      transition: transform 0.3s ease;
      object-fit: cover;
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
      background: rgba(159, 122, 234, 0.2);
      color: white;
      transform: translateX(5px);
    }

    .sidebar .nav-link.active {
      background: linear-gradient(90deg, #9f7aea 0%, #805ad5 100%);
      color: white;
      box-shadow: 0 4px 10px rgba(159, 122, 234, 0.3);
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
      color: white;
    }

    /* ===== MAIN CONTENT ===== */
    .main-content {
      margin-left: 280px;
      padding: 30px;
      min-height: 100vh;
    }

    /* ===== HEADER ===== */
    .page-header {
      background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
      color: white;
      padding: 30px;
      border-radius: 15px;
      margin-bottom: 30px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }

    .header-content h2 {
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .header-content h2 i {
      margin-right: 10px;
    }

    .header-content p {
      font-size: 16px;
      opacity: 0.9;
      margin: 0;
    }

    /* ===== STATS CARDS ===== */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      display: flex;
      align-items: center;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #9f7aea, #667eea);
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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

    .stat-icon.purple { 
      background: linear-gradient(135deg, #9f7aea, #805ad5); 
      color: white; 
    }
    .stat-icon.blue { 
      background: linear-gradient(135deg, #4299e1, #3182ce); 
      color: white; 
    }
    .stat-icon.green { 
      background: linear-gradient(135deg, #48bb78, #38a169); 
      color: white; 
    }

    .stat-details h3 {
      font-size: 13px;
      color: #718096;
      font-weight: 500;
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .stat-number {
      font-size: 24px;
      font-weight: 700;
      color: #2d3748;
      line-height: 1.2;
    }

    /* ===== MAP CARD ===== */
    .map-card {
      background: white;
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .map-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .map-header h3 {
      font-size: 20px;
      font-weight: 600;
      color: #2d3748;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .map-header h3 i {
      color: #9f7aea;
    }

    .map-controls {
      display: flex;
      gap: 10px;
    }

    .map-btn {
      padding: 8px 15px;
      border: none;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 5px;
      background: #edf2f7;
      color: #4a5568;
    }

    .map-btn:hover {
      background: #9f7aea;
      color: white;
      transform: translateY(-2px);
    }

    .map-btn i {
      font-size: 14px;
    }

    #map {
      width: 100%;
      height: 550px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      z-index: 1;
    }

    /* ===== LEGEND ===== */
    .legend {
      background: white;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-top: 15px;
    }

    .legend h4 {
      font-size: 14px;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .legend-grid {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 12px;
      color: #4a5568;
    }

    .legend-color {
      width: 15px;
      height: 15px;
      border-radius: 3px;
    }

    /* ===== INFO PANEL ===== */
    .info-panel {
      background: #f7fafc;
      border-radius: 10px;
      padding: 15px;
      margin-top: 20px;
      border-left: 4px solid #9f7aea;
    }

    .info-panel p {
      margin: 0;
      color: #4a5568;
      font-size: 14px;
    }

    .info-panel i {
      color: #9f7aea;
      margin-right: 5px;
    }

    /* ===== PRINT STYLES ===== */
    @media print {
      .sidebar,
      .stats-grid,
      .map-controls,
      .legend,
      .info-panel {
        display: none !important;
      }
      
      .main-content {
        margin-left: 0;
        padding: 20px;
      }
      
      .map-card {
        box-shadow: none;
        padding: 0;
      }
      
      #map {
        height: 400px;
      }
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      .sidebar {
        width: 80px;
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
        padding: 20px;
      }
      
      .page-header {
        flex-direction: column;
        text-align: center;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .map-header {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }

    /* ===== ANIMATIONS ===== */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .stat-card, .map-card {
      animation: fadeIn 0.5s ease-out;
    }

    /* ===== CUSTOM SCROLLBAR ===== */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #edf2f7;
    }

    ::-webkit-scrollbar-thumb {
      background: #9f7aea;
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #805ad5;
    }

    /* ===== LABEL STYLES ===== */
    .barangay-label {
      background: rgba(0,0,0,0.7);
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 500;
      white-space: nowrap;
      border: 1px solid rgba(255,255,255,0.3);
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="logo">
      <a href="dashboard.php"><img src="image/DENR.jpg" alt="DENR" /></a>
      <h3 class="sidebar-title">Department of Environment<br>and Natural Resources</h3>
    </div>
    <nav>
      <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
      <a href="Farmer_Receive.php" class="nav-link"><i class="fas fa-users"></i><span>Land Owner Request</span></a>
      <a href="mortality.php" class="nav-link"><i class="fas fa-exclamation-triangle"></i><span>Mortality</span></a>
      <a href="SeedlingDistribution.php" class="nav-link"><i class="fas fa-seedling"></i><span>Seedling Distribution</span></a>
      <a href="seedlingplanted.php" class="nav-link"><i class="fas fa-tree"></i><span>Seedling Planted</span></a>
      <a href="thematicmapping.php" class="nav-link active"><i class="fas fa-map"></i><span>Thematic Mapping</span></a>
      <a href="geographic_seedling_location.php" class="nav-link"><i class="fas fa-globe-asia"></i><span>Geographic Location</span></a>
    </nav>
    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
      <div class="header-content">
        <h2><i class="fas fa-map"></i>Thematic Mapping of Seedling Planted</h2>
        <p>Visualize seedling distribution across different barangays with color-coded thematic mapping</p>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon purple">
          <i class="fas fa-map-marker-alt"></i>
        </div>
        <div class="stat-details">
          <h3>Locations</h3>
          <div class="stat-number"><?php echo $totalLocations; ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green">
          <i class="fas fa-seedling"></i>
        </div>
        <div class="stat-details">
          <h3>Total Seedlings</h3>
          <div class="stat-number"><?php echo number_format($totalSeedlings); ?></div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon blue">
          <i class="fas fa-leaf"></i>
        </div>
        <div class="stat-details">
          <h3>Varieties</h3>
          <div class="stat-number"><?php echo $totalVarieties; ?></div>
        </div>
      </div>
    </div>

    <!-- Map Card -->
    <div class="map-card">
      <div class="map-header">
        <h3><i class="fas fa-map-marked-alt"></i>Seedling Distribution Map - Bansalan & Digos City</h3>
        <div class="map-controls">
          <button class="map-btn" onclick="resetMap()"><i class="fas fa-sync-alt"></i> Reset View</button>
          <button class="map-btn" onclick="toggleLabels()"><i class="fas fa-tag"></i> Toggle Labels</button>
        </div>
      </div>
      
      <div id="map"></div>

      <!-- Legend -->
      <div class="legend">
        <h4><i class="fas fa-palette"></i> Barangay Color Legend</h4>
        <div class="legend-grid" id="legendGrid"></div>
      </div>

      <!-- Info Panel -->
      <div class="info-panel">
        <p><i class="fas fa-info-circle"></i> Click on any barangay to see detailed planting information. Colors represent different barangays for easy identification.</p>
      </div>
    </div>
  </div>

<script>
// Pass PHP data into JavaScript
var seedlingReports = <?php echo json_encode($reports); ?>;

// Group by location
var seedlingData = {};
seedlingReports.forEach(function(report) {
    let loc = report.location;
    let num = parseInt(report.numSeedlings) || 0;
    let variety = report.seedlingVariety || "Unknown";
    if (!seedlingData[loc]) {
        seedlingData[loc] = { total: 0, varieties: [] };
    }
    seedlingData[loc].total += num;
    if (!seedlingData[loc].varieties.includes(variety)) {
        seedlingData[loc].varieties.push(variety);
    }
});

// Initialize map
var map = L.map('map').setView([6.78, 125.35], 12);

// Add satellite imagery tiles (better for thematic mapping)
L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
    maxZoom: 19
}).addTo(map);

// Add a semi-transparent overlay for better contrast
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_only_labels/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap, &copy; CartoDB',
    opacity: 0.5
}).addTo(map);

// Barangays list + colors
const Barangays = ["Alegre","Alta Vista","Anonang","Bitaug","Bonifacio","Buenavista","Darapuay","Dolo","Eman","Kinuskusan","Libertad","Linawan","Mabuhay","Mabunga","Managa","Marber","New Clarin","Poblacion Uno","Poblacion Dos","Rizal","Santo Niño","Sibayan","Tinongtongan","Tubod","Union","Balabag","Goma","Aplaya","Bato","Kapatagan"];

// Enhanced color palette
const barangayColors = {
    "Aplaya":"#FF6B6B", "Balabag":"#4ECDC4", "Bato":"#45B7D1", "Bitaug":"#96CEB4", 
    "Bonifacio":"#FFE194", "Darapuay":"#E6B89C", "Dolo":"#A8E6CF", "Eman":"#D4A5A5",
    "Kinuskusan":"#9B59B6", "Libertad":"#3498DB", "Linawan":"#E67E22", "Mabuhay":"#2ECC71",
    "Mabunga":"#F1C40F", "Managa":"#E74C3C", "Marber":"#1ABC9C", "New Clarin":"#34495E",
    "Poblacion Uno":"#7F8C8D", "Poblacion Dos":"#8E44AD", "Rizal":"#2980B9", 
    "Santo Niño":"#27AE60", "Sibayan":"#D35400", "Tinongtongan":"#C0392B", 
    "Tubod":"#16A085", "Union":"#F39C12", "Kapatagan":"#2C3E50", "Alegre":"#E67E22",
    "Alta Vista":"#3498DB", "Anonang":"#9B59B6", "Buenavista":"#1ABC9C"
};

// Generate legend
function generateLegend() {
    const legendGrid = document.getElementById('legendGrid');
    legendGrid.innerHTML = '';
    
    // Get unique barangays that have colors
    const uniqueBarangays = [...new Set(Object.keys(barangayColors))];
    
    uniqueBarangays.sort().forEach(barangay => {
        const item = document.createElement('div');
        item.className = 'legend-item';
        item.innerHTML = `
            <span class="legend-color" style="background: ${barangayColors[barangay]}"></span>
            <span>${barangay}</span>
        `;
        legendGrid.appendChild(item);
    });
}

// Filter Bansalan + Digos City barangays
function filterBarangays(feature) {
    return (feature.properties.NAME_2 === "Bansalan" || feature.properties.NAME_2 === "DigosCity") &&
           Barangays.includes(feature.properties.NAME_3);
}

let barangayLayer;
let labelsVisible = true;
let labelMarkers = [];

// Load Level 3 GeoJSON
fetch("level3.json")
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(data => {
        let selectedBarangays = [];

        barangayLayer = L.geoJSON(data, {
            filter: filterBarangays,
            style: function(feature) {
                var name = feature.properties.NAME_3;
                return {
                    color: "#fff",
                    weight: 2,
                    fillColor: barangayColors[name] || "#CCCCCC",
                    fillOpacity: 0.8
                };
            },
            onEachFeature: function(feature, layer) {
                var barangayName = feature.properties.NAME_3;
                var cityName = feature.properties.NAME_2;
                
                // Enhanced popup content
                let popupContent = `
                    <div style="font-family: 'Poppins', sans-serif; min-width: 220px;">
                        <h4 style="margin: 0 0 10px 0; color: #2d3748; border-bottom: 2px solid #9f7aea; padding-bottom: 5px;">
                            <i class="fas fa-map-pin" style="color: #9f7aea;"></i> ${barangayName}
                        </h4>
                        <div style="margin: 10px 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="color: #718096;">Municipality:</span>
                                <strong style="color: #2d3748;">${cityName}</strong>
                            </div>
                `;

                if (seedlingData[barangayName]) {
                    popupContent += `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="color: #718096;">Seedlings Planted:</span>
                                <strong style="color: #48bb78;">${seedlingData[barangayName].total.toLocaleString()}</strong>
                            </div>
                            <div style="margin-top: 10px;">
                                <span style="color: #718096; display: block; margin-bottom: 5px;">Varieties:</span>
                                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                    ${seedlingData[barangayName].varieties.map(v => 
                                        `<span style="background: #9f7aea20; padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #805ad5;">${v}</span>`
                                    ).join('')}
                                </div>
                            </div>
                    `;
                } else {
                    popupContent += `
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="color: #718096;">Seedlings Planted:</span>
                                <strong style="color: #e53e3e;">0</strong>
                            </div>
                            <div style="margin-top: 10px;">
                                <span style="color: #718096;">No planting records</span>
                            </div>
                    `;
                }

                popupContent += `
                        </div>
                    </div>
                `;

                layer.bindPopup(popupContent, {
                    maxWidth: 300,
                    className: 'custom-popup'
                });

                // Highlight on hover
                layer.on('mouseover', function() {
                    this.setStyle({
                        weight: 3,
                        fillOpacity: 1
                    });
                });

                layer.on('mouseout', function() {
                    this.setStyle({
                        weight: 2,
                        fillOpacity: 0.8
                    });
                });

                // Highlight on click
                layer.on('click', function() {
                    this.setStyle({ weight: 3, color: '#FFD700' });
                    barangayLayer.eachLayer(l => { 
                        if (l !== layer) l.setStyle({ weight: 2, color: '#fff' }); 
                    });
                });

                // Add label
                var centroid = turf.centroid(feature);
                var coords = centroid.geometry.coordinates;
                var label = L.marker([coords[1], coords[0]], {
                    icon: L.divIcon({
                        className: "barangay-label",
                        html: `<span>${barangayName}</span>`
                    }),
                    interactive: false
                }).addTo(map);
                
                labelMarkers.push(label);

                selectedBarangays.push(feature);
            }
        }).addTo(map);

        // Mask outside areas
        var world = turf.polygon([[[-180,-90],[180,-90],[180,90],[-180,90],[-180,-90]]]);
        if (selectedBarangays.length > 0) {
            let combined = selectedBarangays.reduce((acc,f)=>acc?turf.union(acc,f):f,null);
            let mask = turf.difference(world, combined);
            L.geoJSON(mask, { style: { fillColor:"white", color:"white", weight:0, fillOpacity:1 } }).addTo(map);
            map.fitBounds(barangayLayer.getBounds());
        }

        // Generate legend
        generateLegend();
    })
    .catch(error => {
        console.error('Error loading GeoJSON:', error);
        document.getElementById('map').innerHTML = '<div style="text-align: center; padding: 50px; color: #e53e3e;">Error loading map data. Please try again later.</div>';
    });

// Function to reset map view
function resetMap() {
    if (barangayLayer) {
        map.fitBounds(barangayLayer.getBounds());
    }
}

// Function to toggle labels
function toggleLabels() {
    labelsVisible = !labelsVisible;
    labelMarkers.forEach(marker => {
        if (labelsVisible) {
            marker.addTo(map);
        } else {
            map.removeLayer(marker);
        }
    });
}

// Add scale control
L.control.scale({ imperial: false, metric: true }).addTo(map);

// Add animation to stat cards
document.addEventListener('DOMContentLoaded', function() {
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
});
</script>
</body>
</html>
