<?php
require_once '../config/database.php';
requireLogin();
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}
$database = new Database();
$db = $database->getConnection();

// courses list for dropdown
$coursesStmt = $db->query('SELECT id, title FROM courses ORDER BY title');
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Handle add enrollment
if (isset($_POST['add_enrollment'])) {
    $courseId = (int)$_POST['course_id'];
    $studentId = (int)$_POST['student_id'];
    if ($courseId && $studentId) {
        $stmt = $db->prepare('INSERT IGNORE INTO enrollments (course_id, student_id, status, enrollment_date) VALUES (?,?,"enrolled", NOW())');
        $stmt->execute([$courseId, $studentId]);
    }
    header('Location: enrollments.php?course_id=' . $courseId);
    exit();
}

// Handle remove
if (isset($_POST['remove_enrollment'])) {
    $enrollId = (int)$_POST['enroll_id'];
    $courseId = (int)$_POST['course_id'];
    $stmt = $db->prepare('DELETE FROM enrollments WHERE id = ?');
    $stmt->execute([$enrollId]);
    header('Location: enrollments.php?course_id=' . $courseId);
    exit();
}

$students = [];
if ($selectedCourseId) {
    $enStmt = $db->prepare('SELECT e.id, u.id as student_id, u.first_name, u.last_name, u.email, e.enrollment_date AS enrolled_at
                          FROM enrollments e JOIN users u ON e.student_id = u.id WHERE e.course_id = ?');
    $enStmt->execute([$selectedCourseId]);
    $students = $enStmt->fetchAll(PDO::FETCH_ASSOC);

    // Students not enrolled yet
    $availStmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE role = "student" AND id NOT IN (SELECT student_id FROM enrollments WHERE course_id = ?) ORDER BY first_name');
    $availStmt->execute([$selectedCourseId]);
    $availableStudents = $availStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $students = [];
    $availableStudents = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollments - Melbourne LMS</title>
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
                    <h1 class="h2"><i class="fas fa-user-plus"></i> Enrollments</h1>
                </div>
                <form method="get" class="row g-2 mb-3">
                    <div class="col-md-6"><select name="course_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">Select course</option><?php foreach ($courses as $c): ?><option value="<?php echo $c['id']; ?>" <?php echo $selectedCourseId == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['title']); ?></option><?php endforeach; ?>
                        </select></div>
                </form>
                <?php if ($selectedCourseId): ?>
                    <div class="card shadow">
                        <div class="card-body">
                            <h5>Enrolled Students</h5>
                            <?php if (empty($students)): ?><p class="text-muted">No students enrolled.</p><?php else: ?><div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Enrolled</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php foreach ($students as $s): ?><tr>
                                                    <td><?php echo $s['student_id']; ?></td>
                                                    <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                                                    <td><?php echo formatDate($s['enrolled_at']); ?></td>
                                                    <td class="text-end">
                                                        <form method="post" onsubmit="return confirm('Remove this enrollment?');" class="d-inline"><input type="hidden" name="remove_enrollment" value="1"><input type="hidden" name="enroll_id" value="<?php echo $s['id']; ?>"><input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                                                    </td>
                                                </tr><?php endforeach; ?></tbody>
                                    </table>
                                </div><?php endif; ?>
                            <hr>
                            <h5>Add Student</h5>
                            <?php if (empty($availableStudents)): ?><p class="text-muted">All students already enrolled.</p><?php else: ?><form method="post" class="row g-2 align-items-end"><input type="hidden" name="add_enrollment" value="1"><input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">
                                    <div class="col-md-6"><select name="student_id" class="form-select" required><?php foreach ($availableStudents as $st): ?><option value="<?php echo $st['id']; ?>"><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-auto"><button class="btn btn-primary"><i class="fas fa-plus"></i> Add</button></div>
                                </form><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>