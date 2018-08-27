--
-- Table structure for table `site_status`
--

DROP TABLE IF EXISTS `site_status`;
CREATE TABLE IF NOT EXISTS `site_status` (
  `website` varchar(255) NOT NULL,
  `status` mediumint(3) UNSIGNED NOT NULL,
  `ssl_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`website`),
  UNIQUE KEY `website` (`website`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;