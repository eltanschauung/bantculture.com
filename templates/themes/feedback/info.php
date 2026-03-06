<?php
	$theme = array();

	$theme['name'] = 'Feedback';
	$theme['description'] = 'Generates a public feedback submission page backed by the feedback moderation queue.';
	$theme['version'] = 'v1.0';

	$theme['config'] = array();

	$theme['config'][] = array(
		'title' => 'Feedback path',
		'name' => 'feedback_path',
		'type' => 'text',
		'default' => '/feedback',
		'comment' => 'Directory where feedback files are generated (example: /feedback).'
	);

	$theme['config'][] = array(
		'title' => 'Field placeholder',
		'name' => 'placeholder',
		'type' => 'text',
		'default' => 'You can submit anonymous feedback in this form!'
	);

	$theme['build_function'] = 'feedback_build';
	$theme['install_callback'] = 'feedback_install';

	if (!function_exists('feedback_install')) {
		function feedback_install($settings) {
			$feedback_path = isset($settings['feedback_path']) ? trim($settings['feedback_path']) : '';
			if ($feedback_path === '') {
				return array(false, _('Feedback path cannot be empty.'));
			}
			if (strpos($feedback_path, '..') !== false) {
				return array(false, _('Feedback path cannot contain "..".'));
			}

			if (!query("CREATE TABLE IF NOT EXISTS ``feedback`` (
				`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`time` int(11) NOT NULL,
				`ip` varchar(39) CHARACTER SET ascii NOT NULL,
				`body` text NOT NULL,
				`unread` tinyint(1) NOT NULL DEFAULT 1,
				PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1")) {
				return array(false, _('Unable to create feedback table.'));
			}

			if (!query("CREATE TABLE IF NOT EXISTS ``feedback_comments`` (
				`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`feedback_id` int(11) unsigned NOT NULL,
				`mod` int(11) DEFAULT NULL,
				`time` int(11) NOT NULL,
				`body` text NOT NULL,
				PRIMARY KEY (`id`),
				KEY `feedback_time` (`feedback_id`,`time`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")) {
				return array(false, _('Unable to create feedback comments table.'));
			}

			$unread_column = query("SHOW COLUMNS FROM ``feedback`` LIKE 'unread'");
			if (!$unread_column) {
				return array(false, _('Unable to inspect feedback schema.'));
			}
			if (!$unread_column->fetch(PDO::FETCH_ASSOC)) {
				if (!query("ALTER TABLE ``feedback`` ADD `unread` tinyint(1) NOT NULL DEFAULT 1")) {
					return array(false, _('Unable to add unread column to feedback table.'));
				}
			}
		}
	}
