<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('student')) {
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

// Ensure student is enrolled in course and fetch course
$stmt = $db->prepare("SELECT c.*, u.first_name, u.last_name 
                      FROM courses c 
                      JOIN enrollments e ON c.id = e.course_id 
                      JOIN users u ON c.instructor_id = u.id
                      WHERE e.student_id = ? AND e.status = 'enrolled' AND c.id = ?");
$stmt->execute([$_SESSION['user_id'], $courseId]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) {
	header('Location: courses.php');
	exit();
}

// Fetch materials
$materialsStmt = $db->prepare('SELECT * FROM course_materials WHERE course_id = ? ORDER BY upload_date DESC');
$materialsStmt->execute([$courseId]);
$materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch assignments for this course with current student's status
$assignStmt = $db->prepare('SELECT a.*, 
	(SELECT s.grade FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS my_grade,
	(SELECT s.submitted_at FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS my_submitted_at
	FROM assignments a
	WHERE a.course_id = ?
	ORDER BY a.due_date IS NULL, a.due_date ASC, a.created_at DESC');
$assignStmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $courseId]);
$assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($course['title']); ?> - Melbourne LMS</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="../assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
	<?php include '../includes/student_navbar.php'; ?>
	<div class="container-fluid">
		<div class="row">
			<?php include '../includes/student_sidebar.php'; ?>
			<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
				<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
					<h1 class="h2"><i class="fas fa-book"></i> <?php echo htmlspecialchars($course['title']); ?></h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<a href="../forum/index.php?course_id=<?php echo $courseId; ?>" class="btn btn-sm btn-outline-secondary">
							<i class="fas fa-comments"></i> Forum
						</a>
					</div>
				</div>

				<div class="row">
					<div class="col-lg-7">
						<div class="card shadow mb-4">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-info-circle"></i> Course Info</h6>
							</div>
							<div class="card-body">
								<div class="row g-3">
									<div class="col-sm-6"><strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></div>
									<div class="col-sm-6"><strong>Instructor:</strong> <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></div>
									<div class="col-sm-6"><strong>Credits:</strong> <?php echo (int)$course['credits']; ?></div>
									<div class="col-sm-6"><strong>Semester:</strong> <?php echo htmlspecialchars($course['semester']); ?></div>
									<div class="col-sm-6"><strong>Year:</strong> <?php echo (int)$course['year']; ?></div>
								</div>
								<div class="mt-3"><strong>Description</strong><div class="text-muted small">&nbsp;</div><?php echo nl2br(htmlspecialchars($course['description'])); ?></div>
							</div>
						</div>
					</div>
					<div class="col-lg-5">
						<div class="card shadow">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-alt"></i> Materials</h6>
							</div>
							<div class="card-body">
								<?php if (empty($materials)): ?>
									<p class="text-muted">No materials available yet.</p>
								<?php else: ?>
									<div class="list-group list-group-flush">
										<?php foreach ($materials as $m): ?>
											<a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start" href="../<?php echo $m['file_path']; ?>" target="_blank">
												<div>
													<div class="fw-bold"><?php echo htmlspecialchars($m['title']); ?></div>
													<small class="text-muted"><?php echo htmlspecialchars($m['description']); ?></small>
												</div>
												<small class="text-muted ms-3"><?php echo formatDate($m['upload_date']); ?></small>
											</a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<div class="row mt-4">
					<div class="col-lg-12">
						<div class="card shadow">
							<div class="card-header py-3 d-flex justify-content-between align-items-center">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-tasks"></i> Assignments</h6>
								<a href="assignments.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-list"></i> All Assignments</a>
							</div>
							<div class="card-body">
								<?php if (empty($assignments)): ?>
									<p class="text-muted">No assignments yet.</p>
								<?php else: ?>
									<div class="table-responsive">
										<table class="table table-hover align-middle">
											<thead>
												<tr>
													<th>Title</th>
													<th>Due</th>
													<th>Status</th>
													<th>Grade</th>
													<th></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($assignments as $a): ?>
												<tr>
													<td><strong><?php echo htmlspecialchars($a['title']); ?></strong></td>
													<td><?php echo $a['due_date'] ? formatDate($a['due_date']) : '<span class="text-muted">—</span>'; ?></td>
													<td>
														<?php if ($a['my_submitted_at']): ?>
															<span class="badge bg-success">Submitted</span>
														<?php else: ?>
															<span class="badge bg-warning text-dark">Pending</span>
														<?php endif; ?>
													</td>
													<td><?php echo $a['my_grade'] !== null ? htmlspecialchars($a['my_grade']) : '<span class="text-muted">—</span>'; ?></td>
													<td class="text-end">
														<a class="btn btn-sm btn-outline-primary" href="assignment_view.php?id=<?php echo (int)$a['id']; ?>">
															<i class="fas fa-folder-open"></i> View & Submit
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
					</div>
				</div>
			</main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


