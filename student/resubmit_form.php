<?php
// Start the session
session_start();

// Check if the user is logged in AND is a student, otherwise redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("location: login.php");
    exit;
}

// Include database connection
include 'db_connect.php';

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

// --- Handle GET request (display form) ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['did']) && isset($_GET['version'])) {
        $did = intval($_GET['did']);
        $current_version = intval($_GET['version']);

        // Fetch current document details to pre-populate the form
        $sql_fetch_doc = "
            SELECT
                d.dtitle,
                d.file_path,
                dd.dadviser,
                dd.program,
                p.description AS program_description
            FROM
                document d
            JOIN
                document_details dd ON d.did = dd.did
            LEFT JOIN
                program p ON dd.program = p.pid
            WHERE
                d.did = ? AND d.version = ? AND dd.dauthor = ?;
        ";

        if ($conn) {
            if ($stmt_fetch_doc = $conn->prepare($sql_fetch_doc)) {
                $stmt_fetch_doc->bind_param("iii", $did, $current_version, $student_uid);
                if ($stmt_fetch_doc->execute()) {
                    $result_fetch_doc = $stmt_fetch_doc->get_result();
                    if ($doc_data = $result_fetch_doc->fetch_assoc()) {
                        $document_title = htmlspecialchars($doc_data['dtitle']);
                        $adviser_name = htmlspecialchars($doc_data['dadviser']);
                        $program_id = $doc_data['program'];
                        $program_description = htmlspecialchars($doc_data['program_description'] ?? 'N/A');
                        $current_file_path = htmlspecialchars($doc_data['file_path']);
                    } else {
                        $error_message .= "Document not found or you do not have permission to resubmit it.";
                    }
                    $result_fetch_doc->free();
                } else {
                    error_log("Error executing fetch doc query: " . $stmt_fetch_doc->error);
                    $error_message .= "Database error fetching document details.";
                }
                $stmt_fetch_doc->close();
            } else {
                error_log("Error preparing fetch doc query: " . $conn->error);
                $error_message .= "Database error preparing document fetch.";
            }
        } else {
            $error_message .= "Database connection not available.";
        }
    } else {
        $error_message .= "Invalid document ID or version provided for resubmission.";
    }
}

// --- Handle POST request (form submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $did = intval($_POST['did']);
    $current_version = intval($_POST['current_version']); // The version being resubmitted (old version)
    $document_title = trim($_POST['paperTitle']); // Title from the hidden field
    $adviser_name = trim($_POST['adviserName']); // Adviser from the hidden field
    $program_id = intval($_POST['programDegree']); // Program from the hidden field

    $uploadOk = 1; // Flag for overall success

    // --- File Upload Handling ---
    if (empty($_FILES["paperFile"]["name"])) {
        $error_message .= "Please select a new paper file to resubmit. ";
        $uploadOk = 0;
    } else {
        $target_dir = "uploads/" . $student_uid . "/";
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                $error_message .= "Error creating upload directory. ";
                $uploadOk = 0;
            }
        }

        if ($uploadOk == 1) {
            if (isset($_FILES["paperFile"]) && $_FILES["paperFile"]["error"] === UPLOAD_ERR_OK) {
                $original_file_name = basename($_FILES["paperFile"]["name"]);
                $fileType = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));

                $allowed_types = array("pdf", "doc", "docx", "rtf");
                if (!in_array($fileType, $allowed_types)) {
                    $error_message .= "Sorry, only " . implode(", ", $allowed_types) . " files are allowed. ";
                    $uploadOk = 0;
                }

                if ($_FILES["paperFile"]["size"] > 10000000) { // 10MB limit
                    $error_message .= "Sorry, your file is too large (max 10MB). ";
                    $uploadOk = 0;
                }

                // Determine the next version
                $new_version = 1; // Default if no previous versions exist (shouldn't happen for resubmit)
                if ($conn) {
                    $sql_max_version = "SELECT MAX(version) FROM document WHERE did = ?";
                    if ($stmt_max_version = $conn->prepare($sql_max_version)) {
                        $stmt_max_version->bind_param("i", $did);
                        if ($stmt_max_version->execute()) {
                            $stmt_max_version->bind_result($max_version_result);
                            if ($stmt_max_version->fetch() && $max_version_result !== null) {
                                $new_version = $max_version_result + 1;
                            }
                        } else {
                            error_log("Error executing max version query: " . $stmt_max_version->error);
                            $error_message .= "An internal error occurred while determining new version.";
                            $uploadOk = 0;
                        }
                        $stmt_max_version->close();
                    } else {
                        error_log("Error preparing max version query: " . $conn->error);
                        $error_message .= "An internal error occurred while preparing version query.";
                        $uploadOk = 0;
                    }
                } else {
                    $error_message .= "Database connection not available for version determination.";
                    $uploadOk = 0;
                }

                // Construct new file path with new version in name to avoid overwriting
                $safe_file_name = preg_replace("/[^a-zA-Z0-9_\-.]/", "", pathinfo($original_file_name, PATHINFO_FILENAME));
                $target_file = $target_dir . $safe_file_name . "_v" . $new_version . "." . $fileType;


            } else {
                if (isset($_FILES["paperFile"]) && $_FILES["paperFile"]["error"] !== UPLOAD_ERR_NO_FILE) {
                    $error_message .= "File upload error: " . $_FILES["paperFile"]["error"] . ". ";
                }
                $uploadOk = 0;
            }
        }
    }

    // If all checks pass, proceed with file move and database operations
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["paperFile"]["tmp_name"], $target_file)) {
            // --- Database Insertion/Updates ---

            // 1. Insert new document version into 'document' table
            $sql_insert_doc = "INSERT INTO document (did, dtitle, file_path, version) VALUES (?, ?, ?, ?)";
            if ($conn && $stmt_insert_doc = $conn->prepare($sql_insert_doc)) {
                $stmt_insert_doc->bind_param("issi", $did, $document_title, $target_file, $new_version);
                if (!$stmt_insert_doc->execute()) {
                    error_log("Error inserting new document version: " . $stmt_insert_doc->error);
                    $error_message .= "Error saving new document version.";
                    $uploadOk = 0;
                }
                $stmt_insert_doc->close();
            } else {
                error_log("Error preparing insert doc query: " . $conn->error);
                $error_message .= "Database error preparing document insertion.";
                $uploadOk = 0;
            }

            // 2. Insert into 'submission_log' table
            if ($uploadOk == 1 && $conn) {
                // Using the schema from your 'create_submission_log_table_recheck' Canvas
                $sql_log_submission = "INSERT INTO submission_log (did, version, uid, timestamp) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
                if ($stmt_log_submission = $conn->prepare($sql_log_submission)) {
                    $stmt_log_submission->bind_param("iii", $did, $new_version, $student_uid);
                    if (!$stmt_log_submission->execute()) {
                        error_log("Error logging resubmission: " . $stmt_log_submission->error);
                        $error_message .= "Error logging resubmission.";
                        $uploadOk = 0;
                    }
                    $stmt_log_submission->close();
                } else {
                    error_log("Error preparing log submission query: " . $conn->error);
                    $error_message .= "Database error preparing submission log.";
                    $uploadOk = 0;
                }
            }

            // 3. Re-assign reviewers to the new version and set initial status in document_evaluation
            // Fetch reviewers from the previous version
            $reviewers_to_reassign = [];
            if ($uploadOk == 1 && $conn) {
                $sql_fetch_reviewers = "SELECT reviewer_id FROM document_reviewers WHERE did = ? AND version = ?";
                if ($stmt_fetch_reviewers = $conn->prepare($sql_fetch_reviewers)) {
                    $stmt_fetch_reviewers->bind_param("ii", $did, $current_version);
                    if ($stmt_fetch_reviewers->execute()) {
                        $result_reviewers = $stmt_fetch_reviewers->get_result();
                        while ($row = $result_reviewers->fetch_assoc()) {
                            $reviewers_to_reassign[] = $row['reviewer_id'];
                        }
                        $result_reviewers->free();
                    } else {
                        error_log("Error fetching previous reviewers: " . $stmt_fetch_reviewers->error);
                        $error_message .= "Error fetching previous reviewers for re-assignment.";
                        $uploadOk = 0;
                    }
                    $stmt_fetch_reviewers->close();
                } else {
                    error_log("Error preparing fetch reviewers query: " . $conn->error);
                    $error_message .= "Database error preparing reviewer fetch.";
                    $uploadOk = 0;
                }
            }

            // Insert these reviewers for the new version into document_reviewers
            // And update/insert their status in document_evaluation for the new review cycle
            if ($uploadOk == 1 && $conn && !empty($reviewers_to_reassign)) {
                $sql_insert_new_reviewers = "INSERT INTO document_reviewers (did, version, reviewer_id) VALUES (?, ?, ?)";
                // Use INSERT ... ON DUPLICATE KEY UPDATE for document_evaluation
                // Assuming (did, id) is a UNIQUE KEY or PRIMARY KEY in document_evaluation
                $sql_upsert_evaluation_status = "
                    INSERT INTO document_evaluation (did, id, status, version) VALUES (?, ?, 'Review Ongoing', ?)
                    ON DUPLICATE KEY UPDATE status = 'Review Ongoing', version = VALUES(version);
                ";
                
                if ($stmt_insert_new_reviewers = $conn->prepare($sql_insert_new_reviewers)) {
                    if ($stmt_upsert_evaluation_status = $conn->prepare($sql_upsert_evaluation_status)) {
                        foreach ($reviewers_to_reassign as $reviewer_id) {
                            // Insert into document_reviewers for the new version
                            $stmt_insert_new_reviewers->bind_param("iii", $did, $new_version, $reviewer_id);
                            if (!$stmt_insert_new_reviewers->execute()) {
                                error_log("Error re-assigning reviewer " . $reviewer_id . " to new version " . $new_version . ": " . $stmt_insert_new_reviewers->error);
                                $error_message .= "Error re-assigning some reviewers.";
                                // Continue loop, don't set uploadOk to 0 for partial success
                            } else {
                                // Upsert (Insert/Update) initial status into document_evaluation
                                // Note: We are now passing 'version' to document_evaluation as well,
                                // assuming it exists as a column in that table based on its use in student_dashboard.
                                $stmt_upsert_evaluation_status->bind_param("iii", $did, $reviewer_id, $new_version);
                                if (!$stmt_upsert_evaluation_status->execute()) {
                                    error_log("Error upserting initial evaluation status for reviewer " . $reviewer_id . " on new version " . $new_version . ": " . $stmt_upsert_evaluation_status->error);
                                    $error_message .= "Error setting initial evaluation status for some reviewers.";
                                }
                            }
                        }
                        $stmt_insert_new_reviewers->close();
                        $stmt_upsert_evaluation_status->close();
                    } else {
                        error_log("Error preparing upsert evaluation status query: " . $conn->error);
                        $error_message .= "Database error preparing initial evaluation status upsert.";
                        $uploadOk = 0;
                    }
                } else {
                    error_log("Error preparing insert new reviewers query: " . $conn->error);
                    $error_message .= "Database error preparing reviewer re-assignment.";
                    $uploadOk = 0;
                }
            }

            // 4. Handle success/failure and redirect
            if ($uploadOk == 1) {
                $_SESSION['upload_message'] = "Document ID " . $did . " has been resubmitted as Version " . $new_version . " successfully!";
                header("location: student_dashboard.php");
                exit;
            } else {
                // If there was an error after file upload, attempt to delete the uploaded file
                if (file_exists($target_file)) {
                    unlink($target_file);
                    error_log("Cleaned up uploaded file due to DB error: " . $target_file);
                }
            }

        } else {
            $error_message .= "Error moving uploaded file.";
        }
    }
}

// Close database connection (only if the connection was opened and valid)
if (isset($conn) && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Resubmit Paper - Revue System</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding-top: 0;
            box-sizing: border-box;
        }

        .header {
            background-color: #0d47a1;
            color: white;
            padding: 30px 25px 15px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            width: 100%;
            position: relative;
            margin-bottom: 40px;
        }

        .header-left {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            margin-top: -15px;
        }

        .header .logo img {
            height: 80px;
            margin-right: 15px;
            margin-top: -30px;
            position: relative;
            z-index: 1;
        }

        .site-titles {
            display: flex;
            flex-direction: column;
        }

        .site-titles .school-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .site-titles .university-name {
            font-size: 14px;
        }

        .center-title {
            flex-grow: 1;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: white;
            margin-top: -15px;
            padding: 0 20px;
        }

        .user-info {
            display: none; /* Hide user info on this page */
        }

        .resubmit-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            margin-top: 20px;
        }
        .resubmit-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #0277bd;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="file"],
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
            display: inline-block;
            vertical-align: middle;
        }
        .form-group .read-only {
            background-color: #f0f0f0;
            color: #666;
            cursor: not-allowed;
        }
        .btn-resubmit {
            background-color: #f0ad4e; /* Orange for resubmit */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }
        .btn-resubmit:hover {
            background-color: #ec971f;
        }
        .message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: block;
            margin-top: 20px;
            text-align: center;
            color: #0277bd;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                padding: 20px 15px 10px 15px;
                margin-bottom: 20px;
            }
            .header-left, .center-title {
                margin-top: 0;
                margin-left: 0;
                margin-right: 0;
                margin-bottom: 10px;
            }
            .header .logo img {
                margin-right: 0;
                margin-bottom: 10px;
                height: 60px;
                margin-top: -20px;
            }
            .header-left {
                flex-direction: column;
                align-items: center;
            }
            .site-titles .school-name {
                font-size: 15px;
            }
            .site-titles .university-name {
                font-size: 13px;
            }
            .center-title {
                font-size: 18px;
                margin: 10px 0;
                padding: 0;
            }
            .resubmit-container {
                margin-top: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="logo">
                 <img src="images/SAS-Logo.png" alt="University Logo">
            </div>
            <div class="site-titles">
                <div class="school-name">SCHOOL OF ADVANCED STUDIES</div>
                <div class="university-name">Saint Louis University, Baguio City</div>
            </div>
        </div>
        <div class="center-title">
            Student Paper Resubmission Page
        </div>
    </div>

    <div class="resubmit-container">
        <h2>Resubmit Paper</h2>

        <?php
        // Display messages from session if redirected from dashboard
        if (isset($_SESSION['upload_message'])) {
            echo '<div class="message success-message">' . $_SESSION['upload_message'] . '</div>';
            unset($_SESSION['upload_message']); // Clear the message after displaying
        }
        if (!empty($error_message)) {
            echo '<div class="message error-message">' . $error_message . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="did" value="<?php echo htmlspecialchars($did); ?>">
            <input type="hidden" name="current_version" value="<?php echo htmlspecialchars($current_version); ?>">
            <input type="hidden" name="paperTitle" value="<?php echo htmlspecialchars($document_title); ?>">
            <input type="hidden" name="adviserName" value="<?php echo htmlspecialchars($adviser_name); ?>">
            <input type="hidden" name="programDegree" value="<?php echo htmlspecialchars($program_id); ?>">

            <div class="form-group">
                <label for="displayPaperTitle">Paper Title:</label>
                <input type="text" id="displayPaperTitle" value="<?php echo htmlspecialchars($document_title); ?>" class="read-only" readonly>
            </div>
            <div class="form-group">
                <label for="displayAdviserName">Adviser Name:</label>
                <input type="text" id="displayAdviserName" value="<?php echo htmlspecialchars($adviser_name); ?>" class="read-only" readonly>
            </div>
            <div class="form-group">
                <label for="displayProgramDegree">Program/Degree:</label>
                <input type="text" id="displayProgramDegree" value="<?php echo htmlspecialchars($program_description); ?>" class="read-only" readonly>
            </div>
            <div class="form-group">
                <label for="currentFilePath">Current File:</label>
                <?php if (!empty($current_file_path)): ?>
                    <a href="<?php echo htmlspecialchars($current_file_path); ?>" download class="action-links">Download Current File (Version <?php echo htmlspecialchars($current_version); ?>)</a>
                <?php else: ?>
                    <p>No current file available.</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="paperFile">Select New Paper File:</label>
                <input type="file" id="paperFile" name="paperFile" required>
            </div>
            <button type="submit" class="btn-resubmit" name="submit">Resubmit Paper</button>
        </form>

        <a href="student_dashboard.php" class="back-link">Back to Dashboard</a>
    </div>
</body>
</html>