/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cronjobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start_from` int(11) NOT NULL,
  `status` enum('waiting','starting','running','done','error') NOT NULL DEFAULT 'waiting',
  `filename` varchar(50) NOT NULL,
  `function` varchar(50) NOT NULL,
  `arguments` blob,
  `result` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
