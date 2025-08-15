<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('student')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch assignments for student's enrolled courses and submission status
$stmt = $db->prepare('SELECT a.*, c.title AS course_title, c.course_code,
	(SELECT s.grade FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS my_grade,
	(SELECT s.submitted_at FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS my_submitted_at
	FROM assignments a
	JOIN courses c ON a.course_id = c.id
	JOIN enrollments e ON e.course_id = c.id AND e.student_id = ? AND e.status = "enrolled"
	ORDER BY a.due_date IS NULL, a.due_date ASC, a.created_at DESC');
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Assignments - Student</title>
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
					<h1 class="h2"><i class="fas fa-tasks"></i> Assignments</h1>
				</div>

				<div class="card shadow">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary">All Assignments</h6>
					</div>
					<div class="card-body">
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
											<th>Status</th>
											<th>Grade</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($assignments as $a): ?>
										<tr>
											<td><strong><?php echo htmlspecialchars($a['title']); ?></strong></td>
											<td><?php echo htmlspecialchars($a['course_code'] . ' - ' . $a['course_title']); ?></td>
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
			</main>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



