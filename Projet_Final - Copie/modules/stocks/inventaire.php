<?php
// modules/stocks/inventaire.php - Gestion des inventaires physiques

require_once __DIR__ . '/../../include/db.php';

try {
    $pdo = getDB();
    
    // Récupérer tous les produits avec leur stock actuel
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.model,
            b.name as brand,
            s.size,
            s.quantity as stock_system,
            s.id as stock_id
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        LEFT JOIN stock s ON p.id = s.product_id
        WHERE p.active = 1
        ORDER BY b.name, p.model, s.size
    ");
    $inventory_items = $stmt->fetchAll();
    
    // Statistiques
    $total_items = count($inventory_items);
    $total_quantity = array_sum(array_column($inventory_items, 'stock_system'));
    
} catch (Exception $e) {
    error_log("Erreur inventaire : " . $e->getMessage());
    $inventory_items = [];
    $total_items = 0;
    $total_quantity = 0;
}

// Traitement du formulaire d'inventaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inventory'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['inventory'] as $stock_id => $physical_count) {
            // Récupérer le stock système
            $stmt = $pdo->prepare("SELECT quantity, product_id, size FROM stock WHERE id = ?");
            $stmt->execute([$stock_id]);
            $stock_data = $stmt->fetch();
            
            $system_count = $stock_data['quantity'];
            $difference = $physical_count - $system_count;
            
            if ($difference != 0) {
                // Mettre à jour le stock
                $stmt = $pdo->prepare("UPDATE stock SET quantity = ? WHERE id = ?");
                $stmt->execute([$physical_count, $stock_id]);
                
                // Enregistrer le mouvement d'ajustement
                $stmt = $pdo->prepare("
                    INSERT INTO stock_movements (
                        product_id, 
                        size, 
                        type,
                        quantity, 
                        reference_type, 
                        notes,
                        movement_date
                    ) VALUES (?, ?, 'adjustment', ?, 'inventory', ?, NOW())
                ");
                $notes = "Inventaire physique - Écart: $difference (Système: $system_count → Physique: $physical_count)";
                $stmt->execute([
                    $stock_data['product_id'],
                    $stock_data['size'],
                    $difference,
                    $notes
                ]);
            }
        }
        
        $pdo->commit();
        $_SESSION['success'] = "Inventaire enregistré avec succès !";
        header('Location: ?module=stocks&action=inventaire');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erreur : " . $e->getMessage();
    }
}
?>

<div id="inventaire-module">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.2rem; color: var(--light); margin-bottom: 0.5rem;">📊 Inventaire Physique</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Comptage et ajustement des stocks</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary" onclick="window.location.href='?module=stocks&action=produits'">
                ← Retour aux Stocks
            </button>
            <button class="btn btn-primary" onclick="document.getElementById('inventoryForm').submit()">
                ✓ Valider l'Inventaire
            </button>
        </div>
    </div>

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

    <!-- KPIs -->
    <div class="cards-grid" style="margin-bottom: 2rem;">
        <div class="card">
            <div class="card-label">Articles à inventorier</div>
            <div class="card-value"><?php echo $total_items; ?></div>
        </div>
        <div class="card">
            <div class="card-label">Stock Système Total</div>
            <div class="card-value"><?php echo number_format($total_quantity, 0, ',', ' '); ?></div>
        </div>
        <div class="card">
            <div class="card-label">Écarts détectés</div>
            <div class="card-value" id="discrepancy-count">0</div>
        </div>
        <div class="card">
            <div class="card-label">Progression</div>
            <div class="card-value" id="progress-percentage">0%</div>
        </div>
    </div>

    <!-- Instructions -->
    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
        <strong>📋 Instructions :</strong> 
        Comptez physiquement chaque article et saisissez la quantité réelle dans la colonne "Stock Physique". 
        Les écarts seront automatiquement calculés et ajustés lors de la validation.
    </div>

    <!-- Filtres -->
    <div class="table-container" style="margin-bottom: 1rem; padding: 1rem;">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" class="form-control" id="searchInput" 
                       placeholder="🔍 Rechercher un produit..." 
                       onkeyup="filterInventory()">
            </div>
            <select class="form-control" id="brandFilter" style="width: auto; min-width: 150px;" onchange="filterInventory()">
                <option value="">Toutes les marques</option>
                <?php 
                $brands = array_unique(array_column($inventory_items, 'brand'));
                foreach ($brands as $brand): 
                ?>
                    <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-secondary" onclick="autoFillZeros()">
                Remplir à 0 (ruptures)
            </button>
        </div>
    </div>

    <!-- Formulaire d'inventaire -->
    <form id="inventoryForm" method="POST">
        <div class="table-container">
            <table id="inventoryTable">
                <thead>
                    <tr>
                        <th style="width: 5%;">
                            <input type="checkbox" onchange="toggleAllChecked(this)">
                        </th>
                        <th>Marque</th>
                        <th>Modèle</th>
                        <th>Pointure</th>
                        <th>Stock Système</th>
                        <th style="width: 150px;">Stock Physique</th>
                        <th>Écart</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory_items as $item): ?>
                    <?php if ($item['stock_id']): ?>
                    <tr class="inventory-row" data-brand="<?php echo htmlspecialchars($item['brand']); ?>">
                        <td>
                            <input type="checkbox" class="item-checkbox" onchange="updateProgress()">
                        </td>
                        <td><?php echo htmlspecialchars($item['brand']); ?></td>
                        <td><?php echo htmlspecialchars($item['model']); ?></td>
                        <td><strong>T.<?php echo $item['size']; ?></strong></td>
                        <td>
                            <strong class="system-count"><?php echo $item['stock_system']; ?></strong>
                        </td>
                        <td>
                            <input type="number" 
                                   class="form-control physical-count" 
                                   name="inventory[<?php echo $item['stock_id']; ?>]"
                                   data-system="<?php echo $item['stock_system']; ?>"
                                   placeholder="Compter..."
                                   min="0"
                                   oninput="calculateDifference(this)">
                        </td>
                        <td class="difference-cell">
                            <span class="difference">-</span>
                        </td>
                        <td class="status-cell">
                            <span class="badge">Non compté</span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='?module=stocks&action=produits'">
                Annuler
            </button>
            <button type="submit" name="save_inventory" class="btn btn-primary">
                ✓ Valider et Enregistrer l'Inventaire
            </button>
        </div>
    </form>
</div>

<script>
let totalItems = <?php echo $total_items; ?>;
let countedItems = 0;
let discrepancies = 0;

function calculateDifference(input) {
    const row = input.closest('tr');
    const systemCount = parseInt(input.dataset.system);
    const physicalCount = parseInt(input.value) || 0;
    const difference = physicalCount - systemCount;
    
    const diffCell = row.querySelector('.difference');
    const statusCell = row.querySelector('.status-cell span');
    const checkbox = row.querySelector('.item-checkbox');
    
    if (input.value === '') {
        diffCell.textContent = '-';
        statusCell.textContent = 'Non compté';
        statusCell.className = 'badge';
        checkbox.checked = false;
    } else {
        checkbox.checked = true;
        
        if (difference === 0) {
            diffCell.textContent = '✓ OK';
            diffCell.style.color = 'var(--success)';
            statusCell.textContent = 'Conforme';
            statusCell.className = 'badge success';
        } else if (difference > 0) {
            diffCell.textContent = '+' + difference;
            diffCell.style.color = 'var(--success)';
            statusCell.textContent = 'Excédent';
            statusCell.className = 'badge info';
        } else {
            diffCell.textContent = difference;
            diffCell.style.color = 'var(--danger)';
            statusCell.textContent = 'Manquant';
            statusCell.className = 'badge danger';
        }
    }
    
    updateProgress();
}

function updateProgress() {
    countedItems = document.querySelectorAll('.item-checkbox:checked').length;
    
    // Compter les écarts (différent de OK et -)
    discrepancies = Array.from(document.querySelectorAll('.difference')).filter(el => 
        el.textContent !== '-' && el.textContent !== '✓ OK'
    ).length;
    
    const percentage = totalItems > 0 ? Math.round((countedItems / totalItems) * 100) : 0;
    
    document.getElementById('progress-percentage').textContent = percentage + '%';
    document.getElementById('discrepancy-count').textContent = discrepancies;
}

function filterInventory() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const brandFilter = document.getElementById('brandFilter').value;
    const rows = document.querySelectorAll('.inventory-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const brand = row.dataset.brand;
        
        const matchesSearch = text.includes(searchTerm);
        const matchesBrand = !brandFilter || brand === brandFilter;
        
        row.style.display = (matchesSearch && matchesBrand) ? '' : 'none';
    });
}

function toggleAllChecked(checkbox) {
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateProgress();
}

function autoFillZeros() {
    if (confirm('Remplir tous les champs vides avec 0 (rupture de stock) ?')) {
        document.querySelectorAll('.physical-count').forEach(input => {
            if (input.value === '') {
                input.value = 0;
                calculateDifference(input);
            }
        });
    }
}
</script>

<style>
.physical-count {
    font-size: 1rem;
    font-weight: 600;
    text-align: center;
}

.physical-count:focus {
    background: rgba(108, 92, 231, 0.1);
    border-color: var(--primary);
}

.difference {
    font-weight: 700;
    font-size: 1rem;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
}

.badge.success {
    background: rgba(46, 213, 115, 0.2);
    color: #2ed573;
}

.badge.danger {
    background: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
}

.badge.info {
    background: rgba(78, 205, 196, 0.2);
    color: #4ecdc4;
}
</style>