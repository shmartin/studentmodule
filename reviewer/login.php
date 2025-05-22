<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reviewer Login</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;600&display=swap');

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: linear-gradient(135deg, #667eea, #764ba2);
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .login-container {
      background: white;
      padding: 40px 30px;
      border-radius: 15px;
      box-shadow: 0 15px 25px rgba(0, 0, 0, 0.2);
      width: 350px;
      animation: fadeIn 0.8s ease;
      text-align: center;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .login-container h2 {
      margin-bottom: 25px;
      color: #333;
    }

    input {
      width: 100%;
      padding: 12px;
      margin: 10px 0 20px;
      border: 1px solid #ccc;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    input:focus {
      border-color: #764ba2;
      outline: none;
      box-shadow: 0 0 5px rgba(118, 75, 162, 0.5);
    }

    input[type="submit"] {
      background: #764ba2;
      color: white;
      border: none;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    input[type="submit"]:hover {
      background: #5f3a91;
    }

    .footer-note {
      margin-top: 15px;
      font-size: 0.85em;
      color: #888;
    }
  </style>
</head>
<body>

<div class="login-container">
  <h2>Reviewer Login</h2>
  <form method="POST">
    <input type="email" name="email" placeholder="Email" required />
    <input type="password" name="password" placeholder="Password" required />
    <input type="submit" value="Login" />
  </form>
  <div class="footer-note">Forgot password? Contact admin.</div>
</div>

</body>
</html>

<!-- <?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = "SELECT uid, password FROM users WHERE email = '$email' AND role = 'reviewer'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        if ($password === $row['password']) {
            $_SESSION['reviewer_id'] = $row['uid'];
            header("Location: reviewer.php");
            exit();
        } else {
            echo "Wrong password.";
        }
    } else {
        echo "Reviewer not found.";
    }
}
?>

<form method="POST">
    Email: <input type="email" name="email" required><br>
    Password: <input type="password" name="password" required><br>
    <input type="submit" value="Login">
</form>
 -->