<?php
	require 'info.php';

	function feedback_build($action, $settings, $board) {
		if ($action !== 'all') {
			return;
		}

		FeedbackTheme::build($settings);
	}

	class FeedbackTheme {
		public static function build($settings) {
			$feedback_path = self::normalize_feedback_path(isset($settings['feedback_path']) ? $settings['feedback_path'] : '/feedback');
			$output_dir = self::resolve_output_dir($feedback_path);

			self::ensure_dir($output_dir);
			self::write_index($output_dir, $settings);
		}

		private static function normalize_feedback_path($path) {
			$path = str_replace('\\', '/', trim((string)$path));
			if ($path === '' || $path === '/') {
				$path = '/feedback';
			}
			if (strpos($path, '..') !== false) {
				error(_('Invalid feedback path.'));
			}

			return '/' . trim($path, '/');
		}

		private static function resolve_output_dir($feedback_path) {
			global $config;

			$relative = ltrim($feedback_path, '/');
			$home = isset($config['dir']['home']) ? rtrim($config['dir']['home'], '/\\') : '';

			if ($home === '') {
				return $relative;
			}

			return $home . '/' . $relative;
		}

		private static function ensure_dir($path) {
			if (is_dir($path)) {
				return;
			}

			if (!@mkdir($path, 0775, true) && !is_dir($path)) {
				error(sprintf(_('Unable to create directory: %s'), $path));
			}
		}

		private static function write_index($output_dir, $settings) {
			global $config;

			$placeholder = isset($settings['placeholder']) ? trim((string)$settings['placeholder']) : '';
			if ($placeholder === '') {
				$placeholder = 'You can submit anonymous feedback in this form!';
			}

			$root = isset($config['dir']['template']) ? dirname($config['dir']['template']) : getcwd();
			$bootstrap_path = rtrim(str_replace('\\', '/', $root), '/');
			$bootstrap_path .= '/inc/bootstrap.php';

			$bootstrap_path_json = json_encode($bootstrap_path, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($bootstrap_path_json === false) {
				$bootstrap_path_json = '""';
			}

			file_write(
				$output_dir . '/index.php',
				Element('themes/feedback/feedback_index.php', array(
					'placeholder' => $placeholder,
					'bootstrap_path_json' => $bootstrap_path_json
				))
			);
		}
	}
