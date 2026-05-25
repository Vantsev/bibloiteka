<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$conn = new mysqli("localhost", "root", "");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Automatically create the database for this specific lab project if it doesn't exist yet
$conn->query("CREATE DATABASE IF NOT EXISTS library_lab4s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db("library_lab4s");
$conn->set_charset("utf8mb4");
?>