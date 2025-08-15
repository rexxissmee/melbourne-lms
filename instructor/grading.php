<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

// Ungraded submissions
$ungradedStmt = $db->prepare('SELECT s.*, a.title AS assignment_title, a.max_points, c.title AS course_title
	FROM assignment_submissions s
	JOIN assignments a ON s.assignment_id = a.id
	JOIN courses c ON a.course_id = c.id
	WHERE c.instructor_id = ? AND s.grade IS NULL
	ORDER BY s.submitted_at ASC');
$ungradedStmt->execute([$_SESSION['user_id']]);
$ungraded = $ungradedStmt->fetchAll(PDO::FETCH_ASSOC);

// Recently graded submissions
$gradedStmt = $db->prepare('SELECT s.*, a.title AS assignment_title, a.max_points, c.title AS course_title
	FROM assignment_submissions s
	JOIN assignments a ON s.assignment_id = a.id
	JOIN courses c ON a.course_id = c.id
	WHERE c.instructor_id = ? AND s.grade IS NOT NULL
	ORDER BY s.graded_at DESC LIMIT 20');
$gradedStmt->execute([$_SESSION['user_id']]);
$recent = $gradedStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Grading - Instructor</title>
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
					<h1 class="h2"><i class="fas fa-clipboard-check"></i> Grading</h1>
				</div>

				<div class="row g-4">
					<div class="col-lg-7">
						<div class="card shadow">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary">Ungraded Submissions</h6>
							</div>
							<div class="card-body">
								<?php if (empty($ungraded)): ?>
									<p class="text-muted">No ungraded submissions.</p>
								<?php else: ?>
									<div class="table-responsive">
										<table class="table table-hover align-middle">
											<thead>
												<tr>
													<th>Assignment</th>
													<th>Course</th>
													<th>Student</th>
													<th>Submitted</th>
													<th></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($ungraded as $s): ?>
												<tr>
													<td><strong><?php echo htmlspecialchars($s['assignment_title']); ?></strong></td>
													<td><?php echo htmlspecialchars($s['course_title']); ?></td>
													<td>#<?php echo (int)$s['student_id']; ?></td>
													<td><?php echo formatDate($s['submitted_at']); ?></td>
													<td class="text-end">
														<a class="btn btn-sm btn-primary" href="assignment_view.php?id=<?php echo (int)$s['assignment_id']; ?>">
															<i class="fas fa-pen"></i> Grade
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

					<div class="col-lg-5">
						<div class="card shadow">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary">Recently Graded</h6>
							</div>
							<div class="card-body">
								<?php if (empty($recent)): ?>
									<p class="text-muted">No recent grading.</p>
								<?php else: ?>
									<ul class="list-group list-group-flush">
										<?php foreach ($recent as $s): ?>
										<li class="list-group-item d-flex justify-content-between align-items-center">
											<div>
												<strong><?php echo htmlspecialchars($s['assignment_title']); ?></strong>
												<div class="small text-muted"><?php echo htmlspecialchars($s['course_title']); ?> • Grade: <?php echo htmlspecialchars($s['grade']); ?>/<?php echo htmlspecialchars($s['max_points']); ?></div>
											</div>
											<small class="text-muted"><?php echo formatDate($s['graded_at']); ?></small>
										</li>
										<?php endforeach; ?>
									</ul>
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



