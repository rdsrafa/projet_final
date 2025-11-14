<?php
// modules/stocks/add_product.php - Traitement de l'ajout d'un nouveau produit

require_once __DIR__ . '/../../include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();
        $pdo->beginTransaction();
        
        // Récupérer les données du formulaire
        $brand_id = $_POST['brand_id'] ?? null;
        $model = trim($_POST['model'] ?? '');
        $colorway = trim($_POST['colorway'] ?? '');
        $year = $_POST['year'] ?? date('Y');
        $price = floatval($_POST['price'] ?? 0);
        $cost = floatval($_POST['cost'] ?? 0);
        $sku = trim($_POST['sku'] ?? '');
        
        // Validation des données
        if (empty($brand_id) || empty($model) || empty($colorway) || empty($sku)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis");
        }
        
        if ($price <= 0 || $cost <= 0) {
            throw new Exception("Les prix doivent être supérieurs à 0");
        }
        
        // Vérifier si le SKU existe déjà
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        if ($stmt->fetch()) {
            throw new Exception("Ce SKU existe déjà dans la base de données");
        }
        
        // Insérer le produit
        $stmt = $pdo->prepare("
            INSERT INTO products (
                brand_id, 
                model, 
                colorway, 
                year, 
                sku, 
                price, 
                cost, 
                active,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $brand_id,
            $model,
            $colorway,
            $year,
            $sku,
            $price,
            $cost
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        // Créer les entrées de stock pour les pointures standards (36 à 46)
        $stmt = $pdo->prepare("
            INSERT INTO stock (
                product_id, 
                size, 
                quantity, 
                location, 
                min_quantity, 
                max_quantity,
                created_at
            ) VALUES (?, ?, 0, 'Entrepôt', 5, 50, NOW())
        ");
        
        $standard_sizes = [36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46];
        foreach ($standard_sizes as $size) {
            $stmt->execute([$product_id, $size]);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Produit '$model' ajouté avec succès ! Vous pouvez maintenant gérer son stock.";
        header('Location: ?module=stocks&action=produits');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Erreur lors de l'ajout du produit : " . $e->getMessage();
        header('Location: ?module=stocks&action=produits');
        exit;
    }
} else {
    // Si on accède directement à cette page en GET, rediriger
    header('Location: ?module=stocks&action=produits');
    exit;
}
?>