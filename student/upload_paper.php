<?php
// Start the session and check if the user is logged in as a student
session_start();

// Check if the user is logged in AND is a student, otherwise redirect to login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("location: login.php");
    exit;
}

// Include database connection
include 'db_connect.php';

$upload_message = "";
$error_message = "";
$programs = []; // Array to hold program data for the dropdown

// --- Fetch Programs for Dropdown ---
// Ensure database connection is valid before attempting to fetch programs
if ($conn) {
    $sql_programs = "SELECT pid, description FROM program ORDER BY description";
    // Using query for simple select, consider prepared statements for more complex queries with user input
    if ($result_programs = $conn->query($sql_programs)) {
        while ($row = $result_programs->fetch_assoc()) {
            $programs[] = $row;
        }
        $result_programs->free(); // Free result set
    } else {
        // Handle the database error gracefully
        error_log("Error fetching programs: " . $conn->error); // Log the error on the server
        $error_message .= "Could not load program list due to a database error.";
    }
} else {
     // Handle the database connection error (already done in db_connect, but adding message here)
     $error_message .= "Database connection failed, could not load program list.";
}


// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $document_title = trim($_POST['paperTitle']);
    $adviser_name = trim($_POST['adviserName']); // Added Adviser Name field retrieval
    $program_id = isset($_POST['programDegree']) ? $_POST['programDegree'] : ''; // Added Program ID field retrieval

    $student_uid = $_SESSION['uid'];

     // --- Basic Validation ---
     // Added checks for adviser_name and program_id
    if (empty($document_title) || empty($adviser_name) || empty($_FILES["paperFile"]["name"]) || empty($program_id) || !is_numeric($program_id)) {
         $error_message .= "Please fill in all required fields and select a valid Program/Degree and file. ";
         $uploadOk = 0; // Prevent file upload and DB operations
    } else {
         // Fields are not empty, proceed with file checks
         $uploadOk = 1; // Assume OK initially if basic fields are present

        // --- File Upload Handling ---
        // Define upload directory (make sure this directory exists and is writable by the web server)
        // Using a subdirectory for each student based on their UID
        $target_dir = "uploads/" . $student_uid . "/"; // Use student_uid variable

        // Create student's upload directory if it doesn't exist
        if (!is_dir($target_dir)) {
            // mkdir returns true on success, false on failure
            if (!mkdir($target_dir, 0777, true)) { // Adjust permissions for security on a live server
                 $error_message .= "Error creating upload directory. ";
                 $uploadOk = 0; // Prevent upload if directory can't be created
            }
        }

        // Only proceed with file checks if directory creation was successful or already exists
        if ($uploadOk == 1) {
            // Ensure file is uploaded via POST and no PHP upload errors occurred
            if (isset($_FILES["paperFile"]) && $_FILES["paperFile"]["error"] === UPLOAD_ERR_OK) {

                $original_file_name = basename($_FILES["paperFile"]["name"]);
                // Sanitize the file name - a more robust sanitization might be needed
                $safe_file_name = preg_replace("/[^a-zA-Z0-9_\-.]/", "", $original_file_name);
                $target_file = $target_dir . $safe_file_name;
                $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

                // Allow certain file formats (adjust as needed) - check MIME type too for better security
                $allowed_types = array("pdf", "doc", "docx", "rtf");
                // You can also check MIME type: in_array($_FILES["paperFile"]["type"], array("application/pdf", "..."))
                if (!in_array($fileType, $allowed_types)) {
                    $error_message .= "Sorry, only " . implode(", ", $allowed_types) . " files are allowed. ";
                    $uploadOk = 0;
                }

                // Check file size (e.g., max 10MB)
                if ($_FILES["paperFile"]["size"] > 10000000) { // 10MB limit
                    $error_message .= "Sorry, your file is too large (max 10MB). ";
                    $uploadOk = 0;
                }

                 // Optional: Check if file with the same sanitized name already exists in the target directory
                 /*
                 if (file_exists($target_file)) {
                     $error_message .= "A file with this name already exists. ";
                     $uploadOk = 0;
                 }
                 */

            } else {
                 // File upload error or no file uploaded (handled by initial empty check, but good to have this too)
                 // UPLOAD_ERR_NO_FILE error code means no file was uploaded
                 if (isset($_FILES["paperFile"]) && $_FILES["paperFile"]["error"] !== UPLOAD_ERR_NO_FILE) {
                     $error_message .= "File upload error: " . $_FILES["paperFile"]["error"] . ". Please ensure file was uploaded correctly. ";
                 } else if (!isset($_FILES["paperFile"])) {
                      $error_message .= "File upload error: File data not received by the server. ";
                 }
                 $uploadOk = 0;
            }
        } // Closing brace for if ($uploadOk == 1) for file checks
    } // Closing brace for if (empty(...) else {}) for basic validation


    // If all checks pass and uploadOk is still 1, attempt to move the uploaded file and insert into database
    if ($uploadOk == 1) {
         // Move the uploaded file
         if (move_uploaded_file($_FILES["paperFile"]["tmp_name"], $target_file)) {

            // --- Database Insertion ---

            // Determine the next document ID for a *new* document for this student
            $new_did = 1; // Default if no previous documents
            if ($conn) { // Check database connection before query
                 $sql_max_did = "SELECT MAX(did) FROM document_details WHERE dauthor = ?";
                 if ($stmt_max_did = $conn->prepare($sql_max_did)) {
                    $stmt_max_did->bind_param("i", $student_uid);
                    if ($stmt_max_did->execute()) { // Check execution
                        $stmt_max_did->bind_result($max_did_result);
                        if ($stmt_max_did->fetch() && $max_did_result !== null) {
                            $new_did = $max_did_result + 1;
                        }
                    } else {
                         // Log the error but can potentially proceed with default new_did=1
                         error_log("Error executing max did query: " . $stmt_max_did->error);
                         // $error_message .= "Could not determine new document ID. "; // Decide if this should be a user-visible error
                    }
                    $stmt_max_did->close();
                 } else {
                      // Error preparing the query is more critical
                      error_log("Error preparing max did query: " . $conn->error);
                      $error_message .= "An internal error occurred (code 101).";
                      $uploadOk = 0; // Prevent further DB operations if this fails
                 }
            } else {
                 // Database connection failed (already handled in db_connect, but adding message here)
                 $error_message .= "Database connection not available for determining new document ID.";
                 $uploadOk = 0; // Prevent further DB operations
            }


            $new_version = 1; // Initial version is always 1 for a new document


            // Insert into document table
            if ($uploadOk == 1 && $conn) { // Only proceed if no errors so far and connection is valid
                $sql_document = "INSERT INTO document (did, dtitle, file_path, version) VALUES (?, ?, ?, ?)";
                if ($stmt_document = $conn->prepare($sql_document)) {
                    $stmt_document->bind_param("issi", $new_did, $document_title, $target_file, $new_version);
                    if ($stmt_document->execute()) {

                        // Insert into document_details table (link document to author, adviser, and program)
                        // Check if document_details already exists for this did (it shouldn't for a new did, but a safety check)
                        $sql_check_details = "SELECT COUNT(*) FROM document_details WHERE did = ?";
                         if ($conn) { // Check connection before this query
                            if ($stmt_check_details = $conn->prepare($sql_check_details)) {
                                $stmt_check_details->bind_param("i", $new_did);
                                if ($stmt_check_details->execute()) {
                                    $stmt_check_details->bind_result($details_count);
                                    $stmt_check_details->fetch();
                                    $stmt_check_details->close();

                                    if ($details_count == 0) { // Only insert document_details if it's a truly new document ID
                                         // Use the adviser name and program ID from the form
                                        $sql_document_details = "INSERT INTO document_details (did, dauthor, dadviser, program) VALUES (?, ?, ?, ?)";
                                        if ($stmt_document_details = $conn->prepare($sql_document_details)) {
                                            $stmt_document_details->bind_param("iisi", $new_did, $student_uid, $adviser_name, $program_id);
                                            if ($stmt_document_details->execute()) {

                                                // --- Insert into document_submission_log table ---
                                                $sql_submission_log = "INSERT INTO submission_log (document_did, document_version, submitted_by_id) VALUES (?, ?, ?)";
                                                if ($stmt_submission_log = $conn->prepare($sql_submission_log)) {
                                                    $stmt_submission_log->bind_param("iii", $new_did, $new_version, $student_uid);
                                                    if ($stmt_submission_log->execute()) {
                                                         // Success!
                                                         $upload_message = "Your paper \"" . htmlspecialchars($document_title) . "\" has been uploaded successfully.";
                                                         // Optional: Clear form fields on success
                                                         // Note: Clearing fields after success requires redirecting or carefully managing POST data display
                                                    } else {
                                                         error_log("Error inserting submission log: " . $stmt_submission_log->error);
                                                         $error_message .= "An internal error occurred (code 102).";
                                                         // Consider cleaning up document and details entries if log insertion fails
                                                    }
                                                    $stmt_submission_log->close();
                                                } else {
                                                     error_log("Error preparing submission log insertion: " . $conn->error);
                                                     $error_message .= "An internal error occurred (code 103).";
                                                     // Consider cleaning up document and details entries if log insertion fails
                                                }

                                            } else {
                                                 error_log("Error inserting document details: " . $stmt_document_details->error);
                                                 $error_message .= "An internal error occurred (code 104).";
                                                 // Consider deleting the document entry and uploaded file if details insertion fails
                                            }
                                            $stmt_document_details->close();
                                        } else {
                                            error_log("Error preparing document details insertion: " . $conn->error);
                                            $error_message .= "An internal error occurred (code 105).";
                                            // Consider deleting the document entry and uploaded file if details insertion fails
                                        }
                                    } else {
                                         // This case should ideally not happen for a newly determined did
                                         error_log("Unexpected database state: document details already exist for did " . $new_did);
                                         $error_message .= "An internal error occurred (code 108).";
                                         // Consider deleting the document entry and uploaded file
                                    }

                                } else {
                                     error_log("Error executing document details count query: " . $stmt_check_details->error);
                                     $error_message .= "An internal error occurred (code 109).";
                                     // Consider deleting the document entry and uploaded file
                                }
                            } else {
                                error_log("Error preparing document details count query: " . $conn->error);
                                $error_message .= "An internal error occurred (code 110).";
                                 // Consider deleting the document entry and uploaded file
                            }
                        }


                    } else {
                         error_log("Error inserting document information: " . $stmt_document->error);
                         $error_message .= "An internal error occurred (code 106).";
                         // Consider deleting the uploaded file if database insertion fails
                    }
                    $stmt_document->close();
                } else {
                     error_log("Error preparing document insertion: " . $conn->error);
                     $error_message .= "An internal error occurred (code 107).";
                     // Consider deleting the uploaded file if database insertion fails
                }

            } else {
                 // File move failed or a previous step set uploadOk to 0, error_message is already set
                 // No database insertion should occur
                 error_log("File move failed or uploadOk was 0. Error: " . $error_message);
            }
        } else {
             // Initial checks failed (uploadOk was 0), error_message is already set
             // No file move or database insertion should occur
              error_log("Initial checks failed. Error: " . $error_message);
        }
    }

}
// Close database connection (only if the connection was opened and valid, and not redirected)
if (isset($conn) && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Paper - Revue System</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7f6; /* Match dashboard background */
            margin: 0;
            display: flex;
            flex-direction: column; /* Stack header and content */
            align-items: center; /* Center content horizontally */
            min-height: 100vh;
             padding-top: 0;
             box-sizing: border-box;
        }

        .header {
            background-color: #0d47a1;
            color: white;
            padding: 30px 25px 15px 25px; /* Match dashboard header padding */
            display: flex;
            align-items: center;
            justify-content: space-between; /* Match dashboard header layout */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            width: 100%;
            position: relative;
             margin-bottom: 40px; /* Space below header */
        }

        .header-left {
            display: flex;
            align-items: center;
            flex-shrink: 0;
             margin-top: -15px; /* Match dashboard header alignment */
        }

        .header .logo img {
            height: 80px; /* Match dashboard logo size */
            margin-right: 15px;
            margin-top: -30px; /* Match dashboard logo overlap */
            position: relative;
            z-index: 1;
        }

        .site-titles {
            display: flex;
            flex-direction: column;
        }

        .site-titles .school-name {
            font-size: 16px; /* Match dashboard font size */
            font-weight: bold;
            margin-bottom: 2px;
        }

        .site-titles .university-name {
            font-size: 14px; /* Match dashboard font size */
        }

         .center-title {
             flex-grow: 1;
            text-align: center;
            font-size: 20px; /* Match dashboard font size */
            font-weight: bold;
            color: white;
             margin-top: -15px; /* Match dashboard alignment */
            padding: 0 20px;
        }

         /* Hide the user info section on this page */
        .user-info {
            display: none;
        }


         .upload-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
             margin-top: 20px; /* Space below header/above form */
        }
        .upload-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #0277bd; /* Match dashboard heading color */
             border-bottom: 2px solid #e0e0e0; /* Match dashboard heading style */
             padding-bottom: 10px;
             margin-top: 0; /* No top margin for the first heading in the container */
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
         .form-group select { /* Added select for dropdown */
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
             box-sizing: border-box;
             display: inline-block; /* Ensure it doesn't break layout */
             vertical-align: middle; /* Vertically align with other inline elements */
        }
         .btn-upload {
            background-color: #5cb85c;
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
        .btn-upload:hover {
            background-color: #4cae4c;
        }
        .message {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px; /* Add bottom margin */
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
            color: #0277bd; /* Match dashboard link color */
            text-decoration: none;
        }
         .back-link:hover {
            text-decoration: underline;
        }

         /* Responsive adjustments for the header and form on smaller screens */
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
              .upload-container {
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
            Student Paper Submission Page
        </div>
        </div>

    <div class="upload-container">
        <h2>Upload New Paper</h2>

        <?php
        if (!empty($upload_message)) {
            echo '<div class="message success-message">' . $upload_message . '</div>';
        }
        if (!empty($error_message)) {
            echo '<div class="message error-message">' . $error_message . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="paperTitle">Paper Title:</label>
                <input type="text" id="paperTitle" name="paperTitle" value="<?php echo isset($_POST['paperTitle']) ? htmlspecialchars($_POST['paperTitle']) : ''; ?>" required>
            </div>
             <div class="form-group">
                <label for="adviserName">Adviser Name:</label>
                 <input type="text" id="adviserName" name="adviserName" value="<?php echo isset($_POST['adviserName']) ? htmlspecialchars($_POST['adviserName']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="programDegree">Program/Degree:</label>
                <select id="programDegree" name="programDegree" required>
                    <option value="">-- Select Program/Degree --</option>
                    <?php foreach ($programs as $program): ?>
                        <option value="<?php echo htmlspecialchars($program['pid']); ?>" <?php echo (isset($_POST['programDegree']) && $_POST['programDegree'] == $program['pid']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($program['description']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="paperFile">Select Paper File:</label>
                <input type="file" id="paperFile" name="paperFile" required>
            </div>
            <button type="submit" class="btn-upload" name="submit">Upload Paper</button>
        </form>

         <a href="student_dashboard.php" class="back-link">Back to Dashboard</a>
    </div>
</body>
</html>
