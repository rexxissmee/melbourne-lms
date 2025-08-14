<?php
require_once '../config/database.php';
requireLogin();
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}
$database = new Database();
$db = $database->getConnection();

// Delete course
if (isset($_POST['delete_course'])) {
    $cid = (int)$_POST['course_id'];
    $stmt = $db->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->execute([$cid]);
    header('Location: courses.php');
    exit();
}

// Course status:
// - active: course is open to students (visible, enrollable, accessible)
// - inactive: course is temporarily hidden from students (no enroll/access), staff can manage
// - archived: course is finished/locked for reference (no edits or new enrollments)
// Persist as plain text in column `status` with values: 'active' | 'inactive' | 'archived'
if (isset($_POST['update_status'])) {
    $cid = (int)$_POST['course_id'];
    $status = in_array($_POST['status'], ['active', 'inactive', 'archived'], true) ? $_POST['status'] : 'active';
    $stmt = $db->prepare('UPDATE courses SET status = ? WHERE id = ?');
    $stmt->execute([$status, $cid]);
    header('Location: courses.php');
    exit();
}

$coursesStmt = $db->query('SELECT c.*, u.first_name, u.last_name,
                                  (SELECT COUNT(e.id) FROM enrollments e WHERE e.course_id = c.id) AS student_count
                           FROM courses c
                           LEFT JOIN users u ON c.instructor_id = u.id
                           ORDER BY c.created_at DESC');
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courses - Melbourne LMS</title>
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
                    <h1 class="h2"><i class="fas fa-book"></i> Courses</h1>
                </div>
                <div class="card shadow">
                    <div class="card-body">
                        <?php if (empty($courses)): ?><p class="text-muted">No courses found.</p><?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Title</th>
                                            <th>Code</th>
                                            <th>Instructor</th>
                                            <th>Students</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $c): ?>
                                            <tr>
                                                <td><?php echo $c['id']; ?></td>
                                                <td><?php echo htmlspecialchars($c['title']); ?></td>
                                                <td><?php echo htmlspecialchars($c['course_code']); ?></td>
                                                <td><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></td>
                                                <td><?php echo $c['student_count']; ?></td>
                                                <td>
                                                    <?php // Render status selector; semantics documented above 
                                                    ?>
                                                    <form method="post">
                                                        <input type="hidden" name="update_status" value="1">
                                                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                                        <!-- Options: active (open), inactive (hidden/no access), archived (locked/reference) -->
                                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $k => $label): ?>
                                                                <option value="<?php echo $k; ?>" <?php echo $c['status'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-secondary" href="course_view.php?id=<?php echo $c['id']; ?>"><i class="fas fa-eye"></i></a>
                                                    <form method="post" class="d-inline ms-1" onsubmit="return confirm('Delete this course?');"><input type="hidden" name="course_id" value="<?php echo $c['id']; ?>"><button name="delete_course" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
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