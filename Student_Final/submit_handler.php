<?php
include('includes/session.php');
include('includes/db.php');

$student_uid = $_SESSION['student_id'];
$upload_dir = "uploads/" . $student_uid . "/";

if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get and sanitize form inputs
$dtitle = trim($_POST['dtitle']);
$dadviser = trim($_POST['dadviser']);
$program = intval($_POST['program']);
$version = 1;

// ✅ Check for duplicate title by the same student
$check_sql = "
    SELECT d.did
    FROM document d
    JOIN document_details dd ON d.did = dd.did
    WHERE dd.dauthor = ? AND d.dtitle = ?
";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("is", $student_uid, $dtitle);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo "<p style='color:red; font-family:sans-serif;'>You have already submitted a document with the same title. Please choose a different title.</p>";
    echo "<a href='upload_paper.php'>Go Back</a>";
    exit;
}
$check_stmt->close();

// Handle file upload
if (isset($_FILES['paper_file']) && $_FILES['paper_file']['error'] == 0) {
    $original_file_name = basename($_FILES["paper_file"]["name"]);
    $fileType = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
    $allowed_types = ["pdf", "doc", "docx", "rtf"];
    if (!in_array($fileType, $allowed_types)) {
        echo "Invalid file type. Only PDF, DOC, DOCX, or RTF allowed.";
        exit;
    }
    if ($_FILES["paperFile"]["size"] > 10000000) { // 10MB limit
            $error_message .= "File too large. Maximum file size is 10MB. ";
            $uploadOk = 0;
        }

    // Sanitize the filename for saving
    $safe_file_name = preg_replace("/[^a-zA-Z0-9_\-.]/", "", pathinfo($original_file_name, PATHINFO_FILENAME));
    $target_file = $upload_dir . $safe_file_name . "_v" . $version . "." . $fileType;

    if (!move_uploaded_file($_FILES['paper_file']['tmp_name'], $target_file)) {
        echo "File upload failed.";
        exit;
    }
} else {
    echo "No file uploaded or an upload error occurred.";
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Insert into document
    $stmt = $conn->prepare("INSERT INTO document (dtitle, file_path, version) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $dtitle, $target_file, $version);
    $stmt->execute();
    $did = $stmt->insert_id;
    $stmt->close();

    // Insert into document_details
    $stmt = $conn->prepare("INSERT INTO document_details (did, dauthor, dadviser, program) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $did, $student_uid, $dadviser, $program);
    $stmt->execute();
    $stmt->close();

    // Insert into submission_log
    $stmt = $conn->prepare("INSERT INTO submission_log (did, version, uid) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $did, $version, $student_uid);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    header("Location: submissions.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    echo "Error occurred: " . $e->getMessage();
    exit;
}
?>


