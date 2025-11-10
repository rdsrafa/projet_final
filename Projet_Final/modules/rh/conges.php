<?php
// modules/rh/conges.php - Gestion des Congés

// Récupérer les employés
$employees_query = "SELECT id, first_name, last_name, position FROM employees WHERE status = 'active' ORDER BY last_name";
$employees_result = mysqli_query($conn, $employees_query);
$employees = [];
while ($row = mysqli_fetch_assoc($employees_result)) {
    $employees[] = $row;
}

// Récupérer les demandes de congés
$leaves_query = "
    SELECT l.*, 
           CONCAT(e.first_name, ' ', e.last_name) as employee_name,
           e.position,
           CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
    FROM leaves l
    JOIN employees e ON l.employee_id = e.id
    LEFT JOIN employees approver ON l.approved_by = approver.id
    ORDER BY 
        CASE l.status
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        l.created_at DESC
";
$leaves_result = mysqli_query($conn, $leaves_query);
$leaves = [];
while ($row = mysqli_fetch_assoc($leaves_result)) {
    $leaves[] = $row;
}

// Statistiques des congés
$stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'approved' THEN days_count ELSE 0 END) as total_days_taken
    FROM leaves
    WHERE YEAR(start_date) = YEAR(CURDATE())
";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Traitement des actions (Approbation/Rejet)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $leave_id = intval($_POST['leave_id']);
    $action = $_POST['action'];
    $employee_id = 1; // ID de l'admin connecté
    
    if ($action === 'approve') {
        $update_query = "UPDATE leaves SET status = 'approved', approved_by = $employee_id, approved_at = NOW() WHERE id = $leave_id";
    } else if ($action === 'reject') {
        $update_query = "UPDATE leaves SET status = 'rejected', approved_by = $employee_id, approved_at = NOW() WHERE id = $leave_id";
    }
    
    if (isset($update_query)) {
        mysqli_query($conn, $update_query);
        header('Location: index.php?module=rh&action=conges');
        exit;
    }
}

// Fonction pour formater les types de congés
function getLeaveTypeLabel($type) {
    $types = [
        'CP' => ['label' => 'Congés Payés', 'class' => 'info'],
        'RTT' => ['label' => 'RTT', 'class' => 'warning'],
        'Maladie' => ['label' => 'Arrêt Maladie', 'class' => 'danger'],
        'Sans solde' => ['label' => 'Sans Solde', 'class' => 'secondary'],
        'Formation' => ['label' => 'Formation', 'class' => 'primary']
    ];
    return $types[$type] ?? ['label' => $type, 'class' => 'secondary'];
}

function getStatusLabel($status) {
    $statuses = [
        'pending' => ['label' => '⏳ En attente', 'class' => 'warning'],
        'approved' => ['label' => '✓ Approuvé', 'class' => 'success'],
        'rejected' => ['label' => '✗ Refusé', 'class' => 'danger']
    ];
    return $statuses[$status] ?? ['label' => $status, 'class' => 'secondary'];
}
?>

<div id="conges-module">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h3 style="font-size: 1.2rem; color: var(--light); margin-bottom: 0.5rem;">Gestion des Congés</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Demandes, validations et planification</p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-secondary" onclick="window.location.href='index.php?module=rh&action=personnel'">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                Personnel
            </button>
            <button class="btn btn-primary" onclick="showAddLeaveModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="white" viewBox="0 0 24 24">
                    <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                Nouvelle Demande
            </button>
        </div>
    </div>

    <!-- KPIs Congés -->
    <div class="cards-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v10z"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo $stats['pending']; ?></div>
            <div class="card-label">En Attente</div>
            <div class="trend warning">⏳ À traiter</div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(45deg, #4ECDC4, #FFE66D);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo $stats['approved']; ?></div>
            <div class="card-label">Approuvés</div>
            <div class="trend up">✓ Validés</div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(45deg, #FFE66D, #FF6B6B);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo $stats['rejected']; ?></div>
            <div class="card-label">Refusés</div>
            <div class="trend down">✗ Rejetés</div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(45deg, #6C5CE7, #4ECDC4);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="white" viewBox="0 0 24 24">
                        <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                    </svg>
                </div>
            </div>
            <div class="card-value"><?php echo $stats['total_days_taken']; ?></div>
            <div class="card-label">Jours Pris (2025)</div>
            <div style="margin-top: 0.5rem; color: var(--light); opacity: 0.7; font-size: 0.85rem;">
                Total annuel
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="table-container" style="margin-bottom: 1rem; padding: 1rem;">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" class="form-control" placeholder="🔍 Rechercher..." 
                       onkeyup="filterLeaves(this.value)">
            </div>
            <select class="form-control" style="width: auto; min-width: 150px;" onchange="filterByStatus(this.value)">
                <option value="">Tous les statuts</option>
                <option value="pending">En attente</option>
                <option value="approved">Approuvés</option>
                <option value="rejected">Refusés</option>
            </select>
            <select class="form-control" style="width: auto; min-width: 150px;" onchange="filterByType(this.value)">
                <option value="">Tous les types</option>
                <option value="CP">Congés Payés</option>
                <option value="RTT">RTT</option>
                <option value="Maladie">Maladie</option>
                <option value="Formation">Formation</option>
                <option value="Sans solde">Sans solde</option>
            </select>
        </div>
    </div>

    <!-- Liste des demandes de congés -->
    <div class="table-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3>📋 Demandes de Congés</h3>
            <button class="btn btn-secondary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" viewBox="0 0 24 24">
                    <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z"/>
                </svg>
                Exporter
            </button>
        </div>
        
        <table id="leaves-table">
            <thead>
                <tr>
                    <th>Collaborateur</th>
                    <th>Poste</th>
                    <th>Type</th>
                    <th>Date début</th>
                    <th>Date fin</th>
                    <th>Durée</th>
                    <th>Motif</th>
                    <th>Statut</th>
                    <th>Validé par</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 2rem; color: var(--secondary);">
                        Aucune demande de congés
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($leaves as $leave): 
                        $type_info = getLeaveTypeLabel($leave['type']);
                        $status_info = getStatusLabel($leave['status']);
                    ?>
                    <tr class="leave-row" data-status="<?php echo $leave['status']; ?>" data-type="<?php echo $leave['type']; ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <div class="avatar" style="width: 35px; height: 35px; font-size: 0.85rem;">
                                    <?php 
                                    $names = explode(' ', $leave['employee_name']);
                                    echo strtoupper(substr($names[0], 0, 1) . substr($names[1] ?? '', 0, 1));
                                    ?>
                                </div>
                                <strong><?php echo htmlspecialchars($leave['employee_name']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($leave['position']); ?></td>
                        <td>
                            <span class="badge <?php echo $type_info['class']; ?>">
                                <?php echo $type_info['label']; ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($leave['start_date'])); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($leave['end_date'])); ?></td>
                        <td><strong><?php echo $leave['days_count']; ?> jour<?php echo $leave['days_count'] > 1 ? 's' : ''; ?></strong></td>
                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($leave['reason'] ?? '-'); ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $status_info['class']; ?>">
                                <?php echo $status_info['label']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($leave['approver_name'] ?? '-'); ?></td>
                        <td>
                            <?php if ($leave['status'] === 'pending'): ?>
                            <div style="display: flex; gap: 0.5rem;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-sm btn-success" title="Approuver">
                                        ✓
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Refuser">
                                        ✗
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <button class="btn btn-sm btn-secondary" onclick="viewLeaveDetails(<?php echo $leave['id']; ?>)">
                                👁️ Voir
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Calendrier des absences (aperçu) -->
    <div class="section-header" style="margin-top: 2rem;">
        <h3>📅 Calendrier des Absences - Novembre 2025</h3>
    </div>
    <div class="table-container" style="padding: 2rem;">
        <p style="text-align: center; color: var(--secondary);">
            🚧 Calendrier visuel en développement
        </p>
    </div>
</div>

<!-- Modal Nouvelle Demande -->
<div id="addLeaveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📅 Nouvelle Demande de Congés</h3>
            <span class="close-modal" onclick="closeModal('addLeaveModal')">&times;</span>
        </div>
        <form method="POST" action="modules/rh/process_leave.php">
            <div class="form-group">
                <label>Collaborateur</label>
                <select class="form-control" name="employee_id" required>
                    <option value="">Sélectionner...</option>
                    <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo $emp['id']; ?>">
                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' - ' . $emp['position']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Type de congé</label>
                <select class="form-control" name="type" required>
                    <option value="">Sélectionner...</option>
                    <option value="CP">Congés Payés (CP)</option>
                    <option value="RTT">RTT</option>
                    <option value="Maladie">Arrêt Maladie</option>
                    <option value="Sans solde">Sans Solde</option>
                    <option value="Formation">Formation</option>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Date de début</label>
                    <input type="date" class="form-control" name="start_date" required 
                           onchange="calculateDays()">
                </div>

                <div class="form-group">
                    <label>Date de fin</label>
                    <input type="date" class="form-control" name="end_date" required 
                           onchange="calculateDays()">
                </div>
            </div>

            <div class="form-group">
                <label>Nombre de jours</label>
                <input type="number" class="form-control" name="days_count" 
                       id="days_count" min="1" required readonly>
                <small style="color: var(--secondary);">Calculé automatiquement</small>
            </div>

            <div class="form-group">
                <label>Motif / Raison</label>
                <textarea class="form-control" name="reason" rows="3" 
                          placeholder="Facultatif"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addLeaveModal')">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    Enregistrer la demande
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddLeaveModal() {
    document.getElementById('addLeaveModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function filterLeaves(searchTerm) {
    const rows = document.querySelectorAll('#leaves-table tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
    });
}

function filterByStatus(status) {
    const rows = document.querySelectorAll('.leave-row');
    rows.forEach(row => {
        if (!status || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterByType(type) {
    const rows = document.querySelectorAll('.leave-row');
    rows.forEach(row => {
        if (!type || row.dataset.type === type) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function calculateDays() {
    const startDate = document.querySelector('input[name="start_date"]').value;
    const endDate = document.querySelector('input[name="end_date"]').value;
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('days_count').value = diffDays;
    }
}

function viewLeaveDetails(leaveId) {
    alert('Détails de la demande #' + leaveId);
}
</script>