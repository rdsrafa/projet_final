<?php
// employes/dashboard.php
session_start();
require_once '../include/db.php';

// Vérifier si l'utilisateur est connecté et est un employé
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['employee', 'manager'])) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

// Récupérer les informations de l'employé
$stmt = $db->prepare("SELECT * FROM v_employee_users WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Erreur : Employé introuvable");
}

// Récupérer les statistiques du jour
$today_sales = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total
    FROM sales 
    WHERE employee_id = ? AND DATE(sale_date) = CURDATE()
");
$today_sales->execute([$employee['employee_id']]);
$stats_today = $today_sales->fetch();

// Statistiques du mois
$month_sales = $db->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as total
    FROM sales 
    WHERE employee_id = ? 
    AND MONTH(sale_date) = MONTH(CURRENT_DATE())
    AND YEAR(sale_date) = YEAR(CURRENT_DATE())
");
$month_sales->execute([$employee['employee_id']]);
$stats_month = $month_sales->fetch();

// Récupérer les dernières ventes
$recent_sales = $db->prepare("
    SELECT s.*, c.first_name, c.last_name, c.type,
           COUNT(sl.id) as items_count
    FROM sales s
    INNER JOIN clients c ON s.client_id = c.id
    LEFT JOIN sale_lines sl ON s.id = sl.sale_id
    WHERE s.employee_id = ?
    GROUP BY s.id
    ORDER BY s.sale_date DESC
    LIMIT 5
");
$recent_sales->execute([$employee['employee_id']]);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Employé - Adoo Sneakers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .employee-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .employee-header {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .employee-header h1 {
            font-size: 2rem;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .employee-info {
            color: var(--secondary);
            font-size: 1.1rem;
        }

        .position-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(255, 230, 109, 0.2);
            border-radius: 20px;
            color: #FFE66D;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .employee-nav {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .nav-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            text-decoration: none;
            color: var(--light);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .nav-card:hover {
            transform: translateY(-5px);
            background: linear-gradient(45deg, rgba(255, 230, 109, 0.1), rgba(78, 205, 196, 0.1));
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .nav-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .nav-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-card p {
            color: var(--secondary);
            font-size: 0.9rem;
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
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #FFE66D, #4ECDC4);
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

        .stat-subtitle {
            color: var(--light);
            font-size: 0.85rem;
            margin-top: 0.5rem;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sale-card {
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .sale-card:hover {
            transform: translateX(5px);
            border-color: #FFE66D;
        }

        .sale-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .sale-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--light);
        }

        .sale-status {
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

        .status-cancelled {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .sale-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

        .client-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: rgba(78, 205, 196, 0.2);
            border-radius: 15px;
            color: var(--secondary);
            font-size: 0.85rem;
            font-weight: 600;
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
            transform: scale(1.05);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .employee-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .employee-nav {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
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

    <div class="employee-container">
        <!-- Header -->
        <div class="employee-header">
            <div>
                <h1>👤 Espace Employé</h1>
                <p class="employee-info">
                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                </p>
                <span class="position-badge">
                    📋 <?php echo htmlspecialchars($employee['position']); ?>
                </span>
            </div>
            <a href="../logout.php" class="btn-logout">🚪 Déconnexion</a>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Ventes Aujourd'hui</div>
                <div class="stat-value"><?php echo $stats_today['count']; ?></div>
                <div class="stat-subtitle">
                    <?php echo number_format($stats_today['total'], 2); ?> €
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ventes du Mois</div>
                <div class="stat-value"><?php echo $stats_month['count']; ?></div>
                <div class="stat-subtitle">
                    <?php echo number_format($stats_month['total'], 2); ?> €
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Objectif Mensuel</div>
                <div class="stat-value">
                    <?php 
                    $objectif = 50;
                    $progression = $objectif > 0 ? ($stats_month['count'] / $objectif) * 100 : 0;
                    echo min(100, round($progression)); 
                    ?>%
                </div>
                <div class="stat-subtitle">
                    <?php echo $stats_month['count']; ?> / <?php echo $objectif; ?> ventes
                </div>
            </div>
        </div>

        <!-- Actions Rapides -->
        <div class="employee-nav">
            <a href="vente.php" class="nav-card">
                <div class="nav-icon">🛒</div>
                <h3>Nouvelle Vente</h3>
                <p>Créer une vente</p>
            </a>
            <a href="stock.php" class="nav-card">
                <div class="nav-icon">📦</div>
                <h3>Stock</h3>
                <p>Consulter le stock</p>
            </a>
            <a href="ventes_list.php" class="nav-card">
                <div class="nav-icon">📊</div>
                <h3>Mes Ventes</h3>
                <p>Historique complet</p>
            </a>
            <a href="mes_conges.php" class="nav-card">
                <div class="nav-icon">🏖️</div>
                <h3>Congés</h3>
                <p>Gérer mes congés</p>
            </a>
            <a href="mes_fiches_paie.php" class="nav-card">
                <div class="nav-icon">💰</div>
                <h3>Fiches de Paie</h3>
                <p>Mes bulletins de salaire</p>
            </a>
        </div>

        <!-- Dernières Ventes -->
        <div class="content-section">
            <h2 class="section-title">📋 Dernières Ventes</h2>
            <?php if ($recent_sales->rowCount() == 0): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🛍️</div>
                    <p>Aucune vente enregistrée pour le moment.</p>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                        Commencez à enregistrer vos ventes !
                    </p>
                </div>
            <?php else: ?>
                <?php 
                $sales = $recent_sales->fetchAll();
                foreach ($sales as $sale): 
                ?>
                <div class="sale-card">
                    <div class="sale-header">
                        <div>
                            <div class="sale-number">
                                Vente #<?php echo htmlspecialchars($sale['sale_number']); ?>
                            </div>
                            <span class="client-badge">
                                👤 <?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?>
                            </span>
                        </div>
                        <span class="sale-status status-<?php echo $sale['status']; ?>">
                            <?php 
                            $status_labels = [
                                'completed' => '✓ Complétée',
                                'pending' => '⏳ En attente',
                                'cancelled' => '✗ Annulée'
                            ];
                            echo $status_labels[$sale['status']] ?? $sale['status'];
                            ?>
                        </span>
                    </div>
                    <div class="sale-details">
                        <div>
                            <div class="detail-item">Date & Heure</div>
                            <div class="detail-value">
                                <?php echo date('d/m/Y H:i', strtotime($sale['sale_date'])); ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-item">Type Client</div>
                            <div class="detail-value">
                                <?php echo ucfirst(htmlspecialchars($sale['type'])); ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-item">Articles</div>
                            <div class="detail-value"><?php echo $sale['items_count']; ?> produit(s)</div>
                        </div>
                        <div>
                            <div class="detail-item">Montant Total</div>
                            <div class="detail-value" style="color: #FFE66D; font-size: 1.2rem;">
                                <?php echo number_format($sale['total'], 2); ?> €
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Informations Utiles -->
        <div class="content-section">
            <h2 class="section-title">📞 Informations Utiles</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                <div>
                    <div style="color: var(--secondary); margin-bottom: 0.5rem;">📧 Email Support</div>
                    <div style="color: var(--light); font-weight: 600;">support@adoo-sneakers.fr</div>
                </div>
                <div>
                    <div style="color: var(--secondary); margin-bottom: 0.5rem;">📱 Téléphone</div>
                    <div style="color: var(--light); font-weight: 600;">+33 1 23 45 67 89</div>
                </div>
                <div>
                    <div style="color: var(--secondary); margin-bottom: 0.5rem;">⏰ Horaires</div>
                    <div style="color: var(--light); font-weight: 600;">Lun-Sam: 9h-19h</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>