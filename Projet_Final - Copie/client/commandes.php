<?php
// client/commandes.php - Historique des commandes
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

require_once '../include/db.php';

$client_id = $_SESSION['client_id'];
$db = getDB();

// Récupérer les commandes du client
$stmt = $db->prepare("
    SELECT 
        s.*,
        COUNT(sl.id) as items_count,
        GROUP_CONCAT(CONCAT(p.model, ' (Taille ', sl.size, ')') SEPARATOR ', ') as products_summary
    FROM sales s
    LEFT JOIN sale_lines sl ON s.id = sl.sale_id
    LEFT JOIN products p ON sl.product_id = p.id
    WHERE s.client_id = ?
    GROUP BY s.id
    ORDER BY s.sale_date DESC
");
$stmt->execute([$client_id]);
$orders = $stmt->fetchAll();

// Statistiques
$stats = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total), 0) as total_spent,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_orders,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed_orders
    FROM sales
    WHERE client_id = ?
");
$stats->execute([$client_id]);
$stats = $stats->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes - Adoo Sneakers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .commandes-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .nav-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .nav-btn {
            padding: 1rem 2rem;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--light);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-btn:hover, .nav-btn.active {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0.5rem 0;
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .order-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .order-number {
            font-size: 1.3rem;
            color: var(--light);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .order-date {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .status-pending {
            background: rgba(255, 230, 109, 0.2);
            color: #FFE66D;
        }

        .status-completed {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .status-cancelled {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .status-refunded {
            background: rgba(168, 230, 207, 0.2);
            color: #A8E6CF;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.03);
            padding: 1rem;
            border-radius: 10px;
        }

        .info-label {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
        }

        .info-value {
            color: var(--light);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .order-products {
            background: rgba(255, 255, 255, 0.03);
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .products-label {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .products-list {
            color: var(--light);
            line-height: 1.6;
        }

        .order-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-detail {
            padding: 0.6rem 1.2rem;
            background: linear-gradient(45deg, var(--secondary), #44A08D);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
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
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--glass-border);
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

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .detail-table th {
            background: var(--glass);
            padding: 1rem;
            text-align: left;
            color: var(--secondary);
            font-weight: 600;
            border-bottom: 2px solid var(--glass-border);
        }

        .detail-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            color: var(--light);
        }

        .detail-table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .total-section {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--glass-border);
        }

        .total-row:last-child {
            border: none;
            margin-top: 0.5rem;
            padding-top: 1rem;
            border-top: 2px solid var(--glass-border);
        }

        .total-row:last-child .total-value {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
        }

        .empty-state h3 {
            color: var(--light);
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--secondary);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <div class="commandes-container">
        <!-- Navigation -->
        <div class="nav-bar">
            <a href="dashboard.php" class="nav-btn">🏠 Tableau de bord</a>
            <a href="catalogue.php" class="nav-btn">👟 Catalogue</a>
            <a href="commandes.php" class="nav-btn active">📦 Mes Commandes</a>
            <a href="profil.php" class="nav-btn">👤 Mon Profil</a>
            <a href="../logout.php" class="nav-btn" style="margin-left: auto;">🚪 Déconnexion</a>
        </div>

        <!-- Header -->
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 2rem; background: linear-gradient(45deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                📦 Mes Commandes
            </h1>
            <p style="color: var(--secondary); margin-top: 0.5rem;">
                Historique de vos achats
            </p>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Commandes</div>
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Dépensé</div>
                <div class="stat-value"><?php echo number_format($stats['total_spent'], 0); ?>€</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">En cours</div>
                <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Livrées</div>
                <div class="stat-value"><?php echo $stats['completed_orders']; ?></div>
            </div>
        </div>

        <!-- Liste des commandes -->
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <h3>📦 Aucune commande</h3>
                <p>Vous n'avez pas encore passé de commande</p>
                <a href="catalogue.php" class="btn btn-primary" style="display: inline-block; padding: 1rem 2rem; text-decoration: none;">
                    👟 Découvrir le Catalogue
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <div class="order-number">Commande #<?php echo htmlspecialchars($order['sale_number']); ?></div>
                        <div class="order-date">
                            📅 <?php echo date('d/m/Y à H:i', strtotime($order['sale_date'])); ?>
                            <?php if ($order['channel'] === 'Web'): ?>
                                <span style="background: rgba(78, 205, 196, 0.2); color: var(--secondary); padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.8rem; margin-left: 0.5rem;">
                                    🌐 Web
                                </span>
                            <?php else: ?>
                                <span style="background: rgba(255, 230, 109, 0.2); color: #FFE66D; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.8rem; margin-left: 0.5rem;">
                                    🏪 Boutique
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="order-status status-<?php echo $order['status']; ?>">
                        <?php 
                        $status_labels = [
                            'pending' => '⏳ En cours',
                            'completed' => '✅ Livrée',
                            'cancelled' => '❌ Annulée',
                            'refunded' => '💰 Remboursée'
                        ];
                        echo $status_labels[$order['status']];
                        ?>
                    </span>
                </div>

                <div class="order-info">
                    <div class="info-item">
                        <div class="info-label">📦 Articles</div>
                        <div class="info-value"><?php echo $order['items_count']; ?> produit(s)</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">💳 Paiement</div>
                        <div class="info-value">
                            <?php 
                            $payment_methods = [
                                'CB' => '💳 Carte Bancaire',
                                'Especes' => '💵 Espèces',
                                'Virement' => '🏦 Virement',
                                'Cheque' => '📝 Chèque'
                            ];
                            echo $payment_methods[$order['payment_method']];
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">💰 Montant Total</div>
                        <div class="info-value" style="color: var(--secondary);">
                            <?php echo number_format($order['total'], 2); ?>€
                        </div>
                    </div>
                </div>

                <?php if ($order['products_summary']): ?>
                <div class="order-products">
                    <div class="products-label">👟 Produits commandés :</div>
                    <div class="products-list">
                        <?php 
                        $products = explode(', ', $order['products_summary']);
                        foreach ($products as $product) {
                            echo '• ' . htmlspecialchars($product) . '<br>';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="order-actions">
                    <button class="btn-detail" onclick="showOrderDetail(<?php echo $order['id']; ?>)">
                        👁️ Voir le Détail
                    </button>
                    <?php if ($order['status'] === 'completed'): ?>
                    <button class="btn-detail" style="background: linear-gradient(45deg, #FFE66D, #FF6B6B);">
                        ⭐ Laisser un Avis
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Modal Détail Commande -->
    <div id="orderDetailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="color: var(--light);">📦 Détail de la Commande</h2>
                <span class="close-modal" onclick="closeOrderDetail()">&times;</span>
            </div>
            <div id="orderDetailContent">
                <!-- Contenu chargé dynamiquement -->
            </div>
        </div>
    </div>

    <script>
        function showOrderDetail(orderId) {
            // Charger les détails via AJAX ou afficher directement
            fetch(`get_order_detail.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div style="background: var(--glass); padding: 1.5rem; border-radius: 15px; margin-bottom: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="color: var(--light); margin-bottom: 0.5rem;">Commande #${data.sale_number}</h3>
                                    <p style="color: var(--secondary);">📅 ${data.sale_date}</p>
                                </div>
                                <span class="order-status status-${data.status}">${data.status_label}</span>
                            </div>
                        </div>

                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Taille</th>
                                    <th>Quantité</th>
                                    <th>Prix Unit.</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    data.lines.forEach(line => {
                        html += `
                            <tr>
                                <td>
                                    <div style="color: var(--secondary); font-size: 0.85rem; font-weight: 600;">${line.brand}</div>
                                    <div style="font-weight: 700;">${line.model}</div>
                                    <div style="opacity: 0.7; font-size: 0.9rem;">${line.colorway}</div>
                                </td>
                                <td><strong>${line.size}</strong></td>
                                <td>${line.quantity}</td>
                                <td>${line.unit_price}€</td>
                                <td><strong>${line.total}€</strong></td>
                            </tr>
                        `;
                    });

                    html += `
                            </tbody>
                        </table>

                        <div class="total-section">
                            <div class="total-row">
                                <span style="color: var(--secondary); font-weight: 600;">Sous-total</span>
                                <span style="color: var(--light); font-weight: 700;">${data.subtotal}€</span>
                            </div>
                            <div class="total-row">
                                <span style="color: var(--secondary); font-weight: 600;">TVA (20%)</span>
                                <span style="color: var(--light); font-weight: 700;">${data.tax}€</span>
                            </div>
                            <div class="total-row">
                                <span style="color: var(--secondary); font-weight: 700;">Total TTC</span>
                                <span class="total-value">${data.total}€</span>
                            </div>
                        </div>
                    `;

                    document.getElementById('orderDetailContent').innerHTML = html;
                    document.getElementById('orderDetailModal').classList.add('active');
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Impossible de charger les détails de la commande');
                });
        }

        function closeOrderDetail() {
            document.getElementById('orderDetailModal').classList.remove('active');
        }

        // Fermer modal en cliquant à l'extérieur
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>