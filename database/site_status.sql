--
-- Table structure for table `site_status`
--

CREATE TABLE IF NOT EXISTS `site_status` (
  `website` varchar(255) NOT NULL,
  `status` mediumint(3) UNSIGNED NOT NULL,
  `ssl_expiry` datetime DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
  PRIMARY KEY (`website`),
  UNIQUE KEY `website` (`website`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
