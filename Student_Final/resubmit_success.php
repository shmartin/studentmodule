
<?php
include('includes/session.php');
$did = $_GET['did'] ?? '';
$version = $_GET['version'] ?? '';
$message = $_SESSION['upload_message'] ?? 'Your document has been resubmitted.';
unset($_SESSION['upload_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Resubmission Successful</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/dashboard-styles.css">
  <style>
    .success-container {
      max-width: 600px;
      margin: 60px auto;
      padding: 30px;
      background: #fff;
      border-radius: 8px;
      text-align: center;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .success-container h2 {
      color: #27ae60;
      margin-bottom: 20px;
    }

    .success-container p {
      font-size: 1.1em;
      margin-bottom: 30px;
    }

    .success-container a {
      text-decoration: none;
      padding: 10px 20px;
      background-color: #004080;
      color: white;
      border-radius: 5px;
      font-weight: bold;
    }

    .success-container a:hover {
      background-color: #002b5e;
    }
  </style>
</head>
<body>

<header>
  <div class="header-container">
    <img src="images/SAS Logo.png" alt="SAS Logo" class="logo">
    <div class="institution-text">
      <div class="page-title">DOCUMENT RESUBMISSION</div>
      <div>SCHOOL OF ADVANCED STUDIES</div>
      <div class="university-name">Saint Louis University, Baguio City</div>
    </div>
  </div>
</header>

<div class="success-container">
  <h2>🎉 Resubmission Successful!</h2>
  <p><?= htmlspecialchars($message) ?></p>
  <a href="submissions.php">Back to My Submissions</a>
</div>

</body>
</html>
