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

// Get document ID, version, and reviewer ID from URL parameters
$document_did = isset($_GET['did']) ? intval($_GET['did']) : 0;
$document_version = isset($_GET['version']) ? intval($_GET['version']) : 0;
$reviewer_id = isset($_GET['reviewer_id']) ? intval($_GET['reviewer_id']) : 0;


$document = null; // Variable to store document details
$feedback_info = null; // Variable to store specific reviewer feedback and name
$error_message = "";

// --- Fetch Document Details and Specific Reviewer Feedback ---
// Ensure database connection is valid and parameters are valid
if ($conn && $document_did > 0 && $document_version > 0 && $reviewer_id > 0) {
    // Fetch document details and specific reviewer's feedback and name
    $sql_document_and_feedback = "
        SELECT
            d.dtitle,
            d.file_path,
            u.firstname,
            u.lastname,
            de.feedback
        FROM
            document d
        JOIN
            document_reviewers dr ON d.did = dr.did AND d.version = dr.version
        JOIN
            users u ON dr.reviewer_id = u.uid
        LEFT JOIN -- LEFT JOIN because a reviewer might be assigned but not have submitted evaluation yet
            document_evaluation de ON dr.did = de.did AND dr.reviewer_id = de.id -- Join on did and reviewer ID ('id' column in document_evaluation)
        WHERE
            d.did = ? AND d.version = ? AND dr.reviewer_id = ?
    ";

    if ($stmt_document_and_feedback = $conn->prepare($sql_document_and_feedback)) {
        $stmt_document_and_feedback->bind_param("iii", $document_did, $document_version, $reviewer_id);

        if ($stmt_document_and_feedback->execute()) {
            $result_document_and_feedback = $stmt_document_and_feedback->get_result();
            if ($result_document_and_feedback->num_rows > 0) {
                // Fetch the row
                $row = $result_document_and_feedback->fetch_assoc();

                $document = [
                    'dtitle' => $row['dtitle'],
                    'file_path' => $row['file_path']
                ];

                $feedback_info = [
                    'reviewer_name' => htmlspecialchars($row['firstname'] . ' ' . $row['lastname']),
                    'feedback' => htmlspecialchars($row['feedback'] ?? '') // Use fetched feedback or empty string
                ];

            } else {
                // This case might happen if the reviewer_id in the URL doesn't match a reviewer for this document/version
                 $error_message = "Could not find feedback details for the specified reviewer and document.";
            }
            $result_document_and_feedback->free(); // Free result set
        } else {
            error_log("Error executing document and feedback query: " . $stmt_document_and_feedback->error);
            $error_message = "An internal error occurred while fetching document and feedback details.";
        }
        $stmt_document_and_feedback->close();
    } else {
        error_log("Error preparing document and feedback query: " . $conn->error);
        $error_message = "An internal error occurred while preparing document and feedback query.";
    }
} else {
    $error_message = "Invalid document ID, version, or reviewer ID provided.";
}

// Close the database connection (optional here)
if (isset($conn) && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Reviewer Feedback - Revue System</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            background-color: #f4f7f6; /* Light gray background */
            color: #333;
        }

        .header {
            background-color: #0d47a1; /* Dark blue background color from the image */
            color: white;
            /* Increase top and bottom padding to make space for the overlapping logo */
            padding: 30px 25px 15px 25px; /* Increased top padding, normal bottom padding */
            display: flex;
            align-items: center; /* Keep items vertically aligned */
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            position: relative; /* Needed if we were to use absolute positioning, but negative margin is simpler here */
        }

        .header-left {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            /* Adjust margin-top to align with the visual top of the header */
            margin-top: -15px; /* Pull the left section up slightly */
        }

        .header .logo img {
            /* Increase logo height significantly */
            height: 80px; /* Increased height (adjust as needed for desired overlap) */
            margin-right: 15px;
            /* Use a negative top margin to pull the image up into the header's padding */
            margin-top: -30px; /* Pulls the logo up by 30px (adjust based on header padding and logo height) */
            position: relative; /* Ensure z-index works if needed */
            z-index: 1; /* Make sure the logo appears above the header background */
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
            /* font-weight: normal; */
        }

        .center-title {
            flex-grow: 1;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: white;
            padding: 0 20px;
            /* Adjust margin-top to align with the visual top of the header */
            margin-top: -15px; /* Pull the center title up slightly */
        }

        .user-info {
            font-size: 14px;
            color: #e0e0e0;
            flex-shrink: 0;
            /* Adjust margin-top to align with the visual top of the header */
            margin-top: -15px; /* Pull the user info up slightly */
        }

        .container {
            padding: 20px;
            max-width: 800px; /* Adjusted max-width for document viewing */
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        h2, h3 {
            color: #0277bd;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-top: 20px;
        }

        .document-details p {
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .document-link {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 15px;
            background-color: #0277bd; /* Blue color */
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }

        .document-link:hover {
            background-color: #01579b; /* Darker blue */
        }

        .feedback-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff3cd; /* Light yellow background */
            border: 1px solid #ffecb3; /* Yellow border */
            border-radius: 4px;
            color: #664d03; /* Dark yellow text */
        }

         .feedback-section h3 {
            color: #664d03; /* Match text color */
            border-bottom: 1px solid #ffecb3; /* Match border color */
            padding-bottom: 5px;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .feedback-section p {
            margin-bottom: 0; /* Remove bottom margin from the last paragraph */
            line-height: 1.6;
        }

        .feedback-section strong {
            display: block; /* Make the reviewer name appear on its own line */
            margin-bottom: 5px;
        }


        .no-feedback-message {
            margin-top: 20px;
            padding: 15px;
            background-color: #e9e9eb; /* Light gray background */
            border: 1px solid #d3d3d6; /* Gray border */
            border-radius: 4px;
            color: #555; /* Dark gray text */
        }


        .error-message {
            color: #d9534f;
            font-weight: bold;
            margin-top: 20px;
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

        /* Responsive adjustments for the header and container */
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
              .container {
                 padding: 15px;
                 margin-top: 15px;
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
            View Reviewer Feedback
        </div>
        <div class="user-info">
            Logged in as: <?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?>
        </div>
    </div>

    <div class="container">
        <?php if ($document): ?>
            <h2>Document Details</h2>
            <div class="document-details">
                <p><strong>Document ID:</strong> <?php echo htmlspecialchars($document_did); ?></p>
                <p><strong>Version:</strong> <?php echo htmlspecialchars($document_version); ?></p>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($document['dtitle']); ?></p>
                <p><strong>File:</strong> <a href="<?php echo htmlspecialchars($document['file_path']); ?>" class="document-link" download>Download Document</a></p>
            </div>

            <?php if ($feedback_info): // Check if feedback_info was successfully fetched ?>
                <div class="feedback-section">
                    <h3>Reviewer Feedback</h3>
                    <?php if (!empty($feedback_info['reviewer_name'])): ?>
                        <p><strong>From:</strong> <?php echo $feedback_info['reviewer_name']; ?></p>
                    <?php endif; ?>

                    <?php if (!empty($feedback_info['feedback'])): ?>
                         <p><?php echo nl2br($feedback_info['feedback']); ?></p>
                    <?php else: ?>
                        <div class="no-feedback-message">
                            <p>No feedback from the reviewer is available yet.</p>
                        </div>
                    <?php endif; ?>

                </div>
            <?php elseif (!empty($error_message)): // Display error if fetching feedback_info failed ?>
                 <p class="error-message"><?php echo $error_message; ?></p>
            <?php else: // This case might not be strictly necessary but good for completeness ?>
                 <div class="no-feedback-message">
                    <p>No feedback details could be loaded.</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <a href="student_dashboard.php" class="back-link">Back to Dashboard</a>
    </div>
</body>
</html>





