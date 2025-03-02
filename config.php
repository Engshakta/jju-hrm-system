<?php
$host = "localhost";
$username = "root";
$password = ""; // Adjust if you have a password
$database = "hrm_jigjiga";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>