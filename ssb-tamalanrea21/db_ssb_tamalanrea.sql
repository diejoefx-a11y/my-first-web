-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: db_ssb_tamalanrea
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `atlet`
--

DROP TABLE IF EXISTS `atlet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `atlet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nisn_nik` varchar(30) DEFAULT NULL,
  `no_kk` varchar(30) DEFAULT NULL,
  `no_akta` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `tempat_lahir` varchar(50) DEFAULT NULL,
  `tanggal_lahir` date NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT 'Laki-laki',
  `posisi_utama` varchar(30) NOT NULL,
  `posisi_sekunder` varchar(30) DEFAULT '-',
  `kaki_dominan` enum('Kanan','Kiri','Keduanya') DEFAULT 'Kanan',
  `tinggi_badan` int(11) DEFAULT 0,
  `berat_badan` int(11) DEFAULT 0,
  `kelompok_usia` varchar(10) NOT NULL,
  `foto_profil` varchar(255) DEFAULT 'default_avatar.png',
  `status_keanggotaan` enum('Aktif','Non-Aktif','Alumni','Mutasi') DEFAULT 'Aktif',
  `created_at` datetime DEFAULT current_timestamp(),
  `file_kk` varchar(255) DEFAULT NULL,
  `file_akta` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nisn_nik` (`nisn_nik`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `atlet`
--

LOCK TABLES `atlet` WRITE;
/*!40000 ALTER TABLE `atlet` DISABLE KEYS */;
INSERT INTO `atlet` VALUES (8,'0021589427','73713816787826','3578-LT-23884383','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Ahmad Rayhan','Makassar','2018-02-19','Laki-laki','Kiper','-','Kanan',158,39,'U-8','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(9,'0017402650','73719370247996','3578-LT-20593927','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Muhammad Kenzo','Makassar','2018-06-25','Laki-laki','Bek Sayap Kiri','-','',140,47,'U-8','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(10,'0074815629','73713002052723','3578-LT-60347929','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Rafi Althaf','Makassar','2018-05-07','Laki-laki','Bek Sayap Kanan','-','Kanan',134,29,'U-8','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(11,'0018301214','73716500740191','3578-LT-42896226','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Devan Prasetya','Makassar','2018-07-25','Laki-laki','Bek Sayap Kiri','-','Kanan',125,68,'U-8','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(12,'0030991884','73716767638177','3578-LT-73613817','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Zayn Malik','Makassar','2018-10-19','Laki-laki','Gelandang Bertahan','-','Kanan',134,55,'U-8','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(13,'0056836908','73713542088070','3578-LT-16363185','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Bagas Pratama','Makassar','2016-08-23','Laki-laki','Bek Sayap Kiri','-','',134,27,'U-10','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(14,'0031181813','73715730572503','3578-LT-74854706','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Andi Rian','Makassar','2016-06-04','Laki-laki','Bek Tengah','-','',158,56,'U-10','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(15,'0088804647','73711918847612','3578-LT-18488885','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Fatur Rahman','Makassar','2016-08-07','Laki-laki','Gelandang Bertahan','-','Kanan',152,59,'U-10','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(16,'0062045416','73718704265653','3578-LT-63921088','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Fadel Muhammad','Makassar','2016-12-20','Laki-laki','Penyerang Sayap','-','Kiri',131,37,'U-10','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(17,'7371011205120001','73713990782672','3578-LT-82752798','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Reza Aditya','Makassar','2016-09-06','Laki-laki','Gelandang Serang','-','Kiri',142,47,'U-10','atlet_1786451099_7668.jpeg','Aktif','2026-08-11 14:12:10','kk_1786451099_8204.pdf','akta_1786451099_5049.jpeg'),(18,'0091259685','73713876513949','3578-LT-22019171','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Dafa Al-Faris','Makassar','2014-10-24','Laki-laki','Bek Sayap Kiri','-','',135,60,'U-12','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(19,'0044532855','73719907851360','3578-LT-38794639','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Naufal Az-Zahir','Makassar','2014-01-26','Laki-laki','Gelandang Bertahan','-','',136,37,'U-12','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(20,'0087578891','73717653089867','3578-LT-71156187','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Rizky Ramadhan','Makassar','2014-06-17','Laki-laki','Bek Sayap Kanan','-','Kanan',134,47,'U-12','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(21,'0024598069','73717733905318','3578-LT-89428246','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Gibran Rakabuming','Makassar','2014-03-10','Laki-laki','Gelandang Bertahan','-','Kanan',161,35,'U-12','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(22,'0085391997','73711356962412','3578-LT-82505336','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Irfan Jaya','Makassar','2014-04-11','Laki-laki','Penyerang Tengah','-','Kanan',166,56,'U-12','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(23,'0078596992','73713329141482','3578-LT-97336519','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Alvin Febrian','Makassar','2012-08-13','Laki-laki','Kiper','-','',129,56,'U-14','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(24,'0080844447','73716815778305','3578-LT-20500270','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Rehan Saputra','Makassar','2012-08-10','Laki-laki','Gelandang Bertahan','-','',143,58,'U-14','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(25,'0018169378','73719089598597','3578-LT-76062406','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Galang Sopyan','Makassar','2012-04-14','Laki-laki','Bek Sayap Kiri','-','',150,34,'U-14','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(26,'0091212022','73711514206207','3578-LT-33379537','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Kaka Slank','Makassar','2012-02-03','Laki-laki','Gelandang Serang','-','Kanan',154,37,'U-14','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(27,'0077286844','73716641108245','3578-LT-27518210','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Bintang Timur','Makassar','2012-12-23','Laki-laki','Gelandang Serang','-','Kanan',166,41,'U-14','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(28,'0045476021','73715866112171','3578-LT-78863491','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Dimas Anggara','Makassar','2010-11-02','Laki-laki','Bek Sayap Kanan','-','Kiri',122,42,'U-16','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(29,'0014685915','73718890272904','3578-LT-68596187','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Aditya Putra','Makassar','2010-03-28','Laki-laki','Bek Tengah','-','Kiri',160,64,'U-16','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(30,'0050710943','73719519802048','3578-LT-70863608','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Bayu Pradana','Makassar','2010-01-15','Laki-laki','Gelandang Bertahan','-','',151,67,'U-16','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(31,'0089447913','73718706179042','3578-LT-31539553','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Fajar Alfian','Makassar','2010-10-07','Laki-laki','Gelandang Serang','-','Kiri',141,35,'U-16','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(32,'0081427579','73717907676122','3578-LT-67470633','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Eka Ramdani','Makassar','2010-10-14','Laki-laki','Gelandang Bertahan','-','Kanan',138,49,'U-16','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(33,'0082006839','73715204817644','3578-LT-26227345','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Rahmat Hidayat','Makassar','2008-10-25','Laki-laki','Bek Sayap Kiri','-','',139,41,'U-18','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(34,'0023636161','73714905929400','3578-LT-84872173','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Zulham Zamrun','Makassar','2008-06-17','Laki-laki','Bek Tengah','-','Kanan',123,29,'U-18','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(35,'0015878945','73715963208328','3578-LT-97284323','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Asnawi Mangkualam','Makassar','2008-07-18','Laki-laki','Gelandang Serang','-','Kanan',138,61,'U-18','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(36,'0079148749','73715049409057','3578-LT-84136622','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Saddil Ramdani','Makassar','2008-12-24','Laki-laki','Penyerang Sayap','-','Kanan',139,52,'U-18','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(37,'0086621834','73716983543229','3578-LT-39990298','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Witan Sulaeman','Makassar','2008-07-15','Laki-laki','Penyerang Tengah','-','',167,39,'U-18','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(38,'0036785238','73713791148050','3578-LT-40175762','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Ferdinand Sinaga','Makassar','2005-12-23','Laki-laki','Bek Sayap Kanan','-','Kanan',131,31,'Senior','atlet_1786447649_8640.jpeg','Aktif','2026-08-11 14:12:10','kk_1786447649_4496.jpeg','akta_1786447649_3108.jpeg'),(39,'0031349989','73718844718285','3578-LT-61296907','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Syamsul Chaeruddin','Makassar','2005-10-02','Laki-laki','Bek Sayap Kiri','-','',138,37,'Senior','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(40,'0044942521','73714554585536','3578-LT-18091467','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Zulkifli Syukur','Makassar','2005-11-25','Laki-laki','Bek Sayap Kiri','-','Kanan',178,61,'Senior','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(41,'0029423131','73718210616967','3578-LT-73252590','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Hamka Hamzah','Makassar','2005-01-27','Laki-laki','Bek Sayap Kiri','-','',154,62,'Senior','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(42,'0054075988','73717260621391','3578-LT-53082980','$2y$10$/2YJ2ePaQjkWyHXFRdxoMObSldtUMbS3Jjl.lFpLao0f8GTcLxyiC','Rasyid Bakri','Makassar','2005-11-05','Laki-laki','Penyerang Sayap','-','',142,26,'Senior','default_avatar.png','Aktif','2026-08-11 14:12:10',NULL,NULL),(43,'44234','','','$2y$10$Oip1NjW0pHeKVLrH6zCV4ewbCY2P.w0sI6khZ7g9qaO6km55wuFcG','dad','gdfgf','2018-06-11','Laki-laki','Kiper (GK)','','Kanan',0,0,'U-8','default_avatar.png','Aktif','2026-08-11 20:02:01',NULL,NULL),(44,'353534543','45435','5345345435','$2y$10$1IVxoD0TlsU7v/O1y2Fss.3fQ0xQTO7Y04DlRo1IqtlaQUuQmOaU.','tyrtytrytr','gjgjgjgjh','2020-08-11','Laki-laki','Penyerang Sayap (Winger)','','Kanan',0,0,'U-8','default_avatar.png','Aktif','2026-08-11 20:05:35',NULL,NULL);
/*!40000 ALTER TABLE `atlet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluasi_atlet`
--

DROP TABLE IF EXISTS `evaluasi_atlet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluasi_atlet` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atlet_id` int(11) NOT NULL,
  `tanggal_evaluasi` date NOT NULL,
  `nilai_passing` int(11) DEFAULT 70,
  `nilai_dribbling` int(11) DEFAULT 70,
  `nilai_shooting` int(11) DEFAULT 70,
  `nilai_tackling` int(11) DEFAULT 70,
  `nilai_stamina` int(11) DEFAULT 70,
  `nilai_disiplin` int(11) DEFAULT 70,
  `vo2max` float DEFAULT 45,
  `catatan_pelatih` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `atlet_id` (`atlet_id`),
  CONSTRAINT `evaluasi_atlet_ibfk_1` FOREIGN KEY (`atlet_id`) REFERENCES `atlet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluasi_atlet`
--

LOCK TABLES `evaluasi_atlet` WRITE;
/*!40000 ALTER TABLE `evaluasi_atlet` DISABLE KEYS */;
INSERT INTO `evaluasi_atlet` VALUES (8,8,'2026-08-11',89,91,81,62,88,76,57.4,'Perkembangan sangat baik pada KU U-8. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(9,9,'2026-08-11',70,86,78,81,83,80,53.9,'Perkembangan sangat baik pada KU U-8. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(10,10,'2026-08-11',73,81,61,65,86,77,57.2,'Perkembangan sangat baik pada KU U-8. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(11,11,'2026-08-11',91,69,88,62,80,76,56.7,'Perkembangan sangat baik pada KU U-8. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(12,12,'2026-08-11',80,76,66,64,75,87,42.3,'Perkembangan sangat baik pada KU U-8. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(13,13,'2026-08-11',92,78,64,87,80,95,49.3,'Perkembangan sangat baik pada KU U-10. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(14,14,'2026-08-11',83,85,67,84,81,88,44,'Perkembangan sangat baik pada KU U-10. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(15,15,'2026-08-11',87,76,69,88,90,79,54.7,'Perkembangan sangat baik pada KU U-10. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(16,16,'2026-08-11',76,66,85,81,79,95,45.9,'Perkembangan sangat baik pada KU U-10. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(17,17,'2026-08-11',90,69,78,68,93,76,43.4,'Perkembangan sangat baik pada KU U-10. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(18,18,'2026-08-11',75,82,80,86,92,84,50.5,'Perkembangan sangat baik pada KU U-12. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(19,19,'2026-08-11',79,92,60,81,93,82,51.9,'Perkembangan sangat baik pada KU U-12. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(20,20,'2026-08-11',75,88,67,63,75,77,52.5,'Perkembangan sangat baik pada KU U-12. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(21,21,'2026-08-11',76,66,90,88,89,84,49.1,'Perkembangan sangat baik pada KU U-12. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(22,22,'2026-08-11',89,82,73,65,74,75,50.9,'Perkembangan sangat baik pada KU U-12. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(23,23,'2026-08-11',92,81,65,75,73,77,47.9,'Perkembangan sangat baik pada KU U-14. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(24,24,'2026-08-11',88,72,70,86,77,79,42.6,'Perkembangan sangat baik pada KU U-14. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(25,25,'2026-08-11',78,89,72,86,72,77,47.4,'Perkembangan sangat baik pada KU U-14. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(26,26,'2026-08-11',85,87,87,77,77,90,51.2,'Perkembangan sangat baik pada KU U-14. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(27,27,'2026-08-11',79,70,62,67,93,89,42.9,'Perkembangan sangat baik pada KU U-14. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(28,28,'2026-08-11',78,66,70,71,92,94,49.2,'Perkembangan sangat baik pada KU U-16. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(29,29,'2026-08-11',65,81,62,84,87,79,50.3,'Perkembangan sangat baik pada KU U-16. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(30,30,'2026-08-11',91,66,70,62,83,80,58,'Perkembangan sangat baik pada KU U-16. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(31,31,'2026-08-11',80,82,89,61,82,75,50.8,'Perkembangan sangat baik pada KU U-16. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(32,32,'2026-08-11',73,83,75,80,88,89,49,'Perkembangan sangat baik pada KU U-16. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(33,33,'2026-08-11',65,86,68,84,93,95,57.2,'Perkembangan sangat baik pada KU U-18. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(34,34,'2026-08-11',75,77,66,67,89,82,53.2,'Perkembangan sangat baik pada KU U-18. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(35,35,'2026-08-11',67,83,87,79,94,94,44.6,'Perkembangan sangat baik pada KU U-18. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(36,36,'2026-08-11',70,68,63,76,73,78,40.9,'Perkembangan sangat baik pada KU U-18. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(37,37,'2026-08-11',67,71,67,82,88,76,54.7,'Perkembangan sangat baik pada KU U-18. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(38,38,'2026-08-11',69,68,82,73,82,79,55.8,'Perkembangan sangat baik pada KU Senior. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(39,39,'2026-08-11',74,73,62,83,82,77,47.8,'Perkembangan sangat baik pada KU Senior. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(40,40,'2026-08-11',88,92,84,68,91,81,52.3,'Perkembangan sangat baik pada KU Senior. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(41,41,'2026-08-11',84,69,72,79,74,95,55.5,'Perkembangan sangat baik pada KU Senior. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(42,42,'2026-08-11',90,89,71,74,72,85,51.1,'Perkembangan sangat baik pada KU Senior. Tingkatkan fokus dan kerja sama tim.','2026-08-11 14:12:10'),(43,43,'2026-08-11',75,75,75,75,75,80,45,'Pendaftaran atlet baru di SSB Tamalanrea.','2026-08-11 20:02:01'),(44,44,'2026-08-11',10,10,10,10,10,10,10,'Pendaftaran atlet baru di SSB Tamalanrea.','2026-08-11 20:05:35');
/*!40000 ALTER TABLE `evaluasi_atlet` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iuran_spp`
--

DROP TABLE IF EXISTS `iuran_spp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iuran_spp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atlet_id` int(11) NOT NULL,
  `bulan` int(11) NOT NULL,
  `tahun` int(11) NOT NULL,
  `jumlah` decimal(12,2) DEFAULT 150000.00,
  `status_bayar` enum('Lunas','Menunggu','Belum Bayar') DEFAULT 'Belum Bayar',
  `tanggal_bayar` date DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT '-',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `atlet_id` (`atlet_id`),
  CONSTRAINT `iuran_spp_ibfk_1` FOREIGN KEY (`atlet_id`) REFERENCES `atlet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iuran_spp`
--

LOCK TABLES `iuran_spp` WRITE;
/*!40000 ALTER TABLE `iuran_spp` DISABLE KEYS */;
INSERT INTO `iuran_spp` VALUES (22,8,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(23,9,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(24,10,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(25,11,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(26,12,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(27,13,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(28,14,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(29,15,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(30,16,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(31,17,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(32,18,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(33,19,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(34,20,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(35,21,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(36,22,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(37,23,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(38,24,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(39,25,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(40,26,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(41,27,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(42,28,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(43,29,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(44,30,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(45,31,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(46,32,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(47,33,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(48,34,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(49,35,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(50,36,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(51,37,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(52,38,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(53,39,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(54,40,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(55,41,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(56,42,8,2026,150000.00,'Lunas','2026-08-11','-','2026-08-11 14:12:10'),(57,43,8,2026,150000.00,'Belum Bayar',NULL,'SPP Pendaftaran Bulanan','2026-08-11 20:02:01'),(58,44,8,2026,150000.00,'Belum Bayar',NULL,'SPP Pendaftaran Bulanan','2026-08-11 20:05:35');
/*!40000 ALTER TABLE `iuran_spp` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orang_tua`
--

DROP TABLE IF EXISTS `orang_tua`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orang_tua` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atlet_id` int(11) NOT NULL,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `no_whatsapp` varchar(20) NOT NULL,
  `alamat` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `atlet_id` (`atlet_id`),
  CONSTRAINT `orang_tua_ibfk_1` FOREIGN KEY (`atlet_id`) REFERENCES `atlet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orang_tua`
--

LOCK TABLES `orang_tua` WRITE;
/*!40000 ALTER TABLE `orang_tua` DISABLE KEYS */;
INSERT INTO `orang_tua` VALUES (8,8,'Bpk. Ahmad Sr.','Ibu Mariam','081276032768','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(9,9,'Bpk. Muhammad Sr.','Ibu Siti','081227160662','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(10,10,'Bpk. Rafi Sr.','Ibu Mariam','081271033583','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(11,11,'Bpk. Devan Sr.','Ibu Nur','081232007712','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(12,12,'Bpk. Zayn Sr.','Ibu Indah','081252085522','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(13,13,'Bpk. Bagas Sr.','Ibu Siti','081290229708','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(14,14,'Bpk. Andi Sr.','Ibu Siti','081223935787','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(15,15,'Bpk. Fatur Sr.','Ibu Indah','081223830372','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(16,16,'Bpk. Fadel Sr.','Ibu Nur','081286379037','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(17,17,'Bpk. Reza Sr.','Ibu Siti','081259189784','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(18,18,'Bpk. Dafa Sr.','Ibu Indah','081252838448','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(19,19,'Bpk. Naufal Sr.','Ibu Indah','081263334669','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(20,20,'Bpk. Rizky Sr.','Ibu Rahma','081225746822','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(21,21,'Bpk. Gibran Sr.','Ibu Nur','081228135597','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(22,22,'Bpk. Irfan Sr.','Ibu Rahma','081252407822','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(23,23,'Bpk. Alvin Sr.','Ibu Mariam','081299841153','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(24,24,'Bpk. Rehan Sr.','Ibu Siti','081219558485','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(25,25,'Bpk. Galang Sr.','Ibu Rahma','081226731916','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(26,26,'Bpk. Kaka Sr.','Ibu Nur','081287242606','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(27,27,'Bpk. Bintang Sr.','Ibu Nur','081211652010','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(28,28,'Bpk. Dimas Sr.','Ibu Mariam','081294653384','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(29,29,'Bpk. Aditya Sr.','Ibu Mariam','081236380245','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(30,30,'Bpk. Bayu Sr.','Ibu Siti','081265687722','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(31,31,'Bpk. Fajar Sr.','Ibu Indah','081266893771','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(32,32,'Bpk. Eka Sr.','Ibu Rahma','081250357676','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(33,33,'Bpk. Rahmat Sr.','Ibu Rahma','081214414191','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(34,34,'Bpk. Zulham Sr.','Ibu Rahma','081282109857','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(35,35,'Bpk. Asnawi Sr.','Ibu Rahma','081235478214','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(36,36,'Bpk. Saddil Sr.','Ibu Rahma','081267883992','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(37,37,'Bpk. Witan Sr.','Ibu Nur','081242419050','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(38,38,'Bpk. Ferdinand Sr.','Ibu Rahma','081284549224','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(39,39,'Bpk. Syamsul Sr.','Ibu Rahma','081264437863','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(40,40,'Bpk. Zulkifli Sr.','Ibu Rahma','081270042361','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(41,41,'Bpk. Hamka Sr.','Ibu Siti','081255025217','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(42,42,'Bpk. Rasyid Sr.','Ibu Nur','081272083675','Jl. Perintis Kemerdekaan KM 10, Tamalanrea, Makassar'),(43,43,'tetert','tertret','3453543543543','fgfdgdfg'),(44,44,'gdgdfg','ddfgdfgfd','3242342342','gdgdgdf');
/*!40000 ALTER TABLE `orang_tua` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `statistik_pertandingan`
--

DROP TABLE IF EXISTS `statistik_pertandingan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `statistik_pertandingan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atlet_id` int(11) NOT NULL,
  `turnamen_id` int(11) NOT NULL,
  `main` int(11) DEFAULT 0,
  `gol` int(11) DEFAULT 0,
  `assist` int(11) DEFAULT 0,
  `kartu_kuning` int(11) DEFAULT 0,
  `kartu_merah` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `atlet_id` (`atlet_id`),
  KEY `turnamen_id` (`turnamen_id`),
  CONSTRAINT `statistik_pertandingan_ibfk_1` FOREIGN KEY (`atlet_id`) REFERENCES `atlet` (`id`) ON DELETE CASCADE,
  CONSTRAINT `statistik_pertandingan_ibfk_2` FOREIGN KEY (`turnamen_id`) REFERENCES `turnamen` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `statistik_pertandingan`
--

LOCK TABLES `statistik_pertandingan` WRITE;
/*!40000 ALTER TABLE `statistik_pertandingan` DISABLE KEYS */;
INSERT INTO `statistik_pertandingan` VALUES (9,8,6,0,0,0,0,0),(10,9,6,0,0,0,0,0),(11,10,6,2,5,0,0,0),(12,11,6,0,0,0,0,0),(13,12,6,3,3,0,0,0),(14,13,7,0,0,0,0,0),(15,14,7,0,0,0,0,0),(16,15,7,0,0,0,0,0),(17,16,7,0,0,0,0,0),(18,17,7,0,8,0,0,0);
/*!40000 ALTER TABLE `statistik_pertandingan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turnamen`
--

DROP TABLE IF EXISTS `turnamen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `turnamen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_turnamen` varchar(100) NOT NULL,
  `kelompok_usia` varchar(20) DEFAULT 'Semua KU',
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `pencapaian` varchar(100) DEFAULT '-',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turnamen`
--

LOCK TABLES `turnamen` WRITE;
/*!40000 ALTER TABLE `turnamen` DISABLE KEYS */;
INSERT INTO `turnamen` VALUES (6,'Tropyhio','U-8','2026-04-11','2026-08-11','SDN Bulu Rokeng','Juara 1'),(7,'Liga Merdeka','U-10','2026-08-12','2026-08-13','Mandai','Juara III');
/*!40000 ALTER TABLE `turnamen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','pelatih') DEFAULT 'admin',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$pvKhYrfP9BroY3bqJlLuwOYBTKW7T5.aqjSpIIgJyxXfZocdqsMhq','Administrator SSB','admin','2026-08-10 13:55:25'),(2,'coach_andi','$2y$10$pvKhYrfP9BroY3bqJlLuwOYBTKW7T5.aqjSpIIgJyxXfZocdqsMhq','Coach Andi Wijaya','pelatih','2026-08-10 13:55:25');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-11 20:34:39
