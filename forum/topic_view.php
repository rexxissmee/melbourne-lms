<?php
require_once '../config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

$topicId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($topicId <= 0) {
	header('Location: index.php');
	exit();
}

// Fetch topic and course access info
$topicStmt = $db->prepare('SELECT ft.*, fc.course_id, fc.name as category_name, c.instructor_id
                           FROM forum_topics ft
                           JOIN forum_categories fc ON ft.category_id = fc.id
                           JOIN courses c ON fc.course_id = c.id
                           WHERE ft.id = ?');
$topicStmt->execute([$topicId]);
$topic = $topicStmt->fetch(PDO::FETCH_ASSOC);
if (!$topic) {
	header('Location: index.php');
	exit();
}

// Access control: student enrolled or course instructor
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$hasAccess = false;
if ($role === 'student') {
	$acc = $db->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'enrolled' LIMIT 1");
	$acc->execute([$userId, $topic['course_id']]);
	$hasAccess = (bool)$acc->fetchColumn();
} elseif ($role === 'instructor') {
	$hasAccess = ((int)$topic['instructor_id'] === (int)$userId);
}
if (!$hasAccess) {
	header('Location: index.php');
	exit();
}

// New reply
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$content = trim($_POST['content'] ?? '');
	if ($content === '') {
		$errors[] = 'Content is required.';
	}
	if (empty($errors)) {
		$ins = $db->prepare('INSERT INTO forum_posts (topic_id, user_id, content, created_at) VALUES (?, ?, ?, NOW())');
		$ins->execute([$topicId, $userId, $content]);
		$upd = $db->prepare('UPDATE forum_topics SET last_post_at = NOW() WHERE id = ?');
		$upd->execute([$topicId]);
		header('Location: topic_view.php?id=' . $topicId);
		exit();
	}
}

// Fetch posts
$postsStmt = $db->prepare('SELECT fp.*, u.first_name, u.last_name, u.role FROM forum_posts fp JOIN users u ON fp.user_id = u.id WHERE fp.topic_id = ? ORDER BY fp.created_at ASC');
$postsStmt->execute([$topicId]);
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($topic['title']); ?> - Forum - Melbourne LMS</title>
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
					<h1 class="h2"><i class="fas fa-comments"></i> <?php echo htmlspecialchars($topic['title']); ?></h1>
					<a href="index.php?course_id=<?php echo $topic['course_id']; ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
				</div>

				<div class="card shadow mb-4">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-stream"></i> Posts</h6>
					</div>
					<div class="card-body">
						<?php foreach ($posts as $post): ?>
							<div class="mb-4">
								<div class="d-flex justify-content-between">
									<strong><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?></strong>
									<small class="text-muted"><?php echo formatDate($post['created_at']); ?></small>
								</div>
								<div><?php echo renderForumText($post['content']); ?></div>
								<hr>
							</div>
						<?php endforeach; ?>

						<?php if (empty($posts)): ?>
							<p class="text-muted">No posts yet.</p>
						<?php endif; ?>
					</div>
				</div>

				<div class="card shadow">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-reply"></i> Reply</h6>
					</div>
					<div class="card-body">
						<?php if (!empty($errors)): ?>
							<div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
						<?php endif; ?>
						<form method="post">
							<div class="mb-3">
								<textarea name="content" rows="4" class="form-control" placeholder="You can use [b]bold[/b], [i]italic[/i], [u]underline[/u]" required></textarea>
							</div>
							<button class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Reply</button>
						</form>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>