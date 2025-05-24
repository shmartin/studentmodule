<?php
include('includes/session.php');
include('includes/db.php');

$student_uid = $_SESSION['uid'];
$upload_message = "";
$error_message = "";

$did = null;
$current_version = null;
$document_title = "";
$adviser_name = "";
$program_id = null;
$program_description = "";
$current_file_path = "";

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['did']) && isset($_GET['version'])) {
        $did = intval($_GET['did']);
        $current_version = intval($_GET['version']);

        $sql = "SELECT d.dtitle, d.file_path, dd.dadviser, dd.program, p.description AS program_description
                FROM document d
                JOIN document_details dd ON d.did = dd.did
                LEFT JOIN program p ON dd.program = p.pid
                WHERE d.did = ? AND d.version = ? AND dd.dauthor = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $did, $current_version, $student_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($doc_data = $result->fetch_assoc()) {
            $document_title = htmlspecialchars($doc_data['dtitle']);
            $adviser_name = htmlspecialchars($doc_data['dadviser']);
            $program_id = $doc_data['program'];
            $program_description = htmlspecialchars($doc_data['program_description'] ?? 'N/A');
            $current_file_path = htmlspecialchars($doc_data['file_path']);
        } else {
            $error_message .= "Document not found or permission denied.";
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $did = intval($_POST['did']);
    $current_version = intval($_POST['current_version']);
    $document_title = trim($_POST['paperTitle']);
    $adviser_name = trim($_POST['adviserName']);
    $program_id = intval($_POST['programDegree']);
    $uploadOk = 1;
    $upload_dir = "uploads/" . $student_uid . "/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES["paperFile"]) && $_FILES["paperFile"]["error"] === UPLOAD_ERR_OK) {
        $original_file_name = basename($_FILES["paperFile"]["name"]);
        $fileType = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
        $allowed_types = ["pdf", "doc", "docx", "rtf"];
        if (!in_array($fileType, $allowed_types)) {
            $error_message .= "Invalid file type. ";
            $uploadOk = 0;
        }
        if ($_FILES["paperFile"]["size"] > 10000000) {
            $error_message .= "File too large. ";
            $uploadOk = 0;
        }

        if ($uploadOk == 1) {
            $stmt = $conn->prepare("SELECT MAX(version) FROM document WHERE did = ?");
            $stmt->bind_param("i", $did);
            $stmt->execute();
            $stmt->bind_result($max_version);
            $stmt->fetch();
            $new_version = $max_version + 1;
            $stmt->close();

            $safe_file_name = preg_replace("/[^a-zA-Z0-9_\-.]/", "", pathinfo($original_file_name, PATHINFO_FILENAME));
            $target_file = $upload_dir . $safe_file_name . "_v" . $new_version . "." . $fileType;

            if (move_uploaded_file($_FILES["paperFile"]["tmp_name"], $target_file)) {
                // Insert new version
                $stmt = $conn->prepare("INSERT INTO document (did, dtitle, file_path, version) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $did, $document_title, $target_file, $new_version);
                $stmt->execute();
                $stmt->close();

                // Log submission
                $stmt = $conn->prepare("INSERT INTO submission_log (did, version, uid, timestamp) VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->bind_param("iii", $did, $new_version, $student_uid);
                $stmt->execute();
                $stmt->close();

                // Copy reviewers
                $stmt = $conn->prepare("
                    INSERT INTO document_reviewers (did, version, reviewer_id, review_status, review_date, assigned_date, assigned_by)
                    SELECT ?, ?, reviewer_id, review_status, CURRENT_TIMESTAMP, assigned_date, assigned_by
                    FROM document_reviewers WHERE did = ? AND version = ?
                ");
                $stmt->bind_param("iiii", $did, $new_version, $did, $current_version);
                $stmt->execute();
                $stmt->close();

                // Insert updated evaluations for new version
                $stmt = $conn->prepare("SELECT reviewer_id, dauthor, feedback, status FROM document_evaluation WHERE did = ? AND version = ?");
                $stmt->bind_param("ii", $did, $current_version);
                $stmt->execute();
                $result = $stmt->get_result();

                while ($row = $result->fetch_assoc()) {
                    $reviewer_id = $row['reviewer_id'];
                    $dauthor = $row['dauthor'];
                    $status = $row['status'];
                    $feedback = $row['feedback'];

                    if (strtolower($status) === 'revision required') {
                        $new_status = 'under review';
                        $new_feedback = '';
                    } else {
                        $new_status = $status;
                        $new_feedback = $feedback;
                    }

                    $stmt_insert = $conn->prepare("
                        INSERT INTO document_evaluation (did, dauthor, feedback, status, reviewer_id, version)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_insert->bind_param("isssii", $did, $dauthor, $new_feedback, $new_status, $reviewer_id, $new_version);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt->close();

                // Update status for previous version
                $stmt = $conn->prepare("
                    UPDATE document_evaluation 
                    SET status = 'Successfully resubmitted!' 
                    WHERE did = ? AND version = ? AND status = 'revision required'
                ");
                $stmt->bind_param("ii", $did, $current_version);
                $stmt->execute();
                $stmt->close();

                $_SESSION['upload_message'] = "Your document has been successfully resubmitted as version $new_version.";
                header("Location: resubmit_success.php?did=$did&version=$new_version");
                exit;
            } else {
                $error_message .= "File upload failed.";
            }
        }
    } else {
        $error_message .= "Please upload a valid file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Resubmit Document</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/dashboard-styles.css">
  <style>
    .resubmit-container {
      background: #fff;
      max-width: 700px;
      margin: 40px auto;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .resubmit-container h2 {
      color: #004080;
      margin-bottom: 20px;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      font-weight: bold;
      display: block;
      margin-bottom: 8px;
    }
    .form-group input,
    .form-group input[type="file"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .form-group button {
      background-color: #004080;
      color: white;
      border: none;
      padding: 12px 24px;
      font-size: 1rem;
      border-radius: 6px;
      cursor: pointer;
    }
    .form-errors {
      color: red;
      margin-bottom: 20px;
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

<div class="resubmit-container">
  <h2>Resubmit Paper</h2>
  <?php if (!empty($error_message)): ?>
    <div class="form-errors"><?= $error_message ?></div>
  <?php endif; ?>

  <form action="resubmit_document.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="did" value="<?= $did ?>">
    <input type="hidden" name="current_version" value="<?= $current_version ?>">
    <input type="hidden" name="paperTitle" value="<?= $document_title ?>">
    <input type="hidden" name="adviserName" value="<?= $adviser_name ?>">
    <input type="hidden" name="programDegree" value="<?= $program_id ?>">

    <div class="form-group">
      <label>Paper Title</label>
      <input type="text" value="<?= $document_title ?>" readonly>
    </div>

    <div class="form-group">
      <label>Adviser</label>
      <input type="text" value="<?= $adviser_name ?>" readonly>
    </div>

    <div class="form-group">
      <label>Program</label>
      <input type="text" value="<?= $program_description ?>" readonly>
    </div>

    <div class="form-group">
      <label>Upload Revised File (PDF, DOCX, max 10MB)</label>
      <input type="file" name="paperFile" required accept=".pdf,.doc,.docx,.rtf">
    </div>

    <div class="form-group">
      <button type="submit">Submit Revision</button>
    </div>
  </form>
</div>

</body>
</html>



