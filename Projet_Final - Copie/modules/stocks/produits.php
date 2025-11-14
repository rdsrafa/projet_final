<?php
// modules/stocks/produits.php - Gestion des stocks avec variantes (pointures/coloris)

require_once __DIR__ . '/../../include/db.php';

try {
    $pdo = getDB();
    
    // Récupérer les produits réels depuis la base de données
    $stmt = $pdo->query("
        SELECT 
            p.*,
            b.name as brand,
            COALESCE(SUM(s.quantity), 0) as total_stock
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        LEFT JOIN stock s ON p.id = s.product_id
        WHERE p.active = 1
        GROUP BY p.id
        ORDER BY b.name, p.model
    ");
    $products = $stmt->fetchAll();
    
    // Statistiques stocks
    $stats = $pdo->query("
        SELECT 
            COUNT(DISTINCT p.id) as total_products,
            COALESCE(SUM(s.quantity), 0) as total_quantity,
            COALESCE(SUM(s.quantity * p.cost), 0) as total_stock_value,
            COUNT(CASE WHEN s.quantity <= s.min_quantity AND s.quantity > 0 THEN 1 END) as low_stock_items,
            COUNT(CASE WHEN s.quantity = 0 THEN 1 END) as out_of_stock
        FROM products p
        LEFT JOIN stock s ON p.id = s.product_id
        WHERE p.active = 1
    ")->fetch();
    
    // Alertes dynamiques
    // 1. RUPTURES TOTALES (stock = 0)
    $ruptures = $pdo->query("
        SELECT 
            p.model,
            b.name as brand,
            s.size,
            s.quantity,
            p.id as product_id
        FROM stock s
        JOIN products p ON s.product_id = p.id
        JOIN brands b ON p.brand_id = b.id
        WHERE s.quantity = 0 AND p.active = 1
        ORDER BY b.name, p.model
        LIMIT 5
    ")->fetchAll();
    
    // 2. STOCK BAS (quantity <= min_quantity et > 0)
    $stock_bas = $pdo->query("
        SELECT 
            p.model,
            b.name as brand,
            s.size,
            s.quantity,
            s.min_quantity,
            p.id as product_id
        FROM stock s
        JOIN products p ON s.product_id = p.id
        JOIN brands b ON p.brand_id = b.id
        WHERE s.quantity <= s.min_quantity 
          AND s.quantity > 0 
          AND p.active = 1
        ORDER BY s.quantity ASC
        LIMIT 5
    ")->fetchAll();
    
    // 3. RUPTURE IMMINENTE (stock total d'un produit < 15 paires)
    $imminentes = $pdo->query("
        SELECT 
            p.model,
            b.name as brand,
            SUM(s.quantity) as stock_total,
            p.id as product_id,
            GROUP_CONCAT(
                CASE WHEN s.quantity = 0 
                THEN CONCAT('T.', s.size) 
                END SEPARATOR ', '
            ) as tailles_epuisees
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        JOIN stock s ON p.id = s.product_id
        WHERE p.active = 1
        GROUP BY p.id
        HAVING stock_total > 0 AND stock_total < 15
        ORDER BY stock_total ASC
        LIMIT 3
    ")->fetchAll();
    
    // 4. ROTATION LENTE (pas de vente depuis 30 jours)
    $rotation_lente = $pdo->query("
        SELECT 
            p.model,
            b.name as brand,
            COALESCE(MAX(s.sale_date), p.created_at) as derniere_vente,
            DATEDIFF(NOW(), COALESCE(MAX(s.sale_date), p.created_at)) as jours_sans_vente,
            SUM(st.quantity) as stock_total,
            p.id as product_id
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        JOIN stock st ON p.id = st.product_id
        LEFT JOIN sale_lines sl ON p.id = sl.product_id
        LEFT JOIN sales s ON sl.sale_id = s.id
        WHERE p.active = 1
        GROUP BY p.id
        HAVING jours_sans_vente >= 30 AND stock_total > 20
        ORDER BY jours_sans_vente DESC
        LIMIT 3
    ")->fetchAll();
    
    // Compter le nombre total d'alertes
    $total_alertes = count($ruptures) + count($stock_bas) + count($imminentes) + count($rotation_lente);
    
} catch (Exception $e) {
    error_log("Erreur stocks : " . $e->getMessage());
    $products = [];
    $stats = [
        'total_products' => 0,
        'total_quantity' => 0,
        'total_stock_value' => 0,
        'low_stock_items' => 0,
        'out_of_stock' => 0
    ];
    $total_alertes = 0;
}
?>

<div id="stocks-module">
    <!-- Header Actions -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.2rem; color: var(--light); margin-bottom: 0.5rem;">Gestion des Stocks</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Gérez vos stocks par pointure et coloris avec traçabilité</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary" onclick="window.location.href='?module=stocks&action=mouvement'">
                📦 Mouvements
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='?module=stocks&action=inventaire'">
                📊 Inventaire
            </button>
            <button class="btn btn-primary" onclick="showAddProductModal()">
                ➕ Nouveau Produit
            </button>
        </div>
    </div>

    <!-- Messages de succès/erreur -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" style="margin-bottom: 1.5rem;">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- KPIs Stock -->
    <div class="cards-grid">
        <div class="card">
            <div class="card-label">Modèles Différents</div>
            <div class="card-value"><?php echo $stats['total_products']; ?></div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                <?php echo number_format($stats['total_quantity'], 0, ',', ' '); ?> paires au total
            </div>
        </div>

        <div class="card">
            <div class="card-label">Valeur du Stock</div>
            <div class="card-value"><?php echo number_format($stats['total_stock_value'], 2, ',', ' '); ?> €</div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                Prix d'achat total
            </div>
        </div>

        <div class="card">
            <div class="card-label">Alertes Stock Bas</div>
            <div class="card-value" style="color: var(--warning);"><?php echo $stats['low_stock_items']; ?></div>
            <div class="trend down">⚠️ Action requise</div>
        </div>

        <div class="card">
            <div class="card-label">Ruptures de Stock</div>
            <div class="card-value" style="color: var(--danger);"><?php echo $stats['out_of_stock']; ?></div>
            <div class="trend down">🚨 Critique</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="table-container" style="margin-bottom: 1rem; padding: 1rem;">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" class="form-control" id="searchInput" placeholder="🔍 Rechercher un modèle, marque, référence..." 
                    onkeyup="filterProducts(this.value)">
            </div>
            <select class="form-control" id="brandFilter" style="width: auto; min-width: 150px;" onchange="filterByBrand(this.value)">
                <option value="">Toutes les marques</option>
                <?php
                $brands = array_unique(array_column($products, 'brand'));
                foreach ($brands as $brand):
                ?>
                    <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Grille de produits -->
    <div class="section-header">
        <h3>👟 Catalogue Sneakers</h3>
    </div>
    <div class="stock-grid" id="product-grid">
        <?php foreach ($products as $product): 
            $total_stock = $product['total_stock'];
            $stock_level = $total_stock > 80 ? 'high' : ($total_stock > 30 ? 'medium' : 'low');
            
            // Récupérer les détails des pointures
            $sizes_stmt = $pdo->prepare("SELECT size, quantity FROM stock WHERE product_id = ? ORDER BY size");
            $sizes_stmt->execute([$product['id']]);
            $sizes_data = $sizes_stmt->fetchAll();
        ?>
        <div class="stock-item" data-brand="<?php echo htmlspecialchars($product['brand']); ?>" data-stock-level="<?php echo $stock_level; ?>">
            <!-- Image placeholder avec couleur de marque -->
            <div style="width: 100%; height: 160px; background: linear-gradient(135deg, 
                <?php 
                    $colors = ['Nike' => '#FF6B6B', 'Adidas' => '#4ECDC4', 'Jordan' => '#FFE66D', 'Yeezy' => '#6C5CE7'];
                    echo $colors[$product['brand']] ?? '#95a5a6'; 
                ?>, 
                rgba(26, 26, 46, 0.8)); 
                border-radius: 15px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                <div style="font-size: 3rem;">👟</div>
                <div style="position: absolute; top: 0.5rem; right: 0.5rem; background: var(--glass); backdrop-filter: blur(10px); padding: 0.3rem 0.6rem; border-radius: 10px; font-size: 0.75rem;">
                    <?php echo htmlspecialchars($product['sku']); ?>
                </div>
                <?php if ($total_stock < 20): ?>
                <div style="position: absolute; top: 0.5rem; left: 0.5rem; background: rgba(255,107,107,0.9); padding: 0.3rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: 700;">
                    ⚠️ Stock bas
                </div>
                <?php endif; ?>
            </div>

            <h4><?php echo htmlspecialchars($product['model']); ?></h4>
            <div style="font-size: 0.85rem; color: var(--secondary); margin-bottom: 0.5rem;">
                <?php echo htmlspecialchars($product['brand']); ?> - <?php echo htmlspecialchars($product['colorway']); ?>
            </div>
            <div class="price"><?php echo number_format($product['price'], 2, ',', ' '); ?> €</div>
            
            <!-- Stock total -->
            <div style="margin: 1rem 0; padding: 0.8rem; background: var(--glass); border-radius: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.85rem; color: var(--light); opacity: 0.7;">Stock total:</span>
                    <span style="font-size: 1.2rem; font-weight: 700; color: 
                        <?php 
                            echo $total_stock > 80 ? 'var(--success)' : ($total_stock > 30 ? 'var(--warning)' : 'var(--danger)'); 
                        ?>;">
                        <?php echo $total_stock; ?> paires
                    </span>
                </div>
                <div style="margin-top: 0.5rem; height: 6px; background: var(--glass); border-radius: 10px; overflow: hidden;">
                    <div style="height: 100%; background: linear-gradient(90deg, var(--secondary), var(--primary)); width: <?php echo min(100, $total_stock); ?>%;"></div>
                </div>
            </div>

            <!-- Pointures disponibles -->
            <div style="margin-top: 0.8rem;">
                <div style="font-size: 0.85rem; color: var(--secondary); margin-bottom: 0.5rem; font-weight: 600;">Pointures disponibles:</div>
                <div class="size-badges">
                    <?php foreach ($sizes_data as $size): 
                        $class = $size['quantity'] > 10 ? 'available' : ($size['quantity'] > 0 ? 'low' : 'out');
                    ?>
                        <div class="size-badge <?php echo $class; ?>" title="Stock: <?php echo $size['quantity']; ?>">
                            <?php echo $size['size']; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Actions -->
            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button class="btn btn-sm btn-primary" style="flex: 1;" onclick="showStockDetails(<?php echo $product['id']; ?>)">
                    Détails Stock
                </button>
                <button class="btn btn-sm btn-secondary" onclick="editProduct(<?php echo $product['id']; ?>)" title="Modifier">
                    ✏️
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 🔴 SECTION ALERTES DYNAMIQUES -->
    <?php if ($total_alertes > 0): ?>
    <div class="section-header" style="margin-top: 3rem;">
        <h3>⚠️ Alertes & Actions Requises <span class="badge danger" style="margin-left: 1rem;"><?php echo $total_alertes; ?></span></h3>
    </div>

    <div class="alerts-grid">
        
        <!-- RUPTURES TOTALES -->
        <?php foreach ($ruptures as $rupture): ?>
        <div class="alert alert-danger" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong>🚨 Rupture totale :</strong> 
                <?php echo htmlspecialchars($rupture['brand']); ?> 
                <?php echo htmlspecialchars($rupture['model']); ?> 
                - Taille <?php echo $rupture['size']; ?> 
                - <span style="color: #ff6b6b; font-weight: 700;">0 stock restant</span>
            </div>
            <button class="btn btn-sm btn-primary" 
                    onclick="window.location.href='?module=achats&action=commandes&product_id=<?php echo $rupture['product_id']; ?>'">
                📦 Réapprovisionner
            </button>
        </div>
        <?php endforeach; ?>
        
        <!-- RUPTURES IMMINENTES -->
        <?php foreach ($imminentes as $imminente): ?>
        <div class="alert alert-danger" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong>⚠️ Rupture imminente :</strong> 
                <?php echo htmlspecialchars($imminente['brand']); ?> 
                <?php echo htmlspecialchars($imminente['model']); ?> 
                - Plus que <strong style="color: #ff6b6b;"><?php echo $imminente['stock_total']; ?> paires</strong>
                <?php if ($imminente['tailles_epuisees']): ?>
                    (<?php echo $imminente['tailles_epuisees']; ?> épuisées)
                <?php endif; ?>
            </div>
            <button class="btn btn-sm btn-primary" 
                    onclick="window.location.href='?module=achats&action=commandes&product_id=<?php echo $imminente['product_id']; ?>'">
                Commander
            </button>
        </div>
        <?php endforeach; ?>
        
        <!-- STOCK FAIBLE -->
        <?php foreach ($stock_bas as $bas): ?>
        <div class="alert alert-warning" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong>🟡 Stock faible :</strong> 
                <?php echo htmlspecialchars($bas['brand']); ?> 
                <?php echo htmlspecialchars($bas['model']); ?> 
                - Taille <?php echo $bas['size']; ?>
                - <strong style="color: #ffe66d;"><?php echo $bas['quantity']; ?> restantes</strong> 
                (seuil: <?php echo $bas['min_quantity']; ?>)
            </div>
            <button class="btn btn-sm btn-secondary" 
                    onclick="showStockDetails(<?php echo $bas['product_id']; ?>)">
                📊 Voir détails
            </button>
        </div>
        <?php endforeach; ?>
        
        <!-- ROTATION LENTE -->
        <?php foreach ($rotation_lente as $lent): ?>
        <div class="alert alert-info" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong>🔵 Rotation lente :</strong> 
                <?php echo htmlspecialchars($lent['brand']); ?> 
                <?php echo htmlspecialchars($lent['model']); ?> 
                - <strong style="color: #4ecdc4;"><?php echo $lent['jours_sans_vente']; ?> jours</strong> sans vente
                (<?php echo $lent['stock_total']; ?> paires en stock)
            </div>
            <button class="btn btn-sm btn-secondary" 
                    onclick="alert('Fonction promotion en développement')">
                🏷️ Promotion
            </button>
        </div>
        <?php endforeach; ?>
        
    </div>

    <?php else: ?>
    <!-- Message si aucune alerte -->
    <div class="section-header" style="margin-top: 3rem;">
        <h3>✅ Aucune alerte - Stock sous contrôle</h3>
    </div>
    <div class="alert alert-success" style="text-align: center; padding: 2rem;">
        <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
        <h4>Tout va bien !</h4>
        <p>Aucun stock critique nécessitant une action immédiate.</p>
    </div>
    <?php endif; ?>

    <!-- Mouvements récents -->
    <div class="section-header" style="margin-top: 2rem;">
        <h3>📦 Mouvements Récents</h3>
        <button class="btn btn-secondary btn-sm" onclick="window.location.href='?module=stocks&action=mouvement'">
            Voir tous les mouvements →
        </button>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Produit</th>
                    <th>Pointure</th>
                    <th>Quantité</th>
                    <th>Référence</th>
                    <th>Utilisateur</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $recent_movements = $pdo->query("
                        SELECT 
                            sm.movement_date,
                            sm.type,
                            sm.quantity,
                            sm.reference_type,
                            sm.reference_id,
                            p.model,
                            sm.size,
                            COALESCE(u.username, 'Système') as user_name
                        FROM stock_movements sm
                        JOIN products p ON sm.product_id = p.id
                        LEFT JOIN employees e ON sm.employee_id = e.id
                        LEFT JOIN users u ON e.id = u.employee_id
                        ORDER BY sm.movement_date DESC
                        LIMIT 5
                    ")->fetchAll();
                    
                    foreach ($recent_movements as $mv):
                        $badge_types = [
                            'out' => ['danger', 'Sortie'],
                            'in' => ['success', 'Entrée'],
                            'adjustment' => ['warning', 'Ajustement'],
                            'transfer' => ['info', 'Transfert']
                        ];
                        $badge = $badge_types[$mv['type']] ?? ['', ucfirst($mv['type'])];
                ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($mv['movement_date'])); ?></td>
                    <td><span class="badge <?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span></td>
                    <td><?php echo htmlspecialchars($mv['model']); ?></td>
                    <td><?php echo $mv['size']; ?></td>
                    <td><strong style="color: <?php echo $mv['quantity'] < 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                        <?php echo $mv['quantity'] > 0 ? '+' : ''; ?><?php echo $mv['quantity']; ?>
                    </strong></td>
                    <td>
                        <?php if ($mv['reference_type'] && $mv['reference_id']): ?>
                            <code><?php echo strtoupper($mv['reference_type']); ?>-<?php echo $mv['reference_id']; ?></code>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($mv['user_name']); ?></td>
                </tr>
                <?php 
                    endforeach;
                } catch (Exception $e) {
                    echo '<tr><td colspan="7" style="text-align: center; color: var(--danger);">Erreur lors du chargement des mouvements</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Détails Stock -->
<div id="stockDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📊 Détails du Stock</h3>
            <span class="close-modal" onclick="closeModal('stockDetailsModal')">&times;</span>
        </div>
        <div id="stock-details-content">
            <!-- Contenu chargé dynamiquement -->
        </div>
    </div>
</div>

<!-- Modal Nouveau Produit - VERSION CORRIGÉE -->
<div id="addProductModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>👟 Nouveau Produit</h3>
            <span class="close-modal" onclick="closeModal('addProductModal')">&times;</span>
        </div>
        <form method="POST" action="?module=stocks&action=add_product" onsubmit="return validateProductForm()">
            <div class="form-group">
                <label>Marque <span style="color: var(--danger);">*</span></label>
                <select class="form-control" name="brand_id" id="brand_id" required>
                    <option value="">Sélectionner une marque...</option>
                    <?php
                    try {
                        $brands_list = $pdo->query("SELECT id, name FROM brands WHERE active = 1 ORDER BY name")->fetchAll();
                        foreach ($brands_list as $b):
                    ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php 
                        endforeach;
                    } catch (Exception $e) {
                        echo '<option value="">Erreur chargement marques</option>';
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Modèle <span style="color: var(--danger);">*</span></label>
                <input type="text" class="form-control" name="model" id="model" 
                       placeholder="Ex: Air Jordan 1 Retro High" required>
            </div>

            <div class="form-group">
                <label>Coloris <span style="color: var(--danger);">*</span></label>
                <input type="text" class="form-control" name="colorway" id="colorway" 
                       placeholder="Ex: Chicago, Panda, Triple Black" required>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Année <span style="color: var(--danger);">*</span></label>
                    <input type="number" class="form-control" name="year" id="year" 
                           value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                </div>

                <div class="form-group">
                    <label>SKU / Référence <span style="color: var(--danger);">*</span></label>
                    <input type="text" class="form-control" name="sku" id="sku" 
                           placeholder="NKE-AJ1-CHI-001" required>
                </div>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Prix de vente (€) <span style="color: var(--danger);">*</span></label>
                    <input type="number" class="form-control" name="price" id="price" 
                           step="0.01" min="0.01" placeholder="189.00" required>
                </div>

                <div class="form-group">
                    <label>Prix d'achat (€) <span style="color: var(--danger);">*</span></label>
                    <input type="number" class="form-control" name="cost" id="cost" 
                           step="0.01" min="0.01" placeholder="95.00" required>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top: 1rem; font-size: 0.9rem;">
                <strong>ℹ️ Info :</strong> Les pointures standards (36-46) seront automatiquement créées avec un stock initial à 0.
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProductModal')">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    ✅ Ajouter le produit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddProductModal() {
    document.getElementById('addProductModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function validateProductForm() {
    const price = parseFloat(document.getElementById('price').value);
    const cost = parseFloat(document.getElementById('cost').value);
    
    if (cost >= price) {
        alert('⚠️ Attention : Le prix d\'achat doit être inférieur au prix de vente pour avoir une marge bénéficiaire.');
        return confirm('Voulez-vous continuer malgré tout ?');
    }
    
    return true;
}

function filterProducts(searchTerm) {
    const items = document.querySelectorAll('.stock-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
    });
}

function filterByBrand(brand) {
    const items = document.querySelectorAll('.stock-item');
    items.forEach(item => {
        if (!brand || item.dataset.brand === brand) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function showStockDetails(productId) {
    document.getElementById('stockDetailsModal').classList.add('active');
    document.getElementById('stock-details-content').innerHTML = `
        <div style="text-align: center; padding: 2rem;">
            <div class="loading" style="width: 40px; height: 40px; margin: 0 auto; border: 4px solid var(--glass); border-top: 4px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite;"></div>
            <p style="margin-top: 1rem;">Chargement des détails...</p>
        </div>
    `;
    
    // TODO: Charger les détails via AJAX
    // fetch('?module=stocks&action=get_details&product_id=' + productId)
}

function editProduct(productId) {
    alert('Fonction d\'édition en développement - Produit ID: ' + productId);
}
</script>

<style>
.alerts-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    border-left: 4px solid;
    background: var(--glass);
    backdrop-filter: blur(10px);
}

.alert-danger {
    border-left-color: #ff6b6b;
    background: rgba(255, 107, 107, 0.1);
}

.alert-warning {
    border-left-color: #ffe66d;
    background: rgba(255, 230, 109, 0.1);
}

.alert-info {
    border-left-color: #4ecdc4;
    background: rgba(78, 205, 196, 0.1);
}

.alert-success {
    border-left-color: #2ed573;
    background: rgba(46, 213, 115, 0.1);
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge.danger {
    background: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
}

.badge.success {
    background: rgba(46, 213, 115, 0.2);
    color: #2ed573;
}

.badge.warning {
    background: rgba(255, 230, 109, 0.2);
    color: #ffe66d;
}

.badge.info {
    background: rgba(78, 205, 196, 0.2);
    color: #4ecdc4;
}

.size-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.size-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.85rem;
    text-align: center;
    min-width: 40px;
    transition: all 0.3s ease;
}

.size-badge.available {
    background: rgba(46, 213, 115, 0.2);
    color: #2ed573;
    border: 2px solid rgba(46, 213, 115, 0.4);
}

.size-badge.low {
    background: rgba(255, 230, 109, 0.2);
    color: #ffe66d;
    border: 2px solid rgba(255, 230, 109, 0.4);
}

.size-badge.out {
    background: rgba(255, 107, 107, 0.1);
    color: #ff6b6b;
    border: 2px solid rgba(255, 107, 107, 0.3);
    opacity: 0.5;
    text-decoration: line-through;
}

.size-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.stock-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stock-item {
    background: var(--card-bg);
    border-radius: 15px;
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.stock-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(108, 92, 231, 0.2);
}

.stock-item h4 {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: var(--light);
}

.price {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary);
    margin: 0.5rem 0;
}

code {
    font-family: 'Courier New', monospace;
    background: var(--glass);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
}

.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    border-radius: 20px;
    padding: 2rem;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass);
}

.modal-header h3 {
    color: var(--light);
    font-size: 1.5rem;
    margin: 0;
}

.close-modal {
    font-size: 2rem;
    cursor: pointer;
    color: var(--secondary);
    transition: color 0.3s;
}

.close-modal:hover {
    color: var(--danger);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--light);
    font-weight: 600;
}

.form-row {
    display: grid;
    gap: 1rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h3 {
    color: var(--light);
    font-size: 1.3rem;
}

.loading {
    border: 4px solid var(--glass);
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>