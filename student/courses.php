<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('student')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT c.*, u.first_name, u.last_name, e.enrollment_date, e.final_grade 
          FROM courses c 
          JOIN enrollments e ON c.id = e.course_id 
          JOIN users u ON c.instructor_id = u.id 
          WHERE e.student_id = ? AND e.status = 'enrolled' 
          ORDER BY e.enrollment_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$enrolledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Courses - Melbourne LMS</title>
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
					<h1 class="h2"><i class="fas fa-book"></i> My Courses</h1>
				</div>

				<div class="card shadow">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list"></i> Enrolled Courses</h6>
					</div>
					<div class="card-body">
						<?php if (empty($enrolledCourses)): ?>
							<div class="text-center py-4">
								<i class="fas fa-book fa-3x text-gray-300 mb-3"></i>
								<p class="text-muted">You are not enrolled in any courses yet.</p>
							</div>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover align-middle">
									<thead>
										<tr>
											<th>Course</th>
											<th>Code</th>
											<th>Instructor</th>
											<th>Enrolled</th>
											<th>Final Grade</th>
											<th>Actions</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($enrolledCourses as $course): ?>
										<tr>
											<td>
												<strong><?php echo htmlspecialchars($course['title']); ?></strong>
												<div class="small text-muted">Semester: <?php echo htmlspecialchars($course['semester']); ?>, Year: <?php echo (int)$course['year']; ?></div>
											</td>
											<td><?php echo htmlspecialchars($course['course_code']); ?></td>
											<td><?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?></td>
											<td><?php echo formatDate($course['enrollment_date']); ?></td>
											<td><?php echo is_null($course['final_grade']) ? '-' : $course['final_grade']; ?></td>
											<td>
												<a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
													<i class="fas fa-eye"></i> View
												</a>
												<a href="../forum/index.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-secondary">
													<i class="fas fa-comments"></i> Forum
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


