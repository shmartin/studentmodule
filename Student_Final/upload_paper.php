<?php
include('includes/session.php');
include('includes/db.php');

// Fetch programs for the dropdown
$programs = [];
$prog_result = $conn->query("SELECT pid, description FROM program ORDER BY description ASC");
while ($row = $prog_result->fetch_assoc()) {
    $programs[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Submit New Paper</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/dashboard-styles.css">
  <link rel="stylesheet" href="css/upload-styles.css">
</head>
<body>

<header>
  <div class="header-container">
    <img src="images/SAS Logo.png" alt="SAS Logo" class="logo">
    <div class="institution-text">
      <div class="page-title">SUBMIT NEW PAPER</div>
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
      <li><a href="submissions.php">📄 My Submissions</a></li>
      <li><a href="upload_paper.php" class="active">➕ Submit New</a></li>
      <li><a href="logout.php">🚪 Logout</a></li>
    </ul>
  </aside>

  <main class="main-content">
    <h2>Submit New Paper</h2>
    <form action="submit_handler.php" method="POST" enctype="multipart/form-data" class="upload-form">
      <label>Document Title</label>
      <input type="text" name="dtitle" required>

      <label>Adviser Name</label>
      <input type="text" name="dadviser" required>

      <label>Academic Program</label>
      <select name="program" required>
        <option value="">-- Select Program --</option>
        <?php foreach ($programs as $p): ?>
          <option value="<?= $p['pid'] ?>"><?= htmlspecialchars($p['description']) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Upload File (PDF or DOCX)</label>
      <input type="file" name="paper_file" accept=".pdf,.docx" required>

      <button type="submit">Submit Paper</button>
    </form>
  </main>
</div>

</body>
</html>
