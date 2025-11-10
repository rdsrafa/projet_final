<?php
/**
 * supplier/catalogue.php
 * Gestion du catalogue de produits du fournisseur
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
            case 'add':
                $brand_id = intval($_POST['brand_id']);
                $model = trim($_POST['model']);
                $colorway = trim($_POST['colorway']);
                $year = intval($_POST['year']);
                $sku = trim($_POST['sku']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $cost = floatval($_POST['cost']);
                
                if (empty($model) || empty($colorway) || empty($sku) || $price <= 0 || $cost <= 0) {
                    $error = "Veuillez remplir tous les champs correctement";
                } else {
                    // Vérifier que le SKU n'existe pas déjà
                    $stmt = $db->prepare("SELECT id FROM products WHERE sku = ?");
                    $stmt->execute([$sku]);
                    
                    if ($stmt->fetch()) {
                        $error = "Ce SKU existe déjà";
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO products (brand_id, model, colorway, year, sku, description, price, cost, supplier_id, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$brand_id, $model, $colorway, $year, $sku, $description, $price, $cost, $supplier_id]);
                        $success = "✅ Produit ajouté avec succès !";
                    }
                }
                break;
                
            case 'update':
                $product_id = intval($_POST['product_id']);
                $brand_id = intval($_POST['brand_id']);
                $model = trim($_POST['model']);
                $colorway = trim($_POST['colorway']);
                $year = intval($_POST['year']);
                $sku = trim($_POST['sku']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $cost = floatval($_POST['cost']);
                
                // Vérifier que le produit appartient bien au fournisseur
                $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND supplier_id = ?");
                $stmt->execute([$product_id, $supplier_id]);
                
                if (!$stmt->fetch()) {
                    $error = "Produit non trouvé";
                } elseif (empty($model) || empty($colorway) || empty($sku) || $price <= 0 || $cost <= 0) {
                    $error = "Veuillez remplir tous les champs correctement";
                } else {
                    // Vérifier que le SKU n'est pas utilisé par un autre produit
                    $stmt = $db->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
                    $stmt->execute([$sku, $product_id]);
                    
                    if ($stmt->fetch()) {
                        $error = "Ce SKU est déjà utilisé par un autre produit";
                    } else {
                        $stmt = $db->prepare("
                            UPDATE products 
                            SET brand_id = ?, model = ?, colorway = ?, year = ?, sku = ?, 
                                description = ?, price = ?, cost = ?, updated_at = NOW()
                            WHERE id = ? AND supplier_id = ?
                        ");
                        $stmt->execute([$brand_id, $model, $colorway, $year, $sku, $description, $price, $cost, $product_id, $supplier_id]);
                        $success = "✅ Produit mis à jour avec succès !";
                    }
                }
                break;
                
            case 'delete':
                $product_id = intval($_POST['product_id']);
                
                // Vérifier que le produit appartient bien au fournisseur
                $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND supplier_id = ?");
                $stmt->execute([$product_id, $supplier_id]);
                
                if (!$stmt->fetch()) {
                    $error = "Produit non trouvé";
                } else {
                    $stmt = $db->prepare("DELETE FROM products WHERE id = ? AND supplier_id = ?");
                    $stmt->execute([$product_id, $supplier_id]);
                    $success = "🗑️ Produit supprimé avec succès !";
                }
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer tous les produits du fournisseur
try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT p.*, b.name as brand_name,
               COALESCE(
                   (SELECT SUM(pol.quantity_ordered) 
                    FROM purchase_order_lines pol 
                    JOIN purchase_orders po ON pol.purchase_order_id = po.id 
                    WHERE pol.product_id = p.id 
                    AND po.supplier_id = ? 
                    AND po.status = 'received'), 
                   0
               ) as total_ordered,
               COALESCE(
                   (SELECT SUM(s.quantity) 
                    FROM stock s 
                    WHERE s.product_id = p.id), 
                   0
               ) as stock_quantity
        FROM products p
        JOIN brands b ON p.brand_id = b.id
        WHERE p.supplier_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$supplier_id, $supplier_id]);
    $products = $stmt->fetchAll();
    
    // Récupérer la liste des marques pour le formulaire
    $stmt = $db->prepare("SELECT id, name FROM brands ORDER BY name");
    $stmt->execute();
    $brands = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
    $products = [];
    $brands = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Catalogue - Fournisseur</title>
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

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--glass);
            border-radius: 15px;
            border: 1px solid var(--glass-border);
        }

        .btn-add {
            background: linear-gradient(45deg, var(--accent), var(--secondary));
            color: var(--dark);
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 230, 109, 0.4);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .product-name {
            font-size: 1.3rem;
            color: var(--light);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .product-brand {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .product-description {
            color: var(--light);
            opacity: 0.8;
            font-size: 0.95rem;
            margin: 1rem 0;
            line-height: 1.5;
        }

        .product-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--light);
            opacity: 0.7;
            margin-top: 0.3rem;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-action {
            flex: 1;
            padding: 0.8rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .btn-delete {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
            border: 1px solid var(--primary);
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
            max-width: 600px;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--secondary);
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--light);
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(255, 230, 109, 0.2);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, var(--accent), var(--secondary));
            color: var(--dark);
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 230, 109, 0.4);
        }

        .stock-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .stock-low {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .stock-medium {
            background: rgba(255, 230, 109, 0.2);
            color: var(--accent);
        }

        .stock-high {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
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

    <div class="supplier-container">
        <!-- Header -->
        <div class="supplier-header">
            <div>
                <h1>👟 Mon Catalogue</h1>
                <p style="color: var(--secondary); margin-top: 0.5rem;">
                    <?php echo htmlspecialchars($supplier_name); ?>
                </p>
            </div>
            <a href="../logout.php" class="btn-logout">🚪 Déconnexion</a>
        </div>

        <!-- Navigation -->
        <div class="supplier-nav">
            <a href="dashboard.php" class="nav-btn">🏠 Tableau de bord</a>
            <a href="commandes.php" class="nav-btn">📦 Commandes</a>
            <a href="catalogue.php" class="nav-btn active">👟 Mon Catalogue</a>
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

        <!-- Action Bar -->
        <div class="action-bar">
            <div>
                <h3 style="color: var(--light); margin-bottom: 0.5rem;">
                    Total: <?php echo count($products); ?> produit(s)
                </h3>
            </div>
            <button class="btn-add" onclick="openAddModal()">
                ➕ Ajouter un produit
            </button>
        </div>

        <!-- Products Grid -->
        <div class="products-grid">
            <?php if (empty($products)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 3rem; color: var(--secondary);">
                    <h3>Aucun produit dans votre catalogue</h3>
                    <p>Commencez par ajouter votre premier produit !</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-header">
                            <div>
                                <h3 class="product-name"><?php echo htmlspecialchars($product['model']); ?></h3>
                                <span class="product-brand"><?php echo htmlspecialchars($product['brand_name']); ?></span>
                            </div>
                            <?php
                            $stock = $product['stock_quantity'];
                            $stockClass = $stock <= 5 ? 'stock-low' : ($stock <= 20 ? 'stock-medium' : 'stock-high');
                            ?>
                            <span class="stock-badge <?php echo $stockClass; ?>">
                                📦 <?php echo $stock; ?>
                            </span>
                        </div>

                        <p style="color: var(--accent); font-size: 0.9rem; margin-bottom: 0.5rem;">
                            <?php echo htmlspecialchars($product['colorway']); ?> - <?php echo $product['year']; ?>
                        </p>

                        <p style="color: var(--secondary); font-size: 0.85rem; margin-bottom: 1rem;">
                            SKU: <?php echo htmlspecialchars($product['sku']); ?>
                        </p>

                        <?php if ($product['description']): ?>
                        <p class="product-description">
                            <?php echo htmlspecialchars($product['description']); ?>
                        </p>
                        <?php endif; ?>

                        <div class="product-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($product['price'], 2); ?> €</div>
                                <div class="stat-label">Prix vente</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo number_format($product['cost'], 2); ?> €</div>
                                <div class="stat-label">Coût</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $product['total_ordered']; ?></div>
                                <div class="stat-label">Commandés</div>
                            </div>
                        </div>

                        <div class="product-actions">
                            <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                ✏️ Modifier
                            </button>
                            <button class="btn-action btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['model'])); ?>')">
                                🗑️ Supprimer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Add/Edit Product -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Ajouter un produit</h2>
                <button class="btn-close" onclick="closeModal()">&times;</button>
            </div>

            <form id="productForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="product_id" id="productId">

                <div class="form-group">
                    <label for="brand_id">Marque *</label>
                    <select id="brand_id" name="brand_id" class="form-control" required>
                        <option value="">Sélectionnez une marque</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="model">Modèle *</label>
                    <input type="text" id="model" name="model" class="form-control" placeholder="Ex: Air Jordan 1" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="colorway">Coloris *</label>
                        <input type="text" id="colorway" name="colorway" class="form-control" placeholder="Ex: Chicago" required>
                    </div>

                    <div class="form-group">
                        <label for="year">Année *</label>
                        <input type="number" id="year" name="year" class="form-control" min="2000" max="2030" value="2024" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="sku">SKU *</label>
                    <input type="text" id="sku" name="sku" class="form-control" placeholder="Ex: NKE-AJ1-CHI-001" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Description du produit..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Prix de vente (€) *</label>
                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="cost">Coût (€) *</label>
                        <input type="number" id="cost" name="cost" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    💾 Enregistrer
                </button>
            </form>
        </div>
    </div>

    <!-- Form for deletion -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="product_id" id="deleteProductId">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter un produit';
            document.getElementById('formAction').value = 'add';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('productModal').classList.add('active');
        }

        function openEditModal(product) {
            document.getElementById('modalTitle').textContent = 'Modifier le produit';
            document.getElementById('formAction').value = 'update';
            document.getElementById('productId').value = product.id;
            document.getElementById('brand_id').value = product.brand_id;
            document.getElementById('model').value = product.model;
            document.getElementById('colorway').value = product.colorway;
            document.getElementById('year').value = product.year;
            document.getElementById('sku').value = product.sku;
            document.getElementById('description').value = product.description || '';
            document.getElementById('price').value = product.price;
            document.getElementById('cost').value = product.cost;
            document.getElementById('productModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
        }

        function deleteProduct(productId, productName) {
            if (confirm('Êtes-vous sûr de vouloir supprimer "' + productName + '" ?')) {
                document.getElementById('deleteProductId').value = productId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const productModal = document.getElementById('productModal');
            if (event.target === productModal) {
                closeModal();
            }
        }
    </script>
</body>
</html>