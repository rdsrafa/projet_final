<?php
/**
 * modules/admin/suppliers.php
 * Module de gestion des fournisseurs pour l'admin
 * S'intègre dans index.php
 */

// Ce fichier est appelé depuis index.php, la session est déjà vérifiée

$success = '';
$error = '';

// Traitement des actions (approuver/refuser/désactiver)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $supplier_id = $_POST['supplier_id'] ?? 0;
    
    try {
        $db = getDB();
        
        switch($action) {
            case 'approve':
                // Récupérer le user_id du fournisseur
                $stmt = $db->prepare("SELECT user_id FROM suppliers WHERE id = ?");
                $stmt->execute([$supplier_id]);
                $user_id = $stmt->fetchColumn();
                
                // Approuver le fournisseur ET activer son compte utilisateur
                $stmt = $db->prepare("UPDATE suppliers SET status = 'approved' WHERE id = ?");
                $stmt->execute([$supplier_id]);
                
                $stmt = $db->prepare("UPDATE users SET active = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                
                $success = "✅ Fournisseur approuvé avec succès ! Il peut maintenant se connecter.";
                break;
                
            case 'reject':
                $stmt = $db->prepare("UPDATE suppliers SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$supplier_id]);
                $success = "❌ Fournisseur refusé.";
                break;
                
            case 'deactivate':
                $stmt = $db->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$supplier_id]);
                $success = "🚫 Fournisseur désactivé.";
                break;
                
            case 'reactivate':
                $stmt = $db->prepare("UPDATE suppliers SET status = 'approved', approved_at = NOW() WHERE id = ?");
                $stmt->execute([$supplier_id]);
                $success = "♻️ Fournisseur réactivé.";
                break;
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer tous les fournisseurs
try {
    $db = getDB();
    
    // Fournisseurs en attente
    $stmt_pending = $db->query("
        SELECT s.*, u.email, u.created_at as user_created_at
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'pending'
        ORDER BY u.created_at DESC
    ");
    $pending_suppliers = $stmt_pending->fetchAll();
    
    // Fournisseurs approuvés
    $stmt_approved = $db->query("
        SELECT s.*, u.email, u.last_login
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'approved'
        ORDER BY s.name ASC
    ");
    $approved_suppliers = $stmt_approved->fetchAll();
    
    // Fournisseurs inactifs
    $stmt_inactive = $db->query("
        SELECT s.*, u.email
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        WHERE s.status IN ('rejected', 'inactive')
        ORDER BY s.name ASC
    ");
    $inactive_suppliers = $stmt_inactive->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur de récupération : " . $e->getMessage();
}
?>

<style>
    .suppliers-section {
        padding: 1rem 0;
    }

    .alert-message {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        animation: slideIn 0.3s ease;
    }

    .alert-success {
        background: rgba(78, 205, 196, 0.2);
        border: 1px solid var(--secondary);
        color: var(--secondary);
    }

    .alert-error {
        background: rgba(255, 107, 107, 0.2);
        border: 1px solid var(--primary);
        color: var(--primary);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-box {
        background: var(--glass);
        padding: 1.5rem;
        border-radius: 15px;
        border: 1px solid var(--glass-border);
        text-align: center;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .stat-label {
        color: var(--secondary);
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    .tabs-container {
        margin-bottom: 2rem;
    }

    .tabs-nav {
        display: flex;
        gap: 1rem;
        border-bottom: 2px solid var(--glass-border);
        margin-bottom: 2rem;
    }

    .tab-btn {
        padding: 1rem 1.5rem;
        background: transparent;
        border: none;
        color: var(--light);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        position: relative;
        opacity: 0.6;
        transition: all 0.3s ease;
    }

    .tab-btn.active {
        opacity: 1;
        color: var(--secondary);
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(45deg, var(--primary), var(--secondary));
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .supplier-card {
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .supplier-card:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
    }

    .supplier-card.pending {
        border-left: 4px solid #FFE66D;
    }

    .supplier-card.approved {
        border-left: 4px solid var(--secondary);
    }

    .supplier-card.inactive {
        border-left: 4px solid var(--primary);
        opacity: 0.7;
    }

    .supplier-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .supplier-name {
        font-size: 1.3rem;
        color: var(--light);
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .supplier-email {
        color: var(--secondary);
        font-size: 0.9rem;
    }

    .supplier-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 10px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

    .info-label {
        font-size: 0.8rem;
        color: var(--secondary);
        opacity: 0.8;
    }

    .info-value {
        color: var(--light);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .supplier-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }

    .btn-action {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .btn-approve {
        background: linear-gradient(45deg, #4ECDC4, #44A08D);
        color: white;
    }

    .btn-approve:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(78, 205, 196, 0.4);
    }

    .btn-reject {
        background: linear-gradient(45deg, #FF6B6B, #EE5A6F);
        color: white;
    }

    .btn-reject:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
    }

    .btn-deactivate {
        background: rgba(255, 230, 109, 0.2);
        color: #FFE66D;
        border: 1px solid #FFE66D;
    }

    .btn-reactivate {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
        border: 1px solid var(--secondary);
    }

    .badge-status {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .badge-pending {
        background: rgba(255, 230, 109, 0.2);
        color: #FFE66D;
    }

    .badge-approved {
        background: rgba(78, 205, 196, 0.2);
        color: var(--secondary);
    }

    .badge-rejected {
        background: rgba(255, 107, 107, 0.2);
        color: var(--primary);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--secondary);
        opacity: 0.7;
    }

    .empty-state h3 {
        color: var(--light);
        margin-bottom: 0.5rem;
    }
</style>

<div class="suppliers-section">
    
    <?php if ($success): ?>
    <div class="alert-message alert-success">
        <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert-message alert-error">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo count($pending_suppliers); ?></div>
            <div class="stat-label">⏳ En attente</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo count($approved_suppliers); ?></div>
            <div class="stat-label">✅ Approuvés</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo count($inactive_suppliers); ?></div>
            <div class="stat-label">🚫 Inactifs</div>
        </div>
    </div>

    <!-- Onglets -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchSupplierTab('pending')">
                ⏳ En attente (<?php echo count($pending_suppliers); ?>)
            </button>
            <button class="tab-btn" onclick="switchSupplierTab('approved')">
                ✅ Approuvés (<?php echo count($approved_suppliers); ?>)
            </button>
            <button class="tab-btn" onclick="switchSupplierTab('inactive')">
                🚫 Inactifs (<?php echo count($inactive_suppliers); ?>)
            </button>
        </div>

        <!-- Contenu : Fournisseurs en attente -->
        <div id="pending-tab" class="tab-content active">
            <?php if (empty($pending_suppliers)): ?>
                <div class="empty-state">
                    <h3>✅ Aucune demande en attente</h3>
                    <p>Tous les fournisseurs ont été traités !</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_suppliers as $supplier): ?>
                    <div class="supplier-card pending">
                        <div class="supplier-header">
                            <div>
                                <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                <div class="supplier-email">📧 <?php echo htmlspecialchars($supplier['email']); ?></div>
                            </div>
                            <span class="badge-status badge-pending">⏳ En attente</span>
                        </div>

                        <div class="supplier-info-grid">
                            <div class="info-item">
                                <span class="info-label">📞 Téléphone</span>
                                <span class="info-value"><?php echo htmlspecialchars($supplier['phone'] ?? 'Non renseigné'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">🏢 Entreprise</span>
                                <span class="info-value"><?php echo htmlspecialchars($supplier['company_name'] ?? $supplier['name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">📅 Demande envoyée</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($supplier['user_created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="supplier-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn-action btn-approve" onclick="return confirm('Approuver ce fournisseur ?')">
                                    ✅ Approuver
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" class="btn-action btn-reject" onclick="return confirm('Refuser ce fournisseur ?')">
                                    ❌ Refuser
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Contenu : Fournisseurs approuvés -->
        <div id="approved-tab" class="tab-content">
            <?php if (empty($approved_suppliers)): ?>
                <div class="empty-state">
                    <h3>Aucun fournisseur approuvé</h3>
                </div>
            <?php else: ?>
                <?php foreach ($approved_suppliers as $supplier): ?>
                    <div class="supplier-card approved">
                        <div class="supplier-header">
                            <div>
                                <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                <div class="supplier-email">📧 <?php echo htmlspecialchars($supplier['email']); ?></div>
                            </div>
                            <span class="badge-status badge-approved">✅ Actif</span>
                        </div>

                        <div class="supplier-info-grid">
                            <div class="info-item">
                                <span class="info-label">📞 Téléphone</span>
                                <span class="info-value"><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">🕒 Dernière connexion</span>
                                <span class="info-value">
                                    <?php echo $supplier['last_login'] ? date('d/m/Y H:i', strtotime($supplier['last_login'])) : 'Jamais'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">✅ Approuvé le</span>
                                <span class="info-value">
                                    <?php echo $supplier['approved_at'] ? date('d/m/Y', strtotime($supplier['approved_at'])) : 'N/A'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="supplier-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <button type="submit" class="btn-action btn-deactivate" onclick="return confirm('Désactiver ce fournisseur ?')">
                                    🚫 Désactiver
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Contenu : Fournisseurs inactifs -->
        <div id="inactive-tab" class="tab-content">
            <?php if (empty($inactive_suppliers)): ?>
                <div class="empty-state">
                    <h3>Aucun fournisseur inactif</h3>
                </div>
            <?php else: ?>
                <?php foreach ($inactive_suppliers as $supplier): ?>
                    <div class="supplier-card inactive">
                        <div class="supplier-header">
                            <div>
                                <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                                <div class="supplier-email">📧 <?php echo htmlspecialchars($supplier['email']); ?></div>
                            </div>
                            <span class="badge-status badge-rejected">
                                <?php echo $supplier['status'] === 'rejected' ? '❌ Refusé' : '🚫 Inactif'; ?>
                            </span>
                        </div>

                        <div class="supplier-info-grid">
                            <div class="info-item">
                                <span class="info-label">📞 Téléphone</span>
                                <span class="info-value"><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <div class="supplier-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="supplier_id" value="<?php echo $supplier['id']; ?>">
                                <input type="hidden" name="action" value="reactivate">
                                <button type="submit" class="btn-action btn-reactivate" onclick="return confirm('Réactiver ce fournisseur ?')">
                                    ♻️ Réactiver
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function switchSupplierTab(tabName) {
    // Cacher tous les contenus
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Désactiver tous les boutons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Activer l'onglet sélectionné
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}
</script>