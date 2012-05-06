/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `objects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('avatars','models','seqs','sounds','textures') NOT NULL,
  `filename` varchar(75) NOT NULL,
  `upload_time` int(11) DEFAULT NULL,
  `objectpath_id` smallint(6) NOT NULL,
  `citizen_id` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
