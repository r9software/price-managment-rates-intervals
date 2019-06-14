
use midevsco_price_managment;


CREATE TABLE `midevsco_price_managment`.`price_dates`
( `id` INT NOT NULL AUTO_INCREMENT ,
  `date_start` DATE NOT NULL ,
  `date_end` DATE NOT NULL ,
  `price` FLOAT NOT NULL ,
  PRIMARY KEY (`id`)) ENGINE = MyISAM;
