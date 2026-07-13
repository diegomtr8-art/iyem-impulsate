/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: u489236361_CG9dI
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `citas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `edicion_id` bigint(20) unsigned DEFAULT NULL,
  `restaurantero_id` bigint(20) unsigned NOT NULL,
  `servicio_id` bigint(20) unsigned NOT NULL,
  `cliente_id` bigint(20) unsigned NOT NULL,
  `inicio` datetime NOT NULL,
  `fin` datetime NOT NULL,
  `propuesta_inicio` datetime DEFAULT NULL,
  `propuesta_fin` datetime DEFAULT NULL,
  `token_confirmacion` varchar(64) DEFAULT NULL,
  `recordatorio_24h_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `recordatorio_2h_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `recordatorio_1h_enviado` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('pendiente','confirmada','cancelada','completada','rechazada','reagendada','pendiente_reconfirmacion') DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `mesa` varchar(20) DEFAULT NULL,
  `estado_tv` enum('pendiente','llamando','en_curso','finalizada','ausente','reprogramada') NOT NULL DEFAULT 'pendiente',
  `hora_real_inicio` timestamp NULL DEFAULT NULL,
  `hora_real_fin` timestamp NULL DEFAULT NULL,
  `llamada_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `citas_servicio_id_foreign` (`servicio_id`),
  KEY `citas_cliente_id_foreign` (`cliente_id`),
  KEY `citas_estado_idx` (`estado`),
  KEY `citas_inicio_idx` (`inicio`),
  KEY `citas_restaurantero_inicio_estado_idx` (`restaurantero_id`,`inicio`,`estado`),
  KEY `citas_estado_tv_index` (`estado_tv`),
  KEY `citas_edicion_id_foreign` (`edicion_id`),
  CONSTRAINT `citas_cliente_id_foreign` FOREIGN KEY (`cliente_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `citas_edicion_id_foreign` FOREIGN KEY (`edicion_id`) REFERENCES `eventos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `citas_restaurantero_id_foreign` FOREIGN KEY (`restaurantero_id`) REFERENCES `restauranteros` (`id`) ON DELETE CASCADE,
  CONSTRAINT `citas_servicio_id_foreign` FOREIGN KEY (`servicio_id`) REFERENCES `servicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citas`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `citas` WRITE;
/*!40000 ALTER TABLE `citas` DISABLE KEYS */;
/*!40000 ALTER TABLE `citas` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `encuesta_respuestas`
--

DROP TABLE IF EXISTS `encuesta_respuestas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `encuesta_respuestas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `encuesta_satisfaccion_id` bigint(20) unsigned NOT NULL,
  `pregunta` varchar(255) NOT NULL,
  `respuesta` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `encuesta_respuestas_encuesta_satisfaccion_id_foreign` (`encuesta_satisfaccion_id`),
  CONSTRAINT `encuesta_respuestas_encuesta_satisfaccion_id_foreign` FOREIGN KEY (`encuesta_satisfaccion_id`) REFERENCES `encuestas_satisfaccion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encuesta_respuestas`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `encuesta_respuestas` WRITE;
/*!40000 ALTER TABLE `encuesta_respuestas` DISABLE KEYS */;
/*!40000 ALTER TABLE `encuesta_respuestas` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `encuestas_satisfaccion`
--

DROP TABLE IF EXISTS `encuestas_satisfaccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `encuestas_satisfaccion` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('comprador','proveedor') NOT NULL,
  `token` varchar(60) NOT NULL,
  `completada_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `encuestas_satisfaccion_evento_id_user_id_tipo_unique` (`evento_id`,`user_id`,`tipo`),
  UNIQUE KEY `encuestas_satisfaccion_token_unique` (`token`),
  KEY `encuestas_satisfaccion_user_id_foreign` (`user_id`),
  CONSTRAINT `encuestas_satisfaccion_evento_id_foreign` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `encuestas_satisfaccion_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encuestas_satisfaccion`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `encuestas_satisfaccion` WRITE;
/*!40000 ALTER TABLE `encuestas_satisfaccion` DISABLE KEYS */;
/*!40000 ALTER TABLE `encuestas_satisfaccion` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `evento_usuario`
--

DROP TABLE IF EXISTS `evento_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `evento_usuario` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `motivo_rechazo` text DEFAULT NULL,
  `respondido_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `evento_usuario_evento_id_user_id_tipo_unique` (`evento_id`,`user_id`,`tipo`),
  KEY `evento_usuario_user_id_foreign` (`user_id`),
  CONSTRAINT `evento_usuario_evento_id_foreign` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evento_usuario_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evento_usuario`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `evento_usuario` WRITE;
/*!40000 ALTER TABLE `evento_usuario` DISABLE KEYS */;
/*!40000 ALTER TABLE `evento_usuario` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `eventos`
--

DROP TABLE IF EXISTS `eventos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `sector_economico` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_hora_inicio` datetime DEFAULT NULL,
  `fecha_hora_fin` datetime DEFAULT NULL,
  `fecha_hora_inicio_proveedores` datetime DEFAULT NULL,
  `fecha_hora_fin_proveedores` datetime DEFAULT NULL,
  `fecha_hora_inicio_compradores` datetime DEFAULT NULL,
  `fecha_hora_fin_compradores` datetime DEFAULT NULL,
  `max_citas_por_comprador` int(11) NOT NULL DEFAULT 3,
  `tiempo_entre_citas_minutos` int(11) NOT NULL DEFAULT 30,
  `fecha_inicio_agenda` date DEFAULT NULL,
  `fecha_fin_agenda` date DEFAULT NULL,
  `fecha_corte` date DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventos`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `eventos` WRITE;
/*!40000 ALTER TABLE `eventos` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventos` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `horarios`
--

DROP TABLE IF EXISTS `horarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `horarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `restaurantero_id` bigint(20) unsigned NOT NULL,
  `dia_semana` tinyint(4) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `horarios_restaurantero_id_foreign` (`restaurantero_id`),
  CONSTRAINT `horarios_restaurantero_id_foreign` FOREIGN KEY (`restaurantero_id`) REFERENCES `restauranteros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horarios`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `horarios` WRITE;
/*!40000 ALTER TABLE `horarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `horarios` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_05_18_164541_add_two_factor_columns_to_users_table',1),
(5,'2026_05_18_164542_create_passkeys_table',1),
(6,'2026_05_18_164742_create_personal_access_tokens_table',1),
(7,'2026_05_18_165829_create_permission_tables',1),
(8,'2026_05_18_200000_create_restauranteros_table',1),
(9,'2026_05_18_200001_create_servicios_table',1),
(10,'2026_05_18_200002_create_horarios_table',1),
(11,'2026_05_18_200003_create_citas_table',1),
(12,'2026_05_18_210000_add_municipio_to_restauranteros_table',2),
(13,'2026_05_20_000001_add_descripcion_to_restauranteros_table',3),
(14,'2026_05_20_184440_create_page_visits_table',4),
(15,'2026_05_22_000001_add_categoria_to_restauranteros_table',5),
(16,'2026_05_22_100000_create_ediciones_table',6),
(17,'2026_05_22_100001_add_edicion_id_to_citas_table',6),
(18,'2026_05_22_100002_add_edicion_id_to_restauranteros_table',6),
(19,'2026_06_01_000001_add_fecha_agenda_to_ediciones_table',7),
(20,'2026_06_03_000001_add_foreign_keys_to_edicion_id_columns',8),
(21,'2026_06_03_000002_add_performance_indexes_to_citas_table',8),
(22,'2026_06_03_000003_verify_existing_users',8),
(23,'2026_06_03_200001_add_dual_role_fields_to_users_table',8),
(24,'2026_06_03_200002_add_gestion_fields_to_citas_table',8),
(25,'2026_06_03_200003_create_notificaciones_table',8),
(26,'2026_06_03_200004_add_aprobacion_to_restauranteros_table',8),
(27,'2026_06_03_200005_add_profile_fields_to_users_table',8),
(28,'2026_06_04_200000_add_sitio_web_to_users_table',9),
(29,'2026_06_04_300000_add_tv_control_fields_to_citas_table',10),
(30,'2026_06_04_111551_add_sitio_web_to_users_table',11),
(31,'2026_06_04_123009_add_tv_control_fields_to_citas_table',11),
(32,'2026_06_05_100000_rename_ediciones_to_eventos_and_add_fields',11),
(33,'2026_06_05_200000_create_evento_usuario_table',11),
(34,'2026_06_05_300000_add_rol_seleccionado_to_users_table',11),
(35,'2026_06_05_400000_make_active_role_nullable_in_users',12),
(36,'2026_06_07_100000_add_recordatorio_1h_enviado_to_citas_table',13),
(37,'2026_06_08_093952_add_estado_to_evento_usuario_table',14),
(38,'2026_06_08_200000_add_fecha_fin_ventanas_to_eventos_table',15),
(39,'2026_06_08_210000_add_rfc_municipio_nombre_empresa_to_users_table',16),
(40,'2026_06_08_220000_add_iyem_fields_to_restauranteros_table',16),
(41,'2026_06_10_100000_add_imagen_to_eventos_table',17),
(42,'2026_06_10_200000_create_encuestas_satisfaccion_table',17);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES
(1,'App\\Models\\User',1);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notificaciones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(50) NOT NULL DEFAULT 'info',
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `cita_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notificaciones_cita_id_foreign` (`cita_id`),
  KEY `notificaciones_user_id_leida_index` (`user_id`,`leida`),
  CONSTRAINT `notificaciones_cita_id_foreign` FOREIGN KEY (`cita_id`) REFERENCES `citas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `notificaciones_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `page_visits`
--

DROP TABLE IF EXISTS `page_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `page_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `restaurantero_id` bigint(20) unsigned DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `ip` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `page_visits_restaurantero_id_index` (`restaurantero_id`),
  KEY `page_visits_created_at_index` (`created_at`),
  CONSTRAINT `page_visits_restaurantero_id_foreign` FOREIGN KEY (`restaurantero_id`) REFERENCES `restauranteros` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=236 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `page_visits`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `page_visits` WRITE;
/*!40000 ALTER TABLE `page_visits` DISABLE KEYS */;
INSERT INTO `page_visits` VALUES
(1,NULL,'/restauranteros/1','desktop','2806:10be:9:f31a:ac1f:69d8:3bf9:f47a','2026-06-07 09:52:45','2026-06-07 09:52:45'),
(2,NULL,'/restauranteros/2','desktop','2806:10be:9:f31a:ac1f:69d8:3bf9:f47a','2026-06-07 10:06:47','2026-06-07 10:06:47'),
(3,NULL,'/restauranteros/2','desktop','2806:10be:9:f31a:ac1f:69d8:3bf9:f47a','2026-06-07 10:07:06','2026-06-07 10:07:06'),
(4,NULL,'/restauranteros/1','desktop','2806:10be:9:f31a:ac1f:69d8:3bf9:f47a','2026-06-07 10:07:38','2026-06-07 10:07:38'),
(5,NULL,'/restauranteros/2','desktop','187.251.136.251','2026-06-08 09:14:43','2026-06-08 09:14:43'),
(6,NULL,'/restauranteros/1','desktop','187.251.136.251','2026-06-08 09:15:54','2026-06-08 09:15:54'),
(7,NULL,'/restauranteros/2','desktop','187.251.136.251','2026-06-08 09:15:59','2026-06-08 09:15:59'),
(8,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:26:49','2026-06-08 10:26:49'),
(9,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:28:22','2026-06-08 10:28:22'),
(10,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:29:02','2026-06-08 10:29:02'),
(11,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:29:40','2026-06-08 10:29:40'),
(12,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:31:30','2026-06-08 10:31:30'),
(13,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:32:21','2026-06-08 10:32:21'),
(14,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:33:26','2026-06-08 10:33:26'),
(15,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:37:28','2026-06-08 10:37:28'),
(16,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:37:53','2026-06-08 10:37:53'),
(17,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 10:39:18','2026-06-08 10:39:18'),
(18,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:42','2026-06-08 10:40:42'),
(19,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:50','2026-06-08 10:40:50'),
(20,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:53','2026-06-08 10:40:53'),
(21,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:54','2026-06-08 10:40:54'),
(22,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:55','2026-06-08 10:40:55'),
(23,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:55','2026-06-08 10:40:55'),
(24,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:56','2026-06-08 10:40:56'),
(25,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:56','2026-06-08 10:40:56'),
(26,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:57','2026-06-08 10:40:57'),
(27,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:57','2026-06-08 10:40:57'),
(28,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:40:58','2026-06-08 10:40:58'),
(29,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:06','2026-06-08 10:41:06'),
(30,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:28','2026-06-08 10:41:28'),
(31,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:28','2026-06-08 10:41:28'),
(32,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:28','2026-06-08 10:41:28'),
(33,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:30','2026-06-08 10:41:30'),
(34,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:31','2026-06-08 10:41:31'),
(35,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:31','2026-06-08 10:41:31'),
(36,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:31','2026-06-08 10:41:31'),
(37,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:32','2026-06-08 10:41:32'),
(38,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:32','2026-06-08 10:41:32'),
(39,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:32','2026-06-08 10:41:32'),
(40,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:41:33','2026-06-08 10:41:33'),
(41,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:42:04','2026-06-08 10:42:04'),
(42,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:42:05','2026-06-08 10:42:05'),
(43,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:42:06','2026-06-08 10:42:06'),
(44,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:42:06','2026-06-08 10:42:06'),
(45,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:42:20','2026-06-08 10:42:20'),
(46,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:42:34','2026-06-08 10:42:34'),
(47,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:42:59','2026-06-08 10:42:59'),
(48,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:01','2026-06-08 10:43:01'),
(49,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:01','2026-06-08 10:43:01'),
(50,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:02','2026-06-08 10:43:02'),
(51,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:03','2026-06-08 10:43:03'),
(52,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:06','2026-06-08 10:43:06'),
(53,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:07','2026-06-08 10:43:07'),
(54,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:09','2026-06-08 10:43:09'),
(55,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:09','2026-06-08 10:43:09'),
(56,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 10:43:12','2026-06-08 10:43:12'),
(57,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 10:43:24','2026-06-08 10:43:24'),
(58,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 11:00:25','2026-06-08 11:00:25'),
(59,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:15','2026-06-08 11:01:15'),
(60,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:34','2026-06-08 11:01:34'),
(61,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:38','2026-06-08 11:01:38'),
(62,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:39','2026-06-08 11:01:39'),
(63,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:39','2026-06-08 11:01:39'),
(64,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:40','2026-06-08 11:01:40'),
(65,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:41','2026-06-08 11:01:41'),
(66,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:42','2026-06-08 11:01:42'),
(67,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:42','2026-06-08 11:01:42'),
(68,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:43','2026-06-08 11:01:43'),
(69,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:01:43','2026-06-08 11:01:43'),
(70,7,'/restauranteros/7','desktop','187.251.136.251','2026-06-08 11:02:31','2026-06-08 11:02:31'),
(71,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:02:31','2026-06-08 11:02:31'),
(72,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 11:02:31','2026-06-08 11:02:31'),
(73,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:33','2026-06-08 11:02:33'),
(74,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:43','2026-06-08 11:02:43'),
(75,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:44','2026-06-08 11:02:44'),
(76,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:44','2026-06-08 11:02:44'),
(77,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:48','2026-06-08 11:02:48'),
(78,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:50','2026-06-08 11:02:50'),
(79,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:53','2026-06-08 11:02:53'),
(80,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:53','2026-06-08 11:02:53'),
(81,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:02:58','2026-06-08 11:02:58'),
(82,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:03:00','2026-06-08 11:03:00'),
(83,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:03:02','2026-06-08 11:03:02'),
(84,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:03:29','2026-06-08 11:03:29'),
(85,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:03:57','2026-06-08 11:03:57'),
(86,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:00','2026-06-08 11:04:00'),
(87,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:06','2026-06-08 11:04:06'),
(88,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:10','2026-06-08 11:04:10'),
(89,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:19','2026-06-08 11:04:19'),
(90,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:19','2026-06-08 11:04:19'),
(91,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:19','2026-06-08 11:04:19'),
(92,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:19','2026-06-08 11:04:19'),
(93,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:04:20','2026-06-08 11:04:20'),
(94,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:05:40','2026-06-08 11:05:40'),
(95,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:05:49','2026-06-08 11:05:49'),
(96,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:05:55','2026-06-08 11:05:55'),
(97,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:05:56','2026-06-08 11:05:56'),
(98,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:05:59','2026-06-08 11:05:59'),
(99,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:06:01','2026-06-08 11:06:01'),
(100,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:06:02','2026-06-08 11:06:02'),
(101,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:06:03','2026-06-08 11:06:03'),
(102,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:06:10','2026-06-08 11:06:10'),
(103,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:06:10','2026-06-08 11:06:10'),
(104,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:06:11','2026-06-08 11:06:11'),
(105,NULL,'/restauranteros/3','desktop','187.251.136.251','2026-06-08 11:06:12','2026-06-08 11:06:12'),
(106,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:06:22','2026-06-08 11:06:22'),
(107,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:06:36','2026-06-08 11:06:36'),
(108,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:06:39','2026-06-08 11:06:39'),
(109,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 11:08:51','2026-06-08 11:08:51'),
(110,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:14:01','2026-06-08 11:14:01'),
(111,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:14:05','2026-06-08 11:14:05'),
(112,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:15:20','2026-06-08 11:15:20'),
(113,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:15:31','2026-06-08 11:15:31'),
(114,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:15:37','2026-06-08 11:15:37'),
(115,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:15:41','2026-06-08 11:15:41'),
(116,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:15:46','2026-06-08 11:15:46'),
(117,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:15:49','2026-06-08 11:15:49'),
(118,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:15:56','2026-06-08 11:15:56'),
(119,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:15:57','2026-06-08 11:15:57'),
(120,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:16:01','2026-06-08 11:16:01'),
(121,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:16:01','2026-06-08 11:16:01'),
(122,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:16:03','2026-06-08 11:16:03'),
(123,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:16:03','2026-06-08 11:16:03'),
(124,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:16:07','2026-06-08 11:16:07'),
(125,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:16:16','2026-06-08 11:16:16'),
(126,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:16:47','2026-06-08 11:16:47'),
(127,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:16:51','2026-06-08 11:16:51'),
(128,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:17:03','2026-06-08 11:17:03'),
(129,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:17:09','2026-06-08 11:17:09'),
(130,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:17:10','2026-06-08 11:17:10'),
(131,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:17:20','2026-06-08 11:17:20'),
(132,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:17:24','2026-06-08 11:17:24'),
(133,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:05','2026-06-08 11:24:05'),
(134,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:09','2026-06-08 11:24:09'),
(135,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:11','2026-06-08 11:24:11'),
(136,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:11','2026-06-08 11:24:11'),
(137,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:11','2026-06-08 11:24:11'),
(138,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:12','2026-06-08 11:24:12'),
(139,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:14','2026-06-08 11:24:14'),
(140,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:14','2026-06-08 11:24:14'),
(141,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:19','2026-06-08 11:24:19'),
(142,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:20','2026-06-08 11:24:20'),
(143,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 11:24:20','2026-06-08 11:24:20'),
(144,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 11:24:56','2026-06-08 11:24:56'),
(145,4,'/restauranteros/4','desktop','187.251.136.251','2026-06-08 11:25:03','2026-06-08 11:25:03'),
(146,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 11:25:18','2026-06-08 11:25:18'),
(147,7,'/restauranteros/7','desktop','187.251.136.251','2026-06-08 11:25:36','2026-06-08 11:25:36'),
(148,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 11:26:59','2026-06-08 11:26:59'),
(149,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-08 11:27:02','2026-06-08 11:27:02'),
(150,7,'/restauranteros/7','desktop','187.251.136.61','2026-06-08 11:30:03','2026-06-08 11:30:03'),
(151,7,'/restauranteros/7','desktop','187.251.136.61','2026-06-08 11:36:51','2026-06-08 11:36:51'),
(152,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:47:45','2026-06-08 11:47:45'),
(153,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:47:55','2026-06-08 11:47:55'),
(154,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:48:05','2026-06-08 11:48:05'),
(155,6,'/restauranteros/6','desktop','187.251.136.189','2026-06-08 11:56:58','2026-06-08 11:56:58'),
(156,4,'/restauranteros/4','desktop','187.251.136.189','2026-06-08 11:57:36','2026-06-08 11:57:36'),
(157,4,'/restauranteros/4','desktop','187.251.136.189','2026-06-08 11:58:42','2026-06-08 11:58:42'),
(158,6,'/restauranteros/6','desktop','187.251.136.251','2026-06-08 12:03:11','2026-06-08 12:03:11'),
(159,6,'/restauranteros/6','desktop','187.251.136.61','2026-06-08 13:04:59','2026-06-08 13:04:59'),
(160,9,'/restauranteros/9','desktop','187.251.136.61','2026-06-08 13:33:21','2026-06-08 13:33:21'),
(161,8,'/restauranteros/8','desktop','187.251.136.61','2026-06-08 13:34:58','2026-06-08 13:34:58'),
(162,8,'/restauranteros/8','desktop','187.251.136.61','2026-06-08 13:34:58','2026-06-08 13:34:58'),
(163,8,'/restauranteros/8','desktop','187.251.136.61','2026-06-08 13:35:09','2026-06-08 13:35:09'),
(164,8,'/restauranteros/8','desktop','187.251.136.61','2026-06-08 13:35:21','2026-06-08 13:35:21'),
(165,8,'/restauranteros/8','desktop','187.251.136.61','2026-06-08 13:35:28','2026-06-08 13:35:28'),
(166,8,'/restauranteros/8','desktop','187.251.136.61','2026-06-08 14:05:23','2026-06-08 14:05:23'),
(167,9,'/restauranteros/9','desktop','187.251.136.61','2026-06-08 14:05:42','2026-06-08 14:05:42'),
(168,9,'/restauranteros/9','desktop','187.251.136.61','2026-06-08 14:11:03','2026-06-08 14:11:03'),
(169,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-08 14:24:18','2026-06-08 14:24:18'),
(170,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-08 14:30:16','2026-06-08 14:30:16'),
(171,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-08 15:18:48','2026-06-08 15:18:48'),
(172,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-08 15:19:37','2026-06-08 15:19:37'),
(173,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-09 09:37:42','2026-06-09 09:37:42'),
(174,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-09 09:48:22','2026-06-09 09:48:22'),
(175,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 09:59:35','2026-06-09 09:59:35'),
(176,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 09:59:57','2026-06-09 09:59:57'),
(177,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 10:01:01','2026-06-09 10:01:01'),
(178,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 10:22:30','2026-06-09 10:22:30'),
(179,7,'/restauranteros/7','desktop','187.251.136.189','2026-06-09 10:23:57','2026-06-09 10:23:57'),
(180,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 10:27:21','2026-06-09 10:27:21'),
(181,7,'/restauranteros/7','desktop','187.251.136.189','2026-06-09 10:30:43','2026-06-09 10:30:43'),
(182,5,'/restauranteros/5','desktop','187.251.136.189','2026-06-09 10:44:23','2026-06-09 10:44:23'),
(183,5,'/restauranteros/5','desktop','187.251.136.189','2026-06-09 10:45:52','2026-06-09 10:45:52'),
(184,7,'/restauranteros/7','mobile','187.251.136.251','2026-06-09 10:46:43','2026-06-09 10:46:43'),
(185,7,'/restauranteros/7','mobile','187.251.136.251','2026-06-09 10:46:43','2026-06-09 10:46:43'),
(186,7,'/restauranteros/7','mobile','187.251.136.251','2026-06-09 10:52:11','2026-06-09 10:52:11'),
(187,5,'/restauranteros/5','desktop','187.251.136.189','2026-06-09 10:52:36','2026-06-09 10:52:36'),
(188,7,'/restauranteros/7','mobile','187.251.136.251','2026-06-09 10:53:53','2026-06-09 10:53:53'),
(189,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:04:38','2026-06-09 11:04:38'),
(190,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:04:42','2026-06-09 11:04:42'),
(191,7,'/restauranteros/7','desktop','187.251.136.61','2026-06-09 11:04:43','2026-06-09 11:04:43'),
(192,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:05:16','2026-06-09 11:05:16'),
(193,7,'/restauranteros/7','desktop','187.251.136.61','2026-06-09 11:05:33','2026-06-09 11:05:33'),
(194,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:05:51','2026-06-09 11:05:51'),
(195,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:05:51','2026-06-09 11:05:51'),
(196,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:06:00','2026-06-09 11:06:00'),
(197,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:06:05','2026-06-09 11:06:05'),
(198,10,'/restauranteros/10','desktop','187.251.136.61','2026-06-09 11:06:05','2026-06-09 11:06:05'),
(199,9,'/restauranteros/9','desktop','187.251.136.61','2026-06-09 11:06:22','2026-06-09 11:06:22'),
(200,9,'/restauranteros/9','desktop','187.251.136.61','2026-06-09 11:06:22','2026-06-09 11:06:22'),
(201,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 11:13:02','2026-06-09 11:13:02'),
(202,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 11:13:13','2026-06-09 11:13:13'),
(203,7,'/restauranteros/7','desktop','187.251.136.189','2026-06-09 11:54:17','2026-06-09 11:54:17'),
(204,7,'/restauranteros/7','desktop','187.251.136.189','2026-06-09 11:55:36','2026-06-09 11:55:36'),
(205,5,'/restauranteros/5','desktop','187.251.136.189','2026-06-09 11:55:54','2026-06-09 11:55:54'),
(206,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 11:56:41','2026-06-09 11:56:41'),
(207,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 11:56:59','2026-06-09 11:56:59'),
(208,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 11:57:19','2026-06-09 11:57:19'),
(209,9,'/restauranteros/9','desktop','187.251.136.189','2026-06-09 11:57:35','2026-06-09 11:57:35'),
(210,8,'/restauranteros/8','desktop','187.251.136.189','2026-06-09 11:58:31','2026-06-09 11:58:31'),
(211,8,'/restauranteros/8','desktop','187.251.136.189','2026-06-09 11:58:32','2026-06-09 11:58:32'),
(212,8,'/restauranteros/8','desktop','187.251.136.189','2026-06-09 11:58:42','2026-06-09 11:58:42'),
(213,10,'/restauranteros/10','desktop','187.251.136.189','2026-06-09 11:58:47','2026-06-09 11:58:47'),
(214,10,'/restauranteros/10','desktop','187.251.136.189','2026-06-09 11:58:48','2026-06-09 11:58:48'),
(215,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-09 12:45:02','2026-06-09 12:45:02'),
(216,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-09 12:45:50','2026-06-09 12:45:50'),
(217,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-09 12:47:14','2026-06-09 12:47:14'),
(218,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-09 12:47:35','2026-06-09 12:47:35'),
(219,8,'/restauranteros/8','desktop','187.251.136.251','2026-06-09 12:50:11','2026-06-09 12:50:11'),
(220,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-09 12:55:00','2026-06-09 12:55:00'),
(221,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-09 12:55:39','2026-06-09 12:55:39'),
(222,5,'/restauranteros/5','desktop','187.251.136.189','2026-06-09 12:57:48','2026-06-09 12:57:48'),
(223,11,'/restauranteros/11','desktop','187.251.136.251','2026-06-09 13:28:52','2026-06-09 13:28:52'),
(224,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-09 13:38:17','2026-06-09 13:38:17'),
(225,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-09 13:38:32','2026-06-09 13:38:32'),
(226,14,'/restauranteros/14','desktop','187.251.136.251','2026-06-09 13:39:32','2026-06-09 13:39:32'),
(227,14,'/restauranteros/14','mobile','187.251.136.251','2026-06-09 13:39:38','2026-06-09 13:39:38'),
(228,14,'/restauranteros/14','desktop','187.251.136.251','2026-06-09 13:43:21','2026-06-09 13:43:21'),
(229,10,'/restauranteros/10','desktop','187.251.136.251','2026-06-09 13:51:32','2026-06-09 13:51:32'),
(230,10,'/restauranteros/10','desktop','187.251.136.251','2026-06-09 13:52:36','2026-06-09 13:52:36'),
(231,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-09 13:52:46','2026-06-09 13:52:46'),
(232,7,'/restauranteros/7','desktop','187.251.136.251','2026-06-09 13:52:59','2026-06-09 13:52:59'),
(233,9,'/restauranteros/9','desktop','187.251.136.251','2026-06-09 14:43:40','2026-06-09 14:43:40'),
(234,5,'/restauranteros/5','desktop','187.251.136.251','2026-06-09 14:44:40','2026-06-09 14:44:40'),
(235,7,'/restauranteros/7','desktop','187.251.136.251','2026-06-09 14:58:47','2026-06-09 14:58:47');
/*!40000 ALTER TABLE `page_visits` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `passkeys`
--

DROP TABLE IF EXISTS `passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `passkeys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `credential` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`credential`)),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `passkeys_credential_id_unique` (`credential_id`),
  KEY `passkeys_user_id_index` (`user_id`),
  CONSTRAINT `passkeys_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `passkeys`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `passkeys` WRITE;
/*!40000 ALTER TABLE `passkeys` DISABLE KEYS */;
/*!40000 ALTER TABLE `passkeys` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT INTO `password_reset_tokens` VALUES
('diegom.iyem@gmail.com','$2y$12$nDaSQxMsxvp5HMsdXa2O2./lkFYehB8UyCQYGkP210INH/lr0Xzy.','2026-06-01 14:52:42'),
('diegomtr8@gmail.com','$2y$12$lTk5a9fdecsEdmrg7eX0zeyvr2VC9FnWaXuwV851Gs08iX4aH1BdS','2026-06-01 15:00:44'),
('master45yol@outlook.com','$2y$12$rlrUkUgQtxCxYohUO2IpKeiCD9QQ8cUusb754aL5temXeTl/IphWW','2026-06-01 17:22:12'),
('miguelramirez030317@gmail.com','$2y$12$uVgZv1oT/veHNyyAilzeO.A7iZuKT5gH8WkPDSuXXwpCjfVrPbyVy','2026-06-01 12:56:04');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `restauranteros`
--

DROP TABLE IF EXISTS `restauranteros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `restauranteros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `edicion_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `nombre_restaurante` varchar(255) NOT NULL,
  `razon_social` varchar(200) DEFAULT NULL,
  `nombre_representante` varchar(200) DEFAULT NULL,
  `curp_representante` varchar(18) DEFAULT NULL,
  `fecha_inicio_operaciones` date DEFAULT NULL,
  `num_empleados` smallint(5) unsigned DEFAULT NULL,
  `domicilio_en_yucatan` tinyint(1) DEFAULT NULL,
  `mercado_meta` text DEFAULT NULL,
  `tiempo_vida_anaquel` text DEFAULT NULL,
  `requisitos_alimentos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requisitos_alimentos`)),
  `apoyo_requisitos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`apoyo_requisitos`)),
  `requiere_refrigeracion` tinyint(1) DEFAULT NULL,
  `requiere_congelacion` tinyint(1) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `productos_top` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`productos_top`)),
  `categorias_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categorias_json`)),
  `rfc` varchar(13) DEFAULT NULL,
  `sitio_web` varchar(255) DEFAULT NULL,
  `redes_sociales` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`redes_sociales`)),
  `telefono` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `municipio` varchar(255) DEFAULT NULL,
  `categoria` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `aprobado` tinyint(1) NOT NULL DEFAULT 0,
  `rechazado` tinyint(1) NOT NULL DEFAULT 0,
  `motivo_rechazo` text DEFAULT NULL,
  `solicitado_aprobacion_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restauranteros_user_id_foreign` (`user_id`),
  KEY `restauranteros_edicion_id_foreign` (`edicion_id`),
  CONSTRAINT `restauranteros_edicion_id_foreign` FOREIGN KEY (`edicion_id`) REFERENCES `eventos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `restauranteros_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `restauranteros`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `restauranteros` WRITE;
/*!40000 ALTER TABLE `restauranteros` DISABLE KEYS */;
/*!40000 ALTER TABLE `restauranteros` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES
(1,'admin','web','2026-05-19 01:27:33','2026-05-19 01:27:33'),
(2,'restaurantero','web','2026-05-19 01:27:33','2026-05-19 01:27:33'),
(3,'cliente','web','2026-05-19 01:27:33','2026-05-19 01:27:33');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `servicios`
--

DROP TABLE IF EXISTS `servicios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `servicios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `restaurantero_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `duracion_minutos` int(11) NOT NULL DEFAULT 30,
  `precio` decimal(10,2) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `servicios_restaurantero_id_foreign` (`restaurantero_id`),
  CONSTRAINT `servicios_restaurantero_id_foreign` FOREIGN KEY (`restaurantero_id`) REFERENCES `restauranteros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicios`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `servicios` WRITE;
/*!40000 ALTER TABLE `servicios` DISABLE KEYS */;
/*!40000 ALTER TABLE `servicios` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `curp` varchar(18) DEFAULT NULL,
  `rfc` varchar(13) DEFAULT NULL,
  `municipio` varchar(100) DEFAULT NULL,
  `nombre_empresa` varchar(200) DEFAULT NULL,
  `necesidades` text DEFAULT NULL,
  `sitio_web` varchar(200) DEFAULT NULL,
  `active_role` enum('comprador','proveedor') DEFAULT NULL,
  `rol_seleccionado` tinyint(1) NOT NULL DEFAULT 0,
  `perfil_completo` tinyint(1) NOT NULL DEFAULT 0,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_team_id` bigint(20) unsigned DEFAULT NULL,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'Administrador Impulsate',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'comprador',0,0,'impulsate@iyemyucatan.com','2026-06-10 15:27:19','$2y$12$IvtoEIpE93p2simSOUZzJutckWscDCxyye5lDHqAqTOKmsklOEAPq',NULL,NULL,NULL,'2oiI32PaAJXrEal9qFO3xlY7loA4oL5ngNIL8XMBUgHZ0HZlvod8doIQnipd',NULL,NULL,'2026-06-10 15:27:19','2026-06-10 15:27:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-06-10 21:38:41
