<?php
session_start();
echo "<h1>Test Session</h1>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Non défini') . "\n";
echo "User Name: " . ($_SESSION['user_name'] ?? 'Non défini') . "\n";
echo "Role: " . ($_SESSION['role'] ?? 'Non défini') . "\n";
echo "</pre>";

// Test des URLs
echo "<h2>Test URLs:</h2>";
echo "<a href='index.php?module=ventes&action=select_client'>Sélection client</a><br>";
echo "<a href='index.php?module=ventes&action=panier'>Panier</a>";

// Option: Forcer un rôle pour tester
if (isset($_GET['setrole'])) {
    $_SESSION['role'] = $_GET['setrole'];
    header('Location: test_session.php');
}
?>
<br><br>
<a href="test_session.php?setrole=admin">Forcer rôle Admin</a><br>
<a href="test_session.php?setrole=employee">Forcer rôle Employee</a><br>
<a href="test_session.php?setrole=client">Forcer rôle Client</a>