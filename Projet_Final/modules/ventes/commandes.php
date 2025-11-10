<?php
// client/commandes.php - Mes commandes (espace client)

require_once __DIR__ . '/../include/db.php';

// Vérifier que l'utilisateur est bien un client
if ($_SESSION['role'] !== 'client') {
    header('Location: ../index.php');
    exit;
}

try {
    $pdo = getDB();
    
    // Récupérer l'ID du client
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $client_id = $stmt->fetchColumn();
    
    if (!$client_id) {
        throw new Exception("Client introuvable");
    }
    
    // Traitement de la validation de paiement
    if (isset($_POST['validate_payment'])) {
        $sale_id = $_POST['sale_id'];
        
        $stmt = $pdo->prepare("
            UPDATE sales 
            SET payment_status = 'paid', 
                status = 'completed' 
            WHERE id = ? AND client_id = ? AND status = 'pending'
        ");
        $stmt->execute([$sale_id, $client_id]);
        
        // Mettre à jour le total dépensé
        $stmt = $pdo->prepare("SELECT total FROM sales WHERE id = ?");
        $stmt->execute([$sale_id]);
        $total = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            UPDATE clients
            SET total_spent = total_spent + ?,
                loyalty_points = loyalty_points + ?
            WHERE id = ?
        ");
        $points = floor($total / 10);
        $stmt->execute([$total, $points, $client_id]);
        
        $_SESSION['success'] = "Paiement validé avec succès !";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Récupérer les commandes du client
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.sale_number,
            s.sale_date,
            s.channel,
            s.status,
            s.payment_status,
            s.payment_method,
            s.total,
            COUNT(sl.id) as items_count,
            GROUP_CONCAT(
                CONCAT(p.model, ' (T.', sl.size, ') x', sl.quantity) 
                SEPARATOR '<br>'
            ) as items_detail
        FROM sales s
        LEFT JOIN sale_lines sl ON s.id = sl.sale_id
        LEFT JOIN products p ON sl.product_id = p.id
        WHERE s.client_id = ?
        GROUP BY s.id
        ORDER BY s.sale_date DESC
    ");
    $stmt->execute([$client_id]);
    $orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erreur commandes client : " . $e->getMessage());
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes</title>
</head>
<body>

<div class="container" style="padding: 2rem;">
    
    <!-- En-tête -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h2>📦 Mes Commandes</h2>
            <p style="color: var(--secondary); margin: 0;">Historique de vos achats</p>
        </div>
        <a href="?module=ventes&action=panier" class="btn btn-primary">
            🛍️ Nouvelle commande
        </a>
    </div>
    
    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div style="background: rgba(46, 213, 115, 0.2); border-left: 4px solid #2ed573; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #2ed573;">
            ✓ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div style="background: rgba(255, 71, 87, 0.2); border-left: 4px solid #ff4757; padding: 1rem; margin-bottom: 1rem; border-radius: 4px; color: #ff4757;">
            ✗ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Liste des commandes -->
    <?php if (empty($orders)): ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
            <h3 style="margin-bottom: 0.5rem;">Aucune commande</h3>
            <p style="color: var(--secondary);">Vous n'avez pas encore passé de commande.</p>
            <a href="?module=ventes&action=panier" class="btn btn-primary" style="margin-top: 1rem;">
                Passer une commande
            </a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Date</th>
                        <th>Articles</th>
                        <th>Canal</th>
                        <th>Montant</th>
                        <th>Paiement</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($order['sale_number']); ?></strong></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($order['sale_date'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" 
                                    onclick="showOrderDetails('<?php echo htmlspecialchars($order['items_detail']); ?>')">
                                👁️ Voir (<?php echo $order['items_count']; ?> articles)
                            </button>
                        </td>
                        <td>
                            <?php if ($order['channel'] === 'Web'): ?>
                                <span class="badge" style="background: rgba(108,92,231,0.2); color: #6C5CE7;">🌐 Web</span>
                            <?php else: ?>
                                <span class="badge info">🏪 Boutique</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo number_format($order['total'], 2, ',', ' '); ?> €</strong></td>
                        <td>
                            <?php if ($order['payment_status'] === 'paid'): ?>
                                <span class="badge success">✓ Payé</span>
                            <?php elseif ($order['payment_status'] === 'pending'): ?>
                                <span class="badge warning">⏳ En attente</span>
                            <?php else: ?>
                                <span class="badge danger">✗ Remboursé</span>
                            <?php endif; ?>
                            <br>
                            <small style="color: var(--secondary);">
                                <?php 
                                $payment_icons = [
                                    'CB' => '💳 Carte',
                                    'Especes' => '💵 Espèces',
                                    'Virement' => '🏦 Virement',
                                    'Cheque' => '📝 Chèque'
                                ];
                                echo $payment_icons[$order['payment_method']] ?? $order['payment_method'];
                                ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($order['status'] === 'completed'): ?>
                                <span class="badge success">✓ Complétée</span>
                            <?php elseif ($order['status'] === 'pending'): ?>
                                <span class="badge warning">⏳ En attente</span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span class="badge danger">✗ Annulée</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(108,92,231,0.2); color: #6C5CE7;">
                                    ↩️ Remboursée
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($order['payment_status'] === 'pending' && $order['status'] === 'pending'): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Confirmer le paiement de cette commande ?');">
                                    <input type="hidden" name="sale_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="validate_payment" class="btn btn-sm btn-primary">
                                        💳 Valider le paiement
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-sm btn-secondary" disabled>
                                    ✓ Payée
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Statistiques -->
    <?php
    $stats = [
        'total_orders' => count($orders),
        'total_spent' => array_sum(array_column($orders, 'total')),
        'pending_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'pending')),
        'completed_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'completed'))
    ];
    ?>
    
    <div class="cards-grid" style="margin-top: 2rem;">
        <div class="card">
            <div class="card-label">Total commandé</div>
            <div class="card-value"><?php echo number_format($stats['total_spent'], 2, ',', ' '); ?> €</div>
        </div>
        <div class="card">
            <div class="card-label">Commandes totales</div>
            <div class="card-value"><?php echo $stats['total_orders']; ?></div>
        </div>
        <div class="card">
            <div class="card-label">En attente</div>
            <div class="card-value"><?php echo $stats['pending_orders']; ?></div>
        </div>
        <div class="card">
            <div class="card-label">Complétées</div>
            <div class="card-value"><?php echo $stats['completed_orders']; ?></div>
        </div>
    </div>
    
</div>

<!-- Modal détails commande -->
<div id="orderDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📦 Détails de la commande</h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <div id="orderDetailsContent" style="padding: 1rem;"></div>
    </div>
</div>

<script>
function showOrderDetails(itemsHtml) {
    document.getElementById('orderDetailsContent').innerHTML = itemsHtml;
    document.getElementById('orderDetailsModal').classList.add('active');
}

function closeModal() {
    document.getElementById('orderDetailsModal').classList.remove('active');
}
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.close-modal {
    font-size: 2rem;
    cursor: pointer;
    color: var(--secondary);
    transition: color 0.3s;
}

.close-modal:hover {
    color: var(--light);
}
</style>

</body>
</html>