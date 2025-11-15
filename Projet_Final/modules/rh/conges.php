<?php
/**
 * modules/rh/conges_admin.php
 * Gestion des demandes de congés des employés par l'admin
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
        switch($action) {
            case 'approve':
                $leave_id = (int)$_POST['leave_id'];
                $stmt = $db->prepare("
                    UPDATE employee_leaves 
                    SET status = 'approved', 
                        approved_by = (SELECT employee_id FROM users WHERE id = ?),
                        approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $leave_id]);
                $success = "✅ Congé approuvé avec succès !";
                break;
                
            case 'reject':
                $leave_id = (int)$_POST['leave_id'];
                $rejection_reason = $_POST['rejection_reason'] ?? '';
                
                $stmt = $db->prepare("
                    UPDATE employee_leaves 
                    SET status = 'rejected',
                        approved_by = (SELECT employee_id FROM users WHERE id = ?),
                        approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $leave_id]);
                $success = "❌ Congé refusé.";
                break;
                
            case 'delete':
                $leave_id = (int)$_POST['leave_id'];
                $stmt = $db->prepare("DELETE FROM employee_leaves WHERE id = ?");
                $stmt->execute([$leave_id]);
                $success = "🗑️ Demande supprimée.";
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer les données
try {
    // Filtres
    $filter_status = isset($_GET['status']) ? $_GET['status'] : 'pending';
    $filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
    
    // Requête de base
    $sql = "
        SELECT 
            l.*,
            e.first_name, 
            e.last_name, 
            e.position,
            e.department,
            approver.first_name as approver_first_name,
            approver.last_name as approver_last_name
        FROM employee_leaves l
        JOIN employees e ON l.employee_id = e.id
        LEFT JOIN employees approver ON l.approved_by = approver.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Filtres
    if ($filter_status !== 'all') {
        $sql .= " AND l.status = ?";
        $params[] = $filter_status;
    }
    
    if ($filter_type !== 'all') {
        $sql .= " AND l.type = ?";
        $params[] = $filter_type;
    }
    
    $sql .= " ORDER BY 
        CASE l.status 
            WHEN 'pending' THEN 1 
            WHEN 'approved' THEN 2 
            WHEN 'rejected' THEN 3 
        END,
        l.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $leaves = $stmt->fetchAll();
    
    // Statistiques
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'approved' AND YEAR(start_date) = YEAR(CURDATE()) THEN days_count ELSE 0 END) as total_days_approved
        FROM employee_leaves
        WHERE YEAR(start_date) = YEAR(CURDATE())
    ");
    $stats = $stmt->fetch();
    
    // Congés à venir (approuvés)
    $stmt = $db->query("
        SELECT 
            l.*,
            e.first_name, 
            e.last_name, 
            e.position
        FROM employee_leaves l
        JOIN employees e ON l.employee_id = e.id
        WHERE l.status = 'approved' 
          AND l.start_date >= CURDATE()
        ORDER BY l.start_date ASC
        LIMIT 5
    ");
    $upcoming_leaves = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
    $leaves = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'total_days_approved' => 0];
    $upcoming_leaves = [];
}

// Fonction pour le nom du type de congé
function getLeaveTypeName($type) {
    $types = [
        'CP' => '🏖️ Congés Payés',
        'RTT' => '📅 RTT',
        'Maladie' => '🤒 Maladie',
        'Sans solde' => '💼 Sans solde',
        'Formation' => '📚 Formation'
    ];
    return $types[$type] ?? $type;
}

// Fonction pour le badge de statut
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="status-badge status-pending">⏳ En attente</span>',
        'approved' => '<span class="status-badge status-approved">✅ Approuvé</span>',
        'rejected' => '<span class="status-badge status-rejected">❌ Refusé</span>'
    ];
    return $badges[$status] ?? $status;
}
?>

<style>
    .conges-section { padding: 1rem 0; }
    
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
    
    .leave-card {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .leave-card.pending {
        border-left: 4px solid #FFE66D;
    }
    
    .leave-card.approved {
        border-left: 4px solid var(--secondary);
    }
    
    .leave-card.rejected {
        border-left: 4px solid var(--primary);
    }
    
    .leave-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }
    
    .leave-header {
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
    
    .employee-position {
        color: var(--secondary);
        font-size: 0.9rem;
    }
    
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-pending {
        background: rgba(255, 230, 109, 0.2);
        color: #FFE66D;
    }
    
    .status-approved {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
    }
    
    .status-rejected {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
    }
    
    .leave-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
        margin: 1rem 0;
    }
    
    .detail-item { text-align: center; }
    
    .detail-label {
        color: var(--secondary);
        font-size: 0.85rem;
        margin-bottom: 0.3rem;
    }
    
    .detail-value {
        color: var(--light);
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .leave-actions {
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
    
    .btn-approve {
        background: linear-gradient(45deg, #4ECDC4, #44A08D);
        color: white;
    }
    
    .btn-approve:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
    }
    
    .btn-reject {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
        border: 1px solid var(--primary);
    }
    
    .btn-reject:hover {
        background: var(--primary);
        color: white;
    }
    
    .btn-delete {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
        border: 1px solid var(--primary);
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
    
    .upcoming-box {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .upcoming-title {
        font-size: 1.2rem;
        color: var(--light);
        font-weight: 700;
        margin-bottom: 1rem;
    }
    
    .upcoming-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 8px;
        margin-bottom: 0.5rem;
    }
    
    .upcoming-item:last-child {
        margin-bottom: 0;
    }

    .badge-urgent {
        padding: 0.3rem 0.6rem;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
        margin-left: 0.5rem;
    }
</style>

<div class="conges-section">
    
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
            <h3 style="font-size: 1.5rem; color: var(--light); margin-bottom: 0.5rem;">
                🏖️ Gestion des Congés Employés
            </h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">
                Approuvez ou refusez les demandes de congés
            </p>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">📄 Total Demandes (année)</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">⏳ En attente</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">✅ Approuvés</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">❌ Refusés</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $stats['total_days_approved']; ?></div>
            <div class="stat-label">📅 Jours approuvés</div>
        </div>
    </div>

    <!-- Congés à venir -->
    <?php if (!empty($upcoming_leaves)): ?>
    <div class="upcoming-box">
        <div class="upcoming-title">📅 Congés Approuvés à Venir</div>
        <?php foreach ($upcoming_leaves as $leave): ?>
        <div class="upcoming-item">
            <div>
                <strong><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></strong>
                <span style="color: var(--secondary); margin-left: 0.5rem;">
                    <?php echo getLeaveTypeName($leave['type']); ?>
                </span>
            </div>
            <div style="color: var(--light); font-weight: 600;">
                <?php echo date('d/m/Y', strtotime($leave['start_date'])); ?> 
                → 
                <?php echo date('d/m/Y', strtotime($leave['end_date'])); ?>
                <span style="color: var(--secondary); margin-left: 0.5rem;">
                    (<?php echo $leave['days_count']; ?> jours)
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="filters-bar">
        <span style="color: var(--light); font-weight: 600;">🔍 Filtrer :</span>
        <form method="GET" style="display: flex; gap: 1rem; flex: 1;">
            <input type="hidden" name="module" value="rh">
            <input type="hidden" name="action" value="conges_admin">
            
            <select class="filter-select" name="status" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>⏳ En attente</option>
                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>✅ Approuvés</option>
                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>❌ Refusés</option>
            </select>
            
            <select class="filter-select" name="type" onchange="this.form.submit()">
                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>Tous les types</option>
                <option value="CP" <?php echo $filter_type === 'CP' ? 'selected' : ''; ?>>🏖️ Congés Payés</option>
                <option value="RTT" <?php echo $filter_type === 'RTT' ? 'selected' : ''; ?>>📅 RTT</option>
                <option value="Maladie" <?php echo $filter_type === 'Maladie' ? 'selected' : ''; ?>>🤒 Maladie</option>
                <option value="Sans solde" <?php echo $filter_type === 'Sans solde' ? 'selected' : ''; ?>>💼 Sans solde</option>
                <option value="Formation" <?php echo $filter_type === 'Formation' ? 'selected' : ''; ?>>📚 Formation</option>
            </select>
        </form>
    </div>

    <!-- Liste des demandes de congés -->
    <?php if (empty($leaves)): ?>
        <div class="empty-state">
            <h3>🏖️ Aucune demande</h3>
            <p>Il n'y a pas de demandes de congés avec ces filtres.</p>
        </div>
    <?php else: ?>
        <?php foreach ($leaves as $leave): 
            // Vérifier si la demande est urgente (dans moins de 7 jours et en attente)
            $is_urgent = $leave['status'] === 'pending' && 
                         strtotime($leave['start_date']) - time() < 7 * 24 * 60 * 60;
        ?>
        <div class="leave-card <?php echo $leave['status']; ?>">
            <div class="leave-header">
                <div>
                    <div class="employee-name">
                        <?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?>
                        <?php if ($is_urgent): ?>
                            <span class="badge-urgent">⚠️ URGENT</span>
                        <?php endif; ?>
                    </div>
                    <div class="employee-position">
                        <?php echo htmlspecialchars($leave['position']); ?>
                        <?php if ($leave['department']): ?>
                            • <?php echo htmlspecialchars($leave['department']); ?>
                        <?php endif; ?>
                    </div>
                    <div style="color: var(--secondary); font-size: 0.85rem; margin-top: 0.3rem;">
                        📝 Demandé le <?php echo date('d/m/Y à H:i', strtotime($leave['created_at'])); ?>
                    </div>
                </div>
                <?php echo getStatusBadge($leave['status']); ?>
            </div>

            <div class="leave-details">
                <div class="detail-item">
                    <div class="detail-label">📋 Type</div>
                    <div class="detail-value"><?php echo getLeaveTypeName($leave['type']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">📅 Début</div>
                    <div class="detail-value"><?php echo date('d/m/Y', strtotime($leave['start_date'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">📅 Fin</div>
                    <div class="detail-value"><?php echo date('d/m/Y', strtotime($leave['end_date'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">⏱️ Durée</div>
                    <div class="detail-value"><?php echo $leave['days_count']; ?> jours</div>
                </div>
            </div>

            <?php if ($leave['reason']): ?>
            <div style="padding: 1rem; background: rgba(255, 255, 255, 0.03); border-radius: 10px; margin: 1rem 0;">
                <div style="color: var(--secondary); font-size: 0.85rem; margin-bottom: 0.3rem;">💬 Motif :</div>
                <div style="color: var(--light);"><?php echo nl2br(htmlspecialchars($leave['reason'])); ?></div>
            </div>
            <?php endif; ?>

            <?php if ($leave['status'] !== 'pending' && $leave['approved_by']): ?>
            <div style="color: var(--secondary); font-size: 0.85rem; margin-top: 1rem;">
                <?php 
                $action = $leave['status'] === 'approved' ? 'Approuvé' : 'Refusé';
                echo "$action par " . htmlspecialchars($leave['approver_first_name'] . ' ' . $leave['approver_last_name']);
                if ($leave['approved_at']) {
                    echo " le " . date('d/m/Y à H:i', strtotime($leave['approved_at']));
                }
                ?>
            </div>
            <?php endif; ?>

            <?php if ($leave['status'] === 'pending'): ?>
            <div class="leave-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                    <button type="submit" class="btn-action btn-approve" onclick="return confirm('✅ Approuver cette demande de congé ?')">
                        ✅ Approuver
                    </button>
                </form>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                    <button type="submit" class="btn-action btn-reject" onclick="return confirm('❌ Refuser cette demande de congé ?')">
                        ❌ Refuser
                    </button>
                </form>
            </div>
            <?php endif; ?>
            
            <form method="POST" style="display: inline; margin-top: 1rem;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                <button type="submit" class="btn-action btn-delete" onclick="return confirm('🗑️ Supprimer cette demande ?')">
                    🗑️ Supprimer
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
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