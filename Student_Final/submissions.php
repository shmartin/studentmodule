<?php
include('includes/session.php');
include('includes/db.php');

$student_id = $_SESSION['uid'];

$sql = "
SELECT
    d.did, d.dtitle, d.version,
    sl.timestamp,
    u.firstname, u.lastname,
    dr.reviewer_id, -- Select reviewer_id from document_reviewers to count all assigned
    de.status AS reviewer_status,
    de.feedback
FROM document d
JOIN document_details dd ON d.did = dd.did
LEFT JOIN submission_log sl ON d.did = sl.did AND d.version = sl.version AND sl.uid = dd.dauthor
LEFT JOIN document_reviewers dr ON d.did = dr.did AND d.version = dr.version
LEFT JOIN users u ON dr.reviewer_id = u.uid
LEFT JOIN document_evaluation de ON d.did = de.did AND d.version = de.version AND de.reviewer_id = dr.reviewer_id
WHERE dd.dauthor = ?
ORDER BY sl.timestamp DESC, d.did DESC, u.lastname ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$submissions = [];
while ($row = $result->fetch_assoc()) {
    $key = $row['did'] . '-' . $row['version'];
    if (!isset($submissions[$key])) {
        $submissions[$key] = [
            'did' => $row['did'],
            'dtitle' => $row['dtitle'],
            'version' => $row['version'],
            'timestamp' => $row['timestamp'],
            'reviewers' => [],
            'can_resubmit' => false // Initialize new flag
        ];
    }

    $reviewer_id = $row['reviewer_id'];
    if (!isset($submissions[$key]['reviewers'][$reviewer_id])) { // Prevent duplicate entries for the same reviewer
        $reviewer_name = $row['firstname'] ? $row['firstname'] . ' ' . $row['lastname'] : 'Not Assigned';
        $status = $row['reviewer_status'] ?? 'Pending'; // Default to Pending if no evaluation entry
        $feedback = $row['feedback'] ?? null;
        $has_feedback = !empty($feedback);

        $submissions[$key]['reviewers'][$reviewer_id] = [
            'name' => $reviewer_name,
            'status' => $status,
            'feedback' => $has_feedback,
            'reviewer_id' => $reviewer_id
        ];
    }
}

// After fetching all rows, iterate through submissions to apply the new logic
foreach ($submissions as $key => &$sub) { // Use & to modify the original array
    // Re-index the reviewers array to be a simple sequential array for iteration in HTML
    $sub['reviewers'] = array_values($sub['reviewers']);

    $total_assigned_reviewers = count($sub['reviewers']); // Total number of reviewers assigned
    $finalized_reviewers_count = 0; // Reviewers whose status is not 'Pending' or 'Under Review'
    $revision_required_count = 0;
    $approved_count = 0;
    $completed_count = 0;
    $rejected_count = 0; // Assuming 'rejected' means no resubmit

    foreach ($sub['reviewers'] as $reviewer) {
        $status = strtolower($reviewer['status']);

        if ($status !== 'pending' && $status !== 'under review') {
            $finalized_reviewers_count++;

            if ($status === 'revision required') {
                $revision_required_count++;
            } elseif ($status === 'approved') {
                $approved_count++;
            } elseif ($status === 'completed') {
                $completed_count++;
            } elseif ($status === 'rejected') {
                $rejected_count++;
            }
        }
    }

    // New primary condition: All assigned reviewers must have provided a final status.
    // That means the number of finalized reviewers must equal the total number of assigned reviewers.
    $all_assigned_reviewers_finalized = ($total_assigned_reviewers > 0 && $finalized_reviewers_count === $total_assigned_reviewers);

    // Determine if the resubmit link should be shown:
    // It should ONLY appear if:
    // 1. All assigned reviewers have provided a final status.
    // 2. No reviewer has explicitly 'rejected' the document.
    // 3. AND ( (all finalized reviewers said "revision required") OR (at least one said "approved" or "completed" AND all other *finalized* reviewers said "revision required") )
    // This logic ensures that if ALL reviewers are "approved" or "completed" (and no "revision required"), the link should NOT appear.
    $sub['can_resubmit'] = (
        $all_assigned_reviewers_finalized && // ALL assigned reviewers must have given a final status
        $rejected_count === 0 && // No rejections
        (
            // Scenario A: All finalized reviewers said "revision required"
            ($approved_count === 0 && $completed_count === 0 && $revision_required_count === $finalized_reviewers_count)
            ||
            // Scenario B: At least one said "approved" or "completed" AND all other FINALIZED reviewers said "revision required"
            (($approved_count > 0 || $completed_count > 0) && ($approved_count + $completed_count + $revision_required_count) === $finalized_reviewers_count)
        )
    );

    // If all reviews are finalized AND all are approved or completed AND no one required revision, then cannot resubmit.
    // This condition should effectively be covered by the main logic now, but remains as a safeguard.
    if ($all_assigned_reviewers_finalized && $rejected_count === 0 && $revision_required_count === 0 && ($approved_count + $completed_count) === $finalized_reviewers_count) {
        $sub['can_resubmit'] = false;
    }

}
unset($sub); // Unset the reference to avoid unexpected modifications

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Submissions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/dashboard-styles.css">
  <link rel="stylesheet" href="css/submissions-styles.css">
  <style>
    .status-badge {
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: bold;
      display: inline-block;
    }
    .pending {
      background-color: #ffcc00;
      color: #333;
    }
    .under-review {
      background-color: #2196F3;
      color: white;
    }
    .approved {
      background-color: #4CAF50;
      color: white;
    }
    .rejected {
      background-color: #f44336;
      color: white;
    }
    .resubmit-link {
      display: inline-block;
      margin-top: 5px;
      font-size: 0.9em;
      color: #d35400;
      font-weight: bold;
      text-decoration: none;
    }
    .resubmit-link:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<header>
  <div class="header-container">
    <img src="images/SAS Logo.png" alt="SAS Logo" class="logo">
    <div class="institution-text">
      <div class="page-title">STUDENT DASHBOARD</div>
      <div>SCHOOL OF ADVANCED STUDIES</div>
      <div class="university-name">Saint Louis University, Baguio City</div>
    </div>
  </div>
</header>

<div class="dashboard-container">
  <aside class="sidebar">
    <h3>Navigation</h3>
    <ul>
      <li><a href="dashboard.php">🏠 Dashboard</a></li>
      <li><a href="submissions.php" class="active">📄 My Submissions</a></li>
      <li><a href="upload_paper.php">➕ Submit New</a></li>
      <li><a href="logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <h2>My Submissions</h2>
    <table class="submissions-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Version</th>
          <th>Submitted On</th>
          <th>Reviewer</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($submissions)): ?>
          <tr>
            <td colspan="6" style="text-align:center; padding: 20px; color: #777;">
              You have no paper submissions at this time.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($submissions as $sub):
            $reviewer_count = count($sub['reviewers']);
          ?>
            <tr>
              <td rowspan="<?= $reviewer_count ?>"><?= htmlspecialchars($sub['dtitle']) ?>
              <?php if ($sub['can_resubmit']): ?>
              <br><a href="resubmit_document.php?did=<?= $sub['did'] ?>&version=<?= $sub['version'] ?>" class="resubmit-link">🔁 Resubmit</a>
              <?php endif; ?></td>
              <td rowspan="<?= $reviewer_count ?>">v<?= $sub['version'] ?></td>
              <td rowspan="<?= $reviewer_count ?>"><?= date('M d, Y h:i A', strtotime($sub['timestamp'])) ?></td>

              <td><?= htmlspecialchars($sub['reviewers'][0]['name']) ?></td>
              <td>
                <span class="status-badge <?= strtolower(str_replace(' ', '-', $sub['reviewers'][0]['status'])) ?>">
                  <?= htmlspecialchars($sub['reviewers'][0]['status']) ?>
                </span>
              </td>
              <td>
                <?php if ($sub['reviewers'][0]['feedback']): ?>
                  <a href="view_submission.php?did=<?= $sub['did'] ?>&version=<?= $sub['version'] ?>&reviewer=<?= $sub['reviewers'][0]['reviewer_id'] ?>">View Feedback</a>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
            <?php for ($i = 1; $i < $reviewer_count; $i++): ?>
              <tr>
                <td><?= htmlspecialchars($sub['reviewers'][$i]['name']) ?></td>
                <td>
                  <span class="status-badge <?= strtolower(str_replace(' ', '-', $sub['reviewers'][$i]['status'])) ?>">
                    <?= htmlspecialchars($sub['reviewers'][$i]['status']) ?>
                  </span>
                </td>
                <td>
                  <?php if ($sub['reviewers'][$i]['feedback']): ?>
                    <a href="view_submission.php?did=<?= $sub['did'] ?>&version=<?= $sub['version'] ?>&reviewer=<?= $sub['reviewers'][$i]['reviewer_id'] ?>">View Feedback</a>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endfor; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </main>
</div>

</body>
</html>







