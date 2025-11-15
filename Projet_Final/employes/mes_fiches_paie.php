<?php
// employes/mes_fiches_paie.php - Consultation des fiches de paie de l'employé
session_start();
require_once '../include/db.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['employee', 'manager'])) {
    header('Location: ../login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = null;
$success = null;

// Récupérer les infos de l'employé
$stmt = $db->prepare("SELECT * FROM v_employee_users WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Erreur : Employé introuvable");
}

$employee_id = $employee['employee_id'];

// Récupérer les fiches de paie
$annee_filter = $_GET['annee'] ?? date('Y');

$stmt = $db->prepare("
    SELECT 
        p.*,
        CASE p.month
            WHEN 1 THEN 'Janvier'
            WHEN 2 THEN 'Février'
            WHEN 3 THEN 'Mars'
            WHEN 4 THEN 'Avril'
            WHEN 5 THEN 'Mai'
            WHEN 6 THEN 'Juin'
            WHEN 7 THEN 'Juillet'
            WHEN 8 THEN 'Août'
            WHEN 9 THEN 'Septembre'
            WHEN 10 THEN 'Octobre'
            WHEN 11 THEN 'Novembre'
            WHEN 12 THEN 'Décembre'
        END as mois_nom
    FROM payslips p
    WHERE p.employee_id = ? 
    AND p.year = ?
    ORDER BY p.year DESC, p.month DESC
");
$stmt->execute([$employee_id, $annee_filter]);
$fiches_paie = $stmt->fetchAll();

// Récupérer les années disponibles
$annees = $db->prepare("
    SELECT DISTINCT year as annee 
    FROM payslips 
    WHERE employee_id = ?
    ORDER BY year DESC
");
$annees->execute([$employee_id]);
$annees_dispo = $annees->fetchAll();

// Calculer les totaux de l'année
$total_brut = 0;
$total_net = 0;
$total_deductions = 0;
$total_bonuses = 0;

foreach ($fiches_paie as $fiche) {
    $total_brut += $fiche['gross_salary'];
    $total_net += $fiche['net_salary'];
    $total_deductions += $fiche['deductions'];
    $total_bonuses += $fiche['bonuses'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Fiches de Paie - ADOO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .paie-container {
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .header {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            font-size: 1.8rem;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-secondary { 
            background: var(--glass);
            border: 1px solid var(--glass-border);
            color: var(--light);
        }

        .btn-primary { 
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            color: var(--dark);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .info-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
        }

        .info-card h2 {
            color: var(--light);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            padding: 1rem;
            background: var(--dark);
            border-radius: 12px;
            border: 1px solid var(--glass-border);
        }

        .info-item label {
            display: block;
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .info-item .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--light);
        }

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
            padding: 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #FFE66D, #4ECDC4);
        }

        .stat-card h3 {
            color: var(--secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .filters {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filters label {
            font-weight: 600;
            color: var(--light);
        }

        .filters select {
            padding: 0.75rem;
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--light);
            min-width: 150px;
        }

        .fiches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .fiche-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .fiche-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .fiche-header {
            background: linear-gradient(135deg, #FFE66D 0%, #4ECDC4 100%);
            color: var(--dark);
            padding: 1.5rem;
            text-align: center;
        }

        .fiche-header h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            font-weight: 800;
        }

        .fiche-header .periode {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .fiche-body {
            padding: 1.5rem;
        }

        .fiche-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--glass-border);
            color: var(--light);
        }

        .fiche-row:last-child {
            border-bottom: none;
        }

        .fiche-row.total {
            background: var(--dark);
            margin: 0 -1.5rem;
            padding: 1rem 1.5rem;
            font-weight: 700;
            font-size: 1.2rem;
            border-top: 2px solid var(--glass-border);
            margin-top: 0.5rem;
        }

        .fiche-row.total strong {
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .fiche-footer {
            padding: 1rem 1.5rem;
            background: var(--dark);
            text-align: center;
        }

        .no-results {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 3rem;
            border-radius: 20px;
            text-align: center;
            color: var(--secondary);
        }

        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .fiches-grid {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .header .btn,
            .filters {
                display: none;
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

    <div class="paie-container">
        <div class="header">
            <div>
                <h1>💰 Mes Fiches de Paie</h1>
                <small style="color: var(--secondary);">
                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                </small>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">← Retour</a>
        </div>

        <!-- Informations employé -->
        <div class="info-card">
            <h2>👤 Mes Informations</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>Nom Complet</label>
                    <div class="value"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></div>
                </div>
                <div class="info-item">
                    <label>Poste</label>
                    <div class="value"><?= htmlspecialchars($employee['position']) ?></div>
                </div>
                <div class="info-item">
                    <label>Date d'embauche</label>
                    <div class="value"><?= isset($employee['hire_date']) ? date('d/m/Y', strtotime($employee['hire_date'])) : 'N/A' ?></div>
                </div>
                <div class="info-item">
                    <label>Salaire de base</label>
                    <div class="value"><?= isset($employee['salary']) ? number_format($employee['salary'], 2) : '0.00' ?> €</div>
                </div>
            </div>
        </div>

        <!-- Statistiques annuelles -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Brut (<?= $annee_filter ?>)</h3>
                <div class="value"><?= number_format($total_brut, 0) ?> €</div>
            </div>
            <div class="stat-card">
                <h3>Total Net (<?= $annee_filter ?>)</h3>
                <div class="value"><?= number_format($total_net, 0) ?> €</div>
            </div>
            <div class="stat-card">
                <h3>Primes (<?= $annee_filter ?>)</h3>
                <div class="value"><?= number_format($total_bonuses, 0) ?> €</div>
            </div>
            <div class="stat-card">
                <h3>Cotisations (<?= $annee_filter ?>)</h3>
                <div class="value"><?= number_format($total_deductions, 0) ?> €</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters">
            <label for="annee">📅 Année :</label>
            <select id="annee" name="annee" onchange="window.location.href='?annee='+this.value">
                <?php if (empty($annees_dispo)): ?>
                    <option><?= date('Y') ?></option>
                <?php else: ?>
                    <?php foreach ($annees_dispo as $annee): ?>
                        <option value="<?= $annee['annee'] ?>" <?= $annee['annee'] == $annee_filter ? 'selected' : '' ?>>
                            <?= $annee['annee'] ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <!-- Fiches de paie -->
        <?php if (count($fiches_paie) > 0): ?>
            <div class="fiches-grid">
                <?php foreach ($fiches_paie as $fiche): ?>
                    <div class="fiche-card">
                        <div class="fiche-header">
                            <h3>💼 Fiche de Paie</h3>
                            <div class="periode">
                                <?= $fiche['mois_nom'] ?> <?= $fiche['year'] ?>
                            </div>
                        </div>
                        <div class="fiche-body">
                            <div class="fiche-row">
                                <span>Salaire de base</span>
                                <strong><?= number_format($fiche['gross_salary'], 2) ?> €</strong>
                            </div>
                            <?php if ($fiche['bonuses'] > 0): ?>
                            <div class="fiche-row">
                                <span>Primes</span>
                                <strong style="color: var(--secondary);">+ <?= number_format($fiche['bonuses'], 2) ?> €</strong>
                            </div>
                            <?php endif; ?>
                            <div class="fiche-row">
                                <span>Cotisations sociales</span>
                                <strong style="color: var(--primary);">- <?= number_format($fiche['deductions'], 2) ?> €</strong>
                            </div>
                            <div class="fiche-row total">
                                <span>Salaire net</span>
                                <strong><?= number_format($fiche['net_salary'], 2) ?> €</strong>
                            </div>
                        </div>
                        <div class="fiche-footer">
                            <button onclick="imprimerFiche(<?= $fiche['id'] ?>)" class="btn btn-primary btn-sm">
                                🖨️ Télécharger PDF
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">😕</div>
                <h3 style="color: var(--light); margin-bottom: 0.5rem;">Aucune fiche de paie</h3>
                <p>Aucune fiche de paie n'est disponible pour l'année <?= $annee_filter ?>.</p>
            </div>
        <?php endif; ?>

        <!-- Informations complémentaires -->
        <div class="info-card" style="margin-top: 2rem;">
            <h2>ℹ️ Informations</h2>
            <ul style="list-style: none; padding: 0; color: var(--light);">
                <li style="padding: 0.5rem 0;">📄 Les fiches de paie sont disponibles le 1er de chaque mois</li>
                <li style="padding: 0.5rem 0;">🔒 Vos données sont sécurisées et confidentielles</li>
                <li style="padding: 0.5rem 0;">📧 En cas de question, contactez le service RH à rh@adoo.com</li>
                <li style="padding: 0.5rem 0;">💾 Conservez vos fiches de paie pendant au moins 3 ans</li>
            </ul>
        </div>
    </div>

    <script>
        function imprimerFiche(ficheId) {
            // Rediriger vers une page de génération PDF
            window.open('generer_pdf_paie.php?id=' + ficheId, '_blank');
        }
    </script>
</body>
</html>