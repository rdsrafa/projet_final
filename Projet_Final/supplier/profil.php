<?php
/**
 * supplier/profil.php
 * Gestion du profil du fournisseur
 */
session_start();

// Vérification de la connexion et du rôle
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'supplier') {
    header('Location: ../login.php');
    exit;
}

require_once '../include/db.php';

$supplier_id = $_SESSION['supplier_id'];
$user_id = $_SESSION['user_id'];

$success = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDB();
        
        switch($action) {
            case 'update_info':
                $name = trim($_POST['name']);
                $contact_name = trim($_POST['contact_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $postal_code = trim($_POST['postal_code']);
                $country = trim($_POST['country']);
                $siret = trim($_POST['siret']);
                $notes = trim($_POST['notes']);
                
                if (empty($name) || empty($email)) {
                    $error = "Le nom et l'email sont obligatoires";
                } else {
                    // Vérifier si l'email n'est pas déjà utilisé par un autre compte
                    $stmt = $db->prepare("SELECT id FROM suppliers WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $supplier_id]);
                    
                    if ($stmt->fetch()) {
                        $error = "Cet email est déjà utilisé";
                    } else {
                        $stmt = $db->prepare("
                            UPDATE suppliers 
                            SET name = ?, contact_name = ?, email = ?, phone = ?, 
                                address = ?, city = ?, postal_code = ?, country = ?, 
                                siret = ?, notes = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $name, $contact_name, $email, $phone, 
                            $address, $city, $postal_code, $country, 
                            $siret, $notes, $supplier_id
                        ]);
                        
                        // Mettre à jour l'email dans la table users aussi
                        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
                        $stmt->execute([$email, $user_id]);
                        
                        $success = "✅ Profil mis à jour avec succès !";
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = "Tous les champs sont obligatoires";
                } elseif ($new_password !== $confirm_password) {
                    $error = "Les nouveaux mots de passe ne correspondent pas";
                } elseif (strlen($new_password) < 6) {
                    $error = "Le mot de passe doit contenir au moins 6 caractères";
                } else {
                    // Vérifier le mot de passe actuel
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!password_verify($current_password, $user['password_hash'])) {
                        $error = "Mot de passe actuel incorrect";
                    } else {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$new_hash, $user_id]);
                        $success = "🔒 Mot de passe modifié avec succès !";
                    }
                }
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les informations du fournisseur
try {
    $db = getDB();
    
    $stmt = $db->prepare("
        SELECT s.*, u.username, u.email as user_email
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch();
    
    // Statistiques du fournisseur
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(CASE WHEN status = 'received' THEN total ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN status IN ('draft', 'sent') THEN total ELSE 0 END), 0) as pending_amount,
            COALESCE(AVG(CASE WHEN status = 'received' THEN total ELSE NULL END), 0) as avg_order_value
        FROM purchase_orders 
        WHERE supplier_id = ?
    ");
    $stmt->execute([$supplier_id]);
    $stats = $stmt->fetch();
    
    // Dernière commande
    $stmt = $db->prepare("
        SELECT * FROM purchase_orders 
        WHERE supplier_id = ? 
        ORDER BY order_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$supplier_id]);
    $last_order = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Fournisseur</title>
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

        /* Stats Cards */
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
            padding: 2rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--accent), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 1rem 0;
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-section {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
        }

        .content-section.full-width {
            grid-column: 1 / -1;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form Styles */
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

        .form-control:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        /* Info Display */
        .info-item {
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .info-label {
            color: var(--secondary);
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
        }

        .info-value {
            color: var(--light);
            font-weight: 600;
            font-size: 1.1rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-approved {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .status-pending {
            background: rgba(255, 230, 109, 0.2);
            color: var(--accent);
        }

        .status-rejected {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
        }

        .star {
            color: var(--accent);
        }

        @media (max-width: 1024px) {
            .content-grid {
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
                <h1>🏢 Mon Profil</h1>
                <p style="color: var(--secondary); margin-top: 0.5rem;">
                    <?php echo htmlspecialchars($supplier['name']); ?>
                </p>
            </div>
            <a href="../logout.php" class="btn-logout">🚪 Déconnexion</a>
        </div>

        <!-- Navigation -->
        <div class="supplier-nav">
            <a href="dashboard.php" class="nav-btn">🏠 Tableau de bord</a>
            <a href="commandes.php" class="nav-btn">📦 Commandes</a>
            <a href="catalogue.php" class="nav-btn">👟 Mon Catalogue</a>
            <a href="profil.php" class="nav-btn active">🏢 Mon Profil</a>
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

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Commandes Totales</div>
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">CA Réalisé</div>
                <div class="stat-value"><?php echo number_format($stats['total_revenue'], 0); ?>€</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">En Attente</div>
                <div class="stat-value"><?php echo number_format($stats['pending_amount'], 0); ?>€</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Panier Moyen</div>
                <div class="stat-value"><?php echo number_format($stats['avg_order_value'], 0); ?>€</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Informations du compte -->
            <div class="content-section">
                <h2 class="section-title">👤 Informations du compte</h2>
                
                <div class="info-item">
                    <div class="info-label">Nom d'utilisateur</div>
                    <div class="info-value"><?php echo htmlspecialchars($supplier['username']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">Statut du compte</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $supplier['status']; ?>">
                            <?php 
                            $status_labels = [
                                'approved' => '✅ Approuvé',
                                'pending' => '⏳ En attente',
                                'rejected' => '❌ Rejeté'
                            ];
                            echo $status_labels[$supplier['status']] ?? $supplier['status'];
                            ?>
                        </span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Note fournisseur</div>
                    <div class="info-value">
                        <div class="rating-display">
                            <?php 
                            $rating = $supplier['rating'];
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $rating ? '<span class="star">⭐</span>' : '<span style="opacity: 0.3;">⭐</span>';
                            }
                            ?>
                            <span style="margin-left: 0.5rem;"><?php echo $rating; ?>/5</span>
                        </div>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Membre depuis</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($supplier['created_at'])); ?></div>
                </div>

                <?php if ($last_order): ?>
                <div class="info-item">
                    <div class="info-label">Dernière commande</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($last_order['order_date'])); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Modifier le profil -->
            <div class="content-section">
                <h2 class="section-title">✏️ Modifier mes informations</h2>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_info">

                    <div class="form-group">
                        <label for="name">Nom de l'entreprise *</label>
                        <input type="text" id="name" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($supplier['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_name">Nom du contact</label>
                        <input type="text" id="contact_name" name="contact_name" class="form-control" 
                               value="<?php echo htmlspecialchars($supplier['contact_name']); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier['phone']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Adresse</label>
                        <input type="text" id="address" name="address" class="form-control" 
                               value="<?php echo htmlspecialchars($supplier['address']); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">Ville</label>
                            <input type="text" id="city" name="city" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier['city']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="postal_code">Code postal</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier['postal_code']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="country">Pays</label>
                            <input type="text" id="country" name="country" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier['country']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="siret">SIRET</label>
                            <input type="text" id="siret" name="siret" class="form-control" 
                                   value="<?php echo htmlspecialchars($supplier['siret']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes / Informations complémentaires</label>
                        <textarea id="notes" name="notes" class="form-control"><?php echo htmlspecialchars($supplier['notes']); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        💾 Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>

        <!-- Changer le mot de passe -->
        <div class="content-section full-width">
            <h2 class="section-title">🔒 Changer mon mot de passe</h2>
            
            <form method="POST" style="max-width: 600px;">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label for="current_password">Mot de passe actuel *</label>
                    <input type="password" id="current_password" name="current_password" 
                           class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe *</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="form-control" minlength="6" required>
                        <small style="color: var(--secondary); display: block; margin-top: 0.5rem;">
                            Minimum 6 caractères
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe *</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="form-control" minlength="6" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    🔐 Modifier le mot de passe
                </button>
            </form>
        </div>
    </div>
</body>
</html>