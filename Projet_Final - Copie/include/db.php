<?php
// include/db.php - Configuration et connexion à la base de données

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'base_de_donnee_si');
define('DB_USER', 'root');
define('DB_PASS', 'mysql');
define('DB_CHARSET', 'utf8mb4');

// Classe de gestion de la base de données
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            // En production, logger l'erreur au lieu de l'afficher
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Empêcher le clonage
    private function __clone() {}
    
    // Empêcher la désérialisation
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Fonction helper pour obtenir la connexion rapidement
function getDB() {
    return Database::getInstance()->getConnection();
}

// Fonction pour exécuter une requête SELECT
function query($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Fonction pour exécuter une requête INSERT/UPDATE/DELETE
function execute($sql, $params = []) {
    $db = getDB();
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

// Fonction pour obtenir le dernier ID inséré
function lastInsertId() {
    return getDB()->lastInsertId();
}

// Fonction pour sécuriser les données
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Fonction pour formater les dates
function formatDate($date, $format = 'd/m/Y') {
    return date($format, strtotime($date));
}

// Fonction pour formater les prix
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . '€';
}

// Données de démonstration (pour le prototype)
// En production, ces données seraient en base de données
$demo_data = [
    'brands' => [
        ['id' => 1, 'name' => 'Nike', 'color' => '#FF6B6B'],
        ['id' => 2, 'name' => 'Adidas', 'color' => '#4ECDC4'],
        ['id' => 3, 'name' => 'Jordan', 'color' => '#FFE66D'],
        ['id' => 4, 'name' => 'Yeezy', 'color' => '#6C5CE7'],
        ['id' => 5, 'name' => 'New Balance', 'color' => '#A8E6CF'],
    ],
    
    'products' => [
        [
            'id' => 1,
            'brand_id' => 1,
            'brand' => 'Nike',
            'model' => 'Air Jordan 1 Retro High',
            'colorway' => 'Chicago',
            'year' => 2024,
            'price' => 189.00,
            'cost' => 95.00,
            'sku' => 'NKE-AJ1-CHI-001',
            'image' => 'assets/img/aj1-chicago.jpg'
        ],
        [
            'id' => 2,
            'brand_id' => 2,
            'brand' => 'Adidas',
            'model' => 'Yeezy Boost 350 V2',
            'colorway' => 'Zebra',
            'year' => 2024,
            'price' => 210.00,
            'cost' => 110.00,
            'sku' => 'ADI-YZY-ZEB-002',
            'image' => 'assets/img/yeezy-350.jpg'
        ],
        [
            'id' => 3,
            'brand_id' => 1,
            'brand' => 'Nike',
            'model' => 'Dunk Low',
            'colorway' => 'Panda',
            'year' => 2024,
            'price' => 145.00,
            'cost' => 75.00,
            'sku' => 'NKE-DNK-PAN-003',
            'image' => 'assets/img/dunk-panda.jpg'
        ],
        [
            'id' => 4,
            'brand_id' => 1,
            'brand' => 'Nike',
            'model' => 'Air Force 1 \'07',
            'colorway' => 'White',
            'year' => 2024,
            'price' => 110.00,
            'cost' => 60.00,
            'sku' => 'NKE-AF1-WHT-004',
            'image' => 'assets/img/af1-white.jpg'
        ],
        [
            'id' => 5,
            'brand_id' => 3,
            'brand' => 'Jordan',
            'model' => 'Travis Scott Fragment',
            'colorway' => 'Military Blue',
            'year' => 2024,
            'price' => 1500.00,
            'cost' => 800.00,
            'sku' => 'JRD-TSF-MIL-005',
            'image' => 'assets/img/travis-scott.jpg'
        ],
    ],
    
    'stock' => [
        ['product_id' => 1, 'size' => 40, 'quantity' => 12],
        ['product_id' => 1, 'size' => 41, 'quantity' => 8],
        ['product_id' => 1, 'size' => 42, 'quantity' => 15],
        ['product_id' => 1, 'size' => 43, 'quantity' => 10],
        ['product_id' => 1, 'size' => 44, 'quantity' => 0],
        ['product_id' => 2, 'size' => 40, 'quantity' => 25],
        ['product_id' => 2, 'size' => 41, 'quantity' => 18],
        ['product_id' => 2, 'size' => 42, 'quantity' => 22],
        ['product_id' => 2, 'size' => 43, 'quantity' => 14],
        ['product_id' => 2, 'size' => 44, 'quantity' => 10],
        ['product_id' => 3, 'size' => 40, 'quantity' => 4],
        ['product_id' => 3, 'size' => 41, 'quantity' => 3],
        ['product_id' => 3, 'size' => 42, 'quantity' => 5],
        ['product_id' => 3, 'size' => 43, 'quantity' => 0],
    ],
    
    'clients' => [
        ['id' => 1, 'name' => 'Thomas Martin', 'email' => 'thomas.m@email.com', 'type' => 'VIP', 'total_spent' => 4500],
        ['id' => 2, 'name' => 'Sophie Durand', 'email' => 'sophie.d@email.com', 'type' => 'Regular', 'total_spent' => 890],
        ['id' => 3, 'name' => 'Lucas Bernard', 'email' => 'lucas.b@email.com', 'type' => 'VIP', 'total_spent' => 6200],
        ['id' => 4, 'name' => 'Emma Petit', 'email' => 'emma.p@email.com', 'type' => 'Regular', 'total_spent' => 450],
    ],
    
    'employees' => [
        ['id' => 1, 'name' => 'Marie Laurent', 'role' => 'Responsable Ventes', 'email' => 'marie.l@adoo.fr'],
        ['id' => 2, 'name' => 'Jean Perrin', 'role' => 'Vendeur', 'email' => 'jean.p@adoo.fr'],
        ['id' => 3, 'name' => 'Sophie Moreau', 'role' => 'Responsable Stock', 'email' => 'sophie.m@adoo.fr'],
        ['id' => 4, 'name' => 'Marc Dubois', 'role' => 'Responsable Achats', 'email' => 'marc.d@adoo.fr'],
    ]
];

// Fonction pour obtenir les données de démo
function getDemoData($type) {
    global $demo_data;
    return $demo_data[$type] ?? [];
}

?>