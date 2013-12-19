<?php
/**
 * Clamd - A ClamAV plugin for CakePHP
 * Copyright (C) 2009 Stichting Lone Wolves
 * Written by Sander Marechal <s.marechal@jejik.com>
 *
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @package clamd
 * @copyright Copyright (C) 2009 Stichting Lone Wolves
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * 
 * Modified to work as a plugin to osTicket, to scan attachments.
 */

/**
 * Interface to a ClamAV daemon
 */
class Clamd extends Object
{
	/**#@+
	 * Scan result status
	 */
	const OK          = 1;
	const FOUND       = 2;
	const ERROR       = 4;
	/**#@-*/

	/** @var string Object description */
	public $description = 'Interface to a ClamAV daemon';

	/** @var array Default clamd configuration */
	public $_baseConfig = array(
		'host'		=> 'unix:///var/run/clamav/clamd.ctl',
		'port'		=> 0,
		'timeout'	=> 60
	);

	/** @var array The configuration settings */
	public $config = array();

	/** @var resource Reference to the connection to clamd */
	public $connection = null;

	/** @var boolean The state of the connection */
	public $connected = false;

	/** @var array The last error number and string */
	public $lastError = array();

	/**
	 * Constructor
	 *
	 * @param array $config Clamd configuration, which will be merged with the base configuration
	 */
	public function __construct($config = array())
	{
		parent::__construct();

		$this->config = array_merge($this->_baseConfig, $config);
	}

	/**
	 * Connect to clamd
	 *
	 * @return boolean Success
	 */
	public function connect()
	{
		if ($this->connection != null) {
			$this->disconnect();
		}

		$this->connection = @fsockopen($this->config['host'], $this->config['port'], $errNum, $errStr, $this->config['timeout']);
		if (!empty($errNum) || !empty($errStr)) {
			$this->setLastError($errStr, $errNum);
		}

		return $this->connected = is_resource($this->connection);
	}

	/**
	 * Disconnect from spamd
	 *
	 * @return boolean Success
	 */
	public function disconnect()
	{
		if (!is_resource($this->connection)) {
			$this->connected = false;
			return true;
		}

		$this->connected = !fclose($this->connection);
		if (!$this->connected) {
			$this->connection = null;
		}

		return !$this->connected;
	}

	/**
	 * Get the last error as a string.
	 *
	 * @return string Last error
	 */
	public function lastError()
	{
		if (!empty($this->lastError)) {
			return $this->lastError['num'].': '.$this->lastError['str'];
		} else {
			return null;
		}
	}

	/**
	 * Clear the last error
	 */
	public function clearLastError()
	{
		$this->lastError = array();
	}

	/**
	 * Set the last error.
	 *
	 * @param integer $errNum Error code
	 * @param string $errStr Error string
	 */
	public function setLastError($errNum, $errStr)
	{
		$this->lastError = array('num' => $errNum, 'str' => $errStr);
	}

	/**
	 * Write to the spamd socket
	 *
	 * @param string $data The data to write to the socket
	 * @return boolean Success
	 */
	function write($data) {
		if (!$this->connected) {
			if (!$this->connect()) {
				return false;
			}
		}

		return @fwrite($this->connection, $data, strlen($data));
	}

	/**
	 * Read from the spamd socket and close the connection
	 *
	 * @return mixed Socket data
	 */
	public function read()
	{
		if (!$this->connected) {
			return false;
		}

		$buffer = '';
		while (!feof($this->connection)) {
			$buffer .= fread($this->connection, 1024);
		}

		$this->disconnect();
		return $buffer;
	}

	/**
	 * Send a command to spamd
	 *
	 * @param string command The command to send
	 * @return boolean Success
	 */
	public function send($command)
	{
		if (in_array($command[0], array('n', 'z'))) {
			$command[0] = 'n';
		} else {
			$command = 'n' . $command;
		}

		if (substr($command, -1) != "\n") {
			$command .= "\n";
		}

		if (!$this->write($command)) {
			$this->disconnect();
			return false;
		}

		return true;
	}
	
	/**
	 * Execute a command on the spamd socket and wait for a response. The response
	 * will be stripped of session IDs
	 *
	 * @param string $command The command to execute
	 * @return mixed The result or false on failure
	 */
	public function exec($command)
	{
		if (!$this->send($command)) {
			return false;
		}

		return $this->read();
	}

	/**
	 * The PING command
	 *
	 * @return boolean Success
	 */
	public function ping()
	{
		return (trim($this->exec('PING')) == 'PONG');
	}

	/**
	 * Return the correct status constant for a status string
	 *
	 * @param string $code The status code in string form
	 * @result int The status code const
	 */
	protected function status_code($code)
	{
		$code = strtolower($code);
		if ($code == 'ok') {
			return self::OK;
		}
		if ($code == 'found') {
			return self::FOUND;
		}
		return self::ERROR;
	}

	/**
	 * Scan a file or directory recursively.
	 * 
	 * The result of the scan is an array of scan results. Each result is an array:
	 *
	 * array(3) {
	 *     ['file'] => '/full/path/to/file'
	 *     ['status'] => self::OK | self::FOUND | self::ERROR
	 *     ['message'] => empty | virus name | error message
	 * }
	 *
	 * Note that if you recursively scan a directory the results will only contain
	 * positive matches and errors. Clamd does not return clean files when scanning
	 * recursively.
	 *
	 * @param string $path full path to the file to scan
	 * @return mixed An array of scan results or false on failure
	 */
	public function rscan($path, $continue = false)
	{
		if (!is_file($path) && !is_dir($path)) {
			$this->setLastError(0, __('Recursive scan path is not a readable file or directory', true));
			return false;
		}

		$command = $continue ? 'CONTSCAN' : 'SCAN';
		if (!$response = $this->exec($command . ' ' . $path)) {
			return false;
		}

		$result = array();
		foreach (explode("\n", $response) as $line) {
			if (!preg_match('/^([^:]+):(?: (.*))? (OK|FOUND|ERROR)$/', $line, $match)) {
				continue;
			}

			$result[] = array(
				'file' => $match[1],
				'status' => $this->status_code($match[3]),
				'msg' => $match[2]
			);
		}

		return $result;
	}

	/**
	 * Scan a single file
	 *
	 * @param string $path Full path to the file to scan
	 * @param string &$msg Name of the virus found or the clamd error message
	 * @return int The scan result status (self::OK, self::FOUND or self::ERROR) or false on failure
	 */
	public function scan($path, &$msg = '')
	{
		if (!is_file($path)) {
			$this->setLastError(0, __('Scan path is not a readable file', true));
			return false;
		}

		if (!$result = $this->rscan($path)) {
			return false;
		}

		$result = array_shift($result);
		$msg = $result['msg'];
		return $result['status'];
	}
}

/**
 * A simple shell to interface with Clamd
 */
class ClamdShell extends Shell
{
	/** @var object Reference to the Clamd object */
	private $clamd = null;

	/**
	 * Print a welcome message
	 */
	public function initialize() {
		$this->clamd = null;
		$this->out('Cake Clamd Shell. Type "help" for usage information.');
		$this->hr();
	}

	/**
	 * Main shell execution loop
	 */
	public function main()
	{
		while (true) {
			$text = $this->in('');

			if (substr($text, 0, 7) == 'connect') {
				sscanf($text, '%s %s %d %d', $command, $host, $port, $timeout);
				$this->clamd = new Clamd($host, $port, $timeout);
				continue;
			}

			if ($text == 'quit' || $text == 'exit' || $text == 'q') {
				$this->_stop();
			}

			if (substr($text, 0, 4) == 'help') {
				$this->help();
				continue;
			}

			if ($this->clamd == null) {
				$this->out('Not connected. Connect to a ClamAV daemon first');
				continue;
			}

			if ($text == 'ping') {
				if (!$response = $this->clamd->ping()) {
					$this->out('Error: ' . $this->clamd->lastError());
				} else {
					$this->out('PONG');
				}

				continue;
			}

			if (substr($text, 0, 4) == 'scan') {
				$msg = '';
				$file = substr($text, 5);
				$this->clamd->clearLastError();
				$response = $this->clamd->scan($file, $msg);

				if ($response === false) {
					$this->out('Error: ' . $this->clamd->lastError());
				} elseif ($response == Clamd::OK) {
					$this->out($file . ' is clean');
				} elseif ($response == Clamd::FOUND) {
					$this->out($file . ' is infected with ' . $msg);
				} else {
					$this->out('Error: ' . $msg);
				}
				continue;
			}

			if (substr($text, 0, 5) == 'rscan') {
				$file = substr($text, 6);
				$this->clamd->clearLastError();
				if (!$response = $this->clamd->rscan($file, true)) {
					$this->out('Error: ' . $this->clamd->lastError());
					continue;
				}

				foreach ($response as $file) {
					if ($file['status'] == Clamd::FOUND) {
						$this->out($file['file'] . ' is infected with ' . $file['msg']);
					} else {
						$this->out($file['file'] . ' error: ' . $file['msg']);
					}
				}
				continue;
			}

			$this->out('Unknown command: ' . $text);
		}
	}

	/**
	 * Print shell help
	 */
	public function help()
	{
		$this->out('Interactive commandline interface to the Clamd plugin');
		$this->hr();
		$this->out("Usage: cake clamd");
		$this->hr();
		$this->out('Commands:');
		$this->out("\n\texit|quit|q\n\t\tQuit the shell");
		$this->out("\n\tconnect <host> [<port> [<timeout>]]\n\t\tConnect to a ClamAV daemon");
		$this->out("\n\tping\n\t\tSend a PING command to the clamav daemon");
		$this->out("\n\tscan <file>\n\t\tScan <file> for viruses");
		$this->out("\n\trscan <directory>\n\t\tRecursively scan <directory> for viruses. Only infected files and errors are returned.");
		$this->out("\n\thelp\n\t\tShow this help");
		$this->out('');
	}
}

?>
