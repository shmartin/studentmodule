<?php
// Include your database connection file
include 'db_connect.php';

// Start a session
session_start();

// Check if the user is already logged in and is a student, if yes then redirect him to student dashboard
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("location: student_dashboard.php");
    exit;
}

$email = $password = "";
$login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get email and password from the form
    $email = $_POST['email'];
    $password = $_POST['password']; // Remember: In a real app, hash passwords!

    // Prepare a select statement
    // Check for both email, password, AND the 'student' role
    $sql = "SELECT uid, firstname, lastname, role FROM users WHERE email = ? AND password = ? AND role = 'student'";

    if ($stmt = $conn->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("ss", $email, $password);

        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            // Store result
            $stmt->store_result();

            // Check if a student user with these credentials exists
            if ($stmt->num_rows == 1) {
                // Bind result variables
                $stmt->bind_result($uid, $firstname, $lastname, $role);
                if ($stmt->fetch()) {
                    // Credentials are correct and user is a student, start a new session
                    session_regenerate_id(true); // Regenerate session ID for security
                    $_SESSION['loggedin'] = TRUE;
                    $_SESSION['uid'] = $uid;
                    $_SESSION['firstname'] = $firstname;
                    $_SESSION['lastname'] = $lastname;
                    $_SESSION['role'] = $role; // Store the role

                    // Redirect to student dashboard page
                    header("location: student_dashboard.php");
                    exit; // Stop script execution after redirect
                }
            } else {
                // Display an error message if email or password (or role) is not valid
                $login_err = "Invalid email or password.";
            }
        } else {
            echo "Oops! Something went wrong. Please try again later.";
        }

        // Close statement
        $stmt->close();
    }
}

// Close connection (only if the connection was opened, and not redirected)
if (isset($conn) && $conn) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Login - Revue System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <style>
        body {
            font-family: 'Arial', sans-serif;
            /* Use the same background as the dashboard */
            background-color: #f4f7f6;
            margin: 0;
            display: flex;
            flex-direction: column; /* Stack header and login form vertically */
            align-items: center; /* Center content horizontally */
            min-height: 100vh;
             padding-top: 0; /* Adjust if necessary */
             box-sizing: border-box;
        }

        .header {
            background-color: #0d47a1; /* Dark blue background color from the image */
            color: white;
             /* Adjust padding for the login page header (no user info on the right) */
            padding: 30px 25px 15px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between; /* Use space-between to match dashboard layout */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
            width: 100%; /* Make header full width */
             position: relative;
             margin-bottom: 40px; /* Space between header and login card */
        }

        .header-left {
            display: flex;
            align-items: center;
            flex-shrink: 0;
             margin-top: -15px;
             /* Adjust margin-right if you want space between left section and center title */
             /* In space-between layout, margin-right might not be needed here */
             /* margin-right: 20px; */
        }

        .header .logo img {
            height: 80px; /* Increased height (adjust as needed) */
            margin-right: 15px;
            margin-top: -30px; /* Pulls the logo up */
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
             flex-grow: 1; /* Allow the center title to grow and take space like on dashboard */
            text-align: center;
            font-size: 20px; /* Adjust font size as needed */
            font-weight: bold;
            color: white;
             /* Adjust margin-top to align with the visual top of the header */
            margin-top: -15px;
             padding: 0 20px; /* Add padding to prevent text from touching sides */
             /* Adjust margin-left if you want space between left section and center title */
             /* In space-between layout, margin-left might not be needed here */
             /* margin-left: 20px; */
        }

        /* Hide the user info section on the login page */
        .user-info {
            display: none;
        }

        .login-card {
            background-color: #fff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 350px;
            text-align: center;
            /* Adjust margin-top if needed to fine-tune vertical alignment */
             margin-top: 20px;
        }

        .login-card .lock-icon {
            font-size: 40px;
            color: #ffab40;
            margin-bottom: 15px;
        }

        .login-card h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #333;
            font-size: 24px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon input[type="text"],
        .input-with-icon input[type="password"] {
            width: calc(100% - 40px);
            padding: 12px 12px 12px 35px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .input-with-icon .icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 18px;
        }

        .btn {
            background-color: #0277bd;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 17px;
            transition: background-color 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .btn:hover {
            background-color: #01579b;
        }

        .error-message {
            color: #d32f2f;
            margin-bottom: 20px;
            padding: 10px;
            background-color: #ffcdd2;
            border: 1px solid #ef9a9a;
            border-radius: 4px;
        }

        .forgot-password-links {
            margin-top: 20px;
            font-size: 14px;
        }

        .forgot-password-links a {
            color: #0277bd;
            text-decoration: none;
        }

        .forgot-password-links a:hover {
            text-decoration: underline;
        }

         /* Responsive adjustments for the header on smaller screens */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                 padding: 20px 15px 10px 15px;
                 margin-bottom: 20px; /* Adjust space below header when stacked */
            }
             .header-left, .center-title {
                 margin-top: 0; /* Remove negative margin when stacked */
                 margin-left: 0;
                 margin-right: 0;
                 margin-bottom: 10px; /* Add space between stacked items */
             }
            .header .logo img {
                margin-right: 0;
                margin-bottom: 10px;
                height: 60px; /* Adjust logo size for smaller screens */
                 margin-top: -20px; /* Adjust negative margin for smaller screens */
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
              .login-card {
                 margin-top: 0; /* Remove extra margin if header is stacked */
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
            </div>
    </div>

    <div class="login-card">
        <div class="lock-icon">
             <i class="fas fa-lock"></i> </div>
        <h2>User Login</h2>
        <?php
        if (!empty($login_err)) {
            echo '<div class="error-message">' . $login_err . '</div>';
        }
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="email" style="display: none;">Email:</label> <div class="input-with-icon">
                     <i class="fas fa-envelope icon"></i> <input type="text" id="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password" style="display: none;">Password:</label> <div class="input-with-icon">
                     <i class="fas fa-key icon"></i> <input type="password" id="password" name="password" placeholder="Password" required>
                </div>
            </div>
            <div>
                <input type="submit" class="btn" value="Login">
            </div>
        </form>

        <div class="forgot-password-links">
            <p><a href="#">Forgot Password?</a></p> <p><a href="#">- Account Request</a></p> </div>
    </div>
</body>
</html>

