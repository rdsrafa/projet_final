<?php
/**
 * modules/rh/paie.php
 * Module de gestion de la paie (COMPLET et CORRIGÉ)
 */

// ⚠️ NE PAS FAIRE require_once : db.php est déjà chargé dans index.php
// La fonction getDB() est déjà disponible

// Vérification session admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';
$db = getDB();

// Fonction pour obtenir le nom du mois
if (!function_exists('getMonthName')) {
    function getMonthName($month) {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        return $months[(int)$month] ?? '';
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'generate_payslips':
                $month = (int)$_POST['month'];
                $year = (int)$_POST['year'];
                
                $stmt = $db->query("SELECT * FROM employees WHERE status = 'active'");
                $employees = $stmt->fetchAll();
                
                $generated_count = 0;
                
                foreach ($employees as $emp) {
                    $stmt = $db->prepare("
                        SELECT id FROM payslips 
                        WHERE employee_id = ? AND month = ? AND year = ?
                    ");
                    $stmt->execute([$emp['id'], $month, $year]);
                    
                    if (!$stmt->fetch()) {
                        $gross_salary = $emp['salary'];
                        $deductions = round($gross_salary * 0.22, 2);
                        $net_salary = round($gross_salary - $deductions, 2);
                        
                        $stmt = $db->prepare("
                            INSERT INTO payslips 
                            (employee_id, month, year, gross_salary, net_salary, deductions, bonuses, hours_worked, overtime_hours, status)
                            VALUES (?, ?, ?, ?, ?, ?, 0, 151.67, 0, 'draft')
                        ");
                        $stmt->execute([
                            $emp['id'], $month, $year, 
                            $gross_salary, $net_salary, $deductions
                        ]);
                        $generated_count++;
                    }
                }
                
                $success = "✅ $generated_count fiche(s) de paie générée(s) pour " . getMonthName($month) . " $year !";
                break;
                
            case 'validate_payslip':
                $payslip_id = (int)$_POST['payslip_id'];
                $stmt = $db->prepare("UPDATE payslips SET status = 'validated' WHERE id = ?");
                $stmt->execute([$payslip_id]);
                $success = "✅ Fiche de paie validée !";
                break;
                
            case 'mark_paid':
                $payslip_id = (int)$_POST['payslip_id'];
                $stmt = $db->prepare("UPDATE payslips SET status = 'paid' WHERE id = ?");
                $stmt->execute([$payslip_id]);
                $success = "💰 Fiche de paie marquée comme payée !";
                break;
                
            case 'delete_payslip':
                $payslip_id = (int)$_POST['payslip_id'];
                $stmt = $db->prepare("DELETE FROM payslips WHERE id = ?");
                $stmt->execute([$payslip_id]);
                $success = "🗑️ Fiche de paie supprimée.";
                break;
                
            case 'add_bonus':
                $payslip_id = (int)$_POST['payslip_id'];
                $bonus = (float)$_POST['bonus'];
                
                $stmt = $db->prepare("SELECT * FROM payslips WHERE id = ?");
                $stmt->execute([$payslip_id]);
                $payslip = $stmt->fetch();
                
                if ($payslip) {
                    $new_bonuses = $payslip['bonuses'] + $bonus;
                    $new_net = $payslip['gross_salary'] - $payslip['deductions'] + $new_bonuses;
                    
                    $stmt = $db->prepare("
                        UPDATE payslips 
                        SET bonuses = ?, net_salary = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([round($new_bonuses, 2), round($new_net, 2), $payslip_id]);
                    
                    $success = "✅ Prime de " . number_format($bonus, 2) . "€ ajoutée avec succès !";
                } else {
                    $error = "Fiche de paie introuvable";
                }
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les données
try {
    $current_month = (int)date('n');
    $current_year = (int)date('Y');
    
    $filter_month = isset($_GET['month']) ? (int)$_GET['month'] : $current_month;
    $filter_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
    
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT employee_id) as total_employees,
            COALESCE(SUM(gross_salary), 0) as total_gross,
            COALESCE(SUM(net_salary), 0) as total_net,
            COALESCE(SUM(deductions), 0) as total_deductions,
            COALESCE(SUM(bonuses), 0) as total_bonuses
        FROM payslips
        WHERE month = ? AND year = ?
    ");
    $stmt->execute([$filter_month, $filter_year]);
    $stats = $stmt->fetch();
    
    $stmt = $db->prepare("
        SELECT 
            p.*,
            e.first_name, e.last_name, e.position, e.department
        FROM payslips p
        JOIN employees e ON p.employee_id = e.id
        WHERE p.month = ? AND p.year = ?
        ORDER BY e.last_name, e.first_name
    ");
    $stmt->execute([$filter_month, $filter_year]);
    $payslips = $stmt->fetchAll();
    
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM payslips
        WHERE month = ? AND year = ?
        GROUP BY status
    ");
    $stmt->execute([$filter_month, $filter_year]);
    $status_stats = $stmt->fetchAll();
    
    $draft_count = 0;
    $validated_count = 0;
    $paid_count = 0;
    
    foreach ($status_stats as $stat) {
        if ($stat['status'] === 'draft') $draft_count = $stat['count'];
        if ($stat['status'] === 'validated') $validated_count = $stat['count'];
        if ($stat['status'] === 'paid') $paid_count = $stat['count'];
    }
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
    $stats = ['total_employees' => 0, 'total_gross' => 0, 'total_net' => 0, 'total_deductions' => 0, 'total_bonuses' => 0];
    $payslips = [];
}
?>

<style>
    .paie-section { padding: 1rem 0; }
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
    .stat-box:hover { transform: translateY(-5px); }
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .stat-label { color: var(--secondary); font-size: 0.9rem; margin-top: 0.5rem; }
    .filters-bar {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .filter-select {
        padding: 0.8rem;
        background: var(--dark);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        color: var(--light);
        min-width: 150px;
    }
    .payslip-card {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    .payslip-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }
    .payslip-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }
    .employee-name {
        font-size: 1.2rem;
        color: var(--light);
        font-weight: 700;
        margin-bottom: 0.3rem;
    }
    .employee-position { color: var(--secondary); font-size: 0.9rem; }
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .status-draft { background: rgba(255, 230, 109, 0.2); color: #FFE66D; }
    .status-validated { background: rgba(78, 205, 196, 0.2); color: var(--secondary); }
    .status-paid { background: rgba(168, 230, 207, 0.2); color: #A8E6CF; }
    .payslip-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
        margin: 1rem 0;
    }
    .detail-item { text-align: center; }
    .detail-label { color: var(--secondary); font-size: 0.85rem; margin-bottom: 0.3rem; }
    .detail-value { color: var(--light); font-weight: 700; font-size: 1.1rem; }
    .detail-value.positive { color: var(--secondary); }
    .detail-value.negative { color: var(--primary); }
    .payslip-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        flex-wrap: wrap;
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
    .btn-validate {
        background: linear-gradient(45deg, #4ECDC4, #44A08D);
        color: white;
    }
    .btn-validate:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
    }
    .btn-paid {
        background: linear-gradient(45deg, #A8E6CF, #4ECDC4);
        color: white;
    }
    .btn-bonus {
        background: linear-gradient(45deg, #FFE66D, #FF6B6B);
        color: white;
    }
    .btn-delete {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
        border: 1px solid var(--primary);
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
    }
    .modal.active { display: flex; }
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
    .form-group { margin-bottom: 1.5rem; }
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
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--secondary);
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
    }
    .empty-state h3 {
        color: var(--light);
        margin-bottom: 1rem;
    }
    .summary-box {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 20px;
        padding: 2rem;
        color: white;
        margin-bottom: 2rem;
    }
    .summary-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
    }
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }
    .summary-item {
        background: rgba(255, 255, 255, 0.2);
        padding: 1rem;
        border-radius: 10px;
        text-align: center;
    }
    .summary-value {
        font-size: 1.8rem;
        font-weight: 800;
        margin: 0.5rem 0;
    }
    .summary-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
</style>

<div class="paie-section">
    
    <?php if ($success): ?>
    <div class="alert-message alert-success">
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-message alert-error">
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.5rem; color: var(--light); margin-bottom: 0.5rem;">💰 Gestion de la Paie</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">
                Période : <?php echo getMonthName($filter_month) . ' ' . $filter_year; ?>
            </p>
        </div>
        <button class="btn btn-primary" onclick="showGenerateModal()">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24">
                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </svg>
            Générer Fiches de Paie
        </button>
    </div>

    <!-- Filtres -->
    <div class="filters-bar">
        <span style="color: var(--light); font-weight: 600;">📅 Filtrer par période :</span>
        <form method="GET" style="display: flex; gap: 1rem; flex: 1;">
            <input type="hidden" name="module" value="rh">
            <input type="hidden" name="action" value="paie">
            <select class="filter-select" name="month" required>
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $filter_month ? 'selected' : ''; ?>>
                        <?php echo getMonthName($m); ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select class="filter-select" name="year" required>
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $filter_year ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filtrer</button>
        </form>
    </div>

    <!-- Résumé du mois -->
    <?php if (!empty($payslips)): ?>
    <div class="summary-box">
        <div class="summary-title">
            📊 Résumé <?php echo getMonthName($filter_month) . ' ' . $filter_year; ?>
        </div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">👥 Employés</div>
                <div class="summary-value"><?php echo $stats['total_employees']; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">💵 Total Brut</div>
                <div class="summary-value"><?php echo number_format($stats['total_gross'], 0); ?>€</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">📉 Charges</div>
                <div class="summary-value"><?php echo number_format($stats['total_deductions'], 0); ?>€</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">🎁 Primes</div>
                <div class="summary-value"><?php echo number_format($stats['total_bonuses'], 0); ?>€</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">💰 Total Net</div>
                <div class="summary-value"><?php echo number_format($stats['total_net'], 0); ?>€</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistiques par statut -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo count($payslips); ?></div>
            <div class="stat-label">📄 Total Fiches</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $draft_count; ?></div>
            <div class="stat-label">📝 Brouillons</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $validated_count; ?></div>
            <div class="stat-label">✅ Validées</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $paid_count; ?></div>
            <div class="stat-label">💰 Payées</div>
        </div>
    </div>

    <!-- Liste des fiches de paie -->
    <?php if (empty($payslips)): ?>
        <div class="empty-state">
            <h3>📄 Aucune fiche de paie</h3>
            <p>Générez les fiches de paie pour <?php echo getMonthName($filter_month) . ' ' . $filter_year; ?></p>
            <button class="btn btn-primary" onclick="showGenerateModal()" style="margin-top: 1rem;">
                📄 Générer les fiches
            </button>
        </div>
    <?php else: ?>
        <?php foreach ($payslips as $payslip): ?>
        <div class="payslip-card">
            <div class="payslip-header">
                <div>
                    <div class="employee-name">
                        <?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?>
                    </div>
                    <div class="employee-position">
                        <?php echo htmlspecialchars($payslip['position']); ?>
                        <?php if ($payslip['department']): ?>
                            • <?php echo htmlspecialchars($payslip['department']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="status-badge status-<?php echo $payslip['status']; ?>">
                    <?php 
                    $status_labels = [
                        'draft' => '📝 Brouillon',
                        'validated' => '✅ Validée',
                        'paid' => '💰 Payée'
                    ];
                    echo $status_labels[$payslip['status']];
                    ?>
                </span>
            </div>

            <div class="payslip-details">
                <div class="detail-item">
                    <div class="detail-label">💵 Salaire Brut</div>
                    <div class="detail-value"><?php echo number_format($payslip['gross_salary'], 2); ?>€</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">📉 Charges (22%)</div>
                    <div class="detail-value negative">-<?php echo number_format($payslip['deductions'], 2); ?>€</div>
                </div>
                <?php if ($payslip['bonuses'] > 0): ?>
                <div class="detail-item">
                    <div class="detail-label">🎁 Primes</div>
                    <div class="detail-value positive">+<?php echo number_format($payslip['bonuses'], 2); ?>€</div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <div class="detail-label">💰 Salaire Net</div>
                    <div class="detail-value" style="font-size: 1.5rem; color: var(--secondary);">
                        <?php echo number_format($payslip['net_salary'], 2); ?>€
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">⏱️ Heures</div>
                    <div class="detail-value"><?php echo number_format($payslip['hours_worked'], 2); ?>h</div>
                </div>
                <?php if ($payslip['overtime_hours'] > 0): ?>
                <div class="detail-item">
                    <div class="detail-label">⚡ Heures Sup.</div>
                    <div class="detail-value positive"><?php echo number_format($payslip['overtime_hours'], 2); ?>h</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="payslip-actions">
                <?php if ($payslip['status'] === 'draft'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="validate_payslip">
                        <input type="hidden" name="payslip_id" value="<?php echo $payslip['id']; ?>">
                        <button type="submit" class="btn-action btn-validate" onclick="return confirm('Valider cette fiche de paie ?')">
                            ✅ Valider
                        </button>
                    </form>
                    <button class="btn-action btn-bonus" onclick="showBonusModal(<?php echo $payslip['id']; ?>, '<?php echo htmlspecialchars($payslip['first_name'] . ' ' . $payslip['last_name']); ?>')">
                        🎁 Ajouter Prime
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_payslip">
                        <input type="hidden" name="payslip_id" value="<?php echo $payslip['id']; ?>">
                        <button type="submit" class="btn-action btn-delete" onclick="return confirm('Supprimer cette fiche ?')">
                            🗑️ Supprimer
                        </button>
                    </form>
                <?php elseif ($payslip['status'] === 'validated'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="payslip_id" value="<?php echo $payslip['id']; ?>">
                        <button type="submit" class="btn-action btn-paid" onclick="return confirm('Marquer comme payée ?')">
                            💰 Marquer Payée
                        </button>
                    </form>
                <?php else: ?>
                    <span style="color: var(--secondary); font-weight: 600;">
                        ✅ Payée
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Générer Fiches -->
<div id="generateModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--light);">📄 Générer Fiches de Paie</h3>
            <span class="close-modal" onclick="closeModal('generateModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="generate_payslips">
            
            <div class="form-group">
                <label>Mois *</label>
                <select class="form-control" name="month" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $current_month ? 'selected' : ''; ?>>
                            <?php echo getMonthName($m); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Année *</label>
                <select class="form-control" name="year" required>
                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                ✅ Générer les fiches
            </button>
        </form>
    </div>
</div>

<!-- Modal Ajouter Prime -->
<div id="bonusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="color: var(--light);">🎁 Ajouter une Prime</h3>
            <span class="close-modal" onclick="closeModal('bonusModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_bonus">
            <input type="hidden" name="payslip_id" id="bonus_payslip_id">
            
            <div class="form-group">
                <label>Employé</label>
                <input type="text" class="form-control" id="bonus_employee_name" readonly>
            </div>
            
            <div class="form-group">
                <label>Montant de la prime (€) *</label>
                <input type="number" step="0.01" class="form-control" name="bonus" required placeholder="500.00">
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%;">
                ✅ Ajouter la prime
            </button>
        </form>
    </div>
</div>

<script>
function showGenerateModal() {
    document.getElementById('generateModal').classList.add('active');
}

function showBonusModal(payslipId, employeeName) {
    document.getElementById('bonus_payslip_id').value = payslipId;
    document.getElementById('bonus_employee_name').value = employeeName;
    document.getElementById('bonusModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Fermer les modals en cliquant en dehors
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}
</script>