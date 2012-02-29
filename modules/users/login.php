<?php

class adminLogout extends uBasicModule implements iAdminModule {
        public function GetTitle() { return 'Logout'; }
	public function GetSortOrder() { return -9900;}
        public function SetupParents() {
                $this->AddParent('/');
        }
        public function RunModule() {
		unset($_SESSION['admin_auth']);
		$obj = utopia::GetInstance('uDashboard');
		header('Location: '.$obj->GetURL());
		die();
	}
}

class internalmodule_AdminLogin extends uDataModule implements iAdminModule{
	// title: the title of this page, to appear in header box and navigation
	public function GetTitle() { return 'Admin Login'; }
	public function GetOptions() { return ALWAYS_ACTIVE | NO_HISTORY | PERSISTENT_PARENT | NO_NAV; }

	public function GetTabledef() { return 'tabledef_Users'; }
	public function SetupFields() {
		$this->CreateTable('users','tabledef_Users');
		$this->AddField('password','password','users');
	}

	public function SetupParents() {
		$this->AddParentCallback('*',array($this,'checkLogin'),0);

		// admin account has not been set up, redirect to config.
		if (!constant('admin_user')) {
			utopia::cancelTemplate();
			echo 'No admin user has been set up.';
			uConfig::ShowConfig();
			die();
		}

		self::TryLogin();
	}

	public static function TryLogin($adminOnly=false) {
		// login not attempted.
		if (!array_key_exists('__admin_login_u',$_REQUEST)) return;

		$un = $_REQUEST['__admin_login_u']; $pw = $_REQUEST['__admin_login_p'];
		unset($_REQUEST['__admin_login_u']); unset($_REQUEST['__admin_login_p']);

		$obj = utopia::GetInstance(__CLASS__);
		if ( strcasecmp($un,constant('admin_user')) == 0 && $pw===constant('admin_pass') ) {
			$_SESSION['admin_auth'] = ADMIN_USER;
		} elseif (!$adminOnly && $obj->LookupRecord(array('username'=>$un,'password'=>md5($pw)))) {
			$_SESSION['admin_auth'] = $un;
		} else {
			ErrorLog('Username and password do not match.');
		}

		if (self::IsLoggedIn() && ((utopia::GetCurrentModule() == __CLASS__) || (array_key_exists('adminredirect',$_REQUEST) && $_REQUEST['adminredirect'] == 1))) {
			$obj = utopia::GetInstance('uDashboard');
			header('Location: '.$obj->GetURL()); die();
		}
	}
  
	public static function IsLoggedIn($authType = NULL) {
		self::TryLogin();
		if (!isset($_SESSION['admin_auth'])) return false;
		if ($authType === NULL) return true;

		return ($_SESSION['admin_auth'] === $authType);
	}
/*
	private $map = array();
	public static function RequireLogin($module,$authType = true, $orHigher=true) {
		self::$map[$module] = array($authType,$orHigher);
	}

	public static function IsAuthed($module) {
		if (!array_key_exists($module,self::$map)) return true;
		return self::IsLoggedIn(self::$map[$module][0],self::$map[$module][1]);
		//return array_key_exists('admin_auth',$_SESSION) && ($_SESSION['admin_auth'] >= $authType);
	}*/

	public function ParentLoadPoint() { return 0; }
	public function checkLogin($parent) {
		self::TryLogin();

		// if auth not required, return
		$obj = utopia::GetInstance($parent);
		if (!($obj instanceof iAdminModule)) return true;
		if ($parent === get_class($this)) return true;

		// if authed, dont show the login
		if (!self::IsLoggedIn()) {
			if (!AjaxEcho('window.location.reload();') && $parent == utopia::GetCurrentModule()) {
				$this->_RunModule();
			}
			return FALSE;
		}
	}

	static function RequireLogin($accounts=NULL) { }

	public function RunModule() {
		//__admin_login_u
		//__admin_login_p
		// perform login
		echo 'Please log in';
		echo '<form id="loginForm" action="" onsubmit="this.action = window.location;" method="post"><table>';
		echo '<tr><td align="right">Username:</td><td>'.utopia::DrawInput('__admin_login_u',itTEXT,'',NULL,array('id'=>'lu')).'</td></tr>';
		echo '<tr><td align="right">Password:</td><td>'.utopia::DrawInput('__admin_login_p',itPASSWORD).'</td></tr>';
		echo '<tr><td></td><td align="right">'.utopia::DrawInput('',itSUBMIT,'Log In').'</td></tr>';
		echo '</table></form><script type="text/javascript">$(function (){$(\'#lu\').focus()})</script>';
	}
}