<?php
/**
 * 最終更新クラス
 *
 * @package   PukiWiki
 * @access    public
 * @author    Logue <logue@hotmail.co.jp>
 * @copyright 2012-2013 PukiWiki Advance Developers Team
 * @create    2012/12/31
 * @license   GPL v2 or (at your option) any later version
 * @version   $Id: Recent.php,v 1.0.0 2013/09/02 22:57:00 Logue Exp $
 **/

namespace PukiWiki;

use PukiWiki\Auth\Auth;
use PukiWiki\Listing;
use PukiWiki\File\FileFactory;

/**
 * 最終更新クラス
 */
class Recent{
	// 更新履歴のキャッシュ名
	const RECENT_CACHE_NAME = 'recent';
	// 更新履歴／削除履歴で表示する最小ページ数
	const RECENT_MIN_SHOW_PAGES = 10;
	// 更新履歴／削除履歴で表示する最大ページ数
	const RECENT_MAX_SHOW_PAGES = 60;
	/**
	 * 最終更新のキャッシュを取得
	 * @param boolean $force キャッシュを再生成する
	 * @return array
	 */
	public static function get($force = false){
		global $cache, $whatsnew;
		static $recent_pages;

		if ($force){
			// キャッシュ再生成
			unset($recent_pages);
			$cache['wiki']->removeItem(self::RECENT_CACHE_NAME);
		}else if (!empty($recent_pages)){
			// メモリにキャッシュがある場合
			return $recent_pages;
		}else if ($cache['wiki']->hasItem(self::RECENT_CACHE_NAME)) {
			// キャッシュから最終更新を読み込む
			$recent_pages = $cache['wiki']->getItem(self::RECENT_CACHE_NAME);
			return $recent_pages;
		}

		// Wikiのページ一覧を取得
		$pages = Listing::pages('wiki');

		// ページ一覧からファイルの更新日時を取得
		$recent_pages = array();
		foreach($pages as $page){
			if ($page !== $whatsnew){
				$wiki = Factory::Wiki($page);
				 if (! $wiki->isHidden() ) $recent_pages[$page] = $wiki->time();
			}
		}
		// 更新日時順にソート
		arsort($recent_pages, SORT_NUMERIC);

		// Cut unused lines
		// BugTrack2/179: array_splice() will break integer keys in hashtable
		$count   = self::RECENT_MAX_SHOW_PAGES + self::RECENT_MIN_SHOW_PAGES;
		$_recent = array();
		foreach($recent_pages as $key=>$value) {
			unset($recent_pages[$key]);
			$_recent[$key] = $value;
			if (--$count < 1) break;
		}
		$recent_pages = & $_recent;

		// Save to recent cache data
		$cache['wiki']->setItem(self::RECENT_CACHE_NAME, $recent_pages);

		return $recent_pages;
	}
	/**
	 * 最終更新のキャッシュを更新
	 * @param string $page ページ名
	 * @param boolean $is_deleted 削除フラグ
	 * @return void
	 */
	public static function set($page, $is_deleted = false){
		global $whatsnew,$cache;

		// ページが最終更新だった場合処理しない
		if (empty($page) || $page === $whatsnew) return;

		$wiki = Factory::Wiki($page);

		// 削除フラグが立っている場合、削除履歴を付ける
		if (!$wiki->has() || $is_deleted) self::updateRecentDeleted($page);

		// 更新キャッシュを読み込み（キャッシュ再生成する）
		$recent_pages = self::get(true);
/*
		// キャッシュ内のページ情報を削除
		if (isset($recent_pages[$page])) unset($recent_pages[$page]);

		// トップにページ情報を追記
		if ( $page !== $whatsnew)
			$recent_pages = array($page => $wiki->time()) + $recent_pages;
*/
		// 最終更新ページを更新
		self::updateRecentChanges($recent_pages);
	}
	/**
	 * 最終更新ページを更新（そもそもわざわざWikiページを作成する必要あるのだろうか・・・？）
	 * @global string $whatsnew
	 * @param array $recent_pages
	 * @return void
	 */
	private static function updateRecentChanges($recent_pages){
		global $whatsnew;
		// 最終更新ページを作り直す
		// （削除履歴みたく正規表現で該当箇所を書き換えるよりも、ページを作りなおしてしまったほうが速いだろう・・・）
		$buffer[] = '#norelated';
		foreach ($recent_pages as $_page=>$time){
			// RecentChanges のwikiソース生成部分の問題
			// http://pukiwiki.sourceforge.jp/dev/?BugTrack2%2F343#f62964e7 
			$buffer[] = '- &epoch('.$time.');' . ' - ' . '[[' . str_replace('&#39;', '\'', Utility::htmlsc($_page)) . ']]';
		}
		FileFactory::Wiki($whatsnew)->set($buffer);
	}
	/**
	 * 削除履歴を生成
	 * @global string $whatsdeleted
	 * @param string $deleted 削除したページ
	 * @return type
	 */
	private static function updateRecentDeleted($deleted){
		global $whatsdeleted;
		if (Auth::check_role('readonly') || !Factory::Wiki($deleted)->isHidden()) return;

		$delated = Factory::Wiki($whatsdeleted);

		// 削除履歴を確認する
		foreach ($delated->get() as $line) {
			if (preg_match('/^-(.+) - (\[\[.+\]\])$/', $line, $matches)) {
				$lines[$matches[2]] = $line;
			}
		}

		// 新たに削除されるページ名
		$_page = '[[' .  str_replace('&#39;', '\'', Utility::htmlsc($deleted)) . ']]';

		// 削除されるページ名と同じページが存在した時にそこの行を削除する
		if (isset($lines[$_page])) unset($lines[$_page]);

		// 削除履歴に追記
		array_unshift($lines, '-&epoch(' . UTIME . '); - ' . $_page);
		array_unshift($lines, '#norelated');

		// 履歴の最大記録数を制限
		$lines = array_splice($lines, 0, self::RECENT_MAX_SHOW_PAGES);
		// ファイル一覧キャッシュを再生成
		Listing::get(null, true);
		// 削除履歴を付ける
		$delated->set($lines);
	}
}