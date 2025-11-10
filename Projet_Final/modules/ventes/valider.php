<?php
// modules/ventes/valider.php - Validation et enregistrement des ventes
// ⚠️ Ne pas faire session_start() : déjà fait dans index.php

require_once __DIR__ . '/../../include/db.php';

// Log de débogage
error_log("=== DÉBUT VALIDATION VENTE ===");
error_log("GET params: " . print_r($_GET, true));
error_log("Panier: " . print_r($_SESSION['temp_cart'] ?? [], true));

try {
    $pdo = getDB();
    
    // Récupérer les paramètres
    $client_id = $_GET['client_id'] ?? null;
    $payment_method = $_GET['payment'] ?? 'CB';
    $channel = $_GET['channel'] ?? 'Web';
    
    error_log("Client ID: $client_id, Payment: $payment_method, Channel: $channel");
    
    // Vérifier le panier
    if (empty($_SESSION['temp_cart'])) {
        throw new Exception("Le panier est vide");
    }
    
    if (!$client_id) {
        throw new Exception("Client non spécifié");
    }
    
    // Vérifier que le client existe
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, type FROM clients WHERE id = ? AND active = 1");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if (!$client) {
        throw new Exception("Client introuvable (ID: $client_id)");
    }
    
    error_log("Client trouvé: {$client['first_name']} {$client['last_name']}");
    
    // Démarrer la transaction
    $pdo->beginTransaction();
    error_log("Transaction démarrée");
    
    // Calculer les totaux
    $subtotal = 0;
    foreach ($_SESSION['temp_cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $tax = $subtotal * 0.20;
    $total = $subtotal + $tax;
    
    error_log("Totaux calculés - Subtotal: $subtotal, Tax: $tax, Total: $total");
    
    // Générer le numéro de vente unique
    $sale_number = 'VTE-' . date('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    
    // Vérifier l'unicité du numéro
    $stmt = $pdo->prepare("SELECT id FROM sales WHERE sale_number = ?");
    $stmt->execute([$sale_number]);
    if ($stmt->fetch()) {
        // Si doublon, régénérer
        $sale_number = 'VTE-' . date('YmdHis') . '-' . mt_rand(100, 999);
    }
    
    error_log("Numéro de vente généré: $sale_number");
    
    // Déterminer le statut de paiement selon le canal et le rôle
    $is_client = ($_SESSION['role'] ?? '') === 'client';
    $payment_status = ($channel === 'Web' && $is_client) ? 'pending' : 'paid';
    $sale_status = ($payment_status === 'pending') ? 'pending' : 'completed';
    
    error_log("Statuts - Payment: $payment_status, Sale: $sale_status");
    
    // Récupérer l'employee_id si c'est un employé qui vend
    $employee_id = null;
    if (in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'employee'])) {
        $stmt_emp = $pdo->prepare("SELECT employee_id FROM users WHERE id = ?");
        $stmt_emp->execute([$_SESSION['user_id']]);
        $employee_id = $stmt_emp->fetchColumn();
        error_log("Employee ID: " . ($employee_id ?? 'NULL'));
    }
    
    // Créer la vente
    $stmt = $pdo->prepare("
        INSERT INTO sales (
            sale_number,
            client_id,
            employee_id,
            channel,
            status,
            subtotal,
            tax,
            total,
            payment_method,
            payment_status,
            sale_date,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $result = $stmt->execute([
        $sale_number,
        $client_id,
        $employee_id,
        $channel,
        $sale_status,
        $subtotal,
        $tax,
        $total,
        $payment_method,
        $payment_status
    ]);
    
    if (!$result) {
        throw new Exception("Erreur lors de la création de la vente");
    }
    
    $sale_id = $pdo->lastInsertId();
    error_log("Vente créée avec ID: $sale_id");
    
    // Créer les lignes de vente et mettre à jour le stock
    $stmt_line = $pdo->prepare("
        INSERT INTO sale_lines (
            sale_id,
            product_id,
            size,
            quantity,
            unit_price,
            total
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt_check_stock = $pdo->prepare("
        SELECT quantity FROM stock 
        WHERE product_id = ? AND size = ?
    ");
    
    $stmt_update_stock = $pdo->prepare("
        UPDATE stock 
        SET quantity = quantity - ? 
        WHERE product_id = ? AND size = ?
    ");
    
    foreach ($_SESSION['temp_cart'] as $item) {
        $line_total = $item['price'] * $item['quantity'];
        
        error_log("Traitement article: Product {$item['product_id']}, Size {$item['size']}, Qty {$item['quantity']}");
        
        // Vérifier le stock disponible
        $stmt_check_stock->execute([$item['product_id'], $item['size']]);
        $stock_row = $stmt_check_stock->fetch();
        
        if (!$stock_row) {
            throw new Exception("Aucun stock trouvé pour le produit " . $item['model'] . " (Taille " . $item['size'] . ")");
        }
        
        $available_stock = $stock_row['quantity'];
        error_log("Stock disponible: $available_stock");
        
        if ($available_stock < $item['quantity']) {
            throw new Exception("Stock insuffisant pour le produit " . $item['model'] . " (Taille " . $item['size'] . "). Disponible: $available_stock, Demandé: {$item['quantity']}");
        }
        
        // Créer la ligne de vente
        $line_result = $stmt_line->execute([
            $sale_id,
            $item['product_id'],
            $item['size'],
            $item['quantity'],
            $item['price'],
            $line_total
        ]);
        
        if (!$line_result) {
            throw new Exception("Erreur lors de la création de la ligne de vente");
        }
        
        error_log("Ligne de vente créée pour produit {$item['product_id']}");
        
        // Décrémenter le stock
        $update_result = $stmt_update_stock->execute([
            $item['quantity'],
            $item['product_id'],
            $item['size']
        ]);
        
        if (!$update_result || $stmt_update_stock->rowCount() === 0) {
            throw new Exception("Erreur lors de la mise à jour du stock pour " . $item['model']);
        }
        
        $new_stock = $available_stock - $item['quantity'];
        error_log("Stock mis à jour: $available_stock -> $new_stock");
    }
    
    // Mettre à jour le total dépensé du client (seulement si payé)
    if ($payment_status === 'paid') {
        $stmt = $pdo->prepare("
            UPDATE clients
            SET total_spent = total_spent + ?,
                loyalty_points = loyalty_points + ?
            WHERE id = ?
        ");
        $points = floor($total / 10); // 1 point par 10€
        $stmt->execute([$total, $points, $client_id]);
        error_log("Client mis à jour: +$total€, +$points points");
    }
    
    // Valider la transaction
    $pdo->commit();
    error_log("Transaction validée avec succès");
    
    // Vider le panier
    $_SESSION['temp_cart'] = [];
    error_log("Panier vidé");
    
    // Message de succès adapté avec style HTML
    if ($sale_status === 'pending') {
        $_SESSION['success'] = "
            <div style='text-align: center;'>
                <div style='font-size: 3rem; margin-bottom: 1rem;'>✅</div>
                <h3 style='margin-bottom: 0.5rem; color: #2ed573;'>Commande créée avec succès !</h3>
                <p style='font-size: 1.2rem; margin: 1rem 0;'><strong>N° $sale_number</strong></p>
                <p style='color: #ffa502;'>⏳ En attente de validation du paiement</p>
                <p style='margin-top: 1rem; font-size: 1.3rem;'><strong>" . number_format($total, 2, ',', ' ') . " €</strong></p>
            </div>
        ";
    } else {
        $_SESSION['success'] = "
            <div style='text-align: center;'>
                <div style='font-size: 3rem; margin-bottom: 1rem;'>🎉</div>
                <h3 style='margin-bottom: 0.5rem; color: #2ed573;'>Vente enregistrée avec succès !</h3>
                <p style='font-size: 1.2rem; margin: 1rem 0;'><strong>N° $sale_number</strong></p>
                <p><strong>Client :</strong> {$client['first_name']} {$client['last_name']}</p>
                <p style='margin-top: 1rem; font-size: 1.5rem; color: var(--primary);'><strong>" . number_format($total, 2, ',', ' ') . " €</strong></p>
                <p style='color: var(--secondary); margin-top: 0.5rem; font-size: 0.9rem;'>
                    💳 {$payment_method} • " . ($channel === 'Web' ? '🌐' : '🏪') . " {$channel}
                </p>
            </div>
        ";
    }
    
    error_log("Message de succès défini");
    error_log("=== FIN VALIDATION VENTE - SUCCÈS ===");
    
    // Redirection selon le rôle
    if ($_SESSION['role'] === 'client') {
        header('Location: ../../index.php?module=client&action=commandes');
    } else {
        header('Location: ../../index.php?module=ventes');
    }
    exit;
    
} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("Transaction annulée");
    }
    
    // Log de l'erreur détaillé
    error_log("=== ERREUR VALIDATION VENTE ===");
    error_log("Message: " . $e->getMessage());
    error_log("File: " . $e->getFile());
    error_log("Line: " . $e->getLine());
    error_log("Trace: " . $e->getTraceAsString());
    
    // Message d'erreur pour l'utilisateur
    $_SESSION['error'] = "
        <div style='text-align: center;'>
            <div style='font-size: 3rem; margin-bottom: 1rem;'>❌</div>
            <h3 style='margin-bottom: 1rem; color: #ff4757;'>Erreur lors de la validation</h3>
            <p style='font-size: 1.1rem;'>" . htmlspecialchars($e->getMessage()) . "</p>
            <p style='margin-top: 1rem; color: var(--secondary); font-size: 0.9rem;'>
                Veuillez réessayer ou contacter le support si le problème persiste.
            </p>
        </div>
    ";
    
    // Redirection vers le panier
    $redirect = '../../index.php?module=ventes&action=panier';
    if (isset($client_id)) {
        $redirect .= "&client_id=$client_id";
    }
    header('Location: ' . $redirect);
    exit;
}
?>