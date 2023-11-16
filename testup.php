<?php
/*
Plugin Name: TESTUP
Description: Clone site into testup directory and show it only with access from specific IP address. Swap site with it on publish.
Author: synchro_vision
Version: 0.0.3-alpha
GitHub Repository: synchrovision/testup
*/
add_action('init',function(){
	load_plugin_textdomain('testup',false,dirname(plugin_basename( __FILE__ )).'/languages');
});
add_action('admin_init',['TESTUP','hook_admin_init']);

class TESTUP{
	public static function init(){
		chdir(self::is_testup()?dirname(ABSPATH):ABSPATH);
		if(is_dir($d='/Applications/MAMP/Library/bin') && strpos(getenv('PATH'),$d)===false){putenv('PATH='.getenv('PATH').':'.$d);}
	}
	public static function extract_consts($file){
		$rtn=[];
		preg_match_all("/define\s*\(\s*'(?P<key>.+?)'\s*,\s*'(?P<value>.+?)'\s*\);/",file_get_contents($file),$matches);
		foreach($matches['key'] as $i=>$key){
			$rtn[$key]=$matches['value'][$i];
		}
		return $rtn;
	}
	public static function replace_consts($file,$consts){
		$contents=preg_replace_callback(
			"/(?P<before_value>define\s*\(\s*'(?P<key>.+?)'\s*,\s*')(?P<value>.+?)(?P<after_value>'\s*\);)/",
			function($matches)use($consts){
				if(!isset($consts[$matches['key']])){return $matches[0];}
				return $matches['before_value'].$consts[$matches['key']].$matches['after_value'];
			},
			file_get_contents($file)
		);
		file_put_contents($file,$contents);
	}
	public static function get_main_dir(){
		return self::is_testup()?dirname(ABSPATH):rtrim(ABSPATH,'/');
	}
	public static function get_testup_dir(){
		return self::is_testup()?rtrim(ABSPATH,'/'):rtrim(ABSPATH,'/').'/testup';
	}
	public static function get_main_consts(){
		return self::extract_consts(self::get_main_dir().'/wp-config.php');
	}
	public static function get_testup_consts(){
		return self::extract_consts(self::get_testup_dir().'/wp-config.php');
	}
	public static function get_db_connection_clause($host,$user,$pass){
		return sprintf('-h %s -u %s -p%s',str_replace(':',' -P ',esc_sql($host)),esc_sql($user),esc_sql($pass));
	}
	public static function get_main_db_connection_clause(){
		if(!self::is_testup()){
			return self::get_db_connection_clause(DB_HOST,DB_USER,DB_PASSWORD);
		}
		$conf=self::extract_consts(dirname(ABSPATH).'/wp-config.php');
		return self::get_db_connection_clause($conf['DB_HOST'],$conf['DB_USER'],$conf['DB_PASSWORD']);
	}
	public static function get_testup_db_connection_clause(){
		if(self::is_testup()){
			return self::get_db_connection_clause(DB_HOST,DB_USER,DB_PASSWORD);
		}
		$conf=self::extract_consts(ABSPATH.'testup'.'/wp-config.php');
		return self::get_db_connection_clause($conf['DB_HOST'],$conf['DB_USER'],$conf['DB_PASSWORD']);
	}

	public static function create($param=null){
		if(self::is_testup() || is_dir(ABSPATH.'testup')){return new WP_Error('error',__('Testup already exists','testup'));}
		self::init();
		$sql_file='wp-content/testup.sql';
		$main_connect=self::get_main_db_connection_clause();
		if(empty($param)){
			$testup_dbname=DB_NAME.'_testup';
			$testup_connect=self::get_main_db_connection_clause();
			passthru(sprintf("mysql %s -e 'CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8;'",$testup_connect,$testup_dbname),$result);
			if($result!==0){return new WP_Error('error',__('Failed to create database','testup'));}
		}
		else{
			$testup_dbname=$param['DB_NAME'];
			$testup_connect=self::get_db_connection_clause($param['DB_HOST'],$param['DB_USER'],$param['DB_PASSWORD']);
			passthru(sprintf("mysql %s %s",$testup_connect,$testup_dbname),$result);
			if($result!==0){return new WP_Error('error',__('Failed to connect database','testup'));}
		}
		passthru(sprintf('mysqldump %s %s > %s',$main_connect,DB_NAME,$sql_file));
		passthru(sprintf("mysql %s %s < %s",$testup_connect,$testup_dbname,$sql_file));
		passthru(sprintf('cp -r %s %1$s_testup && mv -f %1$s_testup %1$s/testup',rtrim(ABSPATH,'/')));
		if(file_exists($f=ABSPATH.'testup/wp-config.php')){
			self::replace_consts($f,$param?:['DB_NAME'=>$testup_dbname]);
		}
		self::register();
		return true;
	}
	public static function remove(){
		self::init();
		$testup_dir=self::get_testup_dir();
		if(!is_dir($testup_dir)){return false;}
		$testup_consts=self::get_testup_consts();
		$testup_connect=self::get_testup_db_connection_clause();
		passthru(sprintf("mysql %s -e 'DROP DATABASE `%s`;'",$testup_connect,$testup_consts['DB_NAME']));
		passthru(sprintf("rm -r -f %s",$testup_dir));
		self::update_registration(null);
		return true;
	}
	public static function publish(){
		self::init();
		$main_dir=self::get_main_dir();
		$testup_dir=self::get_testup_dir();
		if(!is_dir($testup_dir)){return false;}
		$backup_dir=$main_dir.'_bu'.wp_date('YmdHi');
		$main_consts=self::get_main_consts();
		$main_connect=self::get_main_db_connection_clause();
		$testup_consts=self::get_testup_consts();
		$testup_connect=self::get_testup_db_connection_clause();
		$backup_sql_file='wp-content/backup.sql';
		$testup_sql_file='wp-content/testup.sql';
		passthru(sprintf('mysqldump %s %s > %s',$main_connect,$main_consts['DB_NAME'],$backup_sql_file));
		passthru(sprintf('mysqldump %s %s > %s',$testup_connect,$testup_consts['DB_NAME'],$testup_sql_file));
		passthru(sprintf("mysql %s %s < %s",$main_connect,$main_consts['DB_NAME'],$testup_sql_file));
		if(file_exists($f=$testup_dir.'/wp-config.php')){
			self::replace_consts($f,$main_consts);
		}
		self::update_registration(null);
		passthru(sprintf('mv -f %s %s && mv -f %2$s/testup %1$s',$main_dir,$backup_dir));
		return true;
	}
	public static function rebase($hard=false){
		self::init();
		$main_consts=self::get_main_consts();
		$main_connect=self::get_main_db_connection_clause();
		$testup_consts=self::get_testup_consts();
		$testup_connect=self::get_testup_db_connection_clause();
		$tmp_sql_file='wp-content/testup_tmp.sql';
		passthru(sprintf('mysqldump %s%s %s > %s',$main_connect,$hard?'':' --insert-ignore -t',$main_consts['DB_NAME'],$tmp_sql_file));
		passthru(sprintf("mysql %s %s < %s",$testup_connect,$testup_consts['DB_NAME'],$tmp_sql_file),$result);
		return $result==0;
	}
	public static function register($ip=null){
		self::update_registration(array_merge(self::get_registered(),[$ip??$_SERVER['REMOTE_ADDR']]));
	}
	public static function deregister($ip=null){
		self::update_registration(array_diff(self::get_registered(),[$ip??$_SERVER['REMOTE_ADDR']]));
	}
	public static function get_registered(){
		$f=self::get_main_dir().'/.htaccess';
		if(!file_exists($f)){return [];}
		preg_match_all('/SetEnvIf Remote_Addr "\^(?P<ip>.+?)" TESTUP=yes/',file_get_contents($f),$matches);
		return array_map(function($ip){return str_replace('\\.','.',$ip);},$matches['ip']);
	}
	public static function update_registration($ips){
		$f=self::get_main_dir().'/.htaccess';
		$content=file_get_contents($f);
		$content=preg_replace('/# BEGIN testup(.+)# END testup\n/s','',$content);
		$code='';
		if(!empty($ips)){
			$code="# BEGIN testup\nRewriteEngine on\n";
			$code.="<Files ~ \"\.sql$\">\nOrder allow,deny\nDeny from all\n</Files>\n";
			foreach(array_unique($ips) as $ip){
				$code.="SetEnvIf Remote_Addr \"^".str_replace('.','\\.',$ip)."\" TESTUP=yes\n";
			}
			$code.="RewriteCond %{ENV:TESTUP} yes\nRewriteCond %{REQUEST_URI} !/testup\nRewriteRule ^(.*)$ testup/$1 [L]\n# END testup\n";
		}
		file_put_contents($f,$code.$content);
	}
	public static function is_testup(){
		return getenv('TESTUP')==='yes';
	}
	public static function include_template($template){
		include __DIR__.'/templates/'.$template.'.php';
	}
	public static function hook_admin_init(){
		if(!empty($_REQUEST['testup_action'])){
			if(wp_verify_nonce($_REQUEST['_testup_nonce'],'testup_action')){
				switch($_REQUEST['testup_action']){
					case 'create':$result=self::create(empty($_REQUEST['input_db_conf'])?null:$_REQUEST['DB_CONF']);break;
					case 'publish':$result=self::publish();break;
					case 'remove':$result=self::remove();break;
					case 'rebase':$result=self::rebase(!empty($_REQUEST['testup_rebase_hard']));break;
					case 'register':$result=self::register();break;
					case 'deregister':$result=self::deregister();break;
				}
			}
			else{
				$result=new WP_Error('invalid',__('invalid request','testup'));
			}
			if(is_wp_error($result)){
				add_action('admin_notices',function()use($result){
					printf('<div class="error"><p>%s</p></div>',$result->get_error_message());
				});
			}
			else{
				header('Location: '.admin_url());exit;
			}
		}
		add_action('wp_dashboard_setup',function(){
			if(!current_user_can('edit_themes')){return;}
			wp_add_dashboard_widget('testup','TESTUP',['TESTUP','dashboard_widget_content']);
		});
		add_action('admin_notices',function(){
			if(!TESTUP::is_testup()){return;}
			printf('<div class="notice"><p>%s</p></div>',esc_html(__('You are now seeing testup site','testup'))); 
		});
	}
	public static function dashboard_widget_content(){
		if(self::is_testup()){
			self::include_template('dashboard_testup');
		}
		else{
			if(is_dir(ABSPATH.'testup')){self::include_template('dashboard_main');}
			else{self::include_template('dashboard_main_setup');}
		}
	}
}