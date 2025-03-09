<?php

// index.php - Entry point
require_once 'config.php';

// Check if database connection and schema exist
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
$dbExists = true;

if ($conn->connect_error) {
    $error = "Database connection error: " . $conn->connect_error;
    $dbExists = false;
} else {
    // Check if database exists
    $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    
    if ($result->num_rows == 0) {
        // Create database
        if ($conn->query("CREATE DATABASE " . DB_NAME)) {
            $conn->select_db(DB_NAME);
            
            // Set up tables and initial data
            setupDatabase();
            
            $success = "Database and tables created successfully. Default admin credentials:<br>
                        Email: " . MASTER_ADMIN_EMAIL . "<br>
                        Password: change_this_password<br>
                        <strong>Please login and change your password immediately.</strong>";
        } else {
            $error = "Could not create database: " . $conn->error;
            $dbExists = false;
        }
    } else {
        $conn->select_db(DB_NAME);
    }
    
    $conn->close();
}

// Check if user is already logged in
if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/dashboard.php");
    exit;
} else {
    // Redirect to login page
    header("Location: " . SITE_URL . "/login.php");
    exit;
}