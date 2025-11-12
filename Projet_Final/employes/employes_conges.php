<?php
session_start();
require_once '../include/db.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employe') {
    header('Location: ../login.php');
    exit();
}

$employe_id = $_SESSION['employe_id'];
$error = null;
$success = null;

// Récupérer les infos de l'employé
$stmt = $pdo->prepare("SELECT * FROM personnel WHERE id = ?");
$stmt->execute([$employe_id]);
$employe = $stmt->fetch(PDO::FETCH_ASSOC);

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
        
        // Insérer la demande
        $stmt = $pdo->prepare("
            INSERT INTO conges (employe_id, type_conge, date_debut, date_fin, nb_jours, motif, statut, date_demande) 
            VALUES (?, ?, ?, ?, ?, ?, 'en_attente', NOW())
        ");
        $stmt->execute([$employe_id, $type_conge, $date_debut, $date_fin, $nb_jours, $motif]);
        
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
        $stmt = $pdo->prepare("
            SELECT * FROM conges 
            WHERE id = ? AND employe_id = ? AND statut = 'en_attente'
        ");
        $stmt->execute([$conge_id, $employe_id]);
        $conge = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conge) {
            throw new Exception("Impossible d'annuler cette demande");
        }
        
        $stmt = $pdo->prepare("DELETE FROM conges WHERE id = ?");
        $stmt->execute([$conge_id]);
        
        $success = "Demande annulée avec succès";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer toutes les demandes de congés de l'employé
$mes_conges = $pdo->prepare("
    SELECT * FROM conges 
    WHERE employe_id = ?
    ORDER BY date_demande DESC
");
$mes_conges->execute([$employe_id]);
$conges = $mes_conges->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total' => count($conges),
    'en_attente' => 0,
    'approuve' => 0,
    'refuse' => 0,
    'jours_pris' => 0
];

$annee_courante = date('Y');
foreach ($conges as $conge) {
    $stats[$conge['statut']]++;
    if ($conge['statut'] === 'approuve' && date('Y', strtotime($conge['date_debut'])) == $annee_courante) {
        $stats['jours_pris'] += $conge['nb_jours'];
    }
}

$mois_fr = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

require_once '../include/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🏖️ Mes Demandes de Congés</h2>
        <a href="dashboard.php" class="btn btn-secondary">← Retour</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h5>Demandes Totales</h5>
                    <h3><?= $stats['total'] ?></h3>
                    <small>Toutes les demandes</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h5>En Attente</h5>
                    <h3><?= $stats['en_attente'] ?></h3>
                    <small>À traiter</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h5>Approuvées</h5>
                    <h3><?= $stats['approuve'] ?></h3>
                    <small>Congés validés</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h5>Jours Pris (<?= $annee_courante ?>)</h5>
                    <h3><?= $stats['jours_pris'] ?></h3>
                    <small>Sur l'année en cours</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de demande -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5>➕ Nouvelle Demande de Congé</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="type_conge">Type de congé *</label>
                            <select class="form-control" id="type_conge" name="type_conge" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="conge_paye">Congé payé</option>
                                <option value="conge_maladie">Congé maladie</option>
                                <option value="conge_sans_solde">Congé sans solde</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date_debut">Date de début *</label>
                            <input type="date" class="form-control" id="date_debut" 
                                   name="date_debut" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="date_fin">Date de fin *</label>
                            <input type="date" class="form-control" id="date_fin" 
                                   name="date_fin" min="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nombre de jours</label>
                            <input type="text" class="form-control" id="nb_jours_calc" 
                                   value="0" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="motif">Motif (optionnel)</label>
                    <textarea class="form-control" id="motif" name="motif" rows="3"
                              placeholder="Précisez le motif de votre demande..."></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>ℹ️ À savoir :</strong>
                    <ul class="mb-0">
                        <li>Votre demande sera examinée par votre responsable</li>
                        <li>Vous recevrez une notification dès qu'elle sera traitée</li>
                        <li>Les congés payés doivent être demandés au moins 7 jours à l'avance</li>
                    </ul>
                </div>
                
                <button type="submit" name="demander_conge" class="btn btn-primary btn-lg">
                    Envoyer la Demande
                </button>
            </form>
        </div>
    </div>

    <!-- Mes demandes -->
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5>📋 Historique de Mes Demandes</h5>
        </div>
        <div class="card-body">
            <?php if (empty($conges)): ?>
                <div class="alert alert-info">
                    <strong>Aucune demande de congé</strong>
                    <p class="mb-0">Vous n'avez pas encore fait de demande de congé.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Type</th>
                            <th>Période</th>
                            <th>Durée</th>
                            <th>Date de Demande</th>
                            <th>Statut</th>
                            <th>Motif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conges as $conge): ?>
                        <tr>
                            <td>
                                <?php
                                $types = [
                                    'conge_paye' => '🏖️ Congé payé',
                                    'conge_maladie' => '🤒 Maladie',
                                    'conge_sans_solde' => '💼 Sans solde',
                                    'autre' => '📝 Autre'
                                ];
                                echo $types[$conge['type_conge']] ?? $conge['type_conge'];
                                ?>
                            </td>
                            <td>
                                <strong>Du</strong> <?= date('d/m/Y', strtotime($conge['date_debut'])) ?><br>
                                <strong>Au</strong> <?= date('d/m/Y', strtotime($conge['date_fin'])) ?>
                            </td>
                            <td>
                                <strong><?= $conge['nb_jours'] ?></strong> jour(s)
                            </td>
                            <td>
                                <small><?= date('d/m/Y à H:i', strtotime($conge['date_demande'])) ?></small>
                            </td>
                            <td>
                                <?php
                                $badges = [
                                    'en_attente' => 'warning',
                                    'approuve' => 'success',
                                    'refuse' => 'danger'
                                ];
                                $labels = [
                                    'en_attente' => '⏳ En attente',
                                    'approuve' => '✅ Approuvé',
                                    'refuse' => '❌ Refusé'
                                ];
                                ?>
                                <span class="badge badge-<?= $badges[$conge['statut']] ?>">
                                    <?= $labels[$conge['statut']] ?>
                                </span>
                                <?php if ($conge['date_traitement']): ?>
                                    <br><small class="text-muted">
                                        le <?= date('d/m/Y', strtotime($conge['date_traitement'])) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($conge['motif'] ?: '-') ?></small>
                            </td>
                            <td>
                                <?php if ($conge['statut'] === 'en_attente'): ?>
                                    <a href="?annuler=1&id=<?= $conge['id'] ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Annuler cette demande ?')">
                                        Annuler
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Informations complémentaires -->
    <div class="card mt-4">
        <div class="card-body">
            <h6>📌 Règles et Informations :</h6>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-primary">Congés Payés</h6>
                    <ul>
                        <li>Droit à 25 jours par an (5 semaines)</li>
                        <li>À poser au moins 7 jours à l'avance</li>
                        <li>Maximum 2 semaines consécutives en été</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary">Congés Maladie</h6>
                    <ul>
                        <li>Justificatif médical obligatoire sous 48h</li>
                        <li>À déclarer dès le premier jour</li>
                        <li>Délai de carence de 3 jours</li>
                    </ul>
                </div>
            </div>
        </div>
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

<?php require_once '../include/footer.php'; ?>