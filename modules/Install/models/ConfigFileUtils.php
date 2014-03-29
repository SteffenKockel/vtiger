<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

class Install_ConfigFileUtils_Model {

	private $rootDirectory;
	private $dbHostname;
	private $dbPort;
	private $dbUsername;
	private $dbPassword;
	private $dbName;
	private $dbType;
	private $siteUrl;
	private $cacheDir;
	private $vtCharset = 'UTF-8';
	private $currencyName;
	private $adminEmail;

	function Install_ConfigFileUtils_Model($configFileParameters) {
		if (isset($configFileParameters['root_directory']))
			$this->rootDirectory = $configFileParameters['root_directory'];

		if (isset($configFileParameters['db_hostname'])) {
			if(strpos($configFileParameters['db_hostname'], ":")) {
				list($this->dbHostname,$this->dbPort) = explode(":",$configFileParameters['db_hostname']);
			} else {
				$this->dbHostname = $configFileParameters['db_hostname'];
			}
		}

		if (isset($configFileParameters['db_username'])) $this->dbUsername = $configFileParameters['db_username'];
		if (isset($configFileParameters['db_password'])) $this->dbPassword = $configFileParameters['db_password'];
		if (isset($configFileParameters['db_name'])) $this->dbName = $configFileParameters['db_name'];
		if (isset($configFileParameters['db_type'])) $this->dbType = $configFileParameters['db_type'];
		if (isset($configFileParameters['site_URL'])) $this->siteUrl = $configFileParameters['site_URL'];
		if (isset($configFileParameters['admin_email'])) $this->adminEmail = $configFileParameters['admin_email'];
		if (isset($configFileParameters['currency_name'])) $this->currencyName = $configFileParameters['currency_name'];
		if (isset($configFileParameters['vt_charset'])) $this->vtCharset = $configFileParameters['vt_charset'];

		// update default port
		if ($this->dbPort == '') $this->dbPort = self::getDbDefaultPort($this->dbType);

		$this->cacheDir = 'cache/';
	}

	static function getDbDefaultPort($dbType) {
		if(Install_Utils_Model::isMySQL($dbType)) {
			return "3306";
		}
	}

	function createConfigFile() {
		/* open template configuration file read only */
		$templateFilename = 'config.template.php';
		$templateHandle = fopen($templateFilename, "r");
		if($templateHandle) {
			/* open include configuration file write only */
			$includeFilename = 'config.inc.php';
	      	$includeHandle = fopen($includeFilename, "w");
			if($includeHandle) {
			   	while (!feof($templateHandle)) {
	  				$buffer = fgets($templateHandle);

		 			/* replace _DBC_ variable */
		  			$buffer = str_replace( "_DBC_SERVER_", $this->dbHostname, $buffer);
		  			$buffer = str_replace( "_DBC_PORT_", $this->dbPort, $buffer);
		  			$buffer = str_replace( "_DBC_USER_", $this->dbUsername, $buffer);
		  			$buffer = str_replace( "_DBC_PASS_", $this->dbPassword, $buffer);
		  			$buffer = str_replace( "_DBC_NAME_", $this->dbName, $buffer);
		  			$buffer = str_replace( "_DBC_TYPE_", $this->dbType, $buffer);

		  			$buffer = str_replace( "_SITE_URL_", $this->siteUrl, $buffer);

		  			/* replace dir variable */
		  			$buffer = str_replace( "_VT_ROOTDIR_", $this->rootDirectory, $buffer);
		  			$buffer = str_replace( "_VT_CACHEDIR_", $this->cacheDir, $buffer);
		  			$buffer = str_replace( "_VT_TMPDIR_", $this->cacheDir."images/", $buffer);
		  			$buffer = str_replace( "_VT_UPLOADDIR_", $this->cacheDir."upload/", $buffer);
			      	$buffer = str_replace( "_DB_STAT_", "true", $buffer);

					/* replace charset variable */
					$buffer = str_replace( "_VT_CHARSET_", $this->vtCharset, $buffer);

			      	/* replace master currency variable */
		  			$buffer = str_replace( "_MASTER_CURRENCY_", $this->currencyName, $buffer);

			      	/* replace the application unique key variable */
		      		$buffer = str_replace( "_VT_APP_UNIQKEY_", md5(time() + rand(1,9999999) + md5($this->rootDirectory)) , $buffer);

					/* replace support email variable */
					$buffer = str_replace( "_USER_SUPPORT_EMAIL_", $this->adminEmail, $buffer);

		      		fwrite($includeHandle, $buffer);
	      		}
	  			fclose($includeHandle);
	  		}
	  		fclose($templateHandle);
	  	}

	  	if ($templateHandle && $includeHandle) {
	  		return true;
	  	}
	  	return false;
	}

	function getConfigFileContents() {

		$configFileContents = "<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * (License); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * *********************************************************************************** */

include('vtigerversion.php');

// more than 8MB memory needed for graphics
// memory limit default value = 64M
ini_set('memory_limit','64M');

// helpdesk support email id and support name (Example: 'support@vtiger.com' and 'vtiger support')
\$HELPDESK_SUPPORT_EMAIL_ID = '{$this->adminEmail}';
\$HELPDESK_SUPPORT_NAME = 'your-support name';
\$HELPDESK_SUPPORT_EMAIL_REPLY_ID = \$HELPDESK_SUPPORT_EMAIL_ID;

\$dbconfig['db_server'] = '{$this->dbHostname}';
\$dbconfig['db_port'] = ':{$this->dbPort}';
\$dbconfig['db_username'] = '{$this->dbUsername}';
\$dbconfig['db_password'] = '{$this->dbPassword}';
\$dbconfig['db_name'] = '{$this->dbName}';
\$dbconfig['db_type'] = '{$this->dbType}';
\$dbconfig['db_status'] = 'true';

// TODO: test if port is empty
// TODO: set db_hostname dependending on db_type
\$dbconfig['db_hostname'] = \$dbconfig['db_server'].\$dbconfig['db_port'];

// log_sql default value = false
\$dbconfig['log_sql'] = false;

// persistent default value = true
\$dbconfigoption['persistent'] = true;

// autofree default value = false
\$dbconfigoption['autofree'] = false;

// debug default value = 0
\$dbconfigoption['debug'] = 0;

// seqname_format default value = '%s_seq'
\$dbconfigoption['seqname_format'] = '%s_seq';

// portability default value = 0
\$dbconfigoption['portability'] = 0;

// ssl default value = false
\$dbconfigoption['ssl'] = false;

\$host_name = \$dbconfig['db_hostname'];

\$site_URL = '{$this->siteUrl}';

// root directory path
\$root_directory = '{$this->rootDirectory}';

// cache direcory path
\$cache_dir = '{$this->cacheDir}';

// tmp_dir default value prepended by cache_dir = images/
\$tmp_dir = '{$this->cacheDir}images/';

// import_dir default value prepended by cache_dir = import/
\$import_dir = 'cache/import/';

// upload_dir default value prepended by cache_dir = upload/
\$upload_dir = '{$this->cacheDir}upload/';

// maximum file size for uploaded files in bytes also used when uploading import files
// upload_maxsize default value = 3000000
\$upload_maxsize = 3000000;

// flag to allow export functionality
// 'all' to allow anyone to use exports
// 'admin' to only allow admins to export
// 'none' to block exports completely
// allow_exports default value = all
\$allow_exports = 'all';

// files with one of these extensions will have '.txt' appended to their filename on upload
\$upload_badext = array('php', 'php3', 'php4', 'php5', 'pl', 'cgi', 'py', 'asp', 'cfm', 'js', 'vbs', 'html', 'htm', 'exe', 'bin', 'bat', 'sh', 'dll', 'phps', 'phtml', 'xhtml', 'rb', 'msi', 'jsp', 'shtml', 'sth', 'shtm');

// full path to include directory including the trailing slash
// includeDirectory default value = \$root_directory..'include/
\$includeDirectory = \$root_directory.'include/';

// list_max_entries_per_page default value = 20
\$list_max_entries_per_page = '20';

// limitpage_navigation default value = 5
\$limitpage_navigation = '5';

// history_max_viewed default value = 5
\$history_max_viewed = '5';

// default_module default value = Home
\$default_module = 'Home';

// default_action default value = index
\$default_action = 'index';

// set default theme
// default_theme default value = blue
\$default_theme = 'softed';

// show or hide time to compose each page
// calculate_response_time default value = true
\$calculate_response_time = true;

// default text that is placed initially in the login form for user name
// no default_user_name default value
\$default_user_name = '';

// default text that is placed initially in the login form for password
// no default_password default value
\$default_password = '';

// create user with default username and password
// create_default_user default value = false
\$create_default_user = false;

// default_user_is_admin default value = false
\$default_user_is_admin = false;

// if your MySQL/PHP configuration does not support persistent connections set this to true to avoid a large performance slowdown
// disable_persistent_connections default value = false
\$disable_persistent_connections = false;

//Master currency name
\$currency_name = '{$this->currencyName}';

// default charset
// default charset default value = 'UTF-8' or 'ISO-8859-1'
\$default_charset = '{$this->vtCharset}';

// default language
// default_language default value = en_us
\$default_language = 'en_us';

// add the language pack name to every translation string in the display.
// translation_string_prefix default value = false
\$translation_string_prefix = false;

//Option to cache tabs permissions for speed.
\$cache_tab_perms = true;

//Option to hide empty home blocks if no entries.
\$display_empty_home_blocks = false;

//Disable Stat Tracking of vtiger CRM instance
\$disable_stats_tracking = false;

// Generating Unique Application Key
\$application_unique_key = '".md5(time() + rand(1,9999999) + md5($this->rootDirectory)) ."';

// trim descriptions, titles in listviews to this value
\$listview_max_textlength = 40;

// Maximum time limit for PHP script execution (in seconds)
\$php_max_execution_time = 0;

// Set the default timezone as per your preference
//\$default_timezone = '';

/** If timezone is configured, try to set it */
if(isset(\$default_timezone) && function_exists('date_default_timezone_set')) {
	@date_default_timezone_set(\$default_timezone);
}";
		return $configFileContents;
	}
}
