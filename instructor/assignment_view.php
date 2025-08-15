<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('instructor')) {
	header('Location: ../auth/login.php');
	exit();
}

$database = new Database();
$db = $database->getConnection();

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($assignmentId <= 0) {
	header('Location: assignments.php');
	exit();
}

// Ensure assignment belongs to instructor
$aStmt = $db->prepare('SELECT a.*, c.title AS course_title, c.id as course_id FROM assignments a JOIN courses c ON a.course_id = c.id WHERE a.id = ? AND c.instructor_id = ?');
$aStmt->execute([$assignmentId, $_SESSION['user_id']]);
$assignment = $aStmt->fetch(PDO::FETCH_ASSOC);
if (!$assignment) {
	header('Location: assignments.php');
	exit();
}

// Handle grading submission (single student)
$gradeErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
	$submissionId = (int)($_POST['submission_id'] ?? 0);
	$grade = isset($_POST['grade']) ? (float)$_POST['grade'] : null;
	$feedback = trim($_POST['feedback'] ?? '');

	if ($submissionId <= 0) { $gradeErrors[] = 'Invalid submission.'; }
	if ($grade === null || $grade < 0) { $gradeErrors[] = 'Grade must be a non-negative number.'; }
	if ($grade > (float)$assignment['max_points']) { $gradeErrors[] = 'Grade cannot exceed max points.'; }

	// Ensure submission belongs to this assignment
	if (empty($gradeErrors)) {
		$check = $db->prepare('SELECT id FROM assignment_submissions WHERE id = ? AND assignment_id = ?');
		$check->execute([$submissionId, $assignmentId]);
		if (!$check->fetch()) { $gradeErrors[] = 'Submission not found for this assignment.'; }
	}

	if (empty($gradeErrors)) {
		$upd = $db->prepare('UPDATE assignment_submissions SET grade = ?, feedback = ?, graded_by = ?, graded_at = NOW() WHERE id = ?');
		$upd->execute([$grade, $feedback, $_SESSION['user_id'], $submissionId]);
		header('Location: assignment_view.php?id=' . $assignmentId);
		exit();
	}
}

// Fetch submissions
$sStmt = $db->prepare('SELECT s.*, u.first_name, u.last_name FROM assignment_submissions s JOIN users u ON s.student_id = u.id WHERE s.assignment_id = ? ORDER BY s.submitted_at DESC');
$sStmt->execute([$assignmentId]);
$submissions = $sStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Assignment Submissions</title>
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
					<h1 class="h2"><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($assignment['title']); ?> <small class="text-muted">(<?php echo htmlspecialchars($assignment['course_title']); ?>)</small></h1>
					<div class="btn-toolbar">
						<a class="btn btn-sm btn-outline-secondary" href="assignments.php"><i class="fas fa-arrow-left"></i> Back</a>
					</div>
				</div>

				<div class="card shadow mb-4">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary">Details</h6>
					</div>
					<div class="card-body">
						<p class="mb-1"><strong>Due:</strong> <?php echo $assignment['due_date'] ? formatDate($assignment['due_date']) : '<span class="text-muted">—</span>'; ?></p>
						<p class="mb-1"><strong>Max points:</strong> <?php echo htmlspecialchars($assignment['max_points']); ?></p>
						<p class="mt-3"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
					</div>
				</div>

				<div class="card shadow">
					<div class="card-header py-3 d-flex justify-content-between align-items-center">
						<h6 class="m-0 font-weight-bold text-primary">Submissions</h6>
					</div>
					<div class="card-body">
						<?php if (!empty($gradeErrors)): ?>
							<div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $gradeErrors)); ?></div>
						<?php endif; ?>
						<?php if (empty($submissions)): ?>
							<p class="text-muted">No submissions yet.</p>
						<?php else: ?>
							<div class="table-responsive">
								<table class="table table-hover align-middle">
									<thead>
										<tr>
											<th>Student</th>
											<th>Submitted</th>
											<th>File</th>
											<th>Grade</th>
											<th>Feedback</th>
											<th></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($submissions as $s): ?>
										<tr>
											<td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
											<td><?php echo formatDate($s['submitted_at']); ?></td>
											<td>
												<?php if (!empty($s['file_path'])): ?>
													<a class="btn btn-sm btn-outline-secondary" href="../<?php echo $s['file_path']; ?>" target="_blank"><i class="fas fa-download"></i> Download</a>
												<?php else: ?>
													<span class="text-muted">No file</span>
												<?php endif; ?>
											</td>
											<td><?php echo $s['grade'] !== null ? htmlspecialchars($s['grade']) : '<span class="text-warning">Ungraded</span>'; ?></td>
											<td><?php echo $s['feedback'] ? htmlspecialchars($s['feedback']) : '<span class="text-muted">—</span>'; ?></td>
											<td class="text-end">
												<button type="button" class="btn btn-sm btn-primary" 
													data-bs-toggle="modal" 
													data-bs-target="#gradeModal"
													data-submission-id="<?php echo (int)$s['id']; ?>"
													data-student-name="<?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name'], ENT_QUOTES); ?>"
													data-grade="<?php echo $s['grade'] !== null ? htmlspecialchars($s['grade']) : ''; ?>"
													data-feedback="<?php echo htmlspecialchars($s['feedback'] ?? '', ENT_QUOTES); ?>"
												>
													<i class="fas fa-pen"></i> Grade
												</button>
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

	<div class="modal fade" id="gradeModal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title"><i class="fas fa-clipboard-check"></i> Grade Submission <small class="text-muted" id="gradeStudent"></small></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
				</div>
				<form method="post">
					<div class="modal-body">
						<input type="hidden" name="grade_submission" value="1">
						<input type="hidden" name="submission_id" id="gradeSubmissionId" value="">
						<div class="mb-3">
							<label class="form-label">Grade (0 - <?php echo htmlspecialchars($assignment['max_points']); ?>)</label>
							<input type="number" name="grade" id="gradeValue" step="0.01" min="0" max="<?php echo htmlspecialchars($assignment['max_points']); ?>" class="form-control" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Feedback</label>
							<textarea name="feedback" id="gradeFeedback" class="form-control" rows="4"></textarea>
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

	<script>
	var gradeModal = document.getElementById('gradeModal');
	if (gradeModal) {
		gradeModal.addEventListener('show.bs.modal', function (event) {
			var button = event.relatedTarget;
			if (!button) return;
			var submissionId = button.getAttribute('data-submission-id') || '';
			var studentName = button.getAttribute('data-student-name') || '';
			var grade = button.getAttribute('data-grade') || '';
			var feedback = button.getAttribute('data-feedback') || '';
			document.getElementById('gradeSubmissionId').value = submissionId;
			document.getElementById('gradeValue').value = grade;
			document.getElementById('gradeFeedback').value = feedback;
			document.getElementById('gradeStudent').textContent = studentName ? ('• ' + studentName) : '';
		});
	}
	</script>
</body>
</html>
