<?php
// modules/ventes/clients.php - Liste et gestion des clients

require_once __DIR__ . '/../../include/db.php';

try {
    $pdo = getDB();
    
    // Récupérer tous les clients avec leurs statistiques
    $stmt = $pdo->query("
        SELECT 
            c.*,
            COUNT(DISTINCT s.id) as total_orders,
            COALESCE(SUM(CASE WHEN s.status = 'completed' THEN s.total ELSE 0 END), 0) as total_spent
        FROM clients c
        LEFT JOIN sales s ON c.id = s.client_id
        WHERE c.active = 1
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculer les statistiques
    $stats = [
        'total' => count($clients),
        'vip' => count(array_filter($clients, fn($c) => $c['type'] === 'VIP')),
        'regular' => count(array_filter($clients, fn($c) => $c['type'] === 'Regular')),
        'pro' => count(array_filter($clients, fn($c) => $c['type'] === 'Pro')),
        'total_revenue' => array_sum(array_column($clients, 'total_spent'))
    ];
    
} catch (Exception $e) {
    error_log("Erreur clients : " . $e->getMessage());
    $clients = [];
    $stats = ['total' => 0, 'vip' => 0, 'regular' => 0, 'pro' => 0, 'total_revenue' => 0];
}
?>

<div id="clients-module">
    
    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            ✓ <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            ✗ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <!-- En-tête -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h3 style="font-size: 1.2rem; color: var(--light); margin-bottom: 0.5rem;">👥 Gestion des Clients</h3>
            <p style="color: var(--secondary); font-size: 0.9rem;">Liste et gestion de tous vos clients</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='index.php?module=ventes&action=nouveau_client'">
            ➕ Nouveau Client
        </button>
    </div>

    <!-- Statistiques rapides -->
    <div class="cards-grid" style="margin-bottom: 2rem;">
        <div class="card">
            <div class="card-label">Total Clients</div>
            <div class="card-value"><?php echo $stats['total']; ?></div>
        </div>
        <div class="card">
            <div class="card-label">Clients VIP</div>
            <div class="card-value" style="color: #f39c12;"><?php echo $stats['vip']; ?></div>
            <div style="margin-top: 0.5rem; color: var(--secondary); font-size: 0.85rem;">
                ⭐ Privilégiés
            </div>
        </div>
        <div class="card">
            <div class="card-label">Clients Pro</div>
            <div class="card-value" style="color: #4ecdc4;"><?php echo $stats['pro']; ?></div>
            <div style="margin-top: 0.5rem; color: var(--secondary); font-size: 0.85rem;">
                💼 Entreprises
            </div>
        </div>
        <div class="card">
            <div class="card-label">CA Total Clients</div>
            <div class="card-value"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> €</div>
        </div>
    </div>

    <?php if (empty($clients)): ?>
        <!-- Message si aucun client -->
        <div class="card" style="padding: 3rem; text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 1rem;">👥</div>
            <h3 style="color: var(--light); margin-bottom: 1rem;">Aucun client enregistré</h3>
            <p style="color: var(--secondary); margin-bottom: 2rem;">
                Commencez par ajouter votre premier client pour effectuer des ventes.
            </p>
            <button class="btn btn-primary" onclick="window.location.href='index.php?module=ventes&action=nouveau_client'">
                ➕ Créer le premier client
            </button>
        </div>
    <?php else: ?>

        <!-- Barre de recherche et filtres -->
        <div class="table-container" style="margin-bottom: 1rem; padding: 1rem;">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                <div style="flex: 1; min-width: 250px;">
                    <input type="text" 
                           class="form-control" 
                           id="searchInput" 
                           placeholder="🔍 Rechercher un client par nom, email, téléphone..." 
                           onkeyup="filterClients()">
                </div>
                <select class="form-control" id="typeFilter" style="width: auto; min-width: 150px;" onchange="filterClients()">
                    <option value="">Tous les types</option>
                    <option value="Regular">👤 Réguliers</option>
                    <option value="VIP">⭐ VIP</option>
                    <option value="Pro">💼 Pro</option>
                </select>
                <button class="btn btn-secondary btn-sm" onclick="exportClients()">
                    📥 Exporter CSV
                </button>
            </div>
        </div>

        <!-- Tableau des clients -->
        <div class="table-container">
            <table id="clientsTable">
                <thead>
                    <tr>
                        <th onclick="sortTable(0)">Client ↕</th>
                        <th onclick="sortTable(1)">Email ↕</th>
                        <th onclick="sortTable(2)">Téléphone ↕</th>
                        <th onclick="sortTable(3)">Type ↕</th>
                        <th onclick="sortTable(4)">Commandes ↕</th>
                        <th onclick="sortTable(5)">Total dépensé ↕</th>
                        <th onclick="sortTable(6)">Membre depuis ↕</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr class="client-row" data-type="<?php echo $client['type']; ?>" data-client-id="<?php echo $client['id']; ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div class="avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <?php echo strtoupper(substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($client['email']); ?></td>
                        <td><?php echo htmlspecialchars($client['phone'] ?? 'Non renseigné'); ?></td>
                        <td>
                            <?php if ($client['type'] === 'VIP'): ?>
                                <span class="badge warning">⭐ VIP</span>
                            <?php elseif ($client['type'] === 'Pro'): ?>
                                <span class="badge info">💼 Pro</span>
                            <?php else: ?>
                                <span class="badge">👤 Régulier</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $client['total_orders']; ?></td>
                        <td><strong><?php echo number_format($client['total_spent'], 2, ',', ' ') . ' €'; ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-sm btn-secondary" 
                                        title="Voir profil"
                                        onclick="viewClient(<?php echo $client['id']; ?>)">
                                    👁️
                                </button>
                                <button class="btn btn-sm btn-primary" 
                                        title="Nouvelle vente"
                                        onclick="window.location.href='index.php?module=ventes&action=panier&client_id=<?php echo $client['id']; ?>'">
                                    🛒
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination (si nécessaire) -->
        <?php if (count($clients) > 20): ?>
        <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 2rem;">
            <button class="btn btn-secondary btn-sm">← Précédent</button>
            <span style="color: var(--secondary);">Page 1 sur <?php echo ceil(count($clients) / 20); ?></span>
            <button class="btn btn-secondary btn-sm">Suivant →</button>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.alert {
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success {
    background: rgba(46, 213, 115, 0.2);
    border-color: #2ed573;
    color: #2ed573;
}

.alert-error {
    background: rgba(255, 71, 87, 0.2);
    border-color: #ff4757;
    color: #ff4757;
}

.client-row {
    transition: all 0.3s ease;
}

.client-row:hover {
    background: rgba(108, 92, 231, 0.1);
}

table th {
    cursor: pointer;
    user-select: none;
}

table th:hover {
    background: rgba(108, 92, 231, 0.2);
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
let sortDirection = {};

function filterClients() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const typeFilter = document.getElementById('typeFilter').value;
    const rows = document.querySelectorAll('.client-row');
    
    let visibleCount = 0;
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const type = row.dataset.type;
        
        const matchesSearch = text.includes(searchTerm);
        const matchesType = !typeFilter || type === typeFilter;
        
        if (matchesSearch && matchesType) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Afficher un message si aucun résultat
    updateNoResultsMessage(visibleCount);
}

function updateNoResultsMessage(count) {
    const existingMessage = document.getElementById('noResultsMessage');
    if (existingMessage) existingMessage.remove();
    
    if (count === 0) {
        const table = document.getElementById('clientsTable');
        const message = document.createElement('div');
        message.id = 'noResultsMessage';
        message.style.cssText = 'text-align: center; padding: 2rem; color: var(--secondary);';
        message.innerHTML = `
            <div style="font-size: 2rem; margin-bottom: 1rem;">🔍</div>
            <p>Aucun client ne correspond à votre recherche</p>
        `;
        table.parentElement.appendChild(message);
    }
}

function sortTable(columnIndex) {
    const table = document.getElementById('clientsTable');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Déterminer la direction du tri
    sortDirection[columnIndex] = sortDirection[columnIndex] === 'asc' ? 'desc' : 'asc';
    const direction = sortDirection[columnIndex];
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Essayer de convertir en nombre
        const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return direction === 'asc' ? aNum - bNum : bNum - aNum;
        }
        
        return direction === 'asc' 
            ? aValue.localeCompare(bValue)
            : bValue.localeCompare(aValue);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

function viewClient(clientId) {
    // Rediriger vers une page de détails du client (à créer)
    alert('Fonctionnalité en développement : Profil client #' + clientId);
}

function exportClients() {
    const rows = document.querySelectorAll('.client-row');
    let csv = 'Nom,Email,Téléphone,Type,Commandes,Total dépensé,Membre depuis\n';
    
    rows.forEach(row => {
        if (row.style.display !== 'none') {
            const cells = row.querySelectorAll('td');
            const data = [
                cells[0].textContent.trim().replace(/\s+/g, ' '),
                cells[1].textContent.trim(),
                cells[2].textContent.trim(),
                cells[3].textContent.trim(),
                cells[4].textContent.trim(),
                cells[5].textContent.trim(),
                cells[6].textContent.trim()
            ];
            csv += data.join(',') + '\n';
        }
    });
    
    // Télécharger le fichier
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'clients_' + new Date().toISOString().split('T')[0] + '.csv';
    link.click();
}
</script>