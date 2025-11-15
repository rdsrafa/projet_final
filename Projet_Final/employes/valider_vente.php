<?php
// employes/valider_vente.php - Validation des ventes pour employés
session_start();
require_once '../include/db.php';

// Vérifier les droits
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['employee', 'manager'])) {
    header('Location: ../login.php');
    exit();
}

try {
    $db = getDB();
    
    // Récupérer l'employee_id
    $stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $employee_data = $stmt->fetch();
    
    if (!$employee_data) {
        throw new Exception("Employé introuvable");
    }
    
    $employee_id = $employee_data['employee_id'];
    $client_id = $_GET['client_id'] ?? null;
    $payment_method = $_GET['payment'] ?? 'CB';
    $channel = $_GET['channel'] ?? 'Boutique';
    
    // Vérifications
    if (!$client_id) {
        throw new Exception("Aucun client sélectionné");
    }
    
    if (empty($_SESSION['temp_cart'])) {
        throw new Exception("Panier vide");
    }
    
    // Vérifier le client
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND active = 1");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if (!$client) {
        throw new Exception("Client introuvable");
    }
    
    // Démarrer la transaction
    $db->beginTransaction();
    
    // Calculer les totaux
    $subtotal = 0;
    foreach ($_SESSION['temp_cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $tax = $subtotal * 0.20;
    $total = $subtotal + $tax;
    
    // Générer le numéro de vente
    $sale_number = 'VTE-' . date('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // Créer la vente
    $stmt = $db->prepare("
        INSERT INTO sales (
            sale_number, client_id, employee_id, channel, status,
            subtotal, tax, total, payment_method, payment_status, sale_date
        ) VALUES (?, ?, ?, ?, 'completed', ?, ?, ?, ?, 'paid', NOW())
    ");
    
    $stmt->execute([
        $sale_number, $client_id, $employee_id, $channel,
        $subtotal, $tax, $total, $payment_method
    ]);
    
    $sale_id = $db->lastInsertId();
    
    // Créer les lignes de vente
    $stmt_line = $db->prepare("
        INSERT INTO sale_lines (sale_id, product_id, size, quantity, unit_price, total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt_stock = $db->prepare("
        UPDATE stock 
        SET quantity = quantity - ? 
        WHERE product_id = ? AND size = ?
    ");
    
    foreach ($_SESSION['temp_cart'] as $item) {
        $line_total = $item['price'] * $item['quantity'];
        
        // Ligne de vente
        $stmt_line->execute([
            $sale_id, $item['product_id'], $item['size'],
            $item['quantity'], $item['price'], $line_total
        ]);
        
        // Décrémenter le stock
        $stmt_stock->execute([
            $item['quantity'], $item['product_id'], $item['size']
        ]);
        
        // Mouvement de stock
        $db->prepare("
            INSERT INTO stock_movements 
            (product_id, size, type, quantity, reference_type, reference_id, movement_date)
            VALUES (?, ?, 'out', ?, 'sale', ?, NOW())
        ")->execute([$item['product_id'], $item['size'], $item['quantity'], $sale_id]);
    }
    
    // Mettre à jour le client
    $db->prepare("
        UPDATE clients
        SET total_spent = total_spent + ?,
            loyalty_points = loyalty_points + ?
        WHERE id = ?
    ")->execute([$total, floor($total / 10), $client_id]);
    
    // Valider la transaction
    $db->commit();
    
    // Vider le panier
    $_SESSION['temp_cart'] = [];
    
    // Message de succès
    $_SESSION['success'] = "
        <div style='text-align: center;'>
            <div style='font-size: 3rem; margin-bottom: 1rem;'>🎉</div>
            <h3 style='color: #2ed573;'>Vente enregistrée avec succès !</h3>
            <p style='font-size: 1.2rem; margin: 1rem 0;'><strong>N° $sale_number</strong></p>
            <p><strong>Client :</strong> {$client['first_name']} {$client['last_name']}</p>
            <p style='font-size: 1.5rem; color: var(--secondary); margin-top: 1rem;'>
                <strong>" . number_format($total, 2) . " €</strong>
            </p>
        </div>
    ";
    
    header('Location: ventes_list.php');
    exit;
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    $_SESSION['error'] = "
        <div style='text-align: center;'>
            <div style='font-size: 3rem; margin-bottom: 1rem;'>❌</div>
            <h3 style='color: #ff4757;'>Erreur lors de la validation</h3>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
        </div>
    ";
    
    header('Location: vente.php' . ($client_id ? "?client_id=$client_id" : ''));
    exit;
}