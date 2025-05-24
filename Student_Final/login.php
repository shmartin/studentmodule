<?php
include('includes/db.php');
session_start();

// Check if the user is already logged in and is a student, if yes then redirect to dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: dashboard.php");
    exit;
}

$email = $password = "";
$login_err = "";

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password']; // In a real app, passwords must be hashed

    // Query to validate login
    $sql = "SELECT uid, firstname, lastname, role FROM users WHERE email = ? AND password = ? AND role = 'student'";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $email, $password);
        if ($stmt->execute()) {
            $stmt->store_result();
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($uid, $firstname, $lastname, $role);
                if ($stmt->fetch()) {
                    // Login success - set session
                    session_regenerate_id(true);
                    $_SESSION['loggedin'] = true;
                    $_SESSION['uid'] = $uid;
                    $_SESSION['student_id'] = $uid; // 🔧 Required for dashboard.php
                    $_SESSION['firstname'] = $firstname;
                    $_SESSION['lastname'] = $lastname;
                    $_SESSION['role'] = $role;

                    header("Location: dashboard.php");
                    exit;
                }
            } else {
                $login_err = "Invalid email or password.";
            }
        } else {
            $login_err = "Oops! Something went wrong. Please try again later.";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Login</title>
  <link rel="stylesheet" href="css/login-styles2.css">
</head>
<body>
  <header>
    <div class="logo-container">
      <img src="images/SAS Logo.png" alt="Institution Logo" class="logo" />
      <div class="institution-name">
        <span class="page-title">STUDENT PAPER SUBMISSION PAGE</span>
        <span>SCHOOL OF ADVANCED STUDIES</span>
        <span class="university-name">Saint Louis University, Baguio City</span>
      </div>
    </div>
  </header>

  <div class="login-box">
    <div class="lock-icon">🔒</div>
    <h2>User Login</h2>
    <?php if (!empty($login_err)): ?>
      <p style="color: red; text-align:center;"><?php echo $login_err; ?></p>
    <?php endif; ?>
    <form action="login.php" method="POST">
      <div class="input-group">
        <label for="email">👤</label>
        <input type="text" id="email" name="email" placeholder="Email" required>
      </div>
      <div class="input-group">
        <label for="password">🔑</label>
        <input type="password" id="password" name="password" placeholder="Password" required>
      </div>
      <button type="submit">Login</button>
    </form>
    <div class="extra-links">
      <a href="forgot-password.html">Forgot Password?</a><br>
    </div>
  </div>
</body>
</html>

