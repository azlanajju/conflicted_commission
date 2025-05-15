<?php
// Start the session to maintain login status
session_start();

require_once 'config.php';
require_once 'logger.php';

// Define the correct PIN - keep this the same as in index.php
define('ACCESS_PIN', '574231'); // Change this to match your preferred PIN

// Check if logout action is requested
if (isset($_GET['logout'])) {
    // Destroy the session
    $_SESSION = array();
    session_destroy();
    // Redirect to the same page
    header('Location: view_logs.php');
    exit;
}

// Check if PIN form is submitted
$pinError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_pin'])) {
    $inputPin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    
    if ($inputPin === ACCESS_PIN) {
        // PIN is correct, set session
        $_SESSION['pin_verified'] = true;
        $_SESSION['username'] = $username; // Store username in session
    } else {
        // PIN is incorrect
        $pinError = 'Invalid PIN. Please try again.';
    }
}

// Only proceed if user is authenticated
if (!isset($_SESSION['pin_verified'])) {
    // Display PIN form below
    $logs = [];
    $totalRecords = 0;
    $totalPages = 0;
    $fileLogContents = [];
} else {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();

    // Default limit and page
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Check for filters
    $whereClause = '';
    $params = [];

    if (isset($_GET['child_id']) && !empty($_GET['child_id'])) {
        $whereClause .= " AND ChildPromoterID = :childId";
        $params[':childId'] = $_GET['child_id'];
    }

    if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
        $whereClause .= " AND DATE(UpdatedAt) >= :startDate";
        $params[':startDate'] = $_GET['start_date'];
    }

    if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
        $whereClause .= " AND DATE(UpdatedAt) <= :endDate";
        $params[':endDate'] = $_GET['end_date'];
    }

    // Prepare query
    $query = "SELECT * FROM CommissionUpdateLogs WHERE 1=1" . $whereClause . " ORDER BY UpdatedAt DESC LIMIT :offset, :limit";
    $countQuery = "SELECT COUNT(*) as total FROM CommissionUpdateLogs WHERE 1=1" . $whereClause;

    // Execute count query
    $countStmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Execute main query
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if file logs exist
    $fileLogPath = 'logs/commission_updates.log';
    $fileLogsExist = file_exists($fileLogPath);
    $fileLogContents = [];

    if ($fileLogsExist) {
        $fileLogContents = file($fileLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        // Get only the most recent entries (last 100 lines)
        $fileLogContents = array_slice($fileLogContents, -100);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Update Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .table th {
            background-color: #f1f5ff;
        }
        .commission-change {
            font-weight: bold;
        }
        .commission-positive {
            color: #198754;
        }
        .commission-negative {
            color: #dc3545;
        }
        .header-container {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-bottom-color: #f8f9fa;
            font-weight: bold;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .filter-form {
            padding: 15px;
            background-color: #f1f5ff;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .file-log {
            font-family: Consolas, monospace;
            font-size: 0.9rem;
            background-color: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .pin-container {
            max-width: 400px;
            margin: 80px auto;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 100;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <?php if (!isset($_SESSION['pin_verified'])): ?>
            <!-- PIN verification form -->
            <div class="pin-container">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 text-center"><i class="bi bi-shield-lock me-2"></i>Security Verification</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-4 text-center">
                                <img src="https://img.icons8.com/color/96/000000/password--v1.png" alt="Security" class="mb-3" />
                                <h5>Enter PIN to Access Commission Logs</h5>
                                <p class="text-muted">This page is protected. Please enter the access PIN to continue.</p>
                            </div>
                            
                            <?php if (!empty($pinError)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pinError ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Your Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your name" required autofocus>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="pin" class="form-label">Access PIN</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                    <input type="password" class="form-control" id="pin" name="pin" placeholder="Enter PIN" required>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="verify_pin" class="btn btn-primary">
                                    <i class="bi bi-unlock me-2"></i>Verify & Access
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Logout button for verified users -->
            <a href="?logout=1" class="btn btn-outline-danger logout-btn">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
            
            <div class="header-container p-4 mb-4">
                <h1 class="text-center"><i class="bi bi-journal-check me-2"></i>Commission Update Logs</h1>
                <p class="text-center mb-0">View history of all commission updates</p>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Commission Report
                </a>
                <div>
                    <button class="btn btn-success me-2" onclick="exportTableToExcel('logs-table')">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print Logs
                    </button>
                </div>
            </div>
            
            <ul class="nav nav-tabs" id="logTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="database-logs-tab" data-bs-toggle="tab" data-bs-target="#database-logs" type="button" role="tab" aria-controls="database-logs" aria-selected="true">
                        <i class="bi bi-database me-1"></i>Database Logs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="file-logs-tab" data-bs-toggle="tab" data-bs-target="#file-logs" type="button" role="tab" aria-controls="file-logs" aria-selected="false">
                        <i class="bi bi-file-text me-1"></i>File Logs
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="logTabsContent">
                <!-- Database Logs Tab -->
                <div class="tab-pane fade show active" id="database-logs" role="tabpanel" aria-labelledby="database-logs-tab">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Commission Update History</h5>
                                <span class="badge bg-light text-dark"><?= $totalRecords ?> Records Found</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filter Form -->
                            <form class="filter-form mb-4" method="GET">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="child_id" class="form-label">Child Promoter ID</label>
                                        <input type="text" class="form-control" id="child_id" name="child_id" value="<?= isset($_GET['child_id']) ? htmlspecialchars($_GET['child_id']) : '' ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control date-picker" id="start_date" name="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '' ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control date-picker" id="end_date" name="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '' ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="limit" class="form-label">Records Per Page</label>
                                        <select class="form-select" id="limit" name="limit">
                                            <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                                            <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                                        </select>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-filter me-1"></i>Apply Filters
                                        </button>
                                        <a href="view_logs.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle me-1"></i>Clear Filters
                                        </a>
                                    </div>
                                </div>
                            </form>
                            
                            <?php if (count($logs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="logs-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Log ID</th>
                                                <th scope="col">Child ID</th>
                                                <th scope="col">Child Name</th>
                                                <th scope="col">Child Commission</th>
                                                <th scope="col">Parent Commission</th>
                                                <th scope="col">Updated By</th>
                                                <th scope="col">Updated At</th>
                                                <th scope="col">IP Address</th>
                                                <th scope="col">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($logs as $log): ?>
                                                <?php 
                                                    $childCommDiff = $log['NewChildCommission'] - $log['OldChildCommission'];
                                                    $parentCommDiff = $log['NewParentCommission'] - $log['OldParentCommission'];
                                                    
                                                    $childCommClass = $childCommDiff > 0 ? 'commission-positive' : ($childCommDiff < 0 ? 'commission-negative' : '');
                                                    $parentCommClass = $parentCommDiff > 0 ? 'commission-positive' : ($parentCommDiff < 0 ? 'commission-negative' : '');
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($log['LogID']) ?></td>
                                                    <td><?= htmlspecialchars($log['ChildPromoterID']) ?></td>
                                                    <td><?= htmlspecialchars($log['ChildPromoterName']) ?></td>
                                                    <td class="commission-change">
                                                        <?= htmlspecialchars($log['OldChildCommission']) ?> → 
                                                        <span class="<?= $childCommClass ?>">
                                                            <?= htmlspecialchars($log['NewChildCommission']) ?>
                                                            <?php if ($childCommDiff > 0): ?>
                                                                <i class="bi bi-arrow-up-short"></i>
                                                            <?php elseif ($childCommDiff < 0): ?>
                                                                <i class="bi bi-arrow-down-short"></i>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td class="commission-change">
                                                        <?= htmlspecialchars($log['OldParentCommission']) ?> → 
                                                        <span class="<?= $parentCommClass ?>">
                                                            <?= htmlspecialchars($log['NewParentCommission']) ?>
                                                            <?php if ($parentCommDiff > 0): ?>
                                                                <i class="bi bi-arrow-up-short"></i>
                                                            <?php elseif ($parentCommDiff < 0): ?>
                                                                <i class="bi bi-arrow-down-short"></i>
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($log['UpdatedBy']) ?></td>
                                                    <td><?= htmlspecialchars($log['UpdatedAt']) ?></td>
                                                    <td><?= htmlspecialchars($log['IPAddress']) ?></td>
                                                    <td><?= htmlspecialchars($log['Notes']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center mt-4">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?><?= isset($_GET['child_id']) ? '&child_id='.urlencode($_GET['child_id']) : '' ?><?= isset($_GET['start_date']) ? '&start_date='.urlencode($_GET['start_date']) : '' ?><?= isset($_GET['end_date']) ? '&end_date='.urlencode($_GET['end_date']) : '' ?>" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            
                                            <?php
                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $startPage + 4);
                                            
                                            if ($endPage - $startPage < 4) {
                                                $startPage = max(1, $endPage - 4);
                                            }
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?><?= isset($_GET['child_id']) ? '&child_id='.urlencode($_GET['child_id']) : '' ?><?= isset($_GET['start_date']) ? '&start_date='.urlencode($_GET['start_date']) : '' ?><?= isset($_GET['end_date']) ? '&end_date='.urlencode($_GET['end_date']) : '' ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?><?= isset($_GET['child_id']) ? '&child_id='.urlencode($_GET['child_id']) : '' ?><?= isset($_GET['start_date']) ? '&start_date='.urlencode($_GET['start_date']) : '' ?><?= isset($_GET['end_date']) ? '&end_date='.urlencode($_GET['end_date']) : '' ?>" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>No log records found matching the criteria.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- File Logs Tab -->
                <div class="tab-pane fade" id="file-logs" role="tabpanel" aria-labelledby="file-logs-tab">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-file-text me-2"></i>File-based Log History</h5>
                                <span class="badge bg-light text-dark"><?= count($fileLogContents) ?> Entries</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($fileLogsExist && count($fileLogContents) > 0): ?>
                                <div class="file-log">
                                    <?php foreach ($fileLogContents as $line): ?>
                                        <?= htmlspecialchars($line) ?><br>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>No file log entries found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr(".date-picker", {
                dateFormat: "Y-m-d",
                allowInput: true
            });
        });

        // Function to export table to Excel
        function exportTableToExcel(tableID) {
            let tableSelect = document.querySelector('#' + tableID);
            let tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            // Specify file name
            let filename = 'commission_logs_' + new Date().toISOString().slice(0,10) + '.xls';
            
            // Create download link
            let downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            
            // Create a link to the file
            if (navigator.msSaveOrOpenBlob) {
                let blob = new Blob(['\ufeff', tableHTML], {
                    type: 'application/vnd.ms-excel'
                });
                navigator.msSaveOrOpenBlob(blob, filename);
            } else {
                downloadLink.href = 'data:application/vnd.ms-excel,' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
        }
    </script>
</body>
</html> 