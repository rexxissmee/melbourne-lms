<?php
require_once '../config/database.php';
requireLogin();
if (!hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($courseId <= 0) {
    header('Location: courses.php');
    exit();
}

$courseStmt = $db->prepare('SELECT c.*, u.first_name, u.last_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.id = ?');
$courseStmt->execute([$courseId]);
$course = $courseStmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
    header('Location: courses.php');
    exit();
}

$matStmt = $db->prepare('SELECT id,title,description,file_path,file_type,file_size,upload_date FROM course_materials WHERE course_id = ? ORDER BY upload_date DESC');
$matStmt->execute([$courseId]);
$materials = $matStmt->fetchAll(PDO::FETCH_ASSOC);

$enStmt = $db->prepare('SELECT u.id, u.first_name,u.last_name,e.enrollment_date FROM enrollments e JOIN users u ON e.student_id = u.id WHERE e.course_id = ?');
$enStmt->execute([$courseId]);
$students = $enStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Detail - Melbourne LMS</title>
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
                    <h1 class="h2"><i class="fas fa-book"></i> <?php echo htmlspecialchars($course['title']); ?></h1>
                    <a href="enrollments.php?course_id=<?php echo $courseId; ?>" class="btn btn-sm btn-primary"><i class="fas fa-user-plus"></i> Manage Enrollments</a>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header"><strong>Course Info</strong></div>
                            <div class="card-body">
                                <ul class="list-unstyled mb-0">
                                    <li><strong>Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></li>
                                    <li><strong>Instructor:</strong> <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></li>
                                    <li><strong>Semester/Year:</strong> <?php echo htmlspecialchars($course['semester'] . '/' . $course['year']); ?></li>
                                    <li><strong>Credits:</strong> <?php echo (int)$course['credits']; ?></li>
                                    <li><strong>Status:</strong> <span class="badge bg-<?php echo $course['status'] === 'archived' ? 'secondary' : ($course['status'] === 'inactive' ? 'warning text-dark' : 'success'); ?> text-capitalize"><?php echo $course['status']; ?></span></li>
                                    <li><strong>Max Students:</strong> <?php echo (int)$course['max_students']; ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card shadow">
                            <div class="card-header"><strong>Enrolled Students (<?php echo count($students); ?>)</strong></div>
                            <div class="card-body" style="max-height:300px;overflow:auto;">
                                <?php if (empty($students)): ?><p class="text-muted">No students enrolled.</p><?php else: ?><ul class="list-group list-group-flush">
                                        <?php foreach ($students as $s): ?><li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?>
                                                <span class="small text-muted"><?php echo formatDate($s['enrollment_date']); ?></span>
                                            </li><?php endforeach; ?></ul><?php endif; ?></div>
                        </div>
                    </div>
                </div>

                <div class="card shadow mt-4">
                    <div class="card-header"><strong>Materials (<?php echo count($materials); ?>)</strong></div>
                    <div class="card-body">
                        <?php if (empty($materials)): ?><p class="text-muted">No materials uploaded.</p><?php else: ?><div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Size</th>
                                            <th>Uploaded</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody><?php foreach ($materials as $m): ?><tr>
                                                <td><?php echo htmlspecialchars($m['title']); ?></td>
                                                <td><?php echo htmlspecialchars($m['file_type']); ?></td>
                                                <td><?php echo number_format($m['file_size'] / 1024, 1); ?> KB</td>
                                                <td><?php echo formatDate($m['upload_date']); ?></td>
                                                <td class="text-end"><a href="../<?php echo $m['file_path']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i></a></td>
                                            </tr><?php endforeach; ?></tbody>
                                </table>
                            </div><?php endif; ?></div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>