ALTER TABLE `objects`
	ADD COLUMN `upload_time` INT NULL  AFTER `filename` ,
	CHANGE COLUMN `type` `type` ENUM('avatars','models','seqs','sounds','textures') NOT NULL  ,
	CHANGE COLUMN `filename` `filename` VARCHAR(75) NOT NULL  ,
	CHANGE COLUMN `objectpath_id` `objectpath_id` SMALLINT(6) NOT NULL  ;
