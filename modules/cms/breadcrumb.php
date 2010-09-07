<?php

FlexDB::AddTemplateParser('breadcrumb','uBreadcrumb::GetTrail','');
class uBreadcrumb {
	private static $extras = array();
	static function AddCrumb($name,$url) {
		self::$extras[] = array($name,$url);
	}
	static function GetTrail() {
		$arr = CallModuleFunc('uCMS_List','GetNestedArray');
		$out = array();

		//TODO: add (Search Results) to end of title if current page is filtered.
		//$fltr(CallModuleFunc(GetCurrentModule(),'IsFiltered'));
		$out[] = '<a href="'.$_SERVER['REQUEST_URI'].'">'.FlexDB::GetTitle(true).'</a>';

		foreach (self::$extras as $a) {
			$out[] = '<a href="'.$a[1].'">'.$a[0].'</a>';
		}

		$row = uCMS_View::findPage();
		do {
			if (!$row) break;
			$url = CallModuleFunc('uCMS_View','GetURL',$row['cms_id']);
			$out[] = '<a href="'.$url.'">'.$row['title'].'</a>';
		} while ($row['parent'] && ($row = uCMS_List::findKey($arr,$row['parent'])));

		$home = uCMS_View::GetHomepage();
		$url = CallModuleFunc('uCMS_View','GetURL',$home['cms_id']);
		$out[] = '<a href="'.$url.'">'.$home['title'].'</a>';

		return '<div class="breadcrumb">'.implode(' &gt; ',array_reverse(array_unique($out))).'</div>';
	}
}

?>