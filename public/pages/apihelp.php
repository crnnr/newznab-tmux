<?php

use Illuminate\Support\Facades\Auth;

if (! Auth::check()) {
    $page->show403();
}

$page->title = 'Api';
$page->meta_title = 'Api Help Topics';
$page->meta_keywords = 'view,nzb,api,details,help,json,rss,atom';
$page->meta_description = 'View description of the site Nzb Api.';

$page->content = $page->smarty->fetch('apidesc.tpl');
$page->pagerender();
