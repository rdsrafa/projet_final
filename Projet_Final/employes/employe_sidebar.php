<!-- Sidebar pour les Employés -->
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <h3>👟 ADOO Sneakers</h3>
        </div>
        <div class="user-badge">
            <div class="avatar">
                <?php echo strtoupper(substr($_SESSION['employe_nom'] ?? 'E', 0, 2)); ?>
            </div>
            <div class="user-details">
                <strong><?= htmlspecialchars($_SESSION['employe_nom'] ?? 'Employé') ?></strong>
                <small><?= htmlspecialchars($_SESSION['employe_poste'] ?? 'Vendeur') ?></small>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-section">
            <a href="index.php?module=employe&action=dashboard" 
               class="nav-item <?= ($module === 'employe' && $action === 'dashboard') ? 'active' : '' ?>">
                <span class="icon">🏠</span>
                <span class="label">Tableau de Bord</span>
            </a>
        </div>

        <!-- Ventes -->
        <div class="nav-section">
            <div class="nav-section-title">Ventes</div>
            
            <a href="index.php?module=employe&action=vente" 
               class="nav-item <?= ($module === 'employe' && $action === 'vente') ? 'active' : '' ?>">
                <span class="icon">🛒</span>
                <span class="label">Nouvelle Vente</span>
            </a>
            
            <a href="index.php?module=employe&action=ventes_list" 
               class="nav-item <?= ($module === 'employe' && $action === 'ventes_list') ? 'active' : '' ?>">
                <span class="icon">📋</span>
                <span class="label">Mes Ventes</span>
            </a>
            
            <a href="index.php?module=ventes&action=clients" 
               class="nav-item <?= ($module === 'ventes' && $action === 'clients') ? 'active' : '' ?>">
                <span class="icon">👥</span>
                <span class="label">Clients</span>
            </a>
        </div>

        <!-- Stock -->
        <div class="nav-section">
            <div class="nav-section-title">Stock</div>
            
            <a href="index.php?module=employe&action=stock" 
               class="nav-item <?= ($module === 'employe' && $action === 'stock') ? 'active' : '' ?>">
                <span class="icon">📦</span>
                <span class="label">Consulter Stock</span>
            </a>
        </div>

        <!-- Mon Espace -->
        <div class="nav-section">
            <div class="nav-section-title">Mon Espace</div>
            
            <a href="index.php?module=employe&action=paie" 
               class="nav-item <?= ($module === 'employe' && $action === 'paie') ? 'active' : '' ?>">
                <span class="icon">💰</span>
                <span class="label">Mes Fiches de Paie</span>
            </a>
            
            <a href="index.php?module=employe&action=conges" 
               class="nav-item <?= ($module === 'employe' && $action === 'conges') ? 'active' : '' ?>">
                <span class="icon">🏖️</span>
                <span class="label">Mes Congés</span>
            </a>
        </div>

        <!-- Déconnexion -->
        <div class="nav-section">
            <a href="logout.php" 
               class="nav-item nav-item-danger"
               onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">
                <span class="icon">🚪</span>
                <span class="label">Déconnexion</span>
            </a>
        </div>
    </nav>

    <!-- Footer Sidebar -->
    <div class="sidebar-footer">
        <div class="info-box">
            <div class="info-item">
                <strong>Heure :</strong>
                <span id="current-time">--:--:--</span>
            </div>
            <div class="info-item">
                <strong>Date :</strong>
                <span><?= date('d/m/Y') ?></span>
            </div>
        </div>
    </div>
</aside>

<style>
/* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(180deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 20px rgba(0,0,0,0.1);
    z-index: 1000;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.logo h3 {
    color: white;
    margin: 0;
    font-size: 1.3rem;
    text-align: center;
}

.user-badge {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
}

.user-badge .avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
}

.user-details {
    display: flex;
    flex-direction: column;
}

.user-details strong {
    font-size: 0.95rem;
}

.user-details small {
    color: rgba(255,255,255,0.7);
    font-size: 0.8rem;
}

.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    padding: 1rem 0;
}

.nav-section {
    margin-bottom: 1.5rem;
}

.nav-section-title {
    padding: 0.5rem 1.5rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.5);
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.nav-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.9rem 1.5rem;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.nav-item.active {
    background: rgba(255,255,255,0.15);
    color: white;
    border-left: 4px solid #667eea;
}

.nav-item.active::before {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-top: 10px solid transparent;
    border-bottom: 10px solid transparent;
    border-right: 10px solid white;
}

.nav-item .icon {
    font-size: 1.3rem;
    width: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.nav-item .label {
    font-weight: 500;
    font-size: 0.95rem;
}

.nav-item-danger {
    color: rgba(255, 107, 107, 0.9) !important;
}

.nav-item-danger:hover {
    background: rgba(255, 107, 107, 0.15) !important;
    color: #ff6b6b !important;
}

.sidebar-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.1);
}

.info-box {
    background: rgba(255,255,255,0.05);
    padding: 1rem;
    border-radius: 8px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.4rem 0;
    font-size: 0.85rem;
}

.info-item strong {
    color: rgba(255,255,255,0.7);
}

/* Scrollbar */
.sidebar-nav::-webkit-scrollbar {
    width: 6px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.05);
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 70px;
    }
    
    .user-badge,
    .nav-section-title,
    .nav-item .label,
    .sidebar-footer {
        display: none;
    }
    
    .logo h3 {
        font-size: 1.5rem;
    }
    
    .nav-item {
        justify-content: center;
    }
}
</style>

<script>
// Mettre à jour l'heure en temps réel
function updateTime() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        timeElement.textContent = `${hours}:${minutes}:${seconds}`;
    }
}

updateTime();
setInterval(updateTime, 1000);
</script>