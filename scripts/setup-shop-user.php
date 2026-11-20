<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
$password=trim((string)fgets(STDIN));if(strlen($password)<12){fwrite(STDERR,"Parola trebuie sa aiba cel putin 12 caractere.\n");exit(1);}
require __DIR__.'/../includes/db.php';
$email='shop@ecoautofin.ro';$hash=password_hash($password,PASSWORD_DEFAULT);
$s=$pdo->prepare('SELECT id FROM users WHERE email=?');$s->execute([$email]);$id=$s->fetchColumn();
if($id){$pdo->prepare('UPDATE users SET password_hash=?,active=1 WHERE id=?')->execute([$hash,$id]);}
else{$pdo->prepare('INSERT INTO users(name,email,password_hash,active) VALUES (?,?,?,1)')->execute(['ECO Autofin Shop',$email,$hash]);$id=$pdo->lastInsertId();}
try{$pdo->exec("CREATE TABLE IF NOT EXISTS eco_user_roles (user_id INT PRIMARY KEY, role VARCHAR(30) NOT NULL DEFAULT 'staff')");$pdo->prepare("INSERT INTO eco_user_roles(user_id,role) VALUES (?,'admin') ON DUPLICATE KEY UPDATE role='admin'")->execute([$id]);}catch(Throwable $e){fwrite(STDERR,"Rolul nu a putut fi actualizat: ".$e->getMessage()."\n");}
echo "Cont actualizat: $email\n";
