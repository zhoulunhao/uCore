<?php

class tabledef_NewsTags extends uTableDef implements iLinkTable {
	public function SetupFields() {
		$this->AddField('link_id',ftNUMBER);
		$this->AddField('news_id',ftNUMBER);
		$this->AddField('tag',ftVARCHAR,150);

		$this->SetPrimaryKey('link_id');
		$this->SetIndexField('news_id');
	}
}

class tabledef_NewsTable extends uTableDef {
	public $tablename = 'news';
	public function SetupFields() {
		$this->AddField('news_id',ftNUMBER);
		$this->AddField('time',ftDATE);
		$this->AddField('heading',ftVARCHAR,150);
		$this->AddField('description',ftTEXT);
		$this->AddField('text',ftLONGTEXT);
		$this->AddField('image',ftIMAGE);
		$this->AddField('archive',ftBOOL);

		$this->AddField('author',ftNUMBER);
		
		$this->AddField('featured',ftBOOL);

		$this->SetPrimaryKey('news_id');
		$this->SetIndexField('author');
	}
}

class module_NewsAdmin extends uListDataModule implements iAdminModule {
	public function GetSortOrder() { return -8800; }
	public function SetupParents() {
		$this->AddParent('/');
	}
	public function GetTitle() { return 'Articles'; }
	public function GetOptions() { return ALLOW_DELETE | ALLOW_FILTER | ALLOW_EDIT; }
	public function GetTabledef() { return 'tabledef_NewsTable'; }
	public function SetupFields() {
		$this->CreateTable('news');
		$this->CreateTable('author','tabledef_Users','news',array('author'=>'user_id'));
		$this->AddField('time','time','news','Posted');
		$this->AddField('author','username','author','Author');

		$this->AddField('heading','heading','news','Title');
		$this->AddField('featured','featured','news','Featured',itCHECKBOX);
		
		$this->AddFilter('time',ctGTEQ,itDATE);
		$this->AddFilter('time',ctLTEQ,itDATE);

		$this->AddOrderBy('time','DESC');
	}
	public function RunModule() {
		$this->ShowData();
	}
}

class module_NewsAdminDetail extends uSingleDataModule implements iAdminModule {
	public function SetupParents() {
		$this->AddParent('module_NewsAdmin','news_id','*');
	}
	public function GetTitle() { return 'Edit Article'; }
	public function GetOptions() { return ALLOW_DELETE | ALLOW_FILTER | ALLOW_ADD | NO_NAV | ALLOW_EDIT; }
	public function GetTabledef() { return 'tabledef_NewsTable'; }
	public function SetupFields() {
		$this->CreateTable('news');
		$this->CreateTable('tags','tabledef_NewsTags','news','news_id');
		$this->CreateTable('author','tabledef_Users','news',array('author'=>'user_id'));

		$this->AddField('time','time','news','Post Date',itDATE);
		$this->AddField('author','author','news','Author',itSUGGEST,'SELECT user_id,username FROM '.TABLE_PREFIX.'tabledef_Users ORDER BY username');
		$this->SetDefaultValue('author',uUserLogin::IsLoggedIn());

		$this->AddField('heading','heading','news','Title',itTEXT);
		$this->AddField('description','description','news','Description',itTEXT);
		$this->FieldStyles_Set('description',array('width'=>'60%'));
		$this->AddField('tags','tag','tags','tags',itTEXT);
		$this->AddPreProcessCallback('tags',array($this,'ppTag'));
		$this->FieldStyles_Set('tags',array('width'=>'60%'));
		
		$this->AddField('featured','featured','news','Featured',itCHECKBOX);
		
		$this->AddField('text','text','news','Content',itHTML);
		$this->FieldStyles_Set('text',array('width'=>'100%','height'=>'10em'));
		$this->AddField('curr_image','image','news','Current Image');
		$this->FieldStyles_Set('curr_image',array('height'=>100));
		$this->AddField('image','image','news','Image',itFILE);
		$this->AddField('archive','archive','news','Archive',itCHECKBOX);
	}
	public function ppTag($v) {
		if (!is_array($v)) return $v;
		sort($v);
		return implode(',',$v);
	}
	public function UpdateField($fieldAlias,$newValue,&$pkVal=NULL) {
		if ($fieldAlias == 'tags') {
			$newValue = explode(',',$newValue);
			foreach ($newValue as $k=>$v) $newValue[$k] = trim($v);
		}
		parent::UpdateField($fieldAlias,$newValue,$pkVal);
	}
	public function RunModule() {
		$this->ShowData();
	}
}

class module_NewsRSS extends uDataModule {
	public function SetupParents() { 
		$this->SetRewrite(true);
	}

	public static $uuid = 'news-rss';
	public function GetTitle() { return 'Articles RSS'; }
	public function GetOptions() { return ALLOW_FILTER; }
	public function GetTabledef() { return 'tabledef_NewsTable'; }
	public function SetupFields() {
		$this->CreateTable('news');
		$this->CreateTable('tags','tabledef_NewsTags','news','news_id');
		$this->AddField('time','time','news','Date',itDATE);
		$this->AddField('heading','heading','news','Heading',itTEXT);
		$this->AddField('description','description','news','Description',itTEXT);
		$this->AddField('tags','tag','tags','tags',itTEXT);
		$this->AddPreProcessCallback('tags',array($this,'ppTag'));
		$this->AddField('text','text','news','Content',itHTML);
		$this->AddField('image','image','news','Image',itFILE);
		$this->AddField('archive','archive','news','Archive',itCHECKBOX);
		$this->AddField('featured','featured','news','Featured');
		
		$this->AddFilter('news_id',ctEQ,itNONE,isset($_GET['news_id'])?$_GET['news_id']:null);
		$this->AddFilter('{time} <= NOW()',ctCUSTOM);
		$this->AddFilter('tags',ctEQ,itNONE,isset($_GET['tags'])?$_GET['tags']:null);
		$this->AddOrderBy('time','desc');
	}
	public function ppTag($v) {
		if (!is_array($v)) return $v;
		sort($v);
		return implode(',',$v);
	}
	public function RunModule() {
		utopia::CancelTemplate();
		$dom = utopia::GetDomainName();
		$siteName = modOpts::GetOption('site_name');

		$items = '';
		$obj = utopia::GetInstance('module_NewsDisplay');
		$pubDate = null;
		$dataset = $this->GetDataset();
		while (($row = $dataset->fetch())) {
			$crop = (strlen($row['text']) > 100) ? substr($row['text'],0,100).'...' : '';
			$link = htmlentities('http://'.$dom.$obj->GetURL(array('news_id'=>$row['news_id'])));
			$img = '';
			if ($row['image']) $img = "\n".'  <media:thumbnail width="150" height="150" url="'.htmlentities('http://'.$dom.uBlob::GetLink(get_class($this),'image',$row['news_id']).'?w=150&h=150').'"/>';
			$updated = date('r',strtotime($row['time']));
			if (!$pubDate || (strtotime($row['time']) > $pubDate)) $pubDate = strtotime($row['time']);
			$items .= <<<FIN
 <item>
  <title>{$row['heading']}</title>
  <description>{$row['description']}</description>
  <link>{$link}</link>
  <guid>{$link}</guid>
  <pubDate>{$updated}</pubDate>{$img}
 </item>
FIN;
		}
		$pubDate = date('r',$pubDate);

		header('Content-Type: application/rss+xml',true);
		$self = htmlentities('http://'.$dom.$_SERVER['REQUEST_URI']);
		echo <<<FIN
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/" xmlns:atom="http://www.w3.org/2005/Atom"><channel>
 <atom:link href="{$self}" rel="self" type="application/rss+xml" />
 <title>{$siteName} News Feed</title>
 <description>Latest news from {$siteName}</description>
 <link>http://{$dom}</link>
 <lastBuildDate>{$pubDate}</lastBuildDate>
 <language>en-gb</language>
 <ttl>15</ttl>
{$items}
</channel></rss>
FIN;
	}
}

class module_NewsTags extends uDataModule {
	public function GetTabledef() { return 'tabledef_NewsTags'; }
	public function GetOptions() { return DISTINCT_ROWS; }
	public function SetupFields() {
		$this->CreateTable('tags');
		$this->AddField('tag','tag','tags','Tag');
		$this->grouping = array('tag');
	}
	public function SetupParents() {}
	public function RunModule() {}
}

class module_NewsDisplay extends uDataModule {
	public function GetTitle() { return 'Latest News'; }
	public function SetupParents() {
		$this->SetRewrite('{heading}-{news_id}',true);
		modOpts::AddOption('news_per_page','Articles Per Page','Articles',2);
		modOpts::AddOption('news_widget_archive','Archive Widget','Articles','article-archive');
		modOpts::AddOption('news_widget_article','Article Widget','Articles','article');
		
		uSearch::AddSearchRecipient(__CLASS__,array('heading','text'),'heading','text');
	}
	public function GetTabledef() { return 'tabledef_NewsTable'; }
	public function SetupFields() {
		$this->CreateTable('news');
		$this->CreateTable('tags','tabledef_NewsTags','news','news_id');
		
		$this->CreateTable('user','tabledef_Users','news',array('author'=>'user_id'));
		$this->CreateTable('author','tabledef_UserProfile','news',array('author'=>'user_id'));
		$this->AddField('author_name','(IF(TRIM(CONCAT(COALESCE({first_name},\'\'),\' \',COALESCE({last_name},\'\'))) != \'\',TRIM(CONCAT(COALESCE({first_name},\'\'),\' \',COALESCE({last_name},\'\'))),`user`.`username`))','author','Author Name');
		$this->AddField('gplus_url','gplus_url','author','Google+ URL');
		$this->AddPreProcessCallback('gplus_url',array($this,'gplusurl'));
		
		$this->AddField('time','time','news','time');
		$this->AddPreProcessCallback('time',array($this,'timeformat'));
		$this->SetFieldType('time',ftDATE);
		
		$this->AddField('heading','heading','news','heading');
		$this->AddField('text','text','news','text');
		$this->AddField('description','description','news','Excerpt');
		$this->AddPreProcessCallback('description',array($this,'makeExcerpt'));
		
		$this->AddField('image','image','news','image');
		$this->AddField('featured','featured','news','Featured');
		
		$this->AddField('tags','tag','tags','tags',itTEXT);
		$this->AddPreProcessCallback('tags',array($this,'ppTag'));
		
		$this->AddFilter('news_id',ctEQ,itNONE,isset($_GET['news_id'])?$_GET['news_id']:null);
		$this->AddFilter('{time} <= NOW()',ctCUSTOM);
		$this->AddFilter('tags',ctEQ,itNONE,isset($_GET['tags'])?$_GET['tags']:null);
		$this->AddOrderBy('time','desc');
	}
	public function makeExcerpt($v,$pk,$processed,$row) {
		if ($processed) return $processed;
		$text = strip_tags($row['text']);
		if (!(preg_match_all('/^(.*?\.)/',$text,$matches))) return '';
		if (isset($matches[0][0])) return $matches[0][0];
		return '';
	}
	public static $uuid = 'news';
	public function timeformat($originalValue,$pkVal,$processedVal) {
		return '<abbr class="published" title="'.$originalValue.'">'.utopia::convDateTime($originalValue,$pkVal,$processedVal).'</abbr>';
	}
	public function gplusurl($v) {
		if (!$v) return $v;
		$u = parse_url($v);
		$q = array();
		if (isset($u['query'])) $q = parse_str($u['query']);
		$q['rel'] = 'author';
		$u['query'] = http_build_query($q);
		return unparse_url($u);
	}
	public function ppTag($v) {
		if (!is_array($v)) $v = array($v);
		sort($v);
		foreach($v as $k=>$tag) {
			$v[$k] = '<a rel="category tag" title="View all posts in '.ucwords($tag).'" href="'.$this->GetURL(array('tags'=>$tag)).'">'.$tag.'</a>';
		}
		return implode(', ',$v);
	}
	public function RunModule() {
		uEvents::AddCallback('ProcessDomDocument',array($this,'ProcessDomDocument'));
		if (isset($_GET['news_id'])) {
			$rec = $this->LookupRecord($_GET['news_id']);
			if (!$rec) utopia::PageNotFound();
			utopia::SetTitle($rec['heading']);
			utopia::SetDescription($rec['description']);

			echo '{widget.'.modOpts::GetOption('news_widget_article').'}';
			return;
		}
		if (isset($_GET['tags'])) utopia::SetTitle('Latest '.ucwords($_GET['tags']).' News');
		echo '{widget.'.modOpts::GetOption('news_widget_archive').'}';
	}
	public function ProcessDomDocument($o,$e,$doc) {
		$head = $doc->getElementsByTagName('head')->item(0);
		
		// add OG protocol
		if (isset($_GET['news_id'])) {
			$rec = $this->LookupRecord($_GET['news_id']);
			$img = 'http://'.utopia::GetDomainName().uBlob::GetLink(get_class($this),'image',$rec['news_id']);
			$meta = $doc->createElement('meta'); $meta->setAttribute('property','og:title'); $meta->setAttribute('content',$rec['heading']); $head->appendChild($meta);
			$meta = $doc->createElement('meta'); $meta->setAttribute('property','og:type'); $meta->setAttribute('content','article'); $head->appendChild($meta);
			$meta = $doc->createElement('meta'); $meta->setAttribute('property','og:url'); $meta->setAttribute('content','http://'.utopia::GetDomainName().$_SERVER['REQUEST_URI']); $head->appendChild($meta);
			if ($rec['image']) { // image exists?
				$meta = $doc->createElement('meta'); $meta->setAttribute('property','og:image'); $meta->setAttribute('content',$img); $head->appendChild($meta);
			}
			$meta = $doc->createElement('meta'); $meta->setAttribute('property','og:site_name'); $meta->setAttribute('content',modOpts::GetOption('site_name')); $head->appendChild($meta);
			$meta = $doc->createElement('meta'); $meta->setAttribute('property','og:description'); $meta->setAttribute('content',$rec['description']); $head->appendChild($meta);
		}

		// add RSS link
		$rssobj = utopia::GetInstance('module_NewsRSS');
		$link = $doc->createElement('link');
		$link->setAttribute('rel','alternate');
		$link->setAttribute('type','application/atom+xml');
		$link->setAttribute('title',modOpts::GetOption('site_name').' News Feed');
		$link->setAttribute('href',$rssobj->GetURL());
		$head->appendChild($link);
	}
	
	public static function AddUserFields($o,$e) {
		$o->AddField('gplus_url',ftVARCHAR,255);
	}
	public static function AddUserFieldsDetail($o,$e) {
		$o->AddSpacer();
		$o->AddField('gplus_url','gplus_url','detail','Google+ URL',itTEXT);
	}
}

uEvents::AddCallback('AfterSetupFields','module_NewsDisplay::AddUserFields','tabledef_UserProfile');
uEvents::AddCallback('AfterSetupFields','module_NewsDisplay::AddUserFieldsDetail','UserProfileDetail');
uEvents::AddCallback('AfterSetupFields','module_NewsDisplay::AddUserFieldsDetail','UserDetailAdmin');
