<?php
// ZEFIRO DATABASE INTERFACE
// last known update: 2014-02-03

class DBI {
	
	public $connection;
	public $maintenance = false;
	
	protected $View;
	
	protected $options = array();
	protected $breadcrumbs = array();
	
	public $user = array();
	
	// CONSTRUCTOR ---------------------------------------------------------------
	
	public function __construct ($db_host,$db_user,$db_pass,$db_name) {
		if (!$this->db_connection = mysql_connect($db_host,$db_user,$db_pass)) {
			echo Z_ERROR_CONNECTION;
			die;
		} else {
			if (!function_exists('mysql_set_charset')) {
				function mysql_set_charset($charset,$dbc) {
					return mysql_query("set names $charset",$dbc);
				}
			}
			mysql_select_db($db_name);
			mysql_set_charset('utf8');
			$this->importUserData();
		}
	}
	
	// USER AUTHENTICATION -------------------------------------------------------
	
	public function importUserData () {
		// the user must have logged in to retrieve permissions
		if ( isset($_SESSION[Z_SESSION_NAME]['user']) &&
				 isset($_SESSION[Z_SESSION_NAME]['user']['name']) &&
				 isset($_SESSION[Z_SESSION_NAME]['user']['password']) ) {
			$user_querystring = "SELECT * FROM z_users WHERE name = '".$_SESSION[Z_SESSION_NAME]['user']['name']."' AND password = '".$_SESSION[Z_SESSION_NAME]['user']['password']."'";
			$user_query = mysql_query($user_querystring);
			if (mysql_num_rows($user_query)>0) {
				return $this->user = mysql_fetch_array($user_query,MYSQL_ASSOC);
			}
		}
		// the user can also retrieve permissions by a remote address
		elseif ( ($user_querystring = "SELECT * FROM z_users WHERE '".$_SERVER['REMOTE_ADDR']."' LIKE remote") &&
						 ($user_query = mysql_query($user_querystring)) &&
						 (mysql_num_rows($user_query)>0) ) {
			return $this->user = mysql_fetch_array($user_query,MYSQL_ASSOC);
		}
		// if nothing helps, the user is anonymous
		else {
			$user_querystring = "SELECT * FROM z_users WHERE name = 'anonymous'";
			$user_query = mysql_query($user_querystring);
			return $this->user = mysql_fetch_array($user_query,MYSQL_ASSOC);
		}
		return NULL;
	}
	
	public function checkUserAuthentication () {
		return (isset($_SESSION[Z_SESSION_NAME]['user']['password']));
	}
	
	public function checkUserPermission ($permission_name) {
		return (
			(isset($_SESSION[Z_SESSION_NAME]['user']['permissions']))
			&&
			(strpos ($_SESSION[Z_SESSION_NAME]['user']['permissions'],$permission_name) !== false)
		);
	}
	
	public function requireUserAuthentication () {
		if (!$this->checkUserAuthentication()) {
			header('Location: index');
		}
	}
	
	public function requireUserPermission ($permission_name) {
		if (!$this->checkUserPermission ($permission_name)) {
			header('Location: z_permission');
		}
	}
	
	// USER DATA -----------------------------------------------------------------
	
	public function setUserVar() {
		// setUserVar ( var_name , value , default )
		$args = func_get_args();
		if (isset($args[0]) && isset($args[1])) return $this->user[$args[0]] = $args[1];
		elseif (isset($args[2])) return $this->user[$args[0]] = $args[2];
	}
	
	public function getUserVar( $var_name ) {
		// getUserVar ( var_name )
		if (isset($this->user[$var_name])) return $this->user[$var_name];
		else return NULL;
	}
	
	// BREADCRUMBS ---------------------------------------------------------------
	
	public function addBreadcrumb () {
		$this->breadcrumbs[] = func_get_args();
	}
	
	public function getBreadcrumbs_HTML () {
		if (!isServerScriptName('index.php')) {
			$this->addBreadcrumb($GLOBALS['layout']->get('title'));
		}
		$html = NULL;
		$html .= '<a href="./">'.Z_HOME.'</a>';
		while (list($index, $crumb) = each($this->breadcrumbs)) {
			switch (count($crumb)) {
				case 1:
					$html .= Z_BREADCRUMB_SYMBOL.$crumb[0];
					break;
				case 2:
					$html .= Z_BREADCRUMB_SYMBOL.'<a class="breadcrumb" href="'.$crumb[1].'">'.$crumb[0].'</a>';
					break;
				case 3:
					$html .= Z_BREADCRUMB_SYMBOL.'<div class="breadcrumb"><span class="tooltip-text">'.$crumb[2].'</span><a href="'.$crumb[1].'">'.$crumb[0].'</a></div>';
					break;
			}
		}
		return $html;
	}
	
	// TOOLBAR -------------------------------------------------------------------
	
	public function addOption () {
		// 0: text
		// 1: link
		// 2: image
		$args = func_get_args();
		$this->options[] = array ($args[0],$args[1],$args[2]);
	}
	
	public function showOptions () {
		reset ($this->options);
		$options = array();
		while (list($index, $option) = each($this->options)) {
			$options[] =
				'<li'.(isset($option[2])?' class="icon '.$option[2].'"':'').'>'
				.'<a href="'.$option[1].'">'.$option[0].'</a></li>'.PHP_EOL;
		}
		echo '<ul>'.PHP_EOL.implode (Z_SEPARATOR_SYMBOL,$options).'</ul>'.PHP_EOL;
	}
	
	public function addLoginOption () {
		if (!isServerScriptName('z_login.php') && !isServerScriptName('z_logout.php')) {
			if (isset($_SESSION[Z_SESSION_NAME]['user']) && isset($_SESSION[Z_SESSION_NAME]['user']['name']) && isset($_SESSION[Z_SESSION_NAME]['user']['password'])) {
				$this->addOption (Z_LOGOUT,'z_logout','logout');
			}
			else {
				$this->addOption (Z_LOGIN,'z_login','login');
			}
		}
	}
	
	// VIEWS ---------------------------------------------------------------------
	
	public function getListView ( ) {
		// getListView ( name [, parameter] )
		$args = func_get_args();
		require_once 'dbi_view.php';
		require_once 'dbi_view_list.php';
		require_once 'dbi_view_list_'.$args[0].'.php';
		$this->View = call_user_func('view_list_'.$args[0].'::create',$this);
		switch (count($args)) {
			case 1: return $this->View->get_HTML (); break;
			case 2: return $this->View->get_HTML ($args[1]); break;
			default: return false;
		}
	}
	
	public function getRecordView ( ) {
		// getListView ( name [, parameter] )
		$args = func_get_args();
		require_once 'dbi_view.php';
		require_once 'dbi_view_record.php';
		require_once 'dbi_view_record_'.$args[0].'.php';
		$this->View = call_user_func('view_record_'.$args[0].'::create',$this);
		switch (count($args)) {
			case 1: return $this->View->get_HTML (); break;
			case 2: return $this->View->get_HTML ($args[1]); break;
			default: return false;
		}
	}
	
	// TEXTBLOCKS --------------------------------------------------------------

	public function getTextblock_HTML ($name) {
		$textblock_querystring = "
			SELECT t.textblock_id, t.name, t.permission,
				t.title_".USER_LANGUAGE." AS title,
				t.content_".USER_LANGUAGE." AS content
			FROM z_textblocks t
			WHERE t.name='{$name}'
		";
		$textblock_query = mysql_query($textblock_querystring);
		$html = NULL;
		if ($textblock = mysql_fetch_object($textblock_query)) {
			if ($textblock->title) $html .= '<h4>'.$textblock->title.'</h4>'.PHP_EOL;
			$html .= SimpleMarkup_HTML($textblock->content);
			if ($this->checkUserPermission($textblock->permission)) {
				$html .= '<p>'.createSmallButton(Z_EDIT,'z_edit_textblock?textblock_id='.$textblock->textblock_id,'icon edit').'</p>'.PHP_EOL;
			}
		}
		else {
			if ($this->checkUserPermission('system')) {
				$html .= '<p>'.createSmallButton(Z_NEW_TEXTBLOCK,'z_edit_textblock?name='.$name,'icon addTextblock').'</p>'.PHP_EOL;
			}
		}
		return $html;
	}
	
}

?>