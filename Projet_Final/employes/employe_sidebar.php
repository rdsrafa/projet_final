<!-- Sidebar pour les Employés -->
<nav class="col-md-2 d-none d-md-block bg-dark sidebar" style="min-height: 100vh;">
    <div class="sidebar-sticky">
        <div class="text-center py-4 border-bottom border-secondary">
            <h5 class="text-white">👤 Espace Employé</h5>
            <small class="text-muted">
                <?= htmlspecialchars($_SESSION['employe_nom'] ?? 'Employé') ?>
            </small>
            <br>
            <small class="badge badge-primary mt-1">
                <?= htmlspecialchars($_SESSION['employe_poste'] ?? '') ?>
            </small>
        </div>
        
        <ul class="nav flex-column mt-3">
            <!-- Dashboard -->
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active bg-primary' : '' ?>" 
                   href="/Projet_Final/employe/dashboard.php">
                    <span class="mr-2">🏠</span> Tableau de Bord
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-item">
                <hr class="bg-secondary">
                <h6 class="sidebar-heading text-muted px-3 mt-3 mb-1 text-uppercase">
                    <small>Ventes</small>
                </h6>
            </li>
            
            <!-- Nouvelle Vente -->
            <li class="nav-item">
                <a class="nav-link text-white" 
                   href="/Projet_Final/modules/ventes/ventes.php">
                    <span class="mr-2">🛒</span> Nouvelle Vente
                </a>
            </li>
            
            <!-- Clients -->
            <li class="nav-item">
                <a class="nav-link text-white" 
                   href="/Projet_Final/modules/ventes/clients.php">
                    <span class="mr-2">👥</span> Clients
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-item">
                <hr class="bg-secondary">
                <h6 class="sidebar-heading text-muted px-3 mt-3 mb-1 text-uppercase">
                    <small>Stock</small>
                </h6>
            </li>
            
            <!-- Consultation Stock -->
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'stock.php' ? 'active bg-primary' : '' ?>" 
                   href="/Projet_Final/employe/stock.php">
                    <span class="mr-2">📦</span> Consulter le Stock
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-item">
                <hr class="bg-secondary">
                <h6 class="sidebar-heading text-muted px-3 mt-3 mb-1 text-uppercase">
                    <small>Mon Espace</small>
                </h6>
            </li>
            
            <!-- Mes Fiches de Paie -->
            <li class="nav-item">
                <a class="nav-link text-white <?= basename($_SERVER['PHP_SELF']) == 'mes_fiches_paie.php' ? 'active bg-primary' : '' ?>" 
                   href="/Projet_Final/employe/mes_fiches_paie.php">
                    <span class="mr-2">💰</span> Mes Fiches de Paie
                </a>
            </li>
            
            <!-- Mes Congés (optionnel) -->
            <li class="nav-item">
                <a class="nav-link text-white" 
                   href="/Projet_Final/employe/mes_conges.php">
                    <span class="mr-2">🏖️</span> Mes Congés
                </a>
            </li>
            
            <!-- Divider -->
            <li class="nav-item">
                <hr class="bg-secondary">
            </li>
            
            <!-- Déconnexion -->
            <li class="nav-item">
                <a class="nav-link text-danger" 
                   href="/Projet_Final/logout.php"
                   onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">
                    <span class="mr-2">🚪</span> Déconnexion
                </a>
            </li>
        </ul>
        
        <!-- Informations rapides -->
        <div class="mt-auto p-3 border-top border-secondary" style="position: absolute; bottom: 0; width: 100%;">
            <small class="text-muted">
                <div class="mb-2">
                    <strong class="text-white">Heure :</strong>
                    <span id="current-time" class="text-white"></span>
                </div>
                <div>
                    <strong class="text-white">Date :</strong>
                    <span class="text-white"><?= date('d/m/Y') ?></span>
                </div>
            </small>
        </div>
    </div>
</nav>

<style>
.sidebar {
    position: fixed;
    top: 56px;
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 56px);
    padding-top: 0;
    overflow-x: hidden;
    overflow-y: auto;
}

.nav-link {
    font-weight: 500;
    color: #fff;
    padding: 0.75rem 1rem;
    transition: all 0.3s;
}

.nav-link:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: #fff !important;
}

.nav-link.active {
    background-color: #007bff;
    color: #fff;
    border-radius: 5px;
    margin: 0 10px;
}

.sidebar-heading {
    font-size: .75rem;
    text-transform: uppercase;
}
</style>

<script>
// Mettre à jour l'heure en temps réel
function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('fr-FR');
    document.getElementById('current-time').textContent = timeStr;
}
updateTime();
setInterval(updateTime, 1000);
</script>