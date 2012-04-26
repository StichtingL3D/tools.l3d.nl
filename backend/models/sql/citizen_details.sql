/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `citizen_details` (
  `citizen_id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('citizen','honered','worldct','l3dmember','universect','webmaster') NOT NULL,
  `password_change_required` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`citizen_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
