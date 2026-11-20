<?php
require __DIR__.'/../includes/user-admin.php';require_login();
if(!eco_user_admin($pdo)){http_response_code(403);exit('Doar administratorul poate gestiona utilizatorii.');}
$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();$action=(string)($_POST['action']??'create');$id=(int)($_POST['id']??0);
 try{
 if($action==='create'){
  $name=trim((string)($_POST['name']??''));$email=mb_strtolower(trim((string)($_POST['email']??'')));$pass=(string)($_POST['password']??'');$role=($_POST['role']??'staff')==='admin'?'admin':'staff';
  if(mb_strlen($name)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<12)throw new RuntimeException('Completează numele, un email valid și o parolă de minimum 12 caractere.');
  $pdo->beginTransaction();$s=$pdo->prepare('INSERT INTO users (name,email,password_hash,active) VALUES (?,?,?,1)');$s->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);$newId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO eco_user_roles (user_id,role) VALUES (?,?)')->execute([$newId,$role]);$pdo->commit();$success='Utilizatorul a fost creat.';
 }elseif($action==='password'){
  $pass=(string)($_POST['password']??'');if(strlen($pass)<12)throw new RuntimeException('Parola trebuie să aibă minimum 12 caractere.');$pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_DEFAULT),$id]);$success='Parola a fost actualizată.';
 }elseif($action==='toggle'){
  if($id===(int)$_SESSION['user_id'])throw new RuntimeException('Nu îți poți dezactiva propriul cont.');
  $pdo->prepare('UPDATE users SET active=IF(active=1,0,1) WHERE id=?')->execute([$id]);$success='Starea contului a fost actualizată.';
 }elseif($action==='role'){
  if($id===(int)$_SESSION['user_id'])throw new RuntimeException('Nu îți poți schimba propriul rol.');
  $role=($_POST['role']??'staff')==='admin'?'admin':'staff';$pdo->prepare('INSERT INTO eco_user_roles (user_id,role) VALUES (?,?) ON DUPLICATE KEY UPDATE role=VALUES(role)')->execute([$id,$role]);$success='Rolul a fost actualizat.';
 }
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e instanceof PDOException?'Operațiunea nu a putut fi finalizată. Verifică dacă emailul este deja folosit.':$e->getMessage();}
}
$users=$pdo->query('SELECT u.id,u.name,u.email,u.active,COALESCE(r.role,\'staff\') AS role FROM users u LEFT JOIN eco_user_roles r ON r.user_id=u.id ORDER BY u.id')->fetchAll();
require __DIR__.'/../includes/header.php';?>
<link rel="stylesheet" href="/assets/css/eco-settings.css?v=1">
<div class="eco-admin"><div class="eco-admin-head"><div><h1>Utilizatori</h1><p>Gestionează accesul colegilor la EcoAutoFin.</p></div></div>
<?php if($error):?><div class="eco-msg eco-error"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="eco-msg eco-ok"><?=e($success)?></div><?php endif;?>
<section class="eco-panel"><h2>Adaugă utilizator</h2><p>Autentificarea folosește adresa de email și parola setată aici.</p><form method="post" class="eco-create"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create"><label>Nume<input name="name" required maxlength="150" autocomplete="off" placeholder="Nume și prenume"></label><label>Email / utilizator<input type="email" name="email" required autocomplete="off" placeholder="nume@firma.ro"></label><label>Parolă<input type="password" name="password" required minlength="12" autocomplete="new-password" placeholder="Minimum 12 caractere"></label><label>Rol<select name="role"><option value="staff">Utilizator</option><option value="admin">Administrator</option></select></label><button class="btn btn-primary" type="submit">Creează utilizator</button></form></section>
<section class="eco-panel"><h2>Conturi existente</h2><div class="eco-users"><?php foreach($users as $u):?><div class="eco-user"><div class="eco-user-info"><strong><?=e($u['name'])?></strong><small><?=e($u['email'])?> · <?=($u['role']==='admin'?'Administrator':'Utilizator')?> · <?=((int)$u['active']?'Activ':'Inactiv')?></small></div><div class="eco-user-actions"><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><input type="password" name="password" minlength="12" required placeholder="Parolă nouă" aria-label="Parolă nouă pentru <?=e($u['name'])?>"><button class="btn btn-secondary">Schimbă parola</button></form><?php if((int)$u['id']!==(int)$_SESSION['user_id']):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><select name="role" aria-label="Rol"><option value="staff" <?=$u['role']==='staff'?'selected':''?>>Utilizator</option><option value="admin" <?=$u['role']==='admin'?'selected':''?>>Administrator</option></select><button class="btn btn-secondary">Salvează rolul</button></form><form method="post" onsubmit="return confirm('Confirmi modificarea accesului?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><button class="btn btn-secondary"><?=((int)$u['active']?'Dezactivează':'Activează')?></button></form><?php endif;?></div></div><?php endforeach;?></div></section></div>
<?php require __DIR__.'/../includes/footer.php';?>
