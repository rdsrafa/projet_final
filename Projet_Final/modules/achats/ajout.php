<?php
/**
 * modules/achats/ajout.php
 * Ajouter des produits à un bon de commande
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

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch($action) {
            case 'add_product':
                $product_id = $_POST['product_id'];
                $size = $_POST['size'];
                $quantity = $_POST['quantity'];
                
                // Récupérer le coût unitaire du produit
                $stmt = $db->prepare("SELECT cost FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    throw new Exception("Produit introuvable");
                }
                
                $unit_cost = $product['cost'];
                $total = $unit_cost * $quantity;
                
                // Vérifier si le produit existe déjà dans la commande
                $stmt = $db->prepare("
                    SELECT id, quantity_ordered 
                    FROM purchase_order_lines 
                    WHERE purchase_order_id = ? AND product_id = ? AND size = ?
                ");
                $stmt->execute([$purchase_id, $product_id, $size]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Mettre à jour la quantité
                    $new_quantity = $existing['quantity_ordered'] + $quantity;
                    $new_total = $unit_cost * $new_quantity;
                    
                    $stmt = $db->prepare("
                        UPDATE purchase_order_lines 
                        SET quantity_ordered = ?, total = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_quantity, $new_total, $existing['id']]);
                } else {
                    // Ajouter nouvelle ligne
                    $stmt = $db->prepare("
                        INSERT INTO purchase_order_lines 
                        (purchase_order_id, product_id, size, quantity_ordered, unit_cost, total)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$purchase_id, $product_id, $size, $quantity, $unit_cost, $total]);
                }
                
                // Mettre à jour le total de la commande
                updatePurchaseTotal($db, $purchase_id);
                
                $success = "✅ Produit ajouté à la commande !";
                break;
                
            case 'remove_line':
                $line_id = $_POST['line_id'];
                
                $stmt = $db->prepare("DELETE FROM purchase_order_lines WHERE id = ?");
                $stmt->execute([$line_id]);
                
                // Mettre à jour le total
                updatePurchaseTotal($db, $purchase_id);
                
                $success = "🗑️ Ligne supprimée.";
                break;
                
            case 'update_quantity':
                $line_id = $_POST['line_id'];
                $quantity = $_POST['quantity'];
                
                // Récupérer le coût unitaire
                $stmt = $db->prepare("SELECT unit_cost FROM purchase_order_lines WHERE id = ?");
                $stmt->execute([$line_id]);
                $line = $stmt->fetch();
                
                $total = $line['unit_cost'] * $quantity;
                
                $stmt = $db->prepare("
                    UPDATE purchase_order_lines 
                    SET quantity_ordered = ?, total = ?
                    WHERE id = ?
                ");
                $stmt->execute([$quantity, $total, $line_id]);
                
                // Mettre à jour le total
                updatePurchaseTotal($db, $purchase_id);
                
                $success = "✅ Quantité mise à jour.";
                break;
        }
    } catch (Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Fonction pour mettre à jour le total de la commande
function updatePurchaseTotal($db, $purchase_id) {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(total), 0) as subtotal 
        FROM purchase_order_lines 
        WHERE purchase_order_id = ?
    ");
    $stmt->execute([$purchase_id]);
    $result = $stmt->fetch();
    
    $subtotal = $result['subtotal'];
    $tax = $subtotal * 0.20; // TVA 20%
    $total = $subtotal + $tax;
    
    $stmt = $db->prepare("
        UPDATE purchase_orders 
        SET subtotal = ?, tax = ?, total = ?
        WHERE id = ?
    ");
    $stmt->execute([$subtotal, $tax, $total, $purchase_id]);
}

// Récupérer les informations de la commande
try {
    $stmt = $db->prepare("
        SELECT po.*, s.name as supplier_name 
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ?
    ");
    $stmt->execute([$purchase_id]);
    $purchase = $stmt->fetch();
    
    if (!$purchase || $purchase['status'] !== 'draft') {
        header('Location: index.php?module=achats');
        exit;
    }
    
    // Lignes de commande
    $stmt = $db->prepare("
        SELECT 
            pol.*,
            p.model, p.colorway, p.sku,
            b.name as brand_name
        FROM purchase_order_lines pol
        JOIN products p ON pol.product_id = p.id
        JOIN brands b ON p.brand_id = b.id
        WHERE pol.purchase_order_id = ?
        ORDER BY pol.created_at DESC
    ");
    $stmt->execute([$purchase_id]);
    $lines = $stmt->fetchAll();
    
    // Produits disponibles
    $products = $db->query("
        SELECT p.*, b.name as brand_name 
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        WHERE p.active = 1
        ORDER BY b.name, p.model
    ")->fetchAll();
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}
?>

<style>
    .ajout-section {
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

    .add-product-form {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        color: var(--secondary);
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 0.8rem;
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        color: var(--light);
        font-size: 1rem;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--secondary);
    }

    .lines-table {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
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

    tbody tr:hover {
        background: rgba(255, 255, 255, 0.03);
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
        width: 80px;
        padding: 0.5rem;
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 8px;
        color: var(--light);
        text-align: center;
    }

    .btn-update {
        padding: 0.5rem 1rem;
        background: linear-gradient(45deg, #4ECDC4, #44A08D);
        border: none;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-update:hover {
        transform: translateY(-2px);
    }

    .btn-remove {
        padding: 0.5rem 1rem;
        background: rgba(255, 107, 107, 0.2);
        border: 1px solid var(--primary);
        border-radius: 8px;
        color: var(--primary);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-remove:hover {
        background: var(--primary);
        color: white;
    }

    .total-section {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        max-width: 400px;
        margin-left: auto;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 0.8rem 0;
        border-bottom: 1px solid var(--glass-border);
    }

    .total-row:last-child {
        border-bottom: none;
        margin-top: 0.5rem;
        padding-top: 1rem;
        border-top: 2px solid var(--glass-border);
    }

    .total-label {
        color: var(--secondary);
        font-weight: 600;
    }

    .total-value {
        color: var(--light);
        font-weight: 700;
        font-size: 1.1rem;
    }

    .total-row:last-child .total-value {
        font-size: 1.5rem;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .actions-bar {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 2rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--secondary);
        opacity: 0.7;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="ajout-section">
    
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="index.php?module=achats">📦 Achats</a>
        <span>/</span>
        <span>Ajouter des produits</span>
    </div>

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

    <!-- Résumé de la commande -->
    <div class="purchase-summary">
        <div class="summary-header">
            <div>
                <div class="summary-title"><?php echo htmlspecialchars($purchase['order_number']); ?></div>
                <div style="color: var(--secondary); margin-top: 0.5rem;">
                    🏢 <?php echo htmlspecialchars($purchase['supplier_name']); ?>
                </div>
            </div>
            <span class="badge-status badge-draft">📝 Brouillon</span>
        </div>
        
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">📦 Articles</div>
                <div class="summary-value"><?php echo count($lines); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">💰 Sous-total</div>
                <div class="summary-value"><?php echo number_format($purchase['subtotal'], 2); ?>€</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">📊 TVA (20%)</div>
                <div class="summary-value"><?php echo number_format($purchase['tax'], 2); ?>€</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">✅ Total TTC</div>
                <div class="summary-value" style="color: var(--secondary);">
                    <?php echo number_format($purchase['total'], 2); ?>€
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire d'ajout -->
    <div class="add-product-form">
        <h3 style="color: var(--light); margin-bottom: 1.5rem;">➕ Ajouter un Produit</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_product">
            <div class="form-row">
                <div class="form-group">
                    <label>Produit *</label>
                    <select class="form-control" name="product_id" id="product_select" required onchange="updateProductInfo()">
                        <option value="">Sélectionner un produit...</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    data-cost="<?php echo $product['cost']; ?>"
                                    data-sku="<?php echo htmlspecialchars($product['sku']); ?>">
                                <?php echo htmlspecialchars($product['brand_name'] . ' - ' . $product['model'] . ' (' . $product['colorway'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Taille *</label>
                    <select class="form-control" name="size" required>
                        <?php for ($i = 36; $i <= 48; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Quantité *</label>
                    <input type="number" class="form-control" name="quantity" min="1" value="1" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="height: 48px;">
                    ➕ Ajouter
                </button>
            </div>
            
            <div id="product_info" style="margin-top: 1rem; color: var(--secondary); font-size: 0.9rem; display: none;">
                💰 Coût unitaire : <span id="cost_display">-</span>€ • SKU: <span id="sku_display">-</span>
            </div>
        </form>
    </div>

    <!-- Liste des produits ajoutés -->
    <div class="lines-table">
        <div class="table-header">
            <h3 style="color: var(--light);">📋 Produits de la Commande</h3>
        </div>
        
        <?php if (empty($lines)): ?>
            <div class="empty-state">
                <h3>📦 Aucun produit ajouté</h3>
                <p>Ajoutez des produits à cette commande</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Taille</th>
                        <th>Quantité</th>
                        <th>Coût Unit.</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $line): ?>
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
                        <td><strong><?php echo $line['size']; ?></strong></td>
                        <td>
                            <form method="POST" style="display: inline-flex; gap: 0.5rem; align-items: center;">
                                <input type="hidden" name="action" value="update_quantity">
                                <input type="hidden" name="line_id" value="<?php echo $line['id']; ?>">
                                <input type="number" class="quantity-input" name="quantity" 
                                       value="<?php echo $line['quantity_ordered']; ?>" min="1">
                                <button type="submit" class="btn-update">✓</button>
                            </form>
                        </td>
                        <td><?php echo number_format($line['unit_cost'], 2); ?>€</td>
                        <td><strong><?php echo number_format($line['total'], 2); ?>€</strong></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="remove_line">
                                <input type="hidden" name="line_id" value="<?php echo $line['id']; ?>">
                                <button type="submit" class="btn-remove" 
                                        onclick="return confirm('Supprimer cette ligne ?')">
                                    🗑️
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Totaux -->
    <?php if (!empty($lines)): ?>
    <div class="total-section">
        <div class="total-row">
            <span class="total-label">Sous-total HT</span>
            <span class="total-value"><?php echo number_format($purchase['subtotal'], 2); ?>€</span>
        </div>
        <div class="total-row">
            <span class="total-label">TVA (20%)</span>
            <span class="total-value"><?php echo number_format($purchase['tax'], 2); ?>€</span>
        </div>
        <div class="total-row">
            <span class="total-label">Total TTC</span>
            <span class="total-value"><?php echo number_format($purchase['total'], 2); ?>€</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="actions-bar">
        <a href="index.php?module=achats" class="btn btn-secondary">
            ← Retour aux achats
        </a>
        <?php if (!empty($lines)): ?>
        <form method="POST" action="index.php?module=achats" style="display: inline;">
            <input type="hidden" name="action" value="send_order">
            <input type="hidden" name="purchase_id" value="<?php echo $purchase_id; ?>">
            <button type="submit" class="btn btn-primary" 
                    onclick="return confirm('Envoyer cette commande au fournisseur ?')">
                📧 Envoyer au Fournisseur
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
function updateProductInfo() {
    const select = document.getElementById('product_select');
    const option = select.options[select.selectedIndex];
    
    if (option.value) {
        const cost = option.dataset.cost;
        const sku = option.dataset.sku;
        
        document.getElementById('cost_display').textContent = parseFloat(cost).toFixed(2);
        document.getElementById('sku_display').textContent = sku;
        document.getElementById('product_info').style.display = 'block';
    } else {
        document.getElementById('product_info').style.display = 'none';
    }
}
</script>
