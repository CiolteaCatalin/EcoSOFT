<?php
require __DIR__.'/../includes/auth.php';require_login();require __DIR__.'/../includes/db.php';
function devize_ready(PDO $pdo):void{
 $pdo->exec('CREATE TABLE IF NOT EXISTS eco_devize (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, number VARCHAR(40) UNIQUE DEFAULT NULL, title VARCHAR(200) NOT NULL DEFAULT \'\', notes TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 $cols=$pdo->query('SHOW COLUMNS FROM eco_devize')->fetchAll(PDO::FETCH_COLUMN);if(!in_array('vat_rate',$cols,true))$pdo->exec('ALTER TABLE eco_devize ADD COLUMN vat_rate DECIMAL(5,2) NULL DEFAULT NULL');
 $pdo->exec('CREATE TABLE IF NOT EXISTS eco_devize_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, deviz_id INT UNSIGNED NOT NULL, sort_order INT NOT NULL, name VARCHAR(500) NOT NULL, quantity DECIMAL(12,3) NOT NULL, unit_price DECIMAL(14,2) NOT NULL, INDEX (deviz_id), FOREIGN KEY (deviz_id) REFERENCES eco_devize(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function deviz_token():string{if(empty($_SESSION['deviz_csrf']))$_SESSION['deviz_csrf']=bin2hex(random_bytes(24));return $_SESSION['deviz_csrf'];}
function deviz_check():void{if(!hash_equals(deviz_token(),(string)($_POST['csrf']??''))) {http_response_code(403);exit('Sesiune invalidă. Reîncarcă pagina.');}}
function deviz_items(PDO $pdo,int $id):array{$s=$pdo->prepare('SELECT * FROM eco_devize_items WHERE deviz_id=? ORDER BY sort_order,id');$s->execute([$id]);return $s->fetchAll();}
function deviz_money($v):string{return number_format((float)$v,2,',','.');}
