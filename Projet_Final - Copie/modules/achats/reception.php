<?php
/**
 * modules/achats/reception.php
 * Réception de commande fournisseur - VERSION CORRIGÉE
 */

// Vérification session admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../login.php');
    exit;
}

require_once __DIR__ . '/../../include/db.php';

$success = '';
$error = '';
$db = getDB();

// Récupérer l'ID de la commande
$purchase_id = $_GET['id'] ?? 0;

if (!$purchase_id) {
    header('Location: index.php?module=achats');
    exit;
}

// Traitement du formulaire de réception
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'receive') {
    try {
        $db->beginTransaction();
        
        // Récupérer les lignes de la commande
        $stmt = $db->prepare("
            SELECT pol.*, p.model, p.colorway
            FROM purchase_order_lines pol
            JOIN products p ON pol.product_id = p.id
            WHERE pol.purchase_order_id = ?
        ");
        $stmt->execute([$purchase_id]);
        $lines = $stmt->fetchAll();
        
        // Pour chaque ligne, mettre à jour le stock
        foreach ($lines as $line) {
            $quantity_received = $_POST['quantity_received'][$line['id']] ?? 0;
            
            if ($quantity_received > 0) {
                // Mettre à jour la quantité reçue dans la ligne de commande
                $stmt = $db->prepare("
                    UPDATE purchase_order_lines 
                    SET quantity_received = quantity_received + ?
                    WHERE id = ?
                ");
                $stmt->execute([$quantity_received, $line['id']]);
                
                // Vérifier si le stock existe déjà pour ce produit/taille
                $stmt = $db->prepare("
                    SELECT id, quantity 
                    FROM stock 
                    WHERE product_id = ? AND size = ?
                ");
                $stmt->execute([$line['product_id'], $line['size']]);
                $stock = $stmt->fetch();
                
                if ($stock) {
                    // Mettre à jour le stock existant
                    $stmt = $db->prepare("
                        UPDATE stock 
                        SET quantity = quantity + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$quantity_received, $stock['id']]);
                } else {
                    // Créer une nouvelle entrée de stock
                    $stmt = $db->prepare("
                        INSERT INTO stock (product_id, size, quantity, location)
                        VALUES (?, ?, ?, 'Entrepôt')
                    ");
                    $stmt->execute([$line['product_id'], $line['size'], $quantity_received]);
                }
                
                // Enregistrer le mouvement de stock
                $stmt = $db->prepare("
                    INSERT INTO stock_movements 
                    (product_id, size, type, quantity, reference_type, reference_id, employee_id, notes, movement_date)
                    VALUES (?, ?, 'in', ?, 'purchase', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $line['product_id'],
                    $line['size'],
                    $quantity_received,
                    $purchase_id,
                    $_SESSION['employee_id'] ?? null,
                    "Réception commande - {$line['model']} {$line['colorway']}"
                ]);
            }
        }
        
        // Vérifier si toutes les quantités ont été reçues
        $stmt = $db->prepare("
            SELECT 
                SUM(quantity_ordered) as total_ordered,
                SUM(quantity_received) as total_received
            FROM purchase_order_lines
            WHERE purchase_order_id = ?
        ");
        $stmt->execute([$purchase_id]);
        $totals = $stmt->fetch();
        
        // Mettre à jour le statut de la commande
        $new_status = ($totals['total_received'] >= $totals['total_ordered']) ? 'received' : 'sent';
        $received_date = ($new_status === 'received') ? date('Y-m-d') : null;
        
        $stmt = $db->prepare("
            UPDATE purchase_orders 
            SET status = ?, received_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $received_date, $purchase_id]);
        
        $db->commit();
        
        // Message de succès selon le statut
        if ($new_status === 'received') {
            $success = "✅ Réception complète ! Toutes les quantités ont été reçues. Stock mis à jour.";
        } else {
            $success = "✅ Réception partielle enregistrée avec succès ! Stock mis à jour.";
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur lors de la réception : " . $e->getMessage();
    }
}

// Récupérer les informations de la commande
try {
    $stmt = $db->prepare("
        SELECT 
            po.*,
            s.name as supplier_name,
            s.email as supplier_email,
            s.phone as supplier_phone
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ?
    ");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch();
    
    if (!$purchase || $purchase['status'] !== 'sent') {
        header('Location: index.php?module=achats');
        exit;
    }
    
    // Lignes de commande
    $stmt = $db->prepare("
        SELECT 
            pol.*,
            p.model, p.colorway, p.sku, p.cost,
            b.name as brand_name,
            s.quantity as current_stock
        FROM purchase_order_lines pol
        JOIN products p ON pol.product_id = p.id
        JOIN brands b ON p.brand_id = b.id
        LEFT JOIN stock s ON s.product_id = pol.product_id AND s.size = pol.size
        WHERE pol.purchase_order_id = ?
        ORDER BY pol.created_at
    ");
    $stmt->execute([$purchase_id]);
    $lines = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>

<style>
    .reception-section {
        padding: 1rem 0;
    }

    .breadcrumb {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        color: var(--secondary);
        font-size: 0.9rem;
    }

    .breadcrumb a {
        color: var(--secondary);
        text-decoration: none;
    }

    .breadcrumb a:hover {
        color: var(--light);
    }

    .purchase-summary {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .summary-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .summary-title {
        font-size: 1.3rem;
        color: var(--light);
        font-weight: 700;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
    }

    .summary-item {
        text-align: center;
    }

    .summary-label {
        color: var(--secondary);
        font-size: 0.85rem;
        margin-bottom: 0.3rem;
    }

    .summary-value {
        color: var(--light);
        font-size: 1.2rem;
        font-weight: 700;
    }

    .reception-table {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead tr {
        border-bottom: 2px solid var(--glass-border);
    }

    th {
        padding: 1rem;
        text-align: left;
        color: var(--secondary);
        font-weight: 600;
        font-size: 0.9rem;
    }

    td {
        padding: 1rem;
        color: var(--light);
        border-bottom: 1px solid var(--glass-border);
    }

    .product-info {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .product-brand {
        color: var(--secondary);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .product-name {
        color: var(--light);
        font-weight: 600;
    }

    .product-details {
        color: var(--light);
        opacity: 0.7;
        font-size: 0.85rem;
    }

    .quantity-input {
        width: 100px;
        padding: 0.6rem;
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        color: var(--light);
        text-align: center;
        font-size: 1rem;
        font-weight: 600;
    }

    .quantity-input:focus {
        outline: none;
        border-color: var(--secondary);
    }

    .stock-info {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .stock-current {
        color: var(--secondary);
        font-size: 0.85rem;
    }

    .stock-after {
        color: var(--success);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .progress-bar {
        height: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
        overflow: hidden;
        margin-top: 0.5rem;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        transition: width 0.3s ease;
    }

    .actions-bar {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
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

    .alert-info {
        background: rgba(78, 205, 196, 0.1);
        border: 1px solid var(--secondary);
        color: var(--secondary);
        padding: 1rem;
        border-radius: 10px;
        margin-top: 1.5rem;
    }

    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
</style>

<div class="reception-section">
    
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="index.php?module=achats">📦 Achats</a>
        <span>/</span>
        <span>Réception commande</span>
    </div>

    <?php if ($success): ?>
    <div class="alert-message alert-success">
        <?php echo $success; ?>
        <?php 
        // Si la commande est complètement reçue, afficher un bouton de retour
        $stmt = $db->prepare("SELECT status FROM purchase_orders WHERE id = ?");
        $stmt->execute([$purchase_id]);
        $current_status = $stmt->fetchColumn();
        if ($current_status === 'received'): 
        ?>
        <div style="margin-top: 1rem;">
            <a href="index.php?module=achats" class="btn btn-primary">
                ← Retour à la liste des achats
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-message alert-error">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Résumé de la commande -->
    <div class="purchase-summary">
        <div class="summary-header">
            <div>
                <div class="summary-title"><?php echo htmlspecialchars($purchase['order_number']); ?></div>
                <div style="color: var(--secondary); margin-top: 0.5rem;">
                    🏢 <?php echo htmlspecialchars($purchase['supplier_name']); ?>
                </div>
            </div>
            <span class="badge-status badge-sent">📧 Envoyée</span>
        </div>
        
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">📦 Articles</div>
                <div class="summary-value"><?php echo count($lines); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">📅 Date envoi</div>
                <div class="summary-value"><?php echo date('d/m/Y', strtotime($purchase['order_date'])); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">🚚 Livraison prévue</div>
                <div class="summary-value">
                    <?php echo $purchase['expected_date'] ? date('d/m/Y', strtotime($purchase['expected_date'])) : 'Non définie'; ?>
                </div>
            </div>
            <div class="summary-item">
                <div class="summary-label">💰 Montant Total</div>
                <div class="summary-value" style="color: var(--secondary);">
                    <?php echo number_format($purchase['total'], 2); ?>€
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de réception -->
    <div class="reception-table">
        <h3 style="color: var(--light); margin-bottom: 1.5rem;">📥 Enregistrer la réception</h3>
        
        <form method="POST" id="receptionForm">
            <input type="hidden" name="action" value="receive">
            
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Taille</th>
                        <th>Commandé</th>
                        <th>Déjà reçu</th>
                        <th>Stock actuel</th>
                        <th>Réceptionner</th>
                        <th>Nouveau stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
                    <?php 
                        $remaining = $line['quantity_ordered'] - $line['quantity_received'];
                        $current_stock = $line['current_stock'] ?? 0;
                    ?>
                    <tr>
                        <td>
                            <div class="product-info">
                                <span class="product-brand"><?php echo htmlspecialchars($line['brand_name']); ?></span>
                                <span class="product-name"><?php echo htmlspecialchars($line['model']); ?></span>
                                <span class="product-details">
                                    <?php echo htmlspecialchars($line['colorway']); ?> • <?php echo htmlspecialchars($line['sku']); ?>
                                </span>
                            </div>
                        </td>
                        <td><strong style="font-size: 1.1rem;"><?php echo $line['size']; ?></strong></td>
                        <td><strong><?php echo $line['quantity_ordered']; ?></strong></td>
                        <td>
                            <span style="color: <?php echo $line['quantity_received'] > 0 ? 'var(--success)' : 'var(--secondary)'; ?>;">
                                <?php echo $line['quantity_received']; ?>
                            </span>
                            <?php if ($line['quantity_received'] > 0): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo ($line['quantity_received'] / $line['quantity_ordered'] * 100); ?>%;"></div>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-weight: 600;"><?php echo $current_stock; ?></span>
                        </td>
                        <td>
                            <input type="number" 
                                   class="quantity-input" 
                                   name="quantity_received[<?php echo $line['id']; ?>]"
                                   min="0" 
                                   max="<?php echo $remaining; ?>"
                                   value="<?php echo $remaining; ?>"
                                   data-line-id="<?php echo $line['id']; ?>"
                                   data-current-stock="<?php echo $current_stock; ?>"
                                   onchange="updateNewStock(this)">
                            <div style="font-size: 0.8rem; color: var(--secondary); margin-top: 0.3rem;">
                                Reste: <?php echo $remaining; ?>
                            </div>
                        </td>
                        <td>
                            <div class="stock-info">
                                <span class="stock-after" id="new-stock-<?php echo $line['id']; ?>">
                                    <?php echo $current_stock + $remaining; ?>
                                </span>
                                <span style="color: var(--success); font-size: 0.85rem;" id="increase-<?php echo $line['id']; ?>">
                                    +<?php echo $remaining; ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="alert-info">
                <strong>ℹ️ Information:</strong> 
                La réception va automatiquement mettre à jour le stock de chaque produit et enregistrer les mouvements.
            </div>

            <div class="actions-bar">
                <a href="index.php?module=achats" class="btn btn-secondary">
                    Annuler
                </a>
                <button type="submit" class="btn btn-success" onclick="return confirm('Confirmer la réception de ces produits ?')">
                    ✅ Valider la réception
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateNewStock(input) {
    const lineId = input.dataset.lineId;
    const currentStock = parseInt(input.dataset.currentStock);
    const receivedQty = parseInt(input.value) || 0;
    const newStock = currentStock + receivedQty;
    
    document.getElementById('new-stock-' + lineId).textContent = newStock;
    document.getElementById('increase-' + lineId).textContent = '+' + receivedQty;
}
</script>