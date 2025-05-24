<?php
include('includes/session.php');
include('includes/db.php');

$did = $_GET['did'];
$version = $_GET['version'];
$reviewer_id = $_GET['reviewer'] ?? null;

$sql = "SELECT d.dtitle, de.feedback, de.status, u.firstname, u.lastname
        FROM document d
        LEFT JOIN document_evaluation de ON d.did = de.did AND d.version = de.version
        LEFT JOIN users u ON de.reviewer_id = u.uid
        WHERE d.did = ? AND d.version = ?";

if ($reviewer_id) {
    $sql .= " AND de.reviewer_id = ?";
}

$stmt = $conn->prepare($sql);
if ($reviewer_id) {
    $stmt->bind_param("iii", $did, $version, $reviewer_id);
} else {
    $stmt->bind_param("ii", $did, $version);
}
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Submission</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/dashboard-styles.css">
  <style>
    .view-container {
      background-color: #fff;
      padding: 30px;
      border-radius: 8px;
      max-width: 700px;
      margin: 30px auto;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .view-container h2 {
      margin-bottom: 15px;
      font-size: 1.5em;
      color: #004080;
    }

    .view-container p {
      font-size: 1.1em;
      margin-bottom: 15px;
      line-height: 1.6;
    }

    .label {
      font-weight: bold;
      color: #333;
    }

    .status-badge {
      padding: 5px 12px;
      border-radius: 20px;
      font-weight: bold;
      font-size: 0.9rem;
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

    .back-link {
      display: inline-block;
      margin-top: 20px;
      text-decoration: none;
      color: #004080;
      font-weight: bold;
    }

    .back-link:hover {
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

<div class="view-container">
  <h2><?= htmlspecialchars($doc['dtitle']) ?></h2>

  <?php
    $status = $doc['status'] ?? 'Pending';
    $status_class = match(strtolower($status)) {
      'pending' => 'pending',
      'under review' => 'under-review',
      'approved' => 'approved',
      'rejected' => 'rejected',
      default => ''
    };
    $reviewer_name = ($doc['firstname']) ? $doc['firstname'] . ' ' . $doc['lastname'] : 'N/A';
  ?>

  <p><span class="label">Status:</span> <span class="status-badge <?= $status_class ?>"><?= $status ?></span></p>
  <p><span class="label">Reviewer:</span> <?= htmlspecialchars($reviewer_name) ?></p>
  <p><span class="label">Feedback:</span><br><?= nl2br($doc['feedback'] ?? 'No feedback yet') ?></p>

  <a href="submissions.php" class="back-link">← Back to My Submissions</a>
</div>

</body>
</html>


