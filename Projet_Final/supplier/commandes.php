<?php
/**
 * supplier/commandes.php
 * Gestion des bons de commande (purchase orders) pour le fournisseur
 */
session_start();

// Vérification de la connexion et du rôle
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'supplier') {
    header('Location: ../login.php');
    exit;
}

require_once '../include/db.php';

$supplier_id = $_SESSION['supplier_id'];
$supplier_name = $_SESSION['user_name'];

$success = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        switch($action) {
            case 'confirm_reception':
                $order_id = intval($_POST['order_id']);
                
                // Vérifier que le bon de commande appartient au fournisseur
                $stmt = $db->prepare("SELECT id FROM purchase_orders WHERE id = ? AND supplier_id = ?");
                $stmt->execute([$order_id, $supplier_id]);
                
                if ($stmt->fetch()) {
                    $stmt = $db->prepare("UPDATE purchase_orders SET status = 'received', received_date = NOW() WHERE id = ?");
                    $stmt->execute([$order_id]);
                    $success = "✅ Réception de la commande confirmée !";
                } else {
                    $error = "Vous n'avez pas accès à cette commande";
                }
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Filtrage
$filter = $_GET['filter'] ?? 'all';

// Récupérer les bons de commande du fournisseur
try {
    $db = getDB();
    
    // Requête de base
    $sql = "
        SELECT po.*, 
               COUNT(pol.id) as items_count,
               SUM(pol.quantity_ordered * pol.unit_cost) as calculated_total
        FROM purchase_orders po
        LEFT JOIN purchase_order_lines pol ON po.id = pol.purchase_order_id
        WHERE po.supplier_id = ?
    ";
    
    // Ajouter le filtre
    if ($filter !== 'all') {
        $sql .= " AND po.status = ?";
    }
    
    $sql .= " GROUP BY po.id ORDER BY po.order_date DESC";
    
    $stmt = $db->prepare($sql);
    
    if ($filter !== 'all') {
        $stmt->execute([$supplier_id, $filter]);
    } else {
        $stmt->execute([$supplier_id]);
    }
    
    $orders = $stmt->fetchAll();
    
    // Statistiques
    $stats = [
        'all' => 0,
        'draft' => 0,
        'sent' => 0,
        'received' => 0,
        'cancelled' => 0
    ];
    
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count
        FROM purchase_orders
        WHERE supplier_id = ?
        GROUP BY status
    ");
    $stmt->execute([$supplier_id]);
    
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
        $stats['all'] += $row['count'];
    }
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
    // CORRECTION: Initialiser $stats même en cas d'erreur
    $stats = [
        'all' => 0,
        'draft' => 0,
        'sent' => 0,
        'received' => 0,
        'cancelled' => 0
    ];
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes - Fournisseur</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #FF6B6B;
            --secondary: #4ECDC4;
            --accent: #FFE66D;
            --dark: #1A1A2E;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --light: #EAEAEA;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            color: var(--light);
            min-height: 100vh;
        }

        .supplier-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        /* Header */
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
            background: linear-gradient(45deg, var(--accent), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Navigation */
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
            background: linear-gradient(45deg, var(--accent), var(--secondary));
            transform: translateY(-2px);
            color: var(--dark);
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

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: rgba(78, 205, 196, 0.2);
            border: 1px solid var(--secondary);
            color: var(--secondary);
        }

        .alert-error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        /* Stats Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: var(--glass);
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid var(--glass-border);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .stat-box.active {
            border: 2px solid var(--secondary);
            background: rgba(78, 205, 196, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--accent), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* Orders Container */
        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .order-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
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
            font-size: 1.5rem;
            color: var(--light);
            font-weight: 700;
        }

        .order-date {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .order-status {
            padding: 0.6rem 1.2rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-draft {
            background: rgba(168, 168, 168, 0.2);
            color: #ccc;
        }

        .status-sent {
            background: rgba(255, 230, 109, 0.2);
            color: var(--accent);
        }

        .status-received {
            background: rgba(78, 205, 196, 0.3);
            color: #44A08D;
        }

        .status-cancelled {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .order-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .order-section h4 {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .order-section p {
            color: var(--light);
            margin-bottom: 0.5rem;
        }

        .order-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .btn-view:hover {
            background: var(--secondary);
            color: white;
        }

        .btn-confirm {
            background: linear-gradient(45deg, var(--secondary), #44A08D);
            color: white;
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
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
            max-width: 900px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--light);
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 2rem;
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-close:hover {
            transform: rotate(90deg);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--secondary);
        }

        .empty-state h3 {
            color: var(--light);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .order-body {
                grid-template-columns: 1fr;
            }

            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }

            .order-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-action {
                width: 100%;
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

    <div class="supplier-container">
        <!-- Header -->
        <div class="supplier-header">
            <div>
                <h1>📦 Mes Commandes</h1>
                <p style="color: var(--secondary); margin-top: 0.5rem;">
                    <?php echo htmlspecialchars($supplier_name); ?>
                </p>
            </div>
            <a href="../logout.php" class="btn-logout">🚪 Déconnexion</a>
        </div>

        <!-- Navigation -->
        <div class="supplier-nav">
            <a href="dashboard.php" class="nav-btn">🏠 Tableau de bord</a>
            <a href="commandes.php" class="nav-btn active">📦 Commandes</a>
            <a href="catalogue.php" class="nav-btn">👟 Mon Catalogue</a>
            <a href="profil.php" class="nav-btn">🏢 Mon Profil</a>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Bar -->
        <div class="stats-bar">
            <a href="?filter=all" class="stat-box <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $stats['all']; ?></div>
                <div class="stat-label">📋 Toutes</div>
            </a>
            <a href="?filter=draft" class="stat-box <?php echo $filter === 'draft' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $stats['draft']; ?></div>
                <div class="stat-label">📝 Brouillon</div>
            </a>
            <a href="?filter=sent" class="stat-box <?php echo $filter === 'sent' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $stats['sent']; ?></div>
                <div class="stat-label">📤 Envoyées</div>
            </a>
            <a href="?filter=received" class="stat-box <?php echo $filter === 'received' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $stats['received']; ?></div>
                <div class="stat-label">✅ Reçues</div>
            </a>
            <a href="?filter=cancelled" class="stat-box <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                <div class="stat-number"><?php echo $stats['cancelled']; ?></div>
                <div class="stat-label">❌ Annulées</div>
            </a>
        </div>

        <!-- Orders List -->
        <div class="orders-container">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <h3>📭 Aucune commande</h3>
                    <p>Vous n'avez pas encore de commandes<?php echo $filter !== 'all' ? ' avec ce statut' : ''; ?>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-number">
                                    #<?php echo htmlspecialchars($order['order_number']); ?>
                                </div>
                                <div class="order-date">
                                    📅 Commandé le: <?php echo date('d/m/Y', strtotime($order['order_date'])); ?>
                                </div>
                                <?php if ($order['expected_date']): ?>
                                <div class="order-date">
                                    🚚 Livraison prévue: <?php echo date('d/m/Y', strtotime($order['expected_date'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="order-status status-<?php echo $order['status']; ?>">
                                <?php 
                                $status_labels = [
                                    'draft' => '📝 Brouillon',
                                    'sent' => '📤 Envoyée',
                                    'received' => '✅ Reçue',
                                    'cancelled' => '❌ Annulée'
                                ];
                                echo $status_labels[$order['status']] ?? $order['status'];
                                ?>
                            </span>
                        </div>

                        <div class="order-body">
                            <div class="order-section">
                                <h4>📦 Détails</h4>
                                <p><strong><?php echo $order['items_count']; ?></strong> article(s)</p>
                                <?php if ($order['received_date']): ?>
                                <p>Reçue le: <strong><?php echo date('d/m/Y', strtotime($order['received_date'])); ?></strong></p>
                                <?php endif; ?>
                            </div>

                            <div class="order-section">
                                <h4>💰 Montant total</h4>
                                <p style="font-size: 1.5rem; font-weight: 700; color: var(--accent);">
                                    <?php echo number_format($order['total'], 2); ?> €
                                </p>
                            </div>
                        </div>

                        <div class="order-actions">
                            <button class="btn-action btn-view" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                👁️ Voir détails
                            </button>
                            
                            <?php if ($order['status'] === 'sent'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="confirm_reception">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" class="btn-action btn-confirm" onclick="return confirm('Confirmer la réception de cette commande ?')">
                                    ✅ Confirmer réception
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Order Details -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="orderModalTitle">Détails de la commande</h2>
                <button class="btn-close" onclick="closeOrderModal()">&times;</button>
            </div>

            <div id="orderDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        function viewOrder(orderId) {
            fetch(`get_order_details.php?order_id=${orderId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('orderDetailsContent').innerHTML = html;
                    document.getElementById('orderModal').classList.add('active');
                })
                .catch(error => {
                    alert('Erreur lors du chargement des détails');
                    console.error(error);
                });
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.remove('active');
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const orderModal = document.getElementById('orderModal');
            if (event.target === orderModal) {
                closeOrderModal();
            }
        }
    </script>
</body>
</html>