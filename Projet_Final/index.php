<?php
// index.php - Page principale de l'ERP Adoo Sneakers
session_start();

// Simuler une connexion utilisateur pour la démo
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Inclure les fichiers nécessaires
require_once 'include/db.php';

// Récupérer le module demandé (par défaut: dashboard)
$module = $_GET['module'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// ⚠️ IMPORTANT : Capturer le contenu du module AVANT d'envoyer le HTML
ob_start();

// Router simple pour charger les modules
switch($module) {
    case 'ventes':
        if ($action === 'panier') {
            require_once 'modules/ventes/panier.php';
        } elseif ($action === 'clients') {
            require_once 'modules/ventes/clients.php';
        } elseif ($action === 'valider') {
            require_once 'modules/ventes/valider.php';
        } elseif ($action === 'cart_handler') {
            require_once 'modules/ventes/cart_handler.php';
        } elseif ($action === 'select_client') {
            require_once 'modules/ventes/select_client.php';
        } elseif ($action === 'nouveau_client') {
            require_once 'modules/ventes/nouveau_client.php';
        } elseif($action === 'commandes'){
            require_once 'modules/ventes/commandes.php';
        } else {
            require_once 'modules/ventes/ventes.php';
        }
        break;
    
    case 'achats':
        if ($action === 'ajout') {
            require_once 'modules/achats/ajout.php';
        } elseif ($action === 'reception') {
            require_once 'modules/achats/reception.php';
        } else {
            require_once 'modules/achats/achats.php';
        }
        break;
    

    case 'stocks':
        if ($action === 'inventaire') {
            require_once 'modules/stocks/inventaire.php';
        } elseif ($action === 'mouvement') {
            require_once 'modules/stocks/mouvement.php';
        } elseif ($action === 'produits') {
            require_once 'modules/stocks/produits.php';
        } elseif ($action === 'add_product') {
            // 🔴 AJOUTER CETTE LIGNE
            require_once 'modules/stocks/add_product.php';
        } else {
            require_once 'modules/stocks/produits.php';
        }
        break;
    
    case 'rh':
        if ($action === 'personnel') {
            require_once 'modules/rh/personnel.php';
        } elseif ($action === 'paie') {
            require_once 'modules/rh/paie.php';
        } elseif ($action === 'conges') {
            require_once 'modules/rh/conges.php';
        } elseif($action === 'process_leave'){
            require_once 'modules/rh/process_leave.php';
        } else {
            require_once 'modules/rh/personnel.php';
        }
        break;
    case 'personnel';
        if($action === 'employe_paie'){
            require_once 'modules/personnel/employe_paie.php';
        } elseif ($action === 'employe'){
            require_once 'modules/personnel/employe.php';
        }
        break;
    case 'admin':
        if ($action === 'suppliers') {
            require_once 'modules/admin/suppliers.php';
        }
        break;
    
    case 'dashboard':
    default:
        // Charger le module dashboard
        require_once 'modules/dashboard/dashboard.php';
        break;
}

// Récupérer le contenu capturé
$module_content = ob_get_clean();

// Inclure le header APRÈS avoir capturé le contenu des modules
require_once 'include/header.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adoo Sneakers - ERP Premium</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <div class="container">
        <!-- Sidebar -->
        <?php require_once 'include/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <h2 id="page-title">
                    <?php 
                    $titles = [
                        'dashboard' => 'Dashboard',
                        'ventes' => 'Gestion des Ventes',
                        'achats' => 'Gestion des Achats',
                        'stocks' => 'Gestion des Stocks',
                        'rh' => 'Ressources Humaines',
                        'admin' => 'Administration'
                    ];
                    echo $titles[$module] ?? 'Dashboard';
                    ?>
                </h2>
                <div class="user-info">
                    <div class="avatar">
                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'Admin', 0, 2)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></div>
                        <div style="font-size: 0.8rem; color: var(--secondary);">
                            <?php echo $_SESSION['user_role'] ?? 'Directeur'; ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Contenu dynamique selon le module -->
            <div class="module-content">
                <?php echo $module_content; ?>
            </div>
        </main>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>

<?php require_once 'include/footer.php'; ?>