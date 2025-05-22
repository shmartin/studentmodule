<?php
session_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}


include 'db.php';

if (!isset($_SESSION['reviewer_id'])) {
    die("Access denied.");
}

$reviewer_id = $_SESSION['reviewer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $did = $_POST['did'];
    $feedback = $_POST['feedback'];
    $status = $_POST['status'];

    $res = mysqli_query($conn, "SELECT dauthor FROM document_details WHERE did = '$did'");
    $row = mysqli_fetch_assoc($res);
    $dauthor = $row['dauthor'];

    $insert = "INSERT INTO document_evaluation (did, dauthor, feedback, status) 
               VALUES ('$did', '$dauthor', '$feedback', '$status')";
    $message = mysqli_query($conn, $insert) ? "Feedback submitted successfully." : "Error submitting feedback.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reviewer Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #667eea, #764ba2);
      min-height: 100vh;
      padding: 50px 15px;
      color: #333;
    }

    .card {
      border-radius: 15px;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
      margin-bottom: 30px;
    }

    .card-title {
      color: #764ba2;
    }

    textarea {
      resize: none;
    }

    .btn-primary {
      background-color: #764ba2;
      border: none;
    }

    .btn-primary:hover {
      background-color: #5f3a91;
    }

    .feedback-message {
      margin-bottom: 20px;
      padding: 10px 15px;
      border-radius: 8px;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="text-end mb-3">
  <a href="?logout=true" class="btn btn-danger">Logout</a>
</div>

  <h2 class="text-white mb-4 text-center">Reviewer Dashboard</h2>

  <?php if (isset($message)): ?>
    <div class="alert alert-info feedback-message text-center"><?= $message ?></div>
  <?php endif; ?>

  <?php
  $sql = "SELECT d.did, d.dtitle, d.file_path,
        (SELECT status FROM document_evaluation 
         WHERE did = d.did ORDER BY id DESC LIMIT 1) AS latest_status
        FROM document_reviewers r 
        JOIN document d ON r.did = d.did AND r.version = d.version 
        WHERE r.reviewer_id = '$reviewer_id'
        ORDER BY 
          CASE 
            WHEN (SELECT status FROM document_evaluation WHERE did = d.did ORDER BY id DESC LIMIT 1) = 'completed' THEN 1 
            ELSE 0 
          END, d.dtitle";


  $result = mysqli_query($conn, $sql);

  while ($row = mysqli_fetch_assoc($result)):
    $did = $row['did'];
    $latestStatus = $row['latest_status'];
    $feedbackQuery = mysqli_query($conn, "SELECT feedback FROM document_evaluation WHERE did = '$did' ORDER BY id DESC LIMIT 1");
    $feedbackRow = mysqli_fetch_assoc($feedbackQuery);
    $existingFeedback = $feedbackRow['feedback'] ?? null;
?>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title"><?= htmlspecialchars($row['dtitle']) ?></h5>
        <a href="http://localhost/revue/student/<?= $row['file_path'] ?>" target="_blank" class="btn btn-outline-dark btn-sm mb-3">View File</a>

        <?php if ($existingFeedback): ?>
          <div class="mb-3">
            <label class="form-label fw-bold text-success">Feedback:</label>
            <div class="p-3 bg-light border rounded">
              <?= nl2br(htmlspecialchars($existingFeedback)) ?><br>
              <span class="badge bg-<?= $latestStatus === 'completed' ? 'success' : 'info' ?> mt-2">
                <?= htmlspecialchars(ucwords($latestStatus)) ?>
              </span>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($latestStatus !== 'completed'): ?>
          <form method="POST">
            <input type="hidden" name="did" value="<?= $did ?>">
            <div class="mb-3">
              <textarea name="feedback" rows="3" class="form-control" placeholder="Enter feedback" required></textarea>
            </div>
            <div class="mb-3">
              <label class="me-3"><input type="radio" name="status" value="revision required" required> Revision Required</label>
              <label><input type="radio" name="status" value="completed"> Completed</label>
            </div>
            <button type="submit" class="btn btn-primary">Submit Feedback</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
<?php endwhile; ?>
    

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
