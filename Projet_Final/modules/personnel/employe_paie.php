<?php
/**
 * employes/employe_paie.php
 * Consultation des fiches de paie pour l'employé connecté
 */

// Vérification session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Récupérer l'employee_id de l'utilisateur connecté
$stmt = $db->prepare("SELECT employee_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$employee_id = $user['employee_id'];

// Récupérer les informations de l'employé
$stmt = $db->prepare("
    SELECT * FROM employees 
    WHERE id = ?
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

// Récupérer les fiches de paie (si vous avez une table payslips)
// Sinon, afficher un message temporaire
$payslips = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM payslips 
        WHERE employee_id = ? 
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$employee_id]);
    $payslips = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table payslips n'existe pas encore
    $payslips = [];
}
?>

<style>
    .paie-section {
        padding: 2rem;
    }

    .employee-info-card {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 2rem;
        margin-bottom: 2rem;
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
        transform: translateY(-3px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .payslip-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--glass-border);
    }

    .payslip-month {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--light);
    }

    .payslip-amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--secondary);
    }

    .payslip-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }

    .detail-item {
        text-align: center;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
    }

    .detail-label {
        color: var(--secondary);
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    .detail-value {
        color: var(--light);
        font-weight: 600;
        font-size: 1.1rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: var(--secondary);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
    }
</style>

<div class="paie-section">
    <h2 style="color: var(--light); margin-bottom: 2rem;">💵 Mes Fiches de Paie</h2>

    <!-- Informations employé -->
    <div class="employee-info-card">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div>
                <h3 style="color: var(--light); margin: 0 0 0.5rem 0;">
                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                </h3>
                <p style="color: var(--secondary); margin: 0;">
                    <?php echo htmlspecialchars($employee['position']); ?>
                </p>
            </div>
            <div style="text-align: right;">
                <div style="color: var(--secondary); font-size: 0.9rem;">Salaire mensuel</div>
                <div style="color: var(--light); font-size: 1.5rem; font-weight: 700;">
                    <?php echo number_format($employee['salary'], 2, ',', ' '); ?> €
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des fiches de paie -->
    <?php if (empty($payslips)): ?>
        <div class="empty-state">
            <div style="font-size: 4rem;">📄</div>
            <h3 style="color: var(--light);">Aucune fiche de paie disponible</h3>
            <p>Vos fiches de paie apparaîtront ici une fois générées par les RH.</p>
        </div>
    <?php else: ?>
        <?php foreach ($payslips as $payslip): ?>
        <div class="payslip-card">
            <div class="payslip-header">
                <div class="payslip-month">
                    📅 <?php echo date('F Y', strtotime($payslip['payment_date'])); ?>
                </div>
                <div class="payslip-amount">
                    <?php echo number_format($payslip['net_salary'], 2, ',', ' '); ?> €
                </div>
            </div>

            <div class="payslip-details">
                <div class="detail-item">
                    <div class="detail-label">💰 Salaire Brut</div>
                    <div class="detail-value">
                        <?php echo number_format($payslip['gross_salary'], 2, ',', ' '); ?> €
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">➖ Cotisations</div>
                    <div class="detail-value" style="color: var(--primary);">
                        <?php echo number_format($payslip['deductions'], 2, ',', ' '); ?> €
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">✅ Net à Payer</div>
                    <div class="detail-value" style="color: var(--secondary);">
                        <?php echo number_format($payslip['net_salary'], 2, ',', ' '); ?> €
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">📊 Heures Travaillées</div>
                    <div class="detail-value">
                        <?php echo $payslip['hours_worked'] ?? 151.67; ?> h
                    </div>
                </div>
            </div>

            <div style="margin-top: 1rem; text-align: right;">
                <button class="btn btn-secondary btn-sm" onclick="downloadPayslip(<?php echo $payslip['id']; ?>)">
                    📥 Télécharger PDF
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function downloadPayslip(payslipId) {
    alert('🚧 Fonctionnalité de téléchargement en cours de développement');
    // Futur : window.location.href = 'download_payslip.php?id=' + payslipId;
}
</script>