<?php
include('includes/session.php');
include('includes/db.php');

// Get student info
$student_id = $_SESSION['uid'];
$sql = "SELECT firstname, lastname FROM users WHERE uid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname);
$stmt->fetch();
$stmt->close();

// Fetch stats
function fetch_count($conn, $sql, $uid) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count;
}

$total = fetch_count($conn, "SELECT COUNT(DISTINCT did) FROM document_details WHERE dauthor = ?", $student_id);
$pending = fetch_count($conn, "SELECT COUNT(*) FROM document_evaluation e JOIN document_details dd ON e.did = dd.did WHERE dd.dauthor = ? AND (e.status = 'under review' OR e.status = '')", $student_id);
$pending_paper = fetch_count($conn, "SELECT COUNT(DISTINCT d.version) FROM document d JOIN document_details dd ON d.did = dd.did JOIN document_evaluation e ON d.did = e.did AND d.version = e.version WHERE dd.dauthor = ? AND (e.status = 'under review' OR e.status = '')", $student_id);
$revision = fetch_count($conn, "SELECT COUNT(*) FROM document_evaluation e JOIN document_details dd ON e.did = dd.did WHERE dd.dauthor = ? AND e.status = 'revision required'", $student_id);
$revision_paper = fetch_count($conn, "SELECT COUNT(DISTINCT d.version) FROM document d JOIN document_details dd ON d.did = dd.did JOIN document_evaluation e ON d.did = e.did AND d.version = e.version WHERE dd.dauthor = ? AND e.status = 'revision required'", $student_id);
$approved = fetch_count($conn, "
    SELECT COUNT(DISTINCT d.did)
    FROM document d
    JOIN document_details dd ON d.did = dd.did
    JOIN (
        SELECT did, MAX(version) AS latest_version
        FROM document
        GROUP BY did
    ) AS latest_docs ON d.did = latest_docs.did AND d.version = latest_docs.latest_version
    WHERE dd.dauthor = ?
    AND NOT EXISTS (
        SELECT 1
        FROM document_reviewers dr
        LEFT JOIN document_evaluation e ON e.did = dr.did AND e.version = dr.version AND e.reviewer_id = dr.reviewer_id
        WHERE dr.did = d.did AND dr.version = d.version
        AND (e.status IS NULL OR (e.status != 'completed' AND e.status != 'approved'))
    )
", $student_id);

// Fetch recent submissions
$recent_submissions = [];
$stmt = $conn->prepare("
  SELECT d.did, d.dtitle, d.version, sl.timestamp, e.status
  FROM document d
  JOIN document_details dd ON d.did = dd.did
  LEFT JOIN submission_log sl ON d.did = sl.did AND d.version = sl.version AND sl.uid = dd.dauthor
  LEFT JOIN document_evaluation e ON d.did = e.did AND d.version = e.version
  WHERE dd.dauthor = ?
  GROUP BY d.did, d.version
  ORDER BY sl.timestamp DESC
  LIMIT 3
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recent_submissions[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="css/dashboard-styles.css">
  <style>
    .summary-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .card {
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
      text-align: center;
    }
    .card h3 {
      margin: 10px 0;
      font-size: 2em;
      color: #004080;
    }
    .card p {
      color: #555;
    }
    .recent-activity, .notifications {
      background: white;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 30px;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    .section-title {
      margin-bottom: 15px;
      font-size: 1.3em;
      border-bottom: 2px solid #004080;
      padding-bottom: 5px;
      color: #004080;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      text-align: left;
      padding: 10px;
      border-bottom: 1px solid #ddd;
    }
    .notifications ul {
      list-style: none;
      padding: 0;
    }
    .notifications li {
      margin: 10px 0;
      padding: 10px;
      background: #f1f5fb;
      border-left: 5px solid #004080;
    }
    .quick-links {
      margin-top: 20px;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    .quick-links a {
      background: #004080;
      color: white;
      padding: 10px 20px;
      text-decoration: none;
      border-radius: 6px;
    }
    .quick-links a:hover {
      background: #002b5e;
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
    <h3>Welcome, <?= htmlspecialchars($firstname) ?> <?= htmlspecialchars($lastname) ?></h3>
    <nav>
      <ul>
        <li><a href="dashboard.php" class="active">🏠 Dashboard</a></li>
        <li><a href="submissions.php">📄 My Submissions</a></li>
        <li><a href="upload_paper.php">➕ Submit New</a></li>
        <li><a href="logout.php">🚪 Logout</a></li>
      </ul>
    </nav>
  </aside>

  <main class="main-content">
    <h2>Overview</h2>

    <div class="summary-cards">
      <div class="card"><h3><?= $total ?></h3><p>Total Paper Submissions</p></div>
      <div class="card"><h3><?= $pending ?></h3><p>Pending Reviews</p></div>
      <div class="card"><h3><?= $revision ?></h3><p>Revision Required</p></div>
      <div class="card"><h3><?= $approved ?></h3><p>Approved Papers</p></div>
    </div>

    <div class="recent-activity">
      <div class="section-title">📄 Recent Submissions</div>
      <table>
        <thead><tr><th>Title</th><th>Version</th><th>Date Submitted</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($recent_submissions)): ?>
            <tr>
              <td colspan="4" style="text-align:center; padding: 20px; color: #777;">
                You have no paper submissions at this time.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($recent_submissions as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['dtitle']) ?></td>
                <td>v<?= $r['version'] ?></td>
                <td><?= date('M d, Y h:i A', strtotime($r['timestamp'])) ?></td>
                <td><?= $r['status'] ?? 'Pending' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="notifications">
      <div class="section-title">🔔 Notifications</div>
      <ul>
        <?php if ($revision > 0): ?>
          <li>🔁 You have at least <?= $revision_paper ?> paper(s) requiring revision.</li>
        <?php endif; ?>
        <?php if ($pending > 0): ?>
          <li>⏳ <?= $pending_paper ?> paper(s) are still under review.</li>
        <?php endif; ?>
      </ul>
    </div>

   </main>
</div>

</body>
</html>


