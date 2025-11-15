<?php
// employes/ventes_list.php - Liste des ventes (design unifié)
session_start();
require_once '../include/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['employee', 'manager'])) {
    header('Location: ../login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer l'employee_id
$stmt = $db->prepare("SELECT employee_id, first_name, last_name FROM v_employee_users WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee_data = $stmt->fetch();

if (!$employee_data) {
    die("Erreur : Employé introuvable");
}

$employe_id = $employee_data['employee_id'];

// Filtres
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');
$statut_filter = $_GET['statut'] ?? '';

// Requête
$query = "
    SELECT 
        s.id, s.sale_number, s.sale_date, s.subtotal, s.total,
        s.status, s.payment_method, s.channel,
        c.first_name as client_prenom, c.last_name as client_nom,
        c.email as client_email, COUNT(sl.id) as nb_articles
    FROM sales s
    LEFT JOIN clients c ON s.client_id = c.id
    LEFT JOIN sale_lines sl ON s.id = sl.sale_id
    WHERE s.employee_id = ?
    AND DATE(s.sale_date) BETWEEN ? AND ?
";

$params = [$employe_id, $date_debut, $date_fin];

if ($statut_filter) {
    $query .= " AND s.status = ?";
    $params[] = $statut_filter;
}

$query .= " GROUP BY s.id ORDER BY s.sale_date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$ventes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total_ventes' => count($ventes),
    'ca_total' => 0,
    'ca_ttc' => 0,
    'nb_articles' => 0
];

foreach ($ventes as $vente) {
    $stats['ca_total'] += $vente['subtotal'];
    $stats['ca_ttc'] += $vente['total'];
    $stats['nb_articles'] += $vente['nb_articles'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Ventes - ADOO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .ventes-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .header {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            font-size: 1.8rem;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            color: var(--dark);
        }

        .btn-secondary {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            color: var(--light);
        }

        .btn-success {
            background: linear-gradient(45deg, #2ed573, #26de81);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
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
            padding: 1.5rem;
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

        .stat-card h3 {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .filters {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            color: var(--light);
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--light);
        }

        .table-container {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 1.5rem;
            border-radius: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: var(--dark);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--light);
            border-bottom: 2px solid var(--glass-border);
        }

        table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            color: var(--light);
        }

        table tr:hover {
            background: rgba(255,255,255,0.05);
        }

        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }

        .badge-warning {
            background: rgba(255, 230, 109, 0.2);
            color: #FFE66D;
        }

        .badge-danger {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--secondary);
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
        }

        .alert {
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 3px solid;
            animation: slideInDown 0.6s ease;
        }

        .alert-success {
            background: rgba(46, 213, 115, 0.2);
            border-color: #2ed573;
            color: var(--light);
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
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

    <div class="ventes-container">
        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="header">
            <div>
                <h1>📊 Mes Ventes</h1>
                <small style="color: var(--secondary);">
                    <?= htmlspecialchars($employee_data['first_name'] . ' ' . $employee_data['last_name']) ?>
                </small>
            </div>
            <div class="btn-group">
                <a href="dashboard.php" class="btn btn-secondary">← Retour</a>
                <a href="vente.php" class="btn btn-success">+ Nouvelle Vente</a>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Nombre de Ventes</h3>
                <div class="value"><?= $stats['total_ventes'] ?></div>
            </div>
            <div class="stat-card">
                <h3>CA Total HT</h3>
                <div class="value"><?= number_format($stats['ca_total'], 0) ?> €</div>
            </div>
            <div class="stat-card">
                <h3>CA Total TTC</h3>
                <div class="value"><?= number_format($stats['ca_ttc'], 0) ?> €</div>
            </div>
            <div class="stat-card">
                <h3>Articles Vendus</h3>
                <div class="value"><?= $stats['nb_articles'] ?></div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters">
            <form method="GET">
                <div class="form-group">
                    <label>Date début</label>
                    <input type="date" name="date_debut" value="<?= $date_debut ?>">
                </div>
                <div class="form-group">
                    <label>Date fin</label>
                    <input type="date" name="date_fin" value="<?= $date_fin ?>">
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut">
                        <option value="">Tous</option>
                        <option value="pending" <?= $statut_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="completed" <?= $statut_filter === 'completed' ? 'selected' : '' ?>>Complétée</option>
                        <option value="cancelled" <?= $statut_filter === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                </div>
            </form>
        </div>

        <!-- Tableau des ventes -->
        <div class="table-container">
            <?php if (count($ventes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>N° Vente</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Canal</th>
                            <th>Articles</th>
                            <th>Montant HT</th>
                            <th>Montant TTC</th>
                            <th>Paiement</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ventes as $vente): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($vente['sale_number']) ?></strong></td>
                                <td><?= date('d/m/Y H:i', strtotime($vente['sale_date'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($vente['client_prenom'] . ' ' . $vente['client_nom']) ?>
                                    <br><small style="color: var(--secondary);"><?= htmlspecialchars($vente['client_email']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $canal_icons = ['Boutique' => '🏪', 'Web' => '🌐'];
                                    echo ($canal_icons[$vente['channel']] ?? '📦') . ' ' . $vente['channel'];
                                    ?>
                                </td>
                                <td><strong><?= $vente['nb_articles'] ?></strong></td>
                                <td><?= number_format($vente['subtotal'], 2) ?> €</td>
                                <td><strong style="color: var(--secondary);"><?= number_format($vente['total'], 2) ?> €</strong></td>
                                <td>
                                    <?php
                                    $paiement_icons = [
                                        'CB' => '💳',
                                        'Especes' => '💵',
                                        'Virement' => '🏦',
                                        'Cheque' => '📝'
                                    ];
                                    echo ($paiement_icons[$vente['payment_method']] ?? '💰') . ' ' . $vente['payment_method'];
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $badges = [
                                        'pending' => 'badge-warning',
                                        'completed' => 'badge-success',
                                        'cancelled' => 'badge-danger'
                                    ];
                                    $labels = [
                                        'pending' => '⏳ En attente',
                                        'completed' => '✅ Complétée',
                                        'cancelled' => '❌ Annulée'
                                    ];
                                    ?>
                                    <span class="badge <?= $badges[$vente['status']] ?? 'badge-warning' ?>">
                                        <?= $labels[$vente['status']] ?? $vente['status'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <h3>😕 Aucune vente trouvée</h3>
                    <p>Aucune vente ne correspond à vos critères pour la période sélectionnée.</p>
                    <a href="vente.php" class="btn btn-success" style="margin-top: 1rem;">
                        + Créer une nouvelle vente
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>