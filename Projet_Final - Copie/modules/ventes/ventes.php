<?php
// modules/ventes/ventes.php - Page principale du module Ventes
require_once __DIR__ . '/../../include/db.php';

try {
    $pdo = getDB();
    
    // Récupérer les vraies données depuis la base
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.sale_number,
            s.sale_date,
            CONCAT(c.first_name, ' ', c.last_name) as client_name,
            c.type as client_type,
            s.channel,
            s.status,
            s.total,
            GROUP_CONCAT(CONCAT(p.model, ' - T.', sl.size) SEPARATOR ', ') as products
        FROM sales s
        JOIN clients c ON s.client_id = c.id
        LEFT JOIN sale_lines sl ON s.id = sl.sale_id
        LEFT JOIN products p ON sl.product_id = p.id
        GROUP BY s.id
        ORDER BY s.sale_date DESC
        LIMIT 20
    ");
    $recent_sales = $stmt->fetchAll();

    // Statistiques
    $stats_query = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() THEN total ELSE 0 END), 0) as today_sales,
            COUNT(CASE WHEN DATE(sale_date) = CURDATE() THEN 1 END) as today_orders,
            COALESCE(SUM(CASE WHEN YEARWEEK(sale_date) = YEARWEEK(NOW()) THEN total ELSE 0 END), 0) as week_sales,
            COUNT(CASE WHEN YEARWEEK(sale_date) = YEARWEEK(NOW()) THEN 1 END) as week_orders,
            COALESCE(SUM(CASE WHEN MONTH(sale_date) = MONTH(NOW()) THEN total ELSE 0 END), 0) as month_sales,
            COUNT(CASE WHEN MONTH(sale_date) = MONTH(NOW()) THEN 1 END) as month_orders,
            COALESCE(AVG(total), 0) as avg_basket
        FROM sales
        WHERE status != 'cancelled'
    ");
    $stats = $stats_query->fetch();

    // Clients VIP
    $vip_clients = $pdo->query("
        SELECT * FROM clients 
        WHERE type = 'VIP' AND active = 1
        ORDER BY total_spent DESC 
        LIMIT 4
    ")->fetchAll();

} catch (Exception $e) {
    error_log("Erreur module ventes : " . $e->getMessage());
    $recent_sales = [];
    $stats = [
        'today_sales' => 0,
        'today_orders' => 0,
        'week_sales' => 0,
        'week_orders' => 0,
        'month_sales' => 0,
        'month_orders' => 0,
        'avg_basket' => 0
    ];
    $vip_clients = [];
}
?>

<div id="ventes-module">
    <!-- Messages de succès/erreur AMÉLIORÉS -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Actions rapides -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.2rem; color: var(--light); margin-bottom: 0.5rem;">Gestion des Ventes</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Gérez vos ventes multi-canal et clients VIP</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary" onclick="window.location.href='index.php?module=ventes&action=clients'">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                Clients
            </button>
            <a href="index.php?module=ventes&action=select_client" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                Nouvelle Vente
            </a>
        </div>
    </div>

    <!-- KPIs Ventes -->
    <div class="cards-grid">
        <div class="card">
            <div class="card-label">CA Aujourd'hui</div>
            <div class="card-value"><?php echo number_format($stats['today_sales'], 2, ',', ' ') . ' €'; ?></div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                <?php echo $stats['today_orders']; ?> commandes
            </div>
        </div>

        <div class="card">
            <div class="card-label">CA cette Semaine</div>
            <div class="card-value"><?php echo number_format($stats['week_sales'], 2, ',', ' ') . ' €'; ?></div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                <?php echo $stats['week_orders']; ?> commandes
            </div>
        </div>

        <div class="card">
            <div class="card-label">CA ce Mois</div>
            <div class="card-value"><?php echo number_format($stats['month_sales'], 2, ',', ' ') . ' €'; ?></div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                <?php echo $stats['month_orders']; ?> commandes
            </div>
        </div>

        <div class="card">
            <div class="card-label">Panier Moyen</div>
            <div class="card-value"><?php echo number_format($stats['avg_basket'], 2, ',', ' ') . ' €'; ?></div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                Par transaction
            </div>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="table-container" style="margin-bottom: 1rem; padding: 1rem;">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" class="form-control" placeholder="🔍 Rechercher une vente, client, produit..." 
                       onkeyup="filterSales(this.value)">
            </div>
            <select class="form-control" style="width: auto; min-width: 150px;" onchange="filterByStatus(this.value)">
                <option value="">Tous les statuts</option>
                <option value="completed">Complétées</option>
                <option value="pending">En attente</option>
                <option value="cancelled">Annulées</option>
            </select>
            <select class="form-control" style="width: auto; min-width: 150px;" onchange="filterByChannel(this.value)">
                <option value="">Tous les canaux</option>
                <option value="Boutique">Boutique</option>
                <option value="Web">E-commerce</option>
            </select>
        </div>
    </div>

    <!-- Liste des ventes -->
    <div class="table-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3>📊 Dernières Ventes</h3>
            <button class="btn btn-secondary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"/>
                </svg>
                Exporter
            </button>
        </div>
        
        <table id="sales-table">
            <thead>
                <tr>
                    <th>N° Vente</th>
                    <th>Date & Heure</th>
                    <th>Client</th>
                    <th>Produits</th>
                    <th>Canal</th>
                    <th>Montant</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_sales)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--secondary);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🛍️</div>
                            <h3 style="margin-bottom: 0.5rem;">Aucune vente enregistrée</h3>
                            <p>Créez votre première vente en cliquant sur "Nouvelle Vente"</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_sales as $sale): ?>
                    <tr class="sale-row" data-status="<?php echo $sale['status']; ?>" data-channel="<?php echo $sale['channel']; ?>">
                        <td><strong><?php echo $sale['sale_number']; ?></strong></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?></td>
                        <td>
                            <?php echo htmlspecialchars($sale['client_name']); ?>
                            <?php if ($sale['client_type'] === 'VIP'): ?>
                                <span class="badge" style="background: rgba(255,230,109,0.2); color: #f39c12; margin-left: 0.5rem;">⭐ VIP</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($sale['products'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($sale['channel'] === 'Boutique'): ?>
                                <span class="badge info">🏪 <?php echo $sale['channel']; ?></span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(108,92,231,0.2); color: #6C5CE7;">🌐 <?php echo $sale['channel']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo number_format($sale['total'], 2, ',', ' ') . ' €'; ?></strong></td>
                        <td>
                            <?php if ($sale['status'] === 'completed'): ?>
                                <span class="badge success">✓ Complétée</span>
                            <?php elseif ($sale['status'] === 'pending'): ?>
                                <span class="badge warning">⏳ En attente</span>
                            <?php else: ?>
                                <span class="badge danger">✗ Annulée</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-secondary" title="Voir détails" 
                                    onclick="window.location.href='index.php?module=ventes&action=detail&id=<?php echo $sale['id']; ?>'">
                                👁️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Clients VIP du mois -->
    <?php if (!empty($vip_clients)): ?>
    <div class="section-header" style="margin-top: 2rem;">
        <h3>⭐ Clients VIP - Top Acheteurs</h3>
    </div>
    <div class="cards-grid">
        <?php foreach ($vip_clients as $client): ?>
        <div class="card" style="cursor: pointer;" onclick="window.location.href='index.php?module=ventes&action=panier&client_id=<?php echo $client['id']; ?>'">
            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                <div class="avatar" style="width: 50px; height: 50px; font-size: 1.2rem;">
                    <?php echo strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)); ?>
                </div>
                <div style="flex: 1;">
                    <h4 style="margin: 0; color: var(--light);">
                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                    </h4>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--secondary);"><?php echo htmlspecialchars($client['email']); ?></p>
                </div>
                <span class="badge warning">⭐ VIP</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="font-size: 0.85rem; color: var(--light); opacity: 0.7;">Total dépensé</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--secondary);">
                        <?php echo number_format($client['total_spent'], 2, ',', ' ') . ' €'; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<style>
/* ============================================
   STYLES AMÉLIORÉS POUR LES MESSAGES
   ============================================ */
.alert {
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    border-radius: 15px;
    border: 3px solid;
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    animation: slideInDown 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    position: relative;
    overflow: hidden;
}

.alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.alert-success {
    background: linear-gradient(135deg, 
        rgba(46, 213, 115, 0.25) 0%, 
        rgba(46, 213, 115, 0.15) 50%,
        rgba(46, 213, 115, 0.25) 100%
    );
    border-color: #2ed573;
    color: var(--light);
}

.alert-error {
    background: linear-gradient(135deg, 
        rgba(255, 71, 87, 0.25) 0%, 
        rgba(255, 71, 87, 0.15) 50%,
        rgba(255, 71, 87, 0.25) 100%
    );
    border-color: #ff4757;
    color: var(--light);
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Animation de pulsation pour attirer l'attention */
.alert {
    animation: slideInDown 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), 
               pulse 2s ease-in-out 0.6s;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }
    50% {
        box-shadow: 0 8px 35px rgba(46, 213, 115, 0.5);
    }
}

.alert-error {
    animation: slideInDown 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), 
               pulseError 2s ease-in-out 0.6s;
}

@keyframes pulseError {
    0%, 100% {
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }
    50% {
        box-shadow: 0 8px 35px rgba(255, 71, 87, 0.5);
    }
}

/* Style pour le contenu HTML dans les messages */
.alert h3 {
    margin: 0;
    font-size: 1.5rem;
}

.alert p {
    margin: 0.5rem 0;
}

.alert strong {
    font-weight: 700;
    font-size: 1.1em;
}
</style>

<script>
function filterSales(searchTerm) {
    const rows = document.querySelectorAll('#sales-table tbody tr.sale-row');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
    });
}

function filterByStatus(status) {
    const rows = document.querySelectorAll('.sale-row');
    rows.forEach(row => {
        if (!status || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterByChannel(channel) {
    const rows = document.querySelectorAll('.sale-row');
    rows.forEach(row => {
        if (!channel || row.dataset.channel === channel) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Auto-hide des messages après 8 secondes
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'all 0.5s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 500);
    });
}, 8000);
</script>