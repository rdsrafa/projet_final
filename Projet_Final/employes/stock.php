<?php
// employes/stock.php - Design unifié
session_start();
require_once '../include/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['employee', 'manager'])) {
    header('Location: ../login.php');
    exit;
}

// Récupérer les filtres
$brand_filter = $_GET['brand'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Construire la requête
$query = "SELECT * FROM v_stock_overview WHERE 1=1";
$params = [];

if ($brand_filter) {
    $query .= " AND brand = ?";
    $params[] = $brand_filter;
}

if ($status_filter) {
    $query .= " AND stock_status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (model LIKE ? OR colorway LIKE ? OR sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY brand, model, size";

$stmt = getDB()->prepare($query);
$stmt->execute($params);
$stock_items = $stmt->fetchAll();

// Récupérer les marques
$brands = getDB()->query("SELECT DISTINCT name FROM brands ORDER BY name")->fetchAll();

// Statistiques
$stats = [
    'total_items' => count($stock_items),
    'total_value' => array_sum(array_column($stock_items, 'stock_value')),
    'low_stock' => count(array_filter($stock_items, fn($item) => $item['stock_status'] === 'low')),
    'out_of_stock' => count(array_filter($stock_items, fn($item) => $item['stock_status'] === 'out'))
];

// Infos employé
$stmt = getDB()->prepare("SELECT first_name, last_name FROM v_employee_users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock - ADOO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stock-container {
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

        .btn-secondary {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            color: var(--light);
        }

        .btn-primary {
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            color: var(--dark);
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

        .stat-card.warning .value {
            background: linear-gradient(45deg, #FFE66D, #ff9800);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-card.danger .value {
            background: linear-gradient(45deg, var(--primary), #ff4757);
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
            position: sticky;
            top: 0;
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

        .badge-ok {
            background: rgba(46, 213, 115, 0.2);
            color: #2ed573;
        }

        .badge-low {
            background: rgba(255, 230, 109, 0.2);
            color: #FFE66D;
        }

        .badge-out {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--secondary);
        }

        @media (max-width: 768px) {
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

    <div class="stock-container">
        <div class="header">
            <div>
                <h1>📦 Consultation du Stock</h1>
                <small style="color: var(--secondary);">
                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                </small>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">← Retour</a>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Articles</h3>
                <div class="value"><?= $stats['total_items'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Valeur du Stock</h3>
                <div class="value"><?= number_format($stats['total_value'], 0) ?> €</div>
            </div>
            <div class="stat-card warning">
                <h3>Stock Bas</h3>
                <div class="value"><?= $stats['low_stock'] ?></div>
            </div>
            <div class="stat-card danger">
                <h3>Rupture</h3>
                <div class="value"><?= $stats['out_of_stock'] ?></div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters">
            <form method="GET">
                <div class="form-group">
                    <label>Recherche</label>
                    <input type="text" name="search" placeholder="Modèle, colorway, SKU..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="form-group">
                    <label>Marque</label>
                    <select name="brand">
                        <option value="">Toutes les marques</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= htmlspecialchars($brand['name']) ?>" 
                                    <?= $brand_filter === $brand['name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($brand['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="status">
                        <option value="">Tous les statuts</option>
                        <option value="ok" <?= $status_filter === 'ok' ? 'selected' : '' ?>>Disponible</option>
                        <option value="low" <?= $status_filter === 'low' ? 'selected' : '' ?>>Stock bas</option>
                        <option value="out" <?= $status_filter === 'out' ? 'selected' : '' ?>>Rupture</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                </div>
            </form>
        </div>

        <!-- Tableau du stock -->
        <div class="table-container">
            <?php if (count($stock_items) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Marque</th>
                            <th>Modèle</th>
                            <th>Coloris</th>
                            <th>Taille</th>
                            <th>Quantité</th>
                            <th>Stock Min</th>
                            <th>Emplacement</th>
                            <th>Prix</th>
                            <th>Valeur</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_items as $item): ?>
                            <tr>
                                <td><code style="background: var(--dark); padding: 0.3rem 0.5rem; border-radius: 5px;"><?= htmlspecialchars($item['sku']) ?></code></td>
                                <td><strong><?= htmlspecialchars($item['brand']) ?></strong></td>
                                <td><?= htmlspecialchars($item['model']) ?></td>
                                <td><?= htmlspecialchars($item['colorway']) ?></td>
                                <td><strong><?= $item['size'] ?></strong></td>
                                <td>
                                    <strong style="font-size: 1.1rem;">
                                        <?= $item['quantity'] ?>
                                    </strong>
                                </td>
                                <td><?= $item['min_quantity'] ?></td>
                                <td><?= htmlspecialchars($item['location']) ?></td>
                                <td><?= number_format($item['price'], 2) ?> €</td>
                                <td><?= number_format($item['stock_value'], 2) ?> €</td>
                                <td>
                                    <?php
                                    $status_map = [
                                        'ok' => ['badge-ok', 'Disponible'],
                                        'low' => ['badge-low', 'Stock bas'],
                                        'out' => ['badge-out', 'Rupture']
                                    ];
                                    $status_info = $status_map[$item['stock_status']];
                                    ?>
                                    <span class="badge <?= $status_info[0] ?>">
                                        <?= $status_info[1] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <h3>Aucun résultat</h3>
                    <p>Aucun article ne correspond à vos critères de recherche.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit du formulaire lors du changement des filtres
        document.querySelectorAll('select[name="brand"], select[name="status"]').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>