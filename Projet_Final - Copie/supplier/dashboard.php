<?php
// supplier/dashboard.php - Espace Fournisseur
session_start();

// Vérifier si l'utilisateur est bien un fournisseur
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'supplier') {
    header('Location: ../login.php');
    exit;
}

require_once '../include/db.php';

// Récupérer les informations du fournisseur
$supplier_id = $_SESSION['supplier_id'];
$db = getDB();

// Infos fournisseur
$stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

// Commandes en cours
$stmt = $db->prepare("
    SELECT po.*, COUNT(pol.id) as items_count
    FROM purchase_orders po
    LEFT JOIN purchase_order_lines pol ON po.id = pol.purchase_order_id
    WHERE po.supplier_id = ?
    GROUP BY po.id
    ORDER BY po.order_date DESC
    LIMIT 5
");
$stmt->execute([$supplier_id]);
$recent_orders = $stmt->fetchAll();

// Statistiques
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(CASE WHEN status = 'received' THEN total ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN status IN ('draft', 'sent') THEN total ELSE 0 END), 0) as pending_amount
    FROM purchase_orders 
    WHERE supplier_id = ?
");
$stmt->execute([$supplier_id]);
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail Fournisseur - Adoo Sneakers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .supplier-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .supplier-header {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .supplier-header h1 {
            font-size: 2rem;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .supplier-nav {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
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
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 1rem 0;
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .content-section {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--light);
        }

        .order-card {
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--light);
        }

        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-sent {
            background: rgba(255, 230, 109, 0.2);
            color: #FFE66D;
        }

        .status-received {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .status-draft {
            background: rgba(168, 168, 168, 0.2);
            color: #ccc;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--light);
            font-weight: 600;
            margin-top: 0.3rem;
        }

        .btn-logout {
            padding: 0.8rem 1.5rem;
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid var(--primary);
            border-radius: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: var(--primary);
            color: white;
        }

        .rating-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(255, 230, 109, 0.2);
            border-radius: 20px;
            color: #FFE66D;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <div class="supplier-container">
        <!-- Header -->
        <div class="supplier-header">
            <div>
                <h1>🏭 Portail Fournisseur</h1>
                <p style="color: var(--secondary); font-size: 1.1rem; margin-top: 0.5rem;">
                    <?php echo htmlspecialchars($supplier['name']); ?>
                </p>
                <span class="rating-badge" style="margin-top: 0.5rem; display: inline-block;">
                    ⭐ Note: <?php echo $supplier['rating']; ?>/5
                </span>
            </div>
            <a href="../logout.php" class="btn-logout">🚪 Déconnexion</a>
        </div>

        <!-- Navigation -->
        <div class="supplier-nav">
            <a href="dashboard.php" class="nav-btn active">🏠 Tableau de bord</a>
            <a href="commandes.php" class="nav-btn">📦 Commandes</a>
            <a href="catalogue.php" class="nav-btn">👟 Mon Catalogue</a>
            <a href="profil.php" class="nav-btn">🏢 Mon Profil</a>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Commandes Totales</div>
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">CA Réalisé</div>
                <div class="stat-value"><?php echo number_format($stats['total_revenue'], 0); ?>€</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">En Attente</div>
                <div class="stat-value"><?php echo number_format($stats['pending_amount'], 0); ?>€</div>
            </div>
        </div>

        <!-- Dernières Commandes -->
        <div class="content-section">
            <h2 class="section-title">📦 Commandes Récentes</h2>
            <?php if (empty($recent_orders)): ?>
                <p style="text-align: center; color: var(--secondary); padding: 2rem;">
                    Aucune commande pour le moment.
                </p>
            <?php else: ?>
                <?php foreach ($recent_orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-number">
                            Commande #<?php echo htmlspecialchars($order['order_number']); ?>
                        </div>
                        <span class="order-status status-<?php echo $order['status']; ?>">
                            <?php 
                            $status_labels = [
                                'draft' => '📝 Brouillon',
                                'sent' => '📤 Envoyée',
                                'received' => '✓ Reçue',
                                'cancelled' => '✗ Annulée'
                            ];
                            echo $status_labels[$order['status']] ?? $order['status'];
                            ?>
                        </span>
                    </div>
                    <div class="order-details">
                        <div>
                            <div class="detail-item">Date de commande</div>
                            <div class="detail-value">
                                <?php echo date('d/m/Y', strtotime($order['order_date'])); ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-item">Date de livraison prévue</div>
                            <div class="detail-value">
                                <?php echo $order['expected_date'] ? date('d/m/Y', strtotime($order['expected_date'])) : 'Non définie'; ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-item">Articles</div>
                            <div class="detail-value"><?php echo $order['items_count']; ?> produits</div>
                        </div>
                        <div>
                            <div class="detail-item">Montant total</div>
                            <div class="detail-value" style="color: #FFE66D; font-size: 1.2rem;">
                                <?php echo number_format($order['total'], 2); ?>€
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Informations de contact -->
        <div class="content-section">
            <h2 class="section-title">📞 Contact Adoo Sneakers</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <div>
                    <div style="color: var(--secondary); margin-bottom: 0.5rem;">📧 Email</div>
                    <div style="color: var(--light); font-weight: 600;">contact@adoo-sneakers.fr</div>
                </div>
                <div>
                    <div style="color: var(--secondary); margin-bottom: 0.5rem;">📱 Téléphone</div>
                    <div style="color: var(--light); font-weight: 600;">+33 1 23 45 67 89</div>
                </div>
                <div>
                    <div style="color: var(--secondary); margin-bottom: 0.5rem;">⏰ Horaires</div>
                    <div style="color: var(--light); font-weight: 600;">Lun-Ven: 9h-18h</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>