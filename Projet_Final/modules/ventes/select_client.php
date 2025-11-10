<?php
// modules/ventes/select_client.php - Sélection du client avant vente

require_once __DIR__ . '/../../include/db.php';

// Vérifier que c'est bien un admin/employé
if (!in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'employee'])) {
    header('Location: index.php?module=ventes&action=panier');
    exit;
}

try {
    $pdo = getDB();
    
    // Récupérer tous les clients actifs
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.first_name,
            c.last_name,
            c.email,
            c.phone,
            c.type,
            c.total_spent,
            COUNT(DISTINCT s.id) as total_orders
        FROM clients c
        LEFT JOIN sales s ON c.id = s.client_id AND s.status = 'completed'
        WHERE c.active = 1
        GROUP BY c.id
        ORDER BY c.type DESC, c.total_spent DESC, c.last_name ASC
    ");
    $clients = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Erreur select_client : " . $e->getMessage());
    $clients = [];
}
?>

<div id="select-client-page">
    
    <!-- En-tête -->
    <div style="margin-bottom: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 2rem;">🛒</span>
            Nouvelle Vente - Sélection du client
        </h2>
        <p style="color: var(--secondary); font-size: 1rem; margin-top: 0.5rem;">
            Choisissez le client pour qui vous effectuez cette vente
        </p>
    </div>
    
    <!-- Barre de recherche -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1rem;">
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <input type="text" 
                       class="form-control" 
                       id="searchInput" 
                       placeholder="🔍 Rechercher un client..." 
                       onkeyup="filterClients()"
                       autofocus>
            </div>
            <select class="form-control" id="typeFilter" style="width: auto; min-width: 150px;" onchange="filterClients()">
                <option value="">Tous les types</option>
                <option value="VIP">⭐ VIP</option>
                <option value="Pro">💼 Pro</option>
                <option value="Regular">👤 Réguliers</option>
            </select>
            <button class="btn btn-primary" onclick="window.location.href='index.php?module=ventes&action=nouveau_client'">
                ➕ Nouveau client
            </button>
        </div>
    </div>
    
    <?php if (empty($clients)): ?>
        <!-- Message si aucun client -->
        <div class="card" style="padding: 3rem; text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">👥</div>
            <h3 style="margin-bottom: 1rem;">Aucun client enregistré</h3>
            <p style="color: var(--secondary); margin-bottom: 2rem;">
                Vous devez d'abord créer un client avant de pouvoir effectuer une vente.
            </p>
            <button class="btn btn-primary" onclick="window.location.href='index.php?module=ventes&action=nouveau_client'">
                ➕ Créer un nouveau client
            </button>
        </div>
    <?php else: ?>
        <!-- Grille de clients -->
        <div class="clients-grid">
            <?php foreach ($clients as $client): ?>
            <div class="client-card" data-type="<?php echo $client['type']; ?>">
                 
                <!-- Avatar et infos -->
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                    <div class="avatar" style="width: 60px; height: 60px; font-size: 1.5rem;">
                        <?php echo strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)); ?>
                    </div>
                    <div style="flex: 1;">
                        <h4 style="margin: 0; color: var(--light); font-size: 1.1rem;">
                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                        </h4>
                        <p style="margin: 0.25rem 0 0 0; color: var(--secondary); font-size: 0.85rem;">
                            <?php echo htmlspecialchars($client['email']); ?>
                        </p>
                    </div>
                    
                    <!-- Badge type -->
                    <?php if ($client['type'] === 'VIP'): ?>
                        <span class="badge warning">⭐ VIP</span>
                    <?php elseif ($client['type'] === 'Pro'): ?>
                        <span class="badge info">💼 Pro</span>
                    <?php else: ?>
                        <span class="badge">👤</span>
                    <?php endif; ?>
                </div>
                
                <!-- Stats -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; padding: 0.75rem; background: var(--glass); border-radius: 8px; margin-bottom: 1rem;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--secondary); margin-bottom: 0.25rem;">
                            Téléphone
                        </div>
                        <div style="font-size: 0.9rem; font-weight: 600;">
                            <?php echo htmlspecialchars($client['phone'] ?? 'N/A'); ?>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--secondary); margin-bottom: 0.25rem;">
                            Commandes
                        </div>
                        <div style="font-size: 0.9rem; font-weight: 600;">
                            <?php echo $client['total_orders']; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Total dépensé -->
                <div style="text-align: center; padding: 0.75rem; background: rgba(108, 92, 231, 0.1); border-radius: 8px; margin-bottom: 1rem;">
                    <div style="font-size: 0.75rem; color: var(--secondary); margin-bottom: 0.25rem;">
                        Total dépensé
                    </div>
                    <div style="font-size: 1.3rem; font-weight: 700; color: var(--primary);">
                        <?php echo number_format($client['total_spent'], 2, ',', ' '); ?> €
                    </div>
                </div>
                
                <!-- Bouton de sélection -->
                <button class="btn btn-primary" 
                        style="width: 100%;" 
                        type="button"
                        onclick="selectClient(<?php echo $client['id']; ?>)">
                    ✅ Sélectionner ce client
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Bouton retour -->
    <div style="margin-top: 2rem; text-align: center;">
        <button class="btn btn-secondary" onclick="window.location.href='index.php?module=ventes'">
            ← Retour aux ventes
        </button>
    </div>
    
</div>

<style>
.clients-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.client-card {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 15px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.client-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 10px 30px rgba(108, 92, 231, 0.3);
}

.avatar {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    flex-shrink: 0;
}

.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    display: inline-block;
    background: rgba(255, 255, 255, 0.1);
    color: var(--light);
}

.badge.warning {
    background: rgba(255, 230, 109, 0.2);
    color: #f39c12;
}

.badge.info {
    background: rgba(78, 205, 196, 0.2);
    color: #4ecdc4;
}
</style>

<script>
function selectClient(clientId) {
    console.log('🎯 Client sélectionné:', clientId);
    window.location.href = 'index.php?module=ventes&action=panier&client_id=' + clientId;
}

function filterClients() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value;
    const cards = document.querySelectorAll('.client-card');
    
    let visibleCount = 0;
    
    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        const type = card.dataset.type;
        
        const matchesSearch = text.includes(searchTerm);
        const matchesType = !typeFilter || type === typeFilter;
        
        if (matchesSearch && matchesType) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Message si aucun résultat
    const noResults = document.getElementById('noResults');
    if (noResults) noResults.remove();
    
    if (visibleCount === 0) {
        const message = document.createElement('div');
        message.id = 'noResults';
        message.style.cssText = 'text-align: center; padding: 3rem; color: var(--secondary);';
        message.innerHTML = `
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
            <h3>Aucun client trouvé</h3>
            <p>Essayez de modifier vos critères de recherche</p>
        `;
        document.querySelector('.clients-grid').after(message);
    }
}
</script>