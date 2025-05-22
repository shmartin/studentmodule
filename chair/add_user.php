<?php
session_start();
include 'dbcon.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['firstname']);
    $lname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $role = $_POST['role'] ?? 'student'; 
    $password = 'password'; 

    if (!empty($fname) && !empty($lname) && !empty($email)) {
        $stmt = $conn->prepare("SELECT uid FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $message = "Email already exists.";
        } else {
            $stmt->close();

            // Get next uid
            $result = $conn->query("SELECT MAX(uid) AS max_uid FROM users");
            $row = $result->fetch_assoc();
            $next_uid = $row['max_uid'] + 1;

            $stmt = $conn->prepare("INSERT INTO users (uid, firstname, lastname, email, password, role) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $next_uid, $fname, $lname, $email, $password, $role);

            if ($stmt->execute()) {
                $message = ucfirst($role) . " account added successfully!";
            } else {
                $message = "Error adding account.";
            }
        }
    } else {
        $message = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add User Account</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .tab-header {
      background-color: #28a745;
      color: white;
      padding: 15px;
      font-size: 1.4rem;
      border-radius: 0.5rem 0.5rem 0 0;
      text-align: center;
    }
    .btn-main {
      background-color: #28a745;
      color: white;
      border: none;
    }
    .btn-main:hover {
      background-color: #218838;
    }
  </style>
</head>
<body>
  <div class="container mt-5">
    <div class="card shadow">
      <div class="tab-header">
        Add New User Account
      </div>
      <div class="card-body">

        <?php if (isset($message)): ?>
          <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="mb-3">
            <label for="firstname" class="form-label">First Name</label>
            <input type="text" name="firstname" id="firstname" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="lastname" class="form-label">Last Name</label>
            <input type="text" name="lastname" id="lastname" class="form-control" required>
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" name="email" id="email" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Role</label><br>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="role" id="student" value="student" checked>
              <label class="form-check-label" for="student">Student</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="role" id="assign_chair" value="assign_chair">
              <label class="form-check-label" for="assign_chair">Assign Chair</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="role" id="reviewer" value="reviewer">
              <label class="form-check-label" for="reviewer">Reviewer</label>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Default Password</label>
            <input type="text" class="form-control" value="password" disabled hidden>
          </div>

          <button type="submit" class="btn btn-main">Create Account</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
