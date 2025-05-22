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

// You can now use $_SESSION['uid'], $_SESSION['firstname'], $_SESSION['lastname']
$student_uid = $_SESSION['uid'];
$student_firstname = $_SESSION['firstname'];
$student_lastname = $_SESSION['lastname'];

// --- Display Student's Submissions ---
// We will fetch the latest version of each document and then fetch reviewers for each
$latest_submissions = []; // Array to hold the latest version of each document

// SQL query to get the LATEST version of each document submitted by the student
// We use a subquery to find the maximum version for each document ID (did) for the current author
// Joined with submission_log to get the timestamp
$sql_latest_submissions = "
    SELECT
        d.did,
        d.dtitle,
        d.version, -- This will now be the latest version due to the join condition below
        d.file_path,
        dd.dadviser,
        sl.timestamp AS submission_timestamp -- Fetch timestamp from submission_log and alias it
    FROM
        document d
    JOIN
        document_details dd ON d.did = dd.did
    LEFT JOIN -- Join with submission_log table
        submission_log sl ON d.did = sl.did AND d.version = sl.version
    JOIN (
        -- Subquery to find the latest version for each document ID for the current author
        SELECT
            d.did,
            MAX(d.version) AS latest_version
        FROM
            document d
        JOIN
            document_details dd ON d.did = dd.did
        WHERE dd.dauthor = ? -- Filter by author in subquery
        GROUP BY
            d.did
    ) AS latest_versions ON d.did = latest_versions.did AND d.version = latest_versions.latest_version
    WHERE
        dd.dauthor = ? -- Still filter by author in the main query for robustness
    ORDER BY
        d.did DESC; -- Order by did desc, showing most recent documents first
";

// Check if database connection is valid before preparing and executing the query
if ($conn) {
    if ($stmt_latest_submissions = $conn->prepare($sql_latest_submissions)) {
        // Bind the student_uid parameter for both placeholders in the query
        $stmt_latest_submissions->bind_param("ii", $student_uid, $student_uid); // Bind parameter twice

        if ($stmt_latest_submissions->execute()) {
            $result_latest_submissions = $stmt_latest_submissions->get_result();
            // Store the latest submissions
            while ($row = $result_latest_submissions->fetch_assoc()) {
                $latest_submissions[$row['did']] = $row; // Use did as the key
            }
            // Free result set
            $result_latest_submissions->free();

        } else {
            // Log the error for debugging
            error_log("Error executing latest submissions query: " . $stmt_latest_submissions->error);
            // Optionally, display a user-friendly error message on the page
            // $error_message .= "Error fetching your latest submissions.";
        }
        $stmt_latest_submissions->close();


        // --- Fetch Reviewers and Their Evaluation Status/Feedback for Each Latest Submission ---
        // Prepare the query to fetch reviewers, their status, and feedback for a specific document version
        // Correctly joining with document_evaluation to get reviewer status
        $sql_reviewers = "
            SELECT
                u.uid AS reviewer_uid, -- Fetch reviewer's UID
                u.firstname,
                u.lastname,
                de.status AS reviewer_status, -- Status from document_evaluation for this reviewer
                de.feedback AS reviewer_feedback -- Feedback from document_evaluation for this reviewer
            FROM
                document_reviewers dr
            JOIN
                users u ON dr.reviewer_id = u.uid
            LEFT JOIN -- LEFT JOIN because a reviewer might be assigned but not have submitted evaluation yet
                document_evaluation de ON dr.did = de.did AND dr.reviewer_id = de.id -- Correct Join: on did, version, and reviewer ID
            WHERE
                dr.did = ? AND dr.version = ?
            ORDER BY
                u.lastname, u.firstname;
        ";

        $overall_resubmit_needed = false; // Flag to determine if the overall resubmit button should be shown
        $resubmit_did = null; // Store the DID of the document needing resubmission
        $resubmit_version = null; // Store the Version of the document needing resubmission

        if ($stmt_reviewers = $conn->prepare($sql_reviewers)) {
            // Loop through each LATEST submission to get its reviewers and their status/feedback
            foreach ($latest_submissions as $did => &$submission) { // Use $did as key, and & to modify the original array elements
                $stmt_reviewers->bind_param("ii", $submission['did'], $submission['version']);

                if ($stmt_reviewers->execute()) {
                    $result_reviewers = $stmt_reviewers->get_result();
                    $reviewers_data = [];
                    $all_reviewers_requested_revision = true; // Assume true until a reviewer status proves otherwise
                    $reviewer_count_for_doc = 0; // Count actual assigned reviewers

                    while ($reviewer_row = $result_reviewers->fetch_assoc()) {
                        $reviewer_count_for_doc++; // Increment count for each assigned reviewer
                        $status = htmlspecialchars($reviewer_row['reviewer_status'] ?? 'Review Ongoing');
                        $reviewers_data[] = [
                            'uid' => $reviewer_row['reviewer_uid'], // Store reviewer's UID
                            'name' => htmlspecialchars($reviewer_row['firstname'] . ' ' . $reviewer_row['lastname']),
                            'status' => $status,
                            'feedback' => htmlspecialchars($reviewer_row['reviewer_feedback'] ?? '') // Default to empty string
                        ];
                        // If any reviewer's status is NOT 'Revision Requested', set the flag to false
                        if ($status !== 'Revision Requested') {
                            $all_reviewers_requested_revision = false;
                        }
                    }

                    // Store the reviewers data array in the current submission
                    $submission['reviewers_data'] = $reviewers_data;

                    // Check if the overall resubmit button should be shown for THIS document
                    // It's needed if there's at least one reviewer AND all reviewers requested revision
                    if ($reviewer_count_for_doc > 0 && $all_reviewers_requested_revision) {
                        $overall_resubmit_needed = true;
                        $resubmit_did = $submission['did']; // Store DID
                        $resubmit_version = $submission['version']; // Store Version
                         // No need to check other documents if one already triggers the resubmit button
                         // break; // Exit the foreach loop if we find a document needing overall resubmit
                    }


                    // Free reviewer result set
                    $result_reviewers->free();

                } else {
                    error_log("Error executing reviewers query for DID " . $submission['did'] . ", Version " . $submission['version'] . ": " . $stmt_reviewers->error);
                    $submission['reviewers_data'] = []; // Indicate error by empty array
                }
            }
            // Close the reviewers statement
            $stmt_reviewers->close();
        } else {
             error_log("Error preparing reviewers query: " . $conn->error);
             // Add an error message for reviewers to all submissions if the query preparation failed
             foreach ($latest_submissions as &$submission) {
                 $submission['reviewers_data'] = []; // Indicate error by empty array
             }
        }

    } else {
        // Log the error for debugging (This is for the main latest submissions query preparation)
        error_log("Error preparing main latest submissions query: " . $conn->error);
        // Optionally, display a user-friendly error message on the page
        // $error_message .= "Error preparing to fetch your latest submissions.";
    }
} else {
     // Database connection failed (handled by db_connect.php, but can add a message)
     error_log("Database connection failed when fetching latest submissions.");
     // Optionally, display a user-friendly error message on the page
     // $error_message .= "Database connection not available.";
}


// Close the database connection (optional here, as it will be closed automatically at the end of the script)
// but explicitly closing is good practice if you are done with DB operations.
if (isset($conn) && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard - Revue System</title>
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
            max-width: 1000px;
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

        .upload-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 15px;
            background-color: #5cb85c;
            color: white;
            text-decoration: none;
            border-radius: 44px; /* Adjusted border-radius */
            transition: background-color 0.3s ease;
        }

        .upload-link:hover {
            background-color: #4cae4c;
        }

        /* Removed history-link style as the link is removed */
        /*
         .history-link {
            display: inline-block;
            margin-left: 20px;
            margin-bottom: 20px;
            padding: 10px 15px;
            background-color: #0277bd;
            color: white;
            text-decoration: none;
            border-radius: 44px;
            transition: background-color 0.3s ease;
         }

         .history-link:hover {
             background-color: #01579b;
         }
        */


        .submissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .submissions-table th,
        .submissions-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top; /* Align content to the top in cells */
        }

        .submissions-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #555;
        }

        .submissions-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .submissions-table tbody tr:hover {
            background-color: #e9e9e9;
        }

        .action-links a {
            margin-right: 10px;
            color: #0277bd;
            text-decoration: none;
        }

        .action-links a:hover {
            text-decoration: underline;
        }

         .reviewer-list {
             list-style: none;
             padding: 0;
             margin: 0;
         }

         .reviewer-item {
             margin-bottom: 5px;
             padding-bottom: 5px;
             border-bottom: 1px dotted #ccc; /* Separator for reviewers */
         }
         .reviewer-item:last-child {
             border-bottom: none; /* No border for the last item */
             margin-bottom: 0;
             padding-bottom: 0;
         }


        .status-pending { color: #f0ad4e; font-weight: bold; }
        .status-under-review { color: #337ab7; font-weight: bold; }
        .status-revision-requested { color: #f0ad4e; font-weight: bold; }
        .status-approved { color: #5cb85c; font-weight: bold; }
        .status-rejected { color: #d9534f; font-weight: bold; }
        .status-review-ongoing { color: #555; font-weight: normal; } /* Style for Review Ongoing status */
        .status-n-a { color: #555; font-weight: normal; } /* Style for N/A status */


        .logout-link {
            display: block;
            margin-top: 20px;
            text-align: right;
            color: #d9534f;
            text-decoration: none;
        }

        .logout-link:hover {
            text-decoration: underline;
        }

        .overall-actions {
             margin-top: 20px;
             text-align: right; /* Align actions to the right */
        }

        .overall-actions .resubmit-link {
             display: inline-block;
             padding: 10px 15px;
             background-color: #f0ad4e; /* Orange color for resubmit */
             color: white;
             text-decoration: none;
             border-radius: 44px;
             transition: background-color 0.3s ease;
             margin-right: 20px; /* Space between resubmit and logout */
        }

         .overall-actions .resubmit-link:hover {
             background-color: #ec971f; /* Darker orange */
         }


        /* Responsive adjustments for the header and smaller screens */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                 padding: 20px 15px 10px 15px;
                 margin-bottom: 20px;
            }
             .header-left, .user-info, .center-title {
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
               .submissions-table th, .submissions-table td {
                 padding: 8px; /* Reduce padding on smaller screens */
                 font-size: 12px; /* Reduce font size */
              }
              .reviewer-item {
                  margin-bottom: 3px;
                  padding-bottom: 3px;
              }
               .overall-actions {
                 text-align: center; /* Center actions on smaller screens */
              }
               .overall-actions .resubmit-link {
                 margin-right: 0; /* Remove right margin */
                 margin-bottom: 10px; /* Add bottom margin */
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
        <div class="user-info">
            Logged in as: <?php echo htmlspecialchars($_SESSION['firstname'] . ' ' . $_SESSION['lastname']); ?>
        </div>
    </div>

    <div class="container">
        <h2>Student Dashboard</h2>

        <a href="upload_paper.php" class="upload-link">Upload New Paper</a>
        <h3>Your Latest Submissions</h3>

        <?php if (empty($latest_submissions)): ?>
            <p>You have not submitted any papers yet.</p>
        <?php else: ?>
            <table class="submissions-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Version</th>
                        <th>Adviser</th>
                        <th>Submission Date</th>
                        <th>Evaluation Status</th>
                        <th>Assigned Reviewer</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($latest_submissions as $submission): ?>
                        <?php
                        $reviewers_data = $submission['reviewers_data'];
                        $reviewer_count = count($reviewers_data);

                        // Handle case where no reviewers are assigned
                        if ($reviewer_count === 0) {
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($submission['did']); ?></td>
                                <td><?php echo htmlspecialchars($submission['dtitle']); ?></td>
                                <td><?php echo htmlspecialchars($submission['version']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($submission['dadviser']); ?>
                                </td>
                                 <td>
                                    <?php
                                        if (!empty($submission['submission_timestamp'])) {
                                            echo htmlspecialchars($submission['submission_timestamp']);
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td></td> <td>No reviewers assigned yet.</td>
                                <td class="action-links">
                                    </td>
                            </tr>
                            <?php
                        } else { // Handle case where reviewers are assigned
                            for ($i = 0; $i < $reviewer_count; $i++):
                                ?>
                                <tr>
                                    <?php if ($i === 0): // Display document details only on the first row for this document group ?>
                                        <td rowspan="<?php echo $reviewer_count; ?>"><?php echo htmlspecialchars($submission['did']); ?></td>
                                        <td rowspan="<?php echo $reviewer_count; ?>"><?php echo htmlspecialchars($submission['dtitle']); ?></td>
                                        <td rowspan="<?php echo $reviewer_count; ?>"><?php echo htmlspecialchars($submission['version']); ?></td>
                                        <td rowspan="<?php echo $reviewer_count; ?>">
                                            <?php echo htmlspecialchars($submission['dadviser']); ?>
                                        </td>
                                         <td rowspan="<?php echo $reviewer_count; ?>">
                                            <?php
                                                if (!empty($submission['submission_timestamp'])) {
                                                    echo htmlspecialchars($submission['submission_timestamp']);
                                                } else {
                                                    echo 'N/A';
                                                }
                                            ?>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <?php
                                            $status = $reviewers_data[$i]['status'];
                                            $status_class = '';
                                            switch ($status) {
                                                case 'Under Review': $status_class = 'status-under-review'; break;
                                                case 'Revision Requested': $status_class = 'status-revision-requested'; break;
                                                case 'Approved': $status_class = 'status-approved'; break;
                                                case 'Rejected': $status_class = 'status-rejected'; break;
                                                case 'Review Ongoing': $status_class = 'status-review-ongoing'; break; // Use new class
                                                default: $status_class = 'status-n-a'; break; // Fallback
                                            }
                                            echo '<span class="' . $status_class . '">' . $status . '</span>';
                                        ?>
                                    </td>

                                    <td>
                                        <?php echo $reviewers_data[$i]['name']; ?>
                                    </td>

                                    <td class="action-links">
                                        <?php
                                        $reviewer_uid = $reviewers_data[$i]['uid']; // Get the reviewer's UID
                                        ?>
                                        <?php if ($reviewer_uid !== null): ?>
                                            <a href="view_document.php?did=<?php echo $submission['did']; ?>&version=<?php echo $submission['version']; ?>&reviewer_id=<?php echo $reviewer_uid; ?>">View</a>
                                        <?php endif; ?>
                                        </td>
                                </tr>
                                <?php
                            endfor;
                        }
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="overall-actions">
             <?php
            // Display the overall Resubmit link if the flag is true
            // This link should likely point to a page that handles the resubmission process for the document
            // that triggered the flag. For simplicity, we'll link to a placeholder or resubmit form.
            // Note: If multiple documents meet the criteria, this button still only appears once.
            // You might need to refine the logic or UI if you need to resubmit specific documents.
            if ($overall_resubmit_needed):
                // Find the DID and Version of the document that triggered the flag
                $resubmit_did = null;
                $resubmit_version = null;
                foreach ($latest_submissions as $submission) {
                    $all_reviewers_requested_revision = true;
                    $reviewer_count_for_doc = count($submission['reviewers_data']);
                    if ($reviewer_count_for_doc > 0) { // Only check if there are reviewers
                        foreach ($submission['reviewers_data'] as $reviewer) {
                            if ($reviewer['status'] !== 'Revision Requested') {
                                $all_reviewers_requested_revision = false;
                                break;
                            }
                        }
                    } else { // If no reviewers, it doesn't meet the "all reviewers requested revision" criteria
                         $all_reviewers_requested_revision = false;
                    }

                     if ($reviewer_count_for_doc > 0 && $all_reviewers_requested_revision) {
                         $resubmit_did = $submission['did'];
                         $resubmit_version = $submission['version'];
                         break; // Found the document that needs resubmission
                     }
                }
            ?>
                 <a href="resubmit_form.php?did=<?php echo $resubmit_did; ?>&version=<?php echo $resubmit_version; ?>" class="resubmit-link">Resubmit Document</a>
             <?php endif; ?>
             <a href="logout.php" class="logout-link">Logout</a>
        </div>

    </div>
</body>
</html>





































