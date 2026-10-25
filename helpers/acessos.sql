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

-- Copiando estrutura para tabela pdv_fiscal.acessos
CREATE TABLE IF NOT EXISTS `acessos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `tipo_operador_id` bigint unsigned NOT NULL,
  `permissoes` json DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acessos_user_id_tipo_operador_id_unique` (`user_id`,`tipo_operador_id`),
  KEY `acessos_tipo_operador_id_foreign` (`tipo_operador_id`),
  CONSTRAINT `acessos_tipo_operador_id_foreign` FOREIGN KEY (`tipo_operador_id`) REFERENCES `tipos_operador` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `acessos_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copiando dados para a tabela pdv_fiscal.acessos: ~15 rows (aproximadamente)
REPLACE INTO `acessos` (`id`, `user_id`, `tipo_operador_id`, `permissoes`, `ativo`, `created_at`, `updated_at`) VALUES
	(1, 2, 3, '{"produtos": "consulta"}', 1, '2026-10-05 11:53:15', '2026-10-05 16:19:38'),
	(2, 4, 1, NULL, 0, '2026-10-05 11:53:15', '2026-10-16 16:25:06'),
	(3, 5, 1, NULL, 0, '2026-10-05 11:53:15', '2026-10-16 16:25:15'),
	(4, 6, 2, NULL, 1, '2026-10-05 11:53:15', '2026-10-05 12:25:12'),
	(5, 7, 2, NULL, 1, '2026-10-05 11:53:15', '2026-10-05 11:53:15'),
	(6, 8, 2, NULL, 1, '2026-10-05 11:53:15', '2026-10-05 11:53:15'),
	(7, 9, 2, NULL, 1, '2026-10-05 11:53:15', '2026-10-05 11:53:15'),
	(8, 10, 3, NULL, 0, '2026-10-05 11:53:15', '2026-10-05 12:26:37'),
	(9, 11, 1, NULL, 0, '2026-10-05 12:23:58', '2026-10-16 16:25:12'),
	(10, 12, 2, NULL, 1, '2026-10-05 12:24:22', '2026-10-05 12:24:22'),
	(11, 10, 1, NULL, 0, '2026-10-05 12:25:48', '2026-10-16 16:25:20'),
	(12, 13, 1, NULL, 0, '2026-10-05 17:16:13', '2026-10-16 16:25:18'),
	(13, 14, 2, '{"cancelar_nfce": "nao_libera"}', 1, '2026-10-05 17:16:31', '2026-10-05 17:17:54'),
	(14, 15, 1, NULL, 1, '2026-10-16 16:25:32', '2026-10-16 16:25:32'),
	(15, 16, 4, NULL, 1, '2026-10-18 00:09:45', '2026-10-18 00:09:45');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
