<?php
session_start(); // Start a new session or resume existing one

// Check if user is logged in (session has user_id)
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is an admin (session has is_admin set to true)
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true;
}

// Require login, if not logged in, redirect to index.php
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// Require admin → first check login, then check admin role
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: missions.php');
        exit;
    }
}

// Get logged-in user's full name 
function getUserName() {
    return $_SESSION['full_name'] ?? 'User';
}

function getUserEmail() {
    return $_SESSION['email'] ?? '';
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatTime($time) {
    return date('g:i A', strtotime($time));
}

function redirect($url) {
    header("Location: $url");
    exit;
}
?>
