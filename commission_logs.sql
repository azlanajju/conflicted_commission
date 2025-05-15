-- Commission Update Logs Table
CREATE TABLE IF NOT EXISTS `CommissionUpdateLogs` (
  `LogID` int(11) NOT NULL AUTO_INCREMENT,
  `ChildPromoterID` int(11) NOT NULL,
  `ChildPromoterName` varchar(255) NOT NULL,
  `OldChildCommission` decimal(10,2) NOT NULL,
  `NewChildCommission` decimal(10,2) NOT NULL,
  `OldParentCommission` decimal(10,2) NOT NULL,
  `NewParentCommission` decimal(10,2) NOT NULL,
  `UpdatedBy` varchar(100) DEFAULT NULL,
  `UpdatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `IPAddress` varchar(50) DEFAULT NULL,
  `Notes` text,
  PRIMARY KEY (`LogID`),
  KEY `ChildPromoterID` (`ChildPromoterID`),
  KEY `UpdatedAt` (`UpdatedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 