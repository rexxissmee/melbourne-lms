<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($userId <= 0) {
    header('Location: users.php');
    exit();
}

$stmt = $db->prepare('SELECT id, username, first_name, last_name, email, role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: users.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $username = sanitizeInput($_POST['username']);
    $first = sanitizeInput($_POST['first_name']);
    $last = sanitizeInput($_POST['last_name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
    $role = in_array($_POST['role'], ['student','instructor','admin'], true) ? $_POST['role'] : $user['role'];
    $password = $_POST['password'] ?? '';

    if ($username && $first && $last && $email) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $db->prepare('UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, role = ?, password = ? WHERE id = ?');
            $upd->execute([$username, $first, $last, $email, $role, $hash, $userId]);
        } else {
            $upd = $db->prepare('UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?');
            $upd->execute([$username, $first, $last, $email, $role, $userId]);
        }
        header('Location: users.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Melbourne LMS</title>
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
                <h1 class="h2"><i class="fas fa-user-edit"></i> Edit User</h1>
            </div>
            <div class="card shadow">
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="update_user" value="1">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <?php foreach (['student'=>'Student','instructor'=>'Instructor','admin'=>'Admin'] as $key=>$label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $user['role']===$key?'selected':''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password (optional)</label>
                            <input type="password" class="form-control" name="password" minlength="6" placeholder="Leave blank to keep current password">
                        </div>
                        <button class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                        <a href="users.php" class="btn btn-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>