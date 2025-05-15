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
    header('Location: invalid_commissions.php');
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

// Only execute the query if the PIN is verified
$childResults = [];
$parentResults = [];
$totalInvalid = 0;

if (isset($_SESSION['pin_verified'])) {
    // Handle form submission for updating records
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commission'])) {
        try {
            // Get original values for logging
            $getOriginalQuery = "SELECT Commission, ParentCommission, Name  
                                FROM Promoters 
                                WHERE PromoterID = :promoterID";
            $origStmt = $conn->prepare($getOriginalQuery);
            $origStmt->bindParam(':promoterID', $_POST['promoterID']);
            $origStmt->execute();
            $originalData = $origStmt->fetch(PDO::FETCH_ASSOC);
            
            // Prepare update statement
            $updateQuery = "UPDATE Promoters SET 
                            Commission = :commission,
                            ParentCommission = :parentCommission 
                            WHERE PromoterID = :promoterID";
            
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bindParam(':commission', $_POST['commission']);
            $updateStmt->bindParam(':parentCommission', $_POST['parentCommission']);
            $updateStmt->bindParam(':promoterID', $_POST['promoterID']);
            $updateStmt->execute();
            
            // Prepare log data
            $logData = [
                'childId' => $_POST['promoterID'],
                'childName' => $originalData['Name'],
                'oldChildCommission' => $originalData['Commission'] ? $originalData['Commission'] : '0',
                'newChildCommission' => $_POST['commission'],
                'oldParentCommission' => $originalData['ParentCommission'] ? $originalData['ParentCommission'] : '0',
                'newParentCommission' => $_POST['parentCommission'],
                'updatedBy' => isset($_SESSION['username']) ? $_SESSION['username'] : 'system',
                'ipAddress' => CommissionLogger::getClientIP(),
                'notes' => isset($_POST['update_notes']) ? $_POST['update_notes'] : 'Fixed invalid commission value'
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
    
    // Query for records with invalid (NULL, blank, NaN) child commission
    $childQuery = "SELECT 
                p.PromoterID, 
                p.PromoterUniqueID, 
                p.Name, 
                p.Commission, 
                p.ParentCommission, 
                p.ParentPromoterID,
                parent.Commission AS ActualParentCommission
            FROM Promoters p
            LEFT JOIN Promoters parent ON p.ParentPromoterID = parent.PromoterUniqueID
            WHERE p.Commission IS NULL 
                OR p.Commission = '' 
                OR p.Commission = 'NaN' 
                OR CAST(p.Commission AS DECIMAL) = 0
            ORDER BY p.Name";
            
    $childStmt = $conn->prepare($childQuery);
    $childStmt->execute();
    $childResults = $childStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query for records with invalid (NULL, blank, NaN) parent commission
    $parentQuery = "SELECT 
                p.PromoterID, 
                p.PromoterUniqueID, 
                p.Name, 
                p.Commission, 
                p.ParentCommission, 
                p.ParentPromoterID,
                parent.Commission AS ActualParentCommission
            FROM Promoters p
            LEFT JOIN Promoters parent ON p.ParentPromoterID = parent.PromoterUniqueID
            WHERE p.ParentCommission IS NULL 
                OR p.ParentCommission = '' 
                OR p.ParentCommission = 'NaN'
                OR CAST(p.ParentCommission AS DECIMAL) = 0
            ORDER BY p.Name";
            
    $parentStmt = $conn->prepare($parentQuery);
    $parentStmt->execute();
    $parentResults = $parentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate the total number of invalid records
    $totalInvalid = count($childResults) + count($parentResults);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Commission Values</title>
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
        .table-hover tbody tr:hover {
            background-color: #f1f5ff;
        }
        .badge-commission {
            font-size: 14px;
            padding: 6px 10px;
        }
        .header-container {
            background: linear-gradient(135deg, #dc3545, #b02a37);
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
        .nav-tabs .nav-link.active {
            background-color: #f8f9fa;
            border-bottom-color: #f8f9fa;
            font-weight: bold;
        }
        .nav-tabs .nav-link {
            color: #495057;
        }
        .invalid-value {
            color: #dc3545;
            font-style: italic;
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
                                <h5>Enter PIN to Access Invalid Commissions</h5>
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
                <h1 class="text-center"><i class="bi bi-exclamation-triangle me-2"></i>Invalid Commission Values</h1>
                <p class="text-center mb-0">Records with missing, blank, or invalid commission values</p>
            </div>
            
            <?php if (isset($updateMessage)): ?>
                <div class="alert alert-<?= $updateStatus ?> alert-dismissible fade show" role="alert">
                    <?= $updateMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Commission Report
                </a>
                <div>
                    <button class="btn btn-success me-2" onclick="exportTableToExcel('active-table')">
                        <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i>Print Report
                    </button>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="bi bi-info-circle me-2"></i>Found <strong><?= $totalInvalid ?></strong> promoters with invalid commission values. These may cause calculation issues and should be corrected.
            </div>
            
            <ul class="nav nav-tabs" id="commissionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="child-comm-tab" data-bs-toggle="tab" data-bs-target="#child-comm" type="button" role="tab" aria-controls="child-comm" aria-selected="true">
                        <i class="bi bi-person me-1"></i>Invalid Child Commission (<?= count($childResults) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="parent-comm-tab" data-bs-toggle="tab" data-bs-target="#parent-comm" type="button" role="tab" aria-controls="parent-comm" aria-selected="false">
                        <i class="bi bi-people me-1"></i>Invalid Parent Commission (<?= count($parentResults) ?>)
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="commissionTabsContent">
                <!-- Invalid Child Commission Tab -->
                <div class="tab-pane fade show active" id="child-comm" role="tabpanel" aria-labelledby="child-comm-tab">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-person-x me-2"></i>Promoters with Invalid Child Commission</h5>
                                <span class="badge bg-light text-dark"><?= count($childResults) ?> Records Found</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($childResults) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="child-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Promoter ID</th>
                                                <th scope="col">Unique ID</th>
                                                <th scope="col">Name</th>
                                                <th scope="col">Child Commission</th>
                                                <th scope="col">Parent Commission</th>
                                                <th scope="col">Parent Promoter ID</th>
                                                <th scope="col">Actual Parent Commission</th>
                                                <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($childResults as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['PromoterID']) ?></td>
                                                    <td><?= htmlspecialchars($row['PromoterUniqueID']) ?></td>
                                                    <td><?= htmlspecialchars($row['Name']) ?></td>
                                                    <td class="invalid-value">
                                                        <?= $row['Commission'] ? htmlspecialchars($row['Commission']) : '<span class="badge bg-danger">Missing</span>' ?>
                                                    </td>
                                                    <td>
                                                        <?= htmlspecialchars($row['ParentCommission']) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['ParentPromoterID']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($row['ActualParentCommission']) ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" 
                                                                class="btn btn-warning action-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal"
                                                                data-promoter-id="<?= htmlspecialchars($row['PromoterID']) ?>"
                                                                data-promoter-name="<?= htmlspecialchars($row['Name']) ?>"
                                                                data-commission="<?= htmlspecialchars($row['Commission']) ?>"
                                                                data-parent-commission="<?= htmlspecialchars($row['ParentCommission']) ?>"
                                                                data-actual-parent-commission="<?= htmlspecialchars($row['ActualParentCommission']) ?>">
                                                            <i class="bi bi-pencil-square"></i> Fix
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>No records found with invalid child commission values.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Invalid Parent Commission Tab -->
                <div class="tab-pane fade" id="parent-comm" role="tabpanel" aria-labelledby="parent-comm-tab">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Promoters with Invalid Parent Commission</h5>
                                <span class="badge bg-light text-dark"><?= count($parentResults) ?> Records Found</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($parentResults) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="parent-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Promoter ID</th>
                                                <th scope="col">Unique ID</th>
                                                <th scope="col">Name</th>
                                                <th scope="col">Child Commission</th>
                                                <th scope="col">Parent Commission</th>
                                                <th scope="col">Parent Promoter ID</th>
                                                <th scope="col">Actual Parent Commission</th>
                                                <th scope="col">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parentResults as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['PromoterID']) ?></td>
                                                    <td><?= htmlspecialchars($row['PromoterUniqueID']) ?></td>
                                                    <td><?= htmlspecialchars($row['Name']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($row['Commission']) ?>
                                                    </td>
                                                    <td class="invalid-value">
                                                        <?= $row['ParentCommission'] ? htmlspecialchars($row['ParentCommission']) : '<span class="badge bg-danger">Missing</span>' ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['ParentPromoterID']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($row['ActualParentCommission']) ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" 
                                                                class="btn btn-warning action-btn" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal"
                                                                data-promoter-id="<?= htmlspecialchars($row['PromoterID']) ?>"
                                                                data-promoter-name="<?= htmlspecialchars($row['Name']) ?>"
                                                                data-commission="<?= htmlspecialchars($row['Commission']) ?>"
                                                                data-parent-commission="<?= htmlspecialchars($row['ParentCommission']) ?>"
                                                                data-actual-parent-commission="<?= htmlspecialchars($row['ActualParentCommission']) ?>">
                                                            <i class="bi bi-pencil-square"></i> Fix
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>No records found with invalid parent commission values.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Modal -->
            <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title" id="editModalLabel"><i class="bi bi-pencil-square me-2"></i>Fix Commission Value</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="promoterID" id="promoterID">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Promoter</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                                        <input type="text" class="form-control" id="promoterName" disabled>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="commission" class="form-label fw-bold">Child Commission</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                        <input type="number" step="0.01" class="form-control" id="commission" name="commission" required>
                                    </div>
                                    <div class="form-text">Enter the correct commission amount for this promoter.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="parentCommission" class="form-label fw-bold">Parent Commission</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                        <input type="number" step="0.01" class="form-control" id="parentCommission" name="parentCommission" required>
                                    </div>
                                    <div class="form-text">Enter the correct parent commission amount.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Actual Parent Commission <small class="text-muted">(from parent record)</small></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-currency-dollar"></i></span>
                                        <input type="text" class="form-control" id="actualParentCommission" disabled>
                                        <button type="button" class="btn btn-secondary" id="useActualCommission">Use This Value</button>
                                    </div>
                                    <div class="form-text">This is the commission value from the parent's record.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="update_notes" class="form-label fw-bold">Update Notes</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-journal-text"></i></span>
                                        <textarea class="form-control" id="update_notes" name="update_notes" rows="2" placeholder="Reason for the update (optional)">Fixed invalid commission value</textarea>
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
            let activeTab = document.querySelector('.tab-pane.active');
            let tableSelect = activeTab.querySelector('table');
            
            if (!tableSelect) {
                alert('No table found to export');
                return;
            }
            
            let tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            // Specify file name
            let filename = 'invalid_commissions_' + new Date().toISOString().slice(0,10) + '.xls';
            
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
                    const promoterId = button.getAttribute('data-promoter-id');
                    const promoterName = button.getAttribute('data-promoter-name');
                    const commission = button.getAttribute('data-commission') || '0';
                    const parentCommission = button.getAttribute('data-parent-commission') || '0';
                    const actualParentCommission = button.getAttribute('data-actual-parent-commission') || '';
                    
                    // Update modal fields
                    document.getElementById('promoterID').value = promoterId;
                    document.getElementById('promoterName').value = promoterName;
                    document.getElementById('commission').value = commission === 'NaN' || commission === '' ? '0' : commission;
                    document.getElementById('parentCommission').value = parentCommission === 'NaN' || parentCommission === '' ? '0' : parentCommission;
                    document.getElementById('actualParentCommission').value = actualParentCommission === 'NaN' || actualParentCommission === '' ? '[Not Available]' : actualParentCommission;
                    
                    // Set up the "Use This Value" button 
                    const useActualBtn = document.getElementById('useActualCommission');
                    
                    // Remove any existing event listeners to prevent duplicates
                    const newUseActualBtn = useActualBtn.cloneNode(true);
                    useActualBtn.parentNode.replaceChild(newUseActualBtn, useActualBtn);
                    
                    // Add new event listener
                    newUseActualBtn.addEventListener('click', function() {
                        if (actualParentCommission && actualParentCommission !== 'NaN' && actualParentCommission !== '') {
                            document.getElementById('parentCommission').value = actualParentCommission;
                        }
                    });
                });
            }
        });
    </script>
</body>
</html> 