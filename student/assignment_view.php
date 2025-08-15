<?php
require_once '../config/database.php';

requireLogin();
if (!hasRole('student')) {
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

// Ensure assignment is in a course the student is enrolled in
$aStmt = $db->prepare('SELECT a.*, c.title AS course_title, c.course_code FROM assignments a JOIN courses c ON a.course_id = c.id JOIN enrollments e ON e.course_id = c.id AND e.student_id = ? AND e.status = "enrolled" WHERE a.id = ?');
$aStmt->execute([$_SESSION['user_id'], $assignmentId]);
$assignment = $aStmt->fetch(PDO::FETCH_ASSOC);
if (!$assignment) {
	header('Location: assignments.php');
	exit();
}

// Handle submission (text and/or file)
$submitErrors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
	$submissionText = trim($_POST['submission_text'] ?? '');
	$filePathDb = null;

	// File upload handling
	if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE) {
		if ($_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
			$uploadDir = __DIR__ . '/../uploads/assignments/';
			if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
			$original = basename($_FILES['submission_file']['name']);
			$ext = pathinfo($original, PATHINFO_EXTENSION);
			$safeName = 'assign_' . $assignmentId . '_stu_' . $_SESSION['user_id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? ('.' . $ext) : '');
			$target = $uploadDir . $safeName;
			if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target)) {
				$filePathDb = 'uploads/assignments/' . $safeName;
			} else {
				$submitErrors[] = 'Failed to upload file.';
			}
		} else {
			$submitErrors[] = 'File upload error.';
		}
	}

	if (empty($submitErrors)) {
		// Upsert submission (unique by assignment_id + student_id)
		$exists = $db->prepare('SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?');
		$exists->execute([$assignmentId, $_SESSION['user_id']]);
		if ($row = $exists->fetch(PDO::FETCH_ASSOC)) {
			$upd = $db->prepare('UPDATE assignment_submissions SET submission_text = ?, file_path = COALESCE(?, file_path), submitted_at = NOW() WHERE id = ?');
			$upd->execute([$submissionText !== '' ? $submissionText : null, $filePathDb, $row['id']]);
		} else {
			$ins = $db->prepare('INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path) VALUES (?,?,?,?)');
			$ins->execute([$assignmentId, $_SESSION['user_id'], $submissionText !== '' ? $submissionText : null, $filePathDb]);
		}
		header('Location: assignment_view.php?id=' . $assignmentId);
		exit();
	}
}

// Fetch my submission
$sStmt = $db->prepare('SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?');
$sStmt->execute([$assignmentId, $_SESSION['user_id']]);
$mySubmission = $sStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($assignment['title']); ?> - Assignment</title>
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
					<h1 class="h2"><i class="fas fa-folder-open"></i> <?php echo htmlspecialchars($assignment['title']); ?> <small class="text-muted"><?php echo htmlspecialchars($assignment['course_code'] . ' - ' . $assignment['course_title']); ?></small></h1>
					<div class="btn-toolbar">
						<a class="btn btn-sm btn-outline-secondary" href="assignments.php"><i class="fas fa-arrow-left"></i> Back</a>
					</div>
				</div>

				<div class="row g-4">
					<div class="col-md-7">
						<div class="card shadow mb-4">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary">Assignment Details</h6>
							</div>
							<div class="card-body">
								<p class="mb-1"><strong>Due:</strong> <?php echo $assignment['due_date'] ? formatDate($assignment['due_date']) : '<span class="text-muted">—</span>'; ?></p>
								<p class="mb-1"><strong>Max points:</strong> <?php echo htmlspecialchars($assignment['max_points']); ?></p>
								<p class="mt-3"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
							</div>
						</div>
					</div>
					<div class="col-md-5">
						<div class="card shadow mb-4">
							<div class="card-header py-3">
								<h6 class="m-0 font-weight-bold text-primary">Your Submission</h6>
							</div>
							<div class="card-body">
								<?php if (!empty($submitErrors)): ?>
									<div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars', $submitErrors)); ?></div>
								<?php endif; ?>
								<?php if ($mySubmission): ?>
									<p class="mb-1"><strong>Submitted at:</strong> <?php echo formatDate($mySubmission['submitted_at']); ?></p>
									<p class="mb-1"><strong>Grade:</strong> <?php echo $mySubmission['grade'] !== null ? htmlspecialchars($mySubmission['grade']) : '<span class="text-warning">Pending</span>'; ?></p>
									<p class="mb-3"><strong>Feedback:</strong> <?php echo $mySubmission['feedback'] ? htmlspecialchars($mySubmission['feedback']) : '<span class="text-muted">—</span>'; ?></p>
									<?php if ($mySubmission['file_path']): ?>
										<a class="btn btn-sm btn-outline-secondary" href="../<?php echo $mySubmission['file_path']; ?>" target="_blank"><i class="fas fa-download"></i> Download File</a>
									<?php endif; ?>
									<hr>
								<?php endif; ?>

								<form method="post" enctype="multipart/form-data">
									<input type="hidden" name="submit_assignment" value="1">
									<div class="mb-3">
										<label class="form-label">Write-up (optional)</label>
										<textarea name="submission_text" class="form-control" rows="4"><?php echo htmlspecialchars($mySubmission['submission_text'] ?? ''); ?></textarea>
									</div>
									<div class="mb-3">
										<label class="form-label">File (optional)</label>
										<input type="file" name="submission_file" class="form-control">
									</div>
									<div class="text-end">
										<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit</button>
									</div>
								</form>
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



