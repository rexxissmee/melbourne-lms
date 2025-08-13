<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch instructor courses with enrollment counts
$courses = [];
$query = "SELECT c.*, 
                 (SELECT COUNT(e.id) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'enrolled') AS enrolled_count
          FROM courses c
          WHERE c.instructor_id = ?
          ORDER BY c.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
	<?php include '../includes/instructor_navbar.php'; ?>

	<div class="container-fluid">
		<div class="row">
			<?php include '../includes/instructor_sidebar.php'; ?>

			<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
				<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
					<h1 class="h2">
						<i class="fas fa-book"></i> My Courses
					</h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<a href="course_create.php" class="btn btn-sm btn-primary">
							<i class="fas fa-plus"></i> Create Course
						</a>
					</div>
				</div>

				<div class="card shadow">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary">
							<i class="fas fa-list"></i> Courses
						</h6>
					</div>
					<div class="card-body">
						<?php if (empty($courses)): ?>
							<div class="text-center py-4">
								<i class="fas fa-book fa-3x text-gray-300 mb-3"></i>
								<p class="text-muted">You haven't created any courses yet.</p>
								<a href="course_create.php" class="btn btn-primary">Create Your First Course</a>
							</div>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover align-middle">
									<thead>
										<tr>
											<th>Title</th>
											<th>Code</th>
											<th>Semester</th>
											<th>Year</th>
											<th>Students</th>
											<th>Status</th>
											<th>Actions</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($courses as $course): ?>
										<tr>
											<td>
												<strong><?php echo htmlspecialchars($course['title']); ?></strong>
												<div class="small text-muted">Created <?php echo formatDate($course['created_at']); ?></div>
											</td>
											<td><?php echo htmlspecialchars($course['course_code']); ?></td>
											<td><?php echo htmlspecialchars($course['semester']); ?></td>
											<td><?php echo htmlspecialchars($course['year']); ?></td>
											<td><span class="badge bg-info"><?php echo (int)$course['enrolled_count']; ?></span> / <?php echo (int)$course['max_students']; ?></td>
											<td>
												<span class="badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($course['status']); ?></span>
											</td>
											<td>
												<div class="btn-group" role="group">
													<a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary" title="View">
														<i class="fas fa-eye"></i>
													</a>
													<a href="course_edit.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit">
														<i class="fas fa-edit"></i>
													</a>
												</div>
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


