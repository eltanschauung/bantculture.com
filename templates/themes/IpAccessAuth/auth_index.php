<?php
	$allowed_passwords = {{ passwords_json|raw }};
	$access_file_setting = {{ access_file_json|raw }};

	function ipaccessauth_get_ip() {
		if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		}

		if (!empty($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		}

		return '';
	}

	function ipaccessauth_to_subnet($ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$parts = explode('.', $ip);
			if (count($parts) !== 4) {
				return false;
			}
			return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$packed = inet_pton($ip);
			if ($packed === false) {
				return false;
			}

			$groups = unpack('n*', $packed);
			$groups = array_values($groups);
			$prefix = array_slice($groups, 0, 3);
			$prefix = array_map(function ($group) {
				return dechex($group);
			}, $prefix);

			return implode(':', $prefix) . '::/48';
		}

		return false;
	}

	function ipaccessauth_find_root($start_dir) {
		$dir = $start_dir;
		while (true) {
			if (is_file($dir . '/inc/config.php')) {
				return $dir;
			}

			$parent = dirname($dir);
			if ($parent === $dir) {
				return null;
			}

			$dir = $parent;
		}
	}

	function ipaccessauth_resolve_access_file($setting) {
		$setting = trim((string)$setting);
		if ($setting === '') {
			$setting = 'access.conf';
		}

		if (preg_match('/^(?:[A-Za-z]:[\\\\\\/]|\/)/', $setting) === 1) {
			return $setting;
		}

		$root = ipaccessauth_find_root(__DIR__);
		if ($root === null) {
			$root = dirname(__DIR__);
		}

		return rtrim($root, '/\\') . '/' . ltrim(str_replace('\\', '/', $setting), '/');
	}

	$success = false;
	$feedback = '';

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$entered_password = strtolower(trim(isset($_POST['password']) ? (string)$_POST['password'] : ''));
		if ($entered_password === '') {
			$feedback = 'Password is required.';
		} elseif (!in_array($entered_password, $allowed_passwords, true)) {
			$feedback = 'Invalid password.';
		} else {
			$ip_address = ipaccessauth_get_ip();
			$subnet = ipaccessauth_to_subnet($ip_address);

			if ($subnet === false) {
				$feedback = 'Unable to determine network range.';
			} else {
				$access_file = ipaccessauth_resolve_access_file($access_file_setting);
				$directory = dirname($access_file);

				if ($directory !== '' && $directory !== '.' && !is_dir($directory) && !@mkdir($directory, 0775, true)) {
					$feedback = 'Unable to create access list directory.';
				} elseif (!file_exists($access_file) && @file_put_contents($access_file, '', LOCK_EX) === false) {
					$feedback = 'Unable to initialize access list.';
				} else {
					$existing = @file($access_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
					if (!is_array($existing)) {
						$existing = array();
					}

					if (!in_array($subnet, $existing, true)) {
						$log_line = '#' . $entered_password . ' ' . date('Y-m-d H:i:s') . ' ' . $ip_address;
						$entry = $subnet . PHP_EOL . $log_line . PHP_EOL;
						if (@file_put_contents($access_file, $entry, FILE_APPEND | LOCK_EX) === false) {
							$feedback = 'Unable to update access list.';
						} else {
							$success = true;
						}
					} else {
						$success = true;
					}
				}
			}
		}
	}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>IP Access Auth</title>
	<style>
		body {
			font-family: sans-serif;
			margin: 0;
			padding: 2rem 1rem;
			background: #f6f7f9;
			color: #222;
		}
		.container {
			max-width: 420px;
			margin: 0 auto;
			background: #fff;
			padding: 1.25rem;
			border: 1px solid #dadada;
		}
		.message {
			margin: 0 0 1rem 0;
		}
		input[type="password"] {
			width: 100%;
			box-sizing: border-box;
			margin-bottom: 0.75rem;
			padding: 0.5rem;
		}
		button {
			padding: 0.5rem 1rem;
		}
		.result {
			margin: 0 0 0.75rem 0;
		}
		.success {
			color: #0b7a2f;
		}
		.error {
			color: #9d1c1c;
		}
	</style>
</head>
<body>
	<div class="container">
		<p class="message">{{ message|e }}</p>
		<?php if ($success): ?>
			<p class="result success">Access granted. You can continue to the board.</p>
		<?php elseif ($feedback !== ''): ?>
			<p class="result error"><?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?></p>
		<?php endif; ?>
		<form method="post" autocomplete="off">
			<input type="password" name="password" autocomplete="off" required>
			<button type="submit">Enter</button>
		</form>
	</div>
</body>
</html>

