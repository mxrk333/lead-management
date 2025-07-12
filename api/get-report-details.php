<?php
// api/get-report-details.php
// This file fetches details for a single problem report and formats it for the modal.

// Adjust path resolution to reach the root config/database.php
require_once dirname(__DIR__) . '/config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<p style="color: #ef4444;">Invalid report ID.</p>';
    exit();
}

$report_id = (int)$_GET['id'];
$current_user_id = isset($_GET['current_user_id']) ? (int)$_GET['current_user_id'] : null; // Get current logged-in user ID

try {
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception("Failed to get database connection.");
    }

    // Fetch report details
    $report_query = "SELECT pr.*, 
                            TIMESTAMPDIFF(HOUR, pr.created_at, COALESCE(pr.resolved_at, NOW())) as hours_open,
                            u.username as assigned_username
                     FROM problem_reports pr
                     LEFT JOIN users u ON pr.assigned_to = u.id
                     WHERE pr.id = ?";
    $report_stmt = $conn->prepare($report_query);
    if (!$report_stmt) {
        throw new Exception("Failed to prepare report query: " . $conn->error);
    }
    $report_stmt->bind_param("i", $report_id);
    $report_stmt->execute();
    $report_result = $report_stmt->get_result();
    $report = $report_result->fetch_assoc();

    if (!$report) {
        echo '<p style="color: #ef4444;">Report not found.</p>';
        exit();
    }

    // Fetch team members for assignment dropdown (still needed for the modal's team_members array, even if dropdown is removed)
    $team_members = [];
    $team_query = "SELECT id, name, username FROM users WHERE role IN ('admin', 'manager') ORDER BY name";
    $team_result = $conn->query($team_query);
    while ($member = $team_result->fetch_assoc()) {
        $team_members[] = $member;
    }

    $conn->close();

    // Determine the selected assigned_to value for the dropdown (no longer used in form, but kept for context)
    $selected_assigned_to = $report['assigned_to'];
    if (empty($selected_assigned_to) && $current_user_id !== null) {
        // If report is unassigned, default to the current logged-in user
        $selected_assigned_to = $current_user_id;
    }

    // Display report details and update form
    ?>
    <div class="report-details-content">
        <div class="detail-item">
            <strong>Report ID:</strong> #<?php echo htmlspecialchars($report['id']); ?>
        </div>
        <div class="detail-item">
            <strong>Submitted By:</strong> <?php echo htmlspecialchars($report['username']); ?>
        </div>
        <div class="detail-item">
            <strong>Contact:</strong> <?php echo htmlspecialchars($report['phone']); ?>
            <?php if ($report['email']): ?>
                (<?php echo htmlspecialchars($report['email']); ?>)
            <?php endif; ?>
        </div>
        <div class="detail-item">
            <strong>Issue Type:</strong> <?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($report['issue_type']))); ?>
        </div>
        <div class="detail-item">
            <strong>Priority:</strong> 
            <span class="priority-badge priority-<?php echo htmlspecialchars($report['priority']); ?>">
                <?php echo ucfirst(htmlspecialchars($report['priority'])); ?>
            </span>
        </div>
        <div class="detail-item">
            <strong>Status:</strong> 
            <span class="status-badge status-<?php echo htmlspecialchars($report['status']); ?>">
                <?php echo ucfirst(str_replace('-', ' ', htmlspecialchars($report['status']))); ?>
            </span>
        </div>
        <div class="detail-item">
            <strong>Assigned To:</strong> 
            <?php echo htmlspecialchars($report['assigned_username'] ?: 'Unassigned'); ?>
        </div>
        <div class="detail-item">
            <strong>Submitted On:</strong> <?php echo date('M j, Y H:i', strtotime($report['created_at'])); ?>
        </div>
        <div class="detail-item">
            <strong>Last Updated:</strong> <?php echo date('M j, Y H:i', strtotime($report['updated_at'])); ?>
        </div>
        <?php if ($report['resolved_at']): ?>
        <div class="detail-item">
            <strong>Resolved On:</strong> <?php echo date('M j, Y H:i', strtotime($report['resolved_at'])); ?>
        </div>
        <?php endif; ?>
        <div class="detail-item">
            <strong>Hours Open:</strong> <?php echo htmlspecialchars($report['hours_open']); ?>h
        </div>
        <div class="detail-item full-width">
            <strong>Description:</strong><br>
            <p><?php echo nl2br(htmlspecialchars($report['description'])); ?></p>
        </div>
        <div class="detail-item full-width">
            <strong>Browser & System Info:</strong><br>
            <p><?php echo nl2br(htmlspecialchars($report['browser_info'])); ?></p>
        </div>

        <hr class="modal-separator">

        <h3>Update Report</h3>
        <form id="statusForm" onsubmit="event.preventDefault(); updateReport(<?php echo $report['id']; ?>);">
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="open" <?php echo $report['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in-progress" <?php echo $report['status'] === 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo $report['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
            </div>
            <!-- Removed Assigned To dropdown as per request -->
            <div class="form-group">
                <label for="resolution_notes">Remarks / Resolution Notes</label>
                <textarea id="resolution_notes" name="resolution_notes" placeholder="Add notes about the resolution or progress..."><?php echo htmlspecialchars($report['resolution_notes']); ?></textarea>
            </div>
            <button type="submit" class="btn-primary">Update Report</button>
        </form>
    </div>
    <style>
        /* Styles for report-details-content and form within modal */
        .report-details-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            padding-bottom: 1rem;
        }
        .detail-item {
            word-break: break-word;
            padding: 0.5rem 0; /* Added vertical padding */
        }
        .detail-item strong {
            color: #1f2937;
            font-weight: 600;
            display: block; /* Make strong a block for better spacing */
            margin-bottom: 0.25rem;
        }
        .detail-item.full-width {
            grid-column: span 2;
        }
        .detail-item p {
            margin-top: 0.5rem;
            background-color: #f9fafb;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            font-size: 0.875rem;
            color: #4b5563;
        }
        /* Separator in modal */
        .modal-separator {
            margin: 1.5rem 0;
            border: 0;
            border-top: 1px solid #e5e7eb;
        }
        @media (max-width: 600px) {
            .report-details-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php

} catch (Exception $e) {
    echo '<p style="color: #ef4444;">Error loading report details: ' . htmlspecialchars($e->getMessage()) . '</p>';
    error_log("Error in get-report-details.php: " . $e->getMessage());
}
?>
