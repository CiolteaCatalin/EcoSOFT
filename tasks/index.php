<?php
require __DIR__.'/../includes/new-common.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS eco_tasks (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, is_done TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_at DATETIME NULL, archived_at DATETIME NULL, INDEX idx_archive (archived_at), INDEX idx_done (is_done)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();$action=(string)($_POST['action']??'');$id=(int)($_POST['id']??0);
 if($action==='add'){$title=trim((string)($_POST['title']??''));if($title!==''&&strlen($title)<=765){$s=$pdo->prepare('INSERT INTO eco_tasks(title) VALUES(?)');$s->execute([$title]);flash('success','Task adăugat.');}}
 elseif($action==='toggle'&&$id>0){$s=$pdo->prepare('UPDATE eco_tasks SET completed_at=IF(is_done=1,NULL,NOW()), is_done=IF(is_done=1,0,1) WHERE id=? AND archived_at IS NULL');$s->execute([$id]);}
 elseif($action==='archive'){$pdo->exec('UPDATE eco_tasks SET archived_at=NOW() WHERE is_done=1 AND archived_at IS NULL');flash('success','Taskurile finalizate au fost arhivate.');}
 redirect('/tasks/'.(($_POST['view']??'')==='archived'?'?view=archived':''));
}
$archived=($_GET['view']??'')==='archived';
$stmt=$pdo->query($archived?'SELECT * FROM eco_tasks WHERE archived_at IS NOT NULL ORDER BY archived_at DESC,id DESC':'SELECT * FROM eco_tasks WHERE archived_at IS NULL ORDER BY is_done ASC,created_at DESC,id DESC');$tasks=$stmt->fetchAll(PDO::FETCH_ASSOC);
$done=(int)$pdo->query('SELECT COUNT(*) FROM eco_tasks WHERE archived_at IS NULL AND is_done=1')->fetchColumn();
require __DIR__.'/../includes/header.php';
?>
<div class="eco-heading"><div><h1>Taskuri</h1><p>O listă simplă pentru lucrurile de făcut.</p></div></div>
<div class="eco-task-tabs"><a class="eco-task-tab <?=$archived?'':'is-active'?>" href="/tasks/">Active</a><a class="eco-task-tab <?=$archived?'is-active':''?>" href="/tasks/?view=archived">Arhivate</a></div>
<?php if(!$archived):?>
<form method="post" class="eco-task-add"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="add"><input type="text" name="title" maxlength="255" required placeholder="Ce ai de făcut?" aria-label="Task nou" autofocus><button class="eco-button" type="submit">+ Adaugă task</button></form>
<?php endif;?>
<div class="eco-task-list">
<?php if(!$tasks):?><div class="eco-empty"><?=$archived?'Nu există taskuri arhivate.':'Nu ai taskuri active. Adaugă primul task.'?></div><?php endif;?>
<?php foreach($tasks as $task):?><div class="eco-task-row <?=$task['is_done']?'is-done':''?>">
<?php if(!$archived):?><form method="post" class="eco-task-toggle"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$task['id']?>"><button type="submit" class="eco-task-check" aria-label="<?=$task['is_done']?'Marchează ca nefinalizat':'Marchează ca finalizat'?>" aria-pressed="<?=$task['is_done']?'true':'false'?>"><?=$task['is_done']?'✓':''?></button></form><?php else:?><span class="eco-task-check is-archived">✓</span><?php endif;?>
<div class="eco-task-text"><strong><?=e($task['title'])?></strong><?php if($archived):?><small>Arhivat la <?=e(date('d.m.Y H:i',strtotime($task['archived_at'])))?></small><?php elseif($task['is_done']):?><small>Finalizat<?= $task['completed_at']?' · '.e(date('d.m.Y H:i',strtotime($task['completed_at']))):''?></small><?php endif;?></div></div><?php endforeach;?>
</div>
<?php if(!$archived&&$done>0):?><form method="post" class="eco-task-archive" onsubmit="return confirm('Arhivezi toate taskurile finalizate?')"><input type="hidden" name="csrf" value="<?=csrf_token()?>"><input type="hidden" name="action" value="archive"><button type="submit" class="eco-button eco-button-light">Arhivează finalizate (<?=$done?>)</button></form><?php endif;?>
<?php require __DIR__.'/../includes/footer.php';?>
