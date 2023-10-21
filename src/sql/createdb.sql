-- --------------------------------------------------------
-- Host:                         DT-ADRIAN
-- Server version:               10.10.2-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             11.3.0.6295
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for climate
CREATE DATABASE IF NOT EXISTS `climate` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `climate`;

-- Dumping structure for table climate.authorship
CREATE TABLE IF NOT EXISTS `authorship` (
  `PERSON_ID` int(10) unsigned NOT NULL,
  `PUBLICATION_ID` int(10) unsigned NOT NULL,
  PRIMARY KEY (`PERSON_ID`,`PUBLICATION_ID`) USING BTREE,
  KEY `FK_PERSON` (`PERSON_ID`) USING BTREE,
  KEY `FK_PUBLICATION` (`PUBLICATION_ID`) USING BTREE,
  CONSTRAINT `FK_PERSON` FOREIGN KEY (`PERSON_ID`) REFERENCES `person` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_PUBLICATION` FOREIGN KEY (`PUBLICATION_ID`) REFERENCES `publication` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Association table that links PERSON to PUBLICATION.';

-- Data exporting was unselected.

-- Dumping structure for table climate.declaration
CREATE TABLE IF NOT EXISTS `declaration` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `TYPE` varchar(20) NOT NULL,
  `TITLE` varchar(100) NOT NULL,
  `DATE` date NOT NULL,
  `COUNTRY` varchar(50) DEFAULT NULL,
  `URL` varchar(200) DEFAULT NULL,
  `SIGNATORIES` text DEFAULT NULL,
  `SIGNATORY_COUNT` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Details of public declarations and open letters expressing climate scepticism';

-- Data exporting was unselected.

-- Dumping structure for table climate.person
CREATE TABLE IF NOT EXISTS `person` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Auto-assigned unique identifier',
  `TITLE` varchar(10) DEFAULT NULL COMMENT 'Person''s title, e.g., Prof., Dr.',
  `FIRST_NAME` varchar(80) DEFAULT NULL COMMENT 'Person''s first names and/or initials',
  `NICKNAME` varchar(40) DEFAULT NULL COMMENT 'Nickname by which commonly known',
  `PREFIX` varchar(16) DEFAULT NULL COMMENT 'Prefix to last name, e.g., van, de',
  `LAST_NAME` varchar(40) NOT NULL COMMENT 'Person''s last name,  without prefix or suffix',
  `SUFFIX` varchar(16) DEFAULT NULL COMMENT 'Suffix to last name, e.g. Jr., Sr.',
  `ALIAS` varchar(40) DEFAULT NULL COMMENT 'Alternative last name',
  `DESCRIPTION` text DEFAULT NULL COMMENT 'Brief biographical description',
  `QUALIFICATIONS` text DEFAULT NULL COMMENT 'Academic qualifications',
  `COUNTRY` varchar(50) DEFAULT NULL COMMENT 'Country of primary professional association',
  `RATING` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Eminence star rating, 0..5',
  `CHECKED` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Set when person''s credentials have been checked',
  `PUBLISHED` bit(1) NOT NULL DEFAULT b'0' COMMENT 'Set if person has published peer-reviewed papers on climate change',
  PRIMARY KEY (`ID`),
  KEY `TITLE` (`TITLE`) USING BTREE,
  KEY `FIRST_NAME` (`FIRST_NAME`) USING BTREE,
  KEY `LAST_NAME` (`LAST_NAME`) USING BTREE,
  KEY `DESCRIPTION` (`DESCRIPTION`(768)) USING BTREE,
  KEY `QUALIFICATIONS` (`QUALIFICATIONS`(768)) USING BTREE,
  KEY `RATING` (`RATING`) USING BTREE,
  KEY `COUNTRY` (`COUNTRY`) USING BTREE,
  CONSTRAINT `RATING` CHECK (`RATING` >= 0 and `RATING` <= 5)
) ENGINE=InnoDB AUTO_INCREMENT=2721 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='People who have publicly expressed contrarian/sceptical views about climate science orthodoxy, whether by signing declarations, open letters or publishing science articles.';

-- Data exporting was unselected.

-- Dumping structure for table climate.publication
CREATE TABLE IF NOT EXISTS `publication` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier',
  `TITLE` varchar(200) NOT NULL COMMENT 'Publication title',
  `AUTHORS` varchar(100) NOT NULL COMMENT 'List of author names',
  `JOURNAL` varchar(100) DEFAULT NULL COMMENT 'Journal title',
  `PUBLICATION_TYPE_ID` varchar(6) DEFAULT NULL COMMENT 'The type of publication',
  `PUBLICATION_DATE` date DEFAULT NULL COMMENT 'Publication date',
  `PUBLICATION_YEAR` year(4) DEFAULT NULL COMMENT 'Publication year',
  `ABSTRACT` text DEFAULT NULL COMMENT 'Abstract from the article',
  `PEER_REVIEWED` bit(1) DEFAULT NULL COMMENT 'Whether article was peer-reviewed',
  `DOI` varchar(255) DEFAULT NULL COMMENT 'Digital Object Identifier',
  `ISSN_ISBN` varchar(20) DEFAULT NULL COMMENT 'International Standard Serial/Book Number',
  `URL` varchar(200) DEFAULT NULL COMMENT 'URL of the article',
  `ACCESSED` date DEFAULT NULL COMMENT 'Date a web page was accessed',
  PRIMARY KEY (`ID`) USING BTREE,
  UNIQUE KEY `DOI` (`DOI`),
  KEY `PUBLICATION_TYPE_ID` (`PUBLICATION_TYPE_ID`),
  CONSTRAINT `PUBLICATION_TYPE_ID` FOREIGN KEY (`PUBLICATION_TYPE_ID`) REFERENCES `publication_type` (`ID`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='References to published articles, whether or not peer-reviewed.';

-- Data exporting was unselected.

-- Dumping structure for table climate.publication_type
CREATE TABLE IF NOT EXISTS `publication_type` (
  `ID` varchar(10) NOT NULL,
  `DESCRIPTION` varchar(25) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Lookup table for article type validation';

-- Data exporting was unselected.

-- Dumping structure for table climate.quotation
CREATE TABLE IF NOT EXISTS `quotation` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique quotation identifier',
  `PERSON_ID` int(10) unsigned DEFAULT NULL COMMENT 'The identifier of the quoting person',
  `AUTHOR` varchar(50) NOT NULL COMMENT 'The quotation author',
  `TEXT` varchar(1000) NOT NULL COMMENT 'The quotation text',
  `DATE` date DEFAULT NULL COMMENT 'The quotation date',
  `SOURCE` varchar(200) DEFAULT NULL COMMENT 'The source of the quotation',
  `URL` varchar(200) DEFAULT NULL COMMENT 'Web URL to the quotation',
  PRIMARY KEY (`ID`),
  KEY `AUTHOR` (`AUTHOR`),
  KEY `PERSON` (`PERSON_ID`),
  CONSTRAINT `PERSON` FOREIGN KEY (`PERSON_ID`) REFERENCES `person` (`ID`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Quotations by leading climate sceptics';

-- Data exporting was unselected.

-- Dumping structure for table climate.signatory
CREATE TABLE IF NOT EXISTS `signatory` (
  `PERSON_ID` int(10) unsigned NOT NULL COMMENT 'The person ID',
  `DECLARATION_ID` int(10) unsigned NOT NULL COMMENT 'The declaration ID',
  PRIMARY KEY (`PERSON_ID`,`DECLARATION_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Joint table for signatories of declarations';

-- Data exporting was unselected.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
