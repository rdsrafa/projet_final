<?php
// modules/rh/process_leave.php - Traitement des demandes de congés

session_start();
require_once '../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $employee_id = intval($_POST['employee_id']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $days_count = intval($_POST['days_count']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    // Validation des données
    if (empty($employee_id) || empty($type) || empty($start_date) || empty($end_date) || $days_count < 1) {
        $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
        header('Location: ../../index.php?module=rh&action=conges');
        exit;
    }
    
    // Vérifier que la date de fin est après la date de début
    if (strtotime($end_date) < strtotime($start_date)) {
        $_SESSION['error'] = "La date de fin doit être après la date de début.";
        header('Location: ../../index.php?module=rh&action=conges');
        exit;
    }
    
    // Insertion dans la base de données
    $insert_query = "
        INSERT INTO leaves (employee_id, type, start_date, end_date, days_count, reason, status, created_at)
        VALUES ($employee_id, '$type', '$start_date', '$end_date', $days_count, " . 
        ($reason ? "'$reason'" : "NULL") . ", 'pending', NOW())
    ";
    
    if (mysqli_query($conn, $insert_query)) {
        $_SESSION['success'] = "Demande de congés enregistrée avec succès.";
    } else {
        $_SESSION['error'] = "Erreur lors de l'enregistrement: " . mysqli_error($conn);
    }
    
    header('Location: ../../index.php?module=rh&action=conges');
    exit;
} else {
    header('Location: ../../index.php?module=rh&action=conges');
    exit;
}
?>