<?php
// Cek apakah constant sudah didefine untuk prevent multiple inclusion
if (!defined('STUDENTHUB_CONFIG')) {
    define('STUDENTHUB_CONFIG', true);
    
    session_start();

    // Database configuration for XAMPP
    $host = "localhost";
    $username = "root"; 
    $password = ""; // Default XAMPP is empty
    $database = "studenthub";

    // Create connection
    $conn = new mysqli($host, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset to utf8
    $conn->set_charset("utf8");

    // Function untuk prevent SQL injection
    function sanitize($data) {
        global $conn;
        return mysqli_real_escape_string($conn, trim($data));
    }

    // Check if user is logged in
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Check user role
    function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
}
?>