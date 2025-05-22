<?php
$host = 'localhost';
$db   = 'revue';  
$user = 'root';               
$pass = '';                    

$dsn = "mysqli:host=$host;dbname=$db;";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}
?>
