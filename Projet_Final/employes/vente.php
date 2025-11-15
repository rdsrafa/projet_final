<?php
// employes/vente.php - Interface de nouvelle vente pour employé
session_start();
require_once '../include/db.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['employee', 'manager'])) {
    header('Location: ../login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'employé
$stmt = $db->prepare("SELECT * FROM v_employee_users WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Erreur : Employé introuvable");
}

// Récupérer la liste des clients actifs
$clients = $db->query("
    SELECT id, first_name, last_name, email, type, phone
    FROM clients 
    WHERE active = 1 
    ORDER BY last_name, first_name
")->fetchAll();

// Client sélectionné
$selected_client_id = $_GET['client_id'] ?? null;
$selected_client = null;

if ($selected_client_id) {
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = ? AND active = 1");
    $stmt->execute([$selected_client_id]);
    $selected_client = $stmt->fetch();
}

// Récupérer les produits disponibles avec stock
$products = $db->query("
    SELECT 
        p.*,
        b.name as brand_name,
        GROUP_CONCAT(CONCAT(s.size, ':', s.quantity) ORDER BY s.size SEPARATOR '|') as stock_data
    FROM products p
    JOIN brands b ON p.brand_id = b.id
    LEFT JOIN stock s ON p.id = s.product_id AND s.quantity > 0
    WHERE p.active = 1
    GROUP BY p.id
    HAVING stock_data IS NOT NULL
    ORDER BY b.name, p.model
")->fetchAll();

// Récupérer le panier temporaire
$cart = $_SESSION['temp_cart'] ?? [];

// Calculer les totaux
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.20;
$total = $subtotal + $tax;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle Vente - ADOO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .vente-container {
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
            transition: all 0.3s;
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
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        .client-selection {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
        }

        .client-selection h2 {
            margin-bottom: 1rem;
            color: var(--light);
        }

        .client-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .client-card {
            padding: 1rem;
            border: 2px solid var(--glass-border);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--dark);
        }

        .client-card:hover {
            border-color: var(--secondary);
            background: rgba(78, 205, 196, 0.1);
            transform: translateY(-3px);
        }

        .client-name {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--light);
        }

        .client-email {
            font-size: 0.9rem;
            color: var(--secondary);
        }

        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .badge-vip {
            background: rgba(255, 230, 109, 0.2);
            color: #FFE66D;
        }

        .badge-pro {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .sale-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .products-section {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 2rem;
            border-radius: 20px;
        }

        .cart-section {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 2rem;
            border-radius: 20px;
            position: sticky;
            top: 2rem;
            max-height: calc(100vh - 4rem);
            overflow-y: auto;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .product-card {
            border: 2px solid var(--glass-border);
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s;
            background: var(--dark);
        }

        .product-card:hover {
            border-color: var(--secondary);
            transform: translateY(-5px);
        }

        .product-image {
            width: 100%;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .product-brand {
            font-size: 0.9rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .product-model {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--light);
        }

        .product-price {
            font-size: 1.2rem;
            color: var(--secondary);
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
            color: var(--light);
        }

        .cart-total {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--secondary);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            margin-bottom: 1rem;
            color: var(--light);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            border: 2px solid var(--primary);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1rem;
            border: 2px solid;
        }

        .alert-warning {
            background: rgba(255, 230, 109, 0.2);
            border-color: #FFE66D;
            color: var(--light);
        }

        .empty-cart {
            text-align: center;
            padding: 2rem;
            color: var(--secondary);
        }

        @media (max-width: 768px) {
            .sale-content {
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

    <div class="vente-container">
        <div class="header">
            <div>
                <h1>🛒 Nouvelle Vente</h1>
                <small style="color: var(--secondary);">
                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                </small>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">← Retour</a>
        </div>

        <!-- Sélection du client -->
        <?php if (!$selected_client): ?>
        <div class="client-selection">
            <h2>👥 Sélectionner un Client</h2>
            <div class="alert alert-warning">
                ⚠️ Veuillez sélectionner un client pour commencer la vente
            </div>
            <div class="client-grid">
                <?php foreach ($clients as $client): ?>
                <div class="client-card" onclick="selectClient(<?= $client['id'] ?>)">
                    <div class="client-name">
                        <?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?>
                    </div>
                    <div class="client-email"><?= htmlspecialchars($client['email']) ?></div>
                    <?php if ($client['type'] === 'VIP'): ?>
                        <span class="badge badge-vip">⭐ VIP</span>
                    <?php elseif ($client['type'] === 'Pro'): ?>
                        <span class="badge badge-pro">💼 Pro</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Interface de vente -->
        <div class="client-selection">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2>Client sélectionné</h2>
                    <div class="client-name" style="font-size: 1.2rem; color: var(--secondary); margin-top: 0.5rem;">
                        <?= htmlspecialchars($selected_client['first_name'] . ' ' . $selected_client['last_name']) ?>
                        <?php if ($selected_client['type'] === 'VIP'): ?>
                            <span class="badge badge-vip">⭐ VIP</span>
                        <?php elseif ($selected_client['type'] === 'Pro'): ?>
                            <span class="badge badge-pro">💼 Pro</span>
                        <?php endif; ?>
                    </div>
                </div>
                <button class="btn btn-secondary" onclick="window.location.href='vente.php'">
                    Changer de client
                </button>
            </div>
        </div>

        <div class="sale-content">
            <!-- Produits -->
            <div class="products-section">
                <h2 style="color: var(--light);">👟 Produits Disponibles</h2>
                <div class="product-grid">
                    <?php foreach ($products as $product): 
                        $stock_items = [];
                        if ($product['stock_data']) {
                            foreach (explode('|', $product['stock_data']) as $item) {
                                list($size, $qty) = explode(':', $item);
                                $stock_items[$size] = $qty;
                            }
                        }
                    ?>
                    <div class="product-card">
                        <div class="product-image">👟</div>
                        <div class="product-brand"><?= htmlspecialchars($product['brand_name']) ?></div>
                        <div class="product-model"><?= htmlspecialchars($product['model']) ?></div>
                        <div class="product-price"><?= number_format($product['price'], 2) ?> €</div>
                        <button class="btn btn-primary" style="width: 100%;" 
                                onclick='showSizeModal(<?= json_encode([
                                    "id" => $product["id"],
                                    "brand_name" => $product["brand_name"],
                                    "model" => $product["model"],
                                    "price" => $product["price"],
                                    "stock" => $stock_items
                                ]) ?>)'>
                            Ajouter
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Panier -->
            <div class="cart-section">
                <h2 style="color: var(--light);">🛒 Panier (<?= count($cart) ?>)</h2>
                <hr style="margin: 1rem 0; border-color: var(--glass-border);">
                
                <?php if (empty($cart)): ?>
                    <div class="empty-cart">
                        <p>Votre panier est vide</p>
                        <p style="font-size: 0.9rem; margin-top: 0.5rem;">Ajoutez des produits pour commencer</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart as $index => $item): ?>
                    <div class="cart-item">
                        <div>
                            <strong><?= htmlspecialchars($item['model']) ?></strong><br>
                            <small style="color: var(--secondary);">T.<?= $item['size'] ?> × <?= $item['quantity'] ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong><?= number_format($item['price'] * $item['quantity'], 2) ?> €</strong><br>
                            <button onclick="removeFromCart(<?= $index ?>)" 
                                    style="background: none; border: none; color: var(--primary); cursor: pointer; font-size: 1.2rem;">
                                ✗
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="cart-total" style="color: var(--light);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Sous-total:</span>
                            <strong><?= number_format($subtotal, 2) ?> €</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--secondary);">
                            <span>TVA (20%):</span>
                            <span><?= number_format($tax, 2) ?> €</span>
                        </div>
                        <hr style="margin: 1rem 0; border-color: var(--glass-border);">
                        <div style="display: flex; justify-content: space-between; font-size: 1.2rem;">
                            <strong>Total:</strong>
                            <strong style="color: var(--secondary);"><?= number_format($total, 2) ?> €</strong>
                        </div>
                    </div>

                    <hr style="margin: 1rem 0; border-color: var(--glass-border);">

                    <div>
                        <label style="color: var(--light); font-weight: 600;"><strong>Mode de paiement:</strong></label>
                        <select id="payment_method" class="form-control">
                            <option value="CB">💳 Carte Bancaire</option>
                            <option value="Especes">💵 Espèces</option>
                            <option value="Virement">🏦 Virement</option>
                            <option value="Cheque">📝 Chèque</option>
                        </select>

                        <label style="color: var(--light); font-weight: 600;"><strong>Canal de vente:</strong></label>
                        <select id="channel" class="form-control">
                            <option value="Boutique">🏪 Boutique</option>
                            <option value="Web">🌐 E-commerce</option>
                        </select>

                        <button id="validateBtn" class="btn btn-success" style="width: 100%; font-size: 1.1rem; margin-top: 1rem;">
                            ✅ Valider la vente
                        </button>

                        <button class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem;" onclick="clearCart()">
                            🗑️ Vider le panier
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal sélection taille -->
    <div id="sizeModal" class="modal">
        <div class="modal-content">
            <h3 id="modalProductName" style="margin-bottom: 1rem; color: var(--light);"></h3>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
        let currentProduct = null;
        const clientId = <?= $selected_client_id ?? 'null' ?>;

        function selectClient(id) {
            window.location.href = 'vente.php?client_id=' + id;
        }

        function showSizeModal(product) {
            currentProduct = product;
            document.getElementById('modalProductName').textContent = 
                product.brand_name + ' - ' + product.model;
            
            const sizes = Object.keys(product.stock);
            
            let html = '<label style="color: var(--light); font-weight: 600;"><strong>Choisir une pointure:</strong></label>';
            html += '<select id="selectedSize" class="form-control">';
            sizes.forEach(size => {
                html += `<option value="${size}">Pointure ${size} (${product.stock[size]} dispo)</option>`;
            });
            html += '</select>';
            html += '<label style="color: var(--light); font-weight: 600;"><strong>Quantité:</strong></label>';
            html += '<input type="number" id="quantity" class="form-control" value="1" min="1" max="10">';
            html += '<button class="btn btn-primary" style="width: 100%; margin-top: 1rem;" onclick="addToCart()">Ajouter au panier</button>';
            html += '<button class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem;" onclick="closeModal()">Annuler</button>';
            
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('sizeModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('sizeModal').classList.remove('active');
        }

        async function addToCart() {
            const size = document.getElementById('selectedSize').value;
            const quantity = parseInt(document.getElementById('quantity').value);
            
            try {
                const response = await fetch('../modules/ventes/cart_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'add',
                        product_id: currentProduct.id,
                        model: currentProduct.model,
                        size: size,
                        quantity: quantity,
                        price: parseFloat(currentProduct.price)
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert('❌ ' + (data.error || 'Impossible d\'ajouter'));
                }
            } catch (error) {
                alert('❌ Erreur de connexion');
            }
        }

        async function removeFromCart(index) {
            if (!confirm('Retirer cet article ?')) return;
            
            try {
                const response = await fetch('../modules/ventes/cart_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'remove',
                        index: index
                    })
                });
                
                const data = await response.json();
                if (data.success) location.reload();
            } catch (error) {
                console.error(error);
            }
        }

        async function clearCart() {
            if (!confirm('Vider tout le panier ?')) return;
            
            try {
                await fetch('../modules/ventes/cart_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'clear'})
                });
                location.reload();
            } catch (error) {
                console.error(error);
            }
        }

        // Validation de la vente
        document.addEventListener('DOMContentLoaded', function() {
            const validateBtn = document.getElementById('validateBtn');
            
            if (validateBtn) {
                validateBtn.addEventListener('click', function() {
                    const payment = document.getElementById('payment_method').value;
                    const channel = document.getElementById('channel').value;
                    
                    this.innerHTML = '⏳ Validation...';
                    this.disabled = true;
                    
                    window.location.href = 'valider_vente.php?client_id=' + clientId +
                                          '&payment=' + payment + '&channel=' + channel;
                });
            }
        });
    </script>
</body>
</html>