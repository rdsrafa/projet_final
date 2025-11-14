<?php
// modules/ventes/cart_handler.php - Gestionnaire AJAX du panier de vente
session_start();
header('Content-Type: application/json');

// Configuration des logs
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Fonction de log
function logCart($message) {
    error_log("[CART] " . $message);
}

// Initialiser le panier s'il n'existe pas
if (!isset($_SESSION['temp_cart'])) {
    $_SESSION['temp_cart'] = [];
    logCart("Panier initialisé");
}

// Récupérer les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log de la requête
logCart("Requête reçue: " . $input);

// Vérifier les données
if (!$data || !isset($data['action'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Action non spécifiée',
        'received' => $data
    ]);
    exit;
}

$action = $data['action'];

try {
    switch ($action) {
        case 'add':
            // Ajouter un produit au panier
            if (!isset($data['product_id'], $data['size'], $data['quantity'], $data['price'])) {
                throw new Exception('Données incomplètes pour l\'ajout au panier');
            }
            
            // Valider les données
            $product_id = filter_var($data['product_id'], FILTER_VALIDATE_INT);
            $size = filter_var($data['size'], FILTER_SANITIZE_STRING);
            $quantity = filter_var($data['quantity'], FILTER_VALIDATE_INT);
            $price = filter_var($data['price'], FILTER_VALIDATE_FLOAT);
            
            if ($product_id === false || $quantity === false || $price === false || $quantity < 1 || $quantity > 100) {
                throw new Exception('Données invalides');
            }
            
            $item = [
                'product_id' => $product_id,
                'model' => $data['model'] ?? 'Produit',
                'size' => $size,
                'quantity' => $quantity,
                'price' => $price
            ];
            
            // Vérifier si le produit existe déjà dans le panier (même taille)
            $found = false;
            foreach ($_SESSION['temp_cart'] as &$cart_item) {
                if ($cart_item['product_id'] == $item['product_id'] && 
                    $cart_item['size'] == $item['size']) {
                    $cart_item['quantity'] += $item['quantity'];
                    $found = true;
                    logCart("Quantité mise à jour pour produit #{$product_id} taille {$size}");
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['temp_cart'][] = $item;
                logCart("Nouveau produit ajouté: #{$product_id} taille {$size} x{$quantity}");
            }
            
            // Calculer le total
            $total = 0;
            foreach ($_SESSION['temp_cart'] as $cart_item) {
                $total += $cart_item['price'] * $cart_item['quantity'];
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Produit ajouté au panier',
                'cart_count' => count($_SESSION['temp_cart']),
                'cart_total' => round($total, 2)
            ]);
            break;
            
        case 'remove':
            // Retirer un produit du panier
            if (!isset($data['index'])) {
                throw new Exception('Index non spécifié');
            }
            
            $index = filter_var($data['index'], FILTER_VALIDATE_INT);
            
            if ($index === false || !isset($_SESSION['temp_cart'][$index])) {
                throw new Exception('Produit introuvable dans le panier');
            }
            
            $removed_item = $_SESSION['temp_cart'][$index];
            array_splice($_SESSION['temp_cart'], $index, 1);
            // Réindexer le tableau
            $_SESSION['temp_cart'] = array_values($_SESSION['temp_cart']);
            
            logCart("Produit retiré: #{$removed_item['product_id']} taille {$removed_item['size']}");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Produit retiré du panier',
                'cart_count' => count($_SESSION['temp_cart'])
            ]);
            break;
            
        case 'update':
            // Mettre à jour la quantité d'un produit
            if (!isset($data['index'], $data['quantity'])) {
                throw new Exception('Données incomplètes pour la mise à jour');
            }
            
            $index = filter_var($data['index'], FILTER_VALIDATE_INT);
            $quantity = filter_var($data['quantity'], FILTER_VALIDATE_INT);
            
            if ($index === false || $quantity === false || !isset($_SESSION['temp_cart'][$index])) {
                throw new Exception('Données invalides');
            }
            
            if ($quantity > 0 && $quantity <= 100) {
                $_SESSION['temp_cart'][$index]['quantity'] = $quantity;
                logCart("Quantité mise à jour à {$quantity} pour index {$index}");
            } else if ($quantity === 0) {
                // Si quantité = 0, retirer l'article
                array_splice($_SESSION['temp_cart'], $index, 1);
                $_SESSION['temp_cart'] = array_values($_SESSION['temp_cart']);
                logCart("Produit retiré (quantité 0) pour index {$index}");
            } else {
                throw new Exception('Quantité invalide (max 100)');
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Quantité mise à jour',
                'cart_count' => count($_SESSION['temp_cart'])
            ]);
            break;
            
        case 'clear':
            // Vider le panier
            $item_count = count($_SESSION['temp_cart']);
            $_SESSION['temp_cart'] = [];
            
            logCart("Panier vidé ({$item_count} articles supprimés)");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Panier vidé',
                'cart_count' => 0
            ]);
            break;
            
        case 'get':
            // Récupérer le contenu du panier
            $subtotal = 0;
            foreach ($_SESSION['temp_cart'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
            
            $tax = $subtotal * 0.20;
            $total = $subtotal + $tax;
            
            echo json_encode([
                'success' => true,
                'cart' => $_SESSION['temp_cart'],
                'count' => count($_SESSION['temp_cart']),
                'subtotal' => round($subtotal, 2),
                'tax' => round($tax, 2),
                'total' => round($total, 2)
            ]);
            break;
            
        case 'count':
            // Obtenir uniquement le nombre d'articles
            $total_items = 0;
            foreach ($_SESSION['temp_cart'] as $item) {
                $total_items += $item['quantity'];
            }
            
            echo json_encode([
                'success' => true,
                'count' => count($_SESSION['temp_cart']),
                'total_items' => $total_items
            ]);
            break;
            
        default:
            throw new Exception('Action inconnue : ' . $action);
    }
    
} catch (Exception $e) {
    logCart("ERREUR: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'action' => $action ?? 'unknown'
    ]);
}

// Log final de l'état du panier
logCart("État final: " . count($_SESSION['temp_cart']) . " articles dans le panier");
?>