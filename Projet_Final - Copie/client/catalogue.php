<?php
// client/catalogue.php - Catalogue des produits avec système de commande
session_start();

// Vérifier si l'utilisateur est bien un client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

require_once '../include/db.php';

$client_id = $_SESSION['client_id'];
$db = getDB();
$success = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch($action) {
            case 'add_to_cart':
                $product_id = $_POST['product_id'];
                $size = $_POST['size'];
                $quantity = $_POST['quantity'] ?? 1;
                
                // Vérifier le stock
                $stmt = $db->prepare("SELECT quantity FROM stock WHERE product_id = ? AND size = ?");
                $stmt->execute([$product_id, $size]);
                $stock = $stmt->fetch();
                
                if (!$stock || $stock['quantity'] < $quantity) {
                    $error = "❌ Stock insuffisant pour cette taille";
                } else {
                    // Vérifier si déjà dans le panier
                    $stmt = $db->prepare("
                        SELECT id, quantity FROM cart 
                        WHERE client_id = ? AND product_id = ? AND size = ?
                    ");
                    $stmt->execute([$client_id, $product_id, $size]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Mettre à jour la quantité
                        $new_qty = $existing['quantity'] + $quantity;
                        $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                        $stmt->execute([$new_qty, $existing['id']]);
                    } else {
                        // Ajouter au panier
                        $stmt = $db->prepare("
                            INSERT INTO cart (client_id, product_id, size, quantity)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$client_id, $product_id, $size, $quantity]);
                    }
                    
                    $success = "✅ Produit ajouté au panier !";
                }
                break;
                
            case 'update_cart':
                $cart_id = $_POST['cart_id'];
                $quantity = $_POST['quantity'];
                
                if ($quantity > 0) {
                    $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND client_id = ?");
                    $stmt->execute([$quantity, $cart_id, $client_id]);
                } else {
                    $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND client_id = ?");
                    $stmt->execute([$cart_id, $client_id]);
                }
                $success = "✅ Panier mis à jour";
                break;
                
            case 'remove_from_cart':
                $cart_id = $_POST['cart_id'];
                $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND client_id = ?");
                $stmt->execute([$cart_id, $client_id]);
                $success = "🗑️ Produit retiré du panier";
                break;
                
            case 'place_order':
                // Créer la commande
                $db->beginTransaction();
                
                // Récupérer le panier
                $stmt = $db->prepare("
                    SELECT c.*, p.price 
                    FROM cart c
                    JOIN products p ON c.product_id = p.id
                    WHERE c.client_id = ?
                ");
                $stmt->execute([$client_id]);
                $cart_items = $stmt->fetchAll();
                
                if (empty($cart_items)) {
                    throw new Exception("Panier vide");
                }
                
                // Calculer le total
                $subtotal = 0;
                foreach ($cart_items as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                }
                
                $tax = $subtotal * 0.20;
                $total = $subtotal + $tax;
                
                // Créer la vente
                $sale_number = 'WEB-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("
                    INSERT INTO sales (sale_number, client_id, channel, status, subtotal, tax, total, payment_method, payment_status)
                    VALUES (?, ?, 'Web', 'pending', ?, ?, ?, 'CB', 'pending')
                ");
                $stmt->execute([$sale_number, $client_id, $subtotal, $tax, $total]);
                $sale_id = $db->lastInsertId();
                
                // Créer les lignes de vente
                foreach ($cart_items as $item) {
                    $line_total = $item['price'] * $item['quantity'];
                    $stmt = $db->prepare("
                        INSERT INTO sale_lines (sale_id, product_id, size, quantity, unit_price, total)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $sale_id,
                        $item['product_id'],
                        $item['size'],
                        $item['quantity'],
                        $item['price'],
                        $line_total
                    ]);
                }
                
                // Vider le panier
                $stmt = $db->prepare("DELETE FROM cart WHERE client_id = ?");
                $stmt->execute([$client_id]);
                
                // Mettre à jour les stats client
                $stmt = $db->prepare("
                    UPDATE clients 
                    SET total_spent = total_spent + ?, 
                        loyalty_points = loyalty_points + ?
                    WHERE id = ?
                ");
                $loyalty_points = floor($total / 10); // 1 point par 10€
                $stmt->execute([$total, $loyalty_points, $client_id]);
                
                $db->commit();
                
                $success = "🎉 Commande passée avec succès ! Numéro : $sale_number";
                header("Refresh: 2; url=commandes.php");
                break;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les produits
$products = $db->query("
    SELECT 
        p.*,
        b.name as brand_name,
        b.color as brand_color,
        (SELECT GROUP_CONCAT(CONCAT(size, ':', quantity) ORDER BY size) 
         FROM stock WHERE product_id = p.id AND quantity > 0) as available_sizes
    FROM products p
    JOIN brands b ON p.brand_id = b.id
    WHERE p.active = 1
    ORDER BY b.name, p.model
")->fetchAll();

// Récupérer le panier
$cart = $db->prepare("
    SELECT 
        c.*,
        p.model, p.colorway, p.price, p.sku,
        b.name as brand_name,
        s.quantity as stock_quantity
    FROM cart c
    JOIN products p ON c.product_id = p.id
    JOIN brands b ON p.brand_id = b.id
    LEFT JOIN stock s ON s.product_id = c.product_id AND s.size = c.size
    WHERE c.client_id = ?
");
$cart->execute([$client_id]);
$cart_items = $cart->fetchAll();

$cart_total = 0;
foreach ($cart_items as $item) {
    $cart_total += $item['price'] * $item['quantity'];
}
$cart_count = array_sum(array_column($cart_items, 'quantity'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - Adoo Sneakers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .catalogue-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .catalogue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
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

        .search-bar {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .search-input {
            width: 100%;
            padding: 1rem;
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--light);
            font-size: 1rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border-color: var(--secondary);
        }

        .product-image {
            width: 100%;
            height: 200px;
            background: var(--dark);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 4rem;
        }

        .product-brand {
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .product-name {
            font-size: 1.2rem;
            color: var(--light);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .product-colorway {
            color: var(--light);
            opacity: 0.7;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .sizes-selector {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .size-btn {
            padding: 0.5rem 1rem;
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--light);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .size-btn:hover:not(.disabled) {
            border-color: var(--secondary);
            background: rgba(78, 205, 196, 0.2);
        }

        .size-btn.selected {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-color: transparent;
        }

        .size-btn.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .cart-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .cart-button {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            position: relative;
            transition: all 0.3s ease;
        }

        .cart-button:hover {
            transform: scale(1.1);
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .cart-modal {
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

        .cart-modal.active {
            display: flex;
        }

        .cart-content {
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .close-cart {
            font-size: 2rem;
            color: var(--secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-cart:hover {
            color: var(--primary);
            transform: rotate(90deg);
        }

        .cart-item {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .qty-control {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .qty-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            color: var(--light);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background: var(--secondary);
            border-color: var(--secondary);
        }

        .cart-total {
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1500;
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
            max-width: 500px;
            width: 90%;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <div class="catalogue-container">
        <!-- Navigation -->
        <div class="nav-bar">
            <a href="dashboard.php" class="nav-btn">🏠 Tableau de bord</a>
            <a href="catalogue.php" class="nav-btn active">👟 Catalogue</a>
            <a href="commandes.php" class="nav-btn">📦 Mes Commandes</a>
            <a href="profil.php" class="nav-btn">👤 Mon Profil</a>
            <a href="../logout.php" class="nav-btn" style="margin-left: auto;">🚪 Déconnexion</a>
        </div>

        <?php if ($success): ?>
        <div class="alert-message alert-success" style="padding: 1rem; background: rgba(78, 205, 196, 0.2); border: 1px solid var(--secondary); border-radius: 12px; color: var(--secondary); margin-bottom: 1rem;">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-message alert-error" style="padding: 1rem; background: rgba(255, 107, 107, 0.2); border: 1px solid var(--primary); border-radius: 12px; color: var(--primary); margin-bottom: 1rem;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="catalogue-header">
            <div>
                <h1 style="font-size: 2rem; background: linear-gradient(45deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    👟 Catalogue Sneakers
                </h1>
                <p style="color: var(--secondary); margin-top: 0.5rem;">
                    <?php echo count($products); ?> sneakers disponibles
                </p>
            </div>
        </div>

        <!-- Barre de recherche -->
        <div class="search-bar">
            <input type="text" class="search-input" id="searchInput" placeholder="🔍 Rechercher une sneaker (marque, modèle, coloris...)" onkeyup="filterProducts()">
        </div>

        <!-- Grille de produits -->
        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
            <div class="product-card" data-search="<?php echo strtolower($product['brand_name'] . ' ' . $product['model'] . ' ' . $product['colorway']); ?>">
                <div class="product-image">👟</div>
                <div class="product-brand" style="color: <?php echo $product['brand_color']; ?>">
                    <?php echo htmlspecialchars($product['brand_name']); ?>
                </div>
                <div class="product-name"><?php echo htmlspecialchars($product['model']); ?></div>
                <div class="product-colorway"><?php echo htmlspecialchars($product['colorway']); ?></div>
                <div class="product-price"><?php echo number_format($product['price'], 2); ?>€</div>
                
                <?php if ($product['available_sizes']): ?>
                    <button class="btn btn-primary" style="width: 100%;" onclick="showProductModal(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                        👟 Commander
                    </button>
                <?php else: ?>
                    <button class="btn btn-secondary" style="width: 100%; opacity: 0.5;" disabled>
                        😢 Rupture de stock
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Panier flottant -->
        <?php if ($cart_count > 0): ?>
        <div class="cart-float">
            <button class="cart-button" onclick="toggleCart()">
                🛒
                <span class="cart-badge"><?php echo $cart_count; ?></span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Panier -->
    <div id="cartModal" class="cart-modal">
        <div class="cart-content">
            <div class="cart-header">
                <h2 style="color: var(--light);">🛒 Mon Panier</h2>
                <span class="close-cart" onclick="toggleCart()">&times;</span>
            </div>

            <?php if (empty($cart_items)): ?>
                <p style="text-align: center; color: var(--secondary); padding: 2rem;">
                    Votre panier est vide
                </p>
            <?php else: ?>
                <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div style="color: var(--secondary); font-weight: 600; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($item['brand_name']); ?>
                        </div>
                        <div style="color: var(--light); font-weight: 700; margin: 0.3rem 0;">
                            <?php echo htmlspecialchars($item['model']); ?>
                        </div>
                        <div style="color: var(--light); opacity: 0.7; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($item['colorway']); ?> • Taille <?php echo $item['size']; ?>
                        </div>
                        <div style="color: var(--secondary); font-weight: 700; font-size: 1.2rem; margin-top: 0.5rem;">
                            <?php echo number_format($item['price'], 2); ?>€
                        </div>
                    </div>
                    <div class="cart-item-actions">
                        <div class="qty-control">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_cart">
                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="quantity" value="<?php echo $item['quantity'] - 1; ?>">
                                <button type="submit" class="qty-btn">-</button>
                            </form>
                            <span style="color: var(--light); font-weight: 700; min-width: 30px; text-align: center;">
                                <?php echo $item['quantity']; ?>
                            </span>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_cart">
                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="quantity" value="<?php echo $item['quantity'] + 1; ?>">
                                <button type="submit" class="qty-btn" <?php echo $item['quantity'] >= $item['stock_quantity'] ? 'disabled' : ''; ?>>+</button>
                            </form>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="remove_from_cart">
                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" style="background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer;">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="cart-total">
                    <div class="total-row">
                        <span style="color: var(--secondary); font-weight: 600;">Sous-total HT</span>
                        <span style="color: var(--light); font-weight: 700;"><?php echo number_format($cart_total, 2); ?>€</span>
                    </div>
                    <div class="total-row">
                        <span style="color: var(--secondary); font-weight: 600;">TVA (20%)</span>
                        <span style="color: var(--light); font-weight: 700;"><?php echo number_format($cart_total * 0.20, 2); ?>€</span>
                    </div>
                    <div class="total-row">
                        <span style="color: var(--secondary); font-weight: 700;">Total TTC</span>
                        <span class="total-value"><?php echo number_format($cart_total * 1.20, 2); ?>€</span>
                    </div>
                </div>

                <form method="POST" style="margin-top: 2rem;">
                    <input type="hidden" name="action" value="place_order">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1.2rem; font-size: 1.1rem;" 
                            onclick="return confirm('Confirmer la commande de ' + <?php echo $cart_count; ?> + ' article(s) pour ' + <?php echo number_format($cart_total * 1.20, 2); ?> + '€ ?')">
                        ✅ Commander (<?php echo number_format($cart_total * 1.20, 2); ?>€)
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Produit -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalProductName" style="color: var(--light);"></h3>
                <span class="close-cart" onclick="closeProductModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="product_id" id="modalProductId">
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--secondary); font-weight: 600; margin-bottom: 0.5rem;">
                        Choisir la taille
                    </label>
                    <div id="modalSizes" class="sizes-selector"></div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--secondary); font-weight: 600; margin-bottom: 0.5rem;">
                        Quantité
                    </label>
                    <input type="number" name="quantity" value="1" min="1" max="10" 
                           style="width: 100%; padding: 0.8rem; background: var(--glass); border: 1px solid var(--glass-border); border-radius: 10px; color: var(--light);">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                    🛒 Ajouter au Panier
                </button>
            </form>
        </div>
    </div>

    <script>
        let selectedSize = null;

        function showProductModal(product) {
            document.getElementById('modalProductName').textContent = product.brand_name + ' ' + product.model;
            document.getElementById('modalProductId').value = product.id;
            
            const sizesContainer = document.getElementById('modalSizes');
            sizesContainer.innerHTML = '';
            
            if (product.available_sizes) {
                const sizes = product.available_sizes.split(',');
                sizes.forEach(sizeInfo => {
                    const [size, quantity] = sizeInfo.split(':');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'size-btn';
                    btn.textContent = size;
                    btn.onclick = function() {
                        document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('selected'));
                        this.classList.add('selected');
                        selectedSize = size;
                        // Créer input hidden pour la taille
                        let sizeInput = document.querySelector('input[name="size"]');
                        if (!sizeInput) {
                            sizeInput = document.createElement('input');
                            sizeInput.type = 'hidden';
                            sizeInput.name = 'size';
                            document.querySelector('#productModal form').appendChild(sizeInput);
                        }
                        sizeInput.value = size;
                    };
                    sizesContainer.appendChild(btn);
                });
            }
            
            document.getElementById('productModal').classList.add('active');
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.remove('active');
            selectedSize = null;
        }

        function toggleCart() {
            document.getElementById('cartModal').classList.toggle('active');
        }

        function filterProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            
            cards.forEach(card => {
                const searchText = card.dataset.search;
                if (searchText.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Fermer les modals en cliquant à l'extérieur
        document.querySelectorAll('.modal, .cart-modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>