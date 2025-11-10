<?php
// modules/ventes/nouveau_client.php - Formulaire de création d'un nouveau client

require_once __DIR__ . '/../../include/db.php';

// Vérifier que c'est bien un admin/employé
if (!in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'employee'])) {
    header('Location: index.php?module=ventes');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();
        
        // Validation des données
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $type = $_POST['type'] ?? 'Regular';
        
        // Vérifications
        if (empty($first_name) || empty($last_name) || empty($email)) {
            throw new Exception("Les champs Prénom, Nom et Email sont obligatoires");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Adresse email invalide");
        }
        
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Un client avec cet email existe déjà");
        }
        
        // Insérer le nouveau client
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                first_name, 
                last_name, 
                email, 
                phone, 
                address, 
                type,
                active,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone ?: null,
            $address ?: null,
            $type
        ]);
        
        $new_client_id = $pdo->lastInsertId();
        
        $_SESSION['success'] = "✓ Client créé avec succès : " . $first_name . " " . $last_name;
        
        // Rediriger selon l'action souhaitée
        if (isset($_POST['action']) && $_POST['action'] === 'create_and_sell') {
            header("Location: index.php?module=ventes&action=panier&client_id=$new_client_id");
        } else {
            header("Location: index.php?module=ventes&action=clients");
        }
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div id="nouveau-client-page">
    
    <!-- En-tête -->
    <div style="margin-bottom: 2rem;">
        <h2 style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 2rem;">👤</span>
            Nouveau Client
        </h2>
        <p style="color: var(--secondary); font-size: 1rem; margin-top: 0.5rem;">
            Créez un nouveau client pour effectuer une vente
        </p>
    </div>
    
    <!-- Message d'erreur -->
    <?php if (isset($error)): ?>
        <div class="alert alert-error" style="margin-bottom: 1.5rem;">
            ✗ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <!-- Formulaire -->
    <div class="card" style="max-width: 800px; margin: 0 auto; padding: 2rem;">
        <form method="POST" id="clientForm" onsubmit="return validateClientForm()">
            
            <!-- Informations personnelles -->
            <div style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
                    <span>📋</span> Informations personnelles
                </h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="first_name">Prénom <span style="color: #ff4757;">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="first_name" 
                               name="first_name" 
                               placeholder="Jean"
                               required
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Nom <span style="color: #ff4757;">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="last_name" 
                               name="last_name" 
                               placeholder="Dupont"
                               required
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email <span style="color: #ff4757;">*</span></label>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           placeholder="jean.dupont@example.com"
                           required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Téléphone</label>
                    <input type="tel" 
                           class="form-control" 
                           id="phone" 
                           name="phone" 
                           placeholder="06 12 34 56 78"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    <small style="color: var(--secondary); font-size: 0.85rem; margin-top: 0.25rem; display: block;">
                        Format: 06 12 34 56 78 ou +33 6 12 34 56 78
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="address">Adresse complète</label>
                    <textarea class="form-control" 
                              id="address" 
                              name="address" 
                              rows="3" 
                              placeholder="123 Rue de la Paix, 75001 Paris"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <!-- Type de client -->
            <div style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
                    <span>⭐</span> Type de client
                </h3>
                
                <div class="form-group">
                    <label for="type">Catégorie</label>
                    <select class="form-control" id="type" name="type">
                        <option value="Regular" <?php echo ($_POST['type'] ?? '') === 'Regular' ? 'selected' : ''; ?>>
                            👤 Régulier - Client standard
                        </option>
                        <option value="VIP" <?php echo ($_POST['type'] ?? '') === 'VIP' ? 'selected' : ''; ?>>
                            ⭐ VIP - Avantages exclusifs et remises
                        </option>
                        <option value="Pro" <?php echo ($_POST['type'] ?? '') === 'Pro' ? 'selected' : ''; ?>>
                            💼 Professionnel - Facturation entreprise
                        </option>
                    </select>
                    <small style="color: var(--secondary); font-size: 0.85rem; margin-top: 0.5rem; display: block;">
                        💡 Les clients VIP bénéficient de remises et avantages spéciaux
                    </small>
                </div>
            </div>
            
            <!-- Actions -->
            <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1.5rem; border-top: 2px solid var(--glass); flex-wrap: wrap;">
                <button type="button" 
                        class="btn btn-secondary" 
                        onclick="window.location.href='index.php?module=ventes&action=select_client'">
                    ← Annuler
                </button>
                
                <button type="submit" 
                        name="action" 
                        value="create_only" 
                        class="btn btn-secondary">
                    💾 Créer uniquement
                </button>
                
                <button type="submit" 
                        name="action" 
                        value="create_and_sell" 
                        class="btn btn-primary">
                    ✅ Créer et vendre
                </button>
            </div>
            
            <div style="margin-top: 1rem; text-align: center; color: var(--secondary); font-size: 0.85rem;">
                <span style="color: #ff4757;">*</span> Champs obligatoires
            </div>
            
        </form>
    </div>
    
</div>

<style>
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--light);
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    background: var(--glass);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: var(--light);
    font-size: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
}

.form-control.error {
    border-color: #ff4757;
}

.form-control::placeholder {
    color: var(--secondary);
    opacity: 0.6;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-error {
    background: rgba(255, 71, 87, 0.2);
    border-color: #ff4757;
    color: #ff4757;
}
</style>

<script>
function validateClientForm() {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    
    // Réinitialiser les erreurs
    document.querySelectorAll('.form-control').forEach(el => el.classList.remove('error'));
    
    let errors = [];
    
    // Validation des champs obligatoires
    if (!firstName) {
        document.getElementById('first_name').classList.add('error');
        errors.push('Le prénom est obligatoire');
    }
    
    if (!lastName) {
        document.getElementById('last_name').classList.add('error');
        errors.push('Le nom est obligatoire');
    }
    
    if (!email) {
        document.getElementById('email').classList.add('error');
        errors.push('L\'email est obligatoire');
    } else {
        // Validation email
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            document.getElementById('email').classList.add('error');
            errors.push('L\'adresse email est invalide');
        }
    }
    
    // Validation téléphone (optionnel mais doit être valide si rempli)
    if (phone) {
        const phoneRegex = /^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;
        if (!phoneRegex.test(phone)) {
            document.getElementById('phone').classList.add('error');
            errors.push('Le numéro de téléphone est invalide');
        }
    }
    
    if (errors.length > 0) {
        alert('⚠️ Erreurs de validation :\n\n' + errors.join('\n'));
        return false;
    }
    
    return true;
}

// Focus automatique sur le premier champ
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('first_name')?.focus();
});

// Prévisualisation du nom complet
document.getElementById('first_name')?.addEventListener('input', updatePreview);
document.getElementById('last_name')?.addEventListener('input', updatePreview);

function updatePreview() {
    const firstName = document.getElementById('first_name').value;
    const lastName = document.getElementById('last_name').value;
    
    if (firstName || lastName) {
        console.log('Client:', firstName, lastName);
    }
}
</script>