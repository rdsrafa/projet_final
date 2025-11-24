-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Hôte : sql102.infinityfree.com
-- Généré le :  lun. 24 nov. 2025 à 03:19
-- Version du serveur :  11.4.7-MariaDB
-- Version de PHP :  7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `if0_40432147_base_de_donnee_si`
--

-- --------------------------------------------------------

--
-- Structure de la table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(7) DEFAULT '#4ECDC4',
  `logo_url` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `brands`
--

INSERT INTO `brands` (`id`, `name`, `color`, `logo_url`, `active`, `created_at`) VALUES
(1, 'Nike', '#FF6B6B', NULL, 1, '2025-11-04 08:47:30'),
(2, 'Adidas', '#4ECDC4', NULL, 1, '2025-11-04 08:47:30'),
(3, 'Jordan', '#FFE66D', NULL, 1, '2025-11-04 08:47:30'),
(4, 'Yeezy', '#6C5CE7', NULL, 1, '2025-11-04 08:47:30'),
(5, 'New Balance', '#A8E6CF', NULL, 1, '2025-11-04 08:47:30');

-- --------------------------------------------------------

--
-- Structure de la table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `cart`
--

INSERT INTO `cart` (`id`, `client_id`, `product_id`, `size`, `quantity`, `added_date`) VALUES
(8, 5, 7, 40, 1, '2025-11-24 08:12:31');

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'France',
  `type` enum('Regular','VIP','Pro') DEFAULT 'Regular',
  `loyalty_points` int(11) DEFAULT 0,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id`, `user_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `city`, `postal_code`, `country`, `type`, `loyalty_points`, `total_spent`, `notes`, `active`, `created_at`, `updated_at`) VALUES
(1, 2, 'Thomas', 'Martin', 'thomas.m@email.com', '06 12 34 56 78', NULL, NULL, NULL, 'France', 'VIP', 0, '0.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 19:28:09'),
(2, 3, 'Sophie', 'Durand', 'sophie.d@email.com', '06 23 45 67 89', NULL, NULL, NULL, 'France', 'Regular', 0, '0.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 19:28:09'),
(3, 4, 'Lucas', 'Bernard', 'lucas.b@email.com', '06 34 56 78 90', NULL, NULL, NULL, 'France', 'VIP', 205, '2052.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-15 20:43:40'),
(4, 5, 'Emma', 'Petit', 'emma.p@email.com', '06 45 67 89 01', NULL, NULL, NULL, 'France', 'Regular', 0, '0.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 19:28:09'),
(5, 9, 'Rafael', 'Rodrigues', 'rafael@gmail.com', '0403035043', NULL, NULL, NULL, 'France', 'Regular', 164, '1644.00', NULL, 1, '2025-11-04 19:23:08', '2025-11-23 17:56:15'),
(6, 11, 'Fatima', 'Saadna', 'fatima.saadnaz@gmail.com', '0771784241', NULL, NULL, NULL, 'France', 'Regular', 13, '132.00', NULL, 1, '2025-11-05 12:35:14', '2025-11-10 12:24:26'),
(7, NULL, 'Jean', 'Dupont', 'jean.dupont@email.com', '0612345678', NULL, NULL, NULL, 'France', 'Regular', 0, '0.00', NULL, 1, '2025-11-06 19:24:47', '2025-11-06 19:24:47'),
(8, NULL, 'Marie', 'Martin', 'marie.martin@email.com', '0623456789', NULL, NULL, NULL, 'France', 'VIP', 0, '0.00', NULL, 1, '2025-11-06 19:24:47', '2025-11-06 19:24:47'),
(9, NULL, 'Thomas', 'Bernard', 'thomas.bernard@email.com', '0634567890', NULL, NULL, NULL, 'France', 'Pro', 50, '504.00', NULL, 1, '2025-11-06 19:24:47', '2025-11-14 14:04:37'),
(10, NULL, 'Marie ', 'Dubois', 'marie.dubois@email.com', '0645678901', NULL, NULL, NULL, 'France', 'Regular', 0, '0.00', NULL, 1, '2025-11-06 19:24:47', '2025-11-22 16:01:41'),
(15, NULL, 'josephine', 'Dupont', 'josephine.dupont@test.com', '0612345678', NULL, NULL, NULL, 'France', 'Regular', 0, '227.99', NULL, 1, '2025-11-06 19:28:09', '2025-11-22 16:01:53'),
(16, NULL, 'Johnatan', 'Martin', 'johnatan.martin@test.com', '0623456789', NULL, NULL, NULL, 'France', 'VIP', 30, '1524.00', NULL, 1, '2025-11-06 19:28:09', '2025-11-22 16:00:43'),
(17, NULL, 'Eloise', 'Bernard', 'eloise.bernard@test.com', '0634567890', NULL, NULL, NULL, 'France', 'Pro', 55, '804.00', NULL, 1, '2025-11-06 19:28:09', '2025-11-23 11:26:57'),
(18, NULL, 'Sophie', 'Dubois', 'sophie.dubois@test.com', '0645678901', NULL, NULL, NULL, 'France', 'Regular', 0, '384.00', NULL, 1, '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(19, NULL, 'Lucas', 'Moreau', 'lucas.moreau@test.com', '0656789012', NULL, NULL, NULL, 'France', 'VIP', 25, '792.00', NULL, 1, '2025-11-06 19:28:09', '2025-11-11 09:42:40'),
(20, NULL, 'Steven', 'Kamel', 'steven.kamel@gmail.com', '0771820205', '1 rue des Etudiant, Nanterre', NULL, NULL, 'France', 'Regular', 30, '300.00', NULL, 1, '2025-11-23 17:40:04', '2025-11-23 17:40:21'),
(21, NULL, 'Arthur', 'Dubois', 'arthur.dubois@gmail.com', '0771820205', '123 Rue de la Justice, Paris 75001', NULL, NULL, 'France', 'Regular', 25, '252.00', NULL, 1, '2025-11-23 17:47:46', '2025-11-23 17:48:01');

-- --------------------------------------------------------

--
-- Structure de la table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `birth_date` date DEFAULT NULL,
  `social_security` varchar(20) DEFAULT NULL,
  `position` varchar(150) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `contract_type` enum('CDI','CDD','Vacataire','Stage') DEFAULT 'CDI',
  `salary` decimal(10,2) NOT NULL,
  `status` enum('active','inactive','vacation') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employees`
--

INSERT INTO `employees` (`id`, `first_name`, `last_name`, `email`, `password_hash`, `phone`, `address`, `city`, `postal_code`, `hire_date`, `birth_date`, `social_security`, `position`, `department`, `contract_type`, `salary`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Marie', 'Laurent', 'marie.l@adoo.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '06 12 34 56 78', NULL, NULL, NULL, '2023-01-15', NULL, NULL, 'Responsable Ventes', NULL, 'CDI', '3200.00', 'active', '2025-11-04 08:47:30', '2025-11-14 19:19:09'),
(2, 'Jean', 'Perrin', 'jean.p@adoo.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '06 23 45 67 89', NULL, NULL, NULL, '2023-03-10', NULL, NULL, 'Vendeur Senior', NULL, 'CDI', '2400.00', 'active', '2025-11-04 08:47:30', '2025-11-14 19:19:09'),
(3, 'Sophie', 'Moreau', 'sophie.m@adoo.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '06 34 56 78 90', NULL, NULL, NULL, '2023-02-20', NULL, NULL, 'Responsable Stock', NULL, 'CDI', '2800.00', 'active', '2025-11-04 08:47:30', '2025-11-14 19:19:09'),
(4, 'Marc', 'Dubois', 'marc.d@adoo.fr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '06 45 67 89 01', NULL, NULL, NULL, '2023-04-05', NULL, NULL, 'Responsable Achats', NULL, 'CDI', '3000.00', 'active', '2025-11-04 08:47:30', '2025-11-14 19:19:09'),
(5, 'Melissa', 'Benzidane', 'melissa@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '0612481917', '16 rue du champs', 'Chapet', '78130', '2025-11-11', '2005-12-17', NULL, 'Responsable de ventes', 'Ventes', 'CDI', '4000.00', 'active', '2025-11-10 08:44:09', '2025-11-14 19:19:09'),
(6, 'Alexis', 'Lacombe', 'alexis.lacombe@gmail.com', NULL, '07 35 93 12 06', '17 rue du champs', 'Chapet', '78130', '2025-11-15', '2005-12-18', NULL, 'vendeur', 'Ventes', 'CDI', '4000.00', 'active', '2025-11-14 20:07:22', '2025-11-15 21:25:14'),
(8, 'Alexis', 'Rodrigues', 'alexis@gmail.com', NULL, '0403035043', '18 rue du champs', 'Chapet', '78130', '2025-11-15', '2005-11-11', NULL, 'vendeur', 'Ventes', 'CDI', '4000.00', 'active', '2025-11-14 20:35:23', '2025-11-14 20:35:23'),
(9, 'Employee', 'Test', 'employee.test@gmail.com', NULL, '07 27 18 59 26', '123 rue de la paix', 'PUTEAUX', '92800', '2025-12-12', '2003-02-01', NULL, 'Vendeur', 'Ventes', 'CDI', '2500.00', 'active', '2025-11-15 09:03:27', '2025-11-15 09:03:27');

-- --------------------------------------------------------

--
-- Structure de la table `employee_leaves`
--

CREATE TABLE `employee_leaves` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `type` enum('CP','RTT','Maladie','Sans solde','Formation') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_count` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `employee_leaves`
--

INSERT INTO `employee_leaves` (`id`, `employee_id`, `type`, `start_date`, `end_date`, `days_count`, `status`, `reason`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 2, 'CP', '2025-12-23', '2026-01-03', 10, 'approved', 'Vacances de fin d\'année', 1, '2025-11-23 17:41:55', '2025-11-14 19:19:09'),
(2, 2, 'CP', '2025-08-01', '2025-08-15', 15, 'approved', 'Vacances d\'été', 1, '2025-08-01 08:00:00', '2025-11-14 19:19:09'),
(3, 2, 'Maladie', '2025-07-10', '2025-07-12', 3, 'approved', 'Grippe', 1, '2025-07-10 07:00:00', '2025-11-14 19:19:09'),
(4, 3, 'Maladie', '2025-10-15', '2025-10-17', 3, 'approved', 'Grippe', 1, '2025-10-15 06:30:00', '2025-11-14 19:19:09'),
(5, 3, 'CP', '2025-12-20', '2025-12-27', 6, 'rejected', 'Vacances de Noël', 1, '2025-11-23 17:41:59', '2025-11-14 19:19:09'),
(6, 4, 'CP', '2025-11-20', '2025-11-22', 3, 'approved', 'Week-end prolongé', 1, '2025-11-23 17:48:34', '2025-11-14 19:19:09'),
(7, 4, 'RTT', '2025-10-05', '2025-10-05', 1, 'approved', 'Jour de RTT', 1, '2025-10-04 12:00:00', '2025-11-14 19:19:09'),
(8, 9, 'RTT', '2025-11-15', '2025-12-15', 31, 'approved', 'j\'ai envie ', 1, '2025-11-15 20:53:19', '2025-11-15 15:38:12'),
(9, 9, 'RTT', '2025-12-02', '2026-01-01', 31, 'approved', 'Je vais passer des vacances avec ma famille ', 1, '2025-11-23 11:28:22', '2025-11-22 16:12:09'),
(10, 9, 'CP', '2025-11-28', '2025-11-30', 3, 'pending', 'Raison personnel', NULL, NULL, '2025-11-23 17:59:42');

-- --------------------------------------------------------

--
-- Structure de la table `leaves`
--

CREATE TABLE `leaves` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `type` enum('CP','RTT','Maladie','Sans solde','Formation') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_count` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('order','stock','delivery','info') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `payslips`
--

CREATE TABLE `payslips` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `gross_salary` decimal(10,2) NOT NULL,
  `net_salary` decimal(10,2) NOT NULL,
  `deductions` decimal(10,2) DEFAULT 0.00,
  `bonuses` decimal(10,2) DEFAULT 0.00,
  `hours_worked` decimal(5,2) DEFAULT 151.67,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `status` enum('draft','validated','paid') DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `payslips`
--

INSERT INTO `payslips` (`id`, `employee_id`, `month`, `year`, `gross_salary`, `net_salary`, `deductions`, `bonuses`, `hours_worked`, `overtime_hours`, `status`, `created_at`) VALUES
(1, 1, 10, 2025, '3200.00', '2496.00', '704.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(2, 1, 9, 2025, '3200.00', '2496.00', '704.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(3, 1, 8, 2025, '3200.00', '2496.00', '704.00', '200.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(4, 1, 7, 2025, '3200.00', '2496.00', '704.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(5, 1, 6, 2025, '3200.00', '2496.00', '704.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(6, 2, 10, 2025, '2400.00', '1872.00', '528.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(7, 2, 9, 2025, '2400.00', '1872.00', '528.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(8, 2, 8, 2025, '2400.00', '1872.00', '528.00', '150.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(9, 2, 7, 2025, '2400.00', '1872.00', '528.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(10, 2, 6, 2025, '2400.00', '1872.00', '528.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(11, 3, 10, 2025, '2800.00', '2184.00', '616.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(12, 3, 9, 2025, '2800.00', '2184.00', '616.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(13, 3, 8, 2025, '2800.00', '2184.00', '616.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(14, 3, 7, 2025, '2800.00', '2184.00', '616.00', '100.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(15, 3, 6, 2025, '2800.00', '2184.00', '616.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(16, 4, 10, 2025, '3000.00', '2340.00', '660.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(17, 4, 9, 2025, '3000.00', '2340.00', '660.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(18, 4, 8, 2025, '3000.00', '2340.00', '660.00', '100.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(19, 4, 7, 2025, '3000.00', '2340.00', '660.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(20, 5, 10, 2025, '4000.00', '3120.00', '880.00', '0.00', '151.67', '0.00', 'paid', '2025-11-14 19:19:09'),
(21, 5, 9, 2025, '4000.00', '3120.00', '880.00', '0.00', '151.67', '0.00', 'validated', '2025-11-14 19:19:09'),
(22, 1, 1, 2025, '3200.00', '2496.00', '704.00', '0.00', '151.67', '0.00', 'draft', '2025-11-15 21:22:22'),
(23, 2, 1, 2025, '2400.00', '1872.00', '528.00', '0.00', '151.67', '0.00', 'draft', '2025-11-15 21:22:22'),
(24, 3, 1, 2025, '2800.00', '2184.00', '616.00', '0.00', '151.67', '0.00', 'draft', '2025-11-15 21:22:22'),
(25, 4, 1, 2025, '3000.00', '2340.00', '660.00', '0.00', '151.67', '0.00', 'draft', '2025-11-15 21:22:22'),
(26, 5, 1, 2025, '4000.00', '3120.00', '880.00', '0.00', '151.67', '0.00', 'draft', '2025-11-15 21:22:22'),
(27, 8, 1, 2025, '4000.00', '3120.00', '880.00', '0.00', '151.67', '0.00', 'draft', '2025-11-15 21:22:22'),
(28, 9, 1, 2025, '2500.00', '1950.00', '550.00', '0.00', '151.67', '0.00', 'validated', '2025-11-15 21:22:22');

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `role` enum('admin','manager','employee','client','supplier') NOT NULL,
  `module` varchar(50) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_create` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `role`, `module`, `can_view`, `can_create`, `can_edit`, `can_delete`, `created_at`) VALUES
(1, 'admin', 'products', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(2, 'admin', 'stock', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(3, 'admin', 'sales', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(4, 'admin', 'clients', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(5, 'admin', 'employees', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(6, 'admin', 'suppliers', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(7, 'admin', 'purchase_orders', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(8, 'manager', 'products', 1, 1, 1, 1, '2025-11-14 18:04:35'),
(9, 'manager', 'stock', 1, 1, 1, 0, '2025-11-14 18:04:35'),
(10, 'manager', 'sales', 1, 1, 1, 0, '2025-11-14 18:04:35'),
(11, 'manager', 'clients', 1, 1, 1, 0, '2025-11-14 18:04:35'),
(12, 'manager', 'employees', 1, 0, 0, 0, '2025-11-14 18:04:35'),
(13, 'manager', 'suppliers', 1, 0, 0, 0, '2025-11-14 18:04:35'),
(14, 'manager', 'purchase_orders', 1, 1, 0, 0, '2025-11-14 18:04:35'),
(15, 'employee', 'products', 1, 0, 0, 0, '2025-11-14 18:04:35'),
(16, 'employee', 'stock', 1, 0, 0, 0, '2025-11-14 18:04:35'),
(17, 'employee', 'sales', 1, 1, 0, 0, '2025-11-14 18:04:35'),
(18, 'employee', 'clients', 1, 1, 1, 0, '2025-11-14 18:04:35');

-- --------------------------------------------------------

--
-- Structure de la table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `model` varchar(200) NOT NULL,
  `colorway` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `products`
--

INSERT INTO `products` (`id`, `brand_id`, `supplier_id`, `model`, `colorway`, `year`, `sku`, `description`, `price`, `cost`, `image_url`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Air Jordan 1 Retro High', 'Chicago', 2024, 'NKE-AJ1-CHI-001', 'La légendaire Air Jordan 1 Retro High Chicago combine cuir premium et design iconique. Un classique intemporel du basketball et du streetwear.', '189.00', '95.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 14:11:41'),
(2, 2, 2, 'Yeezy Boost 350 V2', 'Zebra', 2024, 'ADI-YZY-ZEB-002', 'La Yeezy Boost 350 V2 Zebra offre un confort exceptionnel avec sa semelle Boost et son motif noir et blanc emblématique.', '210.00', '110.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 14:11:41'),
(3, 1, 1, 'Dunk Low', 'Panda', 2024, 'NKE-DNK-PAN-003', 'La Nike Dunk Low Panda allie style simple et efficacité avec son contraste noir et blanc très tendance.', '145.00', '75.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 14:11:41'),
(4, 1, 1, 'Air Force 1 \'07', 'White', 2024, 'NKE-AF1-WHT-004', 'L’Air Force 1 ’07 White, symbole de la mode urbaine, reste un incontournable pour son look épuré et sa durabilité.', '110.00', '60.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 14:11:41'),
(5, 3, 3, 'Travis Scott Fragment', 'Military Blue', 2024, 'JRD-TSF-MIL-005', 'La Jordan Travis Scott Fragment Military Blue est une collaboration très recherchée, mêlant cuir haut de gamme et finitions exclusives.', '1500.00', '800.00', NULL, 1, '2025-11-04 08:47:30', '2025-11-06 14:11:41'),
(6, 2, 4, 'New Adidas premium', 'Noir', 2024, 'ADID-567-FDQ', 'C\'est une nouvelle basket révolutionnaire', '250.00', '100.00', NULL, 1, '2025-11-06 14:41:34', '2025-11-06 14:41:34'),
(7, 2, NULL, 'New Adidas premium2', 'Noir', 2025, 'ADID-568-FDQ', NULL, '200.00', '120.00', NULL, 1, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(8, 5, NULL, 'New balance 530', 'Gris', 2023, 'NEW-456-BAL-234', NULL, '200.00', '95.00', NULL, 1, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(9, 5, NULL, '2002R', 'Noir', 2025, 'NB-2002R', NULL, '180.00', '100.00', NULL, 1, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(10, 5, 4, '2002R', 'Noir', 2024, 'NB-2002', 'Chaussure tendance', '210.00', '100.00', NULL, 1, '2025-11-23 17:52:53', '2025-11-23 17:52:53'),
(11, 1, 4, 'Air Force', 'Blanc', 2024, 'NKE-2024', 'Chaussure tendance', '170.00', '100.00', NULL, 1, '2025-11-23 17:54:25', '2025-11-23 17:54:25');

-- --------------------------------------------------------

--
-- Structure de la table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `status` enum('draft','sent','received','cancelled') DEFAULT 'draft',
  `order_date` timestamp NULL DEFAULT current_timestamp(),
  `expected_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT 0.00,
  `shipping` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `order_number`, `supplier_id`, `status`, `order_date`, `expected_date`, `received_date`, `subtotal`, `tax`, `shipping`, `total`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'BC-2024-001', 1, 'received', '2024-11-01 09:30:00', '2024-11-15', '2025-11-07', '1425.00', '285.00', '50.00', '1760.00', 'Commande urgente pour le Black Friday', '2025-11-05 21:41:22', '2025-11-07 10:57:38'),
(2, 'BC-2024-002', 1, 'received', '2024-10-15 12:20:00', '2024-10-30', NULL, '2275.00', '455.00', '75.00', '2805.00', 'Réapprovisionnement stock automne', '2025-11-05 21:41:22', '2025-11-05 21:41:22'),
(3, 'BC-2024-003', 1, 'received', '2025-11-06 15:43:42', NULL, '2025-11-07', '950.00', '190.00', '0.00', '1140.00', 'Brouillon à valider', '2025-11-05 21:41:22', '2025-11-07 19:28:20'),
(4, 'BC-2024-004', 2, 'received', '2024-11-03 10:15:00', '2024-11-20', '2025-11-07', '3300.00', '660.00', '100.00', '4060.00', 'Collection hiver - Yeezy', '2025-11-05 21:41:22', '2025-11-07 10:54:59'),
(5, 'BC-2024-005', 2, 'received', '2024-10-20 14:45:00', '2024-11-05', NULL, '1100.00', '220.00', '50.00', '1370.00', NULL, '2025-11-05 21:41:22', '2025-11-05 21:41:22'),
(6, 'BC-2024-006', 3, 'received', '2024-11-04 12:30:00', '2024-11-18', '2025-11-07', '2175.00', '435.00', '80.00', '2690.00', 'Dunks et AF1 populaires', '2025-11-05 21:41:22', '2025-11-07 11:01:45'),
(7, 'BC-2024-007', 3, 'cancelled', '2024-10-10 08:00:00', '2024-10-25', NULL, '800.00', '160.00', '40.00', '1000.00', 'Annulée - rupture stock fournisseur', '2025-11-05 21:41:22', '2025-11-05 21:41:22'),
(8, 'BC-2024-008', 4, 'received', '2024-11-05 07:00:00', '2024-11-22', '2025-11-07', '4000.00', '800.00', '150.00', '4950.00', 'Commande spéciale éditions limitées', '2025-11-05 21:41:22', '2025-11-07 10:52:03'),
(9, 'PO-20251106-5573', 4, 'received', '2025-11-06 15:38:32', '2026-05-25', '2025-11-07', '100.00', '20.00', '0.00', '120.00', 'livre moi des chaussures ', '2025-11-06 15:16:59', '2025-11-07 19:23:52'),
(10, 'PO-20251106-5866', 4, 'cancelled', '2025-11-06 15:44:34', '2026-08-25', NULL, '0.00', '0.00', '0.00', '0.00', 'Donne', '2025-11-06 15:44:34', '2025-11-06 17:13:08'),
(11, 'PO-20251107-8035', 2, 'cancelled', '2025-11-07 19:30:30', '2026-05-24', NULL, '0.00', '0.00', '0.00', '0.00', 'LIVRE VITE', '2025-11-07 19:30:30', '2025-11-07 19:34:00'),
(12, 'PO-20251107-3247', 3, 'received', '2025-11-07 19:34:33', '2026-06-24', '2025-11-07', '100.00', '20.00', '0.00', '120.00', 'LIVRE VITE ', '2025-11-07 19:34:18', '2025-11-07 19:34:40'),
(13, 'PO-20251107-9869', 2, 'received', '2025-11-07 19:48:58', '2026-05-25', '2025-11-07', '100.00', '20.00', '0.00', '120.00', 'lIVRE VITE STP', '2025-11-07 19:48:45', '2025-11-07 19:49:05'),
(14, 'PO-20251107-1670', 1, 'received', '2025-11-07 19:55:50', '2026-04-23', '2025-11-07', '100.00', '20.00', '0.00', '120.00', '', '2025-11-07 19:55:38', '2025-11-07 19:55:59'),
(15, 'PO-20251107-0159', 4, 'received', '2025-11-07 20:17:58', '2026-04-25', '2025-11-07', '75.00', '15.00', '0.00', '90.00', '', '2025-11-07 20:17:43', '2025-11-07 20:18:07'),
(16, 'PO-20251107-5392', 1, 'received', '2025-11-07 20:23:08', '2026-05-26', '2025-11-07', '75.00', '15.00', '0.00', '90.00', '', '2025-11-07 20:22:53', '2025-11-07 20:23:17'),
(17, 'PO-20251111-1616', 3, 'received', '2025-11-11 09:45:14', '2025-11-12', '2025-11-11', '95.00', '19.00', '0.00', '114.00', 'J\'ai besoin de cette paire ', '2025-11-11 09:44:53', '2025-11-11 09:45:25'),
(18, 'PO-20251123-1987', 4, 'received', '2025-11-23 11:27:54', '2026-01-27', '2025-11-23', '240.00', '48.00', '0.00', '288.00', 'Je te commande des Adidas new premimum2 ', '2025-11-23 11:27:35', '2025-11-23 17:40:42');

-- --------------------------------------------------------

--
-- Structure de la table `purchase_order_lines`
--

CREATE TABLE `purchase_order_lines` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` int(11) NOT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_received` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `purchase_order_lines`
--

INSERT INTO `purchase_order_lines` (`id`, `purchase_order_id`, `product_id`, `size`, `quantity_ordered`, `quantity_received`, `unit_cost`, `total`, `created_at`) VALUES
(1, 1, 1, 40, 5, 0, '95.00', '475.00', '2025-11-05 21:41:22'),
(2, 1, 1, 42, 10, 0, '95.00', '950.00', '2025-11-05 21:41:22'),
(3, 2, 3, 41, 15, 15, '75.00', '1125.00', '2025-11-05 21:41:22'),
(4, 2, 4, 42, 20, 20, '60.00', '1200.00', '2025-11-05 21:41:22'),
(5, 3, 4, 40, 10, 20, '60.00', '600.00', '2025-11-05 21:41:22'),
(6, 3, 4, 43, 5, 10, '60.00', '300.00', '2025-11-05 21:41:22'),
(7, 4, 2, 40, 10, 0, '110.00', '1100.00', '2025-11-05 21:41:22'),
(8, 4, 2, 41, 8, 0, '110.00', '880.00', '2025-11-05 21:41:22'),
(9, 4, 2, 42, 12, 0, '110.00', '1320.00', '2025-11-05 21:41:22'),
(10, 5, 2, 43, 10, 10, '110.00', '1100.00', '2025-11-05 21:41:22'),
(11, 6, 3, 40, 12, 0, '75.00', '900.00', '2025-11-05 21:41:22'),
(12, 6, 3, 42, 10, 0, '75.00', '750.00', '2025-11-05 21:41:22'),
(13, 6, 4, 41, 8, 0, '60.00', '480.00', '2025-11-05 21:41:22'),
(14, 7, 1, 43, 8, 0, '95.00', '760.00', '2025-11-05 21:41:22'),
(15, 8, 5, 42, 2, 0, '800.00', '1600.00', '2025-11-05 21:41:22'),
(16, 8, 5, 43, 3, 0, '800.00', '2400.00', '2025-11-05 21:41:22'),
(17, 9, 6, 36, 1, 1, '100.00', '100.00', '2025-11-06 15:38:26'),
(18, 12, 6, 36, 1, 1, '100.00', '100.00', '2025-11-07 19:34:27'),
(19, 13, 6, 36, 1, 1, '100.00', '100.00', '2025-11-07 19:48:54'),
(20, 14, 6, 37, 1, 1, '100.00', '100.00', '2025-11-07 19:55:46'),
(21, 15, 3, 42, 1, 1, '75.00', '75.00', '2025-11-07 20:17:54'),
(22, 16, 3, 41, 1, 1, '75.00', '75.00', '2025-11-07 20:23:04'),
(23, 17, 8, 44, 1, 1, '95.00', '95.00', '2025-11-11 09:45:07'),
(24, 18, 7, 40, 2, 2, '120.00', '240.00', '2025-11-23 11:27:49');

-- --------------------------------------------------------

--
-- Structure de la table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `channel` enum('Boutique','Web') NOT NULL,
  `status` enum('pending','completed','cancelled','refunded') DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `payment_method` enum('CB','Especes','Virement','Cheque') NOT NULL,
  `payment_status` enum('pending','paid','refunded') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `sale_date` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sales`
--

INSERT INTO `sales` (`id`, `sale_number`, `client_id`, `employee_id`, `channel`, `status`, `subtotal`, `discount`, `tax`, `total`, `payment_method`, `payment_status`, `notes`, `sale_date`, `created_at`, `updated_at`) VALUES
(1, 'VTE-2025-71570', 15, NULL, 'Boutique', 'completed', '189.99', '0.00', '38.00', '227.99', 'CB', 'paid', NULL, '2025-11-06 19:28:09', '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(2, 'VTE-2025-30421', 16, NULL, 'Web', 'completed', '145.00', '0.00', '29.00', '174.00', 'CB', 'paid', NULL, '2025-11-06 19:28:09', '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(3, 'VTE-2025-37391', 17, NULL, 'Boutique', 'completed', '210.00', '0.00', '42.00', '252.00', 'Especes', 'paid', NULL, '2025-11-05 19:28:09', '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(4, 'VTE-2025-95695', 18, NULL, 'Web', 'completed', '320.00', '0.00', '64.00', '384.00', 'CB', 'paid', NULL, '2025-11-03 19:28:09', '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(5, 'VTE-2025-66304', 19, NULL, 'Boutique', 'completed', '450.00', '0.00', '90.00', '540.00', 'CB', 'paid', NULL, '2025-11-01 19:28:09', '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(6, 'VTE-2025-44438', 16, NULL, 'Boutique', 'completed', '875.00', '0.00', '175.00', '1050.00', 'CB', 'paid', NULL, '2025-10-27 19:28:09', '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(7, 'VTE-2025-23277', 15, NULL, 'Web', 'pending', '245.00', '0.00', '49.00', '294.00', 'Virement', 'pending', NULL, '2025-11-06 19:28:09', '2025-11-06 19:28:09', '2025-11-06 19:28:09'),
(8, 'WEB-20251106-9490', 5, NULL, 'Web', 'pending', '210.00', '0.00', '42.00', '252.00', 'CB', 'pending', NULL, '2025-11-06 19:57:48', '2025-11-06 19:57:48', '2025-11-06 19:57:48'),
(9, 'WEB-20251107-8347', 5, NULL, 'Web', 'pending', '250.00', '0.00', '50.00', '300.00', 'CB', 'pending', NULL, '2025-11-07 20:25:45', '2025-11-07 20:25:45', '2025-11-07 20:25:45'),
(10, 'VTE-20251109-06293', 9, 1, 'Boutique', 'completed', '210.00', '0.00', '42.00', '252.00', 'CB', 'paid', NULL, '2025-11-09 20:43:34', '2025-11-09 20:43:34', '2025-11-09 20:43:34'),
(11, 'VTE-20251109-36528', 3, 1, 'Boutique', 'completed', '210.00', '0.00', '42.00', '252.00', 'CB', 'paid', NULL, '2025-11-09 21:20:05', '2025-11-09 21:20:05', '2025-11-09 21:20:05'),
(12, 'WEB-20251110-2228', 5, NULL, 'Web', 'pending', '250.00', '0.00', '50.00', '300.00', 'CB', 'pending', NULL, '2025-11-10 08:53:43', '2025-11-10 08:53:43', '2025-11-10 08:53:43'),
(13, 'VTE-20251110-44557', 16, 1, 'Boutique', 'completed', '250.00', '0.00', '50.00', '300.00', 'CB', 'paid', NULL, '2025-11-10 12:15:40', '2025-11-10 12:15:40', '2025-11-10 12:15:40'),
(14, 'WEB-20251110-3916', 6, NULL, 'Web', 'pending', '110.00', '0.00', '22.00', '132.00', 'CB', 'pending', NULL, '2025-11-10 12:24:26', '2025-11-10 12:24:26', '2025-11-10 12:24:26'),
(15, 'VTE-20251111-29693', 19, 1, 'Boutique', 'completed', '210.00', '0.00', '42.00', '252.00', 'CB', 'paid', NULL, '2025-11-11 09:42:40', '2025-11-11 09:42:40', '2025-11-11 09:42:40'),
(16, 'VTE-20251114-69485', 9, 1, 'Boutique', 'completed', '210.00', '0.00', '42.00', '252.00', 'CB', 'paid', NULL, '2025-11-14 14:04:37', '2025-11-14 14:04:37', '2025-11-14 14:04:37'),
(17, 'VTE-20251115-08313', 3, 9, 'Boutique', 'completed', '1500.00', '0.00', '300.00', '1800.00', 'CB', 'paid', NULL, '2025-11-15 20:43:40', '2025-11-15 20:43:40', '2025-11-15 20:43:40'),
(18, 'WEB-20251122-2152', 5, NULL, 'Web', 'pending', '250.00', '0.00', '50.00', '300.00', 'CB', 'pending', NULL, '2025-11-22 15:27:11', '2025-11-22 15:27:11', '2025-11-22 15:27:11'),
(19, 'VTE-20251122-61022', 17, 9, 'Boutique', 'completed', '250.00', '0.00', '50.00', '300.00', 'CB', 'paid', NULL, '2025-11-22 16:02:19', '2025-11-22 16:02:19', '2025-11-22 16:02:19'),
(20, 'VTE-20251123-33611', 17, 1, 'Boutique', 'completed', '210.00', '0.00', '42.00', '252.00', 'CB', 'paid', NULL, '2025-11-23 11:26:57', '2025-11-23 11:26:57', '2025-11-23 11:26:57'),
(21, 'VTE-20251123-32769', 20, 1, 'Boutique', 'completed', '250.00', '0.00', '50.00', '300.00', 'CB', 'paid', NULL, '2025-11-23 17:40:21', '2025-11-23 17:40:21', '2025-11-23 17:40:21'),
(22, 'VTE-20251123-86557', 21, 1, 'Boutique', 'completed', '210.00', '0.00', '42.00', '252.00', 'CB', 'paid', NULL, '2025-11-23 17:48:01', '2025-11-23 17:48:01', '2025-11-23 17:48:01'),
(23, 'WEB-20251123-5066', 5, NULL, 'Web', 'pending', '410.00', '0.00', '82.00', '492.00', 'CB', 'pending', NULL, '2025-11-23 17:56:15', '2025-11-23 17:56:15', '2025-11-23 17:56:15');

-- --------------------------------------------------------

--
-- Structure de la table `sale_lines`
--

CREATE TABLE `sale_lines` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `sale_lines`
--

INSERT INTO `sale_lines` (`id`, `sale_id`, `product_id`, `size`, `quantity`, `unit_price`, `discount`, `total`, `serial_number`, `created_at`) VALUES
(1, 1, 1, 42, 1, '189.99', '0.00', '189.99', NULL, '2025-11-06 19:28:09'),
(2, 2, 3, 40, 1, '145.00', '0.00', '145.00', NULL, '2025-11-06 19:28:09'),
(3, 3, 4, 43, 1, '210.00', '0.00', '210.00', NULL, '2025-11-06 19:28:09'),
(4, 4, 1, 41, 2, '160.00', '0.00', '320.00', NULL, '2025-11-06 19:28:09'),
(5, 5, 3, 42, 3, '450.00', '0.00', '450.00', NULL, '2025-11-06 19:28:09'),
(6, 6, 1, 42, 2, '380.00', '0.00', '380.00', NULL, '2025-11-06 19:28:09'),
(7, 6, 3, 41, 2, '290.00', '0.00', '290.00', NULL, '2025-11-06 19:28:09'),
(8, 6, 4, 43, 1, '205.00', '0.00', '205.00', NULL, '2025-11-06 19:28:09'),
(9, 7, 4, 44, 1, '245.00', '0.00', '245.00', NULL, '2025-11-06 19:28:09'),
(10, 8, 2, 42, 1, '210.00', '0.00', '210.00', NULL, '2025-11-06 19:57:48'),
(11, 9, 6, 37, 1, '250.00', '0.00', '250.00', NULL, '2025-11-07 20:25:45'),
(12, 10, 2, 40, 1, '210.00', '0.00', '210.00', NULL, '2025-11-09 20:43:34'),
(13, 11, 2, 40, 1, '210.00', '0.00', '210.00', NULL, '2025-11-09 21:20:05'),
(14, 12, 6, 37, 1, '250.00', '0.00', '250.00', NULL, '2025-11-10 08:53:43'),
(15, 13, 6, 36, 1, '250.00', '0.00', '250.00', NULL, '2025-11-10 12:15:40'),
(16, 14, 4, 40, 1, '110.00', '0.00', '110.00', NULL, '2025-11-10 12:24:26'),
(17, 15, 2, 40, 1, '210.00', '0.00', '210.00', NULL, '2025-11-11 09:42:40'),
(18, 16, 2, 40, 1, '210.00', '0.00', '210.00', NULL, '2025-11-14 14:04:37'),
(19, 17, 5, 42, 1, '1500.00', '0.00', '1500.00', NULL, '2025-11-15 20:43:40'),
(20, 18, 6, 37, 1, '250.00', '0.00', '250.00', NULL, '2025-11-22 15:27:11'),
(21, 19, 6, 36, 1, '250.00', '0.00', '250.00', NULL, '2025-11-22 16:02:19'),
(22, 20, 2, 40, 1, '210.00', '0.00', '210.00', NULL, '2025-11-23 11:26:57'),
(23, 21, 6, 37, 1, '250.00', '0.00', '250.00', NULL, '2025-11-23 17:40:21'),
(24, 22, 2, 42, 1, '210.00', '0.00', '210.00', NULL, '2025-11-23 17:48:01'),
(25, 23, 2, 42, 1, '210.00', '0.00', '210.00', NULL, '2025-11-23 17:56:15'),
(26, 23, 8, 44, 1, '200.00', '0.00', '200.00', NULL, '2025-11-23 17:56:15');

-- --------------------------------------------------------

--
-- Structure de la table `stock`
--

CREATE TABLE `stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `location` varchar(50) DEFAULT 'Entrepôt',
  `min_quantity` int(11) DEFAULT 5,
  `max_quantity` int(11) DEFAULT 50,
  `serial_numbers` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `stock`
--

INSERT INTO `stock` (`id`, `product_id`, `size`, `quantity`, `location`, `min_quantity`, `max_quantity`, `serial_numbers`, `created_at`, `updated_at`) VALUES
(1, 1, 40, 17, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-07 10:57:38'),
(3, 1, 42, 22, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-07 10:57:38'),
(4, 1, 43, 10, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-04 08:47:30'),
(5, 2, 40, 40, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-23 11:26:57'),
(6, 2, 41, 34, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-07 10:57:17'),
(7, 2, 42, 44, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-23 17:48:01'),
(8, 2, 43, 14, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-04 08:47:30'),
(9, 3, 40, 15, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-07 11:01:45'),
(10, 3, 41, 3, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-07 20:23:17'),
(11, 3, 42, 14, 'Entrepôt', 5, 50, NULL, '2025-11-04 08:47:30', '2025-11-07 20:18:07'),
(12, 5, 42, 3, 'Entrepôt', 5, 50, NULL, '2025-11-07 10:52:03', '2025-11-15 20:43:40'),
(13, 5, 43, 6, 'Entrepôt', 5, 50, NULL, '2025-11-07 10:52:03', '2025-11-07 10:54:19'),
(14, 4, 41, 8, 'Entrepôt', 5, 50, NULL, '2025-11-07 11:01:45', '2025-11-07 11:01:45'),
(15, 6, 36, 4, 'Entrepôt', 5, 50, NULL, '2025-11-07 19:23:52', '2025-11-22 16:02:19'),
(16, 4, 40, 40, 'Entrepôt', 5, 50, NULL, '2025-11-07 19:28:20', '2025-11-07 19:29:59'),
(17, 4, 43, 20, 'Entrepôt', 5, 50, NULL, '2025-11-07 19:28:20', '2025-11-07 19:29:59'),
(18, 6, 37, 0, 'Entrepôt', 5, 50, NULL, '2025-11-07 19:55:59', '2025-11-23 17:40:21'),
(19, 7, 36, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(20, 7, 37, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(21, 7, 38, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(22, 7, 39, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(23, 7, 40, 2, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-23 17:40:42'),
(24, 7, 41, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(25, 7, 42, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(26, 7, 43, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(27, 7, 44, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(28, 7, 45, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(29, 7, 46, 0, 'Entrepôt', 5, 50, NULL, '2025-11-10 07:59:11', '2025-11-10 07:59:11'),
(30, 8, 36, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(31, 8, 37, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(32, 8, 38, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(33, 8, 39, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(34, 8, 40, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(35, 8, 41, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(36, 8, 42, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(37, 8, 43, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(38, 8, 44, 2, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:45:25'),
(39, 8, 45, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(40, 8, 46, 0, 'Entrepôt', 5, 50, NULL, '2025-11-11 09:44:07', '2025-11-11 09:44:07'),
(41, 9, 36, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(42, 9, 37, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(43, 9, 38, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(44, 9, 39, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(45, 9, 40, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(46, 9, 41, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(47, 9, 42, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(48, 9, 43, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(49, 9, 44, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(50, 9, 45, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26'),
(51, 9, 46, 0, 'Entrepôt', 5, 50, NULL, '2025-11-23 17:41:26', '2025-11-23 17:41:26');

-- --------------------------------------------------------

--
-- Structure de la table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` int(11) NOT NULL,
  `type` enum('in','out','transfer','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `from_location` varchar(50) DEFAULT NULL,
  `to_location` varchar(50) DEFAULT NULL,
  `reference_type` enum('sale','purchase','transfer','inventory','other') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `movement_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `size`, `type`, `quantity`, `from_location`, `to_location`, `reference_type`, `reference_id`, `serial_number`, `employee_id`, `notes`, `movement_date`) VALUES
(1, 1, 42, 'out', 1, NULL, NULL, 'sale', 1, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(2, 3, 40, 'out', 1, NULL, NULL, 'sale', 2, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(3, 4, 43, 'out', 1, NULL, NULL, 'sale', 3, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(4, 1, 41, 'out', 2, NULL, NULL, 'sale', 4, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(5, 3, 42, 'out', 3, NULL, NULL, 'sale', 5, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(6, 1, 42, 'out', 2, NULL, NULL, 'sale', 6, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(7, 3, 41, 'out', 2, NULL, NULL, 'sale', 6, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(8, 4, 43, 'out', 1, NULL, NULL, 'sale', 6, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(9, 4, 44, 'out', 1, NULL, NULL, 'sale', 7, NULL, NULL, NULL, '2025-11-06 19:28:09'),
(10, 2, 42, 'out', 1, NULL, NULL, 'sale', 8, NULL, NULL, NULL, '2025-11-06 19:57:48'),
(11, 5, 42, 'in', 2, NULL, NULL, 'purchase', 8, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:52:03'),
(12, 5, 43, 'in', 3, NULL, NULL, 'purchase', 8, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:52:03'),
(13, 5, 42, 'in', 2, NULL, NULL, 'purchase', 8, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:54:19'),
(14, 5, 43, 'in', 3, NULL, NULL, 'purchase', 8, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:54:19'),
(15, 2, 40, 'in', 10, NULL, NULL, 'purchase', 4, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:54:59'),
(16, 2, 41, 'in', 8, NULL, NULL, 'purchase', 4, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:54:59'),
(17, 2, 42, 'in', 12, NULL, NULL, 'purchase', 4, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:54:59'),
(18, 2, 40, 'in', 10, NULL, NULL, 'purchase', 4, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:57:17'),
(19, 2, 41, 'in', 8, NULL, NULL, 'purchase', 4, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:57:17'),
(20, 2, 42, 'in', 12, NULL, NULL, 'purchase', 4, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:57:17'),
(21, 1, 40, 'in', 5, NULL, NULL, 'purchase', 1, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:57:38'),
(22, 1, 42, 'in', 10, NULL, NULL, 'purchase', 1, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 10:57:38'),
(23, 3, 40, 'in', 12, NULL, NULL, 'purchase', 6, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 11:01:45'),
(24, 3, 42, 'in', 10, NULL, NULL, 'purchase', 6, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 11:01:45'),
(25, 4, 41, 'in', 8, NULL, NULL, 'purchase', 6, NULL, NULL, 'Réception commande fournisseur', '2025-11-07 11:01:45'),
(26, 6, 36, 'in', 1, NULL, NULL, 'purchase', 9, NULL, NULL, 'Réception commande - Quantité: 1', '2025-11-07 19:23:52'),
(27, 6, 36, 'in', 1, NULL, NULL, 'purchase', 9, NULL, 1, 'Réception commande - New Adidas premium Noir', '2025-11-07 19:23:52'),
(28, 4, 40, 'in', 10, NULL, NULL, 'purchase', 3, NULL, NULL, 'Réception commande - Quantité: 10', '2025-11-07 19:28:20'),
(29, 4, 40, 'in', 10, NULL, NULL, 'purchase', 3, NULL, 1, 'Réception commande - Air Force 1 \'07 White', '2025-11-07 19:28:20'),
(30, 4, 43, 'in', 5, NULL, NULL, 'purchase', 3, NULL, NULL, 'Réception commande - Quantité: 5', '2025-11-07 19:28:20'),
(31, 4, 43, 'in', 5, NULL, NULL, 'purchase', 3, NULL, 1, 'Réception commande - Air Force 1 \'07 White', '2025-11-07 19:28:20'),
(32, 4, 40, 'in', 10, NULL, NULL, 'purchase', 3, NULL, NULL, 'Réception commande - Quantité: 10', '2025-11-07 19:29:59'),
(33, 4, 40, 'in', 10, NULL, NULL, 'purchase', 3, NULL, 1, 'Réception commande - Air Force 1 \'07 White', '2025-11-07 19:29:59'),
(34, 4, 43, 'in', 5, NULL, NULL, 'purchase', 3, NULL, NULL, 'Réception commande - Quantité: 5', '2025-11-07 19:29:59'),
(35, 4, 43, 'in', 5, NULL, NULL, 'purchase', 3, NULL, 1, 'Réception commande - Air Force 1 \'07 White', '2025-11-07 19:29:59'),
(36, 6, 36, 'in', 1, NULL, NULL, 'purchase', 12, NULL, NULL, 'Réception commande - Quantité: 1', '2025-11-07 19:34:40'),
(37, 6, 36, 'in', 1, NULL, NULL, 'purchase', 12, NULL, 1, 'Réception commande - New Adidas premium Noir', '2025-11-07 19:34:40'),
(38, 6, 36, 'in', 1, NULL, NULL, 'purchase', 13, NULL, NULL, 'Réception commande - Quantité: 1', '2025-11-07 19:49:05'),
(39, 6, 36, 'in', 1, NULL, NULL, 'purchase', 13, NULL, 1, 'Réception commande - New Adidas premium Noir', '2025-11-07 19:49:05'),
(40, 6, 37, 'in', 1, NULL, NULL, 'purchase', 14, NULL, NULL, 'Réception commande - Quantité: 1', '2025-11-07 19:55:59'),
(41, 6, 37, 'in', 1, NULL, NULL, 'purchase', 14, NULL, 1, 'Réception commande - New Adidas premium Noir', '2025-11-07 19:55:59'),
(42, 3, 42, 'in', 1, NULL, NULL, 'purchase', 15, NULL, NULL, 'Réception commande - Quantité: 1', '2025-11-07 20:18:07'),
(43, 3, 42, 'in', 1, NULL, NULL, 'purchase', 15, NULL, 1, 'Réception commande - Dunk Low Panda', '2025-11-07 20:18:07'),
(44, 3, 41, 'in', 1, NULL, NULL, 'purchase', 16, NULL, NULL, 'Réception commande - Quantité: 1', '2025-11-07 20:23:17'),
(45, 3, 41, 'in', 1, NULL, NULL, 'purchase', 16, NULL, 1, 'Réception commande - Dunk Low Panda', '2025-11-07 20:23:17'),
(46, 6, 37, 'out', -1, NULL, NULL, 'sale', 9, NULL, NULL, NULL, '2025-11-07 20:25:45'),
(47, 8, 44, 'in', 1, NULL, NULL, 'purchase', 17, NULL, NULL, 'Réception commande - Quantité: 1', '2025-11-11 09:45:25'),
(48, 8, 44, 'in', 1, NULL, NULL, 'purchase', 17, NULL, 1, 'Réception commande - New balance 530 Gris', '2025-11-11 09:45:25'),
(49, 5, 42, 'out', 1, NULL, NULL, 'sale', 17, NULL, NULL, NULL, '2025-11-15 20:43:40'),
(50, 6, 36, 'out', 1, NULL, NULL, 'sale', 19, NULL, NULL, NULL, '2025-11-22 16:02:19'),
(51, 7, 40, 'in', 2, NULL, NULL, 'purchase', 18, NULL, 1, 'Réception commande - New Adidas premium2 Noir', '2025-11-23 17:40:42');

-- --------------------------------------------------------

--
-- Structure de la table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'France',
  `siret` varchar(20) DEFAULT NULL,
  `tva` varchar(20) DEFAULT NULL,
  `payment_terms` int(11) DEFAULT 30,
  `notes` text DEFAULT NULL,
  `rating` int(11) DEFAULT 5,
  `active` tinyint(1) DEFAULT 1,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `suppliers`
--

INSERT INTO `suppliers` (`id`, `user_id`, `name`, `contact_name`, `email`, `phone`, `address`, `city`, `postal_code`, `country`, `siret`, `tva`, `payment_terms`, `notes`, `rating`, `active`, `status`, `created_at`, `updated_at`, `approved_at`) VALUES
(1, 6, 'Nike Official Distributor', 'John Smith', 'contact@nike-distributor.com', '+33 1 23 45 67 89', NULL, 'Paris', NULL, 'France', NULL, NULL, 30, NULL, 5, 1, 'approved', '2025-11-04 15:45:26', '2025-11-04 15:45:26', NULL),
(2, 7, 'Adidas Europe Supply', 'Maria Garcia', 'supply@adidas-europe.com', '+33 1 34 56 78 90', NULL, 'Lyon', NULL, 'France', NULL, NULL, 30, NULL, 5, 1, 'approved', '2025-11-04 15:45:26', '2025-11-04 15:45:26', NULL),
(3, 8, 'Streetwear Wholesale', 'Pierre Dubois', 'contact@streetwear-ws.com', '+33 1 45 67 89 01', NULL, 'Marseille', NULL, 'France', NULL, NULL, 45, NULL, 4, 1, 'approved', '2025-11-04 15:45:26', '2025-11-05 13:37:58', '2025-11-05 14:37:58'),
(4, 10, 'techshop', 'Steven Kamel', 'Steven.Kamel@gmail.com', '06  12  24 87 37', '123 rue de la paix', 'PUTEAUX', '92800', 'France', '123 456 789 0078', NULL, 30, NULL, 5, 1, 'approved', '2025-11-04 20:19:32', '2025-11-05 13:34:13', '2025-11-05 14:34:13');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager','employee','client','supplier') DEFAULT 'client',
  `last_login` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `employee_id`, `username`, `email`, `password_hash`, `role`, `last_login`, `active`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'admin@adoo.fr', '$2y$10$FXFmuyy6CQ7olhbcY0pHjOR35LiWgb4ZtCeTChq2wU4XHCtowSU5m', 'admin', '2025-11-24 08:09:58', 1, '2025-11-04 08:47:30', '2025-11-24 08:09:58'),
(2, NULL, 'thomas.martin', 'thomas.m@email.com', '$2y$10$CwTycUXWue0Thq9StjUM0uBYL0f1qqbL1x6dKVqZs.2B.xBqVqrze', 'client', NULL, 1, '2025-11-04 15:45:26', '2025-11-04 15:45:26'),
(3, NULL, 'sophie.durand', 'sophie.d@email.com', '$2y$10$CwTycUXWue0Thq9StjUM0uBYL0f1qqbL1x6dKVqZs.2B.xBqVqrze', 'client', NULL, 1, '2025-11-04 15:45:26', '2025-11-04 15:45:26'),
(4, NULL, 'lucas.bernard', 'lucas.b@email.com', '$2y$10$CwTycUXWue0Thq9StjUM0uBYL0f1qqbL1x6dKVqZs.2B.xBqVqrze', 'client', NULL, 1, '2025-11-04 15:45:26', '2025-11-04 15:45:26'),
(5, NULL, 'emma.petit', 'emma.p@email.com', '$2y$10$CwTycUXWue0Thq9StjUM0uBYL0f1qqbL1x6dKVqZs.2B.xBqVqrze', 'client', NULL, 1, '2025-11-04 15:45:26', '2025-11-04 15:45:26'),
(6, NULL, 'nike.distributor', 'contact@nike-distributor.com', '$2y$10$E5jF6KlHxQxSvL7mNpOqL.lQF7JjYvVqZJMNxQJvLqN5QjZMxQxQe', 'supplier', NULL, 1, '2025-11-04 15:45:26', '2025-11-04 15:45:26'),
(7, NULL, 'adidas.europe', 'supply@adidas-europe.com', '$2y$10$E5jF6KlHxQxSvL7mNpOqL.lQF7JjYvVqZJMNxQJvLqN5QjZMxQxQe', 'supplier', NULL, 1, '2025-11-04 15:45:26', '2025-11-04 15:45:26'),
(8, NULL, 'streetwear.wholesale', 'contact@streetwear-ws.com', '$2y$10$E5jF6KlHxQxSvL7mNpOqL.lQF7JjYvVqZJMNxQJvLqN5QjZMxQxQe', 'supplier', NULL, 1, '2025-11-04 15:45:26', '2025-11-04 15:45:26'),
(9, NULL, 'rafael.rodrigues', 'rafael@gmail.com', '$2y$10$buuybXoc1ElYp7llZS4/PuXwSHrXH2N2y7q5WKNrjrvGtG8Makvzu', 'client', '2025-11-24 08:12:10', 1, '2025-11-04 19:23:08', '2025-11-24 08:12:10'),
(10, NULL, 'techshop', 'Steven.Kamel@gmail.com', '$2y$10$FPP0/OGqM6LU4J/Bnub8qe3CeK0Ztj2wE.LtamLroBmAR/O4Xe1Ju', 'supplier', '2025-11-24 08:12:28', 1, '2025-11-04 20:19:32', '2025-11-24 08:12:28'),
(11, NULL, 'fatima.saadna', 'fatima.saadnaz@gmail.com', '$2y$10$Ss5ixQwplhf6qGQMUVUG2eAnUd086CW57TFqTJDaLemfvEmW14ZBi', 'client', '2025-11-10 12:23:41', 1, '2025-11-05 12:35:14', '2025-11-10 12:23:41'),
(12, 2, 'jean.perrin', 'jean.p@adoo.fr', '$2y$10$FXFmuyy6CQ7olhbcY0pHjOR35LiWgb4ZtCeTChq2wU4XHCtowSU5m', 'employee', NULL, 1, '2025-11-14 18:04:34', '2025-11-14 18:04:34'),
(13, 3, 'sophie.moreau', 'sophie.m@adoo.fr', '$2y$10$FXFmuyy6CQ7olhbcY0pHjOR35LiWgb4ZtCeTChq2wU4XHCtowSU5m', 'manager', NULL, 1, '2025-11-14 18:04:34', '2025-11-14 18:04:34'),
(14, 4, 'marc.dubois', 'marc.d@adoo.fr', '$2y$10$FXFmuyy6CQ7olhbcY0pHjOR35LiWgb4ZtCeTChq2wU4XHCtowSU5m', 'manager', NULL, 1, '2025-11-14 18:04:34', '2025-11-14 18:04:34'),
(15, 5, 'melissa.benzidane', 'melissa@gmail.com', '$2y$10$FXFmuyy6CQ7olhbcY0pHjOR35LiWgb4ZtCeTChq2wU4XHCtowSU5m', 'manager', NULL, 1, '2025-11-14 18:04:34', '2025-11-14 18:04:34'),
(19, 9, 'EmployeeTest', 'employee.test@gmail.com', '$2y$10$4z36yRvsFpRYfRRA3qkMguK89IeQ6iKmXoojTd4/ldf0/kGRF6BCm', 'employee', '2025-11-23 17:58:17', 1, '2025-11-15 09:04:26', '2025-11-23 17:58:17');

-- --------------------------------------------------------

--
-- Structure de la table `v_employee_users`
--

CREATE TABLE `v_employee_users` (
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` enum('admin','manager','employee','client','supplier') DEFAULT NULL,
  `user_active` tinyint(1) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `position` varchar(150) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `employee_status` enum('active','inactive','vacation') DEFAULT NULL,
  `hire_date` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `v_products_available`
--

CREATE TABLE `v_products_available` (
  `product_id` int(11) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `brand_color` varchar(7) DEFAULT NULL,
  `model` varchar(200) DEFAULT NULL,
  `colorway` varchar(100) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `stock_id` int(11) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `stock_status` varchar(10) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `v_sales_detail`
--

CREATE TABLE `v_sales_detail` (
  `id` int(11) DEFAULT NULL,
  `sale_number` varchar(50) DEFAULT NULL,
  `client_name` varchar(201) DEFAULT NULL,
  `client_type` enum('Regular','VIP','Pro') DEFAULT NULL,
  `channel` enum('Boutique','Web') DEFAULT NULL,
  `status` enum('pending','completed','cancelled','refunded') DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `sale_date` timestamp NULL DEFAULT NULL,
  `items_count` bigint(20) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `v_stock_alerts`
--

CREATE TABLE `v_stock_alerts` (
  `id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(200) DEFAULT NULL,
  `colorway` varchar(100) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `min_quantity` int(11) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `alert_level` varchar(9) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `quantity_needed` bigint(20) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `v_stock_overview`
--

CREATE TABLE `v_stock_overview` (
  `id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(200) DEFAULT NULL,
  `colorway` varchar(100) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `min_quantity` int(11) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock_value` decimal(20,2) DEFAULT NULL,
  `stock_status` varchar(3) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Structure de la table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `size` int(11) NOT NULL,
  `added_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart` (`client_id`,`product_id`,`size`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_client` (`client_id`);

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_user_id` (`user_id`);
ALTER TABLE `clients` ADD FULLTEXT KEY `idx_client_search` (`first_name`,`last_name`,`email`);

--
-- Index pour la table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `employee_leaves`
--
ALTER TABLE `employee_leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Index pour la table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read` (`is_read`);

--
-- Index pour la table `payslips`
--
ALTER TABLE `payslips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_period` (`employee_id`,`month`,`year`),
  ADD KEY `idx_employee` (`employee_id`),
  ADD KEY `idx_period` (`year`,`month`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_brand` (`brand_id`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `fk_products_supplier` (`supplier_id`);
ALTER TABLE `products` ADD FULLTEXT KEY `idx_search` (`model`,`colorway`,`description`);

--
-- Index pour la table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `purchase_order_lines`
--
ALTER TABLE `purchase_order_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_purchase_order` (`purchase_order_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Index pour la table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`sale_number`),
  ADD KEY `idx_sale_number` (`sale_number`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`sale_date`);

--
-- Index pour la table `sale_lines`
--
ALTER TABLE `sale_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sale` (`sale_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Index pour la table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_size` (`product_id`,`size`,`location`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_low_stock` (`quantity`);

--
-- Index pour la table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_date` (`movement_date`);

--
-- Index pour la table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- Index pour la table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wishlist` (`client_id`,`product_id`,`size`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_client` (`client_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `employee_leaves`
--
ALTER TABLE `employee_leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `payslips`
--
ALTER TABLE `payslips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `purchase_order_lines`
--
ALTER TABLE `purchase_order_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `sale_lines`
--
ALTER TABLE `sale_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT pour la table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT pour la table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
