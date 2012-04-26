ALTER TABLE `users`
	DROP COLUMN `password_salt` ,
	DROP COLUMN `password_hash` ,
	DROP COLUMN `admin` ,
	DROP COLUMN `active` ,
	DROP COLUMN `emailaddress` ,
	
	CHANGE COLUMN `id` `citizen_id` INT(11) NOT NULL AUTO_INCREMENT  ,
	CHANGE COLUMN `level` `level` ENUM('citizen','honered','worldct','l3dmember','universect','webmaster') NOT NULL ,
	
	DROP INDEX `emailaddress_UNIQUE` ,
	
	RENAME TO  `citizen_details` ;
