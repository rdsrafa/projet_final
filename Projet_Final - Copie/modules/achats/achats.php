<?php
/**
 * modules/achats/achats.php
 * Module de gestion des achats (bons de commande fournisseurs)
 */

// Vérification session admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../include/db.php';

$success = '';
$error = '';
$redirect_to_add = false;
$new_purchase_id = 0;
$db = getDB();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'create_purchase':
                // Créer un nouveau bon de commande
                $supplier_id = $_POST['supplier_id'];
                $expected_date = $_POST['expected_date'];
                $notes = $_POST['notes'] ?? '';
                
                // Générer numéro de commande
                $order_number = 'PO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO purchase_orders (order_number, supplier_id, status, expected_date, subtotal, total, notes)
                    VALUES (?, ?, 'draft', ?, 0, 0, ?)
                ");
                $stmt->execute([$order_number, $supplier_id, $expected_date, $notes]);
                
                $new_purchase_id = $db->lastInsertId();
                $redirect_to_add = true; // Flag pour redirection JS
                $success = "✅ Bon de commande $order_number créé avec succès !";
                break;
                
            case 'send_order':
                // Envoyer la commande au fournisseur
                $purchase_id = $_POST['purchase_id'];
                
                $stmt = $db->prepare("UPDATE purchase_orders SET status = 'sent', order_date = NOW() WHERE id = ?");
                $stmt->execute([$purchase_id]);
                
                $success = "📧 Commande envoyée au fournisseur !";
                break;
                
            case 'cancel_order':
                // Annuler une commande
                $purchase_id = $_POST['purchase_id'];
                
                $stmt = $db->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$purchase_id]);
                
                $success = "❌ Commande annulée.";
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupération des données
try {
    // Statistiques
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
            SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_count,
            COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total ELSE 0 END), 0) as total_amount
        FROM purchase_orders
    ")->fetch();
    
    // Liste des commandes
    $orders = $db->query("
        SELECT 
            po.*,
            s.name as supplier_name,
            s.email as supplier_email,
            COUNT(pol.id) as items_count
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN purchase_order_lines pol ON po.id = pol.purchase_order_id
        GROUP BY po.id
        ORDER BY po.created_at DESC
    ")->fetchAll();
    
    // Liste des fournisseurs approuvés
    $suppliers = $db->query("
        SELECT id, name, email, phone 
        FROM suppliers 
        WHERE status = 'approved' AND active = 1
        ORDER BY name ASC
    ")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
}
?>

<style>
    .achats-section {
        padding: 1rem 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-box {
        background: var(--glass);
        padding: 1.5rem;
        border-radius: 15px;
        border: 1px solid var(--glass-border);
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-label {
        color: var(--secondary);
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    .purchase-card {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .purchase-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .purchase-card.draft { border-left: 4px solid #FFE66D; }
    .purchase-card.sent { border-left: 4px solid #4ECDC4; }
    .purchase-card.received { border-left: 4px solid #A8E6CF; }
    .purchase-card.cancelled { border-left: 4px solid #FF6B6B; opacity: 0.7; }

    .purchase-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .purchase-number {
        font-size: 1.3rem;
        color: var(--light);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .supplier-name {
        color: var(--secondary);
        font-size: 0.95rem;
    }

    .purchase-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .info-label {
        font-size: 0.8rem;
        color: var(--secondary);
        opacity: 0.8;
    }

    .info-value {
        color: var(--light);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .purchase-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }

    .btn-action {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .btn-add-products {
        background: linear-gradient(45deg, #4ECDC4, #44A08D);
        color: white;
    }

    .btn-add-products:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
    }

    .btn-send {
        background: linear-gradient(45deg, #6C5CE7, #4ECDC4);
        color: white;
    }

    .btn-receive {
        background: linear-gradient(45deg, #A8E6CF, #4ECDC4);
        color: white;
    }

    .btn-cancel {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
        border: 1px solid var(--primary);
    }

    .badge-status {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .badge-draft {
        background: rgba(255, 230, 109, 0.2);
        color: #FFE66D;
    }

    .badge-sent {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
    }

    .badge-received {
        background: rgba(168, 230, 207, 0.2);
        color: #A8E6CF;
    }

    .badge-cancelled {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2rem;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .modal-header h3 {
        color: var(--light);
        font-size: 1.5rem;
    }

    .close-modal {
        font-size: 2rem;
        color: var(--secondary);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .close-modal:hover {
        color: var(--primary);
        transform: rotate(90deg);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        color: var(--secondary);
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .form-control {
        width: 100%;
        padding: 0.8rem;
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        color: var(--light);
        font-size: 1rem;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--secondary);
    }

    .alert-message {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        animation: slideIn 0.3s ease;
    }

    .alert-success {
        background: rgba(78, 205, 196, 0.2);
        border: 1px solid var(--secondary);
        color: var(--secondary);
    }

    .alert-error {
        background: rgba(255, 107, 107, 0.2);
        border: 1px solid var(--primary);
        color: var(--primary);
    }

    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--secondary);
        opacity: 0.7;
    }

    .empty-state h3 {
        color: var(--light);
        margin-bottom: 0.5rem;
    }
</style>

<div class="achats-section">
    
    <?php if ($success): ?>
    <div class="alert-message alert-success">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-message alert-error">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.5rem; color: var(--light); margin-bottom: 0.5rem;">📦 Module Achats</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Gestion des commandes fournisseurs</p>
        </div>
        <button class="btn btn-primary" onclick="showNewOrderModal()">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </svg>
            Nouvelle Commande
        </button>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
            <div class="stat-label">📊 Total Commandes</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['draft_count']; ?></div>
            <div class="stat-label">📝 Brouillons</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['sent_count']; ?></div>
            <div class="stat-label">📧 Envoyées</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['received_count']; ?></div>
            <div class="stat-label">✅ Reçues</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($stats['total_amount'], 0); ?>€</div>
            <div class="stat-label">💰 Montant Total</div>
        </div>
    </div>

    <!-- Liste des commandes -->
    <h3 style="color: var(--light); margin-bottom: 1rem;">📋 Bons de Commande</h3>
    
    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <h3>📦 Aucune commande</h3>
            <p>Créez votre première commande fournisseur !</p>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="purchase-card <?php echo $order['status']; ?>">
                <div class="purchase-header">
                    <div>
                        <div class="purchase-number"><?php echo htmlspecialchars($order['order_number']); ?></div>
                        <div class="supplier-name">🏢 <?php echo htmlspecialchars($order['supplier_name']); ?></div>
                    </div>
                    <span class="badge-status badge-<?php echo $order['status']; ?>">
                        <?php 
                        $status_labels = [
                            'draft' => '📝 Brouillon',
                            'sent' => '📧 Envoyée',
                            'received' => '✅ Reçue',
                            'cancelled' => '❌ Annulée'
                        ];
                        echo $status_labels[$order['status']];
                        ?>
                    </span>
                </div>

                <div class="purchase-info-grid">
                    <div class="info-item">
                        <span class="info-label">📅 Date création</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">🚚 Livraison prévue</span>
                        <span class="info-value">
                            <?php echo $order['expected_date'] ? date('d/m/Y', strtotime($order['expected_date'])) : 'Non définie'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📦 Articles</span>
                        <span class="info-value"><?php echo $order['items_count']; ?> produit(s)</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 Montant Total</span>
                        <span class="info-value"><?php echo number_format($order['total'], 2); ?>€</span>
                    </div>
                </div>

                <?php if ($order['notes']): ?>
                <div style="padding: 0.8rem; background: rgba(255, 255, 255, 0.03); border-radius: 8px; margin: 1rem 0;">
                    <span style="color: var(--secondary); font-size: 0.85rem;">📝 Notes:</span>
                    <p style="color: var(--light); margin: 0.3rem 0 0 0;"><?php echo htmlspecialchars($order['notes']); ?></p>
                </div>
                <?php endif; ?>

                <div class="purchase-actions">
                    <?php if ($order['status'] === 'draft'): ?>
                        <a href="index.php?module=achats&action=ajout&id=<?php echo $order['id']; ?>" 
                           class="btn-action btn-add-products">
                            ➕ Ajouter Produits
                        </a>
                        <?php if ($order['items_count'] > 0): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="purchase_id" value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="action" value="send_order">
                            <button type="submit" class="btn-action btn-send" onclick="return confirm('Envoyer cette commande au fournisseur ?')">
                                📧 Envoyer
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="purchase_id" value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="action" value="cancel_order">
                            <button type="submit" class="btn-action btn-cancel" onclick="return confirm('Annuler cette commande ?')">
                                ❌ Annuler
                            </button>
                        </form>
                    <?php elseif ($order['status'] === 'sent'): ?>
                        <a href="index.php?module=achats&action=reception&id=<?php echo $order['id']; ?>" 
                           class="btn-action btn-receive">
                            📥 Réceptionner
                        </a>
                    <?php elseif ($order['status'] === 'received'): ?>
                        <span style="color: var(--secondary); font-weight: 600;">
                            ✅ Commande réceptionnée le <?php echo date('d/m/Y', strtotime($order['received_date'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Nouvelle Commande -->
<div id="newOrderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📦 Nouvelle Commande Fournisseur</h3>
            <span class="close-modal" onclick="closeModal('newOrderModal')">&times;</span>
        </div>
        <form method="POST" id="newOrderForm">
            <input type="hidden" name="action" value="create_purchase">
            
            <div class="form-group">
                <label>Fournisseur *</label>
                <select class="form-control" name="supplier_id" required>
                    <option value="">Sélectionner un fournisseur...</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>">
                            <?php echo htmlspecialchars($supplier['name']); ?> - <?php echo htmlspecialchars($supplier['email']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Date de livraison prévue *</label>
                <input type="date" class="form-control" name="expected_date" 
                       min="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label>Notes / Instructions</label>
                <textarea class="form-control" name="notes" rows="4" 
                          placeholder="Instructions spéciales, conditions de livraison..."></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newOrderModal')">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    ✅ Créer et Ajouter Produits
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showNewOrderModal() {
    document.getElementById('newOrderModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Fermer modal en cliquant à l'extérieur
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Si redirection nécessaire après création
<?php if ($redirect_to_add && $new_purchase_id): ?>
setTimeout(function() {
    window.location.href = 'index.php?module=achats&action=ajout&id=<?php echo $new_purchase_id; ?>';
}, 1500); // Attendre 1.5s pour voir le message de succès
<?php endif; ?>
</script>