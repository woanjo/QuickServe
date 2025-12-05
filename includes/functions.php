<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: missions.php');
        exit;
    }
}

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
