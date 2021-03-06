<?php
// PukiWiki - Yet another WikiWikiWeb clone.
// $Id: md5.inc.php,v 1.25.5 2011/02/05 11:05:00 Logue Exp $
// Copyright (C)
//   2010-2011 PukiWiki Advance Developers Team
//   2005-2006,2008 PukiWiki Plus! Team
//   2001-2006 PukiWiki Developers Team
// License: GPL v2 or (at your option) any later version
//
//  MD5 plugin: Allow to convert password/passphrase
//	* PHP sha1() -- If you have sha1() or mhash extension
//	* PHP md5()
//	* PHP crypt()
//	* LDAP SHA / SSHA -- If you have sha1() or mhash extension
//	* LDAP MD5 / SMD5
//	* LDAP CRYPT

use PukiWiki\Auth\Auth;
use PukiWiki\Utility;

// User interface of pkwk_hash_compute() for system admin
function plugin_md5_action()
{
	global $get, $post;

	// if (PKWK_SAFE_MODE || PKWK_READONLY) die_message(T_('Prohibited'));
	if (Auth::check_role('safemode') || Auth::check_role('readonly')) Utility::dieMessage(T_('Prohibited'));

	// Wait POST
	$phrase = isset($post['phrase']) ? $post['phrase'] : '';

	if ($phrase == '') {
		// Show the form

		// If plugin=md5&md5=password, only set it (Don't compute)
		$value  = isset($get['md5']) ? $get['md5'] : '';

		return array(
			'msg' =>T_('Compute userPassword'),
			'body'=> plugin_md5_show_form(isset($post['phrase']), $value));

	} else {
		// Compute (Don't show its $phrase at the same time)

		$prefix = isset($post['prefix']);
		$salt   = isset($post['salt']) ? $post['salt'] : '';

		// With scheme-prefix or not
		if (! preg_match('/^\{.+\}.*$/', $salt)) {
			$scheme = isset($post['scheme']) ? '{' . $post['scheme'] . '}': '';
			$salt   = $scheme . $salt;
		}

		return array(
			'msg' =>'Result',
			'body'=>
				//($prefix ? 'userPassword: ' : '') .
				Auth::hash_compute($phrase, $salt, $prefix, TRUE));
	}
}

// $nophrase = Passphrase is (submitted but) empty
// $value    = Default passphrase value
function plugin_md5_show_form($nophrase = FALSE, $value = '')
{
	// if (PKWK_SAFE_MODE || PKWK_READONLY) die_message(T_('Prohibited'));
	if (Auth::check_role('safemode') || Auth::check_role('readonly')) Utility::dieMessage(T_('Prohibited'));
	if (strlen($value) > Auth::PASSPHRASE_LIMIT_LENGTH)
		Utility::dieMessage(T_('Limit: malicious message length'));

	if ($value != '') $value = 'value="' . htmlsc($value) . '" ';

	$sha1_enabled = function_exists('sha1');
	$sha1_checked = $md5_checked = '';
	if ($sha1_enabled) {
		$sha1_checked = 'checked="checked" ';
	} else {
		$md5_checked  = 'checked="checked" ';
	}

	$form = '<p class="alert alert-danger">' . T_("NOTICE: Don't use this feature via untrustful or unsure network") . '</p>' . "\n" . '<hr />' . "\n";

	if ($nophrase) $form .= '<strong>' . T_("NO PHRASE") . '</strong><br />';
	$script = get_script_uri();
	$form .= <<<EOD
<form action="$script" method="post" class="plugin-md5-form">
	<input type="hidden" name="cmd" value="md5" />
	<div class="form-group">
		<label for="_p_md5_phrase" class="control-label">Phrase:</label>
		<input type="text" name="phrase" id="_p_md5_phrase" class="form-control" size="60" $value/>
	</div>
	<div class="form-group">
EOD;

	if ($sha1_enabled) $form .= <<<EOD
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_sha1" value="x-php-sha1" />
			<label for="_p_md5_sha1">PHP sha1()</label>
		</div>
EOD;

	$form .= <<<EOD
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_md5"  value="x-php-md5" />
			<label for="_p_md5_md5">PHP md5()</label>
		</div>
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_crpt" value="x-php-crypt" />
			<label for="_p_md5_crpt">PHP crypt() *</label>
		</div>
EOD;

	if ($sha1_enabled) $form .= <<<EOD
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_lssha" value="SSHA" $sha1_checked/>
			<label for="_p_md5_lssha">LDAP SSHA (sha-1 with a seed) *</label>
		</div>
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_lsha" value="SHA" />
			<label for="_p_md5_lsha">LDAP SHA (sha-1)</label>
		</div>
EOD;

	$form .= <<<EOD
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_lsmd5" value="SMD5" $md5_checked/>
			<label for="_p_md5_lsmd5">LDAP SMD5 (md5 with a seed) *</label>
		</div>
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_lmd5" value="MD5" />
			<label for="_p_md5_lmd5">LDAP MD5</label>
		</div>
		<div class="radio">
			<input type="radio" name="scheme" id="_p_md5_lcrpt" value="CRYPT" />
			<label for="_p_md5_lcrpt">LDAP CRYPT *</label>
		</div>
		<div class="checkbox">
			<input type="checkbox" name="prefix" id="_p_md5_prefix" checked="checked" />
			<label for="_p_md5_prefix">Add scheme prefix (RFC2307, Using LDAP as NIS)</label>
		</div>
	</div>
	<div class="form-group">
		<label for="_p_md5_salt" class="control-label">Salt, '{scheme}', '{scheme}salt', or userPassword itself to specify:</label>
		<input type="text" name="salt" id="_p_md5_salt" size="60" class="form-control" />
	</div>
	<div class="form-group">
		<input type="submit" class="btn btn-info" value="Compute" />
	</div>
	<p>* = Salt enabled</p>
</form>
EOD;

	return $form;
}
/* End of file md5.inc.php */
/* Location: ./wiki-common/plugin/md5.inc.php */
