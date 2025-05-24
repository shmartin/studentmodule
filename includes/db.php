<?php
$host = "localhost";
$db = "revue";
$user = "root";
$pass = "l-AsrqDE3yIztEjo";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
