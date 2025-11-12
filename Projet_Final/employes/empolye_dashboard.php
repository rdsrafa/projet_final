<?php
session_start();
require_once '../include/db.php';

// Vérifier que l'utilisateur est connecté et est un employé
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employe') {
    header('Location: ../login.php');
    exit();
}

$employe_id = $_SESSION['employe_id'];

// Récupérer les infos de l'employé
$stmt = $pdo->prepare("SELECT * FROM personnel WHERE id = ?");
$stmt->execute([$employe_id]);
$employe = $stmt->fetch(PDO::FETCH_ASSOC);

// Statistiques du jour
$today = date('Y-m-d');

// Ventes du jour
$stmt = $pdo->query("
    SELECT COUNT(*) as nb_ventes, COALESCE(SUM(montant_total), 0) as total_jour
    FROM ventes 
    WHERE DATE(date_vente) = '$today' AND statut = 'validee'
");
$stats_jour = $stmt->fetch(PDO::FETCH_ASSOC);

// Produits en rupture de stock
$stmt = $pdo->query("
    SELECT COUNT(*) as nb_rupture
    FROM stocks s
    JOIN produits p ON s.produit_id = p.id
    WHERE s.quantite <= 5
");
$stats_stock = $stmt->fetch(PDO::FETCH_ASSOC);

// Dernières ventes
$dernieres_ventes = $pdo->query("
    SELECT v.*, c.nom, c.prenom
    FROM ventes v
    JOIN clients c ON v.client_id = c.id
    ORDER BY v.date_vente DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

require_once '../include/header.php';
?>

<div class="container-fluid mt-4">
    <!-- En-tête de bienvenue -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h2>👋 Bienvenue, <?= htmlspecialchars($employe['prenom']) ?> !</h2>
                    <p class="mb-0">
                        <strong>Poste :</strong> <?= htmlspecialchars($employe['poste']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Ventes du jour</h5>
                    <h2><?= $stats_jour['nb_ventes'] ?></h2>
                    <p class="mb-0">Total : <?= number_format($stats_jour['total_jour'], 2) ?> €</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Stock faible</h5>
                    <h2><?= $stats_stock['nb_rupture'] ?></h2>
                    <p class="mb-0">Produits à réapprovisionner</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Mon salaire</h5>
                    <h2><?= number_format($employe['salaire'], 2) ?> €</h2>
                    <p class="mb-0">Salaire mensuel</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h5>Statut</h5>
                    <h2><?= ucfirst($employe['statut']) ?></h2>
                    <p class="mb-0">Depuis <?= date('d/m/Y', strtotime($employe['date_embauche'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Accès rapides -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5>🚀 Accès Rapides</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <a href="../modules/ventes/ventes.php" class="btn btn-lg btn-success btn-block" style="height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                <h3>🛒</h3>
                                <h5>Nouvelle Vente</h5>
                                <small>Créer une vente client</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="stock.php" class="btn btn-lg btn-info btn-block" style="height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                <h3>📦</h3>
                                <h5>Consulter Stock</h5>
                                <small>Voir les produits disponibles</small>
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="mes_fiches_paie.php" class="btn btn-lg btn-primary btn-block" style="height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                <h3>💰</h3>
                                <h5>Mes Fiches de Paie</h5>
                                <small>Consulter mes bulletins</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernières ventes -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5>📊 Dernières Ventes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($dernieres_ventes)): ?>
                        <p class="text-muted">Aucune vente enregistrée aujourd'hui</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>N° Vente</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieres_ventes as $vente): ?>
                                <tr>
                                    <td><strong>#<?= $vente['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($vente['nom'] . ' ' . $vente['prenom']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($vente['date_vente'])) ?></td>
                                    <td><strong><?= number_format($vente['montant_total'], 2) ?> €</strong></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'en_attente' => 'warning',
                                            'validee' => 'success',
                                            'annulee' => 'danger'
                                        ];
                                        $labels = [
                                            'en_attente' => 'En attente',
                                            'validee' => 'Validée',
                                            'annulee' => 'Annulée'
                                        ];
                                        ?>
                                        <span class="badge badge-<?= $badges[$vente['statut']] ?>">
                                            <?= $labels[$vente['statut']] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../include/footer.php'; ?>