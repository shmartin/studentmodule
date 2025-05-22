<?php
session_start();
include 'dbcon.php';
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $did = isset($_GET['did']) ? intval($_GET['did']) : 0;
    $assigned = [];

    if ($did > 0) {
        $stmt = $conn->prepare("SELECT reviewer_id FROM document_reviewers WHERE did = ?");
        $stmt->bind_param("i", $did);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assigned[] = $row['reviewer_id'];
        }
        $stmt->close();
    }

    $res = $conn->query("SELECT * FROM users WHERE role = 'reviewer'");
    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $checked = in_array($row['uid'], $assigned) ? 'checked' : '';
            echo "<div class='form-check'>
                    <input class='form-check-input' type='checkbox' name='reviewers[]' value='{$row['uid']}' id='rev{$row['uid']}' {$checked}>
                    <label class='form-check-label' for='rev{$row['uid']}'>
                        {$row['firstname']} {$row['lastname']}
                    </label>
                  </div>";
        }
    } else {
        echo "<p style='color: red;'>No reviewers found. Please add one.</p>";
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_reviewers' && isset($_POST['did'])) {
        $did = intval($_POST['did']);
        $reviewers = $_POST['reviewers'] ?? [];

        if (empty($reviewers)) {
            http_response_code(400);
            echo "Please select at least one reviewer.";
            exit;
        }

        $review_status = 'under-review';
        $review_date = date('Y-m-d H:i:s');
        $assigned_date = $review_date;
        $assigned_by = $_SESSION['uid'];


        $stmt = $conn->prepare("SELECT version FROM document WHERE did = ?");
        $stmt->bind_param("i", $did);
        $stmt->execute();
        $stmt->bind_result($version);
        $stmt->fetch();
        $stmt->close();

        foreach ($reviewers as $rid) {
            $check = $conn->prepare("SELECT * FROM document_reviewers WHERE did = ? AND version = ? AND reviewer_id = ?");
            $check->bind_param("iii", $did, $version, $rid);
            $check->execute();
            $result = $check->get_result();
            if ($result->num_rows === 0) {
                $stmt = $conn->prepare("INSERT INTO document_reviewers 
                    (did, version, reviewer_id, review_status, review_date, assigned_date, assigned_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisssi", $did, $version, $rid, $review_status, $review_date, $assigned_date, $_SESSION['uid']);
                $stmt->execute();
            }
        }

        echo "Reviewers assigned successfully.";

    } elseif ($action === 'add_reviewer' && isset($_POST['firstname'], $_POST['lastname'], $_POST['email'])) {
        $fname = $_POST['firstname'];
        $lname = $_POST['lastname'];
        $email = $_POST['email'];
        $password = 'password';

        if (!empty($fname) && !empty($lname) && !empty($email)) {
            $result = $conn->query("SELECT MAX(uid) AS max_uid FROM users");
            $row = $result->fetch_assoc();
            $new_uid = $row['max_uid'] + 1;

            $stmt = $conn->prepare("INSERT INTO users (uid, firstname, lastname, email, password, role) 
                                    VALUES (?, ?, ?, ?, ?, 'reviewer')");
            $stmt->bind_param("issss", $new_uid, $fname, $lname, $email, $password);
            if ($stmt->execute()) {
                echo "Reviewer added successfully.";
                
            } else {
                http_response_code(500);
                echo "Error adding reviewer.";
            }
        } else {
            http_response_code(400);
            echo "Please fill in all fields.";
        }

    } else {
        http_response_code(400);
        echo "Invalid request.";
    }
}
?>
