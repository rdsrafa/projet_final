<?php
// employes/generer_pdf_paie.php - Génération PDF d'une fiche de paie
session_start();
require_once '../include/db.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['employee', 'manager'])) {
    header('Location: ../login.php');
    exit();
}

// Vérifier que l'ID de la fiche est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Fiche de paie introuvable');
}

$db = getDB();
$payslip_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'employé
$stmt = $db->prepare("SELECT * FROM v_employee_users WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Erreur : Employé introuvable");
}

// Récupérer la fiche de paie
$stmt = $db->prepare("
    SELECT p.* 
    FROM payslips p
    WHERE p.id = ? AND p.employee_id = ?
");
$stmt->execute([$payslip_id, $employee['employee_id']]);
$payslip = $stmt->fetch();

if (!$payslip) {
    die('Fiche de paie introuvable ou accès non autorisé');
}

// Noms des mois en français
$mois_fr = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

$mois_nom = $mois_fr[$payslip['month']];
$periode = $mois_nom . ' ' . $payslip['year'];

// Générer le HTML pour le PDF
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche de Paie - <?php echo $periode; ?></title>
    <style>
        @media print {
            body {
                margin: 0;
                padding: 20px;
            }
            .no-print {
                display: none;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header .periode {
            font-size: 18px;
            color: #666;
        }
        
        .company-info {
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #333;
        }
        
        .company-info h2 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-block h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        
        .info-block p {
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        table th {
            background: #333;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        
        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        
        table tr:hover {
            background: #f9f9f9;
        }
        
        .total-row {
            background: #f0f0f0 !important;
            font-weight: bold;
            font-size: 16px;
        }
        
        .total-row td {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            padding: 15px 12px;
        }
        
        .net-payer {
            background: #4CAF50 !important;
            color: white !important;
            font-size: 18px;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        .btn-download {
            display: inline-block;
            padding: 12px 30px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .btn-download:hover {
            background: #45a049;
        }
        
        .actions {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button onclick="window.print()" class="btn-download">
            🖨️ Imprimer / Télécharger PDF
        </button>
        <a href="mes_fiches_paie.php" class="btn-download" style="background: #666;">
            ← Retour
        </a>
    </div>
    
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <h1>BULLETIN DE PAIE</h1>
            <div class="periode"><?php echo $periode; ?></div>
        </div>
        
        <!-- Informations entreprise -->
        <div class="company-info">
            <h2>ADOO SNEAKERS</h2>
            <p>123 Rue de la Sneaker, 75001 Paris</p>
            <p>SIRET : 123 456 789 00012</p>
            <p>Code NAF : 4772A</p>
        </div>
        
        <!-- Informations salarié et emploi -->
        <div class="info-grid">
            <div class="info-block">
                <h3>Salarié</h3>
                <p><strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($employee['email']); ?></p>
            </div>
            
            <div class="info-block">
                <h3>Emploi</h3>
                <p><strong>Poste :</strong> <?php echo htmlspecialchars($employee['position']); ?></p>
                <p><strong>Date d'embauche :</strong> <?php echo date('d/m/Y', strtotime($employee['hire_date'])); ?></p>
            </div>
        </div>
        
        <!-- Détail de la paie -->
        <table>
            <thead>
                <tr>
                    <th>Libellé</th>
                    <th style="text-align: right;">Base</th>
                    <th style="text-align: right;">Montant</th>
                </tr>
            </thead>
            <tbody>
                <!-- Salaire de base -->
                <tr>
                    <td>Salaire de base</td>
                    <td style="text-align: right;">151.67 h</td>
                    <td style="text-align: right;"><?php echo number_format($payslip['gross_salary'], 2, ',', ' '); ?> €</td>
                </tr>
                
                <!-- Primes -->
                <?php if ($payslip['bonuses'] > 0): ?>
                <tr>
                    <td>Primes et gratifications</td>
                    <td style="text-align: right;">-</td>
                    <td style="text-align: right; color: #4CAF50;">+ <?php echo number_format($payslip['bonuses'], 2, ',', ' '); ?> €</td>
                </tr>
                <?php endif; ?>
                
                <!-- Total brut -->
                <tr class="total-row">
                    <td colspan="2"><strong>SALAIRE BRUT</strong></td>
                    <td style="text-align: right;"><?php echo number_format($payslip['gross_salary'] + $payslip['bonuses'], 2, ',', ' '); ?> €</td>
                </tr>
                
                <!-- Cotisations -->
                <tr>
                    <td>Cotisations salariales</td>
                    <td style="text-align: right;">
                        <?php echo number_format(($payslip['deductions'] / ($payslip['gross_salary'] + $payslip['bonuses'])) * 100, 2); ?>%
                    </td>
                    <td style="text-align: right; color: #f44336;">- <?php echo number_format($payslip['deductions'], 2, ',', ' '); ?> €</td>
                </tr>
                
                <!-- Net à payer -->
                <tr class="net-payer">
                    <td colspan="2"><strong>NET À PAYER</strong></td>
                    <td style="text-align: right;"><?php echo number_format($payslip['net_salary'], 2, ',', ' '); ?> €</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Informations complémentaires -->
        <div style="margin-top: 30px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
            <h3 style="font-size: 14px; margin-bottom: 10px; color: #333;">INFORMATIONS COMPLÉMENTAIRES</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 12px;">
                <p><strong>Mode de paiement :</strong> Virement bancaire</p>
                <p><strong>Date de paiement :</strong> <?php echo date('d/m/Y', strtotime($payslip['created_at'])); ?></p>
                <p><strong>Période :</strong> <?php echo date('d/m/Y', strtotime($payslip['year'] . '-' . $payslip['month'] . '-01')); ?> au <?php echo date('d/m/Y', strtotime($payslip['year'] . '-' . $payslip['month'] . '-' . date('t', strtotime($payslip['year'] . '-' . $payslip['month'] . '-01')))); ?></p>
                <p><strong>Congés acquis :</strong> 2.08 j</p>
            </div>
        </div>
        
        <!-- Pied de page -->
        <div class="footer">
            <p>Ce document a été généré automatiquement le <?php echo date('d/m/Y à H:i'); ?></p>
            <p>Conservez ce bulletin de paie sans limitation de durée</p>
        </div>
    </div>
    
    <script>
        // Auto-print pour téléchargement direct en PDF
        // Décommenter la ligne suivante si vous voulez l'impression automatique
        // window.onload = function() { window.print(); };
    </script>
</body>
</html>