<?php

// надо определить текущий путь скрипта в файловой системе сервера

/*
 * Array
  (
  [UNIQUE_ID] =&gt; V0qk6MCoZAIAANor4FgAAAAD
  [HTTP_HOST] =&gt; localhost
  [HTTP_CONNECTION] =&gt; keep-alive
  [HTTP_CACHE_CONTROL] =&gt; max-age=0
  [HTTP_UPGRADE_INSECURE_REQUESTS] =&gt; 1
  [HTTP_USER_AGENT] =&gt; Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36
  [HTTP_DNT] =&gt; 1
  [HTTP_ACCEPT_ENCODING] =&gt; gzip, deflate, sdch
  [HTTP_ACCEPT_LANGUAGE] =&gt; ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4
  [PATH] =&gt; /usr/bin:/bin:/usr/sbin:/sbin
  [DYLD_LIBRARY_PATH] =&gt; /Applications/XAMPP/xamppfiles/lib
  [SERVER_SIGNATURE] =&gt;
  [SERVER_SOFTWARE] =&gt; Apache/2.4.9 (Unix) PHP/5.5.11 OpenSSL/1.0.1g mod_perl/2.0.8-dev Perl/v5.16.3
  [SERVER_NAME] =&gt; localhost
  [SERVER_ADDR] =&gt; ::1
  [SERVER_PORT] =&gt; 80
  [REMOTE_ADDR] =&gt; ::1
  [DOCUMENT_ROOT] =&gt; /Applications/XAMPP/xamppfiles/htdocs
  [REQUEST_SCHEME] =&gt; http
  [CONTEXT_PREFIX] =&gt;
  [CONTEXT_DOCUMENT_ROOT] =&gt; /Applications/XAMPP/xamppfiles/htdocs
  [SERVER_ADMIN] =&gt; you@example.com
  [SCRIPT_FILENAME] =&gt; /Applications/XAMPP/xamppfiles/htdocs/escrow.com/index.php
  [REMOTE_PORT] =&gt; 56245
  [GATEWAY_INTERFACE] =&gt; CGI/1.1
  [SERVER_PROTOCOL] =&gt; HTTP/1.1
  [REQUEST_METHOD] =&gt; GET
  [QUERY_STRING] =&gt;
  [REQUEST_URI] =&gt; /escrow.com/
  [SCRIPT_NAME] =&gt; /escrow.com/index.php
  [PHP_SELF] =&gt; /escrow.com/index.php
  [REQUEST_TIME_FLOAT] =&gt; 1464509672.638
  [REQUEST_TIME] =&gt; 1464509672
  )
  stop
 */

class Environment {

	public static function findFile($folder, $which) {

		//$_SERVER['DOCUMENT_ROOT']  - document root

		$files = glob($folder . '*');

		foreach ($files as $file) {

			if (strtoupper(pathinfo($file)['basename']) == strtoupper($which)) {
				return pathinfo($file)['dirname'];
			}

			if (is_dir($file)) {
				$find = self::findFile($file . '/', $which);
				if (!empty($find)) {
					return $find;
				}
			}
		}

		return false;
	}

	public static function cutTheSlash($path) {
		return substr($path, -1) == '/'
				? substr($path, 0, -1)
				: $path;
	}

	public static function addTheSlash($path) {
		return substr($path, -1) == '/'
				? $path
				: $path . '/';
	}

	public static function get($key = null) {

		if (empty($_SESSION['environment'])) {

			$siteRoot = self::addTheSlash(pathinfo($_SERVER['SCRIPT_FILENAME'])['dirname']);

			$l = false;
			if (file_exists($siteRoot . 'images/path_config.php')) {
				include($siteRoot . 'images/path_config.php');
				$l = true;
			}	
			
				
			if (!empty($l) && !file_exists($_SESSION['environment']['vh2015'])){
				unlink($siteRoot . 'images/path_config.php');
				$l = false;
			}
				
			if (empty($l)) {
				
				$_SESSION['environment'] = [
					'script_filename'	 => $_SERVER['SCRIPT_FILENAME'],
					'php_self'			 => $_SERVER['PHP_SELF'],
					'site_root'			 => self::addTheSlash(pathinfo($_SERVER['SCRIPT_FILENAME'])['dirname']),
					'document_root'		 => self::addTheSlash($_SERVER['DOCUMENT_ROOT']),
					'framework'			 => self::addTheSlash(self::findFile($_SERVER['DOCUMENT_ROOT'] . '/', 'YiiBase.php')),
					'vh2015'			 => self::addTheSlash(self::findFile($_SERVER['DOCUMENT_ROOT'], '2016.txt')),
					'self_url'			 => self::addTheSlash("//" . $_SERVER['HTTP_HOST'] . self::cutTheSlash(pathinfo($_SERVER['SCRIPT_NAME'])['dirname'])),
				];

				//calculate web url to vh2015  TODO: вынести в отдельную функцию и тоже самое сделать для vendor
				if (strpos($_SESSION['environment']['vh2015'], $_SESSION['environment']['site_root']) !== false) { //inside website
					$_SESSION['environment']['vh2015_url'] = self::addTheSlash($_SESSION['environment']['self_url'] . explode($_SESSION['environment']['site_root'], $_SESSION['environment']['vh2015'])[1]);
				} else {
					
					$tail = explode($_SESSION['environment']['document_root'], $_SESSION['environment']['vh2015'])[1];
					$path_tail = explode($_SESSION['environment']['document_root'], $_SESSION['environment']['php_self'])[0];
					$add = '..';
					for ($i = 0; $i < (count(explode('/', $path_tail)) - 3); $i++) {
						$add .= '/..';
					}

					$_SESSION['environment']['vh2015_url'] = $_SESSION['environment']['self_url'] . $add . $tail . '/';
				}
				
				$h = '<?php $_SESSION["environment"] = json_decode(\''. json_encode($_SESSION['environment']) .'\', true); ?>';
				
				file_put_contents($siteRoot . 'images/path_config.php', $h);
				
				//print_r($_SESSION['environment']);
				
			}
		}

		if (empty($key)) {
			return $_SESSION['environment'];
		} else {
			return $_SESSION['environment'][$key];
		}
	}

}

class Settings {

	public static function getPath() {
		return Environment::get('site_root');
	}

}
