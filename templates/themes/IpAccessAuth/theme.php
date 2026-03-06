<?php
	require 'info.php';

	function ipaccessauth_build($action, $settings, $board) {
		if ($action !== 'all') {
			return;
		}

		IpAccessAuth::build($settings);
	}

	class IpAccessAuth {
		public static function build($settings) {
			$auth_path = self::normalize_auth_path(isset($settings['auth_path']) ? $settings['auth_path'] : '/auth');
			$output_dir = self::resolve_output_dir($auth_path);
			$access_file_path = self::resolve_access_file_path($settings);

			self::ensure_dir($output_dir);
			self::copy_static_files($output_dir);
			self::write_index($output_dir, $settings);
			self::ensure_access_file($access_file_path);
		}

		private static function normalize_auth_path($path) {
			$path = str_replace('\\', '/', trim((string)$path));
			if ($path === '' || $path === '/') {
				$path = '/auth';
			}
			if (strpos($path, '..') !== false) {
				error(_('Invalid auth path.'));
			}

			return '/' . trim($path, '/');
		}

		private static function resolve_output_dir($auth_path) {
			global $config;

			$relative = ltrim($auth_path, '/');
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

		private static function copy_static_files($output_dir) {
			global $config;

			$source = $config['dir']['themes'] . '/IpAccessAuth/auth.htaccess';
			$target = $output_dir . '/.htaccess';

			if (!@copy($source, $target)) {
				error(sprintf(_('Unable to copy auth .htaccess to %s'), $target));
			}
		}

		private static function write_index($output_dir, $settings) {
			$passwords = self::normalize_passwords(isset($settings['passwords']) ? $settings['passwords'] : '');
			if (empty($passwords)) {
				$passwords = self::normalize_passwords('password,nigel,whitehouse');
			}

			$message = isset($settings['message']) ? trim((string)$settings['message']) : '';
			if ($message === '') {
				$message = 'Enter a password to gain access.';
			}

			$access_file = isset($settings['access_file']) ? trim((string)$settings['access_file']) : '';
			if ($access_file === '') {
				$access_file = 'access.conf';
			}

			$passwords_json = json_encode($passwords, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($passwords_json === false) {
				$passwords_json = '[]';
			}

			$access_file_json = json_encode($access_file, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($access_file_json === false) {
				$access_file_json = '"access.conf"';
			}

			file_write(
				$output_dir . '/index.php',
				Element('themes/IpAccessAuth/auth_index.php', array(
					'passwords_json' => $passwords_json,
					'access_file_json' => $access_file_json,
					'message' => $message
				))
			);
		}

		private static function normalize_passwords($password_csv) {
			$passwords = array_map('trim', explode(',', (string)$password_csv));
			$passwords = array_filter($passwords, function ($password) {
				return $password !== '';
			});
			$passwords = array_map('strtolower', $passwords);

			return array_values(array_unique($passwords));
		}

		private static function resolve_access_file_path($settings) {
			global $config;

			$access_file = isset($settings['access_file']) ? trim((string)$settings['access_file']) : '';
			if ($access_file === '') {
				$access_file = 'access.conf';
			}

			if (self::is_absolute_path($access_file)) {
				return $access_file;
			}

			$home = isset($config['dir']['home']) ? rtrim($config['dir']['home'], '/\\') : '';
			$relative = ltrim(str_replace('\\', '/', $access_file), '/');

			if ($home === '') {
				return $relative;
			}

			return $home . '/' . $relative;
		}

		private static function is_absolute_path($path) {
			return preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\/)/', $path) === 1;
		}

		private static function ensure_access_file($path) {
			$dir = dirname($path);
			if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
				if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
					error(sprintf(_('Unable to create access file directory: %s'), $dir));
				}
			}

			if (!file_exists($path) && @file_put_contents($path, '', LOCK_EX) === false) {
				error(sprintf(_('Unable to create access file: %s'), $path));
			}
		}
	}
