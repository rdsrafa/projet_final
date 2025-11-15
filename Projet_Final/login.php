<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'];
    if ($role === 'client') {
        header('Location: client/dashboard.php');
    } elseif ($role === 'supplier') {
        header('Location: supplier/dashboard.php');
    } elseif ($role === 'employee') {
        header('Location: employes/dashboard.php');  // ✅ Ajoutez ça
    } else {
        header('Location: index.php');
    }
    exit;
}

require_once 'include/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        try {
            $db = getDB();
            
            $stmt = $db->prepare("
                SELECT u.*, 
                       e.first_name AS emp_first_name, e.last_name AS emp_last_name, e.position,
                       c.first_name AS cli_first_name, c.last_name AS cli_last_name, c.id AS client_id,
                       s.name AS supplier_name, s.id AS supplier_id, s.status AS supplier_status
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                LEFT JOIN clients c ON u.id = c.user_id
                LEFT JOIN suppliers s ON u.id = s.user_id
                WHERE u.email = ? AND u.active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['active'] != 1) {
                    $error = "Votre compte n'est pas activé.";
                }
                elseif ($user['role'] === 'supplier' && $user['supplier_status'] !== 'approved') {
                    $error = "Votre compte fournisseur est en attente d'approbation.";
                } else {
                    // Connexion réussie
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];  // ✅ IMPORTANT !
                    
                    // Client
                    if ($user['role'] === 'client') {
                        $_SESSION['client_id'] = $user['client_id'];
                        $_SESSION['user_name'] = $user['cli_first_name'] . ' ' . $user['cli_last_name'];
                        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        header('Location: client/dashboard.php');
                        exit;
                    } 
                    // Fournisseur
                    elseif ($user['role'] === 'supplier') {
                        $_SESSION['supplier_id'] = $user['supplier_id'];
                        $_SESSION['user_name'] = $user['supplier_name'];
                        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        header('Location: supplier/dashboard.php');
                        exit;
                    }
                    // Employés (employee, manager, admin)
                    else {
                        $_SESSION['user_name'] = $user['emp_first_name'] . ' ' . $user['emp_last_name'];
                        $_SESSION['user_position'] = $user['position'];
                        $_SESSION['employee_id'] = $user['employee_id'];
                        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        
                        // ✅ Si employee simple -> dashboard employé
                        if ($user['role'] === 'employee') {
                            header('Location: employes/dashboard.php');
                        } else {
                            // Manager/Admin -> dashboard principal
                            header('Location: index.php');
                        }
                        exit;
                    }
                }
            } else {
                $error = "Email ou mot de passe incorrect";
            }
            
        } catch (PDOException $e) {
            $error = "Erreur de connexion : " . $e->getMessage();
        }
    }
}
// Récupérer le message de succès si inscription réussie
if (isset($_GET['registered'])) {
    $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
}
if (isset($_GET['supplier_pending'])) {
    $success = "Votre demande de compte fournisseur a été envoyée. Un administrateur va l'examiner prochainement.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Adoo Sneakers</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .login-box {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 30px;
            padding: 3rem;
            max-width: 450px;
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

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo h1 {
            font-size: 2.5rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .login-logo p {
            color: var(--secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .login-error, .login-success {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
            animation: shake 0.5s;
        }

        .login-error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .login-success {
            background: rgba(78, 205, 196, 0.2);
            border: 1px solid var(--secondary);
            color: var(--secondary);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
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

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.7;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
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

        .btn-login {
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
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }

        .remember-me input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .register-link p {
            color: var(--light);
            margin-bottom: 0.5rem;
        }

        .register-link a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            color: var(--primary);
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--light);
            opacity: 0.7;
            font-size: 0.9rem;
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

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <h1>👟 Adoo Sneakers</h1>
                <p>Connexion à votre compte</p>
            </div>

            <?php if ($error): ?>
            <div class="login-error">
                <strong>⚠️ Erreur</strong><br>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="login-success">
                <strong>✓ Succès</strong><br>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="email">📧 Email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✉️</span>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="votre.email@exemple.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">🔒 Mot de passe</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔑</span>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="••••••••"
                            required
                        >
                    </div>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember" style="margin: 0; font-weight: normal; color: var(--light);">
                        Se souvenir de moi
                    </label>
                </div>

                <button type="submit" class="btn-login">
                    Se Connecter
                </button>
            </form>

            <!-- Liens d'inscription -->
            <div class="register-link">
                <p>Vous n'avez pas de compte ?</p>
                <a href="register.php?type=client">S'inscrire en tant que Client</a>
                <span style="color: var(--light); margin: 0 0.5rem;">|</span>
                <a href="register.php?type=supplier">Devenir Fournisseur</a>
            </div>

            <div class="login-footer">
                <p>Adoo Sneakers ERP v1.0</p>
                <p style="font-size: 0.85rem; margin-top: 0.5rem;">
                    © 2025 - Tous droits réservés
                </p>
            </div>
        </div>
    </div>

    <script>
        // Animation de focus
        document.addEventListener('DOMContentLoaded', () => {
            const emailInput = document.getElementById('email');
            
            setTimeout(() => {
                emailInput.style.borderColor = 'var(--secondary)';
                setTimeout(() => {
                    emailInput.style.borderColor = '';
                }, 1000);
            }, 500);

            // Loading sur submit
            const form = document.querySelector('.login-form');
            form.addEventListener('submit', (e) => {
                const submitBtn = form.querySelector('.btn-login');
                submitBtn.innerHTML = '⏳ Connexion...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>