<?php
$servername = "localhost"; // Your database server name
$username = "root"; // Your database username
$password = ""; // Your database password
$dbname = "revue"; // The name of your database  

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Optional: Set character set
$conn->set_charset("utf8mb4");

// You can now use the $conn variable to execute SQL queries
// Remember to close the connection when done: $conn->close();
?>
