-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 22, 2025 at 11:40 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `revue`
--

-- --------------------------------------------------------

--
-- Table structure for table `document`
--

DROP TABLE IF EXISTS `document`;
CREATE TABLE IF NOT EXISTS `document` (
  `did` int NOT NULL,
  `dtitle` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `version` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`did`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document`
--

INSERT INTO `document` (`did`, `dtitle`, `file_path`, `version`) VALUES
(1, 'MIT Demo', 'uploads/22/dummy-file.pdf', 1);

-- --------------------------------------------------------

--
-- Table structure for table `document_details`
--

DROP TABLE IF EXISTS `document_details`;
CREATE TABLE IF NOT EXISTS `document_details` (
  `did` int NOT NULL,
  `dauthor` int NOT NULL,
  `dadviser` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `program` int NOT NULL,
  UNIQUE KEY `dauthor` (`dauthor`),
  UNIQUE KEY `did` (`did`),
  KEY `program` (`program`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_details`
--

INSERT INTO `document_details` (`did`, `dauthor`, `dadviser`, `program`) VALUES
(1, 22, 'Kasima Rose', 8);

-- --------------------------------------------------------

--
-- Table structure for table `document_evaluation`
--

DROP TABLE IF EXISTS `document_evaluation`;
CREATE TABLE IF NOT EXISTS `document_evaluation` (
  `id` int NOT NULL,
  `did` int NOT NULL,
  `dauthor` int NOT NULL,
  `feedback` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `did` (`did`),
  KEY `dauthor` (`dauthor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_evaluation`
--

INSERT INTO `document_evaluation` (`id`, `did`, `dauthor`, `feedback`, `status`) VALUES
(0, 1, 22, 'follow IMRAD format', 'revision required');

-- --------------------------------------------------------

--
-- Table structure for table `document_reviewers`
--

DROP TABLE IF EXISTS `document_reviewers`;
CREATE TABLE IF NOT EXISTS `document_reviewers` (
  `did` int NOT NULL,
  `version` int NOT NULL,
  `reviewer_id` int NOT NULL,
  `review_status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `review_date` datetime NOT NULL,
  `assigned_date` datetime NOT NULL,
  `assigned_by` int NOT NULL,
  PRIMARY KEY (`did`,`version`,`reviewer_id`),
  KEY `fk_reviewer_user` (`reviewer_id`),
  KEY `fk_reviewer_chair` (`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_reviewers`
--

INSERT INTO `document_reviewers` (`did`, `version`, `reviewer_id`, `review_status`, `review_date`, `assigned_date`, `assigned_by`) VALUES
(1, 1, 7, 'under-review', '2025-05-22 19:30:43', '2025-05-22 19:30:43', 22),
(1, 1, 23, 'under-review', '2025-05-22 19:23:20', '2025-05-22 19:23:20', 22);

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

DROP TABLE IF EXISTS `program`;
CREATE TABLE IF NOT EXISTS `program` (
  `pid` int NOT NULL,
  `description` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--

INSERT INTO `program` (`pid`, `description`) VALUES
(1, 'Bachelor of Science in Information Technology'),
(2, 'Bachelor of Science in Computer Science'),
(3, 'Bachelor of Science in Information Systems'),
(4, 'Bachelor of Science in Software Engineering'),
(5, 'Bachelor of Science in Data Science'),
(6, 'Bachelor of Arts in Communication'),
(7, 'Bachelor of Secondary Education – Math'),
(8, 'Bachelor of Science in Accountancy'),
(9, 'Bachelor of Science in Psychology'),
(10, 'Bachelor of Science in Nursing');

-- --------------------------------------------------------

--
-- Table structure for table `submission_log`
--

DROP TABLE IF EXISTS `submission_log`;
CREATE TABLE IF NOT EXISTS `submission_log` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `did` int DEFAULT NULL,
  `version` int DEFAULT NULL,
  `uid` int DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `uid` int NOT NULL,
  `role` varchar(55) COLLATE utf8mb4_general_ci NOT NULL,
  `firstname` varchar(55) COLLATE utf8mb4_general_ci NOT NULL,
  `lastname` varchar(55) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(55) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(55) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`uid`, `role`, `firstname`, `lastname`, `email`, `password`) VALUES
(0, 'assign_chair', 'Matthew', 'Mark Mary', 'matthew.markmary@gmail.com', 'password'),
(1, 'student', 'Juan', 'Dela Cruz', 'juan.delacruz@gmail.com', 'password1'),
(2, 'student', 'Maria', 'Santos', 'maria.santos@gmail.com', 'password2'),
(3, 'student', 'Jose', 'Reyes', 'jose.reyes@gmail.com', 'password3'),
(4, 'student', 'Ana', 'Lopez', 'ana.lopez@gmail.com', 'password4'),
(5, 'student', 'Pedro', 'Garcia', 'pedro.garcia@gmail.com', 'password5'),
(6, 'reviewer', 'Luis', 'Torres', 'luis.torres@gmail.com', 'password6'),
(7, 'reviewer', 'Carmen', 'Mendoza', 'carmen.mendoza@gmail.com', 'password7'),
(8, 'reviewer', 'Ramon', 'Navarro', 'ramon.navarro@gmail.com', 'password8'),
(9, 'assign_chair', 'Teresa', 'Ramos', 'teresa.ramos@gmail.com', 'password9'),
(10, 'assign_chair', 'Carlos', 'Agustin', 'carlos.agustin@example.com', 'password10'),
(11, 'student', 'John', 'Juan', 'john.juan@example.com', 'password11'),
(12, 'student', 'Ina', 'Malaya', 'ina.malaya@example.com', 'password12'),
(13, 'student', 'Vinz', 'Mendoza', 'vinz.mendoza@example.com', 'password13'),
(14, 'student', 'Mark', 'Flores', 'mark.flores@example.com', 'password14'),
(15, 'student', 'Maria', 'Lee', 'john.juan@example.com', 'password15'),
(16, 'reviewer', 'Mark', 'Jason', 'mark.jason@example.com', 'password'),
(17, 'reviewer', 'Jason', 'Flores', 'jason.flores@example.com', 'password'),
(18, 'reviewer', 'Lee', 'Jan', 'lj@example.com', 'password'),
(19, 'reviewer', 'Jan', 'Lee', 'jl@example.com', 'password'),
(20, 'reviewer', 'Mike', 'Ramos', 'mr.ramos@example.com', 'password'),
(21, 'reviewer', 'Kas', 'Mendoza', 'kmme@example.com', 'password'),
(22, 'student', 'Jane', 'Doe', 'janedoe@gmail.com', 'password'),
(23, 'reviewer', 'john', 'Wick', 'johnwick@gmail.com', 'password');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
