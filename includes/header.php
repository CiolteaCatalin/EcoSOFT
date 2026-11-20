<?php
$config=require __DIR__.'/../config/config.php';
$user=current_user();
$currentPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(!function_exists('eco_nav_active')){function eco_nav_active(string $path,string $current):string{return ($path==='/'?$current==='/':str_starts_with($current,$path))?' active':'';}}
$ecoTitle='Dashboard';foreach(['/lists/'=>'Liste produse','/editable-offers/'=>'Oferte','/tasks/'=>'Taskuri'] as $path=>$title){if(str_starts_with($currentPath,$path)){$ecoTitle=$title;break;}}
if(!function_exists('eco_icon')){function eco_icon($name){return '';}}
?>
<!doctype html><html lang="ro"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#f5f8ff"><link rel="icon" href="/assets/favicon.png" type="image/png"><title><?=e($ecoTitle)?> · <?=e($config['app_name'])?></title>
<link rel="stylesheet" href="/assets/css/app.css?v=<?=filemtime(__DIR__.'/../assets/css/app.css')?>">

<link rel="stylesheet" href="/assets/css/eco-v9.css?v=<?=filemtime(__DIR__.'/../assets/css/eco-v9.css')?>">
</head>
<body><div class="app-shell eco-v8-shell"><main class="main">
<header class="eco-header"><div class="eco-header-inner">
<a class="eco-brand" href="/" aria-label="ECOsoft - Acasă"><img src="/assets/logoeco.png" alt="ECO Autofin"></a>
<button class="eco-mobile-toggle" id="ecoNavToggle" type="button" aria-expanded="false" aria-controls="ecoMainNav">Meniu ☰</button>
<nav class="eco-main-nav" id="ecoMainNav" aria-label="Navigare principală">
<a class="eco-nav-item<?=eco_nav_active('/',$currentPath)?>" href="/">Dashboard</a>
<a class="eco-nav-item<?=eco_nav_active('/lists/',$currentPath)?>" href="/lists/">Liste produse</a>
<a class="eco-nav-item<?=eco_nav_active('/editable-offers/',$currentPath)?>" href="/editable-offers/">Oferte</a>
<a class="eco-nav-item<?=eco_nav_active('/tasks/',$currentPath)?>" href="/tasks/">Taskuri</a>
</nav><div class="eco-header-right"><a href="/editable-offers/edit.php" class="eco-button eco-header-create">+ Ofertă</a><a class="eco-logout" href="/logout.php" title="Deconectare">Ieșire</a></div>
</div></header>
<div class="content"><?php foreach(flashes() as $f):?><div class="alert <?=e($f['type'])?>"><?=e($f['message'])?></div><?php endforeach;?>
