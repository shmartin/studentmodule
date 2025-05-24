-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 24, 2025 at 01:25 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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

CREATE TABLE `document` (
  `did` int(11) NOT NULL,
  `dtitle` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document`
--

INSERT INTO `document` (`did`, `dtitle`, `file_path`, `version`) VALUES
(1, 'MIT Test File', 'uploads/22/dummy-file_1.pdf', 1),
(1, 'MIT Test File', 'uploads/22/dummy-file_1_v2.pdf', 2),
(1, 'MIT Test File', 'uploads/22/dummy-file_1_v3.pdf', 3),
(2, 'MIT Test File 2', 'uploads/22/dummy-file_2.pdf', 1),
(2, 'MIT Test File 2', 'uploads/22/dummy-file_2_v2.pdf', 2),
(4, 'Virtual Lab for DC Machines', 'uploads/1/testfile1_6831882d05d01.pdf', 1),
(4, 'Virtual Lab for DC Machines', 'uploads/1/testfile1_v2.pdf', 2),
(5, 'Optimizing Materials for Improved Energy Output in Solar Photovoltaic (PV) Hybrid Systems', 'uploads/4/testfile2_6831ac2b2da58.pdf', 1);

-- --------------------------------------------------------

--
-- Table structure for table `document_details`
--

CREATE TABLE `document_details` (
  `did` int(11) NOT NULL,
  `dauthor` int(11) NOT NULL,
  `dadviser` varchar(100) NOT NULL,
  `program` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_details`
--

INSERT INTO `document_details` (`did`, `dauthor`, `dadviser`, `program`) VALUES
(1, 22, 'James Stevenson', 4),
(2, 22, 'Kazz Mendosa', 4),
(4, 1, 'James Stevenson', 4),
(5, 4, 'Charles Siskind', 4);

-- --------------------------------------------------------

--
-- Table structure for table `document_evaluation`
--

CREATE TABLE `document_evaluation` (
  `id` int(11) NOT NULL,
  `did` int(11) NOT NULL,
  `dauthor` int(11) NOT NULL,
  `feedback` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `version` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_evaluation`
--

INSERT INTO `document_evaluation` (`id`, `did`, `dauthor`, `feedback`, `status`, `reviewer_id`, `version`) VALUES
(1, 1, 22, '1) Need to align the specific research questions with the general statement of the problem\r\n2) Explicitly discuss the impact of the constraints identified on the final output', 'Successfully resubmitted!', 23, 1),
(2, 1, 22, '1) The methodology is vague. Identify and discuss the method and the research design.', 'Successfully resubmitted!', 18, 1),
(3, 2, 22, '1) Proofread your paper.\r\n2) Justify your choice of using the correlational design.', 'Successfully resubmitted!', 18, 1),
(19, 1, 22, '1) Expand your results and discussion', 'Successfully resubmitted!', 23, 2),
(20, 1, 22, 'Congratulations!', 'Successfully resubmitted!', 18, 2),
(22, 1, 22, 'Finally!', 'completed', 23, 3),
(23, 1, 22, 'At last!', 'completed', 18, 3),
(25, 2, 22, 'Congratulations!', 'completed', 18, 2),
(26, 4, 1, 'a) Reorganize the literature review to explicitly connect each subsection back to specific research questions, clarifying how each piece of literature contributes to understanding those questions.\r\nb) Include a more robust synthesis.', 'Successfully resubmitted!', 17, 1),
(27, 4, 1, 'Terms like \"efficiency\" and \"output\" are vaguely defined. Include context-specific definitions that align with the study\'s methodology and objectives.', 'Successfully resubmitted!', 6, 1),
(28, 4, 1, 'The significance for \"The Researchers Themselves\" and \"Future Researchers\" is somewhat generic. For the researchers, specify what specific knowledge or skills were uniquely developed or applied.', 'Successfully resubmitted!', 16, 1),
(29, 4, 1, '', 'completed', 17, 2),
(30, 4, 1, '', 'completed', 6, 2),
(31, 4, 1, '', 'completed', 16, 2);

-- --------------------------------------------------------

--
-- Table structure for table `document_reviewers`
--

CREATE TABLE `document_reviewers` (
  `did` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `review_status` varchar(50) NOT NULL,
  `review_date` datetime NOT NULL,
  `assigned_date` datetime NOT NULL,
  `assigned_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_reviewers`
--

INSERT INTO `document_reviewers` (`did`, `version`, `reviewer_id`, `review_status`, `review_date`, `assigned_date`, `assigned_by`) VALUES
(1, 1, 18, 'under review', '2025-05-23 12:56:30', '2025-05-23 12:56:30', 0),
(1, 1, 23, 'under review', '2025-05-23 12:42:59', '2025-05-23 12:42:59', 0),
(1, 2, 18, 'under review', '2025-05-24 14:39:24', '2025-05-23 12:56:30', 0),
(1, 2, 23, 'under review', '2025-05-24 14:39:24', '2025-05-23 12:42:59', 0),
(1, 3, 18, 'under review', '2025-05-24 16:18:19', '2025-05-23 12:56:30', 0),
(1, 3, 23, 'under review', '2025-05-24 16:18:19', '2025-05-23 12:42:59', 0),
(2, 1, 18, 'under review', '2025-05-23 13:43:41', '2025-05-23 13:43:41', 0),
(2, 2, 18, 'under review', '2025-05-24 16:20:28', '2025-05-23 13:43:41', 0),
(4, 1, 6, 'under review', '2025-05-24 11:39:47', '2025-05-24 11:39:47', 0),
(4, 1, 16, 'under review', '2025-05-24 11:39:12', '2025-05-24 11:39:12', 0),
(4, 1, 17, 'under review', '2025-05-24 11:30:16', '2025-05-24 11:30:16', 0),
(4, 2, 6, 'under review', '2025-05-24 18:57:59', '2025-05-24 11:39:47', 0),
(4, 2, 16, 'under review', '2025-05-24 18:57:59', '2025-05-24 11:39:12', 0),
(4, 2, 17, 'under review', '2025-05-24 18:57:59', '2025-05-24 11:30:16', 0);

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `pid` int(11) NOT NULL,
  `description` varchar(150) NOT NULL
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

CREATE TABLE `submission_log` (
  `log_id` int(11) NOT NULL,
  `did` int(11) DEFAULT NULL,
  `version` int(11) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submission_log`
--

INSERT INTO `submission_log` (`log_id`, `did`, `version`, `uid`, `timestamp`) VALUES
(4, 1, 1, 22, '2025-05-23 10:39:25'),
(5, 2, 1, 22, '2025-05-23 11:06:26'),
(6, 2, 2, 22, '2025-05-23 12:20:53'),
(8, 1, 2, 22, '2025-05-24 06:39:24'),
(9, 1, 3, 22, '2025-05-24 08:18:19'),
(10, 2, 2, 22, '2025-05-24 08:20:28'),
(12, 4, 1, 1, '2025-05-24 08:49:49'),
(13, 4, 2, 1, '2025-05-24 10:57:59'),
(14, 5, 1, 4, '2025-05-24 11:23:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `uid` int(11) NOT NULL,
  `role` varchar(55) NOT NULL,
  `firstname` varchar(55) NOT NULL,
  `lastname` varchar(55) NOT NULL,
  `email` varchar(55) NOT NULL,
  `password` varchar(55) NOT NULL
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `document`
--
ALTER TABLE `document`
  ADD PRIMARY KEY (`did`,`version`);

--
-- Indexes for table `document_details`
--
ALTER TABLE `document_details`
  ADD UNIQUE KEY `did` (`did`),
  ADD KEY `program` (`program`);

--
-- Indexes for table `document_evaluation`
--
ALTER TABLE `document_evaluation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `did` (`did`),
  ADD KEY `dauthor` (`dauthor`);

--
-- Indexes for table `document_reviewers`
--
ALTER TABLE `document_reviewers`
  ADD PRIMARY KEY (`did`,`version`,`reviewer_id`),
  ADD KEY `fk_reviewer_user` (`reviewer_id`),
  ADD KEY `fk_reviewer_chair` (`assigned_by`);

--
-- Indexes for table `program`
--
ALTER TABLE `program`
  ADD PRIMARY KEY (`pid`);

--
-- Indexes for table `submission_log`
--
ALTER TABLE `submission_log`
  ADD PRIMARY KEY (`log_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `document`
--
ALTER TABLE `document`
  MODIFY `did` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_evaluation`
--
ALTER TABLE `document_evaluation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `submission_log`
--
ALTER TABLE `submission_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
