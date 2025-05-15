<?php
// Start the session to maintain login status
session_start();

require_once 'config.php';
require_once 'logger.php';

// Define the correct PIN - you can change this to any PIN you want
define('ACCESS_PIN', '574231'); // Change this to your preferred PIN

// Check if logout action is requested
if (isset($_GET['logout'])) {
    // Destroy the session
    $_SESSION = array();
    session_destroy();
    // Redirect to the same page
    header('Location: index.php');
    exit;
}

// Check if PIN form is submitted
$pinError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_pin'])) {
    $inputPin = isset($_POST['pin']) ? trim($_POST['pin']) : '';
    
    if ($inputPin === ACCESS_PIN) {
        // PIN is correct, set session
        $_SESSION['pin_verified'] = true;
    } else {
        // PIN is incorrect
        $pinError = 'Invalid PIN. Please try again.';
    }
}

// Initialize database connection
$database = new Database();
$conn = $database->getConnection();

// Initialize logger
$logger = new CommissionLogger($conn);

// Handle form submission for updating records if PIN is verified
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commission']) && isset($_SESSION['pin_verified'])) {
    try {
        // Get original values for logging
        $getOriginalQuery = "SELECT child.Commission AS ChildCommission, child.ParentCommission, child.Name AS ChildName  
                            FROM Promoters child 
                            WHERE child.PromoterID = :childPromoterID";
        $origStmt = $conn->prepare($getOriginalQuery);
        $origStmt->bindParam(':childPromoterID', $_POST['childPromoterID']);
        $origStmt->execute();
        $originalData = $origStmt->fetch(PDO::FETCH_ASSOC);
        
        // Prepare update statement for child promoter
        $updateChildQuery = "UPDATE Promoters SET 
                            Commission = :childCommission,
                            ParentCommission = :parentCommission 
                            WHERE PromoterID = :childPromoterID";
        
        $updateChildStmt = $conn->prepare($updateChildQuery);
        $updateChildStmt->bindParam(':childCommission', $_POST['childCommission']);
        $updateChildStmt->bindParam(':parentCommission', $_POST['parentCommission']);
        $updateChildStmt->bindParam(':childPromoterID', $_POST['childPromoterID']);
        $updateChildStmt->execute();
        
        // Prepare log data
        $logData = [
            'childId' => $_POST['childPromoterID'],
            'childName' => $originalData['ChildName'],
            'oldChildCommission' => $originalData['ChildCommission'],
            'newChildCommission' => $_POST['childCommission'],
            'oldParentCommission' => $originalData['ParentCommission'],
            'newParentCommission' => $_POST['parentCommission'],
            'updatedBy' => isset($_SESSION['username']) ? $_SESSION['username'] : 'system',
            'ipAddress' => CommissionLogger::getClientIP(),
            'notes' => isset($_POST['update_notes']) ? $_POST['update_notes'] : 'Commission update from conflict report'
        ];
        
        // Log the update
        $logger->logCommissionUpdate($logData);
        
        // Set success message
        $updateMessage = "Record updated successfully!";
        $updateStatus = "success";
    } catch (PDOException $e) {
        // Set error message
        $updateMessage = "Error updating record: " . $e->getMessage();
        $updateStatus = "danger";
    }
}

// Only execute the query if the PIN is verified
$results = [];
if (isset($_SESSION['pin_verified'])) {
    // Execute the query to get promoter commission data
    $query = "SELECT 
                child.PromoterID AS ChildPromoterID, 
                child.PromoterUniqueID AS ChildPromoterUniqueID, 
                child.Name AS ChildName, 
                child.Commission AS ChildCommission, 
                child.ParentCommission, 
                parent.PromoterID AS ParentPromoterID, 
                parent.PromoterUniqueID AS ParentPromoterUniqueID, 
                parent.Name AS ParentName, 
                parent.Commission AS ParentCommission 
            FROM Promoters child 
            JOIN Promoters parent ON child.ParentPromoterID = parent.PromoterUniqueID 
            WHERE CAST(child.ParentCommission AS DECIMAL) = CAST(parent.Commission AS DECIMAL)";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conflicted Commission Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .table th {
            background-color: #f1f5ff;
        }
        .table-hover tbody tr:hover {
            background-color: #f1f5ff;
        }
        .badge-commission {
            font-size: 14px;
            padding: 6px 10px;
        }
        .header-container {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .action-btn {
            padding: 4px 8px;
            font-size: 0.85rem;
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
        <!-- PIN verification form -->
        <?php if (!isset($_SESSION['pin_verified'])): ?>
            <div class="pin-container">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0 text-center"><i class="bi bi-shield-lock me-2"></i>Security Verification</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-4 text-center">
                                <img src="https://img.icons8.com/color/96/000000/password--v1.png" alt="Security" class="mb-3" />
                                <h5>Enter PIN to Access Commission Report</h5>
                                <p class="text-muted">This page is protected. Please enter the access PIN to continue.</p>
                            </div>
                            
                            <?php if (!empty($pinError)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $pinError ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="pin" class="form-label">Access PIN</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                    <input type="password" class="form-control" id="pin" name="pin" placeholder="Enter PIN" required autofocus>
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
                <h1 class="text-center"><i class="bi bi-cash-coin me-2"></i>Conflicted Commission Report</h1>
                <p class="text-center mb-0">Displaying commission records where child's parent commission matches parent's commission</p>
            </div>
            
            <?php if (isset($updateMessage)): ?>
                <div class="alert alert-<?= $updateStatus ?> alert-dismissible fade show" role="alert">
                    <?= $updateMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-table me-2"></i>Commission Data</h5>
                        <span class="badge bg-light text-dark"><?= count($results) ?> Records Found</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($results) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">Child ID</th>
                                        <th scope="col">Child Unique ID</th>
                                        <th scope="col">Child Name</th>
                                        <th scope="col">Child Commission</th>
                                        <th scope="col">Parent Commission</th>
                                        <th scope="col">Parent ID</th>
                                        <th scope="col">Parent Unique ID</th>
                                        <th scope="col">Parent Name</th>
                                        <th scope="col">Parent Commission</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['ChildPromoterID']) ?></td>
                                            <td><?= htmlspecialchars($row['ChildPromoterUniqueID']) ?></td>
                                            <td><?= htmlspecialchars($row['ChildName']) ?></td>
                                            <td>
                                                <span class="badge bg-primary badge-commission">
                                                    <?= htmlspecialchars($row['ChildCommission']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info badge-commission">
                                                    <?= htmlspecialchars($row['ParentCommission']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($row['ParentPromoterID']) ?></td>
                                            <td><?= htmlspecialchars($row['ParentPromoterUniqueID']) ?></td>
                                            <td><?= htmlspecialchars($row['ParentName']) ?></td>
                                            <td>
                                                <span class="badge bg-success badge-commission">
                                                    <?= htmlspecialchars($row['ParentCommission']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-warning action-btn" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editModal"
                                                        data-child-id="<?= htmlspecialchars($row['ChildPromoterID']) ?>"
                                                        data-child-name="<?= htmlspecialchars($row['ChildName']) ?>"
                                                        data-child-commission="<?= htmlspecialchars($row['ChildCommission']) ?>"
                                                        data-parent-commission="<?= htmlspecialchars($row['ParentCommission']) ?>"
                                                        data-parent-id="<?= htmlspecialchars($row['ParentPromoterID']) ?>"
                                                        data-parent-name="<?= htmlspecialchars($row['ParentName']) ?>">
                                                    <i class="bi bi-pencil-square"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>No records found matching the criteria.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-4 text-center">
                <button class="btn btn-success me-2" onclick="exportTableToExcel('commission-table')">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print Report
                </button>
                <a href="view_logs.php" class="btn btn-info ms-2">
                    <i class="bi bi-journal-text me-1"></i>View Update Logs
                </a>
            </div>
            
            <!-- Edit Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Commission</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="childPromoterID" id="childPromoterID">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Child Promoter</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control" id="childName" disabled>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="childCommission" class="form-label fw-bold">Child Commission</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                        <input type="number" step="0.01" class="form-control" id="childCommission" name="childCommission" required>
                                    </div>
                                    <div class="form-text">Enter the new commission amount for the child promoter.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Parent Promoter</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                        <input type="text" class="form-control" id="parentName" disabled>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="parentCommission" class="form-label fw-bold">Parent Commission</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                        <input type="number" step="0.01" class="form-control" id="parentCommission" name="parentCommission" required>
                                    </div>
                                    <div class="form-text">Enter the new parent commission amount.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="update_notes" class="form-label fw-bold">Update Notes</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-journal-text"></i></span>
                                        <textarea class="form-control" id="update_notes" name="update_notes" rows="2" placeholder="Reason for the update (optional)"></textarea>
                                    </div>
                                    <div class="form-text">Add notes about why you're making this update.</div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="update_commission" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to export table to Excel
        function exportTableToExcel(tableID) {
            let tableSelect = document.querySelector('.table');
            let tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            // Specify file name
            let filename = 'commission_report_' + new Date().toISOString().slice(0,10) + '.xls';
            
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
        
        // Handle modal data
        document.addEventListener('DOMContentLoaded', function() {
            const editModal = document.getElementById('editModal');
            if (editModal) {
                editModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    
                    // Extract data from button attributes
                    const childId = button.getAttribute('data-child-id');
                    const childName = button.getAttribute('data-child-name');
                    const childCommission = button.getAttribute('data-child-commission');
                    const parentCommission = button.getAttribute('data-parent-commission');
                    const parentName = button.getAttribute('data-parent-name');
                    
                    // Update modal fields
                    document.getElementById('childPromoterID').value = childId;
                    document.getElementById('childName').value = childName;
                    document.getElementById('childCommission').value = childCommission;
                    document.getElementById('parentCommission').value = parentCommission;
                    document.getElementById('parentName').value = parentName;
                });
            }
        });
    </script>
</body>
</html>
