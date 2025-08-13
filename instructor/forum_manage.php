<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

// Courses taught by instructor
$coursesStmt = $db->prepare('SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title');
$coursesStmt->execute([$_SESSION['user_id']]);
$courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedCourseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$selectedCategoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Create category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
	$courseId = (int)($_POST['course_id'] ?? 0);
	$name = sanitizeInput($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	if ($courseId && $name !== '') {
		// Ensure ownership
		$own = $db->prepare('SELECT id FROM courses WHERE id = ? AND instructor_id = ?');
		$own->execute([$courseId, $_SESSION['user_id']]);
		if ($own->fetch()) {
			$ins = $db->prepare('INSERT INTO forum_categories (course_id, name, description) VALUES (?, ?, ?)');
			$ins->execute([$courseId, $name, $description]);
				header('Location: forum_manage.php?course_id=' . $courseId);
				exit();
		}
	}
}

// Update category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
	$courseId = (int)($_POST['course_id'] ?? 0);
	$categoryId = (int)($_POST['category_id'] ?? 0);
	$name = sanitizeInput($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	if ($courseId && $categoryId && $name !== '') {
		$own = $db->prepare('SELECT fc.id FROM forum_categories fc JOIN courses c ON fc.course_id = c.id WHERE fc.id = ? AND fc.course_id = ? AND c.instructor_id = ?');
		$own->execute([$categoryId, $courseId, $_SESSION['user_id']]);
		if ($own->fetch()) {
			$upd = $db->prepare('UPDATE forum_categories SET name = ?, description = ? WHERE id = ? AND course_id = ?');
			$upd->execute([$name, $description, $categoryId, $courseId]);
				header('Location: forum_manage.php?course_id=' . $courseId);
				exit();
		}
	}
}

// Delete category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
	$courseId = (int)($_POST['course_id'] ?? 0);
	$categoryId = (int)($_POST['category_id'] ?? 0);
	if ($courseId && $categoryId) {
		$own = $db->prepare('SELECT fc.id FROM forum_categories fc JOIN courses c ON fc.course_id = c.id WHERE fc.id = ? AND fc.course_id = ? AND c.instructor_id = ?');
		$own->execute([$categoryId, $courseId, $_SESSION['user_id']]);
		if ($own->fetch()) {
			$del = $db->prepare('DELETE FROM forum_categories WHERE id = ? AND course_id = ?');
			$del->execute([$categoryId, $courseId]);
				header('Location: forum_manage.php?course_id=' . $courseId);
				exit();
		}
	}
}

// List categories for selected course
$categories = [];
if ($selectedCourseId) {
	$catStmt = $db->prepare('SELECT fc.*, COUNT(ft.id) as topic_count FROM forum_categories fc LEFT JOIN forum_topics ft ON fc.id = ft.category_id WHERE fc.course_id = ? GROUP BY fc.id ORDER BY fc.name');
	$catStmt->execute([$selectedCourseId]);
	$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Topic management: list topics for selected course (and optional category)
$topics = [];
if ($selectedCourseId) {
    if ($selectedCategoryId) {
        $topicStmt = $db->prepare('SELECT ft.*, fc.name as category_name,
                                          (SELECT COUNT(fp.id) FROM forum_posts fp WHERE fp.topic_id = ft.id) as post_count,
                                          (SELECT id FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at ASC LIMIT 1) as first_post_id,
                                          (SELECT content FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at ASC LIMIT 1) as first_post_content
                                   FROM forum_topics ft
                                   JOIN forum_categories fc ON ft.category_id = fc.id
                                   JOIN courses c ON fc.course_id = c.id
                                   WHERE c.instructor_id = ? AND fc.course_id = ? AND fc.id = ?
                                   ORDER BY ft.is_pinned DESC, ft.last_post_at DESC');
        $topicStmt->execute([$_SESSION['user_id'], $selectedCourseId, $selectedCategoryId]);
    } else {
        $topicStmt = $db->prepare('SELECT ft.*, fc.name as category_name,
                                          (SELECT COUNT(fp.id) FROM forum_posts fp WHERE fp.topic_id = ft.id) as post_count,
                                          (SELECT id FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at ASC LIMIT 1) as first_post_id,
                                          (SELECT content FROM forum_posts fp WHERE fp.topic_id = ft.id ORDER BY fp.created_at ASC LIMIT 1) as first_post_content
                                   FROM forum_topics ft
                                   JOIN forum_categories fc ON ft.category_id = fc.id
                                   JOIN courses c ON fc.course_id = c.id
                                   WHERE c.instructor_id = ? AND fc.course_id = ?
                                   ORDER BY ft.is_pinned DESC, ft.last_post_at DESC');
        $topicStmt->execute([$_SESSION['user_id'], $selectedCourseId]);
    }
    $topics = $topicStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Update topic (title, category, pin/lock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_topic'])) {
    $topicId = (int)($_POST['topic_id'] ?? 0);
    $newTitle = sanitizeInput($_POST['title'] ?? '');
    $newCategoryId = (int)($_POST['category_id'] ?? 0);
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
    $isLocked = isset($_POST['is_locked']) ? 1 : 0;
    $firstPostContent = trim($_POST['first_post_content'] ?? '');
    if ($topicId && $newTitle && $newCategoryId) {
        // Ensure instructor owns the course and category belongs to same course
        $own = $db->prepare('SELECT ft.id FROM forum_topics ft
                             JOIN forum_categories fc ON ft.category_id = fc.id
                             JOIN courses c ON fc.course_id = c.id
                             WHERE ft.id = ? AND c.instructor_id = ?');
        $own->execute([$topicId, $_SESSION['user_id']]);
        if ($own->fetch()) {
            $catChk = $db->prepare('SELECT fc.id FROM forum_categories fc
                                    JOIN courses c ON fc.course_id = c.id
                                    WHERE fc.id = ? AND c.instructor_id = ?');
            $catChk->execute([$newCategoryId, $_SESSION['user_id']]);
            if ($catChk->fetch()) {
                $upd = $db->prepare('UPDATE forum_topics SET title = ?, category_id = ?, is_pinned = ?, is_locked = ? WHERE id = ?');
                $upd->execute([$newTitle, $newCategoryId, $isPinned, $isLocked, $topicId]);
                if ($firstPostContent !== '') {
                    // Update earliest post content of this topic
                    $fpIdStmt = $db->prepare('SELECT id FROM forum_posts WHERE topic_id = ? ORDER BY created_at ASC LIMIT 1');
                    $fpIdStmt->execute([$topicId]);
                    $firstPostId = $fpIdStmt->fetchColumn();
                    if ($firstPostId) {
                        $updPost = $db->prepare('UPDATE forum_posts SET content = ?, updated_at = NOW() WHERE id = ?');
                        $updPost->execute([$firstPostContent, $firstPostId]);
                    }
                }
                header('Location: forum_manage.php?course_id=' . $selectedCourseId . ($selectedCategoryId ? ('&category_id=' . $selectedCategoryId) : ''));
                exit();
            }
        }
    }
}

// Delete topic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_topic'])) {
    $topicId = (int)($_POST['topic_id'] ?? 0);
    if ($topicId) {
        $own = $db->prepare('SELECT ft.id FROM forum_topics ft
                             JOIN forum_categories fc ON ft.category_id = fc.id
                             JOIN courses c ON fc.course_id = c.id
                             WHERE ft.id = ? AND c.instructor_id = ?');
        $own->execute([$topicId, $_SESSION['user_id']]);
        if ($own->fetch()) {
            $del = $db->prepare('DELETE FROM forum_topics WHERE id = ?');
            $del->execute([$topicId]);
            header('Location: forum_manage.php?course_id=' . $selectedCourseId . ($selectedCategoryId ? ('&category_id=' . $selectedCategoryId) : ''));
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Forum Management - Melbourne LMS</title>
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
					<h1 class="h2"><i class="fas fa-comments"></i> Forum Management</h1>
				</div>

				<form method="get" class="row g-2 mb-3">
					<div class="col-md-6">
						<select name="course_id" class="form-select" onchange="this.form.submit()">
							<option value="0">Select a course</option>
							<?php foreach ($courses as $c): ?>
								<option value="<?php echo $c['id']; ?>" <?php echo $selectedCourseId == $c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['title']); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</form>

				<?php if ($selectedCourseId): ?>
				<div class="mb-3 d-flex justify-content-end">
					<a href="../forum/topic_create.php?course_id=<?php echo $selectedCourseId; ?>" class="btn btn-sm btn-primary">
						<i class="fas fa-plus"></i> New Topic
					</a>
				</div>
				<?php endif; ?>

				<?php if ($selectedCourseId): ?>
                <div class="row">
                    <div class="col-lg-5">
						<div class="card shadow">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-folder"></i> Categories</h6>
							</div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <p class="text-muted">No categories yet.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($categories as $cat): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="me-auto">
                                            <div class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($cat['description']); ?></small>
                                        </div>
                                        <div class="text-nowrap">
                                            <span class="badge bg-primary rounded-pill me-2"><?php echo $cat['topic_count']; ?> topics</span>
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCatModalUnified"
                                                data-category-id="<?php echo $cat['id']; ?>"
                                                data-category-name="<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>"
                                                data-category-description="<?php echo htmlspecialchars($cat['description'], ENT_QUOTES); ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this category? All topics under it will also be removed.');">
                                                <input type="hidden" name="delete_category" value="1">
                                                <input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">
                                                <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
						</div>
					</div>
                    <div class="col-lg-7">
						<div class="card shadow">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-plus"></i> Create Category</h6>
							</div>
							<div class="card-body">
								<form method="post">
									<input type="hidden" name="create_category" value="1">
									<input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">
									<div class="mb-3">
										<label class="form-label">Name</label>
										<input type="text" class="form-control" name="name" required>
									</div>
									<div class="mb-3">
										<label class="form-label">Description</label>
										<textarea class="form-control" name="description" rows="3"></textarea>
									</div>
									<button class="btn btn-primary"><i class="fas fa-save"></i> Create</button>
								</form>
							</div>
						</div>
                        </div>
                    </div>
                </div>

                <!-- Topics list -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-stream"></i> Topics</h6>
                                <form method="get" class="d-flex align-items-center">
                                    <input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">
                                    <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="0">All categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo $selectedCategoryId == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="card-body">
                                <?php if (empty($topics)): ?>
                                    <p class="text-muted">No topics found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Category</th>
                                                    <th>Posts</th>
                                                    <th>Created</th>
                                                    <th>Pinned</th>
                                                    <th>Locked</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topics as $t): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($t['title']); ?></td>
                                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($t['category_name']); ?></span></td>
                                                    <td><span class="badge bg-info"><?php echo (int)$t['post_count']; ?></span></td>
                                                    <td><?php echo formatDate($t['created_at']); ?></td>
                                                    <td><?php echo $t['is_pinned'] ? '<i class="fas fa-thumbtack text-warning"></i>' : '-'; ?></td>
                                                    <td><?php echo $t['is_locked'] ? '<i class="fas fa-lock text-danger"></i>' : '-'; ?></td>
                                                    <td class="text-end">
                                                        <a class="btn btn-sm btn-outline-primary" href="../forum/topic_view.php?id=<?php echo $t['id']; ?>"><i class="fas fa-eye"></i></a>
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                            data-bs-toggle="modal" data-bs-target="#editTopicModal"
                                                            data-topic-id="<?php echo $t['id']; ?>"
                                                            data-topic-title="<?php echo htmlspecialchars($t['title'], ENT_QUOTES); ?>"
                                                            data-topic-category="<?php echo $t['category_id']; ?>"
                                                            data-topic-pinned="<?php echo (int)$t['is_pinned']; ?>"
                                                            data-topic-locked="<?php echo (int)$t['is_locked']; ?>"
                                                            data-first-post="<?php echo htmlspecialchars($t['first_post_content'], ENT_QUOTES); ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this topic?')">
                                                            <input type="hidden" name="delete_topic" value="1">
                                                            <input type="hidden" name="topic_id" value="<?php echo $t['id']; ?>">
                                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                        </form>
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
                <?php endif; ?>

				<!-- Unified Edit Modal -->
				<div class="modal fade" id="editCatModalUnified" tabindex="-1">
					<div class="modal-dialog">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title"><i class="fas fa-edit"></i> Edit Category</h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
							</div>
							<form method="post">
								<div class="modal-body">
									<input type="hidden" name="update_category" value="1">
									<input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">
									<input type="hidden" name="category_id" id="editCatId" value="">
									<div class="mb-3">
										<label class="form-label">Name</label>
										<input type="text" class="form-control" name="name" id="editCatName" required>
									</div>
									<div class="mb-3">
										<label class="form-label">Description</label>
										<textarea class="form-control" name="description" id="editCatDesc" rows="3"></textarea>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
									<button type="submit" class="btn btn-primary">Save</button>
								</div>
							</form>
						</div>
					</div>
				</div>
                <!-- Edit Topic Modal -->
                <div class="modal fade" id="editTopicModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Topic</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="update_topic" value="1">
                                    <input type="hidden" name="topic_id" id="editTopicId" value="">
                                    <div class="mb-3">
                                        <label class="form-label">Title</label>
                                        <input type="text" class="form-control" name="title" id="editTopicTitle" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select name="category_id" id="editTopicCategory" class="form-select" required>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description (first post)</label>
                                        <textarea class="form-control" name="first_post_content" id="editTopicFirstPost" rows="5"></textarea>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="editTopicPinned" name="is_pinned">
                                        <label class="form-check-label" for="editTopicPinned">Pinned</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="editTopicLocked" name="is_locked">
                                        <label class="form-check-label" for="editTopicLocked">Locked</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
	(function(){
		var editModal = document.getElementById('editCatModalUnified');
		if (editModal) {
			editModal.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				var catId = button.getAttribute('data-category-id');
				var catName = button.getAttribute('data-category-name');
				var catDesc = button.getAttribute('data-category-description');
				document.getElementById('editCatId').value = catId || '';
				document.getElementById('editCatName').value = catName || '';
				document.getElementById('editCatDesc').value = catDesc || '';
			});
		}

		var editTopicModal = document.getElementById('editTopicModal');
		if (editTopicModal) {
			editTopicModal.addEventListener('show.bs.modal', function (event) {
				var button = event.relatedTarget;
				var id = button.getAttribute('data-topic-id');
				var title = button.getAttribute('data-topic-title');
				var categoryId = button.getAttribute('data-topic-category');
				var pinned = button.getAttribute('data-topic-pinned') === '1';
				var locked = button.getAttribute('data-topic-locked') === '1';
				document.getElementById('editTopicId').value = id || '';
				document.getElementById('editTopicTitle').value = title || '';
				document.getElementById('editTopicCategory').value = categoryId || '';
				document.getElementById('editTopicPinned').checked = pinned;
				document.getElementById('editTopicLocked').checked = locked;
				var firstPost = button.getAttribute('data-first-post') || '';
				document.getElementById('editTopicFirstPost').value = firstPost;
			});
		}
	})();
	</script>
</body>
</html>
