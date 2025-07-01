<?php
/**
 * Admin Service Management
 * GreenLife Wellness Center
 */

$pageTitle = "Manage Services";
require_once 'includes/functions.php';

// Require admin access
requireRole('admin');

$error = '';
$success = '';

// Handle service actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        switch ($action) {
            case 'add_service':
                $result = addService($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                    logAdminAction($_SESSION['user_id'], "Added new service: " . $_POST['service_name']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'update_service':
                $result = updateService($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                    logAdminAction($_SESSION['user_id'], "Updated service ID: " . $_POST['service_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'delete_service':
                $result = deleteService($_POST['service_id'] ?? '');
                if ($result['success']) {
                    $success = $result['message'];
                    logAdminAction($_SESSION['user_id'], "Deleted service ID: " . $_POST['service_id']);
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Get all services with appointment counts
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT s.*, 
               COUNT(a.appointment_id) as appointment_count,
               MAX(a.appointment_date) as last_appointment
        FROM services s
        LEFT JOIN appointments a ON s.service_id = a.service_id
        GROUP BY s.service_id
        ORDER BY s.service_name ASC
    ");
    $services = $stmt->fetchAll();
    
    // Get service statistics
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM services GROUP BY status");
    $statusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM services WHERE DATE(created_at) = CURDATE()");
    $newToday = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT AVG(price) as avg_price FROM services WHERE status = 'active'");
    $avgPrice = $stmt->fetch()['avg_price'] ?? 0;
    
} catch (PDOException $e) {
    $error = 'Error fetching services: ' . $e->getMessage();
    $services = [];
    $statusStats = [];
    $newToday = 0;
    $avgPrice = 0;
}

/**
 * Add new service
 */
function addService($data) {
    $name = sanitizeInput($data['service_name'] ?? '');
    $description = sanitizeInput($data['service_description'] ?? '');
    $duration = (int)($data['duration'] ?? 60);
    $price = (float)($data['price'] ?? 0);
    
    if (empty($name) || empty($price)) {
        return ['success' => false, 'message' => 'Service name and price are required'];
    }
    
    if ($price <= 0) {
        return ['success' => false, 'message' => 'Price must be greater than 0'];
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO services (service_name, service_description, duration, price, created_at) VALUES (?, ?, ?, ?, NOW())");
        $result = $stmt->execute([$name, $description, $duration, $price]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Service added successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to add service'];
        }
    } catch (PDOException $e) {
        error_log("Error adding service: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Update existing service
 */
function updateService($data) {
    $serviceId = (int)($data['service_id'] ?? 0);
    $name = sanitizeInput($data['service_name'] ?? '');
    $description = sanitizeInput($data['service_description'] ?? '');
    $duration = (int)($data['duration'] ?? 60);
    $price = (float)($data['price'] ?? 0);
    $status = sanitizeInput($data['status'] ?? 'active');
    
    if ($serviceId <= 0 || empty($name) || empty($price)) {
        return ['success' => false, 'message' => 'Service ID, name and price are required'];
    }
    
    if ($price <= 0) {
        return ['success' => false, 'message' => 'Price must be greater than 0'];
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE services SET service_name = ?, service_description = ?, duration = ?, price = ?, status = ? WHERE service_id = ?");
        $result = $stmt->execute([$name, $description, $duration, $price, $status, $serviceId]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Service updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to update service'];
        }
    } catch (PDOException $e) {
        error_log("Error updating service: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Delete service (soft delete by setting status to inactive)
 */
function deleteService($serviceId) {
    $serviceId = (int)$serviceId;
    
    if ($serviceId <= 0) {
        return ['success' => false, 'message' => 'Invalid service ID'];
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if service has appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        $appointmentCount = $stmt->fetch()['count'];
        
        if ($appointmentCount > 0) {
            // Soft delete - set status to inactive
            $stmt = $pdo->prepare("UPDATE services SET status = 'inactive' WHERE service_id = ?");
            $result = $stmt->execute([$serviceId]);
            $message = 'Service deactivated (has existing appointments)';
        } else {
            // Hard delete - completely remove
            $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
            $result = $stmt->execute([$serviceId]);
            $message = 'Service deleted successfully';
        }
        
        if ($result) {
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => 'Failed to delete service'];
        }
    } catch (PDOException $e) {
        error_log("Error deleting service: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred';
    }
}

require_once 'includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-spa"></i> Manage Services</h1>
            <p>Add, edit, and manage wellness services offered by the center</p>
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
        
        <!-- Service Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-spa"></i></div>
                <div class="number"><?php echo $statusStats['active'] ?? 0; ?></div>
                <div class="label">Active Services</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-pause"></i></div>
                <div class="number"><?php echo $statusStats['inactive'] ?? 0; ?></div>
                <div class="label">Inactive Services</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-plus"></i></div>
                <div class="number"><?php echo $newToday; ?></div>
                <div class="label">Added Today</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="number">Rs. <?php echo number_format($avgPrice, 0); ?></div>
                <div class="label">Average Price</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <div class="number"><?php echo count($services); ?></div>
                <div class="label">Total Services</div>
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
                        <button class="btn btn-primary btn-lg" onclick="showAddServiceModal()" style="width: 100%;">
                            <i class="fas fa-plus"></i><br>Add New Service
                        </button>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-outline btn-lg" onclick="filterServices('active')" style="width: 100%;">
                            <i class="fas fa-spa"></i><br>Active Services
                        </button>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-outline btn-lg" onclick="filterServices('inactive')" style="width: 100%;">
                            <i class="fas fa-pause"></i><br>Inactive Services
                        </button>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-outline btn-lg" onclick="showPriceAnalysis()" style="width: 100%;">
                            <i class="fas fa-chart-bar"></i><br>Price Analysis
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Services Management -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> All Services</h3>
                <div class="card-actions">
                    <span class="badge badge-primary"><?php echo count($services); ?> Total</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($services)): ?>
                    <div class="text-center p-5">
                        <i class="fas fa-spa" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <h4>No services found</h4>
                        <p class="text-muted">Start by adding your first wellness service.</p>
                        <button class="btn btn-primary" onclick="showAddServiceModal()">
                            <i class="fas fa-plus me-2"></i>Add First Service
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="servicesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Service Details</th>
                                    <th>Duration</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                    <tr data-status="<?php echo $service['status']; ?>">
                                        <td><strong>#<?php echo $service['service_id']; ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                                <?php if ($service['service_description']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($service['service_description'], 0, 80)); ?><?php echo strlen($service['service_description']) > 80 ? '...' : ''; ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-outline"><?php echo $service['duration']; ?> min</span>
                                        </td>
                                        <td>
                                            <strong>Rs. <?php echo number_format($service['price'], 0); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($service['status'] === 'active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge badge-info"><?php echo $service['appointment_count']; ?> bookings</span>
                                                <?php if ($service['last_appointment']): ?>
                                                    <br><small class="text-muted">Last: <?php echo formatDate($service['last_appointment']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline" onclick="editService(<?php echo htmlspecialchars(json_encode($service)); ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline text-danger" onclick="deleteService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['service_name']); ?>')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Service Modal -->
<div id="serviceModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="serviceModalTitle">Add New Service</h4>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="serviceForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" id="serviceAction" name="action" value="add_service">
                <input type="hidden" id="serviceId" name="service_id">
                
                <div class="form-group">
                    <label for="serviceName" class="form-label">Service Name *</label>
                    <input type="text" id="serviceName" name="service_name" class="form-control" required placeholder="e.g., Deep Tissue Massage">
                </div>
                
                <div class="form-group">
                    <label for="serviceDescription" class="form-label">Description</label>
                    <textarea id="serviceDescription" name="service_description" class="form-control" rows="3" placeholder="Brief description of the service..."></textarea>
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="serviceDuration" class="form-label">Duration (minutes)</label>
                            <input type="number" id="serviceDuration" name="duration" class="form-control" value="60" min="15" max="300">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="servicePrice" class="form-label">Price (Rs.) *</label>
                            <input type="number" id="servicePrice" name="price" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label for="serviceStatus" class="form-label">Status</label>
                    <select id="serviceStatus" name="status" class="form-control form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" id="saveServiceBtn">Add Service</button>
                    <button type="button" class="btn btn-outline" onclick="closeServiceModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAddServiceModal() {
    document.getElementById('serviceModalTitle').textContent = 'Add New Service';
    document.getElementById('serviceForm').reset();
    document.getElementById('serviceAction').value = 'add_service';
    document.getElementById('saveServiceBtn').textContent = 'Add Service';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('serviceModal').style.display = 'block';
}

function editService(service) {
    document.getElementById('serviceModalTitle').textContent = 'Edit Service';
    document.getElementById('serviceId').value = service.service_id;
    document.getElementById('serviceName').value = service.service_name;
    document.getElementById('serviceDescription').value = service.service_description || '';
    document.getElementById('serviceDuration').value = service.duration;
    document.getElementById('servicePrice').value = service.price;
    document.getElementById('serviceStatus').value = service.status || 'active';
    document.getElementById('serviceAction').value = 'update_service';
    document.getElementById('saveServiceBtn').textContent = 'Update Service';
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('serviceModal').style.display = 'block';
}

function closeServiceModal() {
    document.getElementById('serviceModal').style.display = 'none';
}

function deleteService(serviceId, serviceName) {
    if (confirm(`Are you sure you want to delete "${serviceName}"?\n\nThis action cannot be undone. If the service has existing appointments, it will be deactivated instead.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete_service">
            <input type="hidden" name="service_id" value="${serviceId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function filterServices(status) {
    const rows = document.querySelectorAll('#servicesTable tbody tr');
    rows.forEach(row => {
        if (status === 'all' || row.getAttribute('data-status') === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function showPriceAnalysis() {
    alert('Price analysis feature coming soon!');
}

// Close modal events
document.querySelector('.modal-close').onclick = closeServiceModal;

window.onclick = function(event) {
    const modal = document.getElementById('serviceModal');
    if (event.target === modal) {
        closeServiceModal();
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
    max-width: 600px;
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

.btn-group {
    display: flex;
    gap: 0.25rem;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.card-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

/* Fix table text visibility issues */
.table td {
    color: #000 !important;
}

.table td .text-muted {
    color: #6b7280 !important;
}

.table td .badge {
    color: #000 !important;
    background-color: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
}

.table td .badge-success {
    color: #fff !important;
    background-color: #10b981 !important;
    border: 1px solid #059669 !important;
}

.table td .badge-warning {
    color: #000 !important;
    background-color: #fef3c7 !important;
    border: 1px solid #fcd34d !important;
}

.table td .badge-info {
    color: #fff !important;
    background-color: #3b82f6 !important;
    border: 1px solid #2563eb !important;
}

.table td .badge-outline {
    color: #000 !important;
    background-color: #f9fafb !important;
    border: 1px solid #d1d5db !important;
}

.table td small {
    color: #374151 !important;
}

@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .btn-group {
        flex-direction: column;
        width: 100%;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>