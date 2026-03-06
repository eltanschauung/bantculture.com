<?php
	$theme = array();

	$theme['name'] = 'IpAccessAuth';
	$theme['description'] = 'Generates an /auth-style password gate that appends visitor ranges to an IP access list.';
	$theme['version'] = 'v1.0';

	$theme['config'] = array();

	$theme['config'][] = array(
		'title' => 'Auth path',
		'name' => 'auth_path',
		'type' => 'text',
		'default' => '/auth',
		'comment' => 'Directory where auth files are generated (example: /auth).'
	);

	$theme['config'][] = array(
		'title' => 'Passwords',
		'name' => 'passwords',
		'type' => 'text',
		'default' => 'password,nigel,whitehouse',
		'comment' => 'Comma-separated passwords.'
	);

	$theme['config'][] = array(
		'title' => 'Message',
		'name' => 'message',
		'type' => 'text',
		'default' => 'Enter a password to gain access.'
	);

	$theme['config'][] = array(
		'title' => 'Access list file',
		'name' => 'access_file',
		'type' => 'text',
		'default' => 'access.conf',
		'comment' => 'Relative to the vichan root unless an absolute path is provided.'
	);

	$theme['build_function'] = 'ipaccessauth_build';
	$theme['install_callback'] = 'ipaccessauth_install';

	if (!function_exists('ipaccessauth_install')) {
		function ipaccessauth_install($settings) {
			$auth_path = isset($settings['auth_path']) ? trim($settings['auth_path']) : '';
			if ($auth_path === '') {
				return array(false, _('Auth path cannot be empty.'));
			}
			if (strpos($auth_path, '..') !== false) {
				return array(false, _('Auth path cannot contain "..".'));
			}

			$passwords = isset($settings['passwords']) ? trim($settings['passwords']) : '';
			if ($passwords === '') {
				return array(false, _('Passwords cannot be empty.'));
			}
		}
	}

