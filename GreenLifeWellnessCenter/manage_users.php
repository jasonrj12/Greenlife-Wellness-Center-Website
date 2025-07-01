<?php
/**
 * Admin User Management
 * GreenLife Wellness Center
 */

$pageTitle = "Manage Users";
require_once __DIR__ . '/includes/functions.php';

// Require admin access
requireRole('admin');

$error = '';
$success = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token';
    } else {
        switch ($action) {
            case 'add':
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = $_POST['role'] ?? 'client';
                $phone = sanitizeInput($_POST['phone'] ?? '');
                $address = sanitizeInput($_POST['address'] ?? '');
                $password = $_POST['password'] ?? '';
                $specialization = sanitizeInput($_POST['specialization'] ?? 'General Practice');
                $bio = sanitizeInput($_POST['bio'] ?? '');
                $experienceYears = intval($_POST['experience_years'] ?? 0);
                
                // Generate random password if not provided
                if (empty($password)) {
                    $password = generateRandomPassword(12);
                }
                
                // Validation
                if (empty($name) || strlen($name) < 2) {
                    $error = 'Please enter a valid name (at least 2 characters)';
                } elseif (!validateEmail($email)) {
                    $error = 'Please enter a valid email address';
                } elseif (!in_array($role, ['client', 'therapist', 'admin'])) {
                    $error = 'Invalid role selected';
                } else {
                    $result = registerUser($name, $email, $password, $phone, $address, $role, $specialization, $bio, $experienceYears);
                    if ($result['success']) {
                        $success = "User created successfully. Password: $password (Please share this with the user)";
                        logAdminAction($_SESSION['user_id'], "Created new user: $name ($email) with role: $role");
                    } else {
                        $error = $result['message'];
                    }
                }
                break;
                
            case 'update':
                $name = sanitizeInput($_POST['name'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                $phone = sanitizeInput($_POST['phone'] ?? '');
                $address = sanitizeInput($_POST['address'] ?? '');
                $specialization = sanitizeInput($_POST['specialization'] ?? null);
                $bio = sanitizeInput($_POST['bio'] ?? null);
                $experienceYears = intval($_POST['experience_years'] ?? 0);
                
                if ($userId && $name && $email && in_array($role, ['client', 'therapist', 'admin'])) {
                    try {
                        $pdo = getDBConnection();
                        
                        // Check if email exists for other users
                        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                        $stmt->execute([$email, $userId]);
                        if ($stmt->fetch()) {
                            $error = 'Email already exists for another user';
                            break;
                        }
                        
                        // Use the new update function that syncs therapist data
                        $result = updateUserAndSyncTherapist($userId, $name, $email, $role, $phone, $address, $specialization, $bio, $experienceYears);
                        
                        if ($result['success']) {
                            $success = $result['message'];
                            logAdminAction($_SESSION['user_id'], "Updated user ID $userId");
                        } else {
                            $error = $result['message'];
                        }
                    } catch (PDOException $e) {
                        $error = 'Error updating user: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Please fill all required fields';
                }
                break;
                
            case 'delete':
                if ($userId) {
                    try {
                        $pdo = getDBConnection();
                        
                        // Prevent deleting current admin user
                        if ($userId == $_SESSION['user_id']) {
                            $error = 'Cannot delete your own account';
                            break;
                        }
                        
                        // Begin transaction for safe deletion
                        $pdo->beginTransaction();
                        
                        try {
                            // Get user info for logging
                            $stmt = $pdo->prepare("SELECT name, email, role FROM users WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            $userInfo = $stmt->fetch();
                            
                            if (!$userInfo) {
                                throw new Exception('User not found');
                            }
                            
                            // Check for related appointments and handle them
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? OR therapist_id = (SELECT therapist_id FROM therapists WHERE user_id = ?)");
                            $stmt->execute([$userId, $userId]);
                            $appointmentCount = $stmt->fetch()['count'];
                            
                            if ($appointmentCount > 0) {
                                // Cancel all related appointments instead of deleting them (for audit trail)
                                $stmt = $pdo->prepare("UPDATE appointments SET status = 'canceled', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Admin] User deleted - appointment canceled') WHERE user_id = ?");
                                $stmt->execute([$userId]);
                                
                                // Cancel appointments where this user was the therapist
                                $stmt = $pdo->prepare("UPDATE appointments SET status = 'canceled', admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n[Admin] Therapist account deleted - appointment canceled') WHERE therapist_id = (SELECT therapist_id FROM therapists WHERE user_id = ?)");
                                $stmt->execute([$userId]);
                            }
                            
                            // Delete therapist record if exists
                            if ($userInfo['role'] === 'therapist') {
                                $stmt = $pdo->prepare("DELETE FROM therapists WHERE user_id = ?");
                                $stmt->execute([$userId]);
                            }
                            
                            // Delete related notifications
                            $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            
                            // Delete related inquiries (optional - you might want to keep these for records)
                            $stmt = $pdo->prepare("DELETE FROM inquiries WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            
                            // Finally delete the user (make sure it's not an admin or current user)
                            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND (role != 'admin' OR user_id != ?)");
                            $result = $stmt->execute([$userId, $_SESSION['user_id']]);
                            
                            if ($result && $stmt->rowCount() > 0) {
                                $pdo->commit();
                                $success = 'User and all related data deleted successfully';
                                logAdminAction($_SESSION['user_id'], "Deleted user: {$userInfo['name']} ({$userInfo['email']}) with role: {$userInfo['role']}");
                            } else {
                                throw new Exception('Failed to delete user or user is protected');
                            }
                            
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            throw $e;
                        }
                        
                    } catch (PDOException $e) {
                        $error = 'Error deleting user: ' . $e->getMessage();
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                }
                break;
                
            case 'toggle_status':
                if ($userId) {
                    $newStatus = $_POST['status'] ?? '';
                    if (in_array($newStatus, ['active', 'inactive'])) {
                        try {
                            $pdo = getDBConnection();
                            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE user_id = ?");
                            $result = $stmt->execute([$newStatus, $userId]);
                            
                            if ($result) {
                                $success = "User status changed to $newStatus";
                                logAdminAction($_SESSION['user_id'], "Changed user ID $userId status to $newStatus");
                            } else {
                                $error = 'Failed to update user status';
                            }
                        } catch (PDOException $e) {
                            $error = 'Error updating user status: ' . $e->getMessage();
                        }
                    }
                }
                break;
                
            case 'migrate_therapists':
                $result = migrateExistingTherapists();
                if ($result['success']) {
                    $success = $result['message'];
                    logAdminAction($_SESSION['user_id'], "Migrated {$result['count']} therapist users");
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'cleanup_orphaned':
                $result = cleanupOrphanedTherapists();
                if ($result['success']) {
                    $success = $result['message'];
                    logAdminAction($_SESSION['user_id'], "Cleaned up {$result['count']} orphaned therapist records");
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Get all users with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

try {
    $pdo = getDBConnection();
    
    // Build search conditions
    $conditions = [];
    $params = [];
    
    if ($search) {
        $conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($roleFilter) {
        $conditions[] = "u.role = ?";
        $params[] = $roleFilter;
    }
    
    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u $whereClause");
    $stmt->execute($params);
    $totalUsers = $stmt->fetch()['total'];
    $totalPages = ceil($totalUsers / $limit);
    
    // Get users for current page
    $stmt = $pdo->prepare("
        SELECT u.*, 
               t.specialization, t.bio, t.experience_years,
               COUNT(a.appointment_id) as appointment_count,
               MAX(a.appointment_date) as last_appointment
        FROM users u
        LEFT JOIN therapists t ON u.user_id = t.user_id
        LEFT JOIN appointments a ON u.user_id = a.user_id
        $whereClause
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error fetching users: ' . $e->getMessage();
    $users = [];
    $totalPages = 1;
}

require_once './includes/header.php';
?>

<div class="container">
    <div class="dashboard">
        <div class="dashboard-header">
            <h1><i class="fas fa-users"></i> Manage Users</h1>
            <p>View and manage all registered users in the system</p>
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
        
        <!-- User Statistics -->
        <div class="stats-grid">
            <?php
            try {
                $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
                $roleStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
                $statusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
                $newToday = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $roleStats = [];
                $statusStats = [];
                $newToday = 0;
            }
            ?>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="number"><?php echo $roleStats['client'] ?? 0; ?></div>
                <div class="label">Clients</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-user-md"></i></div>
                <div class="number"><?php echo $roleStats['therapist'] ?? 0; ?></div>
                <div class="label">Therapists</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-user-shield"></i></div>
                <div class="number"><?php echo $roleStats['admin'] ?? 0; ?></div>
                <div class="label">Administrators</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-user-plus"></i></div>
                <div class="number"><?php echo $newToday; ?></div>
                <div class="label">New Today</div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fas fa-chart-line"></i></div>
                <div class="number"><?php echo $totalUsers; ?></div>
                <div class="label">Total Users</div>
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
                        <button class="btn btn-primary btn-lg" onclick="addUser()" style="width: 100%;">
                            <i class="fas fa-user-plus"></i><br>Add New User
                        </button>
                    </div>
                    <div class="col-3">
                        <a href="manage_users.php?role=client" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-users"></i><br>View Clients
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="manage_users.php?role=therapist" class="btn btn-outline btn-lg" style="width: 100%;">
                            <i class="fas fa-user-md"></i><br>View Therapists
                        </a>
                    </div>
                    <div class="col-3">
                        <button class="btn btn-outline btn-lg" onclick="migrateTherapists()" style="width: 100%;">
                            <i class="fas fa-sync-alt"></i><br>Sync Data
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-search"></i> Search & Filter Users</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-control form-select">
                                <option value="">All Roles</option>
                                <option value="client" <?php echo $roleFilter === 'client' ? 'selected' : ''; ?>>Client</option>
                                <option value="therapist" <?php echo $roleFilter === 'therapist' ? 'selected' : ''; ?>>Therapist</option>
                                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <a href="manage_users.php" class="btn btn-outline">Clear</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" class="btn btn-outline" onclick="cleanupOrphanedTherapists()" title="Cleanup Orphaned Records">
                                    <i class="fas fa-broom"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- User Management Table -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-table"></i> All Users</h3>
                <div class="card-actions">
                    <span class="badge badge-primary"><?php echo $totalUsers; ?> Total</span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center p-5">
                        <i class="fas fa-users" style="font-size: 4rem; color: var(--gray-400); margin-bottom: 1rem;"></i>
                        <h4>No users found</h4>
                        <p class="text-muted">Try adjusting your search criteria or add a new user.</p>
                        <button class="btn btn-primary" onclick="addUser()">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User Details</th>
                                    <th>Role</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Activity</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><strong>#<?php echo $user['user_id']; ?></strong></td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                <?php if ($user['role'] === 'therapist' && $user['specialization']): ?>
                                                    <br><small class="text-info"><i class="fas fa-stethoscope fa-xs"></i> <?php echo htmlspecialchars($user['specialization']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $roleClass = match ($user['role']) {
                                                'admin' => 'badge-error',
                                                'therapist' => 'badge-info',
                                                'client' => 'badge-success',
                                                default => 'badge-outline',
                                            };
                                            ?>
                                            <span class="badge <?php echo $roleClass; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['phone_number']): ?>
                                                <small><i class="fas fa-phone fa-xs text-muted"></i> <?php echo htmlspecialchars($user['phone_number']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">No phone</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = ($user['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-warning';
                                            $statusText = ucfirst($user['status'] ?? 'active');
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge badge-outline"><?php echo $user['appointment_count']; ?> appointments</span>
                                                <?php if ($user['last_appointment']): ?>
                                                    <br><small class="text-muted">Last: <?php echo formatDate($user['last_appointment']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <small><?php echo formatDate($user['created_at']); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-outline" onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, '<?php echo ($user['status'] ?? 'active') === 'active' ? 'inactive' : 'active'; ?>')" title="Toggle Status">
                                                        <i class="fas fa-<?php echo ($user['status'] ?? 'active') === 'active' ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline text-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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

<!-- User Management Modal -->
<div id="userModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Add User</h4>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="userForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" id="userId" name="user_id">
                <input type="hidden" id="userAction" name="action" value="add">
                
                <div class="form-group">
                    <label for="userName" class="form-label">Full Name *</label>
                    <input type="text" id="userName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="userEmail" class="form-label">Email Address *</label>
                    <input type="email" id="userEmail" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="userRole" class="form-label">User Role *</label>
                    <select id="userRole" name="role" class="form-control form-select" required>
                        <option value="client">Client</option>
                        <option value="therapist">Therapist</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>

                <div id="therapistFields" style="display: none;">
                    <div class="form-group">
                        <label for="therapistSpecialization" class="form-label">Specialization</label>
                        <input type="text" id="therapistSpecialization" name="specialization" class="form-control" placeholder="e.g., Massage Therapy, Yoga, Acupuncture">
                    </div>
                    <div class="form-group">
                        <label for="therapistExperience" class="form-label">Years of Experience</label>
                        <input type="number" id="therapistExperience" name="experience_years" class="form-control" min="0" max="50" placeholder="0">
                    </div>
                    <div class="form-group">
                        <label for="therapistBio" class="form-label">Bio/Professional Notes</label>
                        <textarea id="therapistBio" name="bio" class="form-control" rows="3" placeholder="Brief professional background and expertise..."></textarea>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="userPhone" class="form-label">Phone Number</label>
                            <input type="tel" id="userPhone" name="phone" class="form-control" placeholder="+94 xxx xxx xxx">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group" id="passwordGroup">
                            <label for="userPassword" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" id="userPassword" name="password" class="form-control" placeholder="Leave blank to auto-generate">
                                <button class="btn btn-outline" type="button" id="toggleUserPassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted">Auto-generates secure password if left blank</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="userAddress" class="form-label">Address</label>
                    <textarea id="userAddress" name="address" class="form-control" rows="2" placeholder="Full address (optional)"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary" id="saveUserBtn">Save User</button>
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addUser() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('userAction').value = 'add';
    document.getElementById('saveUserBtn').textContent = 'Add User';
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('therapistFields').style.display = 'none';
    document.getElementById('userModal').style.display = 'block';
}

function editUser(userData) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userId').value = userData.user_id;
    document.getElementById('userName').value = userData.name;
    document.getElementById('userEmail').value = userData.email;
    document.getElementById('userRole').value = userData.role;
    document.getElementById('userPhone').value = userData.phone_number || '';
    document.getElementById('userAddress').value = userData.address || '';
    document.getElementById('userAction').value = 'update';
    document.getElementById('saveUserBtn').textContent = 'Update User';
    document.getElementById('passwordGroup').style.display = 'none';

    const therapistFields = document.getElementById('therapistFields');
    if (userData.role === 'therapist') {
        therapistFields.style.display = 'block';
        document.getElementById('therapistSpecialization').value = userData.specialization || '';
        document.getElementById('therapistBio').value = userData.bio || '';
        document.getElementById('therapistExperience').value = userData.experience_years || 0;
    } else {
        therapistFields.style.display = 'none';
    }

    document.getElementById('userModal').style.display = 'block';
}

function toggleUserStatus(userId, newStatus) {
    if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this user?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="status" value="${newStatus}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteUser(userId, userName) {
    if (confirm('Are you sure you want to delete user: ' + userName + '? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

// Close modal events
document.querySelector('.modal-close').onclick = closeModal;

window.onclick = function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Form validation
document.getElementById('userForm').onsubmit = function(e) {
    const name = document.getElementById('userName').value.trim();
    const email = document.getElementById('userEmail').value.trim();
    
    if (name.length < 2) {
        alert('Name must be at least 2 characters long');
        e.preventDefault();
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address');
        e.preventDefault();
        return false;
    }
    
    return true;
}

// Password validation feedback
document.getElementById('userPassword').oninput = function() {
    const password = this.value;
    const requirements = {
        length: password.length >= 8,
        lower: /[a-z]/.test(password),
        upper: /[A-Z]/.test(password),
        number: /[0-9]/.test(password),
        chars: /^[a-zA-Z0-9@$!%*?&._-]*$/.test(password)
    };
    
    // Update UI for requirements
    document.getElementById('user-req-length').innerHTML = requirements.length ? 
        '<i class="fas fa-check text-success"></i> At least 8 characters' : 
        '<i class="fas fa-times text-danger"></i> At least 8 characters';
    
    document.getElementById('user-req-lower').innerHTML = requirements.lower ? 
        '<i class="fas fa-check text-success"></i> One lowercase English letter (a-z)' : 
        '<i class="fas fa-times text-danger"></i> One lowercase English letter (a-z)';
    
    document.getElementById('user-req-upper').innerHTML = requirements.upper ? 
        '<i class="fas fa-check text-success"></i> One uppercase English letter (A-Z)' : 
        '<i class="fas fa-times text-danger"></i> One uppercase English letter (A-Z)';
    
    document.getElementById('user-req-number').innerHTML = requirements.number ? 
        '<i class="fas fa-check text-success"></i> One number (0-9)' : 
        '<i class="fas fa-times text-danger"></i> One number (0-9)';
    
    document.getElementById('user-req-chars').innerHTML = requirements.chars ? 
        '<i class="fas fa-check text-success"></i> Only English letters, numbers, and @$!%*?&._-' : 
        '<i class="fas fa-times text-danger"></i> Only English letters, numbers, and @$!%*?&._-';
    
    // Toggle visibility of requirements
    document.getElementById('userPasswordRequirements').style.display = password ? 'block' : 'none';
}

// Toggle password visibility
document.getElementById('toggleUserPassword').onclick = function() {
    const passwordInput = document.getElementById('userPassword');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.querySelector('i').classList.toggle('fa-eye-slash');
}

document.getElementById('userRole').addEventListener('change', function() {
    const therapistFields = document.getElementById('therapistFields');
    if (this.value === 'therapist') {
        therapistFields.style.display = 'block';
    } else {
        therapistFields.style.display = 'none';
    }
});

function migrateTherapists() {
    if (confirm('This will migrate all existing therapist users to the new system. Do you want to continue?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="migrate_therapists">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function cleanupOrphanedTherapists() {
    if (confirm('This will remove all orphaned therapist records that are not linked to any user. Do you want to continue?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="cleanup_orphaned">
        `;
        document.body.appendChild(form);
        form.submit();
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

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.card-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.input-group {
    display: flex;
}

.input-group .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.input-group .btn {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-left: 0;
}

.d-flex {
    display: flex;
}

.gap-2 {
    gap: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    text-align: center;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.stat-card .icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: var(--primary);
}

.stat-card .number {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

.stat-card .label {
    font-size: 0.875rem;
    color: var(--gray-500);
}

/* Fix Activity column text visibility */
.table td:nth-child(6) {
    color: #000 !important;
}

.table td:nth-child(6) .badge {
    color: #000 !important;
    background-color: #f3f4f6 !important;
    border: 1px solid #d1d5db !important;
}

.table td:nth-child(6) small {
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

<?php require_once './includes/footer.php'; ?>