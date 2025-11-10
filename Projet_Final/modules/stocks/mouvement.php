<?php
// modules/stocks/mouvement.php - Historique des mouvements de stock

require_once __DIR__ . '/../../include/db.php';

try {
    $pdo = getDB();
    
    // Récupérer les mouvements avec détails
    $stmt = $pdo->query("
        SELECT 
            sm.id,
            sm.movement_date,
            sm.type,
            sm.quantity,
            sm.reference_type,
            sm.reference_id,
            sm.notes,
            p.model as product_name,
            b.name as brand,
            sm.size,
            COALESCE(u.username, 'Système') as user_name
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        JOIN brands b ON p.brand_id = b.id
        LEFT JOIN employees e ON sm.employee_id = e.id
        LEFT JOIN users u ON e.id = u.employee_id
        ORDER BY sm.movement_date DESC
        LIMIT 100
    ");
    $movements = $stmt->fetchAll();
    
    // Statistiques des mouvements
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_movements,
            SUM(CASE WHEN type = 'out' AND reference_type = 'sale' THEN ABS(quantity) ELSE 0 END) as total_sales_qty,
            SUM(CASE WHEN type = 'in' AND reference_type = 'purchase' THEN quantity ELSE 0 END) as total_purchases_qty,
            SUM(CASE WHEN type = 'adjustment' THEN ABS(quantity) ELSE 0 END) as total_adjustments_qty,
            COUNT(CASE WHEN DATE(movement_date) = CURDATE() THEN 1 END) as today_movements
        FROM stock_movements
    ")->fetch();
    
} catch (Exception $e) {
    error_log("Erreur mouvements : " . $e->getMessage());
    $movements = [];
    $stats = [
        'total_movements' => 0,
        'total_sales_qty' => 0,
        'total_purchases_qty' => 0,
        'total_adjustments_qty' => 0,
        'today_movements' => 0
    ];
}
?>

<div id="mouvements-module">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.2rem; color: var(--light); margin-bottom: 0.5rem;">📦 Mouvements de Stock</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Traçabilité complète de tous les mouvements</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary" onclick="window.location.href='?module=stocks&action=produits'">
                ← Retour aux Stocks
            </button>
            <button class="btn btn-primary" onclick="exportMovements()">
                📊 Exporter
            </button>
        </div>
    </div>

    <!-- KPIs Mouvements -->
    <div class="cards-grid">
        <div class="card">
            <div class="card-label">Mouvements Totaux</div>
            <div class="card-value"><?php echo number_format($stats['total_movements'], 0, ',', ' '); ?></div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                <?php echo $stats['today_movements']; ?> aujourd'hui
            </div>
        </div>

        <div class="card">
            <div class="card-label">Sorties (Ventes)</div>
            <div class="card-value" style="color: var(--danger);">
                <?php echo number_format($stats['total_sales_qty'], 0, ',', ' '); ?>
            </div>
            <div style="margin-top: 0.5rem; color: var(--danger); opacity: 0.7; font-size: 0.85rem;">
                ↓ Articles vendus
            </div>
        </div>

        <div class="card">
            <div class="card-label">Entrées (Achats)</div>
            <div class="card-value" style="color: var(--success);">
                <?php echo number_format($stats['total_purchases_qty'], 0, ',', ' '); ?>
            </div>
            <div style="margin-top: 0.5rem; color: var(--success); opacity: 0.7; font-size: 0.85rem;">
                ↑ Articles reçus
            </div>
        </div>

        <div class="card">
            <div class="card-label">Ajustements</div>
            <div class="card-value" style="color: var(--warning);">
                <?php echo number_format($stats['total_adjustments_qty'], 0, ',', ' '); ?>
            </div>
            <div style="margin-top: 0.5rem; color: var(--warning); opacity: 0.7; font-size: 0.85rem;">
                ⚠️ Corrections inventaire
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="table-container" style="margin-bottom: 1rem; padding: 1rem;">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" class="form-control" id="searchInput" 
                       placeholder="🔍 Rechercher un produit, référence..." 
                       onkeyup="filterMovements()">
            </div>
            <select class="form-control" id="typeFilter" style="width: auto; min-width: 150px;" onchange="filterMovements()">
                <option value="">Tous les types</option>
                <option value="out">Sorties</option>
                <option value="in">Entrées</option>
                <option value="adjustment">Ajustements</option>
                <option value="transfer">Transferts</option>
            </select>
            <input type="date" class="form-control" id="dateFilter" style="width: auto;" onchange="filterMovements()">
        </div>
    </div>

    <!-- Tableau des mouvements -->
    <div class="table-container">
        <table id="movementsTable">
            <thead>
                <tr>
                    <th>Date & Heure</th>
                    <th>Type</th>
                    <th>Produit</th>
                    <th>Marque</th>
                    <th>Pointure</th>
                    <th>Quantité</th>
                    <th>Référence</th>
                    <th>Utilisateur</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 3rem; color: var(--secondary);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                            <h3 style="margin-bottom: 0.5rem;">Aucun mouvement enregistré</h3>
                            <p>Les mouvements de stock apparaîtront ici</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $movement): ?>
                    <tr class="movement-row" 
                        data-type="<?php echo $movement['type']; ?>"
                        data-date="<?php echo date('Y-m-d', strtotime($movement['movement_date'])); ?>">
                        <td><?php echo date('d/m/Y H:i', strtotime($movement['movement_date'])); ?></td>
                        <td>
                            <?php
                            // Déterminer le badge selon le type et la référence
                            if ($movement['type'] == 'out' && $movement['reference_type'] == 'sale') {
                                $badge = ['danger', '📤 Vente'];
                            } elseif ($movement['type'] == 'in' && $movement['reference_type'] == 'purchase') {
                                $badge = ['success', '📥 Achat'];
                            } elseif ($movement['type'] == 'adjustment') {
                                $badge = ['warning', '⚖️ Ajustement'];
                            } elseif ($movement['type'] == 'transfer') {
                                $badge = ['', '🔄 Transfert'];
                            } else {
                                $badge = ['', ucfirst($movement['type'])];
                            }
                            ?>
                            <span class="badge <?php echo $badge[0]; ?>">
                                <?php echo $badge[1]; ?>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($movement['product_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($movement['brand']); ?></td>
                        <td><strong>T.<?php echo $movement['size']; ?></strong></td>
                        <td>
                            <strong style="color: <?php echo $movement['quantity'] < 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                                <?php echo $movement['quantity'] > 0 ? '+' : ''; ?><?php echo $movement['quantity']; ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($movement['reference_type'] && $movement['reference_id']): ?>
                                <code style="font-size: 0.85rem; background: var(--glass); padding: 0.25rem 0.5rem; border-radius: 4px;">
                                    <?php echo strtoupper($movement['reference_type']); ?>-<?php echo $movement['reference_id']; ?>
                                </code>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($movement['user_name']); ?></td>
                        <td>
                            <?php if ($movement['notes']): ?>
                                <span style="font-size: 0.85rem; color: var(--secondary);" title="<?php echo htmlspecialchars($movement['notes']); ?>">
                                    <?php echo htmlspecialchars(substr($movement['notes'], 0, 50)); ?>
                                    <?php if (strlen($movement['notes']) > 50): ?>...<?php endif; ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Analyse des mouvements -->
    <div class="section-header" style="margin-top: 3rem;">
        <h3>📈 Analyse des Mouvements</h3>
    </div>
    <div class="cards-grid">
        <div class="card">
            <h4 style="margin-bottom: 1rem; color: var(--light);">Top Mouvements (7 derniers jours)</h4>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <?php
                try {
                    $top_movements = $pdo->query("
                        SELECT 
                            p.model,
                            COUNT(*) as nb_movements
                        FROM stock_movements sm
                        JOIN products p ON sm.product_id = p.id
                        WHERE sm.movement_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY p.id
                        ORDER BY nb_movements DESC
                        LIMIT 5
                    ")->fetchAll();
                    
                    if (empty($top_movements)) {
                        echo '<p style="color: var(--secondary); text-align: center;">Aucun mouvement cette semaine</p>';
                    } else {
                        foreach ($top_movements as $top):
                ?>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--glass); border-radius: 8px;">
                                <span><?php echo htmlspecialchars($top['model']); ?></span>
                                <strong><?php echo $top['nb_movements']; ?> mvts</strong>
                            </div>
                <?php 
                        endforeach;
                    }
                } catch (Exception $e) {
                    echo '<p style="color: var(--danger);">Erreur lors du chargement</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
function filterMovements() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;
    const rows = document.querySelectorAll('.movement-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const type = row.dataset.type;
        const date = row.dataset.date;
        
        const matchesSearch = text.includes(searchTerm);
        const matchesType = !typeFilter || type === typeFilter;
        const matchesDate = !dateFilter || date === dateFilter;
        
        row.style.display = (matchesSearch && matchesType && matchesDate) ? '' : 'none';
    });
}

function exportMovements() {
    // Générer un export CSV
    const table = document.getElementById('movementsTable');
    let csv = [];
    
    // Headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(';'));
    
    // Rows visibles uniquement
    table.querySelectorAll('tbody tr.movement-row').forEach(row => {
        if (row.style.display !== 'none') {
            const cols = [];
            row.querySelectorAll('td').forEach(td => {
                cols.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
            });
            csv.push(cols.join(';'));
        }
    });
    
    // Télécharger le fichier CSV
    const csvContent = csv.join('\n');
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'mouvements_stock_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}
</script>

<style>
.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    white-space: nowrap;
}

.badge.success {
    background: rgba(46, 213, 115, 0.2);
    color: #2ed573;
}

.badge.danger {
    background: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
}

.badge.warning {
    background: rgba(255, 230, 109, 0.2);
    color: #ffe66d;
}

.badge.info {
    background: rgba(78, 205, 196, 0.2);
    color: #4ecdc4;
}

code {
    font-family: 'Courier New', monospace;
}

.movement-row {
    transition: background-color 0.2s;
}

.movement-row:hover {
    background-color: rgba(108, 92, 231, 0.05);
}
</style>