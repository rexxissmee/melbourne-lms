<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
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

// Ensure course belongs to instructor and fetch
$stmt = $db->prepare('SELECT * FROM courses WHERE id = ? AND instructor_id = ?');
$stmt->execute([$courseId, $_SESSION['user_id']]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
	header('Location: courses.php');
	exit();
}

// Handle material upload
$uploadErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
	$title = sanitizeInput($_POST['material_title'] ?? '');
	$description = trim($_POST['material_description'] ?? '');
	if ($title === '') { $uploadErrors[] = 'Material title is required.'; }

	if (empty($uploadErrors) && isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
		$uploadDir = __DIR__ . '/../uploads/materials/';
		if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

		$originalName = basename($_FILES['material_file']['name']);
		$ext = pathinfo($originalName, PATHINFO_EXTENSION);
		$safeName = 'course_' . $courseId . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? ('.' . $ext) : '');
		$targetPath = $uploadDir . $safeName;

		if (move_uploaded_file($_FILES['material_file']['tmp_name'], $targetPath)) {
			$filePathDb = 'uploads/materials/' . $safeName;
			$fileType = mime_content_type($targetPath) ?: '';
			$fileSize = (int)filesize($targetPath);
			$ins = $db->prepare('INSERT INTO course_materials (course_id, title, description, file_path, file_type, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
			$ins->execute([$courseId, $title, $description, $filePathDb, $fileType, $fileSize, $_SESSION['user_id']]);
			header('Location: course_view.php?id=' . $courseId);
			exit();
		} else {
			$uploadErrors[] = 'Failed to upload file.';
		}
	} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_material'])) {
		$uploadErrors[] = 'Please select a file.';
	}
}

// Fetch materials
$materialsStmt = $db->prepare('SELECT * FROM course_materials WHERE course_id = ? ORDER BY upload_date DESC');
$materialsStmt->execute([$courseId]);
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch enrolled students
$enrollStmt = $db->prepare('SELECT u.id, u.first_name, u.last_name, e.enrollment_date, e.status
                            FROM enrollments e
                            JOIN users u ON e.student_id = u.id
                            WHERE e.course_id = ?
                            ORDER BY e.enrollment_date DESC');
$enrollStmt->execute([$courseId]);
$enrollments = $enrollStmt->fetchAll(PDO::FETCH_ASSOC);

// Optional: add/remove student enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_enrollment'])) {
	$action = $_POST['action'] ?? '';
	$studentId = (int)($_POST['student_id'] ?? 0);
	if ($studentId > 0) {
		if ($action === 'remove') {
			$del = $db->prepare('DELETE FROM enrollments WHERE student_id = ? AND course_id = ?');
			$del->execute([$studentId, $courseId]);
			header('Location: course_view.php?id=' . $courseId);
			exit();
		} elseif ($action === 'add') {
			// Prevent duplicates
			$chk = $db->prepare('SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?');
			$chk->execute([$studentId, $courseId]);
			if (!$chk->fetch()) {
				$ins = $db->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')");
				$ins->execute([$studentId, $courseId]);
			}
			header('Location: course_view.php?id=' . $courseId);
			exit();
		}
	}
}

// Fetch students for add dropdown
$studentsStmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'student' ORDER BY first_name, last_name");
$studentsStmt->execute();
$allStudents = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Course Details - Melbourne LMS</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
	<?php include '../includes/instructor_navbar.php'; ?>
	<div class="container-fluid">
		<div class="row">
			<?php include '../includes/instructor_sidebar.php'; ?>
			<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
				<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
					<h1 class="h2"><i class="fas fa-book"></i> <?php echo htmlspecialchars($course['title']); ?></h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<a class="btn btn-sm btn-outline-secondary" href="course_edit.php?id=<?php echo $courseId; ?>">
							<i class="fas fa-edit"></i> Edit
						</a>
						<a class="btn btn-sm btn-primary ms-2" href="assignments.php?course_id=<?php echo $courseId; ?>">
							<i class="fas fa-clipboard-list"></i> Assignments
						</a>
					</div>
				</div>

				<div class="row mb-4">
					<div class="col-md-7">
						<div class="card shadow mb-4">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle"></i> Course Info</h6>
							</div>
							<div class="card-body">
								<div class="row g-3">
									<div class="col-sm-6"><strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></div>
									<div class="col-sm-6"><strong>Credits:</strong> <?php echo (int)$course['credits']; ?></div>
									<div class="col-sm-6"><strong>Semester:</strong> <?php echo htmlspecialchars($course['semester']); ?></div>
									<div class="col-sm-6"><strong>Year:</strong> <?php echo (int)$course['year']; ?></div>
									<div class="col-sm-6"><strong>Max Students:</strong> <?php echo (int)$course['max_students']; ?></div>
									<div class="col-sm-6"><strong>Status:</strong> <span class="badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($course['status']); ?></span></div>
								</div>
								<div class="mt-3"><strong>Description</strong><div class="text-muted small">&nbsp;</div><?php echo nl2br(htmlspecialchars($course['description'])); ?></div>
							</div>
						</div>

						<div class="card shadow">
							<div class="card-header py-3 d-flex justify-content-between align-items-center">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-alt"></i> Materials</h6>
								<button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal"><i class="fas fa-upload"></i> Upload</button>
							</div>
							<div class="card-body">
								<?php if (!empty($uploadErrors)): ?>
									<div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $uploadErrors)); ?></div>
								<?php endif; ?>
								<?php if (empty($materials)): ?>
									<p class="text-muted">No materials uploaded yet.</p>
								<?php else: ?>
									<div class="table-responsive">
										<table class="table table-hover">
											<thead>
												<tr>
													<th>Title</th>
													<th>Type</th>
													<th>Size</th>
													<th>Uploaded</th>
													<th></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($materials as $m): ?>
												<tr>
													<td>
														<strong><?php echo htmlspecialchars($m['title']); ?></strong>
														<div class="small text-muted"><?php echo htmlspecialchars($m['description']); ?></div>
													</td>
													<td><?php echo htmlspecialchars($m['file_type']); ?></td>
													<td><?php echo number_format(($m['file_size'] ?? 0) / 1024, 1); ?> KB</td>
													<td><?php echo formatDate($m['upload_date']); ?></td>
													<td class="text-end">
														<a class="btn btn-sm btn-outline-secondary" href="../<?php echo $m['file_path']; ?>" target="_blank"><i class="fas fa-download"></i></a>
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

					<div class="col-md-5">
						<div class="card shadow">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users"></i> Enrollments</h6>
							</div>
							<div class="card-body">
								<form method="post" class="row g-2 align-items-end">
									<input type="hidden" name="manage_enrollment" value="1">
									<div class="col-8">
										<label class="form-label">Add student</label>
										<select name="student_id" class="form-select">
											<?php foreach ($allStudents as $stu): ?>
												<option value="<?php echo $stu['id']; ?>"><?php echo htmlspecialchars($stu['first_name'] . ' ' . $stu['last_name']); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="col-4">
										<button class="btn btn-primary w-100" name="action" value="add"><i class="fas fa-user-plus"></i> Add</button>
									</div>
								</form>

								<div class="table-responsive mt-3">
									<table class="table table-striped align-middle">
										<thead>
											<tr>
												<th>Student</th>
												<th>Enrolled</th>
												<th>Status</th>
												<th></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($enrollments as $en): ?>
											<tr>
												<td><?php echo htmlspecialchars($en['first_name'] . ' ' . $en['last_name']); ?></td>
												<td><?php echo formatDate($en['enrollment_date']); ?></td>
												<td><span class="badge bg-<?php echo $en['status'] === 'enrolled' ? 'success' : ($en['status'] === 'completed' ? 'primary' : 'secondary'); ?>"><?php echo ucfirst($en['status']); ?></span></td>
												<td class="text-end">
													<form method="post" class="d-inline">
														<input type="hidden" name="manage_enrollment" value="1">
														<input type="hidden" name="student_id" value="<?php echo $en['id']; ?>">
														<button class="btn btn-sm btn-outline-danger" name="action" value="remove"><i class="fas fa-user-minus"></i></button>
													</form>
												</td>
											</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</main>
		</div>
	</div>

	<!-- Upload Material Modal -->
	<div class="modal fade" id="uploadModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title"><i class="fas fa-upload"></i> Upload Material</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<form method="post" enctype="multipart/form-data">
					<div class="modal-body">
						<input type="hidden" name="upload_material" value="1">
						<div class="mb-3">
							<label class="form-label">Title</label>
							<input type="text" name="material_title" class="form-control" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea name="material_description" class="form-control" rows="3"></textarea>
						</div>
						<div class="mb-3">
							<label class="form-label">File</label>
							<input type="file" name="material_file" class="form-control" required>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="submit" class="btn btn-primary">Upload</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


