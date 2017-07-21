-- phpMyAdmin SQL Dump
-- version 4.5.2
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: Jul 21, 2017 at 11:14 AM
-- Server version: 5.7.9
-- PHP Version: 7.1.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `instructor_websites`
--

-- --------------------------------------------------------

--
-- Table structure for table `site_status`
--

DROP TABLE IF EXISTS `site_status`;
CREATE TABLE IF NOT EXISTS `site_status` (
  `website` varchar(255) NOT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL,
  `ssl_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`website`),
  UNIQUE KEY `website` (`website`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
