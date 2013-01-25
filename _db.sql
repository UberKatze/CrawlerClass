CREATE TABLE IF NOT EXISTS `example_threads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pulse` timestamp NULL DEFAULT NULL,
  `thread_type` varchar(20) NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;