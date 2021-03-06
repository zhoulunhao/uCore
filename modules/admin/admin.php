<?php

class internalmodule_Reconfigure extends uBasicModule implements iAdminModule {
	// title: the title of this page, to appear in header box and navigation
	public function GetTitle() { return 'Configuration'; }
	public function GetOptions() { return ALWAYS_ACTIVE; }

	public function SetupParents() {}
	public static function Initialise() {
		self::AddParent('/');
	}

	public function GetSortOrder() { return 10000-1; }

	public function RunModule() {
		echo '<h1>'.$this->GetTitle().'</h1>';
		echo '<div class="layoutListSection module-content">';
		uConfig::ShowConfig();
		echo '</div>';
	}
}

//uEvents::AddCallback('AfterRunModule','uDashboard::DrawDashboard','uDashboard');
class uDashboard extends uBasicModule implements iAdminModule {
	// title: the title of this page, to appear in header box and navigation
	public function GetTitle() { return 'Dashboard'; }
	public function GetOptions() { return ALWAYS_ACTIVE; }

	public function GetSortOrder() { return -10000; }
	public function GetURL($filters = NULL) {
		$qs = $filters ? '?'.http_build_query($filters) : '';
		return PATH_REL_CORE.'index.php'.$qs;
	}
	public static function Initialise() {
		self::UpdateHtaccess();
		utopia::RegisterAjax('toggle_debug','uDashboard::toggleDebug');
		uEvents::AddCallback('AfterRunModule','uDashboard::SetupMenu',utopia::GetCurrentModule());
		self::AddParent('/');
	}
	public function SetupParents() {}
	public static function SetupMenu() {
		if (uEvents::TriggerEvent('CanAccessModule',__CLASS__) !== FALSE)
		uAdminBar::AddItem('<a class="btn dashboard-link" href="'.PATH_REL_CORE.'"><span/>Dashboard</a>',FALSE,-100);
	}
	public static function toggleDebug() {
		utopia::DebugMode(!utopia::DebugMode());
		die('window.location.reload();');
	}

	public static function DrawDashboard() {
		// get large widget area
		ob_start();
		uEvents::TriggerEvent('ShowDashboard');
		$largeContent = ob_get_clean();
		if ($largeContent) echo '<div class="dash-large">'.$largeContent.'</div>';

		
		// get small widget area
		$smallContent = '';
		$w = utopia::GetModulesOf('uDashboardWidget');
		foreach ($w as $wid) {
			$wid = $wid['module_name'];
			$ref = new ReflectionClass($wid);
			ob_start();
			if ($ref->hasMethod('Draw100')) $wid::Draw100();
			elseif ($ref->hasMethod('Draw50')) $wid::Draw50();
			elseif ($ref->hasMethod('Draw25')) $wid::Draw25();
			$content = ob_get_clean();
			if (!$content) continue;
			$smallContent .= '<div class="widget-container '.$wid.'"><h1>'.$wid::GetTitle().'</h1><div class="module-content">'.$content.'</div></div>';
		}
		if ($smallContent) echo '<div class="dash-small">'.$smallContent.'</div>';
	}
	public function RunModule() {
		self::DrawDashboard();
	}
	public static function UpdateHtaccess() {
		if ($_SERVER['HTTP_HOST'] == 'cli') return; // don't rewrite htaccess for CLI
		$relcore = ltrim(preg_replace('/^\/~[^\/]+/','',PATH_REL_CORE),'/');
		$relroot = preg_replace('/^\/~[^\/]+/','',PATH_REL_ROOT);
		$content = <<<FIN
#don't use file id in ETag
FileETag MTime Size

#deny access to config file
<Files uConfig.php>
	order allow,deny
	deny from all
</Files>

#enable default cache control and compression
<FilesMatch "\.(ico|pdf|flv|jpg|jpeg|png|gif|js|css|swf)$">
	<IfModule mod_deflate.c>
		SetOutputFilter DEFLATE
	</IfModule>
	<IfModule mod_headers.c>
		Header set Cache-Control "max-age=290304000, public"
		Header set Expires "Thu, 15 Jan 2015 20:00:00 GMT"
	</IfModule>
</FilesMatch>

#URL Rewriting
<IfModule mod_rewrite.c>
	# Tell PHP that the mod_rewrite module is ENABLED.
	SetEnv HTTP_MOD_REWRITE On

	RewriteEngine on
	RewriteRule ^(.*/)?(\.svn)|(\.git) - [F,L]
	ErrorDocument 403 "Access Forbidden"
	
	RewriteRule ^uFiles - [F,L]
	ErrorDocument 403 "Access Forbidden"
	
	# Skip if file exists
	RewriteCond %{REQUEST_FILENAME} -f
	RewriteRule .* - [L]
	
	RewriteCond %{REQUEST_URI}	(/~[^/]+)?
	RewriteRule .*	%1{$relroot}index.php [NE,L,QSA]
</IfModule>
FIN;
		self::updateFile(PATH_ABS_ROOT.'.htaccess',$content);
		
		$content = <<<FIN
if (!-e \$request_filename) {
	rewrite .* {$relroot}index.php;
}
FIN;
		self::updateFile(PATH_ABS_ROOT.'.nginx',$content);

		$content = "include('{$relcore}start.php'); utopia::Launcher();";
		self::updateFile(PATH_ABS_ROOT.'index.php',$content,"<?php /* uCore */\n","\n/*-uCore-*/ ?>");
	}
	
	private static function updateFile($file,$content,$ucStart="\n\n## uCore ##\n",$ucEnd="\n##-uCore-##") {
		if ($ucStart == $ucEnd) throw new Exception('Start and end tags cannot be equal');
		$search = $ucStart.$content.$ucEnd;
		$fileContents = '';
		if (file_exists($file)) $fileContents = file_get_contents($file);
		if (strpos($fileContents,$search) === FALSE) {
			// first remove existing (outdated)
			$s = strpos($fileContents,$ucStart);
			$e = strrpos($fileContents,$ucEnd); // PHP5
			//$e = strpos(strrev($fileContents),strrev($ucEnd)); // PHP4
			if ($s !== FALSE && $e !== FALSE) {
				$e += strlen($ucEnd); // PHP5
				//$e = strlen($fileContents) - $e; // PHP4
				$fileContents = substr_replace($fileContents,'',$s,$e);
			}

			$fileContents = trim($fileContents).$search;
			file_put_contents($file,$fileContents);
			return true;
		}
	}
}
