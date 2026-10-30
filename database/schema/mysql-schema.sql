/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `acessos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acessos` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caixa_ocorrencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caixa_ocorrencias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `caixa_uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venda_uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `acao` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(12,2) NOT NULL DEFAULT '0.00',
  `operador_id` bigint unsigned DEFAULT NULL,
  `supervisor_id` bigint unsigned DEFAULT NULL,
  `supervisor_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `liberado_operador` tinyint(1) NOT NULL DEFAULT '0',
  `ocorrida_em` timestamp NULL DEFAULT NULL,
  `maquina` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `local_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `caixa_ocorrencias_maquina_local_id_unique` (`maquina`,`local_id`),
  KEY `caixa_ocorrencias_caixa_uuid_index` (`caixa_uuid`),
  KEY `caixa_ocorrencias_venda_uuid_index` (`venda_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `caixas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caixas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operador_id` bigint unsigned NOT NULL,
  `pdv_id` bigint unsigned DEFAULT NULL,
  `data_abertura` timestamp NOT NULL,
  `valor_abertura` decimal(12,2) NOT NULL,
  `data_fechamento` timestamp NULL DEFAULT NULL,
  `valor_fechamento_informado` decimal(12,2) DEFAULT NULL,
  `valor_fechamento_esperado` decimal(12,2) DEFAULT NULL,
  `status` enum('aberto','fechado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberto',
  `observacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `caixas_uuid_unique` (`uuid`),
  KEY `caixas_operador_id_foreign` (`operador_id`),
  KEY `caixas_pdv_id_foreign` (`pdv_id`),
  CONSTRAINT `caixas_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`),
  CONSTRAINT `caixas_pdv_id_foreign` FOREIGN KEY (`pdv_id`) REFERENCES `pdvs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categorias_nome_unique` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cest_ncm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cest_ncm` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cest_id` bigint unsigned NOT NULL,
  `ncm` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cest_ncm_cest_id_ncm_unique` (`cest_id`,`ncm`),
  KEY `cest_ncm_ncm_index` (`ncm`),
  CONSTRAINT `cest_ncm_cest_id_foreign` FOREIGN KEY (`cest_id`) REFERENCES `cests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `segmento_codigo` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `segmento_descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cests_codigo_unique` (`codigo`),
  KEY `cests_segmento_codigo_index` (`segmento_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cfop_entrada_conversoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cfop_entrada_conversoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operacao_entrada_id` bigint unsigned NOT NULL,
  `cfop_origem` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cfop_entrada_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cfop_entrada_conversoes_operacao_entrada_id_cfop_origem_unique` (`operacao_entrada_id`,`cfop_origem`),
  KEY `cfop_entrada_conversoes_cfop_entrada_id_foreign` (`cfop_entrada_id`),
  CONSTRAINT `cfop_entrada_conversoes_cfop_entrada_id_foreign` FOREIGN KEY (`cfop_entrada_id`) REFERENCES `cfops_entrada` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `cfop_entrada_conversoes_operacao_entrada_id_foreign` FOREIGN KEY (`operacao_entrada_id`) REFERENCES `operacoes_entrada` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cfop_saida`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cfop_saida` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_operacao` enum('entrada','saida') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'saida',
  `natureza_operacao_padrao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finalidade_padrao` tinyint unsigned NOT NULL DEFAULT '1',
  `ordem` int unsigned DEFAULT NULL,
  `movimenta_estoque` tinyint(1) NOT NULL DEFAULT '1',
  `destacar_bases` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cfop_saida_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cfops_entrada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cfops_entrada` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` char(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cfops_entrada_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classificacoes_ipi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificacoes_ipi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cenq` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '999',
  `aliquota` decimal(6,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classificacoes_ipi_codigo_index` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classificacoes_pis_cofins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificacoes_pis_cofins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `aliquota_pis` decimal(6,4) DEFAULT NULL,
  `aliquota_cofins` decimal(6,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classificacoes_pis_cofins_codigo_index` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `classificacoes_tributarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classificacoes_tributarias` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cst_codigo` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cst_descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `percentual_reducao_ibs` decimal(5,2) DEFAULT NULL,
  `percentual_reducao_cbs` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classificacoes_tributarias_codigo_unique` (`codigo`),
  KEY `classificacoes_tributarias_cst_codigo_index` (`cst_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo_pessoa` enum('fisica','juridica') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_fantasia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cpf_cnpj` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `indicador_ie` enum('contribuinte','isento','nao_contribuinte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_contribuinte',
  `ie` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logradouro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipio` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_municipio` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clientes_cpf_cnpj_unique` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `condutores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `condutores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpf` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `condutores_cpf_unique` (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contagem_estoque_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contagem_estoque_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `contagem_estoque_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `saldo_sistema` decimal(15,3) NOT NULL,
  `quantidade_contada` decimal(15,3) NOT NULL,
  `diferenca` decimal(15,3) NOT NULL,
  `custo_unitario` decimal(15,6) NOT NULL DEFAULT '0.000000',
  `valor_diferenca` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contagem_item_unico` (`contagem_estoque_id`,`produto_id`,`produto_variante_id`),
  KEY `contagem_estoque_itens_produto_id_foreign` (`produto_id`),
  KEY `contagem_estoque_itens_produto_variante_id_foreign` (`produto_variante_id`),
  CONSTRAINT `contagem_estoque_itens_contagem_estoque_id_foreign` FOREIGN KEY (`contagem_estoque_id`) REFERENCES `contagens_estoque` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contagem_estoque_itens_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  CONSTRAINT `contagem_estoque_itens_produto_variante_id_foreign` FOREIGN KEY (`produto_variante_id`) REFERENCES `produto_variantes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contagens_estoque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contagens_estoque` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `data` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `arquivo_nome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `aplicada_em` timestamp NULL DEFAULT NULL,
  `aplicada_por` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contagens_estoque_user_id_foreign` (`user_id`),
  KEY `contagens_estoque_aplicada_por_foreign` (`aplicada_por`),
  CONSTRAINT `contagens_estoque_aplicada_por_foreign` FOREIGN KEY (`aplicada_por`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contagens_estoque_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contas_pagar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas_pagar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo_conta_pagar_id` bigint unsigned NOT NULL,
  `fornecedor_id` bigint unsigned DEFAULT NULL,
  `entrada_nota_id` bigint unsigned DEFAULT NULL,
  `descricao` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_conta` date NOT NULL,
  `valor_total` decimal(12,2) NOT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberta',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contas_pagar_tipo_conta_pagar_id_foreign` (`tipo_conta_pagar_id`),
  KEY `contas_pagar_fornecedor_id_foreign` (`fornecedor_id`),
  KEY `contas_pagar_status_index` (`status`),
  KEY `contas_pagar_data_conta_index` (`data_conta`),
  KEY `contas_pagar_entrada_nota_id_index` (`entrada_nota_id`),
  CONSTRAINT `contas_pagar_fornecedor_id_foreign` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contas_pagar_tipo_conta_pagar_id_foreign` FOREIGN KEY (`tipo_conta_pagar_id`) REFERENCES `tipos_conta_pagar` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contas_pagar_parcelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas_pagar_parcelas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conta_pagar_id` bigint unsigned NOT NULL,
  `numero` smallint unsigned NOT NULL,
  `vencimento` date NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `data_pagamento` date DEFAULT NULL,
  `valor_pago` decimal(12,2) DEFAULT NULL,
  `forma_pagamento_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contas_pagar_parcelas_conta_pagar_id_foreign` (`conta_pagar_id`),
  KEY `contas_pagar_parcelas_vencimento_data_pagamento_index` (`vencimento`,`data_pagamento`),
  CONSTRAINT `contas_pagar_parcelas_conta_pagar_id_foreign` FOREIGN KEY (`conta_pagar_id`) REFERENCES `contas_pagar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contas_receber`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas_receber` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo_conta_receber_id` bigint unsigned NOT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `venda_id` bigint unsigned DEFAULT NULL,
  `descricao` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_conta` date NOT NULL,
  `valor_total` decimal(12,2) NOT NULL,
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberta',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contas_receber_tipo_conta_receber_id_foreign` (`tipo_conta_receber_id`),
  KEY `contas_receber_cliente_id_foreign` (`cliente_id`),
  KEY `contas_receber_status_index` (`status`),
  KEY `contas_receber_data_conta_index` (`data_conta`),
  KEY `contas_receber_venda_id_index` (`venda_id`),
  CONSTRAINT `contas_receber_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contas_receber_tipo_conta_receber_id_foreign` FOREIGN KEY (`tipo_conta_receber_id`) REFERENCES `tipos_conta_receber` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contas_receber_parcelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas_receber_parcelas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conta_receber_id` bigint unsigned NOT NULL,
  `numero` smallint unsigned NOT NULL,
  `vencimento` date NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `data_recebimento` date DEFAULT NULL,
  `valor_recebido` decimal(12,2) DEFAULT NULL,
  `forma_pagamento_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contas_receber_parcelas_conta_receber_id_foreign` (`conta_receber_id`),
  KEY `contas_receber_parcelas_vencimento_data_recebimento_index` (`vencimento`,`data_recebimento`),
  CONSTRAINT `contas_receber_parcelas_conta_receber_id_foreign` FOREIGN KEY (`conta_receber_id`) REFERENCES `contas_receber` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credenciadora_meios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credenciadora_meios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `credenciadora_id` bigint unsigned NOT NULL,
  `meio_pagamento` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credenciadora_meios_meio_pagamento_unique` (`meio_pagamento`),
  KEY `credenciadora_meios_credenciadora_id_foreign` (`credenciadora_id`),
  CONSTRAINT `credenciadora_meios_credenciadora_id_foreign` FOREIGN KEY (`credenciadora_id`) REFERENCES `credenciadoras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credenciadoras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credenciadoras` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cep` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logradouro` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipio` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_municipio` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credenciadoras_cnpj_unique` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cst_entrada_conversoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cst_entrada_conversoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `regime` enum('simples','normal') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_origem` enum('cst','csosn') COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_origem` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_entrada` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gera_credito` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cst_entrada_conversoes_regime_tipo_origem_codigo_origem_unique` (`regime`,`tipo_origem`,`codigo_origem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `empresa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `empresa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cnpj` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `razao_social` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_fantasia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ie` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `im` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `crt` tinyint unsigned NOT NULL DEFAULT '1',
  `rntrc` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logradouro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `complemento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cep` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipio` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_municipio` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `certificado_base64` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `certificado_senha` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificado_validade` date DEFAULT NULL,
  `serie_nfe` int unsigned NOT NULL DEFAULT '1',
  `numero_atual_nfe` bigint unsigned NOT NULL DEFAULT '0',
  `ambiente` tinyint unsigned NOT NULL DEFAULT '2',
  `ambiente_nfse` tinyint unsigned NOT NULL DEFAULT '2',
  `serie_dps` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `ultimo_numero_dps` bigint unsigned NOT NULL DEFAULT '0',
  `nfse_mei` tinyint(1) NOT NULL DEFAULT '0',
  `nfse_regime_especial` tinyint unsigned NOT NULL DEFAULT '0',
  `nfse_informar_im` tinyint(1) NOT NULL DEFAULT '0',
  `nfse_reg_ap_trib_sn` tinyint unsigned NOT NULL DEFAULT '1',
  `nfse_p_tot_trib_sn` decimal(4,2) NOT NULL DEFAULT '0.00',
  `nfse_informar_ibscbs` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `entrada_tributacao_padrao_id` bigint unsigned DEFAULT NULL,
  `entrada_tributacao_st_padrao_id` bigint unsigned DEFAULT NULL,
  `entrada_pis_cofins_padrao_id` bigint unsigned DEFAULT NULL,
  `entrada_margem_padrao` decimal(6,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_cnpj_unique` (`cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entrada_nota_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entrada_nota_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entrada_nota_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `codigo_fornecedor` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `qtd_por_caixa` int unsigned NOT NULL DEFAULT '1',
  `valor_unitario` decimal(14,4) NOT NULL,
  `valor_desconto` decimal(14,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(14,2) NOT NULL,
  `valor_outras_despesas` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_frete` decimal(15,2) NOT NULL DEFAULT '0.00',
  `bc_icms` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_icms` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_icms` decimal(7,4) NOT NULL DEFAULT '0.0000',
  `valor_ipi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_ipi` decimal(7,4) NOT NULL DEFAULT '0.0000',
  `lote` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cfop_origem` char(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cst_origem` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `csosn_origem` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem_mercadoria` tinyint unsigned DEFAULT NULL,
  `cfop_entrada_id` bigint unsigned DEFAULT NULL,
  `cst_csosn_entrada` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gera_credito` tinyint(1) DEFAULT NULL,
  `fiscal_manual` tinyint(1) NOT NULL DEFAULT '0',
  `cst_pis_cofins` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nat_bc_cred` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bc_pis_cofins` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_pis` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `aliquota_cofins` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `valor_pis` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_cofins` decimal(15,2) NOT NULL DEFAULT '0.00',
  `pis_cofins_manual` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `entrada_nota_itens_entrada_nota_id_foreign` (`entrada_nota_id`),
  KEY `entrada_nota_itens_produto_id_foreign` (`produto_id`),
  KEY `entrada_nota_itens_cfop_entrada_id_foreign` (`cfop_entrada_id`),
  CONSTRAINT `entrada_nota_itens_cfop_entrada_id_foreign` FOREIGN KEY (`cfop_entrada_id`) REFERENCES `cfops_entrada` (`id`) ON DELETE SET NULL,
  CONSTRAINT `entrada_nota_itens_entrada_nota_id_foreign` FOREIGN KEY (`entrada_nota_id`) REFERENCES `entradas_nota` (`id`) ON DELETE CASCADE,
  CONSTRAINT `entrada_nota_itens_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `entradas_nota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entradas_nota` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fornecedor_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `tipo_entrada` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `chave_acesso` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '55',
  `serie` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_emissao` date NOT NULL,
  `data_entrada` date NOT NULL,
  `natureza_operacao` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_produtos` decimal(14,2) NOT NULL DEFAULT '0.00',
  `valor_frete` decimal(14,2) NOT NULL DEFAULT '0.00',
  `valor_desconto` decimal(14,2) NOT NULL DEFAULT '0.00',
  `valor_outras` decimal(14,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `valor_total_xml` decimal(15,2) DEFAULT NULL,
  `atualizar_custo` tinyint(1) NOT NULL DEFAULT '1',
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `finalizada_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `operacao_entrada_id` bigint unsigned DEFAULT NULL,
  `forma_pagamento_id` bigint unsigned DEFAULT NULL,
  `duplicatas` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entradas_nota_chave_acesso_unique` (`chave_acesso`),
  KEY `entradas_nota_user_id_foreign` (`user_id`),
  KEY `entradas_nota_fornecedor_id_modelo_serie_numero_index` (`fornecedor_id`,`modelo`,`serie`,`numero`),
  KEY `entradas_nota_status_data_entrada_index` (`status`,`data_entrada`),
  KEY `entradas_nota_operacao_entrada_id_foreign` (`operacao_entrada_id`),
  KEY `entradas_nota_forma_pagamento_id_foreign` (`forma_pagamento_id`),
  CONSTRAINT `entradas_nota_forma_pagamento_id_foreign` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL,
  CONSTRAINT `entradas_nota_fornecedor_id_foreign` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`),
  CONSTRAINT `entradas_nota_operacao_entrada_id_foreign` FOREIGN KEY (`operacao_entrada_id`) REFERENCES `operacoes_entrada` (`id`) ON DELETE SET NULL,
  CONSTRAINT `entradas_nota_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `formas_pagamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `formas_pagamento` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `meio_pagamento` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '99',
  `ind_pag` tinyint NOT NULL DEFAULT '0',
  `ordem` int unsigned DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `uso_saida` tinyint(1) NOT NULL DEFAULT '1',
  `uso_entrada` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fornecedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fornecedores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo_pessoa` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'juridica',
  `cpf_cnpj` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_fantasia` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `indicador_ie` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nao_contribuinte',
  `ie` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logradouro` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `complemento` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bairro` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `municipio` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_municipio` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fornecedores_cnpj_cpf_unique` (`cpf_cnpj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `grupos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grupos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grupos_nome_unique` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inutilizacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inutilizacoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pdv_id` bigint unsigned NOT NULL,
  `serie` int unsigned NOT NULL,
  `numero_inicial` bigint unsigned NOT NULL,
  `numero_final` bigint unsigned NOT NULL,
  `justificativa` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sucesso','erro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'erro',
  `protocolo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `operador_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inutilizacoes_pdv_id_foreign` (`pdv_id`),
  KEY `inutilizacoes_operador_id_foreign` (`operador_id`),
  CONSTRAINT `inutilizacoes_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`),
  CONSTRAINT `inutilizacoes_pdv_id_foreign` FOREIGN KEY (`pdv_id`) REFERENCES `pdvs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inutilizacoes_nfe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inutilizacoes_nfe` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serie_nfe_id` bigint unsigned NOT NULL,
  `serie` int unsigned NOT NULL,
  `numero_inicial` bigint unsigned NOT NULL,
  `numero_final` bigint unsigned NOT NULL,
  `justificativa` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('sucesso','erro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sucesso',
  `protocolo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `motivo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operador_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inutilizacoes_nfe_serie_nfe_id_foreign` (`serie_nfe_id`),
  KEY `inutilizacoes_nfe_operador_id_foreign` (`operador_id`),
  CONSTRAINT `inutilizacoes_nfe_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`),
  CONSTRAINT `inutilizacoes_nfe_serie_nfe_id_foreign` FOREIGN KEY (`serie_nfe_id`) REFERENCES `series_nfe` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventario_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventario_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `inventario_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `unidade` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade_calculada` decimal(15,3) NOT NULL DEFAULT '0.000',
  `quantidade` decimal(15,3) NOT NULL DEFAULT '0.000',
  `custo_unitario` decimal(15,6) NOT NULL DEFAULT '0.000000',
  `valor_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventario_itens_inventario_id_produto_id_unique` (`inventario_id`,`produto_id`),
  KEY `inventario_itens_produto_id_foreign` (`produto_id`),
  CONSTRAINT `inventario_itens_inventario_id_foreign` FOREIGN KEY (`inventario_id`) REFERENCES `inventarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventario_itens_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `inventarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventarios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `data` date NOT NULL,
  `motivo` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01',
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `incluir_zerados` tinyint(1) NOT NULL DEFAULT '0',
  `valor_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `fechado_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventarios_user_id_foreign` (`user_id`),
  KEY `inventarios_status_data_index` (`status`,`data`),
  CONSTRAINT `inventarios_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lista_servico_nacional`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lista_servico_nacional` (
  `codigo` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item` tinyint unsigned NOT NULL,
  `subitem` tinyint unsigned NOT NULL,
  `desdobro` tinyint unsigned NOT NULL,
  `descricao` varchar(600) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marcas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marcas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marcas_nome_unique` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mdfe_condutores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mdfe_condutores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mdfe_id` bigint unsigned NOT NULL,
  `mdfe_veiculo_id` bigint unsigned NOT NULL,
  `condutor_id` bigint unsigned DEFAULT NULL,
  `nome` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpf` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mdfe_condutores_mdfe_id_foreign` (`mdfe_id`),
  KEY `mdfe_condutores_mdfe_veiculo_id_foreign` (`mdfe_veiculo_id`),
  KEY `mdfe_condutores_condutor_id_foreign` (`condutor_id`),
  CONSTRAINT `mdfe_condutores_condutor_id_foreign` FOREIGN KEY (`condutor_id`) REFERENCES `condutores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mdfe_condutores_mdfe_id_foreign` FOREIGN KEY (`mdfe_id`) REFERENCES `mdfes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mdfe_condutores_mdfe_veiculo_id_foreign` FOREIGN KEY (`mdfe_veiculo_id`) REFERENCES `mdfe_veiculos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mdfe_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mdfe_documentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mdfe_id` bigint unsigned NOT NULL,
  `mdfe_municipio_id` bigint unsigned NOT NULL,
  `tipo` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nfe',
  `nota_fiscal_id` bigint unsigned DEFAULT NULL,
  `chave_acesso` varchar(44) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(15,2) NOT NULL DEFAULT '0.00',
  `peso_bruto_kg` decimal(15,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY (`id`),
  KEY `mdfe_documentos_mdfe_id_foreign` (`mdfe_id`),
  KEY `mdfe_documentos_mdfe_municipio_id_foreign` (`mdfe_municipio_id`),
  KEY `mdfe_documentos_nota_fiscal_id_foreign` (`nota_fiscal_id`),
  KEY `mdfe_documentos_chave_acesso_index` (`chave_acesso`),
  CONSTRAINT `mdfe_documentos_mdfe_id_foreign` FOREIGN KEY (`mdfe_id`) REFERENCES `mdfes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mdfe_documentos_mdfe_municipio_id_foreign` FOREIGN KEY (`mdfe_municipio_id`) REFERENCES `mdfe_municipios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mdfe_documentos_nota_fiscal_id_foreign` FOREIGN KEY (`nota_fiscal_id`) REFERENCES `notas_fiscais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mdfe_eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mdfe_eventos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mdfe_id` bigint unsigned NOT NULL,
  `tipo` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_evento` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sequencia` tinyint unsigned NOT NULL DEFAULT '1',
  `justificativa` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dados` json DEFAULT NULL,
  `protocolo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xml` longtext COLLATE utf8mb4_unicode_ci,
  `operador_id` bigint unsigned DEFAULT NULL,
  `registrado_em` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mdfe_eventos_mdfe_id_foreign` (`mdfe_id`),
  KEY `mdfe_eventos_operador_id_foreign` (`operador_id`),
  CONSTRAINT `mdfe_eventos_mdfe_id_foreign` FOREIGN KEY (`mdfe_id`) REFERENCES `mdfes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mdfe_eventos_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mdfe_municipios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mdfe_municipios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mdfe_id` bigint unsigned NOT NULL,
  `tipo` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_ibge` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `mdfe_municipios_mdfe_id_foreign` (`mdfe_id`),
  CONSTRAINT `mdfe_municipios_mdfe_id_foreign` FOREIGN KEY (`mdfe_id`) REFERENCES `mdfes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mdfe_percursos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mdfe_percursos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mdfe_id` bigint unsigned NOT NULL,
  `uf` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ordem` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `mdfe_percursos_mdfe_id_foreign` (`mdfe_id`),
  CONSTRAINT `mdfe_percursos_mdfe_id_foreign` FOREIGN KEY (`mdfe_id`) REFERENCES `mdfes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mdfe_veiculos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mdfe_veiculos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mdfe_id` bigint unsigned NOT NULL,
  `papel` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `veiculo_id` bigint unsigned DEFAULT NULL,
  `ordem` tinyint unsigned NOT NULL DEFAULT '0',
  `placa` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rntrc` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tara_kg` int unsigned DEFAULT NULL,
  `capacidade_kg` int unsigned DEFAULT NULL,
  `capacidade_m3` int unsigned DEFAULT NULL,
  `tipo_rodado` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_carroceria` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proprietario_tipo` tinyint unsigned DEFAULT NULL,
  `proprietario_documento` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proprietario_nome` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proprietario_ie` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proprietario_uf` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `proprietario_rntrc` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mdfe_veiculos_mdfe_id_foreign` (`mdfe_id`),
  KEY `mdfe_veiculos_veiculo_id_foreign` (`veiculo_id`),
  CONSTRAINT `mdfe_veiculos_mdfe_id_foreign` FOREIGN KEY (`mdfe_id`) REFERENCES `mdfes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mdfe_veiculos_veiculo_id_foreign` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `mdfes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mdfes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serie_mdfe_id` bigint unsigned DEFAULT NULL,
  `serie` smallint unsigned DEFAULT NULL,
  `numero` int unsigned DEFAULT NULL,
  `codigo_numerico` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `tipo_emitente` tinyint unsigned NOT NULL DEFAULT '2',
  `tipo_transportador` tinyint unsigned DEFAULT NULL,
  `operador_id` bigint unsigned DEFAULT NULL,
  `uf_inicio` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uf_fim` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `inicio_viagem` datetime DEFAULT NULL,
  `rntrc` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade_nfe` smallint unsigned NOT NULL DEFAULT '0',
  `valor_carga` decimal(15,2) NOT NULL DEFAULT '0.00',
  `peso_carga_kg` decimal(15,3) NOT NULL DEFAULT '0.000',
  `informacoes_adicionais` text COLLATE utf8mb4_unicode_ci,
  `informacoes_fisco` text COLLATE utf8mb4_unicode_ci,
  `chave_acesso` varchar(44) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protocolo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emitido_em` datetime DEFAULT NULL,
  `xml` longtext COLLATE utf8mb4_unicode_ci,
  `motivo_rejeicao` text COLLATE utf8mb4_unicode_ci,
  `encerrado_em` datetime DEFAULT NULL,
  `protocolo_encerramento` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelado_em` datetime DEFAULT NULL,
  `motivo_cancelamento` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protocolo_cancelamento` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mdfes_serie_numero_unique` (`serie`,`numero`),
  UNIQUE KEY `mdfes_chave_acesso_unique` (`chave_acesso`),
  KEY `mdfes_serie_mdfe_id_foreign` (`serie_mdfe_id`),
  KEY `mdfes_operador_id_foreign` (`operador_id`),
  KEY `mdfes_status_index` (`status`),
  CONSTRAINT `mdfes_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `mdfes_serie_mdfe_id_foreign` FOREIGN KEY (`serie_mdfe_id`) REFERENCES `series_mdfe` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `movimentacoes_estoque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimentacoes_estoque` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `variacao` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `saldo_anterior` decimal(12,3) NOT NULL,
  `saldo_posterior` decimal(12,3) NOT NULL,
  `custo_unitario` decimal(12,4) DEFAULT NULL,
  `documento_tipo` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documento_rotulo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `movimentacoes_estoque_created_at_index` (`created_at`),
  KEY `movimentacoes_estoque_tipo_index` (`tipo`),
  KEY `mov_estoque_produto_idx` (`produto_id`,`produto_variante_id`,`created_at`),
  KEY `mov_estoque_documento_idx` (`documento_tipo`,`documento_id`),
  CONSTRAINT `movimentacoes_estoque_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `municipios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `municipios` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo_ibge` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_busca` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `municipios_codigo_ibge_unique` (`codigo_ibge`),
  KEY `municipios_nome_busca_index` (`nome_busca`),
  KEY `municipios_uf_index` (`uf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ncms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ncms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cadastro_avulso` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ncms_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nfse_emissoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nfse_emissoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ordem_servico_id` bigint unsigned DEFAULT NULL,
  `servico_id` bigint unsigned DEFAULT NULL,
  `ambiente` tinyint unsigned NOT NULL,
  `serie` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` bigint unsigned NOT NULL,
  `dps_id` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enviando',
  `valor_servico` decimal(12,2) NOT NULL DEFAULT '0.00',
  `xml_dps` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `xml_nfse` longtext COLLATE utf8mb4_unicode_ci,
  `chave_acesso` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_nfse` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci,
  `enviada_em` timestamp NULL DEFAULT NULL,
  `autorizada_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nfse_emissoes_ambiente_dps_id_unique` (`ambiente`,`dps_id`),
  KEY `nfse_emissoes_ordem_servico_id_index` (`ordem_servico_id`),
  KEY `nfse_emissoes_status_index` (`status`),
  KEY `nfse_emissoes_chave_acesso_index` (`chave_acesso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `nota_fiscal_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nota_fiscal_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nota_fiscal_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_chave_acesso` varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_nitem` int unsigned DEFAULT NULL,
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
  `cfop` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cst_csosn` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `desconto_efetivo` decimal(15,2) NOT NULL DEFAULT '0.00',
  `bc_icms` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_icms` decimal(7,4) NOT NULL DEFAULT '0.0000',
  `valor_icms` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_pis` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_cofins` decimal(15,2) NOT NULL DEFAULT '0.00',
  `cst_pis_cofins` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bc_pis_cofins` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_pis` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `aliquota_cofins` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `valor_ipi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_ipi` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `impostos_manuais` tinyint(1) NOT NULL DEFAULT '0',
  `valor_ibs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_cbs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `impostos_registrados_em` timestamp NULL DEFAULT NULL,
  `custo_unitario` decimal(15,4) DEFAULT NULL,
  `valor_custo` decimal(15,2) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notas_fiscais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notas_fiscais` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cliente_id` bigint unsigned NOT NULL,
  `operador_id` bigint unsigned NOT NULL,
  `serie_nfe_id` bigint unsigned DEFAULT NULL,
  `modelo` tinyint unsigned NOT NULL DEFAULT '55',
  `serie` int unsigned DEFAULT NULL,
  `numero` bigint unsigned DEFAULT NULL,
  `natureza_operacao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `finalidade` tinyint unsigned NOT NULL DEFAULT '1',
  `motivo_ajuste` varchar(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cfop_saida_id` bigint unsigned DEFAULT NULL,
  `forma_pagamento_id` bigint unsigned DEFAULT NULL,
  `tipo_operacao` enum('entrada','saida') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'saida',
  `origem_tipo` enum('manual','venda','pedido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `venda_id` bigint unsigned DEFAULT NULL,
  `status` enum('rascunho','emitida','cancelada','contingencia','inutilizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'rascunho',
  `chave_acesso` varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protocolo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protocolo_cancelamento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xml` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motivo_cancelamento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motivo_rejeicao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `emitida_em` timestamp NULL DEFAULT NULL,
  `cancelado_em` timestamp NULL DEFAULT NULL,
  `valor_produtos` decimal(12,2) NOT NULL DEFAULT '0.00',
  `valor_desconto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `valor_frete` decimal(12,2) NOT NULL DEFAULT '0.00',
  `frete_por_item` tinyint(1) NOT NULL DEFAULT '0',
  `mod_frete` tinyint unsigned NOT NULL DEFAULT '9',
  `transportador_id` bigint unsigned DEFAULT NULL,
  `veiculo_id` bigint unsigned DEFAULT NULL,
  `informacoes_complementares` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notas_referenciadas` json DEFAULT NULL,
  `valor_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `vol_quantidade` int unsigned DEFAULT NULL,
  `vol_especie` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vol_marca` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vol_numeracao` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vol_peso_liquido` decimal(15,3) DEFAULT NULL,
  `vol_peso_bruto` decimal(15,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notas_fiscais_cliente_id_foreign` (`cliente_id`),
  KEY `notas_fiscais_operador_id_foreign` (`operador_id`),
  KEY `notas_fiscais_serie_nfe_id_foreign` (`serie_nfe_id`),
  KEY `notas_fiscais_venda_id_foreign` (`venda_id`),
  KEY `notas_fiscais_cfop_saida_id_foreign` (`cfop_saida_id`),
  KEY `notas_fiscais_forma_pagamento_id_foreign` (`forma_pagamento_id`),
  KEY `notas_fiscais_transportador_id_foreign` (`transportador_id`),
  KEY `notas_fiscais_veiculo_id_foreign` (`veiculo_id`),
  CONSTRAINT `notas_fiscais_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `notas_fiscais_forma_pagamento_id_foreign` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`),
  CONSTRAINT `notas_fiscais_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`),
  CONSTRAINT `notas_fiscais_serie_nfe_id_foreign` FOREIGN KEY (`serie_nfe_id`) REFERENCES `series_nfe` (`id`),
  CONSTRAINT `notas_fiscais_transportador_id_foreign` FOREIGN KEY (`transportador_id`) REFERENCES `transportadores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notas_fiscais_veiculo_id_foreign` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notas_fiscais_venda_id_foreign` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `operacoes_entrada`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `operacoes_entrada` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `movimenta_estoque` tinyint(1) NOT NULL DEFAULT '1',
  `ordem` smallint unsigned NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `operacoes_entrada_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ordem_servico_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordem_servico_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ordem_servico_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned DEFAULT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `codigo_barras` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
  `quantidade` decimal(12,3) NOT NULL,
  `valor_unitario` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ordem_servico_itens_ordem_servico_id_foreign` (`ordem_servico_id`),
  CONSTRAINT `ordem_servico_itens_ordem_servico_id_foreign` FOREIGN KEY (`ordem_servico_id`) REFERENCES `ordens_servico` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ordens_servico`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ordens_servico` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` int unsigned NOT NULL,
  `data_abertura` date NOT NULL,
  `previsao_entrega` date DEFAULT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `cliente_nome` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_telefone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_documento` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operador_id` bigint unsigned NOT NULL,
  `tecnico_id` bigint unsigned DEFAULT NULL,
  `forma_pagamento_id` bigint unsigned DEFAULT NULL,
  `status` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberta',
  `prioridade` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `equipamento` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `marca` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modelo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numero_serie` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acessorios` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `defeito_relatado` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `condicao_entrada` text COLLATE utf8mb4_unicode_ci,
  `laudo_tecnico` text COLLATE utf8mb4_unicode_ci,
  `servico_executado` text COLLATE utf8mb4_unicode_ci,
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `termo_responsabilidade` text COLLATE utf8mb4_unicode_ci,
  `garantia_dias` smallint unsigned DEFAULT NULL,
  `valor_servico` decimal(12,2) NOT NULL DEFAULT '0.00',
  `desconto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `concluida_em` timestamp NULL DEFAULT NULL,
  `entregue_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ordens_servico_numero_unique` (`numero`),
  KEY `ordens_servico_cliente_id_index` (`cliente_id`),
  KEY `ordens_servico_operador_id_index` (`operador_id`),
  KEY `ordens_servico_tecnico_id_index` (`tecnico_id`),
  KEY `ordens_servico_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pdvs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pdvs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `maquina` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serie_nfce` int unsigned NOT NULL,
  `numero_atual_nfce` bigint unsigned NOT NULL DEFAULT '0',
  `csc` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `csc_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `emissao_local` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `emissor_alterado_em` datetime DEFAULT NULL,
  `emissor_alterado_por` bigint unsigned DEFAULT NULL,
  `emissor_motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdvs_maquina_unique` (`maquina`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pedido_compra_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_compra_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pedido_compra_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned DEFAULT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `codigo_barras` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
  `quantidade` decimal(12,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_compra_itens_pedido_compra_id_foreign` (`pedido_compra_id`),
  KEY `pedido_compra_itens_produto_id_foreign` (`produto_id`),
  KEY `pedido_compra_itens_produto_variante_id_foreign` (`produto_variante_id`),
  CONSTRAINT `pedido_compra_itens_pedido_compra_id_foreign` FOREIGN KEY (`pedido_compra_id`) REFERENCES `pedidos_compra` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedido_compra_itens_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pedido_compra_itens_produto_variante_id_foreign` FOREIGN KEY (`produto_variante_id`) REFERENCES `produto_variantes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pedido_venda_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_venda_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pedido_venda_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `codigo_barras` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UN',
  `quantidade` decimal(12,3) NOT NULL,
  `valor_unitario` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pedido_venda_itens_pedido_venda_id_foreign` (`pedido_venda_id`),
  KEY `pedido_venda_itens_produto_id_foreign` (`produto_id`),
  KEY `pedido_venda_itens_produto_variante_id_foreign` (`produto_variante_id`),
  CONSTRAINT `pedido_venda_itens_pedido_venda_id_foreign` FOREIGN KEY (`pedido_venda_id`) REFERENCES `pedidos_venda` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedido_venda_itens_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  CONSTRAINT `pedido_venda_itens_produto_variante_id_foreign` FOREIGN KEY (`produto_variante_id`) REFERENCES `produto_variantes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pedidos_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos_compra` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` int unsigned NOT NULL,
  `data_pedido` date NOT NULL,
  `previsao_entrega` date DEFAULT NULL,
  `fornecedor_id` bigint unsigned DEFAULT NULL,
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aberto',
  `observacao` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pedidos_compra_numero_unique` (`numero`),
  KEY `pedidos_compra_fornecedor_id_foreign` (`fornecedor_id`),
  KEY `pedidos_compra_status_index` (`status`),
  CONSTRAINT `pedidos_compra_fornecedor_id_foreign` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pedidos_venda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedidos_venda` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `numero` int unsigned NOT NULL,
  `data_pedido` date NOT NULL,
  `previsao_entrega` date DEFAULT NULL,
  `operador_id` bigint unsigned NOT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `forma_pagamento_id` bigint unsigned DEFAULT NULL,
  `valor_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'finalizado',
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pedidos_venda_numero_unique` (`numero`),
  KEY `pedidos_venda_operador_id_foreign` (`operador_id`),
  KEY `pedidos_venda_cliente_id_foreign` (`cliente_id`),
  KEY `pedidos_venda_forma_pagamento_id_foreign` (`forma_pagamento_id`),
  KEY `pedidos_venda_status_index` (`status`),
  CONSTRAINT `pedidos_venda_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `pedidos_venda_forma_pagamento_id_foreign` FOREIGN KEY (`forma_pagamento_id`) REFERENCES `formas_pagamento` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pedidos_venda_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produto_lotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_lotes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `entrada_nota_item_id` bigint unsigned DEFAULT NULL,
  `lote` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `quantidade_inicial` decimal(12,3) NOT NULL,
  `quantidade_atual` decimal(12,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produto_lotes_entrada_nota_item_id_foreign` (`entrada_nota_item_id`),
  KEY `produto_lotes_produto_id_validade_index` (`produto_id`,`validade`),
  CONSTRAINT `produto_lotes_entrada_nota_item_id_foreign` FOREIGN KEY (`entrada_nota_item_id`) REFERENCES `entrada_nota_itens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produto_lotes_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produto_variantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_variantes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produto_id` bigint unsigned NOT NULL,
  `cor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estoque` decimal(12,3) NOT NULL DEFAULT '0.000',
  `estoque_minimo` decimal(12,3) DEFAULT '0.000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `codigo_barras_valido` tinyint(1) NOT NULL DEFAULT '0',
  `codigo_barras` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produto_variantes_produto_id_foreign` (`produto_id`),
  CONSTRAINT `produto_variantes_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produto_variantes_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_variantes_cache` (
  `id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `cor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estoque` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produto_variantes_cache_produto_id_index` (`produto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `codigo_interno` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_barras` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referencia` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_barras_valido` tinyint(1) NOT NULL DEFAULT '1',
  `unidade_comercial` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade_tributavel` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_item` varchar(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '00',
  `nat_rec_pis_cofins` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origem_mercadoria` tinyint unsigned NOT NULL DEFAULT '0',
  `preco_venda` decimal(12,2) NOT NULL,
  `preco_custo` decimal(12,2) DEFAULT NULL,
  `tem_variacao` tinyint(1) NOT NULL DEFAULT '0',
  `produto_balanca` tinyint(1) NOT NULL DEFAULT '0',
  `estoque` decimal(12,3) NOT NULL DEFAULT '0.000',
  `estoque_minimo` decimal(12,3) DEFAULT '0.000',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `categoria_id` bigint unsigned DEFAULT NULL,
  `marca_id` bigint unsigned DEFAULT NULL,
  `grupo_id` bigint unsigned DEFAULT NULL,
  `ncm_id` bigint unsigned DEFAULT NULL,
  `cest_id` bigint unsigned DEFAULT NULL,
  `class_trib_ibs_cbs_id` bigint unsigned DEFAULT NULL,
  `tributacao_id` bigint unsigned DEFAULT NULL,
  `pis_cofins_id` bigint unsigned DEFAULT NULL,
  `ipi_id` bigint unsigned DEFAULT NULL,
  `preco_atacado` decimal(10,2) DEFAULT NULL,
  `quantidade_minima_atacado` decimal(10,3) DEFAULT NULL,
  `atacado_tem_prazo` tinyint(1) NOT NULL DEFAULT '0',
  `atacado_data_inicio` date DEFAULT NULL,
  `atacado_data_fim` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `produtos_codigo_interno_unique` (`codigo_interno`),
  UNIQUE KEY `produtos_codigo_barras_unique` (`codigo_barras`),
  KEY `produtos_categoria_id_foreign` (`categoria_id`),
  KEY `produtos_marca_id_foreign` (`marca_id`),
  KEY `produtos_grupo_id_foreign` (`grupo_id`),
  KEY `produtos_ncm_id_foreign` (`ncm_id`),
  KEY `produtos_tributacao_id_foreign` (`tributacao_id`),
  KEY `produtos_cest_id_foreign` (`cest_id`),
  KEY `produtos_class_trib_ibs_cbs_id_foreign` (`class_trib_ibs_cbs_id`),
  KEY `produtos_pis_cofins_id_foreign` (`pis_cofins_id`),
  KEY `produtos_ipi_id_foreign` (`ipi_id`),
  KEY `produtos_referencia_index` (`referencia`),
  CONSTRAINT `produtos_categoria_id_foreign` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produtos_cest_id_foreign` FOREIGN KEY (`cest_id`) REFERENCES `cests` (`id`),
  CONSTRAINT `produtos_class_trib_ibs_cbs_id_foreign` FOREIGN KEY (`class_trib_ibs_cbs_id`) REFERENCES `classificacoes_tributarias` (`id`),
  CONSTRAINT `produtos_grupo_id_foreign` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produtos_ipi_id_foreign` FOREIGN KEY (`ipi_id`) REFERENCES `classificacoes_ipi` (`id`),
  CONSTRAINT `produtos_marca_id_foreign` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produtos_ncm_id_foreign` FOREIGN KEY (`ncm_id`) REFERENCES `ncms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produtos_pis_cofins_id_foreign` FOREIGN KEY (`pis_cofins_id`) REFERENCES `classificacoes_pis_cofins` (`id`),
  CONSTRAINT `produtos_tributacao_id_foreign` FOREIGN KEY (`tributacao_id`) REFERENCES `tributacoes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produtos_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtos_cache` (
  `id` bigint unsigned NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_interno` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_barras` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncm` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cest` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cfop_padrao` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade_comercial` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidade_tributavel` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `origem_mercadoria` tinyint unsigned NOT NULL DEFAULT '0',
  `csosn` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_trib_ibs_cbs` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preco_venda` decimal(12,2) NOT NULL,
  `preco_custo` decimal(12,2) DEFAULT NULL,
  `tem_variacao` tinyint(1) NOT NULL DEFAULT '0',
  `produto_balanca` tinyint(1) NOT NULL DEFAULT '0',
  `estoque` int NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `atualizado_em_origem` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `series_mdfe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_mdfe` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serie` smallint unsigned NOT NULL DEFAULT '1',
  `numero_atual` int unsigned NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `series_mdfe_serie_unique` (`serie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `series_nfe`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_nfe` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serie` int unsigned NOT NULL,
  `numero_atual` bigint unsigned NOT NULL DEFAULT '0',
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `servicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` int unsigned NOT NULL,
  `nome` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `valor_padrao` decimal(12,2) NOT NULL DEFAULT '0.00',
  `codigo_tributacao_nacional` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_tributacao_municipal` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnae` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cnbs` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cindop` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cst_ibscbs` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cclass_trib_ibscbs` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p_tot_trib_fed` decimal(5,2) NOT NULL DEFAULT '0.00',
  `p_tot_trib_est` decimal(5,2) NOT NULL DEFAULT '0.00',
  `p_tot_trib_mun` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tributacao_issqn` tinyint unsigned NOT NULL DEFAULT '1',
  `aliquota_iss` decimal(5,2) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `servicos_codigo_unique` (`codigo`),
  KEY `servicos_nome_index` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sped_configuracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sped_configuracoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ind_ativ` tinyint unsigned NOT NULL DEFAULT '1',
  `ind_perfil` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'A',
  `clas_estab_ind` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_receita_icms` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regime_pis_cofins` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_cta_receita` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome_cta_receita` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cod_cta_compras` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nome_cta_compras` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa_fone` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `empresa_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_nome` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_cpf` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_crc` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_cnpj` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_cep` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_endereco` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_numero` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_complemento` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_bairro` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_municipio` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_cod_municipio` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_uf` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_fone` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contador_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sped_geracoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sped_geracoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'icms_ipi',
  `competencia` date NOT NULL,
  `finalidade` char(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destacar_icms` tinyint(1) NOT NULL,
  `saldo_credor_anterior` decimal(15,2) NOT NULL DEFAULT '0.00',
  `icms_recolher` decimal(15,2) NOT NULL DEFAULT '0.00',
  `saldo_credor_transportar` decimal(15,2) NOT NULL DEFAULT '0.00',
  `totais` json DEFAULT NULL,
  `arquivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_linhas` int unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sped_geracoes_user_id_foreign` (`user_id`),
  KEY `sped_geracoes_competencia_index` (`competencia`),
  CONSTRAINT `sped_geracoes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_meta` (
  `chave` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipos_conta_pagar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_conta_pagar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipos_conta_pagar_descricao_unique` (`descricao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipos_conta_receber`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_conta_receber` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipos_conta_receber_descricao_unique` (`descricao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tipos_operador`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_operador` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome_plural` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contexto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permite_login` tinyint(1) NOT NULL DEFAULT '1',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tipos_operador_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transportadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transportadores` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tipo_pessoa` char(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ie` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logradouro` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bairro` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `municipio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transportadores_documento_unique` (`documento`),
  KEY `transportadores_nome_index` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tributacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tributacoes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `crt` tinyint unsigned NOT NULL,
  `cfop` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `csosn` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cst_icms` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aliquota_icms` decimal(5,2) NOT NULL DEFAULT '0.00',
  `observacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `unidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `unidades` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sigla` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permite_fracionado` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unidades_sigla_unique` (`sigla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `veiculos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `veiculos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transportador_id` bigint unsigned DEFAULT NULL,
  `tipo` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tracao',
  `placa` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uf` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rntrc` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tara_kg` int unsigned DEFAULT NULL,
  `capacidade_kg` int unsigned DEFAULT NULL,
  `capacidade_m3` int unsigned DEFAULT NULL,
  `tipo_rodado` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_carroceria` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `veiculos_placa_unique` (`placa`),
  KEY `veiculos_transportador_id_foreign` (`transportador_id`),
  CONSTRAINT `veiculos_transportador_id_foreign` FOREIGN KEY (`transportador_id`) REFERENCES `transportadores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `venda_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `venda_itens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `venda_id` bigint unsigned NOT NULL,
  `produto_id` bigint unsigned NOT NULL,
  `produto_variante_id` bigint unsigned DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_produto` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ncm` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cfop` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cst_csosn` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantidade` int NOT NULL,
  `preco_unitario` decimal(12,2) NOT NULL,
  `desconto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `valor_bruto` decimal(15,2) DEFAULT NULL,
  `desconto_efetivo` decimal(15,2) NOT NULL DEFAULT '0.00',
  `bc_icms` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_icms` decimal(7,4) NOT NULL DEFAULT '0.0000',
  `valor_icms` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_pis` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_cofins` decimal(15,2) NOT NULL DEFAULT '0.00',
  `cst_pis_cofins` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bc_pis_cofins` decimal(15,2) NOT NULL DEFAULT '0.00',
  `aliquota_pis` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `aliquota_cofins` decimal(8,4) NOT NULL DEFAULT '0.0000',
  `valor_ibs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `valor_cbs` decimal(15,2) NOT NULL DEFAULT '0.00',
  `impostos_registrados_em` timestamp NULL DEFAULT NULL,
  `custo_unitario` decimal(15,4) DEFAULT NULL,
  `valor_custo` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venda_itens_venda_id_foreign` (`venda_id`),
  KEY `venda_itens_produto_id_foreign` (`produto_id`),
  KEY `venda_itens_produto_variante_id_foreign` (`produto_variante_id`),
  CONSTRAINT `venda_itens_produto_id_foreign` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  CONSTRAINT `venda_itens_produto_variante_id_foreign` FOREIGN KEY (`produto_variante_id`) REFERENCES `produto_variantes` (`id`),
  CONSTRAINT `venda_itens_venda_id_foreign` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `venda_pagamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `venda_pagamentos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `venda_id` bigint unsigned NOT NULL,
  `forma_pagamento` enum('dinheiro','pix','credito','debito') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venda_pagamentos_venda_id_foreign` (`venda_id`),
  CONSTRAINT `venda_pagamentos_venda_id_foreign` FOREIGN KEY (`venda_id`) REFERENCES `vendas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caixa_id` bigint unsigned NOT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `pedido_venda_id` bigint unsigned DEFAULT NULL,
  `cpf_na_nota` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operador_id` bigint unsigned NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `desconto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `troco` decimal(12,2) NOT NULL DEFAULT '0.00',
  `forma_pagamento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pendente','emitida','cancelada','contingencia') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendente',
  `chave_nfe` varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `protocolo_nfe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emitida_em` timestamp NULL DEFAULT NULL,
  `numero_nfce` bigint unsigned DEFAULT NULL,
  `tp_emis` tinyint unsigned NOT NULL DEFAULT '1',
  `dh_cont` timestamp NULL DEFAULT NULL,
  `x_just` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `xml_contingencia` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `serie_nfce` int unsigned DEFAULT NULL,
  `motivo_cancelamento` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `motivo_rejeicao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ultimo_arquivo_xml` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `xml_nfce` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendas_uuid_unique` (`uuid`),
  KEY `vendas_caixa_id_foreign` (`caixa_id`),
  KEY `vendas_operador_id_foreign` (`operador_id`),
  KEY `vendas_pedido_venda_id_foreign` (`pedido_venda_id`),
  CONSTRAINT `vendas_caixa_id_foreign` FOREIGN KEY (`caixa_id`) REFERENCES `caixas` (`id`),
  CONSTRAINT `vendas_operador_id_foreign` FOREIGN KEY (`operador_id`) REFERENCES `users` (`id`),
  CONSTRAINT `vendas_pedido_venda_id_foreign` FOREIGN KEY (`pedido_venda_id`) REFERENCES `pedidos_venda` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `vendas_pendentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendas_pendentes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `caixa_id_central` bigint unsigned DEFAULT NULL,
  `operador_id_central` bigint unsigned NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `desconto` decimal(12,2) NOT NULL DEFAULT '0.00',
  `troco` decimal(12,2) NOT NULL DEFAULT '0.00',
  `forma_pagamento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagamentos` json DEFAULT NULL,
  `itens` json NOT NULL,
  `status` enum('pendente_sync','sincronizada','erro_sync') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente_sync',
  `erro_sync_mensagem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `vendida_em` timestamp NOT NULL,
  `sincronizada_em` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendas_pendentes_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_07_30_164859_create_empresa_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_07_30_164900_create_produtos_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_07_30_164901_create_produto_variantes_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_07_30_171827_add_username_and_tipo_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_07_31_013734_create_caixas_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_07_31_013735_create_vendas_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_07_31_013736_create_venda_itens_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_07_31_115443_add_uuid_to_vendas_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_07_31_163922_create_pdvs_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_07_31_163924_add_pdv_id_to_caixas_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_07_31_163926_remove_numeracao_from_empresa_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_07_31_223848_add_numeracao_reservada_to_vendas_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_08_01_031845_add_motivo_rejeicao_to_vendas_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_08_01_114828_create_inutilizacoes_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_08_01_230017_create_venda_pagamentos_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_08_01_232127_alter_forma_pagamento_nullable_in_vendas_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_08_01_232822_add_troco_to_vendas_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_08_02_003943_add_desconto_to_vendas_and_itens_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_08_02_201341_add_xml_path_to_vendas_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_08_02_204219_add_contingencia_sefaz_to_vendas_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_08_02_210849_add_emitida_em_to_vendas_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_08_20_131550_add_produto_balanca_to_produtos_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_08_20_193542_create_categorias_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_08_20_193543_create_marcas_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_08_20_193544_create_grupos_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_08_20_193545_create_ncms_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_08_20_193733_update_produtos_add_foreign_keys',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_08_21_184431_alter_ncms_codigo_length',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_08_21_184654_alter_ncms_descricao_to_text',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_08_22_223258_create_tributacoes_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_08_23_090040_create_cests_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_08_23_090301_add_cest_id_to_produtos_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_08_23_094956_change_cests_descricao_to_text',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_08_23_100155_remove_cest_column_from_produtos_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_08_23_150230_create_classificacoes_tributarias_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_08_23_150332_add_class_trib_ibs_cbs_id_to_produtos_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_08_23_150356_remove_class_trib_ibs_cbs_column_from_produtos_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_08_24_085139_create_classificacoes_pis_cofins_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_08_24_085227_add_pis_cofins_id_to_produtos_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_08_25_000002_add_percentual_reducao_to_classificacoes_tributarias_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_08_25_192324_fix_precisao_percentual_reducao_classificacao_tributaria',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_08_25_234100_create_classificacoes_ipi_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_08_25_234146_add_ipi_id_to_produtos_table',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_08_26_000001_create_clientes_table',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_08_27_000001_add_cpf_na_nota_to_vendas_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_08_28_000001_add_atacado_to_produtos_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_08_29_000001_add_codigo_barras_valido_to_produtos_table',32);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_09_10_083000_create_series_nfe_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_09_10_083001_remove_serie_nfe_numeracao_from_empresa_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_09_10_083002_create_notas_fiscais_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_09_10_083003_create_nota_fiscal_itens_table',33);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_09_10_090000_make_cliente_id_required_in_notas_fiscais_table',34);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'cfop_saida',35);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_09_23_131401_vincular_cfop',36);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_09_23_131400_cfop_cadastro',37);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_09_23_131500_cfop_saida',38);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_09_23_131501_ordem',39);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_09_23_131502_create_forma_pagamento',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_09_23_131503_vincular_forma_pagamento',40);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_09_24_131500_cancelamento_nota_fiscal',41);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_09_24_131500_inutilizacoes_nfe',42);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_09_25_131500_adicioner_edicao',43);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_09_25_131500_informações_complementares',44);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_09_26_131500_notas_referenciadas',45);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_09_27_131500_chave_acesso',46);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_09_27_131500_tipo_operacao',47);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_09_28_131500_new_option',48);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_09_28_131501_cfop_saida_destacar_bases',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_09_28_131502_nota_fiscal_itens_new_fields',49);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_10_01_131500_nota_fiscal_itens_variantes',50);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_10_01_131500_outras_despesas',51);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_10_02_131500_campos_forma_pagto',52);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_10_02_131501_campo_frete',53);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_10_02_131502_mod_frete',54);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_10_02_131503_transportador_nfe',55);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_10_02_131504_new_field_nota_fiscal',56);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_10_02_131505_table_veiculo',57);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_10_02_131506_valume_nfe',58);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_10_03_131500_alter_user',59);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_10_03_131501_acesso_user',60);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_10_03_131502_acesso_supervisor',61);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2026_10_03_131503_acesso_caixa',62);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2026_07_31_114732_create_produtos_cache_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2026_07_31_114734_create_produto_variantes_cache_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2026_07_31_114735_create_vendas_pendentes_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2026_07_31_114737_create_sync_meta_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2026_08_01_230122_add_pagamentos_to_vendas_pendentes_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2026_08_01_232037_alter_forma_pagamento_nullable_in_vendas_pendentes',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2026_08_01_232901_add_troco_to_vendas_pendentes_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2026_08_02_000425_add_preco_custo_to_produtos_cache_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2026_08_02_004109_add_desconto_to_vendas_pendentes_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2026_08_20_131632_add_produto_balanca_to_produtos_cache_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2026_08_25_000001_make_csosn_nullable_in_produtos_cache_table',63);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2026_10_03_000001_create_usuarios_cache_table',64);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2026_10_04_000001_troca_username_por_codigo_em_users',65);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2026_10_04_000002_recria_usuarios_cache_com_codigo',66);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2026_10_04_000003_add_permissoes_supervisor_to_users',67);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2026_10_04_000004_add_permissoes_supervisor_to_usuarios_cache',68);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2026_10_04_000005_cria_tipos_operador_e_acessos',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2026_10_04_000006_preenche_acessos_a_partir_de_users',69);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2026_10_04_000007_add_nome_plural_to_tipos_operador',70);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2026_10_04_000008_recria_usuarios_cache_com_acessos',71);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2026_10_04_000009_remove_colunas_antigas_de_users',72);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2026_10_05_000002_add_uuid_to_caixas',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2026_10_06_000002_emissao_local_no_servidor',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2026_10_07_000001_emissor_padrao_caixa',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2026_10_07_000001_ipconfig_pdv_cache',73);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2026_10_08_000002_add_codigo_barras_valido_to_produto_variantes_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2026_10_08_000003_drop_sku_from_produto_variantes_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2026_10_09_000001_create_fornecedores_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2026_10_09_000002_create_entradas_nota_tables',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2026_10_09_000003_ajustar_empresa',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2026_10_09_000003_ajustar_fornecedores_como_clientes',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2026_10_10_000003_create_entrada_fiscal_tables',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2026_10_10_00032_add_fiscal_manual_to_entrada_nota_itens_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2026_10_10_00032_update_formas_pagamento_and_entradas_nota_tables',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2026_10_11_000000_add_valores_fiscais_to_entrada_nota_itens_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2026_10_11_001_add_qtd_por_caixa_to_entrada_nota_itens_table',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2026_10_12_000000_add_valor_total_xml_to_entradas_nota',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2026_10_13_000001_create_contas_pagar_tables',74);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2026_10_13_002_add_referencia_produtos',75);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2026_10_13_000002_add_duplicatas_to_entrada_nota',76);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2026_10_13_000003_create_contas_receber_tables',77);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2026_10_14_000001_create_pedidos_compra_table',78);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2026_10_15_000001_create_pedidos_venda_table',79);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2026_10_15_000002_create_pedidos_venda_cache_sqlite',80);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2026_10_15_000003_add_pedido_venda_id_to_vendas',81);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2026_10_17_000001_create_unidades_table',82);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2026_10_17_000000_create_ordens_servico_table',83);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2026_10_17_000003_add_cliente_avulso_to_ordens_servico_table',84);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2026_10_17_000002_add_tipo_operador_tecnico',85);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2026_10_18_000000_add_termo_responsabilidade_to_ordens_servico_table',86);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2026_10_18_000001_create_servicos_table',87);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2026_10_18_000002_add_nfse_to_empresa_table',88);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2026_10_18_000003_nfse_campos_da_dps',89);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2026_10_18_000004_create_nfse_emissoes_table',90);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2026_10_20_100000_create_movimentacoes_estoque_table',91);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2026_10_23_000001_impostos_em_venda_itens',92);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2026_10_23_000002_impostos_em_nota_fiscal_itens',93);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2026_10_24_000001_unifica_impostos_manuais_em_nota_fiscal_itens',94);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2026_10_25_000001_custo_nos_itens_de_venda',95);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2026_10_27_100000_create_condutores_table',96);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2026_10_27_100100_add_dados_mdfe_to_veiculos_table',97);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2026_10_27_100200_create_series_mdfe_table',98);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2026_10_27_100300_create_mdfes_tables',99);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2026_10_27_110000_ajustar_empresa_e_series_para_mdfe',100);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2026_10_27_110000_criar_serie_padrao_mdfe',101);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2026_10_28_120000_create_municipios_table',102);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2026_10_29_100000_create_sped_configuracoes_table',103);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2026_10_29_110000_add_campos_geracao_sped',104);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2026_10_29_120000_create_inventarios_table',105);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2026_10_29_130000_create_credenciadoras_table',106);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2026_10_29_140000_add_pis_cofins_aos_itens',107);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2026_10_29_150000_add_pis_cofins_as_entradas',108);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2026_10_29_160000_add_efd_contribuicoes',109);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2026_10_29_170000_add_nat_rec_aos_produtos',110);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2026_10_29_180000_create_contagens_estoque',111);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2026_10_29_210000_create_caixa_ocorrencias_table',112);
