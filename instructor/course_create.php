<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$title = sanitizeInput($_POST['title'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$course_code = sanitizeInput($_POST['course_code'] ?? '');
	$credits = (int)($_POST['credits'] ?? 3);
	$semester = sanitizeInput($_POST['semester'] ?? '');
	$year = (int)($_POST['year'] ?? date('Y'));
	$max_students = (int)($_POST['max_students'] ?? 50);
	$status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

	if ($title === '') { $errors[] = 'Title is required.'; }
	if ($course_code === '') { $errors[] = 'Course code is required.'; }
	if ($credits <= 0) { $errors[] = 'Credits must be positive.'; }
	if ($year < 2000 || $year > 2100) { $errors[] = 'Year must be between 2000 and 2100.'; }
	if ($max_students <= 0) { $errors[] = 'Max students must be positive.'; }

	if (empty($errors)) {
		// Ensure unique course code
		$check = $db->prepare('SELECT id FROM courses WHERE course_code = ?');
		$check->execute([$course_code]);
		if ($check->fetch()) {
			$errors[] = 'Course code already exists.';
		}
	}

	if (empty($errors)) {
		$insert = $db->prepare('INSERT INTO courses (title, description, instructor_id, course_code, credits, semester, year, max_students, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
		$insert->execute([$title, $description, $_SESSION['user_id'], $course_code, $credits, $semester, $year, $max_students, $status]);
		$courseId = (int)$db->lastInsertId();
		header('Location: course_view.php?id=' . $courseId);
		exit();
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Create Course - Melbourne LMS</title>
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
					<h1 class="h2"><i class="fas fa-plus"></i> Create Course</h1>
				</div>

				<?php if (!empty($errors)): ?>
					<div class="alert alert-danger">
						<ul class="mb-0">
							<?php foreach ($errors as $err): ?>
								<li><?php echo htmlspecialchars($err); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div class="card shadow">
					<div class="card-body">
						<form method="post">
							<div class="row g-3">
								<div class="col-md-8">
									<label class="form-label">Title</label>
									<input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
								</div>
								<div class="col-md-4">
									<label class="form-label">Course Code</label>
									<input type="text" name="course_code" class="form-control" required value="<?php echo htmlspecialchars($_POST['course_code'] ?? ''); ?>">
								</div>
								<div class="col-12">
									<label class="form-label">Description</label>
									<textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
								</div>
								<div class="col-md-3">
									<label class="form-label">Credits</label>
									<input type="number" name="credits" min="1" class="form-control" value="<?php echo htmlspecialchars($_POST['credits'] ?? 3); ?>">
								</div>
								<div class="col-md-3">
									<label class="form-label">Semester</label>
									<input type="text" name="semester" class="form-control" value="<?php echo htmlspecialchars($_POST['semester'] ?? ''); ?>">
								</div>
								<div class="col-md-3">
									<label class="form-label">Year</label>
									<input type="number" name="year" class="form-control" value="<?php echo htmlspecialchars($_POST['year'] ?? date('Y')); ?>">
								</div>
								<div class="col-md-3">
									<label class="form-label">Max Students</label>
									<input type="number" name="max_students" min="1" class="form-control" value="<?php echo htmlspecialchars($_POST['max_students'] ?? 50); ?>">
								</div>
								<div class="col-md-3">
									<label class="form-label">Status</label>
									<select name="status" class="form-select">
										<option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
										<option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
									</select>
								</div>
							</div>
							<div class="mt-4">
								<a href="courses.php" class="btn btn-outline-secondary">
									Cancel
								</a>
								<button type="submit" class="btn btn-primary">
									<i class="fas fa-save"></i> Create Course
								</button>
							</div>
						</form>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


