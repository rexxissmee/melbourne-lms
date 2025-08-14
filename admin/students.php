<?php
require_once '../config/database.php';
requireLogin();
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}
$database = new Database();
$db = $database->getConnection();

// delete
if (isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$uid]);
    }
    header('Location: students.php');
    exit();
}

$usersStmt = $db->prepare('SELECT id, username, first_name, last_name, email, created_at FROM users WHERE role = ? ORDER BY created_at DESC');
$usersStmt->execute(['student']);
$students = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Melbourne LMS</title>
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
                    <h1 class="h2"><i class="fas fa-user-graduate"></i> Students</h1>
                </div>
                <div class="card shadow">
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <p class="text-muted">No students found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Joined</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $u): ?>
                                            <tr>
                                                <td><?php echo $u['id']; ?></td>
                                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                                <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                <td><?php echo formatDate($u['created_at']); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-secondary" href="user_edit.php?id=<?php echo $u['id']; ?>"><i class="fas fa-edit"></i></a>
                                                    <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                                                        <form method="post" class="d-inline ms-1" onsubmit="return confirm('Delete this user?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <button name="delete_user" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                        </form>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>