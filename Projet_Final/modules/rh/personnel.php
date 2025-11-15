<?php
/**
 * modules/rh/personnel.php
 * Module de gestion du personnel avec création automatique de compte
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
            case 'add_employee':
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $position = trim($_POST['position']);
                $department = trim($_POST['department']);
                $contract_type = $_POST['contract_type'];
                $salary = $_POST['salary'];
                $hire_date = $_POST['hire_date'];
                $birth_date = $_POST['birth_date'] ?? null;
                $address = trim($_POST['address'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $postal_code = trim($_POST['postal_code'] ?? '');
                
                // 🆕 Récupérer les infos de connexion
                $create_account = isset($_POST['create_account']) && $_POST['create_account'] == '1';
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'employee';
                
                // Vérifier si l'email existe déjà
                $stmt = $db->prepare("SELECT id FROM employees WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception("Un employé avec cet email existe déjà");
                }
                
                // Si création de compte demandée, vérifier les champs
                if ($create_account) {
                    if (empty($username)) {
                        throw new Exception("Le nom d'utilisateur est obligatoire pour créer un compte");
                    }
                    if (empty($password)) {
                        throw new Exception("Le mot de passe est obligatoire pour créer un compte");
                    }
                    if (strlen($password) < 6) {
                        throw new Exception("Le mot de passe doit contenir au moins 6 caractères");
                    }
                    
                    // Vérifier si username existe
                    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        throw new Exception("Ce nom d'utilisateur est déjà utilisé");
                    }
                    
                    // Vérifier si email existe dans users
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        throw new Exception("Cet email est déjà utilisé dans les comptes utilisateurs");
                    }
                }
                
                // Insérer l'employé
                $stmt = $db->prepare("
                    INSERT INTO employees 
                    (first_name, last_name, email, phone, position, department, 
                     contract_type, salary, hire_date, birth_date, address, city, postal_code, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $first_name, $last_name, $email, $phone, $position, $department,
                    $contract_type, $salary, $hire_date, $birth_date, $address, $city, $postal_code
                ]);
                
                $employee_id = $db->lastInsertId();
                
                // 🆕 Créer le compte utilisateur si demandé
                if ($create_account) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->prepare("
                        INSERT INTO users (employee_id, username, email, password_hash, role, active)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$employee_id, $username, $email, $password_hash, $role]);
                    
                    $success = "✅ Employé et compte utilisateur créés avec succès !<br>📧 Email: <strong>$email</strong><br>👤 Username: <strong>$username</strong><br>🎭 Rôle: <strong>" . strtoupper($role) . "</strong>";
                } else {
                    $success = "✅ Employé ajouté avec succès ! Vous pouvez créer son compte plus tard depuis la section 'Gestion du Personnel'.";
                }
                
                $db->commit();
                break;
                
            case 'update_employee':
                $employee_id = $_POST['employee_id'];
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $position = trim($_POST['position']);
                $department = trim($_POST['department']);
                $contract_type = $_POST['contract_type'];
                $salary = $_POST['salary'];
                $address = trim($_POST['address'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $postal_code = trim($_POST['postal_code'] ?? '');
                
                $stmt = $db->prepare("
                    UPDATE employees 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                        position = ?, department = ?, contract_type = ?, salary = ?,
                        address = ?, city = ?, postal_code = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $first_name, $last_name, $email, $phone, $position, $department,
                    $contract_type, $salary, $address, $city, $postal_code, $employee_id
                ]);
                
                $success = "✅ Employé modifié avec succès !";
                $db->commit();
                break;
                
            case 'deactivate_employee':
                $employee_id = $_POST['employee_id'];
                $stmt = $db->prepare("UPDATE employees SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$employee_id]);
                
                // Désactiver aussi le compte utilisateur
                $stmt = $db->prepare("UPDATE users SET active = 0 WHERE employee_id = ?");
                $stmt->execute([$employee_id]);
                
                $success = "🚫 Employé et compte utilisateur désactivés.";
                $db->commit();
                break;
                
            case 'reactivate_employee':
                $employee_id = $_POST['employee_id'];
                $stmt = $db->prepare("UPDATE employees SET status = 'active' WHERE id = ?");
                $stmt->execute([$employee_id]);
                
                // Réactiver aussi le compte utilisateur
                $stmt = $db->prepare("UPDATE users SET active = 1 WHERE employee_id = ?");
                $stmt->execute([$employee_id]);
                
                $success = "✅ Employé et compte utilisateur réactivés.";
                $db->commit();
                break;
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les données
try {
    // Statistiques
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_employees,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
            COUNT(CASE WHEN contract_type = 'CDI' THEN 1 END) as cdi_count,
            COUNT(CASE WHEN contract_type = 'CDD' THEN 1 END) as cdd_count,
            COALESCE(SUM(salary), 0) as total_payroll,
            COALESCE(AVG(salary), 0) as avg_salary
        FROM employees
    ")->fetch();
    
    // Liste des employés avec info compte
    $employees = $db->query("
        SELECT 
            e.*,
            TIMESTAMPDIFF(YEAR, e.hire_date, CURDATE()) as seniority_years,
            TIMESTAMPDIFF(YEAR, e.birth_date, CURDATE()) as age,
            u.id as user_id,
            u.username,
            u.role,
            u.active as user_active
        FROM employees e
        LEFT JOIN users u ON e.id = u.employee_id
        ORDER BY e.status DESC, e.hire_date DESC
    ")->fetchAll();
    
    // Départements distincts
    $departments = $db->query("
        SELECT DISTINCT department 
        FROM employees 
        WHERE department IS NOT NULL 
        ORDER BY department
    ")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
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
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
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

    .filters-bar {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .filters-row {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-input {
        flex: 1;
        min-width: 250px;
        padding: 0.8rem;
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        color: var(--light);
    }

    .filter-select {
        padding: 0.8rem;
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        color: var(--light);
        min-width: 150px;
    }

    .employee-card {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .employee-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .employee-card.inactive {
        opacity: 0.6;
    }

    .employee-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .employee-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        font-weight: 700;
        margin-right: 1rem;
    }

    .employee-main {
        display: flex;
        align-items: center;
        flex: 1;
    }

    .employee-name {
        font-size: 1.3rem;
        color: var(--light);
        font-weight: 700;
        margin-bottom: 0.3rem;
    }

    .employee-position {
        color: var(--secondary);
        font-weight: 600;
    }

    .employee-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .info-label {
        font-size: 0.8rem;
        color: var(--secondary);
        opacity: 0.8;
    }

    .info-value {
        color: var(--light);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .employee-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }

    .btn-action {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .btn-edit {
        background: linear-gradient(45deg, #4ECDC4, #44A08D);
        color: white;
    }

    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
    }

    .btn-deactivate {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
        border: 1px solid var(--primary);
    }

    .btn-reactivate {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
        border: 1px solid var(--secondary);
    }

    .badge-status {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .badge-active {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
    }

    .badge-inactive {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
    }

    .badge-contract {
        padding: 0.3rem 0.6rem;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }

    .badge-cdi {
        background: rgba(168, 230, 207, 0.2);
        color: #A8E6CF;
    }

    .badge-cdd {
        background: rgba(255, 230, 109, 0.2);
        color: #FFE66D;
    }

    .badge-vacation {
        background: rgba(108, 92, 231, 0.2);
        color: #6C5CE7;
    }

    /* 🆕 Badge compte utilisateur */
    .badge-account {
        padding: 0.3rem 0.6rem;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }

    .badge-has-account {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
    }

    .badge-no-account {
        background: rgba(255, 230, 109, 0.2);
        color: #FFE66D;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        overflow-y: auto;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2rem;
        max-width: 700px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        margin: 2rem;
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
        font-size: 1rem;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--secondary);
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
        color: var(--light);
    }

    .alert-error {
        background: rgba(255, 107, 107, 0.2);
        border: 1px solid var(--primary);
        color: var(--primary);
    }

    /* 🆕 Section compte utilisateur dans le formulaire */
    .account-section {
        background: rgba(78, 205, 196, 0.05);
        border: 2px dashed var(--secondary);
        border-radius: 12px;
        padding: 1.5rem;
        margin: 1.5rem 0;
    }

    .checkbox-wrapper {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        margin-bottom: 1rem;
    }

    .checkbox-wrapper input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .checkbox-wrapper label {
        margin: 0 !important;
        font-weight: 700 !important;
        color: var(--light) !important;
        cursor: pointer;
    }

    #accountFields {
        display: none;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--glass-border);
    }

    #accountFields.active {
        display: block;
    }

    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--secondary);
        opacity: 0.7;
    }
</style>

<div class="personnel-section">
    
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

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.5rem; color: var(--light); margin-bottom: 0.5rem;">👥 Gestion du Personnel</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Équipe Adoo Sneakers</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary" onclick="window.location.href='?module=rh&action=conges'">
                    🏖️ Gérer les Congés
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='?module=rh&action=paie'">
                    💰 Consulter fiche de paie
            </button>
            <button class="btn btn-primary" onclick="showAddEmployeeModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                Nouvel Employé
            </button>
        <!-- Dans votre section de boutons RH -->
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['total_employees']; ?></div>
            <div class="stat-label">👥 Total Employés</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['active_count']; ?></div>
            <div class="stat-label">✅ Actifs</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['cdi_count']; ?></div>
            <div class="stat-label">📄 CDI</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($stats['total_payroll'], 0); ?>€</div>
            <div class="stat-label">💰 Masse Salariale</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($stats['avg_salary'], 0); ?>€</div>
            <div class="stat-label">📊 Salaire Moyen</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters-bar">
        <div class="filters-row">
            <input type="text" class="filter-input" id="searchInput" placeholder="🔍 Rechercher un employé..." onkeyup="filterEmployees()">
            
            <select class="filter-select" id="departmentFilter" onchange="filterEmployees()">
                <option value="">Tous les départements</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept); ?>">
                        <?php echo htmlspecialchars($dept); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select class="filter-select" id="contractFilter" onchange="filterEmployees()">
                <option value="">Tous les contrats</option>
                <option value="CDI">CDI</option>
                <option value="CDD">CDD</option>
                <option value="Vacataire">Vacataire</option>
                <option value="Stage">Stage</option>
            </select>
            
            <select class="filter-select" id="statusFilter" onchange="filterEmployees()">
                <option value="">Tous les statuts</option>
                <option value="active">Actifs</option>
                <option value="inactive">Inactifs</option>
            </select>
        </div>
    </div>

    <!-- Liste des employés -->
    <?php if (empty($employees)): ?>
        <div class="empty-state">
            <h3>👥 Aucun employé</h3>
            <p>Ajoutez votre premier employé !</p>
        </div>
    <?php else: ?>
        <?php foreach ($employees as $emp): ?>
            <div class="employee-card <?php echo $emp['status'] === 'inactive' ? 'inactive' : ''; ?>" 
                 data-search="<?php echo strtolower($emp['first_name'] . ' ' . $emp['last_name'] . ' ' . $emp['email'] . ' ' . $emp['position']); ?>"
                 data-department="<?php echo htmlspecialchars($emp['department'] ?? ''); ?>"
                 data-contract="<?php echo $emp['contract_type']; ?>"
                 data-status="<?php echo $emp['status']; ?>">
                
                <div class="employee-header">
                    <div class="employee-main">
                        <div class="employee-avatar">
                            <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <div class="employee-name">
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                <span class="badge-contract badge-<?php echo strtolower($emp['contract_type']); ?>">
                                    <?php echo $emp['contract_type']; ?>
                                </span>
                                <?php if ($emp['user_id']): ?>
                                    <span class="badge-account badge-has-account" title="Compte utilisateur actif">
                                        🔑 <?php echo strtoupper($emp['role']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge-account badge-no-account" title="Pas de compte utilisateur">
                                        ⚠️ Sans compte
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="employee-position">
                                <?php echo htmlspecialchars($emp['position']); ?>
                                <?php if ($emp['department']): ?>
                                    • <?php echo htmlspecialchars($emp['department']); ?>
                                <?php endif; ?>
                                <?php if ($emp['user_id']): ?>
                                    <br><small style="color: var(--secondary);">👤 Username: <?php echo htmlspecialchars($emp['username']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <span class="badge-status badge-<?php echo $emp['status']; ?>">
                        <?php echo $emp['status'] === 'active' ? '✅ Actif' : '🚫 Inactif'; ?>
                    </span>
                </div>

                <div class="employee-info-grid">
                    <div class="info-item">
                        <span class="info-label">📧 Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($emp['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📞 Téléphone</span>
                        <span class="info-value"><?php echo htmlspecialchars($emp['phone'] ?? 'Non renseigné'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">💰 Salaire</span>
                        <span class="info-value"><?php echo number_format($emp['salary'], 0); ?>€</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">📅 Date d'entrée</span>
                        <span class="info-value"><?php echo date('d/m/Y', strtotime($emp['hire_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">⏱️ Ancienneté</span>
                        <span class="info-value"><?php echo $emp['seniority_years']; ?> an(s)</span>
                    </div>
                    <?php if ($emp['birth_date']): ?>
                    <div class="info-item">
                        <span class="info-label">🎂 Âge</span>
                        <span class="info-value"><?php echo $emp['age']; ?> ans</span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($emp['address']): ?>
                <div style="padding: 0.8rem; background: rgba(255, 255, 255, 0.03); border-radius: 8px; margin-top: 1rem;">
                    <span style="color: var(--secondary); font-size: 0.85rem;">📍 Adresse:</span>
                    <p style="color: var(--light); margin: 0.3rem 0 0 0;">
                        <?php echo htmlspecialchars($emp['address']); ?>
                        <?php if ($emp['city']): ?>
                            , <?php echo htmlspecialchars($emp['postal_code'] . ' ' . $emp['city']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="employee-actions">
                    <button class="btn-action btn-edit" onclick="showEditEmployeeModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                        ✏️ Modifier
                    </button>
                    
                    <?php if (!$emp['user_id']): ?>
                    <a href="index.php?module=rh&action=employe" class="btn-action" style="background: rgba(108, 92, 231, 0.2); color: #6C5CE7; text-decoration: none;">
                        🔑 Créer Compte
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($emp['status'] === 'active'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="deactivate_employee">
                        <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                        <button type="submit" class="btn-action btn-deactivate" onclick="return confirm('Désactiver cet employé ?')">
                            🚫 Désactiver
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="reactivate_employee">
                        <input type="hidden" name="employee_id" value="<?php echo $emp['id']; ?>">
                        <button type="submit" class="btn-action btn-reactivate">
                            ♻️ Réactiver
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Ajouter Employé -->
<div id="addEmployeeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--light);">👤 Nouvel Employé</h3>
            <span class="close-modal" onclick="closeModal('addEmployeeModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_employee">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" class="form-control" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" class="form-control" name="last_name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" class="form-control" name="email" id="employee_email" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" class="form-control" name="phone" placeholder="06 12 34 56 78">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Poste *</label>
                    <input type="text" class="form-control" name="position" placeholder="Ex: Vendeur Senior" required>
                </div>
                <div class="form-group">
                    <label>Département</label>
                    <input type="text" class="form-control" name="department" placeholder="Ex: Ventes">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Type de contrat *</label>
                    <select class="form-control" name="contract_type" required>
                        <option value="CDI">CDI</option>
                        <option value="CDD">CDD</option>
                        <option value="Vacataire">Vacataire</option>
                        <option value="Stage">Stage</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salaire brut mensuel (€) *</label>
                    <input type="number" class="form-control" name="salary" step="0.01" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date d'entrée *</label>
                    <input type="date" class="form-control" name="hire_date" required>
                </div>
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" class="form-control" name="birth_date">
                </div>
            </div>

            <div class="form-group">
                <label>Adresse</label>
                <input type="text" class="form-control" name="address" placeholder="123 rue de la Paix">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Ville</label>
                    <input type="text" class="form-control" name="city" placeholder="Paris">
                </div>
                <div class="form-group">
                    <label>Code Postal</label>
                    <input type="text" class="form-control" name="postal_code" placeholder="75001">
                </div>
            </div>

            <!-- 🆕 SECTION COMPTE UTILISATEUR (OBLIGATOIRE) -->
            <div class="account-section">
                <h4 style="color: var(--light); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    🔑 Identifiants de Connexion
                    <span style="font-size: 0.8rem; color: var(--secondary); font-weight: normal;">(obligatoire)</span>
                </h4>
                
                <p style="color: var(--secondary); font-size: 0.9rem; margin-bottom: 1rem;">
                    L'employé utilisera ces identifiants pour se connecter à son interface.
                </p>
                
                <div class="form-group">
                    <label>👤 Nom d'utilisateur *</label>
                    <input type="text" class="form-control" name="username" id="username_input" 
                           placeholder="Ex: jdupont" pattern="[a-zA-Z0-9_.]{3,}" required
                           title="Au moins 3 caractères (lettres, chiffres, underscore, point)">
                    <small style="color: var(--secondary); font-size: 0.85rem;">
                        ✨ Conseil: prénom.nom (ex: jean.dupont)
                    </small>
                </div>
                
                <div class="form-group">
                    <label>🔒 Mot de passe *</label>
                    <input type="password" class="form-control" name="password" id="password_input" 
                           minlength="6" placeholder="Min 6 caractères" required>
                    <small style="color: var(--secondary); font-size: 0.85rem;">
                        Minimum 6 caractères - L'employé pourra le changer plus tard
                    </small>
                </div>
                
                <div class="form-group">
                    <label>🎭 Rôle dans le système *</label>
                    <select class="form-control" name="role" id="role_input" required>
                        <option value="employee">👤 Employee (accès standard - ventes, stock)</option>
                        <option value="manager">👔 Manager (gestion équipe)</option>
                        <option value="admin">👑 Administrateur (accès complet)</option>
                    </select>
                    <small style="color: var(--secondary); font-size: 0.85rem;">
                        ⚠️ Choisissez selon les responsabilités de l'employé
                    </small>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addEmployeeModal')">Annuler</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">✅ Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modifier Employé -->
<div id="editEmployeeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--light);">✏️ Modifier Employé</h3>
            <span class="close-modal" onclick="closeModal('editEmployeeModal')">&times;</span>
        </div>
        <form method="POST" id="editEmployeeForm">
            <input type="hidden" name="action" value="update_employee">
            <input type="hidden" name="employee_id" id="edit_employee_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                </div>
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" class="form-control" name="email" id="edit_email" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" class="form-control" name="phone" id="edit_phone">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Poste *</label>
                    <input type="text" class="form-control" name="position" id="edit_position" required>
                </div>
                <div class="form-group">
                    <label>Département</label>
                    <input type="text" class="form-control" name="department" id="edit_department">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Type de contrat *</label>
                    <select class="form-control" name="contract_type" id="edit_contract_type" required>
                        <option value="CDI">CDI</option>
                        <option value="CDD">CDD</option>
                        <option value="Vacataire">Vacataire</option>
                        <option value="Stage">Stage</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Salaire brut mensuel (€) *</label>
                    <input type="number" class="form-control" name="salary" id="edit_salary" step="0.01" required>
                </div>
            </div>

            <div class="form-group">
                <label>Adresse</label>
                <input type="text" class="form-control" name="address" id="edit_address">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Ville</label>
                    <input type="text" class="form-control" name="city" id="edit_city">
                </div>
                <div class="form-group">
                    <label>Code Postal</label>
                    <input type="text" class="form-control" name="postal_code" id="edit_postal_code">
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editEmployeeModal')">Annuler</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">✅ Modifier</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddEmployeeModal() {
    document.getElementById('addEmployeeModal').classList.add('active');
}

function showEditEmployeeModal(employee) {
    document.getElementById('edit_employee_id').value = employee.id;
    document.getElementById('edit_first_name').value = employee.first_name;
    document.getElementById('edit_last_name').value = employee.last_name;
    document.getElementById('edit_email').value = employee.email;
    document.getElementById('edit_phone').value = employee.phone || '';
    document.getElementById('edit_position').value = employee.position;
    document.getElementById('edit_department').value = employee.department || '';
    document.getElementById('edit_contract_type').value = employee.contract_type;
    document.getElementById('edit_salary').value = employee.salary;
    document.getElementById('edit_address').value = employee.address || '';
    document.getElementById('edit_city').value = employee.city || '';
    document.getElementById('edit_postal_code').value = employee.postal_code || '';
    
    document.getElementById('editEmployeeModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// 🆕 Afficher/masquer les champs de compte
function toggleAccountFields() {
    const checkbox = document.getElementById('create_account_checkbox');
    const fields = document.getElementById('accountFields');
    const usernameInput = document.getElementById('username_input');
    const passwordInput = document.getElementById('password_input');
    const roleInput = document.getElementById('role_input');
    
    if (checkbox.checked) {
        fields.classList.add('active');
        usernameInput.required = true;
        passwordInput.required = true;
        roleInput.required = true;
        
        // Auto-remplir le username basé sur l'email
        const email = document.getElementById('employee_email').value;
        if (email && !usernameInput.value) {
            usernameInput.value = email.split('@')[0].toLowerCase().replace(/[^a-z0-9]/g, '');
        }
    } else {
        fields.classList.remove('active');
        usernameInput.required = false;
        passwordInput.required = false;
        roleInput.required = false;
    }
}

// Auto-générer username basé sur l'email
document.getElementById('employee_email').addEventListener('input', function() {
    const checkbox = document.getElementById('create_account_checkbox');
    const usernameInput = document.getElementById('username_input');
    
    if (checkbox.checked && this.value) {
        const emailPart = this.value.split('@')[0].toLowerCase();
        usernameInput.value = emailPart.replace(/[^a-z0-9]/g, '');
    }
});

function filterEmployees() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const department = document.getElementById('departmentFilter').value;
    const contract = document.getElementById('contractFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    const cards = document.querySelectorAll('.employee-card');
    
    cards.forEach(card => {
        const searchText = card.dataset.search;
        const cardDept = card.dataset.department;
        const cardContract = card.dataset.contract;
        const cardStatus = card.dataset.status;
        
        const matchSearch = searchText.includes(searchTerm);
        const matchDept = !department || cardDept === department;
        const matchContract = !contract || cardContract === contract;
        const matchStatus = !status || cardStatus === status;
        
        if (matchSearch && matchDept && matchContract && matchStatus) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Fermer modals en cliquant à l'extérieur
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Auto-fermeture des messages après 5 secondes
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-message');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
</script>