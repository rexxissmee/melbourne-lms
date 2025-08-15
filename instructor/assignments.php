<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch instructor courses for dropdown and listing
$coursesStmt = $db->prepare('SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY created_at DESC');
$coursesStmt->execute([$_SESSION['user_id']]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle create assignment
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
	$courseId = (int)($_POST['course_id'] ?? 0);
	$title = sanitizeInput($_POST['title'] ?? '');
	$description = trim($_POST['description'] ?? '');
	$dueDate = trim($_POST['due_date'] ?? '');
	$maxPoints = isset($_POST['max_points']) ? (float)$_POST['max_points'] : 100.0;

	if ($courseId <= 0) { $errors[] = 'Course is required.'; }
	if ($title === '') { $errors[] = 'Title is required.'; }
	if ($maxPoints <= 0) { $errors[] = 'Max points must be greater than 0.'; }

	// Ensure course belongs to instructor
	if ($courseId > 0) {
		$checkStmt = $db->prepare('SELECT id FROM courses WHERE id = ? AND instructor_id = ?');
		$checkStmt->execute([$courseId, $_SESSION['user_id']]);
		if (!$checkStmt->fetch()) { $errors[] = 'Invalid course selection.'; }
	}

	if (empty($errors)) {
		$ins = $db->prepare('INSERT INTO assignments (course_id, title, description, due_date, max_points, created_by) VALUES (?,?,?,?,?,?)');
		$ins->execute([$courseId, $title, $description, $dueDate !== '' ? $dueDate : null, $maxPoints, $_SESSION['user_id']]);
		header('Location: assignments.php');
		exit();
	}
}

// Fetch assignments created for instructor's courses
$assignmentsStmt = $db->prepare('SELECT a.*, c.title AS course_title
	FROM assignments a
	JOIN courses c ON a.course_id = c.id
	WHERE c.instructor_id = ?
	ORDER BY a.created_at DESC');
$assignmentsStmt->execute([$_SESSION['user_id']]);
$assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Assignments - Instructor</title>
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
					<h1 class="h2"><i class="fas fa-tasks"></i> Assignments</h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
							<i class="fas fa-plus"></i> Create Assignment
						</button>
					</div>
				</div>

				<div class="card shadow mb-4">
					<div class="card-header py-3 d-flex justify-content-between align-items-center">
						<h6 class="m-0 font-weight-bold text-primary">Your Assignments</h6>
					</div>
					<div class="card-body">
						<?php if (!empty($errors)): ?>
							<div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
						<?php endif; ?>
						<?php if (empty($assignments)): ?>
							<p class="text-muted">No assignments yet.</p>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover">
									<thead>
										<tr>
											<th>Title</th>
											<th>Course</th>
											<th>Due</th>
											<th>Max Points</th>
											<th>Created</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($assignments as $a): ?>
										<tr>
											<td><strong><?php echo htmlspecialchars($a['title']); ?></strong></td>
											<td><?php echo htmlspecialchars($a['course_title']); ?></td>
											<td><?php echo $a['due_date'] ? formatDate($a['due_date']) : '<span class="text-muted">—</span>'; ?></td>
											<td><?php echo htmlspecialchars($a['max_points']); ?></td>
											<td><?php echo formatDate($a['created_at']); ?></td>
											<td class="text-end">
												<a class="btn btn-sm btn-outline-primary" href="assignment_view.php?id=<?php echo (int)$a['id']; ?>">
													<i class="fas fa-folder-open"></i> View Submissions
												</a>
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

	<!-- Create Assignment Modal -->
	<div class="modal fade" id="createModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title"><i class="fas fa-plus"></i> Create Assignment</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<form method="post">
					<div class="modal-body">
						<input type="hidden" name="create_assignment" value="1">
						<div class="mb-3">
							<label class="form-label">Course</label>
							<select name="course_id" class="form-select" required>
								<option value="">Select course</option>
								<?php foreach ($courses as $c): ?>
									<option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="mb-3">
							<label class="form-label">Title</label>
							<input type="text" name="title" class="form-control" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea name="description" class="form-control" rows="4"></textarea>
						</div>
						<div class="mb-3">
							<label class="form-label">Due date</label>
							<input type="datetime-local" name="due_date" class="form-control">
						</div>
						<div class="mb-3">
							<label class="form-label">Max points</label>
							<input type="number" name="max_points" step="0.01" min="0.01" class="form-control" value="100">
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
