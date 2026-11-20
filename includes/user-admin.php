<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
function eco_user_admin(PDO $pdo):bool {
    $pdo->exec('CREATE TABLE IF NOT EXISTS eco_user_roles (user_id INT NOT NULL PRIMARY KEY, role VARCHAR(20) NOT NULL DEFAULT \'staff\') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec("INSERT IGNORE INTO eco_user_roles (user_id,role) SELECT id,'admin' FROM users ORDER BY id ASC LIMIT 1");
    $q=$pdo->prepare("SELECT role FROM eco_user_roles WHERE user_id=?");$q->execute([(int)($_SESSION['user_id']??0)]);
    return $q->fetchColumn()==='admin';
}
