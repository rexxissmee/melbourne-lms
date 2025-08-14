<?php
require_once '../config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Determine access: student (enrolled) or instructor (owner)
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if ($courseId <= 0) {
	header('Location: index.php');
	exit();
}

// Check access to course
if ($role === 'student') {
	$accessStmt = $db->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'enrolled' LIMIT 1");
	$accessStmt->execute([$userId, $courseId]);
	$hasAccess = (bool)$accessStmt->fetchColumn();
} elseif ($role === 'instructor') {
	$accessStmt = $db->prepare("SELECT 1 FROM courses WHERE instructor_id = ? AND id = ? LIMIT 1");
	$accessStmt->execute([$userId, $courseId]);
	$hasAccess = (bool)$accessStmt->fetchColumn();
} else {
	$hasAccess = false;
}

if (!$hasAccess) {
	header('Location: index.php');
	exit();
}

// Load categories for the course
$categoriesStmt = $db->prepare('SELECT id, name FROM forum_categories WHERE course_id = ? ORDER BY name');
$categoriesStmt->execute([$courseId]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$categoryId = (int)($_POST['category_id'] ?? 0);
	$title = sanitizeInput($_POST['title'] ?? '');
	$content = trim($_POST['content'] ?? '');

	if ($categoryId <= 0) {
		$errors[] = 'Please select a category.';
	}
	if ($title === '') {
		$errors[] = 'Title is required.';
	}
	if ($content === '') {
		$errors[] = 'Content is required.';
	}

	// Validate category belongs to this course
	if (empty($errors)) {
		$chk = $db->prepare('SELECT id FROM forum_categories WHERE id = ? AND course_id = ?');
		$chk->execute([$categoryId, $courseId]);
		if (!$chk->fetch()) {
			$errors[] = 'Invalid category.';
		}
	}

	if (empty($errors)) {
		try {
			$db->beginTransaction();
			$insTopic = $db->prepare('INSERT INTO forum_topics (category_id, title, created_by, created_at, last_post_at) VALUES (?, ?, ?, NOW(), NOW())');
			$insTopic->execute([$categoryId, $title, $userId]);
			$topicId = (int)$db->lastInsertId();

			$insPost = $db->prepare('INSERT INTO forum_posts (topic_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())');
			$insPost->execute([$topicId, $userId, $content]);

			$db->commit();
			header('Location: topic_view.php?id=' . $topicId);
			exit();
		} catch (Exception $e) {
			$db->rollBack();
			$errors[] = 'Failed to create topic.';
		}
	}
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Create Topic - Melbourne LMS</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<link href="../assets/css/dashboard.css" rel="stylesheet">
</head>

<body>
	<?php
	if ($role === 'student') {
		include '../includes/student_navbar.php';
	} elseif ($role === 'instructor') {
		include '../includes/instructor_navbar.php';
	}
	?>
	<div class="container-fluid">
		<div class="row">
			<?php
			if ($role === 'student') {
				include '../includes/student_sidebar.php';
			} elseif ($role === 'instructor') {
				include '../includes/instructor_sidebar.php';
			}
			?>
			<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
				<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
					<h1 class="h2"><i class="fas fa-plus"></i> New Topic</h1>
				</div>

				<?php if (empty($categories)): ?>
					<div class="alert alert-info">
						No forum categories available for this course.
						<?php if ($role === 'instructor'): ?>
							<a class="alert-link" href="../instructor/forum_manage.php?course_id=<?php echo $courseId; ?>">Create one here</a>.
						<?php endif; ?>
					</div>
				<?php else: ?>
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
								<div class="mb-3">
									<label class="form-label">Category</label>
									<select name="category_id" class="form-select" required>
										<option value="">Select a category</option>
										<?php foreach ($categories as $cat): ?>
											<option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
												<?php echo htmlspecialchars($cat['name']); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="mb-3">
									<label class="form-label">Title</label>
									<input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
								</div>
								<div class="mb-3">
									<label class="form-label">Content</label>
									<textarea name="content" rows="6" class="form-control" placeholder="You can use [b]bold[/b], [i]italic[/i], [u]underline[/u]" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
								</div>
								<div>
									<a href="index.php?course_id=<?php echo $courseId; ?>" class="btn btn-outline-secondary">Cancel</a>
									<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Create Topic</button>
								</div>
							</form>
						</div>
					</div>
				<?php endif; ?>
			</main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>