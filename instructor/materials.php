<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch instructor courses for filter
$coursesStmt = $db->prepare('SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title');
$coursesStmt->execute([$_SESSION['user_id']]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Delete material
if (isset($_POST['delete_material'])) {
	$materialId = (int)$_POST['material_id'];
	$del = $db->prepare('DELETE m FROM course_materials m JOIN courses c ON m.course_id = c.id WHERE m.id = ? AND c.instructor_id = ?');
	$del->execute([$materialId, $_SESSION['user_id']]);
	header('Location: materials.php' . ($selectedCourseId ? ('?course_id=' . $selectedCourseId) : ''));
	exit();
}

$materials = [];
if ($selectedCourseId > 0) {
	$stmt = $db->prepare('SELECT m.*, c.title as course_title FROM course_materials m JOIN courses c ON m.course_id = c.id WHERE m.course_id = ? AND c.instructor_id = ? ORDER BY m.upload_date DESC');
	$stmt->execute([$selectedCourseId, $_SESSION['user_id']]);
	$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
	$stmt = $db->prepare('SELECT m.*, c.title as course_title FROM course_materials m JOIN courses c ON m.course_id = c.id WHERE c.instructor_id = ? ORDER BY m.upload_date DESC');
	$stmt->execute([$_SESSION['user_id']]);
	$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Materials - Melbourne LMS</title>
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
					<h1 class="h2"><i class="fas fa-file-alt"></i> Course Materials</h1>
				</div>

				<form method="get" class="row g-2 mb-3">
					<div class="col-md-6">
						<select name="course_id" class="form-select" onchange="this.form.submit()">
							<option value="0">All Courses</option>
							<?php foreach ($courses as $c): ?>
								<option value="<?php echo $c['id']; ?>" <?php echo $selectedCourseId == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['title']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</form>

				<div class="card shadow">
					<div class="card-body">
						<?php if (empty($materials)): ?>
							<p class="text-muted">No materials found.</p>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover align-middle">
									<thead>
										<tr>
											<th>Title</th>
											<th>Course</th>
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
												<div class="fw-bold"><?php echo htmlspecialchars($m['title']); ?></div>
												<div class="small text-muted"><?php echo htmlspecialchars($m['description']); ?></div>
											</td>
											<td><?php echo htmlspecialchars($m['course_title']); ?></td>
											<td><?php echo htmlspecialchars($m['file_type']); ?></td>
											<td><?php echo number_format(($m['file_size'] ?? 0) / 1024, 1); ?> KB</td>
											<td><?php echo formatDate($m['upload_date']); ?></td>
											<td class="text-end">
												<a class="btn btn-sm btn-outline-secondary" href="../<?php echo $m['file_path']; ?>" target="_blank"><i class="fas fa-download"></i></a>
												<form method="post" class="d-inline ms-1">
													<input type="hidden" name="material_id" value="<?php echo $m['id']; ?>">
													<button class="btn btn-sm btn-outline-danger" name="delete_material" onclick="return confirm('Delete this material?')"><i class="fas fa-trash"></i></button>
												</form>
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


