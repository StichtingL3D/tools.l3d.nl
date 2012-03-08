/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emailaddress` varchar(255) NOT NULL,
  `active` tinyint(4) DEFAULT '1',
  `admin` tinyint(4) DEFAULT '0',
  `level` varchar(25) NOT NULL,
  `password_hash` varchar(128) DEFAULT NULL,
  `password_salt` varchar(40) DEFAULT NULL,
  `password_change_required` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `emailaddress_UNIQUE` (`emailaddress`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
