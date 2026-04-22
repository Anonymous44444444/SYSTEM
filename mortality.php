<?php
session_start();

/* ===== LOGIN CHECK ===== */
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

/* ===== FIREBASE FETCH ===== */
$firebase_url = "https://validator-b9503-default-rtdb.firebaseio.com/SeedlingMortalityReports.json";
$response = @file_get_contents($firebase_url);
$data = json_decode($response, true) ?? [];

/* ===== PAGINATION ===== */
$totalEntries = count($data);
$entriesPerPage = isset($_GET['entriesPerPage']) ? (int)$_GET['entriesPerPage'] : 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$totalPages = ceil($totalEntries / $entriesPerPage);
$offset = ($currentPage - 1) * $entriesPerPage;
$paginatedData = array_slice($data, $offset, $entriesPerPage);

/* ===== CALCULATE STATISTICS ===== */
$totalMortality = array_sum(array_column($data, 'died'));
$avgMortality = $totalEntries > 0 ? round($totalMortality / $totalEntries) : 0;
$uniqueMunicipalities = count(array_unique(array_column($data, 'municipality')));
$uniqueVarieties = count(array_unique(array_column($data, 'variety')));

// Find highest mortality area
$highestMortality = 0;
$highestArea = '';
foreach ($data as $record) {
    $died = (int)($record['died'] ?? 0);
    if ($died > $highestMortality) {
        $highestMortality = $died;
        $highestArea = ($record['municipality'] ?? '') . ' - ' . ($record['barangay'] ?? '');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seedling Mortality Records - DENR System</title>

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.header-actions {
    display: flex;
    gap: 15px;
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
    border-radius: 15px;
    padding: 25px;
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
    background: linear-gradient(90deg, #f56565, #ed8936);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 70px;
    height: 70px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    font-size: 28px;
}

.stat-icon.red { 
    background: linear-gradient(135deg, #f56565, #e53e3e); 
    color: white; 
}
.stat-icon.orange { 
    background: linear-gradient(135deg, #ed8936, #dd6b20); 
    color: white; 
}
.stat-icon.yellow { 
    background: linear-gradient(135deg, #ecc94b, #d69e2e); 
    color: white; 
}
.stat-icon.purple { 
    background: linear-gradient(135deg, #9f7aea, #805ad5); 
    color: white; 
}

.stat-details h3 {
    font-size: 14px;
    color: #718096;
    font-weight: 500;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
    line-height: 1.2;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #a0aec0;
}

/* ===== CHART CARD ===== */
.chart-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.chart-card h3 {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chart-card h3 i {
    color: #f56565;
}

.chart-container {
    height: 300px;
    position: relative;
}

/* ===== TABLE CARD ===== */
.table-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h3 {
    font-size: 20px;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h3 i {
    color: #f56565;
}

/* ===== TABLE STYLES ===== */
.table {
    width: 100%;
    margin-bottom: 20px;
}

.table thead th {
    background: linear-gradient(135deg, #2d3748, #1a202c);
    color: white;
    font-weight: 500;
    font-size: 14px;
    padding: 15px;
    border: none;
    white-space: nowrap;
}

.table thead th i {
    margin-right: 8px;
    font-size: 12px;
}

.table tbody td {
    padding: 12px 15px;
    vertical-align: middle;
    color: #4a5568;
    font-size: 14px;
    border-bottom: 1px solid #e2e8f0;
}

.table tbody tr:hover {
    background-color: #f7fafc;
}

/* ===== MORTALITY BADGES ===== */
.mortality-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.badge-high {
    background: #fed7d7;
    color: #c53030;
}

.badge-medium {
    background: #feebc8;
    color: #c05621;
}

.badge-low {
    background: #c6f6d5;
    color: #22543d;
}

/* ===== PAGINATION ===== */
.pagination {
    margin: 0;
}

.page-item .page-link {
    color: #4a5568;
    border: 1px solid #e2e8f0;
    padding: 8px 15px;
    margin: 0 3px;
    border-radius: 8px;
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #f56565, #e53e3e);
    border-color: #f56565;
    color: white;
}

.page-item .page-link:hover {
    background: #edf2f7;
    border-color: #cbd5e0;
    color: #2d3748;
}

/* ===== BUTTON STYLES ===== */
.btn-generate {
    background: linear-gradient(135deg, #2d3748, #1a202c);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-generate:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    background: linear-gradient(135deg, #1a202c, #2d3748);
    color: white;
}

/* ===== ENTRIES SELECTOR ===== */
.entries-selector {
    display: flex;
    align-items: center;
    gap: 10px;
}

.entries-selector select {
    padding: 8px 15px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    color: #4a5568;
    cursor: pointer;
}

.entries-selector select:focus {
    outline: none;
    border-color: #f56565;
    box-shadow: 0 0 0 3px rgba(245, 101, 101, 0.1);
}

/* ===== PRINT STYLES ===== */
@media print {
    .sidebar,
    .stats-grid,
    .chart-card,
    .header-actions,
    .entries-selector,
    .pagination,
    .btn-generate {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    
    .table-card {
        box-shadow: none;
        padding: 0;
    }
    
    .table thead th {
        background: #2d3748 !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
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
    
    .table-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .entries-selector {
        justify-content: space-between;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
    }
    
    .entries-selector {
        flex-direction: column;
        align-items: stretch;
    }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.stat-card, .chart-card, .table-card {
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
    background: #f56565;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #e53e3e;
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
        <a href="dashboard.php" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="Farmer_Receive.php" class="nav-link">
            <i class="fas fa-users"></i>
            <span>Land Owner Request</span>
        </a>
        <a href="mortality.php" class="nav-link active">
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
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h2>
                <i class="fas fa-exclamation-triangle"></i>
                Seedling Mortality Records
            </h2>
            <p>Monitor and analyze seedling mortality rates across different areas</p>
        </div>
        <div class="header-actions">
            <button class="btn-generate" onclick="window.print()">
                <i class="fas fa-file-pdf"></i>
                Generate Report
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-skull-crossbones"></i>
            </div>
            <div class="stat-details">
                <h3>Total Mortality</h3>
                <div class="stat-number"><?php echo number_format($totalMortality); ?></div>
                <div class="stat-label">Seedlings lost</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-details">
                <h3>Average Mortality</h3>
                <div class="stat-number"><?php echo number_format($avgMortality); ?></div>
                <div class="stat-label">Per record</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon yellow">
                <i class="fas fa-city"></i>
            </div>
            <div class="stat-details">
                <h3>Municipalities</h3>
                <div class="stat-number"><?php echo $uniqueMunicipalities; ?></div>
                <div class="stat-label">Affected areas</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-leaf"></i>
            </div>
            <div class="stat-details">
                <h3>Varieties</h3>
                <div class="stat-number"><?php echo $uniqueVarieties; ?></div>
                <div class="stat-label">Different types</div>
            </div>
        </div>
    </div>

    <!-- Chart Card -->
    <div class="chart-card">
        <h3>
            <i class="fas fa-chart-bar"></i>
            Mortality Distribution by Area
        </h3>
        <div class="chart-container">
            <canvas id="mortalityChart"></canvas>
        </div>
    </div>

    <!-- Table Card -->
    <div class="table-card">
        <div class="table-header">
            <h3>
                <i class="fas fa-list-alt"></i>
                Mortality Records
            </h3>
            <div class="entries-selector">
                <span>Show</span>
                <select id="entriesPerPage" onchange="changeEntriesPerPage()">
                    <option value="5" <?php echo $entriesPerPage == 5 ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo $entriesPerPage == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $entriesPerPage == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $entriesPerPage == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $entriesPerPage == 100 ? 'selected' : ''; ?>>100</option>
                </select>
                <span>entries</span>
            </div>
        </div>

        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th><i class="fas fa-calendar"></i> Date</th>
                    <th><i class="fas fa-city"></i> Municipality</th>
                    <th><i class="fas fa-map-pin"></i> Barangay</th>
                    <th><i class="fas fa-leaf"></i> Seedling Variety</th>
                    <th><i class="fas fa-skull"></i> Total Dead Seedlings</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($paginatedData)): ?>
                    <?php foreach ($paginatedData as $record): 
                        $died = (int)($record['died'] ?? 0);
                        $badgeClass = $died > 50 ? 'badge-high' : ($died > 20 ? 'badge-medium' : 'badge-low');
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($record['date'] ?? '') ?></td>
                        <td><?= htmlspecialchars($record['municipality'] ?? '') ?></td>
                        <td><?= htmlspecialchars($record['barangay'] ?? '') ?></td>
                        <td><?= htmlspecialchars($record['variety'] ?? '') ?></td>
                        <td>
                            <span class="mortality-badge <?php echo $badgeClass; ?>">
                                <?= number_format($died) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="fas fa-exclamation-circle" style="color: #f56565; font-size: 24px;"></i>
                            <br>
                            No mortality records found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination and Info -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px;">
            <div class="text-muted">
                Showing <?= min($offset + 1, $totalEntries) ?>
                to <?= min($offset + $entriesPerPage, $totalEntries) ?>
                of <?= $totalEntries ?> entries
            </div>

            <?php if ($totalPages > 1): ?>
            <ul class="pagination">
                <li class="page-item <?php echo $currentPage == 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?= $currentPage - 1 ?>&entriesPerPage=<?= $entriesPerPage ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                
                <?php 
                $start = max(1, $currentPage - 2);
                $end = min($totalPages, $currentPage + 2);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                <li class="page-item <?= $currentPage == $i ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&entriesPerPage=<?= $entriesPerPage ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?= $currentPage + 1 ?>&entriesPerPage=<?= $entriesPerPage ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Chart initialization
const ctx = document.getElementById('mortalityChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php 
            $topAreas = array_slice($data, 0, 5);
            echo json_encode(array_map(function($r) {
                return ($r['municipality'] ?? '') . ' - ' . ($r['barangay'] ?? '');
            }, $topAreas));
        ?>,
        datasets: [{
            label: 'Mortality Count',
            data: <?php 
                echo json_encode(array_map(function($r) {
                    return (int)($r['died'] ?? 0);
                }, $topAreas));
            ?>,
            backgroundColor: [
                'rgba(245, 101, 101, 0.8)',
                'rgba(237, 137, 54, 0.8)',
                'rgba(236, 201, 75, 0.8)',
                'rgba(159, 122, 234, 0.8)',
                'rgba(72, 187, 120, 0.8)'
            ],
            borderColor: [
                '#e53e3e',
                '#dd6b20',
                '#d69e2e',
                '#805ad5',
                '#38a169'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
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

// Function to change entries per page
function changeEntriesPerPage() {
    const entries = document.getElementById('entriesPerPage').value;
    window.location.href = '?page=1&entriesPerPage=' + entries;
}

// Add animation delays
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stat-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });
});

// Print optimization
window.onbeforeprint = function() {
    // Any pre-print adjustments
};
</script>

</body>
</html>