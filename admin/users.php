<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$createError = '';

// Handle create user
if (isset($_POST['create_user'])) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $first = sanitizeInput($_POST['first_name'] ?? '');
    $last = sanitizeInput($_POST['last_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
    $role = in_array($_POST['role'] ?? '', ['student', 'instructor', 'admin'], true) ? $_POST['role'] : 'student';
    $password = $_POST['password'] ?? '';
    if ($username && $first && $last && $email && $password) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $db->prepare('INSERT INTO users (username, email, password, first_name, last_name, role) VALUES (?,?,?,?,?,?)');
            $ins->execute([$username, $email, $hash, $first, $last, $role]);
            header('Location: users.php');
            exit();
        } catch (PDOException $e) {
            // Likely duplicate username/email or other DB error
            $createError = 'Failed to create user: ' . ($e->errorInfo[1] === 1062 ? 'Username or Email already exists.' : 'Database error');
        }
    } else {
        $createError = 'Please fill in all required fields correctly.';
    }
}

// Handle delete user
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    // Prevent self-delete
    if ($uid !== (int)$_SESSION['user_id']) {
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$uid]);
    }
    header('Location: users.php');
    exit();
}

$usersStmt = $db->query('SELECT id, username, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC');
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Melbourne LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/admin_navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/admin_sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-users"></i> Users</h1>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal"><i class="fas fa-user-plus"></i> Add User</button>
            </div>
            <div class="card shadow">
                <div class="card-body">
                    <?php if ($createError): ?>
                        <div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle"></i> <?php echo $createError; ?></div>
                    <?php endif; ?>
                    <?php if (empty($users)): ?>
                        <p class="text-muted">No users found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td><?php echo $u['id']; ?></td>
                                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                                            <td><?php echo htmlspecialchars($u['first_name'].' '.$u['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                                            <td><span class="badge bg-secondary text-capitalize"><?php echo htmlspecialchars($u['role']); ?></span></td>
                                            <td><?php echo formatDate($u['created_at']); ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-secondary" href="user_edit.php?id=<?php echo $u['id']; ?>"><i class="fas fa-edit"></i></a>
                                                <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                                    <form method="post" class="d-inline ms-1" onsubmit="return confirm('Delete this user?');">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button name="delete_user" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small ms-2">self</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="create_user" value="1">
          <div class="mb-2">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required>
          </div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">First Name</label>
              <input type="text" class="form-control" name="first_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Name</label>
              <input type="text" class="form-control" name="last_name" required>
            </div>
          </div>
          <div class="mb-3 mt-2">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" minlength="6" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" required>
              <option value="student">Student</option>
              <option value="instructor">Instructor</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>