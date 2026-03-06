<?php
	$bootstrap_path = {{ bootstrap_path_json|raw }};

	function feedback_find_root($start_dir) {
		$dir = $start_dir;
		while (true) {
			if (is_file($dir . '/inc/bootstrap.php')) {
				return $dir;
			}

			$parent = dirname($dir);
			if ($parent === $dir) {
				return null;
			}

			$dir = $parent;
		}
	}

	function feedback_get_client_ip() {
		if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
			return $_SERVER['HTTP_CF_CONNECTING_IP'];
		}

		if (!empty($_SERVER['REMOTE_ADDR'])) {
			return $_SERVER['REMOTE_ADDR'];
		}

		return '';
	}

	function feedback_ip_to_subnet($ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$parts = explode('.', $ip);
			if (count($parts) !== 4) {
				return false;
			}

			return $parts[0] . '.' . $parts[1] . '.0.0/16';
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

	if (!is_string($bootstrap_path) || $bootstrap_path === '' || !is_file($bootstrap_path)) {
		$root = feedback_find_root(__DIR__);
		if ($root !== null) {
			$bootstrap_path = $root . '/inc/bootstrap.php';
		}
	}

	if (!is_string($bootstrap_path) || $bootstrap_path === '' || !is_file($bootstrap_path)) {
		http_response_code(500);
		echo 'Unable to locate vichan bootstrap.';
		exit;
	}

	$bootstrap_dir = dirname($bootstrap_path);
	$root_dir = dirname($bootstrap_dir);
	if (is_dir($root_dir)) {
		@chdir($root_dir);
	}

	require_once $bootstrap_path;

	$store_real_ip = !empty($config['feedback_store_ip']);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$feedback = trim((string)($_POST['feedback'] ?? ''));

		if ($feedback !== '') {
			$subnet = '0.0.0.0';
			if ($store_real_ip) {
				$ip = feedback_get_client_ip();
				$resolved = feedback_ip_to_subnet($ip);
				if ($resolved !== false) {
					$subnet = $resolved;
				}
			}

			$feedback_single_line = preg_replace('/\s+/', ' ', $feedback);

			$query = prepare("INSERT INTO ``feedback`` (`time`, `ip`, `body`) VALUES (:time, :ip, :body)");
			$query->bindValue(':time', time(), PDO::PARAM_INT);
			$query->bindValue(':ip', $subnet, PDO::PARAM_STR);
			$query->bindValue(':body', $feedback_single_line, PDO::PARAM_STR);
			$query->execute() or error(db_error($query));
		}

		header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'), true, 303);
		exit;
	}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Feedback</title>
	<style>
		body {
			font-family: sans-serif;
			margin: 0;
			padding: 2rem 1rem;
			background: #f6f7f9;
			color: #222;
		}
		.container {
			max-width: 640px;
			margin: 0 auto;
			background: #fff;
			padding: 1.25rem;
			border: 1px solid #dadada;
		}
		textarea {
			width: 100%;
			min-height: 8rem;
			box-sizing: border-box;
			padding: 0.6rem;
		}
		button {
			margin-top: 0.6rem;
			padding: 0.5rem 1rem;
		}
	</style>
</head>
<body>
	<div class="container">
		<form method="post" autocomplete="off">
			<textarea id="feedbackField" name="feedback" placeholder="{{ placeholder|e }}" required></textarea><br>
			<button type="submit">Submit</button>
		</form>
	</div>
</body>
</html>
