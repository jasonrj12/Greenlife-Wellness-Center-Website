<?php
/**
 * Admin Inquiry Management
 * GreenLife Wellness Center
 */

$pageTitle = "Manage Inquiries";
require_once __DIR__ . '/includes/functions.php';

// Require admin access
requireRole('admin');

$error = '';
$success = '';

// Handle inquiry actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $inquiryId = $_POST['inquiry_id'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        if ($action === 'respond' && $inquiryId) {
            $response = sanitizeInput($_POST['response'] ?? '');
            
            if (!empty($response)) {
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("UPDATE inquiries SET admin_response = ?, responded_at = NOW(), responded_by = ? WHERE inquiry_id = ?");
                    $result = $stmt->execute([$response, $_SESSION['user_id'], $inquiryId]);
                    
                    if ($result) {
                        $success = 'Response sent successfully';
                        logAdminAction($_SESSION['user_id'], "Responded to inquiry ID: $inquiryId");
                    } else {
                        $error = 'Failed to send response';
                    }
                } catch (PDOException $e) {
                    $error = 'Error sending response: ' . $e->getMessage();
                }
            } else {
                $error = 'Please enter a response';
            }
        } elseif ($action === 'delete' && $inquiryId) {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("DELETE FROM inquiries WHERE inquiry_id = ?");
                $result = $stmt->execute([$inquiryId]);
                
                if ($result) {
                    $success = 'Inquiry deleted successfully';
                    logAdminAction($_SESSION['user_id'], "Deleted inquiry ID: $inquiryId");
                } else {
                    $error = 'Failed to delete inquiry';
                }
            } catch (PDOException $e) {
                $error = 'Error deleting inquiry: ' . $e->getMessage();
            }
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$subjectFilter = $_GET['subject'] ?? '';

// Build query conditions
$conditions = [];
$params = [];

if ($statusFilter === 'answered') {
    $conditions[] = "i.admin_response IS NOT NULL";
} elseif ($statusFilter === 'unanswered') {
    $conditions[] = "i.admin_response IS NULL";
}

if ($subjectFilter) {
    $conditions[] = "i.subject LIKE ?";
    $params[] = "%$subjectFilter%";
}

$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get inquiries with pagination
$page = $_GET['page'] ?? 1;
$limit = 15;
$offset = ($page - 1) * $limit;

try {
    $pdo = getDBConnection();
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM inquiries i $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalInquiries = $stmt->fetch()['total'];
    $totalPages = ceil($totalInquiries / $limit);
    
    // Get inquiries for current page
    $query = "
        SELECT i.*, 
               u.name as client_name, u.email as client_email,
               admin.name as admin_name
        FROM inquiries i
        LEFT JOIN users u ON i.user_id = u.user_id
        LEFT JOIN users admin ON i.responded_by = admin.user_id
        $whereClause
        ORDER BY i.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $inquiries = $stmt->fetchAll();
    
    // Get inquiry statistics
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inquiries");
    $totalCount = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inquiries WHERE admin_response IS NULL");
    $unansweredCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inquiries WHERE admin_response IS NOT NULL");
    $answeredCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inquiries WHERE DATE(created_at) = CURDATE()");
    $todayCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inquiries WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $weekCount = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $error = 'Error fetching inquiries: ' . $e->getMessage();
    $inquiries = [];
    $totalPages = 1;
    $totalCount = $unansweredCount = $answeredCount = $todayCount = $weekCount = 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-envelope"></i> Manage Inquiries</h1>
            <p>View and respond to customer inquiries and support requests</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <!-- Inquiry Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-envelope"></i></div>
                <div class="number"><?php echo $totalCount; ?></div>
                <div class="label">Total Inquiries</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-clock"></i></div>
                <div class="number"><?php echo $unansweredCount; ?></div>
                <div class="label">Unanswered</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <div class="number"><?php echo $answeredCount; ?></div>
                <div class="label">Answered</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-calendar-day"></i></div>
                <div class="number"><?php echo $todayCount; ?></div>
                <div class="label">Today</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-calendar-week"></i></div>
                <div class="number"><?php echo $weekCount; ?></div>
                <div class="label">This Week</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-lightning-bolt"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-3">
                        <a href="manage_inquiries.php?status=unanswered" class="btn btn-primary btn-lg" style="width: 100%;">
                            <i class="fas fa-clock"></i><br>Pending Inquiries
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="manage_inquiries.php?status=answered" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-check-circle"></i><br>Answered Inquiries
                        </a>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-outline btn-lg" onclick="showBulkActions()" style="width: 100%;">
                            <i class="fas fa-tasks"></i><br>Bulk Actions
                        </button>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-outline btn-lg" onclick="exportInquiries()" style="width: 100%;">
                            <i class="fas fa-download"></i><br>Export Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filter & Search Inquiries</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control form-select">
                                <option value="">All Inquiries</option>
                                <option value="unanswered" <?php echo $statusFilter === 'unanswered' ? 'selected' : ''; ?>>Unanswered</option>
                                <option value="answered" <?php echo $statusFilter === 'answered' ? 'selected' : ''; ?>>Answered</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="subject" class="form-label">Search Subject</label>
                            <input type="text" id="subject" name="subject" class="form-control" 
                                   value="<?php echo htmlspecialchars($subjectFilter); ?>" 
                                   placeholder="Search by subject...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                                <a href="manage_inquiries.php" class="btn btn-outline">Clear</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Inquiries List -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> All Inquiries</h3>
                <div class="card-actions">
                    <span class="badge badge-primary"><?php echo $totalInquiries; ?> Total</span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($inquiries)): ?>
                    <div class="text-center p-5">
                        <i class="fas fa-envelope-open" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <h4>No inquiries found</h4>
                        <p class="text-muted">No customer inquiries match your current filters.</p>
                        <a href="manage_inquiries.php" class="btn btn-outline">
                            <i class="fas fa-refresh me-2"></i>View All Inquiries
                        </a>
                    </div>
                <?php else: ?>
                    <div class="inquiries-list">
                        <?php foreach ($inquiries as $inquiry): ?>
                            <div class="inquiry-card" data-inquiry-id="<?php echo $inquiry['inquiry_id']; ?>">
                                <div class="inquiry-header">
                                    <div class="inquiry-info">
                                        <h5><?php echo htmlspecialchars($inquiry['subject']); ?></h5>
                                        <div class="inquiry-meta">
                                            <span class="text-muted">
                                                <i class="fas fa-user fa-sm"></i>
                                                From: <?php echo $inquiry['client_name'] ? htmlspecialchars($inquiry['client_name']) : 'Guest'; ?>
                                                <?php if ($inquiry['client_email']): ?>
                                                    (<?php echo htmlspecialchars($inquiry['client_email']); ?>)
                                                <?php endif; ?>
                                            </span>
                                            <span class="text-muted ms-3">
                                                <i class="fas fa-calendar fa-sm"></i>
                                                <?php echo formatDate($inquiry['created_at']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="inquiry-status">
                                        <?php if (!empty($inquiry['admin_response'])): ?>
                                            <span class="badge badge-success">Answered</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="inquiry-content">
                                    <div class="original-message">
                                        <h6><i class="fas fa-comment"></i> Message:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($inquiry['message'])); ?></p>
                                    </div>
                                    
                                    <?php if (!empty($inquiry['admin_response'])): ?>
                                        <div class="admin-response">
                                            <h6><i class="fas fa-reply"></i> Response:</h6>
                                            <div class="response-content">
                                                <p><?php echo nl2br(htmlspecialchars($inquiry['admin_response'])); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-user-shield fa-sm"></i>
                                                    Responded by: <?php echo htmlspecialchars($inquiry['admin_name']); ?> 
                                                    on <?php echo formatDate($inquiry['responded_at']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="inquiry-actions">
                                    <?php if (empty($inquiry['admin_response'])): ?>
                                        <button class="btn btn-primary" onclick="respondToInquiry(<?php echo $inquiry['inquiry_id']; ?>, '<?php echo htmlspecialchars($inquiry['subject']); ?>', '<?php echo htmlspecialchars($inquiry['message']); ?>')">
                                            <i class="fas fa-reply"></i> Respond
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline" onclick="updateResponse(<?php echo $inquiry['inquiry_id']; ?>, '<?php echo htmlspecialchars($inquiry['subject']); ?>', '<?php echo htmlspecialchars($inquiry['admin_response']); ?>')">
                                            <i class="fas fa-edit"></i> Update Response
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline text-danger" onclick="deleteInquiry(<?php echo $inquiry['inquiry_id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            $queryParams = $_GET;
                            for ($i = 1; $i <= $totalPages; $i++):
                                $queryParams['page'] = $i;
                                $queryString = http_build_query($queryParams);
                            ?>
                                <a href="?<?php echo $queryString; ?>" class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-outline'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Response Modal -->
<div id="responseModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="responseModalTitle">Respond to Inquiry</h4>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="responseForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="respond">
                <input type="hidden" id="inquiryId" name="inquiry_id">
                
                <div class="form-group">
                    <label for="inquirySubject" class="form-label">Subject</label>
                    <input type="text" id="inquirySubject" class="form-control" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label for="originalMessage" class="form-label">Original Message</label>
                    <div id="originalMessage" class="form-control" style="background: #f8f9fa; height: auto; min-height: 100px; white-space: pre-wrap;"></div>
                </div>
                
                <div class="form-group">
                    <label for="response" class="form-label">Your Response *</label>
                    <textarea id="response" name="response" class="form-control" rows="6" 
                              placeholder="Type your response here..." required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Send Response</button>
                    <button type="button" class="btn btn-outline" onclick="closeResponseModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function respondToInquiry(inquiryId, subject, message) {
    document.getElementById('responseModalTitle').textContent = 'Respond to Inquiry';
    document.getElementById('inquiryId').value = inquiryId;
    document.getElementById('inquirySubject').value = subject;
    document.getElementById('originalMessage').textContent = message;
    document.getElementById('response').value = '';
    document.getElementById('responseModal').style.display = 'block';
}

function updateResponse(inquiryId, subject, currentResponse) {
    document.getElementById('responseModalTitle').textContent = 'Update Response';
    document.getElementById('inquiryId').value = inquiryId;
    document.getElementById('inquirySubject').value = subject;
    document.getElementById('response').value = currentResponse;
    document.getElementById('responseModal').style.display = 'block';
}

function deleteInquiry(inquiryId) {
    if (confirm('Are you sure you want to delete this inquiry? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="inquiry_id" value="${inquiryId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeResponseModal() {
    document.getElementById('responseModal').style.display = 'none';
}

function showBulkActions() {
    alert('Bulk actions feature coming soon!');
}

function exportInquiries() {
    alert('Export feature coming soon!');
}

// Close modal events
document.querySelector('.modal-close').onclick = closeResponseModal;

window.onclick = function(event) {
    const modal = document.getElementById('responseModal');
    if (event.target === modal) {
        closeResponseModal();
    }
}
</script>

<style>
/* Modal Styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    border-radius: var(--border-radius);
    width: 90%;
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: var(--shadow-lg);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.modal-body {
    padding: 1.5rem;
}

.modal-close {
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    opacity: 0.8;
    background: none;
    border: none;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    opacity: 1;
    background: rgba(255, 255, 255, 0.1);
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

/* Inquiry Cards */
.inquiries-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.inquiry-card {
    border: 1px solid #e5e7eb;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    background: white;
    transition: var(--transition);
    border-left: 4px solid var(--primary);
}

.inquiry-card:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
}

.inquiry-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.inquiry-info h5 {
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-weight: 600;
}

.inquiry-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    font-size: 0.875rem;
}

.inquiry-content {
    margin-bottom: 1.5rem;
}

.inquiry-content h6 {
    color: var(--dark);
    margin-bottom: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.inquiry-content p {
    margin-bottom: 0;
    line-height: 1.6;
}

.original-message {
    margin-bottom: 1.5rem;
}

.admin-response {
    border-top: 1px solid #e5e7eb;
    padding-top: 1rem;
}

.response-content {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    padding: 1rem;
    border-radius: var(--border-radius);
    border-left: 4px solid var(--success);
    margin-bottom: 0.5rem;
}

.inquiry-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.inquiry-actions .btn {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
}

.card-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.d-flex {
    display: flex;
}

.gap-2 {
    gap: 0.5rem;
}

.ms-3 {
    margin-left: 1rem;
}

/* Fix table and card text visibility issues */
.inquiry-card .text-muted {
    color: #6b7280 !important;
}

.inquiry-card .badge {
    color: #000 !important;
    background-color: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
}

.inquiry-card .badge-success {
    color: #fff !important;
    background-color: #10b981 !important;
    border: 1px solid #059669 !important;
}

.inquiry-card .badge-warning {
    color: #000 !important;
    background-color: #fef3c7 !important;
    border: 1px solid #fcd34d !important;
}

.inquiry-card small {
    color: #374151 !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .inquiry-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .inquiry-meta {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .inquiry-actions {
        flex-direction: column;
    }
    
    .inquiry-actions .btn {
        width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>