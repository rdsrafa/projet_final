<?php
// register.php - Inscription Client ou Fournisseur
session_start();

// Si déjà connecté, rediriger
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'include/db.php';

// Récupérer le type d'inscription (client ou supplier)
$type = $_GET['type'] ?? 'client';
if (!in_array($type, ['client', 'supplier'])) {
    $type = 'client';
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Champs spécifiques fournisseur
    $company_name = trim($_POST['company_name'] ?? '');
    $siret = trim($_POST['siret'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide";
    } elseif ($type === 'supplier' && empty($company_name)) {
        $error = "Le nom de l'entreprise est obligatoire pour les fournisseurs";
    } else {
        try {
            $db = getDB();
            
            // Vérifier si l'email existe déjà
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé";
            } else {
                // Hasher le mot de passe
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Démarrer une transaction
                $db->beginTransaction();
                
                try {
                    if ($type === 'client') {
                        // Créer le client
                        $stmt = $db->prepare("
                            INSERT INTO clients (first_name, last_name, email, phone, type, active)
                            VALUES (?, ?, ?, ?, 'Regular', 1)
                        ");
                        $stmt->execute([$first_name, $last_name, $email, $phone]);
                        $client_id = $db->lastInsertId();
                        
                        // Créer l'utilisateur
                        $username = strtolower($first_name . '.' . $last_name);
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password_hash, role, active)
                            VALUES (?, ?, ?, 'client', 1)
                        ");
                        $stmt->execute([$username, $email, $password_hash]);
                        $user_id = $db->lastInsertId();
                        
                        // Lier l'utilisateur au client
                        $stmt = $db->prepare("UPDATE clients SET user_id = ? WHERE id = ?");
                        $stmt->execute([$user_id, $client_id]);
                        
                        $db->commit();
                        
                        header('Location: login.php?registered=1');
                        exit;
                        
                    } else {
                        // Créer le fournisseur
                        $stmt = $db->prepare("
                            INSERT INTO suppliers (name, contact_name, email, phone, address, city, postal_code, siret, status, active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 1)
                        ");
                        $contact_name = $first_name . ' ' . $last_name;
                        $stmt->execute([$company_name, $contact_name, $email, $phone, $address, $city, $postal_code, $siret]);
                        $supplier_id = $db->lastInsertId();
                        
                        // Créer l'utilisateur
                        $username = strtolower(str_replace(' ', '.', $company_name));
                        $stmt = $db->prepare("
                            INSERT INTO users (username, email, password_hash, role, active)
                            VALUES (?, ?, ?, 'supplier', 0)
                        ");
                        $stmt->execute([$username, $email, $password_hash]);
                        $user_id = $db->lastInsertId();
                        
                        // Lier l'utilisateur au fournisseur
                        $stmt = $db->prepare("UPDATE suppliers SET user_id = ? WHERE id = ?");
                        $stmt->execute([$user_id, $supplier_id]);
                        
                        $db->commit();
                        
                        header('Location: login.php?supplier_pending=1');
                        exit;
                    }
                } catch (PDOException $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
            
        } catch (PDOException $e) {
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Adoo Sneakers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .register-box {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-logo h1 {
            font-size: 2.5rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .register-logo p {
            color: var(--secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .type-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            border-radius: 12px;
            border: 2px solid var(--glass-border);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--light);
            font-weight: 600;
        }

        .type-tab.active {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            border-color: var(--secondary);
            color: white;
        }

        .register-error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid var(--primary);
            color: var(--primary);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group label .required {
            color: var(--primary);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            color: var(--light);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.2);
        }

        .btn-register {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 1rem;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .login-link a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            color: var(--primary);
        }

        @media (max-width: 768px) {
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

    <div class="register-container">
        <div class="register-box">
            <div class="register-logo">
                <h1>👟 Adoo Sneakers</h1>
                <p>Créer votre compte</p>
            </div>

            <!-- Onglets Type -->
            <div class="type-tabs">
                <a href="register.php?type=client" class="type-tab <?php echo $type === 'client' ? 'active' : ''; ?>">
                    👤 Client
                </a>
                <a href="register.php?type=supplier" class="type-tab <?php echo $type === 'supplier' ? 'active' : ''; ?>">
                    🏭 Fournisseur
                </a>
            </div>

            <?php if ($error): ?>
            <div class="register-error">
                <strong>⚠️ Erreur</strong><br>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <?php if ($type === 'supplier'): ?>
                    <!-- Formulaire Fournisseur -->
                    <div class="form-group">
                        <label>🏢 Nom de l'entreprise <span class="required">*</span></label>
                        <input type="text" name="company_name" class="form-control" 
                               placeholder="Nike Distribution France" 
                               value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>👤 Prénom du contact <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-control" 
                                   placeholder="Jean" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>👤 Nom du contact <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-control" 
                                   placeholder="Dupont" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>📧 Email professionnel <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="contact@entreprise.com" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>📱 Téléphone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   placeholder="06 12 34 56 78" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>🏠 Adresse</label>
                        <input type="text" name="address" class="form-control" 
                               placeholder="123 Rue de la Paix" 
                               value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>🏙️ Ville</label>
                            <input type="text" name="city" class="form-control" 
                                   placeholder="Paris" 
                                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>📮 Code postal</label>
                            <input type="text" name="postal_code" class="form-control" 
                                   placeholder="75001" 
                                   value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>🏢 SIRET</label>
                        <input type="text" name="siret" class="form-control" 
                               placeholder="123 456 789 00012" 
                               value="<?php echo htmlspecialchars($_POST['siret'] ?? ''); ?>">
                    </div>

                <?php else: ?>
                    <!-- Formulaire Client -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>👤 Prénom <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-control" 
                                   placeholder="Jean" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>👤 Nom <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-control" 
                                   placeholder="Dupont" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>📧 Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="jean.dupont@email.com" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>📱 Téléphone</label>
                        <input type="tel" name="phone" class="form-control" 
                               placeholder="06 12 34 56 78" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                <?php endif; ?>

                <!-- Mot de passe (commun) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>🔒 Mot de passe <span class="required">*</span></label>
                        <input type="password" name="password" class="form-control" 
                               placeholder="Min. 6 caractères" required>
                    </div>
                    <div class="form-group">
                        <label>🔒 Confirmer mot de passe <span class="required">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="Répéter le mot de passe" required>
                    </div>
                </div>

                <button type="submit" class="btn-register">
                    <?php echo $type === 'supplier' ? '🏭 Créer mon compte Fournisseur' : '👤 Créer mon compte Client'; ?>
                </button>
            </form>

            <div class="login-link">
                <p style="color: var(--light); margin-bottom: 0.5rem;">Vous avez déjà un compte ?</p>
                <a href="login.php">Se connecter</a>
            </div>
        </div>
    </div>
</body>
</html>