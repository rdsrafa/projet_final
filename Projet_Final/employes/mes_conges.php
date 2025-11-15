<?php
// employes/mes_conges.php - Gestion des demandes de congés
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

// Traitement d'une nouvelle demande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demander_conge'])) {
    try {
        $type_conge = $_POST['type_conge'];
        $date_debut = $_POST['date_debut'];
        $date_fin = $_POST['date_fin'];
        $motif = $_POST['motif'] ?? '';
        
        if (empty($type_conge) || empty($date_debut) || empty($date_fin)) {
            throw new Exception("Veuillez remplir tous les champs obligatoires");
        }
        
        if (strtotime($date_fin) < strtotime($date_debut)) {
            throw new Exception("La date de fin doit être après la date de début");
        }
        
        if (strtotime($date_debut) < strtotime('today')) {
            throw new Exception("La date de début ne peut pas être dans le passé");
        }
        
        // Calculer le nombre de jours
        $datetime1 = new DateTime($date_debut);
        $datetime2 = new DateTime($date_fin);
        $interval = $datetime1->diff($datetime2);
        $nb_jours = $interval->days + 1;
        
        // Insérer la demande dans la table 'employee_leaves'
        $stmt = $db->prepare("
            INSERT INTO employee_leaves (employee_id, type, start_date, end_date, days_count, reason, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$employee_id, $type_conge, $date_debut, $date_fin, $nb_jours, $motif]);
        
        $success = "Demande de congé envoyée avec succès. Elle sera examinée par votre responsable.";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Annuler une demande
if (isset($_GET['annuler']) && isset($_GET['id'])) {
    try {
        $conge_id = $_GET['id'];
        
        // Vérifier que le congé appartient bien à l'employé et est en attente
        $stmt = $db->prepare("
            SELECT * FROM employee_leaves 
            WHERE id = ? AND employee_id = ? AND status = 'pending'
        ");
        $stmt->execute([$conge_id, $employee_id]);
        $conge = $stmt->fetch();
        
        if (!$conge) {
            throw new Exception("Impossible d'annuler cette demande");
        }
        
        $stmt = $db->prepare("DELETE FROM employee_leaves WHERE id = ?");
        $stmt->execute([$conge_id]);
        
        $success = "Demande annulée avec succès";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer toutes les demandes de congés de l'employé
$mes_conges = $db->prepare("
    SELECT * FROM employee_leaves 
    WHERE employee_id = ?
    ORDER BY created_at DESC
");
$mes_conges->execute([$employee_id]);
$conges = $mes_conges->fetchAll();

// Statistiques
$stats = [
    'total' => count($conges),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'jours_pris' => 0
];

$annee_courante = date('Y');
foreach ($conges as $conge) {
    $stats[$conge['status']]++;
    if ($conge['status'] === 'approved' && date('Y', strtotime($conge['start_date'])) == $annee_courante) {
        $stats['jours_pris'] += $conge['days_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Congés - ADOO</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .conges-container {
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

        .btn-primary { 
            background: linear-gradient(45deg, #FFE66D, #4ECDC4);
            color: var(--dark);
        }
        
        .btn-secondary { 
            background: var(--glass);
            border: 1px solid var(--glass-border);
            color: var(--light);
        }
        
        .btn-danger { 
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(46, 213, 115, 0.2);
            border-color: #2ed573;
            color: #2ed573;
        }

        .alert-error {
            background: rgba(255, 107, 107, 0.2);
            border-color: var(--primary);
            color: var(--primary);
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

        .card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
        }

        .card h2 {
            color: var(--light);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--light);
            font-weight: 600;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            font-size: 1rem;
            color: var(--light);
            transition: all 0.3s ease;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-box {
            background: rgba(78, 205, 196, 0.1);
            border-left: 4px solid var(--secondary);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 12px;
        }

        .info-box ul {
            margin-left: 1.5rem;
            margin-top: 0.5rem;
            color: var(--light);
        }

        .info-box li {
            margin: 0.5rem 0;
        }

        .conge-card {
            background: var(--dark);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .conge-card:hover {
            transform: translateX(5px);
            border-color: var(--secondary);
        }

        .conge-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-warning {
            background: rgba(255, 230, 109, 0.2);
            color: #FFE66D;
        }

        .badge-success {
            background: rgba(78, 205, 196, 0.2);
            color: var(--secondary);
        }

        .badge-danger {
            background: rgba(255, 107, 107, 0.2);
            color: var(--primary);
        }

        .conge-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            color: var(--secondary);
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--light);
            font-weight: 600;
            margin-top: 0.3rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--secondary);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
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

    <div class="conges-container">
        <div class="header">
            <div>
                <h1>🏖️ Mes Demandes de Congés</h1>
                <small style="color: var(--secondary);">
                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                </small>
            </div>
            <a href="dashboard.php" class="btn btn-secondary">← Retour</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Demandes Totales</h3>
                <div class="value"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-card">
                <h3>En Attente</h3>
                <div class="value" style="color: #FFE66D;"><?= $stats['pending'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Approuvées</h3>
                <div class="value" style="color: var(--secondary);"><?= $stats['approved'] ?></div>
            </div>
            <div class="stat-card">
                <h3>Jours Pris (<?= $annee_courante ?>)</h3>
                <div class="value" style="color: var(--primary);"><?= $stats['jours_pris'] ?></div>
            </div>
        </div>

        <!-- Formulaire de demande -->
        <div class="card">
            <h2>➕ Nouvelle Demande de Congé</h2>
            
            <div class="info-box">
                <strong>ℹ️ À savoir :</strong>
                <ul>
                    <li>Votre demande sera examinée par votre responsable</li>
                    <li>Les congés payés doivent être demandés au moins 7 jours à l'avance</li>
                    <li>Vous recevrez une notification dès qu'elle sera traitée</li>
                </ul>
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="type_conge">Type de congé *</label>
                        <select id="type_conge" name="type_conge" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="CP">Congé Payé</option>
                            <option value="RTT">RTT</option>
                            <option value="Maladie">Congé Maladie</option>
                            <option value="Sans solde">Sans Solde</option>
                            <option value="Formation">Formation</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_debut">Date de début *</label>
                        <input type="date" id="date_debut" name="date_debut" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="date_fin">Date de fin *</label>
                        <input type="date" id="date_fin" name="date_fin" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nombre de jours</label>
                        <input type="text" id="nb_jours_calc" value="0" readonly style="background: var(--glass); cursor: not-allowed;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="motif">Motif (optionnel)</label>
                    <textarea id="motif" name="motif" rows="3" placeholder="Précisez le motif de votre demande..."></textarea>
                </div>
                
                <button type="submit" name="demander_conge" class="btn btn-primary">
                    ✅ Envoyer la Demande
                </button>
            </form>
        </div>

        <!-- Mes demandes -->
        <div class="card">
            <h2>📋 Historique de Mes Demandes</h2>
            <?php if (empty($conges)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏖️</div>
                    <p>Aucune demande de congé.</p>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                        Vous n'avez pas encore fait de demande.
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($conges as $conge): ?>
                <div class="conge-card">
                    <div class="conge-header">
                        <div>
                            <strong style="color: var(--light); font-size: 1.1rem;">
                                <?php
                                $types = [
                                    'CP' => '🏖️ Congé Payé',
                                    'RTT' => '⏰ RTT',
                                    'Maladie' => '🤒 Maladie',
                                    'Sans solde' => '💼 Sans solde',
                                    'Formation' => '📚 Formation'
                                ];
                                echo $types[$conge['type']] ?? $conge['type'];
                                ?>
                            </strong>
                        </div>
                        <?php
                        $badges = [
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger'
                        ];
                        $labels = [
                            'pending' => '⏳ En attente',
                            'approved' => '✅ Approuvé',
                            'rejected' => '❌ Refusé'
                        ];
                        ?>
                        <span class="badge badge-<?= $badges[$conge['status']] ?>">
                            <?= $labels[$conge['status']] ?>
                        </span>
                    </div>
                    
                    <div class="conge-details">
                        <div>
                            <div class="detail-item">Période</div>
                            <div class="detail-value">
                                Du <?= date('d/m/Y', strtotime($conge['start_date'])) ?><br>
                                Au <?= date('d/m/Y', strtotime($conge['end_date'])) ?>
                            </div>
                        </div>
                        <div>
                            <div class="detail-item">Durée</div>
                            <div class="detail-value">
                                <strong><?= $conge['days_count'] ?></strong> jour(s)
                            </div>
                        </div>
                        <div>
                            <div class="detail-item">Demandé le</div>
                            <div class="detail-value">
                                <?= date('d/m/Y', strtotime($conge['created_at'])) ?>
                            </div>
                        </div>
                        <?php if ($conge['approved_at']): ?>
                        <div>
                            <div class="detail-item">Traité le</div>
                            <div class="detail-value">
                                <?= date('d/m/Y', strtotime($conge['approved_at'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($conge['reason']): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--glass-border);">
                        <div class="detail-item">Motif</div>
                        <div class="detail-value"><?= htmlspecialchars($conge['reason']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($conge['status'] === 'pending'): ?>
                    <div style="margin-top: 1rem; text-align: right;">
                        <a href="?annuler=1&id=<?= $conge['id'] ?>" 
                           class="btn btn-danger"
                           style="padding: 0.5rem 1rem; font-size: 0.9rem;"
                           onclick="return confirm('Annuler cette demande ?')">
                            🗑️ Annuler
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Informations complémentaires -->
        <div class="card">
            <h2>ℹ️ Informations</h2>
            <ul style="list-style: none; padding: 0; color: var(--light);">
                <li style="padding: 0.5rem 0;">📄 Consultez vos fiches de paie dans l'onglet dédié</li>
                <li style="padding: 0.5rem 0;">🔒 Vos données sont sécurisées et confidentielles</li>
                <li style="padding: 0.5rem 0;">📧 En cas de question, contactez le service RH à rh@adoo.com</li>
                <li style="padding: 0.5rem 0;">📞 Numéro d'urgence RH : 01 23 45 67 89</li>
            </ul>
        </div>
    </div>

    <script>
        // Calcul automatique du nombre de jours
        document.getElementById('date_debut').addEventListener('change', calculerJours);
        document.getElementById('date_fin').addEventListener('change', calculerJours);

        function calculerJours() {
            const debut = new Date(document.getElementById('date_debut').value);
            const fin = new Date(document.getElementById('date_fin').value);
            
            if (debut && fin && fin >= debut) {
                const diff = Math.floor((fin - debut) / (1000 * 60 * 60 * 24)) + 1;
                document.getElementById('nb_jours_calc').value = diff + ' jour(s)';
            } else {
                document.getElementById('nb_jours_calc').value = '0';
            }
        }
    </script>
</body>
</html>