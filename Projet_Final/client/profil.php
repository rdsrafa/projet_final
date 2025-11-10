<?php
// client/profil.php - Gestion du profil client
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: ../login.php');
    exit;
}

require_once '../include/db.php';

$client_id = $_SESSION['client_id'];
$db = getDB();
$success = '';
$error = '';

// Traitement des mises à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch($action) {
            case 'update_info':
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $phone = trim($_POST['phone']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $postal_code = trim($_POST['postal_code']);
                
                $stmt = $db->prepare("
                    UPDATE clients 
                    SET first_name = ?, last_name = ?, phone = ?, 
                        address = ?, city = ?, postal_code = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $first_name, $last_name, $phone,
                    $address, $city, $postal_code, $client_id
                ]);
                
                $success = "✅ Informations mises à jour avec succès !";
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Vérifier le mot de passe actuel
                $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password_hash'])) {
                    throw new Exception("Mot de passe actuel incorrect");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("Les mots de passe ne correspondent pas");
                }
                
                if (strlen($new_password) < 6) {
                    throw new Exception("Le mot de passe doit contenir au moins 6 caractères");
                }
                
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_hash, $_SESSION['user_id']]);
                
                $success = "✅ Mot de passe modifié avec succès !";
                break;
        }
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}

// Récupérer les infos du client
$stmt = $db->prepare("SELECT c.*, u.email, u.username FROM clients c JOIN users u ON c.user_id = u.id WHERE c.id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Statistiques
$stats = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total), 0) as total_spent
    FROM sales
    WHERE client_id = ?
");
$stats->execute([$client_id]);
$stats = $stats->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Adoo Sneakers</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profil-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .nav-bar {
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
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            transform: translateY(-2px);
        }

        .profile-header {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            font-weight: 700;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 2rem;
            color: var(--light);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .profile-type {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .type-vip {
            background: linear-gradient(45deg, #FFE66D, #FF6B6B);
            color: white;
        }

        .type-regular {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .profile-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 968px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card-section {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
        }

        .section-title {
            font-size: 1.3rem;
            color: var(--light);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--glass-border);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: var(--secondary);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--light);
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }

        .form-control:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .alert-message {
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

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .loyalty-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
        }

        .loyalty-points {
            font-size: 3rem;
            font-weight: 800;
            margin: 1rem 0;
        }

        .points-label {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .points-info {
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>

    <div class="profil-container">
        <!-- Navigation -->
        <div class="nav-bar">
            <a href="dashboard.php" class="nav-btn">🏠 Tableau de bord</a>
            <a href="catalogue.php" class="nav-btn">👟 Catalogue</a>
            <a href="commandes.php" class="nav-btn">📦 Mes Commandes</a>
            <a href="profil.php" class="nav-btn active">👤 Mon Profil</a>
            <a href="../logout.php" class="nav-btn" style="margin-left: auto;">🚪 Déconnexion</a>
        </div>

        <?php if ($success): ?>
        <div class="alert-message alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-message alert-error">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- En-tête Profil -->
        <div class="profile-header">
            <div class="avatar-large">
                <?php echo strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <div class="profile-name">
                    <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                </div>
                <span class="profile-type <?php echo $client['type'] === 'VIP' ? 'type-vip' : 'type-regular'; ?>">
                    <?php echo $client['type'] === 'VIP' ? '⭐ Membre VIP' : '👤 Membre Regular'; ?>
                </span>
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                        <div class="stat-label">Commandes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($stats['total_spent'], 0); ?>€</div>
                        <div class="stat-label">Total Dépensé</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($client['loyalty_points']); ?></div>
                        <div class="stat-label">Points Fidélité</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carte Fidélité -->
        <div class="loyalty-card">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div>
                    <div class="points-label">💎 Points de Fidélité</div>
                    <div class="loyalty-points"><?php echo number_format($client['loyalty_points']); ?></div>
                    <div class="points-info">
                        ℹ️ 1 point = 1€ dépensé • 100 points = 10€ de réduction
                    </div>
                </div>
                <div style="font-size: 4rem; opacity: 0.3;">🎁</div>
            </div>
        </div>

        <!-- Grille de contenu -->
        <div class="content-grid">
            <!-- Informations Personnelles -->
            <div class="card-section">
                <h2 class="section-title">👤 Informations Personnelles</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_info">
                    
                    <div class="form-group">
                        <label>📧 Email (non modifiable)</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($client['email']); ?>" disabled>
                    </div>

                    <div class="form-group">
                        <label>👤 Nom d'utilisateur (non modifiable)</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($client['username']); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($client['first_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($client['last_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>📞 Téléphone</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>" placeholder="06 12 34 56 78">
                    </div>
                    
                    <div class="form-group">
                        <label>📍 Adresse</label>
                        <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($client['address'] ?? ''); ?>" placeholder="123 rue de la Paix">
                    </div>
                    
                    <div class="form-group">
                        <label>🏙️ Ville</label>
                        <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($client['city'] ?? ''); ?>" placeholder="Paris">
                    </div>
                    
                    <div class="form-group">
                        <label>📮 Code Postal</label>
                        <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($client['postal_code'] ?? ''); ?>" placeholder="75001">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        ✅ Enregistrer les modifications
                    </button>
                </form>
            </div>

            <!-- Sécurité -->
            <div class="card-section">
                <h2 class="section-title">🔒 Sécurité</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label>Mot de passe actuel *</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nouveau mot de passe *</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                        <small style="color: var(--secondary); font-size: 0.85rem;">Minimum 6 caractères</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirmer le nouveau mot de passe *</label>
                        <input type="password" class="form-control" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        🔑 Modifier le mot de passe
                    </button>
                </form>

                <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(255, 230, 109, 0.1); border: 1px solid #FFE66D; border-radius: 12px;">
                    <h3 style="color: #FFE66D; margin-bottom: 1rem;">💡 Conseils de sécurité</h3>
                    <ul style="color: var(--light); line-height: 1.8; margin: 0; padding-left: 1.5rem;">
                        <li>Utilisez un mot de passe unique</li>
                        <li>Mélangez majuscules, minuscules et chiffres</li>
                        <li>Ne partagez jamais votre mot de passe</li>
                        <li>Changez-le régulièrement</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Compte créé le -->
        <div style="text-align: center; margin-top: 2rem; padding: 1.5rem; background: var(--glass); border: 1px solid var(--glass-border); border-radius: 15px;">
            <p style="color: var(--secondary);">
                📅 Membre depuis le <?php echo date('d/m/Y', strtotime($client['created_at'])); ?>
            </p>
        </div>
    </div>
</body>
</html>