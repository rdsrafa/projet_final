<?php
/**
 * supplier/get_order_details.php
 * Récupère les détails d'un bon de commande pour le fournisseur
 */
session_start();

// Vérification de la connexion et du rôle
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'supplier') {
    echo '<p style="color: #FF6B6B;">Accès non autorisé</p>';
    exit;
}

require_once '../include/db.php';

$supplier_id = $_SESSION['supplier_id'];
$order_id = intval($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    echo '<p style="color: #FF6B6B;">Commande invalide</p>';
    exit;
}

try {
    $db = getDB();
    
    // Récupérer les informations du bon de commande
    $stmt = $db->prepare("
        SELECT po.*, s.name as supplier_name
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ? AND po.supplier_id = ?
    ");
    $stmt->execute([$order_id, $supplier_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo '<p style="color: #FF6B6B;">Commande non trouvée ou accès non autorisé</p>';
        exit;
    }
    
    // Récupérer les lignes de commande
    $stmt = $db->prepare("
        SELECT 
            pol.*, 
            p.model AS product_name, 
            p.sku AS category,
            p.description
        FROM purchase_order_lines pol
        LEFT JOIN products p ON pol.product_id = p.id
        WHERE pol.purchase_order_id = ?
        ORDER BY pol.id
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    // Calculer le total
    $total = 0;
    $total_qty = 0;
    foreach ($items as $item) {
        $total += $item['quantity_ordered'] * $item['unit_cost'];
        $total_qty += $item['quantity_ordered'];
    }
    
} catch (PDOException $e) {
    echo '<p style="color: #FF6B6B;">Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
?>

<style>
    .order-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2rem;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
    }

    .info-section h3 {
        color: #4ECDC4;
        font-size: 1rem;
        margin-bottom: 1rem;
        text-transform: uppercase;
        font-weight: 600;
    }

    .info-section p {
        color: #EAEAEA;
        margin-bottom: 0.5rem;
        line-height: 1.6;
    }

    .info-section strong {
        color: #FFE66D;
    }

    .order-items-table {
        width: 100%;
        border-collapse: collapse;
        margin: 2rem 0;
    }

    .order-items-table thead {
        background: rgba(255, 230, 109, 0.1);
    }

    .order-items-table th {
        padding: 1rem;
        text-align: left;
        color: #FFE66D;
        font-weight: 600;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
    }

    .order-items-table td {
        padding: 1rem;
        color: #EAEAEA;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .order-items-table tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .item-category {
        display: inline-block;
        padding: 0.3rem 0.8rem;
        background: rgba(78, 205, 196, 0.2);
        color: #4ECDC4;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .order-summary {
        background: rgba(255, 230, 109, 0.1);
        padding: 1.5rem;
        border-radius: 12px;
        margin-top: 2rem;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .summary-row:last-child {
        margin-bottom: 0;
        padding-top: 1rem;
        border-top: 2px solid rgba(255, 255, 255, 0.1);
    }

    .summary-label {
        color: #EAEAEA;
        font-size: 1rem;
    }

    .summary-value {
        font-weight: 700;
        font-size: 1.1rem;
        color: #FFE66D;
    }

    .summary-total {
        font-size: 1.8rem !important;
        background: linear-gradient(45deg, #FFE66D, #4ECDC4);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .status-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.9rem;
    }

    .status-draft {
        background: rgba(168, 168, 168, 0.2);
        color: #ccc;
    }

    .status-sent {
        background: rgba(255, 230, 109, 0.2);
        color: #FFE66D;
    }

    .status-received {
        background: rgba(78, 205, 196, 0.3);
        color: #44A08D;
    }

    .status-cancelled {
        background: rgba(255, 107, 107, 0.2);
        color: #FF6B6B;
    }

    @media (max-width: 768px) {
        .order-info-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .order-items-table {
            display: block;
            overflow-x: auto;
        }
    }
</style>

<div class="order-details">
    <!-- Informations de la commande -->
    <div class="order-info-grid">
        <div class="info-section">
            <h3>📋 Informations du bon de commande</h3>
            <p><strong>Numéro :</strong> #<?php echo htmlspecialchars($order['order_number']); ?></p>
            <p><strong>Date de commande :</strong> <?php echo date('d/m/Y', strtotime($order['order_date'])); ?></p>
            <p><strong>Statut :</strong> 
                <span class="status-badge status-<?php echo $order['status']; ?>">
                    <?php 
                    $status_labels = [
                        'draft' => '📝 Brouillon',
                        'sent' => '📤 Envoyée',
                        'received' => '✅ Reçue',
                        'cancelled' => '❌ Annulée'
                    ];
                    echo $status_labels[$order['status']] ?? $order['status'];
                    ?>
                </span>
            </p>
        </div>

        <div class="info-section">
            <h3>🚚 Livraison</h3>
            <?php if ($order['expected_date']): ?>
            <p><strong>Date prévue :</strong> <?php echo date('d/m/Y', strtotime($order['expected_date'])); ?></p>
            <?php else: ?>
            <p><strong>Date prévue :</strong> Non définie</p>
            <?php endif; ?>
            
            <?php if ($order['received_date']): ?>
            <p><strong>Date de réception :</strong> <?php echo date('d/m/Y à H:i', strtotime($order['received_date'])); ?></p>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <h3>🏢 Fournisseur</h3>
            <p><strong>Nom :</strong> <?php echo htmlspecialchars($order['supplier_name']); ?></p>
        </div>

        <div class="info-section">
            <h3>📝 Informations complémentaires</h3>
            <p><strong>Créé le :</strong> <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?></p>
            <?php if ($order['updated_at'] && $order['updated_at'] != $order['created_at']): ?>
            <p><strong>Modifié le :</strong> <?php echo date('d/m/Y à H:i', strtotime($order['updated_at'])); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Liste des articles -->
    <h3 style="color: #FFE66D; margin-bottom: 1rem;">📦 Articles commandés</h3>
    
    <?php if (empty($items)): ?>
        <p style="text-align: center; color: #4ECDC4; padding: 2rem;">
            Aucun article dans cette commande
        </p>
    <?php else: ?>
    <table class="order-items-table">
        <thead>
            <tr>
                <th>Produit</th>
                <th>Catégorie</th>
                <th>Prix unitaire</th>
                <th>Quantité</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($item['product_name'] ?: 'Produit #' . $item['product_id']); ?></strong>
                        <?php if ($item['description']): ?>
                        <br><small style="color: #4ECDC4; opacity: 0.8;"><?php echo htmlspecialchars($item['description']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item['category']): ?>
                        <span class="item-category">
                            <?php echo htmlspecialchars($item['category']); ?>
                        </span>
                        <?php else: ?>
                        <span style="color: #888;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($item['unit_cost'], 2); ?> €</td>
                    <td>x <?php echo $item['quantity_ordered']; ?></td>
                    <td><strong><?php echo number_format($item['quantity_ordered'] * $item['unit_cost'], 2); ?> €</strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Résumé -->
    <div class="order-summary">
        <div class="summary-row">
            <span class="summary-label">Nombre d'articles :</span>
            <span class="summary-value"><?php echo count($items); ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Quantité totale :</span>
            <span class="summary-value"><?php echo $total_qty; ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label"><strong>MONTANT TOTAL :</strong></span>
            <span class="summary-value summary-total"><?php echo number_format($total, 2); ?> €</span>
        </div>
    </div>

    <?php if (!empty($order['notes'])): ?>
    <div style="margin-top: 2rem; padding: 1rem; background: rgba(78, 205, 196, 0.1); border-left: 3px solid #4ECDC4; border-radius: 8px;">
        <strong style="color: #4ECDC4;">📝 Notes :</strong>
        <p style="color: #EAEAEA; margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($order['status'] === 'sent'): ?>
    <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(255, 230, 109, 0.1); border: 2px solid #FFE66D; border-radius: 12px;">
        <p style="color: #FFE66D; font-size: 1.1rem; margin-bottom: 1rem;">
            ⚠️ <strong>Cette commande est en attente de réception</strong>
        </p>
        <p style="color: #EAEAEA;">
            Une fois les produits reçus, pensez à confirmer la réception depuis la page des commandes.
        </p>
    </div>
    <?php endif; ?>
</div>