<?php
require_once("config.php");
require_once(WWW_DIR."/lib/adminpage.php");
require_once(WWW_DIR."/lib/CollectionsCleaning.php");

$page = new AdminPage();
$cc = new CollectionsCleaning(['Settings' => $page->settings]);

$page->title = "Collections Regex List";

$group = '';
if (isset($_REQUEST['group']) && !empty($_REQUEST['group'])) {
	$group = $_REQUEST['group'];
}

$offset = isset($_REQUEST["offset"]) ? $_REQUEST["offset"] : 0;
$regex = $cc->getRegex($group, ITEMS_PER_PAGE, $offset);
$page->smarty->assign('regex', $regex);

$count = $cc->getCount($group);
$page->smarty->assign('pagertotalitems', $count);
$page->smarty->assign('pageroffset', $offset);
$page->smarty->assign('pageritemsperpage', ITEMS_PER_PAGE);

$page->smarty->assign('pagerquerybase', WWW_TOP . "/collection_regexes-list.php?" . $group . "offset=");
$page->smarty->assign('pager', $page->smarty->fetch("pager.tpl"));

$page->content = $page->smarty->fetch('collection_regexes-list.tpl');
$page->render();