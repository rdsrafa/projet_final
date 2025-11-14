<?php
// client/dashboard.php - Espace Client
session_start();

// Vérifier si l'utilisateur est bien un client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

require_once '../include/db.php';

// Récupérer les informations du client
$client_id = $_SESSION['client_id'];
$db = getDB();

// Infos client
$stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Dernières commandes
$stmt = $db->prepare("
    SELECT s.*, COUNT(sl.id) as items_count 
    FROM sales s
    LEFT JOIN sale_lines sl ON s.id = sl.sale_id
    WHERE s.client_id = ?
    GROUP BY s.id
    ORDER BY s.sale_date DESC
    LIMIT 5
");
$stmt->execute([$client_id]);
$recent_orders = $stmt->fetchAll();

// Statistiques
$stmt = $db->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(total), 0) as total_spent FROM sales WHERE client_id = ?");
$stmt->execute([$client_id]);
$stats = $stmt->fetch();

// Top 3 produits les plus vendus (tous clients)
$top_products = $db->query("
    SELECT p.model, p.colorway, b.name as brand, COUNT(*) as sales_count, p.price
    FROM sale_lines sl
    JOIN products p ON sl.product_id = p.id
    JOIN brands b ON p.brand_id = b.id
    GROUP BY sl.product_id
    ORDER BY sales_count DESC
    LIMIT 3
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Espace - Adoo Sneakers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .client-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .client-header {
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

        .client-header h1 {
            font-size: 2rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .client-nav {
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
            background: linear-gradient(45deg, var(--primary), var(--secondary));
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
            background: linear-gradient(45deg, var(--primary), var(--secondary));
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

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--secondary);
        }

        .product-brand {
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .product-name {
            font-size: 1.1rem;
            color: var(--light);
            margin-bottom: 0.5rem;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .order-card {
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-info h3 {
            color: var(--light);
            margin-bottom: 0.5rem;
        }

        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-completed {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .status-pending {
            background: rgba(255, 230, 109, 0.2);
            color: #FFE66D;
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
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <div class="client-container">
        <!-- Header -->
        <div class="client-header">
            <div>
                <h1>👟 Bienvenue <?php echo htmlspecialchars($client['first_name']); ?> !</h1>
                <p style="color: var(--secondary);">
                    <?php echo $client['type'] === 'VIP' ? '⭐ Membre VIP' : '👤 Membre'; ?> • 
                    <?php echo number_format($client['loyalty_points']); ?> points de fidélité
                </p>
            </div>
            <a href="../logout.php" class="btn-logout">🚪 Déconnexion</a>
        </div>

        <!-- Navigation -->
        <div class="client-nav">
            <a href="dashboard.php" class="nav-btn active">🏠 Tableau de bord</a>
            <a href="catalogue.php" class="nav-btn">👟 Catalogue</a>
            <a href="commandes.php" class="nav-btn">📦 Mes Commandes</a>
            <a href="profil.php" class="nav-btn">👤 Mon Profil</a>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Commandes</div>
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Dépensé</div>
                <div class="stat-value"><?php echo number_format($stats['total_spent'], 0); ?>€</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Points Fidélité</div>
                <div class="stat-value"><?php echo number_format($client['loyalty_points']); ?></div>
            </div>
        </div>

        <!-- Top Sneakers -->
        <div class="content-section">
            <h2 class="section-title">🔥 Sneakers les Plus Populaires</h2>
            <div class="product-grid">
                <?php foreach ($top_products as $product): ?>
                <div class="product-card">
                    <div class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></div>
                    <div class="product-name"><?php echo htmlspecialchars($product['model']); ?></div>
                    <div style="color: var(--light); opacity: 0.7; font-size: 0.9rem; margin-bottom: 1rem;">
                        <?php echo htmlspecialchars($product['colorway']); ?>
                    </div>
                    <div class="product-price"><?php echo number_format($product['price'], 2); ?>€</div>
                    <div style="margin-top: 1rem;">
                        <button style="width: 100%; padding: 0.8rem; background: linear-gradient(45deg, var(--primary), var(--secondary)); border: none; border-radius: 10px; color: white; font-weight: 600; cursor: pointer;">
                            Voir le produit
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Dernières Commandes -->
        <div class="content-section">
            <h2 class="section-title">📦 Mes Dernières Commandes</h2>
            <?php if (empty($recent_orders)): ?>
                <p style="text-align: center; color: var(--secondary); padding: 2rem;">
                    Vous n'avez pas encore passé de commande.
                </p>
            <?php else: ?>
                <?php foreach ($recent_orders as $order): ?>
                <div class="order-card">
                    <div class="order-info">
                        <h3>Commande #<?php echo htmlspecialchars($order['sale_number']); ?></h3>
                        <p style="color: var(--secondary); margin: 0.5rem 0;">
                            <?php echo date('d/m/Y', strtotime($order['sale_date'])); ?> • 
                            <?php echo $order['items_count']; ?> article(s)
                        </p>
                        <p style="color: var(--light); font-weight: 600;">
                            Total: <?php echo number_format($order['total'], 2); ?>€
                        </p>
                    </div>
                    <div>
                        <span class="order-status <?php echo $order['status'] === 'completed' ? 'status-completed' : 'status-pending'; ?>">
                            <?php 
                            echo $order['status'] === 'completed' ? '✓ Livrée' : 
                                ($order['status'] === 'pending' ? '⏳ En cours' : $order['status']); 
                            ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>