<?php
/**
 * modules/personnel/employe.php
 * Gestion des comptes employés (création ID pour connexion)
 */

// Vérification session admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';
$db = getDB();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $db->beginTransaction();
        
        switch($action) {
            case 'create_employee_account':
                // Récupérer les données
                $employee_id = (int)$_POST['employee_id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                
                // Vérifications
                if (strlen($password) < 6) {
                    throw new Exception("Le mot de passe doit contenir au moins 6 caractères");
                }
                
                // Vérifier si l'username existe
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    throw new Exception("Ce nom d'utilisateur est déjà utilisé");
                }
                
                // Vérifier si l'email existe
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception("Cet email est déjà utilisé");
                }
                
                // Vérifier si l'employé a déjà un compte
                $stmt = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
                $stmt->execute([$employee_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Cet employé a déjà un compte utilisateur");
                }
                
                // Hasher le mot de passe
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Créer le compte utilisateur
                $stmt = $db->prepare("
                    INSERT INTO users (employee_id, username, email, password_hash, role, active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$employee_id, $username, $email, $password_hash, $role]);
                
                $db->commit();
                $success = "✅ Compte créé avec succès pour $username !";
                break;
                
            case 'update_account':
                $user_id = (int)$_POST['user_id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                
                // Vérifier username unique (sauf pour cet utilisateur)
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $user_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Ce nom d'utilisateur est déjà utilisé");
                }
                
                // Vérifier email unique
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    throw new Exception("Cet email est déjà utilisé");
                }
                
                // Mettre à jour
                $stmt = $db->prepare("
                    UPDATE users 
                    SET username = ?, email = ?, role = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $role, $user_id]);
                
                $db->commit();
                $success = "✅ Compte modifié avec succès !";
                break;
                
            case 'reset_password':
                $user_id = (int)$_POST['user_id'];
                $new_password = $_POST['new_password'];
                
                if (strlen($new_password) < 6) {
                    throw new Exception("Le mot de passe doit contenir au moins 6 caractères");
                }
                
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);
                
                $db->commit();
                $success = "✅ Mot de passe réinitialisé avec succès !";
                break;
                
            case 'toggle_active':
                $user_id = (int)$_POST['user_id'];
                $active = (int)$_POST['active'];
                
                $stmt = $db->prepare("UPDATE users SET active = ? WHERE id = ?");
                $stmt->execute([$active, $user_id]);
                
                $db->commit();
                $success = $active ? "✅ Compte activé" : "🚫 Compte désactivé";
                break;
        }
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les données
try {
    // Employés sans compte
    $employees_without_account = $db->query("
        SELECT e.* 
        FROM employees e
        LEFT JOIN users u ON e.id = u.employee_id
        WHERE u.id IS NULL AND e.status = 'active'
        ORDER BY e.last_name, e.first_name
    ")->fetchAll();
    
    // Comptes utilisateurs existants
    $users_with_accounts = $db->query("
        SELECT 
            u.*,
            e.first_name,
            e.last_name,
            e.position,
            e.email as employee_email
        FROM users u
        JOIN employees e ON u.employee_id = e.id
        WHERE u.employee_id IS NOT NULL
        ORDER BY e.last_name, e.first_name
    ")->fetchAll();
    
    // Stats
    $stats = $db->query("
        SELECT 
            COUNT(CASE WHEN u.employee_id IS NOT NULL THEN 1 END) as total_accounts,
            COUNT(CASE WHEN u.employee_id IS NOT NULL AND u.active = 1 THEN 1 END) as active_accounts,
            COUNT(CASE WHEN u.employee_id IS NULL AND e.status = 'active' THEN 1 END) as without_account
        FROM employees e
        LEFT JOIN users u ON e.id = u.employee_id
    ")->fetch();
    
} catch (PDOException $e) {
    $error = "Erreur : " . $e->getMessage();
}
?>

<style>
    .personnel-section {
        padding: 1rem 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-box {
        background: var(--glass);
        padding: 1.5rem;
        border-radius: 15px;
        border: 1px solid var(--glass-border);
        text-align: center;
        transition: all 0.3s ease;
    }

    .stat-box:hover {
        transform: translateY(-5px);
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-label {
        color: var(--secondary);
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    .tabs-nav {
        display: flex;
        gap: 1rem;
        border-bottom: 2px solid var(--glass-border);
        margin-bottom: 2rem;
    }

    .tab-btn {
        padding: 1rem 1.5rem;
        background: transparent;
        border: none;
        color: var(--light);
        font-weight: 600;
        cursor: pointer;
        position: relative;
        opacity: 0.6;
        transition: all 0.3s ease;
    }

    .tab-btn.active {
        opacity: 1;
        color: var(--secondary);
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .account-card {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .account-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .account-card.inactive {
        opacity: 0.6;
    }

    .account-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .account-name {
        font-size: 1.2rem;
        color: var(--light);
        font-weight: 700;
        margin-bottom: 0.3rem;
    }

    .account-role {
        color: var(--secondary);
        font-size: 0.9rem;
    }

    .badge {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .badge-admin {
        background: linear-gradient(45deg, #FF6B6B, #EE5A6F);
        color: white;
    }

    .badge-manager {
        background: linear-gradient(45deg, #FFE66D, #FF6B6B);
        color: white;
    }

    .badge-employee {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
    }

    .badge-active {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
    }

    .badge-inactive {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
    }

    .account-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
        margin: 1rem 0;
    }

    .info-item {
        text-align: center;
    }

    .info-label {
        color: var(--secondary);
        font-size: 0.8rem;
        margin-bottom: 0.3rem;
    }

    .info-value {
        color: var(--light);
        font-weight: 600;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 2000;
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
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .close-modal {
        font-size: 2rem;
        color: var(--secondary);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .close-modal:hover {
        color: var(--primary);
        transform: rotate(90deg);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        color: var(--secondary);
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .form-control {
        width: 100%;
        padding: 0.8rem;
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        color: var(--light);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--secondary);
    }

    .employee-select-card {
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .employee-select-card:hover {
        border-color: var(--secondary);
        transform: translateX(5px);
    }

    .employee-select-card.selected {
        background: rgba(78, 205, 196, 0.1);
        border-color: var(--secondary);
    }
</style>

<div class="personnel-section">
    
    <?php if ($success): ?>
    <div style="padding: 1rem; background: rgba(78, 205, 196, 0.2); border: 1px solid var(--secondary); border-radius: 12px; color: var(--secondary); margin-bottom: 1rem;">
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div style="padding: 1rem; background: rgba(255, 107, 107, 0.2); border: 1px solid var(--primary); border-radius: 12px; color: var(--primary); margin-bottom: 1rem;">
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.5rem; color: var(--light);">👥 Gestion du Personnel</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Comptes d'accès des employés</p>
        </div>
        <button class="btn btn-primary" onclick="showCreateModal()">
            ➕ Créer un Compte
        </button>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['total_accounts']; ?></div>
            <div class="stat-label">Comptes Créés</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['active_accounts']; ?></div>
            <div class="stat-label">Comptes Actifs</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['without_account']; ?></div>
            <div class="stat-label">Sans Compte</div>
        </div>
    </div>

    <!-- Onglets -->
    <div class="tabs-nav">
        <button class="tab-btn active" onclick="switchTab('accounts')">
            👤 Comptes Existants (<?php echo count($users_with_accounts); ?>)
        </button>
        <button class="tab-btn" onclick="switchTab('without')">
            ⚠️ Sans Compte (<?php echo count($employees_without_account); ?>)
        </button>
    </div>

    <!-- Tab: Comptes existants -->
    <div id="accounts-tab" class="tab-content active">
        <?php if (empty($users_with_accounts)): ?>
            <p style="text-align: center; padding: 2rem; color: var(--secondary);">Aucun compte créé</p>
        <?php else: ?>
            <?php foreach ($users_with_accounts as $user): ?>
            <div class="account-card <?php echo $user['active'] ? '' : 'inactive'; ?>">
                <div class="account-header">
                    <div>
                        <div class="account-name">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </div>
                        <div class="account-role">
                            <?php echo htmlspecialchars($user['position']); ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <span class="badge <?php 
                            echo $user['role'] === 'admin' ? 'badge-admin' : 
                                ($user['role'] === 'manager' ? 'badge-manager' : 'badge-employee'); 
                        ?>">
                            <?php echo strtoupper($user['role']); ?>
                        </span>
                        <span class="badge <?php echo $user['active'] ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo $user['active'] ? '✅ Actif' : '🚫 Inactif'; ?>
                        </span>
                    </div>
                </div>

                <div class="account-info">
                    <div class="info-item">
                        <div class="info-label">👤 Username</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">📧 Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">🕒 Dernière connexion</div>
                        <div class="info-value">
                            <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?>
                        </div>
                    </div>
                </div>

                <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                    <button class="btn btn-secondary btn-sm" onclick="editAccount(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                        ✏️ Modifier
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                        🔑 Réinitialiser MDP
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="active" value="<?php echo $user['active'] ? 0 : 1; ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <?php echo $user['active'] ? '🚫 Désactiver' : '✅ Activer'; ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Tab: Sans compte -->
    <div id="without-tab" class="tab-content">
        <?php if (empty($employees_without_account)): ?>
            <p style="text-align: center; padding: 2rem; color: var(--secondary);">Tous les employés actifs ont un compte !</p>
        <?php else: ?>
            <div style="background: rgba(255, 230, 109, 0.1); border: 1px solid #FFE66D; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
                <strong style="color: #FFE66D;">⚠️ Attention</strong>
                <p style="color: var(--light); margin: 0.5rem 0 0 0;">Ces employés n'ont pas de compte pour se connecter au système.</p>
            </div>
            
            <?php foreach ($employees_without_account as $emp): ?>
            <div class="account-card" style="border-left: 4px solid #FFE66D;">
                <div class="account-header">
                    <div>
                        <div class="account-name">
                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                        </div>
                        <div class="account-role">
                            <?php echo htmlspecialchars($emp['position']); ?>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="showCreateModalForEmployee(<?php echo htmlspecialchars(
                        json_encode($emp)); ?>)">
                        ➕ Créer Compte
                    </button>
                </div>
                
                <div class="account-info">
                    <div class="info-item">
                        <div class="info-label">📧 Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($emp['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">📱 Téléphone</div>
                        <div class="info-value"><?php echo htmlspecialchars($emp['phone']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">📅 Embauché le</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($emp['hire_date'])); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- Modal: Créer un compte -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--light); margin: 0;">➕ Créer un Compte Employé</h3>
            <span class="close-modal" onclick="closeModal('createModal')">&times;</span>
        </div>
        
        <form method="POST" id="createForm">
            <input type="hidden" name="action" value="create_employee_account">
            <input type="hidden" name="employee_id" id="create_employee_id">
            
            <div class="form-group">
                <label>👤 Sélectionner un Employé *</label>
                <div id="employeeSelectList" style="max-height: 200px; overflow-y: auto;">
                    <?php foreach ($employees_without_account as $emp): ?>
                    <div class="employee-select-card" onclick="selectEmployee(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>', '<?php echo htmlspecialchars($emp['email']); ?>')">
                        <div style="font-weight: 600; color: var(--light);">
                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--secondary);">
                            <?php echo htmlspecialchars($emp['position']); ?> - <?php echo htmlspecialchars($emp['email']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div id="selectedEmployee" style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(78, 205, 196, 0.1); border-radius: 8px; display: none;">
                    <strong style="color: var(--secondary);">Sélectionné:</strong> <span id="selectedEmployeeName"></span>
                </div>
            </div>
            
            <div class="form-group">
                <label>👤 Nom d'utilisateur *</label>
                <input type="text" name="username" id="create_username" class="form-control" required 
                       placeholder="Ex: jdupont" pattern="[a-zA-Z0-9_]{3,}" 
                       title="Au moins 3 caractères (lettres, chiffres, underscore)">
                <small style="color: var(--secondary); font-size: 0.8rem;">
                    Utilisé pour se connecter. Min 3 caractères, lettres/chiffres uniquement.
                </small>
            </div>
            
            <div class="form-group">
                <label>📧 Email *</label>
                <input type="email" name="email" id="create_email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>🔑 Mot de passe *</label>
                <input type="password" name="password" class="form-control" required minlength="6" 
                       placeholder="Min 6 caractères">
                <small style="color: var(--secondary); font-size: 0.8rem;">
                    Minimum 6 caractères
                </small>
            </div>
            
            <div class="form-group">
                <label>🎭 Rôle *</label>
                <select name="role" class="form-control" required>
                    <option value="employee">👤 Employé (accès standard)</option>
                    <option value="manager">👔 Manager (gestion équipe)</option>
                    <option value="admin">👑 Administrateur (accès complet)</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    ✅ Créer le Compte
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')" style="flex: 1;">
                    ❌ Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Modifier un compte -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--light); margin: 0;">✏️ Modifier le Compte</h3>
            <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_account">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div class="form-group">
                <label>👤 Nom d'utilisateur *</label>
                <input type="text" name="username" id="edit_username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>📧 Email *</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>🎭 Rôle *</label>
                <select name="role" id="edit_role" class="form-control" required>
                    <option value="employee">👤 Employé</option>
                    <option value="manager">👔 Manager</option>
                    <option value="admin">👑 Administrateur</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    ✅ Enregistrer
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')" style="flex: 1;">
                    ❌ Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Réinitialiser mot de passe -->
<div id="resetModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--light); margin: 0;">🔑 Réinitialiser le Mot de Passe</h3>
            <span class="close-modal" onclick="closeModal('resetModal')">&times;</span>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset_user_id">
            
            <div style="padding: 1rem; background: rgba(255, 230, 109, 0.1); border: 1px solid #FFE66D; border-radius: 12px; margin-bottom: 1.5rem;">
                <p style="color: var(--light); margin: 0;">
                    Vous allez réinitialiser le mot de passe pour: <strong id="reset_username" style="color: var(--secondary);"></strong>
                </p>
            </div>
            
            <div class="form-group">
                <label>🔑 Nouveau Mot de Passe *</label>
                <input type="password" name="new_password" class="form-control" required minlength="6" 
                       placeholder="Min 6 caractères">
            </div>
            
            <div class="form-group">
                <label>🔑 Confirmer le Mot de Passe *</label>
                <input type="password" id="confirm_password" class="form-control" required minlength="6" 
                       placeholder="Retapez le mot de passe">
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1;" onclick="return validatePasswordReset()">
                    ✅ Réinitialiser
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('resetModal')" style="flex: 1;">
                    ❌ Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Gestion des onglets
function switchTab(tabName) {
    // Désactiver tous les onglets
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    // Activer l'onglet sélectionné
    event.target.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');
}

// Ouvrir/Fermer modals
function showCreateModal() {
    document.getElementById('createModal').classList.add('active');
}

function showCreateModalForEmployee(employee) {
    selectEmployee(employee.id, employee.first_name + ' ' + employee.last_name, employee.email);
    document.getElementById('createModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Fermer modal en cliquant à l'extérieur
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

// Sélectionner un employé
function selectEmployee(id, name, email) {
    // Retirer la sélection précédente
    document.querySelectorAll('.employee-select-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Sélectionner le nouvel employé
    event.target.closest('.employee-select-card').classList.add('selected');
    
    // Remplir les champs
    document.getElementById('create_employee_id').value = id;
    document.getElementById('create_username').value = email.split('@')[0]; // Suggestion basée sur l'email
    document.getElementById('create_email').value = email;
    
    // Afficher la sélection
    document.getElementById('selectedEmployee').style.display = 'block';
    document.getElementById('selectedEmployeeName').textContent = name;
}

// Modifier un compte
function editAccount(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('editModal').classList.add('active');
}

// Réinitialiser mot de passe
function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    document.getElementById('resetModal').classList.add('active');
}

// Valider la réinitialisation du mot de passe
function validatePasswordReset() {
    const password = document.querySelector('#resetModal input[name="new_password"]').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (password !== confirm) {
        alert('❌ Les mots de passe ne correspondent pas !');
        return false;
    }
    
    if (password.length < 6) {
        alert('❌ Le mot de passe doit contenir au moins 6 caractères !');
        return false;
    }
    
    return confirm('⚠️ Confirmer la réinitialisation du mot de passe ?');
}

// Validation du formulaire de création
document.getElementById('createForm').addEventListener('submit', function(e) {
    const employeeId = document.getElementById('create_employee_id').value;
    if (!employeeId) {
        e.preventDefault();
        alert('❌ Veuillez sélectionner un employé !');
        return false;
    }
});

// Auto-fermeture des messages de succès/erreur après 5 secondes
setTimeout(function() {
    const alerts = document.querySelectorAll('[style*="rgba(78, 205, 196, 0.2)"], [style*="rgba(255, 107, 107, 0.2)"]');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
</script>