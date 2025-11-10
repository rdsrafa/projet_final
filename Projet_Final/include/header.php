<?php
// include/header.php - Header global (métadonnées, sécurité)

// Démarrage de la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Fonction pour afficher les messages flash
function displayFlash() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        
        echo "<div class='alert alert-{$type} fade-in' style='margin-bottom: 2rem;'>";
        echo htmlspecialchars($message);
        echo "</div>";
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

// Fonction pour définir un message flash
function setFlash($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Protection CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonction pour obtenir l'URL actuelle
function getCurrentUrl() {
    return $_SERVER['REQUEST_URI'];
}

// Fonction pour rediriger
function redirect($url) {
    header("Location: $url");
    exit;
}

?>