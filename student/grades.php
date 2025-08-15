<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('student')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

// All graded submissions for the student
$stmt = $db->prepare('SELECT s.*, a.title AS assignment_title, a.max_points, c.title AS course_title, c.course_code
	FROM assignment_submissions s
	JOIN assignments a ON s.assignment_id = a.id
	JOIN courses c ON a.course_id = c.id
	WHERE s.student_id = ? AND s.grade IS NOT NULL
	ORDER BY s.graded_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$graded = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Grades</title>
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
					<h1 class="h2"><i class="fas fa-chart-line"></i> My Grades</h1>
				</div>

				<div class="card shadow">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary">Graded Assignments</h6>
					</div>
					<div class="card-body">
						<?php if (empty($graded)): ?>
							<p class="text-muted">No grades yet.</p>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover">
									<thead>
										<tr>
											<th>Assignment</th>
											<th>Course</th>
											<th>Submitted</th>
											<th>Grade</th>
											<th>Graded</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($graded as $g): ?>
										<tr>
											<td><strong><?php echo htmlspecialchars($g['assignment_title']); ?></strong></td>
											<td><?php echo htmlspecialchars($g['course_code'] . ' - ' . $g['course_title']); ?></td>
											<td><?php echo formatDate($g['submitted_at']); ?></td>
											<td><?php echo htmlspecialchars($g['grade']); ?>/<?php echo htmlspecialchars($g['max_points']); ?></td>
											<td><?php echo formatDate($g['graded_at']); ?></td>
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



