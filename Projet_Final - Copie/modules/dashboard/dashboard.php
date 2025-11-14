<?php
/**
 * modules/dashboard/dashboard.php
 * Dashboard principal avec données dynamiques
 */

require_once __DIR__ . '/../../include/db.php';

$db = getDB();

try {
    // 📊 STATISTIQUES TEMPS RÉEL
    
    // CA Mensuel (mois en cours)
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(total), 0) as ca_mensuel,
            COUNT(*) as nb_ventes_mois
        FROM sales 
        WHERE MONTH(sale_date) = MONTH(CURRENT_DATE())
        AND YEAR(sale_date) = YEAR(CURRENT_DATE())
        AND status = 'completed'
    ");
    $ca_data = $stmt->fetch();
    
    // CA mois précédent pour calcul tendance
    $stmt = $db->query("
        SELECT COALESCE(SUM(total), 0) as ca_precedent
        FROM sales 
        WHERE MONTH(sale_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        AND YEAR(sale_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        AND status = 'completed'
    ");
    $ca_precedent = $stmt->fetchColumn();
    
    // Calcul tendance CA
    $ca_tendance = 0;
    if ($ca_precedent > 0) {
        $ca_tendance = (($ca_data['ca_mensuel'] - $ca_precedent) / $ca_precedent) * 100;
    }
    
    // Ventes 30 derniers jours
    $stmt = $db->query("
        SELECT COUNT(*) as nb_ventes
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        AND status = 'completed'
    ");
    $ventes_30j = $stmt->fetchColumn();
    
    // Stock total
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(quantity), 0) as stock_total,
            COUNT(DISTINCT product_id) as nb_produits
        FROM stock
    ");
    $stock_data = $stmt->fetch();
    
    // Nombre d'employés actifs
    $stmt = $db->query("
        SELECT COUNT(*) as nb_employes
        FROM employees
        WHERE status = 'active'
    ");
    $nb_employes = $stmt->fetchColumn();
    
    // 🔥 TOP SNEAKERS DU MOIS
    $stmt = $db->query("
        SELECT 
            p.id,
            p.model,
            p.colorway,
            b.name as brand_name,
            b.color as brand_color,
            COUNT(sl.id) as nb_ventes,
            SUM(sl.total) as ca_total,
            COALESCE(SUM(s.quantity), 0) as stock_total,
            p.price
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        LEFT JOIN sale_lines sl ON p.id = sl.product_id
        LEFT JOIN sales sa ON sl.sale_id = sa.id 
            AND MONTH(sa.sale_date) = MONTH(CURRENT_DATE())
            AND YEAR(sa.sale_date) = YEAR(CURRENT_DATE())
            AND sa.status = 'completed'
        LEFT JOIN stock s ON p.id = s.product_id
        WHERE p.active = 1
        GROUP BY p.id
        HAVING nb_ventes > 0
        ORDER BY nb_ventes DESC, ca_total DESC
        LIMIT 5
    ");
    $top_sneakers = $stmt->fetchAll();
    
    // ⚡ ACTIVITÉS RÉCENTES
    $stmt = $db->query("
        SELECT 
            s.id,
            s.sale_number,
            s.sale_date,
            s.total,
            s.status,
            s.channel,
            CONCAT(c.first_name, ' ', SUBSTRING(c.last_name, 1, 1), '.') as client_name,
            GROUP_CONCAT(CONCAT(p.model, ' - T.', sl.size) SEPARATOR ', ') as products
        FROM sales s
        JOIN clients c ON s.client_id = c.id
        LEFT JOIN sale_lines sl ON s.id = sl.sale_id
        LEFT JOIN products p ON sl.product_id = p.id
        GROUP BY s.id
        ORDER BY s.sale_date DESC
        LIMIT 5
    ");
    $activites_recentes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de récupération des données : " . $e->getMessage();
}
?>

<div id="dashboard">
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error" style="margin-bottom: 2rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- KPI Cards -->
    <div class="cards-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.31-8.86c-1.77-.45-2.34-.94-2.34-1.67 0-.84.79-1.43 2.1-1.43 1.38 0 1.9.66 1.94 1.64h1.71c-.05-1.34-.87-2.57-2.49-2.97V5H10.9v1.69c-1.51.32-2.72 1.3-2.72 2.81 0 1.79 1.49 2.69 3.66 3.21 1.95.46 2.34 1.15 2.34 1.87 0 .53-.39 1.39-2.1 1.39-1.6 0-2.23-.72-2.32-1.64H8.04c.1 1.7 1.36 2.66 2.86 2.97V19h2.34v-1.67c1.52-.29 2.72-1.16 2.73-2.77-.01-2.2-1.9-2.96-3.66-3.42z"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo number_format($ca_data['ca_mensuel'], 0); ?>€</div>
            <div class="card-label">CA Mensuel</div>
            <div class="trend <?php echo $ca_tendance >= 0 ? 'up' : 'down'; ?>">
                <?php echo $ca_tendance >= 0 ? '↑' : '↓'; ?> 
                <?php echo abs(round($ca_tendance, 1)); ?>%
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(45deg, #4ECDC4, #FFE66D);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo number_format($ventes_30j); ?></div>
            <div class="card-label">Ventes (30j)</div>
            <div class="trend up">📈 Actif</div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(45deg, #FFE66D, #FF6B6B);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo number_format($stock_data['stock_total']); ?></div>
            <div class="card-label">Articles en Stock</div>
            <div class="trend"><?php echo $stock_data['nb_produits']; ?> produits</div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(45deg, #FF6B6B, #4ECDC4);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo $nb_employes; ?></div>
            <div class="card-label">Collaborateurs</div>
            <div class="trend up">✓ Actifs</div>
        </div>
    </div>

    <!-- Top Sneakers -->
    <div class="section-header">
        <h3>🔥 Top Sneakers du Mois</h3>
    </div>
    <div class="table-container">
        <?php if (empty($top_sneakers)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--secondary);">
                <p>Aucune vente ce mois-ci</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Rang</th>
                    <th>Modèle</th>
                    <th>Marque</th>
                    <th>Ventes</th>
                    <th>CA</th>
                    <th>Stock</th>
                    <th>Tendance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_sneakers as $index => $sneaker): ?>
                <tr>
                    <td><strong><?php echo $index + 1; ?></strong></td>
                    <td><?php echo htmlspecialchars($sneaker['model']); ?></td>
                    <td>
                        <span class="badge" style="background: <?php echo htmlspecialchars($sneaker['brand_color']); ?>20; color: <?php echo htmlspecialchars($sneaker['brand_color']); ?>;">
                            <?php echo htmlspecialchars($sneaker['brand_name']); ?>
                        </span>
                    </td>
                    <td><?php echo $sneaker['nb_ventes']; ?> paires</td>
                    <td><?php echo number_format($sneaker['ca_total'], 0); ?>€</td>
                    <td>
                        <?php 
                        $stock_class = 'success';
                        if ($sneaker['stock_total'] == 0) $stock_class = 'danger';
                        elseif ($sneaker['stock_total'] <= 10) $stock_class = 'warning';
                        ?>
                        <span class="badge <?php echo $stock_class; ?>">
                            <?php echo $sneaker['stock_total']; ?> restantes
                        </span>
                    </td>
                    <td>
                        <?php 
                        if ($index == 0) echo '🔥 Top 1';
                        elseif ($sneaker['nb_ventes'] >= 10) echo '📈 Excellent';
                        elseif ($sneaker['nb_ventes'] >= 5) echo '📊 Bon';
                        else echo '⭐ Nouveau';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Activités récentes -->
    <div class="section-header" style="margin-top: 2rem;">
        <h3>⚡ Activités Récentes</h3>
    </div>
    <div class="table-container">
        <?php if (empty($activites_recentes)): ?>
            <div style="text-align: center; padding: 2rem; color: var(--secondary);">
                <p>Aucune activité récente</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date & Heure</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Client</th>
                    <th>Montant</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activites_recentes as $activite): ?>
                <tr>
                    <td><?php echo date('d/m H:i', strtotime($activite['sale_date'])); ?></td>
                    <td>
                        <span class="badge <?php echo $activite['status'] == 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo $activite['channel'] == 'Web' ? '🌐' : '🏪'; ?> 
                            <?php echo htmlspecialchars($activite['channel']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(substr($activite['products'], 0, 50)); ?><?php echo strlen($activite['products']) > 50 ? '...' : ''; ?></td>
                    <td><?php echo htmlspecialchars($activite['client_name']); ?></td>
                    <td><strong><?php echo number_format($activite['total'], 2); ?>€</strong></td>
                    <td>
                        <?php
                        $status_labels = [
                            'completed' => '✓ Complété',
                            'pending' => '⏳ En attente',
                            'cancelled' => '✗ Annulé'
                        ];
                        $status_class = $activite['status'] == 'completed' ? 'success' : ($activite['status'] == 'pending' ? 'warning' : 'danger');
                        ?>
                        <span class="badge <?php echo $status_class; ?>">
                            <?php echo $status_labels[$activite['status']] ?? $activite['status']; ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>