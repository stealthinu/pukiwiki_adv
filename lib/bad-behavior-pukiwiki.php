<?php
/*
Bad Behavior - detects and blocks unwanted Web accesses
Copyright (C) 2005,2006,2007,2008,2009,2010,2011 Michael Hampton

Bad Behavior is free software; you can redistribute it and/or modify it under
the terms of the GNU Lesser General Public License as published by the Free
Software Foundation; either version 3 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along
with this program. If not, see <http://www.gnu.org/licenses/>.

Please report any problems to bad . bots AT ioerror DOT us
http://www.bad-behavior.ioerror.us/
*/

// $Id: bad-behavior-pukiwiki.php,v 0.1 2011/02/11 23:15:00 Logue Exp $

###############################################################################
###############################################################################

defined('CONFIG_BADBEHAVIOR_SETTING')	or define('CONFIG_BADBEHAVIOR_SETTING',		'BadBehavior');
defined('CONFIG_BADBEHAVIOR_LOG')		or define('CONFIG_BADBEHAVIOR_LOG',			'BadBehavior/Log');


define('WEEK_SECONDS', 604800);	// 1週間の秒数

define('BB2_CWD', LIB_DIR);

// Settings you can adjust for Bad Behavior.
// Most of these are unused in non-database mode.
// DO NOT EDIT HERE; instead make changes in settings.ini.
// These settings are used when settings.ini is not present.
$bb2_settings_defaults = array(
	'log_table' => 'bad_behavior',
	'display_stats' => false,
	'strict' => false,
	'verbose' => false,
	'logging' => true,
	'httpbl_key' => '',
	'httpbl_threat' => '25',
	'httpbl_maxage' => '30',
	'offsite_forms' => false,
);

// Bad Behavior callback functions.

// Return current time in the format preferred by your database.
function bb2_db_date() {
	return UTIME;	// Example is MySQL format
}

// Return affected rows from most recent query.
function bb2_db_affected_rows() {
	return false;
}

// Escape a string for database usage
function bb2_db_escape($string) {
	// return mysql_real_escape_string($string);
	return $string;	// No-op when database not in use.
}

// Return the number of rows in a particular query.
function bb2_db_num_rows($result) {
	if ($result !== FALSE){
		$log = new Config(CONFIG_BADBEHAVIOR_LOG);
		$log->read();
		return count($log->get('Log'));
	}
	return 0;
}

// Run a query and return the results, if any.
// Should return FALSE if an error occurred.
// Bad Behavior will use the return value here in other callbacks.
function bb2_db_query($query) {
	if (strpos($query, 'DELATE')){
		// 7日以上経過したログを削除
		$log = new Config(CONFIG_BADBEHAVIOR_LOG);
		$log->read();
		$lines = & $log->get('Log');
		$array = array();
		
		foreach ($lines as $line){
			if (($log[2]+WEEK_SECONDS) > UTIME){
				// ログに記載された時刻＋1週間の秒数が現在時刻の秒数よりも多い場合はログ削除
			}else{
				$array[] = $line;
			}
		}
		
		$log->put('Log',$array);	// 単純に追記
		$log->write();	// 保存
	}
	return $query;
}

// Return all rows in a particular query.
// Should contain an array of all rows generated by calling mysql_fetch_assoc()
// or equivalent and appending the result of each call to an array.
function bb2_db_rows($query) {
	return $query;
}

// Create the SQL query for inserting a record in the database.
// See example for MySQL elsewhere.

function bb2_insert($settings, $package, $key)
{
	$obj = new Config(CONFIG_BADBEHAVIOR_LOG);
	$obj->read();
	$array = & $obj->get('Log');	// 今までのデーター
	$array[] = array(
		$key,							// 0
		$package['ip'],					// 1
		UTIME,							// 2
		$package['request_method'],		// 3
		$package['request_uri'],		// 4
		$package['server_protocol'],	// 5
		$package['user_agent']			// 6
	);
	$obj->put('Log',$array);	// 単純に追記
	$obj->write();	// 保存
	return true;
}

// Return emergency contact email address.
function bb2_email() {
	return $notify_from;
}

// retrieve settings from database
// Settings are hard-coded for non-database use
function bb2_read_settings() {
	global $bb2_settings_defaults;
	$config = new Config(CONFIG_BADBEHAVIOR_SETTING);
	$config->read();
	$lines = $config->get('Settings');
	$settings = array();
	foreach ($lines as $line){
		if (preg_match('/^([ -~]+)\[\]$/',$line[0], $matches)){
			$settings[$line[0]] = explode(',',$matches[1]);
		}else{
			$settings[$line[0]] = $line[1];
		}
	}
	return array_merge($bb2_settings_defaults, $settings);
}

// write settings to database
function bb2_write_settings($settings) {
	$config = new Config(CONFIG_BADBEHAVIOR_SETTING);
	$config->read();
	foreach ($config as $line){
		$key = $settings[$line[0]];
		if (is_array($settings[$line[1]])){
			$val = implode(",", $settings[$line[1]]);
		}else{
			$val = $settings[$line[1]];
		}
		$data[] = array($key=>$val);
		unset($key,$val);
	}
	$config->put('Settings',$data);
	$config->write();
	return true;
}

// installation
function bb2_install() {
	$settings = bb2_read_settings();
	if (defined('BB2_NO_CREATE')) return;
	if (!$settings['logging']) return;
	bb2_start($settings);
}

// Display stats? This is optional.
function bb2_insert_stats($force = false) {
	$settings = bb2_read_settings();
	$blocked = 0;

	if ($force || $settings['display_stats']) {
		$config = new Config(CONFIG_BADBEHAVIOR_LOG);
		$config->read();
		$lines = $config->get('Log');
		foreach ($lines as $line){
			if($line[0] !== '00000000'){
				$blocked++;
			}
		}
		if ($blocked !== 0) {
			echo sprintf('<p><a href="http://www.bad-behavior.ioerror.us/">%1$s</a> %2$s <strong>%3$s</strong> %4$s</p>', __('Bad Behavior'), __('has blocked'), $blocked, __('access attempts in the last 7 days.'));
		}
	}
}

// Return the top-level relative path of wherever we are (for cookies)
// You should provide in $url the top-level URL for your site.
function bb2_relative_path() {
	return get_script_uri();
}

// Calls inward to Bad Behavor itself.
require_once(BB2_CWD . "/bad-behavior/core.inc.php");
bb2_install();	// FIXME: see above
