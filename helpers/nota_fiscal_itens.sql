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

-- Copiando estrutura para tabela pdv_fiscal.nota_fiscal_itens
CREATE TABLE IF NOT EXISTS `nota_fiscal_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nota_fiscal_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_chave_acesso` varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_nitem` int unsigned DEFAULT NULL,
  `bc_icms_manual` decimal(12,2) DEFAULT NULL,
  `valor_icms_manual` decimal(12,2) DEFAULT NULL,
  `aliquota_icms_manual` decimal(8,4) DEFAULT NULL,
  `valor_ipi_manual` decimal(12,2) DEFAULT NULL,
  `aliquota_ipi_manual` decimal(8,4) DEFAULT NULL,
  `ncm_id` bigint unsigned DEFAULT NULL,
  `cest_id` bigint unsigned DEFAULT NULL,
  `class_trib_ibs_cbs_id` bigint unsigned DEFAULT NULL,
  `tributacao_id` bigint unsigned DEFAULT NULL,
  `pis_cofins_id` bigint unsigned DEFAULT NULL,
  `ipi_id` bigint unsigned DEFAULT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `valor_unitario` decimal(12,4) NOT NULL,
  `valor_desconto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `valor_outras_despesas` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_frete` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nota_fiscal_itens_nota_fiscal_id_foreign` (`nota_fiscal_id`),
  KEY `nota_fiscal_itens_produto_id_foreign` (`produto_id`),
  KEY `nota_fiscal_itens_ncm_id_foreign` (`ncm_id`),
  KEY `nota_fiscal_itens_cest_id_foreign` (`cest_id`),
  KEY `nota_fiscal_itens_class_trib_ibs_cbs_id_foreign` (`class_trib_ibs_cbs_id`),
  KEY `nota_fiscal_itens_tributacao_id_foreign` (`tributacao_id`),
  KEY `nota_fiscal_itens_pis_cofins_id_foreign` (`pis_cofins_id`),
  KEY `nota_fiscal_itens_ipi_id_foreign` (`ipi_id`),
  KEY `nota_fiscal_itens_produto_variante_id_foreign` (`produto_variante_id`),
  CONSTRAINT `nota_fiscal_itens_cest_id_foreign` FOREIGN KEY (`cest_id`) REFERENCES `cests` (`id`),
  CONSTRAINT `nota_fiscal_itens_class_trib_ibs_cbs_id_foreign` FOREIGN KEY (`class_trib_ibs_cbs_id`) REFERENCES `classificacoes_tributarias` (`id`),
  CONSTRAINT `nota_fiscal_itens_ipi_id_foreign` FOREIGN KEY (`ipi_id`) REFERENCES `classificacoes_ipi` (`id`),
  CONSTRAINT `nota_fiscal_itens_ncm_id_foreign` FOREIGN KEY (`ncm_id`) REFERENCES `ncms` (`id`),
  CONSTRAINT `nota_fiscal_itens_nota_fiscal_id_foreign` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `notas_fiscais` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nota_fiscal_itens_pis_cofins_id_foreign` FOREIGN KEY (`pis_cofins_id`) REFERENCES `classificacoes_pis_cofins` (`id`),
  CONSTRAINT `nota_fiscal_itens_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  CONSTRAINT `nota_fiscal_itens_produto_variante_id_foreign` FOREIGN KEY (`produto_variante_id`) REFERENCES `produto_variantes` (`id`),
  CONSTRAINT `nota_fiscal_itens_tributacao_id_foreign` FOREIGN KEY (`tributacao_id`) REFERENCES `tributacoes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=230 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exportação de dados foi desmarcado.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
