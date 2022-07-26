-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 26, 2022 at 03:54 AM
-- Server version: 10.4.20-MariaDB
-- PHP Version: 8.0.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tasksdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `tblsessions`
--

CREATE TABLE `tblsessions` (
  `id` bigint(20) NOT NULL,
  `userid` int(20) NOT NULL,
  `accesstoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `accesstokenexpiry` datetime NOT NULL,
  `refreshtoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `refreshtokenexpiry` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `tblsessions`
--

INSERT INTO `tblsessions` (`id`, `userid`, `accesstoken`, `accesstokenexpiry`, `refreshtoken`, `refreshtokenexpiry`) VALUES
(1, 1, 'YjUwMTk4Y2JjN2YwYWFkMzY0NzAyYmRlMWNlYWZiZDIxZmJkYWIyZTJjNGJlNjI2MTY1ODgwMDE5MA==', '2022-07-26 04:09:50', 'MzU3MjFlODVmYzAwNWQ3MzA3NDEwNWM4ZmFkYzA1MjUzM2NhNjI3OGE2MmIwZDY1MTY1ODgwMDE5MA==', '2022-08-09 03:49:50'),
(2, 2, 'MjMzNTY2M2YzYzQ3YjViZjE4OWFiMTYwNGZmZTQxNGY3YjcyNDg0NGIwMDI1NjI3MTY1ODc5OTc0OA==', '2022-07-26 04:02:28', 'NWFkM2QyYjI2OWI5NGJiMGY3ZjA0ZjMzZTQ0NWRhZjNkZmRhZjcyZmYwZGU5ODViMTY1ODc5OTc0OA==', '2022-08-09 03:42:28');

-- --------------------------------------------------------

--
-- Table structure for table `tbltasks`
--

CREATE TABLE `tbltasks` (
  `id` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8 NOT NULL,
  `description` mediumtext CHARACTER SET utf8 DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `completed` enum('Y','N') NOT NULL DEFAULT 'N',
  `userid` int(11) NOT NULL COMMENT 'user id of owner of task'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tbltasks`
--

INSERT INTO `tbltasks` (`id`, `title`, `description`, `deadline`, `completed`, `userid`) VALUES
(1, 'omarelsool title', NULL, NULL, 'N', 1),
(2, 'omarelsool title2', NULL, NULL, 'N', 1),
(3, 'omar title', NULL, NULL, 'N', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tblusers`
--

CREATE TABLE `tblusers` (
  `id` int(11) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'utf8_bin for sensitive char',
  `useractive` enum('N','Y') NOT NULL DEFAULT 'Y',
  `loginattempts` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `tblusers`
--

INSERT INTO `tblusers` (`id`, `fullname`, `username`, `password`, `useractive`, `loginattempts`) VALUES
(1, 'omar elsool', 'omarelsool', '$2y$10$PcIWEctnHqjudhGI27MhbeUPV2aQH/dzzY6ggAIQelOE/NQBe43Yy', 'Y', 0),
(2, 'omar elsool', 'omar', '$2y$10$eKJKAMomUyKzA/ioyyQZs.aD4jLS/JrqLPBMk6Ho3707.QAQTxtIa', 'Y', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `accesstoken` (`accesstoken`),
  ADD UNIQUE KEY `refreshtoken` (`refreshtoken`),
  ADD KEY `sessionuserid_fk` (`userid`);

--
-- Indexes for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taskuserid_fk` (`userid`);

--
-- Indexes for table `tblusers`
--
ALTER TABLE `tblusers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblsessions`
--
ALTER TABLE `tblsessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbltasks`
--
ALTER TABLE `tbltasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblusers`
--
ALTER TABLE `tblusers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD CONSTRAINT `sessionuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);

--
-- Constraints for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD CONSTRAINT `taskuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
