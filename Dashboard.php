<?php
session_start();

/* =======================
   LOGIN CHECK
======================= */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

/* =======================
   FARMER SEEDLING REQUESTS
======================= */
$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/FarmerSeedlingRequests.json";
$data = json_decode(@file_get_contents($firebase_url), true);

$total_seedlings = 0;
$daily_data = [];

if ($data) {
    foreach ($data as $record) {
        $qty  = (int)($record['seedlingsRequested'] ?? 0);
        $date = $record['date'] ?? 'Unknown';
        $total_seedlings += $qty;
        $daily_data[$date] = ($daily_data[$date] ?? 0) + $qty;
    }
}

$dates = array_keys($daily_data);
$seedlings_count = array_values($daily_data);
$total_farmer_requests = $data ? count($data) : 0;

/* =======================
   SEEDLING MORTALITY
======================= */
$mortality_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingMortalityReports.json";
$data_mortality = json_decode(@file_get_contents($mortality_url), true);

$mortality_labels = [];
$mortality_counts = [];

if ($data_mortality) {
    foreach ($data_mortality as $record) {
        $mortality_labels[] = $record['seedlingVariety'] ?? 'Unknown';
        $mortality_counts[] = (int)($record['seedlingsDied'] ?? 0);
    }
}

$total_mortality_records = $data_mortality ? count($data_mortality) : 0;

/* =======================
   SEEDLING DISTRIBUTION
======================= */
$distribution_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingDistributions.json";
$data_distribution = json_decode(@file_get_contents($distribution_url), true);

$distribution_labels = [];
$distribution_counts = [];

if ($data_distribution) {
    foreach ($data_distribution as $record) {
        $distribution_labels[] = $record['seedlingType'] ?? 'Unknown';
        $distribution_counts[] = (int)($record['numSeedlings'] ?? 0);
    }
}

$total_distribution_records = $data_distribution ? count($data_distribution) : 0;

/* =======================
   SEEDLING PLANTED
======================= */
$planted_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingPlantedReports.json";
$data_planted = json_decode(@file_get_contents($planted_url), true);

$planted_dates = [];
$planted_counts = [];

if ($data_planted) {
    foreach ($data_planted as $record) {
        $planted_dates[] = $record['date'] ?? 'Unknown';
        $planted_counts[] = (int)($record['numSeedlings'] ?? 0);
    }
}

$total_planted_records = $data_planted ? count($data_planted) : 0;

// Calculate totals for summary cards
$total_requests_value = array_sum($seedlings_count);
$total_mortality_value = array_sum($mortality_counts);
$total_distribution_value = array_sum($distribution_counts);
$total_planted_value = array_sum($planted_counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - DENR System</title>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts -->
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
    padding: 30px;
    min-height: 100vh;
}

/* ===== WELCOME HEADER ===== */
.welcome-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-header h2 {
    font-size: 28px;
    font-weight: 600;
}

.welcome-header h2 i {
    margin-right: 10px;
}

.date-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 14px;
    backdrop-filter: blur(5px);
}

/* ===== STATS CARDS ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
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
    background: linear-gradient(90deg, #4a90e2, #9f7aea);
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

.stat-icon.blue { background: #4299e1; color: white; }
.stat-icon.green { background: #48bb78; color: white; }
.stat-icon.orange { background: #ed8936; color: white; }
.stat-icon.red { background: #f56565; color: white; }

.stat-details {
    flex: 1;
}

.stat-details h3 {
    font-size: 14px;
    color: #718096;
    font-weight: 500;
    margin-bottom: 5px;
}

.stat-details .stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.stat-details .stat-label {
    font-size: 12px;
    color: #a0aec0;
}

.stat-trend {
    position: absolute;
    bottom: 10px;
    right: 15px;
    font-size: 12px;
    padding: 3px 8px;
    border-radius: 12px;
}

.trend-up { background: #c6f6d5; color: #22543d; }
.trend-down { background: #fed7d7; color: #742a2a; }

/* ===== CHARTS SECTION ===== */
.charts-section {
    margin-top: 30px;
}

.chart-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-bottom: 25px;
}

.chart-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.chart-card:hover {
    transform: translateY(-5px);
}

.chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #edf2f7;
}

.chart-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-header h3 i {
    color: #4a90e2;
}

.chart-value {
    font-size: 14px;
    color: #718096;
}

.chart-value span {
    font-weight: 600;
    color: #2d3748;
}

canvas {
    max-height: 250px;
    width: 100% !important;
}

/* ===== QUICK ACTIONS ===== */
.quick-actions {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-top: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.quick-actions h3 {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 12px 25px;
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
    text-decoration: none;
    font-size: 14px;
}

.action-btn i {
    font-size: 16px;
}

.action-btn:hover {
    background: #4a90e2;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(74, 144, 226, 0.3);
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
    
    .chart-row {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .welcome-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stat-card, .chart-card, .welcome-header {
    animation: fadeIn 0.5s ease-out;
}

/* ===== CUSTOM SCROLLBAR ===== */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #edf2f7;
}

::-webkit-scrollbar-thumb {
    background: #4a90e2;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #357abd;
}
</style>
</head>

<body>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar">
    <div class="logo">
        <a href="dashboard.php">
            <img src="/DENR-SYSTEM/SYSTEM/image/DENR.jpg" alt="DENR Logo">
        </a>
        <div class="sidebar-title">
            Department of Environment<br>and Natural Resources
        </div>
    </div>

    <nav>
        <a href="dashboard.php" class="nav-link active">
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
        <a href="geographic_seedling_location.php" class="nav-link">
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
    
    <!-- Welcome Header -->
    <div class="welcome-header">
        <h2>
            <i class="fas fa-hand-peace"></i>
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
        </h2>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            <?php echo date('F j, Y'); ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-file-signature"></i>
            </div>
            <div class="stat-details">
                <h3>Land Owner Requests</h3>
                <div class="stat-number"><?php echo $total_farmer_requests; ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-arrow-up"></i> +<?php echo $total_farmer_requests; ?>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-heartbeat"></i>
            </div>
            <div class="stat-details">
                <h3>Mortality Records</h3>
                <div class="stat-number"><?php echo $total_mortality_records; ?></div>
                <div class="stat-label">Total Records</div>
            </div>
            <div class="stat-trend trend-down">
                <i class="fas fa-exclamation-circle"></i> <?php echo $total_mortality_value; ?> lost
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-truck"></i>
            </div>
            <div class="stat-details">
                <h3>Distribution</h3>
                <div class="stat-number"><?php echo $total_distribution_records; ?></div>
                <div class="stat-label">Total Distributions</div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-seedling"></i> <?php echo $total_distribution_value; ?> given
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-map-pin"></i>
            </div>
            <div class="stat-details">
                <h3>Planted</h3>
                <div class="stat-number"><?php echo $total_planted_records; ?></div>
                <div class="stat-label">Total Planted</div>
            </div>
            <div class="stat-trend trend-up">
                <i class="fas fa-tree"></i> <?php echo $total_planted_value; ?> trees
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-row">
            <!-- Bar Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> Daily Seedling Requests</h3>
                    <div class="chart-value">Total: <span><?php echo $total_requests_value; ?></span></div>
                </div>
                <canvas id="barChart"></canvas>
            </div>

            <!-- Pie Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Mortality by Variety</h3>
                    <div class="chart-value">Records: <span><?php echo $total_mortality_records; ?></span></div>
                </div>
                <canvas id="pieChart"></canvas>
            </div>
        </div>

        <div class="chart-row">
            <!-- Doughnut Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Distribution by Type</h3>
                    <div class="chart-value">Types: <span><?php echo count($distribution_labels); ?></span></div>
                </div>
                <canvas id="doughnutChart"></canvas>
            </div>

            <!-- Line Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Planted Seedlings Trend</h3>
                    <div class="chart-value">Total: <span><?php echo $total_planted_value; ?></span></div>
                </div>
                <canvas id="lineChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="action-buttons">
            <a href="Farmer_Receive.php" class="action-btn">
                <i class="fas fa-plus-circle"></i> New Request
            </a>
            <a href="mortality.php" class="action-btn">
                <i class="fas fa-exclamation-triangle"></i> Report Mortality
            </a>
            <a href="SeedlingDistribution.php" class="action-btn">
                <i class="fas fa-truck"></i> Distribute Seedlings
            </a>
            <a href="seedlingplanted.php" class="action-btn">
                <i class="fas fa-tree"></i> Record Planting
            </a>
            <a href="geographic_seedling_location.php" class="action-btn">
                <i class="fas fa-map"></i> View Map
            </a>
        </div>
    </div>
</div>

<script>
// Chart configurations
const chartOptions = {
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                font: {
                    family: 'Poppins',
                    size: 11
                },
                color: '#4a5568'
            }
        }
    }
};

// Bar Chart
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: { 
        labels: <?=json_encode($dates)?>, 
        datasets: [{ 
            label: 'Seedlings Requested',
            data: <?=json_encode($seedlings_count)?>, 
            backgroundColor: '#4299e1',
            borderRadius: 4
        }] 
    },
    options: {
        ...chartOptions,
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e2e8f0'
                }
            }
        }
    }
});

// Pie Chart
new Chart(document.getElementById('pieChart'), {
    type: 'pie',
    data: { 
        labels: <?=json_encode($mortality_labels)?>, 
        datasets: [{ 
            data: <?=json_encode($mortality_counts)?>, 
            backgroundColor: ['#f56565', '#48bb78', '#4299e1', '#ed8936', '#9f7aea']
        }] 
    },
    options: chartOptions
});

// Doughnut Chart
new Chart(document.getElementById('doughnutChart'), {
    type: 'doughnut',
    data: { 
        labels: <?=json_encode($distribution_labels)?>, 
        datasets: [{ 
            data: <?=json_encode($distribution_counts)?>, 
            backgroundColor: ['#4299e1', '#ed8936', '#48bb78', '#f56565', '#9f7aea']
        }] 
    },
    options: chartOptions
});

// Line Chart
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: { 
        labels: <?=json_encode($planted_dates)?>, 
        datasets: [{ 
            label: 'Seedlings Planted',
            data: <?=json_encode($planted_counts)?>, 
            borderColor: '#48bb78',
            backgroundColor: 'rgba(72, 187, 120, 0.1)',
            tension: 0.4,
            fill: true
        }] 
    },
    options: {
        ...chartOptions,
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e2e8f0'
                }
            }
        }
    }
});
</script>

</body>
</html>