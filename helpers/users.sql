-- --------------------------------------------------------
-- Servidor:                     127.0.0.1
-- Versão do servidor:           8.4.3 - MySQL Community Server - GPL
-- OS do Servidor:               Win64
-- HeidiSQL Versão:              12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Copiando estrutura do banco de dados para pdv_fiscal
CREATE DATABASE IF NOT EXISTS `pdv_fiscal` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `pdv_fiscal`;

-- Copiando estrutura para tabela pdv_fiscal.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` int unsigned NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_codigo_unique` (`codigo`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela pdv_fiscal.users: ~16 rows (aproximadamente)
REPLACE INTO `users` (`id`, `name`, `codigo`, `email`, `email_verified_at`, `password`, `is_admin`, `ativo`, `remember_token`, `created_at`, `updated_at`) VALUES
	(1, 'Administrador', 1, NULL, NULL, '$2y$12$L6AEarDbD7S.6riY5l42OePovO5Xk/VX9YjowMyyGQ7T47aENOnvu', 1, 1, NULL, '2026-07-31 03:28:13', '2026-10-13 13:48:26'),
	(2, 'Operador Fiscal', 2, NULL, NULL, '$2y$12$abaCGuO1.JsUilSVV6gZFesOqqhFbEJ9vjfA1eUBDj9vw71ck6VF.', 0, 1, NULL, '2026-10-03 12:28:52', '2026-10-03 15:13:01'),
	(3, 'Supervisor Teste', 3, NULL, NULL, '$2y$12$QEZR5qQopHv1ggAB7.VcVu4PDHXtzkJk8qwwf8mZHDvqbbUlwExFW', 0, 1, NULL, '2026-10-03 14:26:47', '2026-10-05 01:12:05'),
	(4, 'Operador de Caixa', 4, NULL, NULL, '$2y$12$hWusM8SXWQlJRumytzrtR.lQcW8VY1hU6au.SuHy6CyvgsK8mTc/6', 0, 1, NULL, '2026-10-03 14:27:40', '2026-10-03 15:49:36'),
	(5, 'Operador de caixa 2', 5, NULL, NULL, '$2y$12$lDx5JipzCVBeFDDBh6aJy.JZEsC8MwyXDij0nQFuH8w/BO3ZFiAxq', 0, 1, NULL, '2026-10-05 01:11:30', '2026-10-05 01:11:30'),
	(6, 'Supervisor Teste 2', 6, NULL, NULL, '$2y$12$GMfMyCu.x5JWpEA4QH10E.on57FtAkJEs9/hdQyYza/kD7nv89Xsi', 0, 1, NULL, '2026-10-05 01:11:47', '2026-10-05 01:33:42'),
	(7, 'Supervisor 1', 7, NULL, NULL, '$2y$12$T3wx9rct7hciyvd.jFQZRuFSSreluz831zQndwBAirFy.NCGwg2K2', 0, 1, NULL, '2026-10-05 01:47:03', '2026-10-05 01:47:03'),
	(8, 'Supervisor 7', 8, NULL, NULL, '$2y$12$fWLsRUGesZA8ui3ZwFtpt.9LfZzQ3J9OFC2YLNnpkul8mIZt899Gq', 0, 1, NULL, '2026-10-05 01:47:39', '2026-10-05 11:19:03'),
	(9, 'Supervisor caixa', 9, NULL, NULL, '$2y$12$DcBI2rDTHGsvC5wTwLfxW.xQtDqTVTErSO3ZUbbACrx7vxt84BoG6', 0, 1, NULL, '2026-10-05 11:21:12', '2026-10-05 11:21:12'),
	(10, 'Operador Fiscal', 10, NULL, NULL, '$2y$12$YY1wKpRKyDxgF7IDA.XUmuDc4SPdI.Fmnrfpi0fbxs.5OgnnAURMO', 0, 1, NULL, '2026-10-05 11:26:53', '2026-10-05 11:26:53'),
	(11, 'Operador de Caixa 10', 11, NULL, NULL, '$2y$12$s4iYVVNSvWYwW3aGU8quGullphtkm6/zbL0ZLqgA/cj.45z.6fQai', 0, 1, NULL, '2026-10-05 12:23:58', '2026-10-05 12:23:58'),
	(12, 'Supervisor 10', 12, NULL, NULL, '$2y$12$Jwk7L.W4nxVR72iYi5m.we2DAVKGcrGg2nimMvl8FCgHesYV6JH3u', 0, 1, NULL, '2026-10-05 12:24:22', '2026-10-05 12:24:22'),
	(13, 'Operador de caixa novo', 13, NULL, NULL, '$2y$12$vFSvnsqiBGon46iFnjUsCuhESNqyCftqBQfFK.Oai3CAjheEELY0C', 0, 1, NULL, '2026-10-05 17:16:13', '2026-10-05 17:16:13'),
	(14, 'Supervisor novo', 14, NULL, NULL, '$2y$12$RnahwKs3I8Wo0vNVGZBtKOjEpdgo5z./icE58LynxNN4a/Gume0kS', 0, 1, NULL, '2026-10-05 17:16:31', '2026-10-05 17:16:31'),
	(15, 'Operador 1', 15, NULL, NULL, '$2y$12$f3DSJONOLS3WjDlQJFEXOe1noZkVkVfNdzte8zSf4N5Xe88LTo5Sa', 0, 1, NULL, '2026-10-16 16:25:32', '2026-10-16 16:25:32'),
	(16, 'Técnico Teste', 16, NULL, NULL, '$2y$12$4u/B9y5iaHSb.id7gKE6O.GoeNUoVWf5Gsp7HiU/xsENRiYMOP0jq', 0, 1, NULL, '2026-10-18 00:09:45', '2026-10-18 00:09:45');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
