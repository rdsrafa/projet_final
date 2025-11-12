<?php
// modules/ventes/panier.php - Interface de vente simplifiée

require_once __DIR__ . '/../../include/db.php';

// Déterminer le rôle AVANT de les utiliser
$is_employee = in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'employee']);
$is_client = ($_SESSION['user_role'] ?? '') === 'client';

try {
    $pdo = getDB();

    // Déterminer le client
    $selected_client_id = null;
    $selected_client_name = '';
    $selected_client_type = 'Regular';

    if ($is_client) {
        // Client connecté
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, type FROM clients WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $client_data = $stmt->fetch();
        if ($client_data) {
            $selected_client_id = $client_data['id'];
            $selected_client_name = $client_data['first_name'] . ' ' . $client_data['last_name'];
            $selected_client_type = $client_data['type'];
        }
    } elseif ($is_employee) {
        // Employé : vérifier si un client est sélectionné
        if (isset($_GET['client_id'])) {
            $selected_client_id = $_GET['client_id'];
            $stmt = $pdo->prepare("SELECT first_name, last_name, type FROM clients WHERE id = ? AND active = 1");
            $stmt->execute([$selected_client_id]);
            $client_data = $stmt->fetch();
            if ($client_data) {
                $selected_client_name = $client_data['first_name'] . ' ' . $client_data['last_name'];
                $selected_client_type = $client_data['type'];
            } else {
                // Client introuvable, rediriger
                header('Location: index.php?module=ventes&action=select_client');
                exit;
            }
        } else {
            // Aucun client sélectionné, rediriger automatiquement
            header('Location: index.php?module=ventes&action=select_client');
            exit;
        }
    }

    // Récupérer les produits disponibles avec leur stock
    $products = $pdo->query("
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

    // Récupérer le panier
    $cart = $_SESSION['temp_cart'] ?? [];

    // Calculer les totaux
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $tax = $subtotal * 0.20;
    $total = $subtotal + $tax;

} catch (Exception $e) {
    error_log("Erreur panier : " . $e->getMessage());
    $products = [];
    $cart = [];
    $subtotal = $tax = $total = 0;
}
?>

<div style="display: flex; gap: 2rem; flex-wrap: wrap;">
    <!-- Colonne gauche : Catalogue -->
    <div style="flex: 2; min-width: 300px;">
        
        <!-- Header -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>🛒</span> Nouvelle Vente
                </h2>
                <?php if ($is_employee && !$selected_client_id): ?>
                    <button class="btn btn-primary" onclick="window.location.href='index.php?module=ventes&action=select_client'">
                        👥 Choisir un client
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if ($is_employee): ?>
                <?php if (!$selected_client_id): ?>
                    <!-- Avertissement : aucun client -->
                    <div style="background: rgba(255, 193, 7, 0.3); border: 3px solid #ffc107; padding: 2rem; margin-bottom: 1.5rem; border-radius: 12px; text-align: center;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                        <h3 style="margin-bottom: 1rem; color: #ffc107;">Aucun client sélectionné</h3>
                        <p style="margin-bottom: 1.5rem; font-size: 1.1rem;">
                            Vous devez sélectionner un client pour effectuer une vente.
                        </p>
                        <button class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;" onclick="window.location.href='index.php?module=ventes&action=select_client'">
                            👥 Sélectionner un client
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Client sélectionné -->
                    <div class="card" style="padding: 1.5rem; margin-bottom: 1.5rem; background: rgba(46, 213, 115, 0.2); border: 2px solid #2ed573;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 0.9rem; color: var(--light); opacity: 0.8; margin-bottom: 0.25rem;">
                                    Client sélectionné
                                </div>
                                <div style="font-size: 1.3rem; font-weight: 700; color: #2ed573; display: flex; align-items: center; gap: 0.5rem;">
                                    <?php echo htmlspecialchars($selected_client_name); ?>
                                    <?php if ($selected_client_type === 'VIP'): ?>
                                        <span class="badge warning">⭐ VIP</span>
                                    <?php elseif ($selected_client_type === 'Pro'): ?>
                                        <span class="badge info">💼 Pro</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button class="btn btn-secondary btn-sm" onclick="window.location.href='index.php?module=ventes&action=select_client'">
                                Changer
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Catalogue produits -->
        <h3 style="margin-bottom: 1rem; color: var(--light);">👟 Catalogue Produits</h3>
        
        <?php if (empty($products)): ?>
            <div class="card" style="padding: 3rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📦</div>
                <h3>Aucun produit disponible</h3>
                <p style="color: var(--secondary);">Aucun produit n'est en stock.</p>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($products as $product): 
                    // Parser les données de stock
                    $stock_items = [];
                    if ($product['stock_data']) {
                        foreach (explode('|', $product['stock_data']) as $item) {
                            list($size, $qty) = explode(':', $item);
                            $stock_items[$size] = $qty;
                        }
                    }
                ?>
                <div class="product-card">
                    
                    <div style="background: linear-gradient(135deg, var(--primary), var(--secondary)); height: 100px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                        <span style="font-size: 2.5rem;">👟</span>
                    </div>
                    
                    <h4 style="margin: 0 0 0.5rem 0; color: var(--light); font-size: 0.9rem;">
                        <?php echo htmlspecialchars($product['brand_name']); ?>
                    </h4>
                    
                    <p style="margin: 0 0 1rem 0; color: var(--secondary); font-size: 0.85rem;">
                        <?php echo htmlspecialchars($product['model']); ?>
                    </p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span style="font-size: 1.2rem; font-weight: 700; color: var(--primary);">
                            <?php echo number_format($product['price'], 2, ',', ' ') . ' €'; ?>
                        </span>
                    </div>
                    
                    <button class="btn btn-sm btn-primary" 
                            style="width: 100%;"
                            type="button" 
                            onclick='showSizeModal(<?php echo json_encode([
                                "id" => $product["id"],
                                "brand_name" => $product["brand_name"],
                                "model" => $product["model"],
                                "price" => $product["price"],
                                "stock" => $stock_items
                            ]); ?>)'>
                        Ajouter
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Colonne droite : Panier -->
    <div style="flex: 1; min-width: 300px;">
        <div class="cart-summary">
            <h3>🛒 Panier (<?php echo count($cart); ?>)</h3>
            <hr>
            
            <?php if (empty($cart)): ?>
                <p style="text-align: center; color: var(--secondary); padding: 2rem 0;">
                    Votre panier est vide
                </p>
            <?php else: ?>
                <!-- Liste articles -->
                <?php foreach ($cart as $index => $item): ?>
                <div class="cart-item">
                    <div style="flex: 1;">
                        <strong><?php echo htmlspecialchars($item['model']); ?></strong>
                        <br>
                        <small style="color: var(--secondary);">
                            T.<?php echo $item['size']; ?> × <?php echo $item['quantity']; ?>
                        </small>
                    </div>
                    <div style="text-align: right;">
                        <strong><?php echo number_format($item['price'] * $item['quantity'], 2, ',', ' ') . ' €'; ?></strong>
                        <br>
                        <button onclick="removeFromCart(<?php echo $index; ?>)" class="remove-btn">
                            ✗
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <hr>
                
                <!-- Récapitulatif -->
                <div style="margin-top: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Sous-total :</span>
                        <strong><?php echo number_format($subtotal, 2, ',', ' ') . ' €'; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--secondary);">
                        <span>TVA (20%) :</span>
                        <span><?php echo number_format($tax, 2, ',', ' ') . ' €'; ?></span>
                    </div>
                    <hr>
                    <div style="display: flex; justify-content: space-between; font-size: 1.2rem;">
                        <strong>Total :</strong>
                        <strong style="color: var(--primary);"><?php echo number_format($total, 2, ',', ' ') . ' €'; ?></strong>
                    </div>
                </div>
                
                <hr>
                
                <!-- Paiement -->
                <div class="form-group">
                    <label><strong>Mode de paiement :</strong></label>
                    <select id="payment_method" class="form-control">
                        <option value="CB">💳 Carte Bancaire</option>
                        <option value="Especes">💵 Espèces</option>
                        <option value="Virement">🏦 Virement</option>
                        <option value="Cheque">📝 Chèque</option>
                    </select>
                </div>
                
                <?php if ($is_employee): ?>
                <div class="form-group">
                    <label><strong>Canal de vente :</strong></label>
                    <select id="channel" class="form-control">
                        <option value="Boutique">🏪 Boutique</option>
                        <option value="Web">🌐 E-commerce</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <button id="validateBtn" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem;">
                    ✅ Valider la vente
                </button>
                
                <button class="btn btn-secondary" style="width: 100%; margin-top: 0.5rem;" onclick="clearCart()">
                    🗑️ Vider le panier
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal sélection taille -->
<div id="sizeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalProductName"></h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalContent"></div>
    </div>
</div>

<style>
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.product-card {
    background: var(--card-bg);
    padding: 1rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.product-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 8px 20px rgba(108, 92, 231, 0.3);
}

.cart-summary {
    position: sticky;
    top: 1rem;
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid var(--primary);
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
}

.remove-btn {
    background: none;
    border: none;
    color: #e74c3c;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0.25rem 0.5rem;
}

.remove-btn:hover {
    color: #c0392b;
}

.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(5px);
}

.modal.active {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: 15px;
    max-width: 500px;
    width: 90%;
    border: 2px solid var(--primary);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 1rem;
}

.close-modal {
    font-size: 2rem;
    cursor: pointer;
    color: var(--secondary);
    background: none;
    border: none;
}

.close-modal:hover {
    color: var(--danger);
}

.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.badge.warning {
    background: rgba(255, 230, 109, 0.2);
    color: #f39c12;
}

.badge.info {
    background: rgba(78, 205, 196, 0.2);
    color: #4ecdc4;
}
</style>

<script>
let currentProduct = null;
const isEmployee = <?php echo $is_employee ? 'true' : 'false'; ?>;
const selectedClientId = <?php echo $selected_client_id ? $selected_client_id : 'null'; ?>;

console.log('🚀 Panier chargé - Client:', selectedClientId);

function showSizeModal(product) {
    console.log('📦 Ouverture modal:', product);
    
    if (isEmployee && !selectedClientId) {
        alert('⚠️ Veuillez sélectionner un client');
        window.location.href = 'index.php?module=ventes&action=select_client';
        return;
    }
    
    currentProduct = product;
    
    document.getElementById('modalProductName').textContent = product.brand_name + ' - ' + product.model;
    
    const sizes = Object.keys(product.stock);
    
    if (sizes.length === 0) {
        alert('❌ Aucune taille disponible');
        return;
    }
    
    let html = '<div class="form-group">';
    html += '<label><strong>Choisir une pointure :</strong></label>';
    html += '<select id="selectedSize" class="form-control" style="margin-bottom: 1rem;">';
    sizes.forEach(size => {
        html += `<option value="${size}">Pointure ${size} (${product.stock[size]} dispo)</option>`;
    });
    html += '</select>';
    html += '<label><strong>Quantité :</strong></label>';
    html += '<input type="number" id="quantity" class="form-control" value="1" min="1" max="10" style="margin-bottom: 1.5rem;">';
    html += '<button class="btn btn-primary" style="width: 100%;" onclick="addToCart()">✓ Ajouter au panier</button>';
    html += '</div>';
    
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
        const response = await fetch('modules/ventes/cart_handler.php', {
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
            alert('❌ Erreur : ' + (data.error || 'Impossible d\'ajouter'));
        }
    } catch (error) {
        console.error('❌ Erreur:', error);
        alert('❌ Erreur de connexion');
    }
}

async function removeFromCart(index) {
    if (!confirm('Retirer cet article ?')) return;
    
    try {
        const response = await fetch('modules/ventes/cart_handler.php', {
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
        console.error('❌ Erreur:', error);
    }
}

async function clearCart() {
    if (!confirm('Vider tout le panier ?')) return;
    
    try {
        await fetch('modules/ventes/cart_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'clear'})
        });
        location.reload();
    } catch (error) {
        console.error('❌ Erreur:', error);
    }
}

// Bouton validation
document.addEventListener('DOMContentLoaded', function() {
    const validateBtn = document.getElementById('validateBtn');
    
    if (validateBtn) {
        validateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (isEmployee && !selectedClientId) {
                alert('⚠️ Aucun client sélectionné');
                window.location.href = 'index.php?module=ventes&action=select_client';
                return;
            }
            
            const payment = document.getElementById('payment_method').value;
            const channel = document.getElementById('channel') ? document.getElementById('channel').value : 'Web';
            
            const url = 'index.php?module=ventes&action=valider' +
                       '&client_id=' + selectedClientId +
                       '&payment=' + encodeURIComponent(payment) +
                       '&channel=' + encodeURIComponent(channel);
            
            validateBtn.innerHTML = '⏳ Validation...';
            validateBtn.disabled = true;
            
            window.location.href = url;
        });
    }
});
</script>