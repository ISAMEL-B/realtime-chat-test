-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 27, 2025 at 11:13 PM
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
-- Database: `bisure1`
--

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `user_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`user_id`, `contact_id`) VALUES
(1, 2),
(2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 2, 'Hey Jane, how are you doing?', 1, '2023-11-01 07:00:00'),
(2, 2, 1, 'Hi John! I\'m doing great, thanks for asking. How about you?', 1, '2023-11-01 07:02:00'),
(3, 1, 2, 'Pretty good! Just working on this new chat app called Bisure.', 1, '2023-11-01 07:05:00'),
(4, 2, 1, 'That sounds awesome! Can I try it out?', 1, '2023-11-01 07:06:00'),
(5, 1, 2, 'Of course! I\'ll send you the details shortly.', 1, '2023-11-01 07:10:00'),
(6, 1, 2, 'Hey Jane, how are you doing?', 1, '2023-11-01 07:00:00'),
(7, 2, 1, 'Hi John! I\'m doing great, thanks for asking. How about you?', 1, '2023-11-01 07:02:00'),
(8, 1, 2, 'Pretty good! Just working on this new chat app called Bisure.', 1, '2023-11-01 07:05:00'),
(9, 2, 1, 'That sounds awesome! Can I try it out?', 1, '2023-11-01 07:06:00'),
(10, 1, 2, 'Of course! I\'ll send you the details shortly.', 1, '2023-11-01 07:10:00'),
(23, 2, 1, 'Yes praise', 1, '2025-06-24 21:30:34'),
(24, 2, 1, 'How are you', 1, '2025-06-24 21:31:04'),
(25, 2, 1, 'Waooo', 1, '2025-06-24 21:34:44'),
(26, 2, 1, 'Yes okay???', 1, '2025-06-24 21:34:59'),
(27, 2, 1, 'Ggg', 1, '2025-06-24 22:04:38'),
(28, 2, 1, 'Fjf', 1, '2025-06-24 22:09:22'),
(29, 2, 1, 'Real', 1, '2025-06-24 22:14:41'),
(30, 1, 2, 'hfhfh', 1, '2025-06-24 22:20:43'),
(31, 2, 1, 'Hello', 1, '2025-06-24 22:22:19'),
(32, 2, 1, 'Yes', 1, '2025-06-24 22:32:05'),
(33, 2, 1, 'Waooo', 1, '2025-06-24 22:40:04'),
(34, 1, 2, 'Jjh', 1, '2025-06-27 20:22:15'),
(35, 2, 1, 'hfhfhf', 1, '2025-06-27 20:22:34'),
(36, 1, 2, 'Hshs', 1, '2025-06-27 20:22:49'),
(37, 1, 2, 'Bshsh', 1, '2025-06-27 20:22:56'),
(38, 1, 2, 'Help sir', 1, '2025-06-27 20:23:08'),
(39, 1, 2, 'When', 1, '2025-06-27 20:23:38'),
(40, 1, 2, 'Why', 1, '2025-06-27 20:23:48'),
(41, 1, 2, 'Vshsh', 1, '2025-06-27 20:23:57'),
(42, 1, 2, 'Nowww', 1, '2025-06-27 20:28:31'),
(43, 1, 2, 'Then', 1, '2025-06-27 20:33:28'),
(44, 1, 2, 'Heh', 1, '2025-06-27 20:33:48'),
(45, 1, 2, 'Gshsh', 1, '2025-06-27 20:36:33'),
(46, 1, 2, 'Plusss all', 1, '2025-06-27 20:36:45'),
(47, 1, 2, 'Bzbzb', 1, '2025-06-27 20:36:58'),
(48, 1, 2, 'We\'ll be back', 1, '2025-06-27 20:37:25'),
(49, 1, 2, 'Waooo', 0, '2025-06-27 20:38:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_online` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `phone`, `name`, `password`, `profile_pic`, `last_seen`, `created_at`, `is_online`) VALUES
(1, '0757003628', 'isamel', '$2y$10$3aJsnKqb/IHTkd59aL.zB.mwjgzkLXgNfoCABHjeepnkM4WHrqlgG', NULL, '2025-06-27 23:38:39', '2025-06-24 19:31:56', 1),
(2, '0773494188', 'praise', '$2y$10$cQQwTwMf./UfHAiS3zQAHeYHxo.aXvwa4/ww1E3xMIbYPC.CQllAK', NULL, '2025-06-27 23:42:25', '2025-06-24 19:32:44', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`user_id`,`contact_id`),
  ADD KEY `contact_id` (`contact_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `contacts_ibfk_2` FOREIGN KEY (`contact_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
