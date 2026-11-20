<?php
require_once __DIR__.'/auth.php';require_login();require_once __DIR__.'/db.php';
function eco_setup(PDO $pdo):void{
$pdo->exec("CREATE TABLE IF NOT EXISTS eco_lists(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(220) NOT NULL,description TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS eco_list_products(id INT AUTO_INCREMENT PRIMARY KEY,list_id INT NOT NULL,reference VARCHAR(100) DEFAULT '',name VARCHAR(255) NOT NULL,price_eur DECIMAL(14,2) NOT NULL DEFAULT 0,price_ron DECIMAL(14,2) NOT NULL DEFAULT 0,exchange_rate DECIMAL(12,6) NOT NULL DEFAULT 0,on_site TINYINT(1) NOT NULL DEFAULT 0,checked_at DATE NULL,notes TEXT NULL,sort_order INT DEFAULT 0,INDEX(list_id),FOREIGN KEY(list_id) REFERENCES eco_lists(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS eco_list_product_tiers(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,min_qty INT NOT NULL,max_qty INT NULL,price_eur DECIMAL(14,2) NOT NULL,sort_order INT NOT NULL DEFAULT 0,INDEX(product_id),FOREIGN KEY(product_id) REFERENCES eco_list_products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS eco_editable_offers(id INT AUTO_INCREMENT PRIMARY KEY,number VARCHAR(70) NOT NULL DEFAULT '',offer_date DATE NOT NULL,language CHAR(2) NOT NULL DEFAULT 'ro',currency CHAR(3) NOT NULL DEFAULT 'EUR',customer VARCHAR(255) DEFAULT '',invoice_address TEXT NULL,delivery_address TEXT NULL,vat_number VARCHAR(100) DEFAULT '',email VARCHAR(255) DEFAULT '',phone VARCHAR(100) DEFAULT '',payment_terms TEXT NULL,delivery_terms TEXT NULL,price_terms VARCHAR(255) DEFAULT '',our_ref VARCHAR(255) DEFAULT '',file_link VARCHAR(500) DEFAULT '',discount_percent DECIMAL(8,2) NULL,transport_cost DECIMAL(14,2) NULL,transport_note VARCHAR(255) DEFAULT '',notes TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS eco_editable_offer_items(id INT AUTO_INCREMENT PRIMARY KEY,offer_id INT NOT NULL,parent_id INT NULL,reference VARCHAR(100) DEFAULT '',name VARCHAR(255) NOT NULL,description TEXT NULL,image_path VARCHAR(255) DEFAULT '',price DECIMAL(14,2) NOT NULL DEFAULT 0,quantity DECIMAL(12,3) NOT NULL DEFAULT 1,is_free TINYINT(1) NOT NULL DEFAULT 0,sort_order INT DEFAULT 0,INDEX(offer_id),FOREIGN KEY(offer_id) REFERENCES eco_editable_offers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function eco_rate():array{
 $cache=__DIR__.'/../private-cache/eur-ron.json';
 $data=is_file($cache)?json_decode((string)@file_get_contents($cache),true):null;
 if(is_array($data)&&!empty($data['rate'])&&($data['fetched']??0)>time()-21600)return $data;
 $fetch=function(string $url):string{
  if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>9,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>2,CURLOPT_USERAGENT=>'ECOsoft/2.1',CURLOPT_SSL_VERIFYPEER=>true]);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return $status===200&&is_string($body)?$body:'';}
  $ctx=stream_context_create(['http'=>['timeout'=>8,'header'=>"User-Agent: ECOsoft/2.1\r\n"]]);return (string)@file_get_contents($url,false,$ctx);
 };
 $new=null;
 $xml=$fetch('https://www.bnr.ro/nbrfxrates.xml');
 if($xml&&preg_match('~<Rate\b[^>]*\bcurrency=["\']EUR["\'][^>]*>\s*([0-9]+[.,][0-9]+)\s*</Rate>~i',$xml,$m)){
  preg_match('~<Cube\b[^>]*\bdate=["\']([^"\']+)~i',$xml,$d);$new=['rate'=>(float)str_replace(',','.',$m[1]),'date'=>$d[1]??date('Y-m-d'),'source'=>'BNR','fetched'=>time()];
 }
 if(!$new){$json=json_decode($fetch('https://open.er-api.com/v6/latest/EUR'),true);if(($json['result']??'')==='success'&&isset($json['rates']['RON']))$new=['rate'=>(float)$json['rates']['RON'],'date'=>gmdate('Y-m-d',($json['time_last_update_unix']??time())),'source'=>'ExchangeRate-API (rezervă)','fetched'=>time()];}
 if($new&&$new['rate']>0){$dir=dirname($cache);if(!is_dir($dir))@mkdir($dir,0700,true);@file_put_contents($cache,json_encode($new));return $new;}
 if(is_array($data)&&($data['rate']??0)>0){$data['stale']=true;return $data;}
 return ['rate'=>null,'date'=>null,'source'=>'indisponibil','fetched'=>0];
}
function eco_money($v,$currency='EUR'):string{return number_format((float)$v,2,',','.').' '.($currency==='EUR'?'€':'RON');}
function eco_offer_items(PDO $pdo,int $id):array{$s=$pdo->prepare('SELECT * FROM eco_editable_offer_items WHERE offer_id=? ORDER BY sort_order,id');$s->execute([$id]);return $s->fetchAll();}
function eco_upload_image(array $file):string{
 if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
 if($file['error']!==UPLOAD_ERR_OK||$file['size']>5*1024*1024)throw new RuntimeException('Imagine invalidă sau mai mare de 5 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext)throw new RuntimeException('Sunt acceptate doar JPG, PNG și WEBP.');
 $dir=__DIR__.'/../uploads/offer-images';if(!is_dir($dir))mkdir($dir,0755,true);$name=bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Nu se poate salva imaginea.');return 'uploads/offer-images/'.$name;
}
eco_setup($pdo);

function eco_tiers(PDO $pdo,int $productId):array{$s=$pdo->prepare('SELECT min_qty,max_qty,price_eur FROM eco_list_product_tiers WHERE product_id=? ORDER BY sort_order,id');$s->execute([$productId]);return $s->fetchAll();}
function eco_tiers_map(PDO $pdo,array $products):array{if(!$products)return []; $ids=array_map('intval',array_column($products,'id'));$s=$pdo->prepare('SELECT product_id,min_qty,max_qty,price_eur FROM eco_list_product_tiers WHERE product_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY sort_order,id');$s->execute($ids);$out=[];foreach($s as $r)$out[(int)$r['product_id']][]=$r;return $out;}
function eco_tier_label(array $t):string{$min=(int)$t['min_qty'];$max=$t['max_qty']===null?null:(int)$t['max_qty'];return number_format($min,0,',','.').($max===null?'+':($max===$min?'':'–'.number_format($max,0,',','.'))).' buc.';}
function eco_parse_tiers(array $rows):array{$out=[];foreach($rows as $row){$a=trim((string)($row['min']??''));$b=trim((string)($row['max']??''));$price=trim((string)($row['price']??''));if($a===''&&$b===''&&$price==='')continue;if(!ctype_digit($a)||($b!==''&&!ctype_digit($b))||!preg_match('/^\d+(?:[.,]\d{1,2})?$/',$price))throw new RuntimeException('Completează corect cantitatea și prețul fiecărui prag.');$min=(int)$a;$max=$b===''?null:(int)$b;$eur=(float)str_replace(',','.',$price);if($min<1||($max!==null&&$max<$min))throw new RuntimeException('Intervalul de cantitate este invalid.');$out[]=['min'=>$min,'max'=>$max,'price'=>$eur];}usort($out,fn($a,$b)=>$a['min']<=>$b['min']);foreach($out as $i=>$t){if($i&&($out[$i-1]['max']===null||$t['min']<=$out[$i-1]['max']))throw new RuntimeException('Pragurile se suprapun: dacă primul este exact 100 buc., următorul trebuie să înceapă la 101 (nu 100). Modifică intervalele și salvează din nou.');}return $out;}
function eco_save_tiers(PDO $pdo,int $pid,array $tiers):void{$s=$pdo->prepare('DELETE FROM eco_list_product_tiers WHERE product_id=?');$s->execute([$pid]);$s=$pdo->prepare('INSERT INTO eco_list_product_tiers(product_id,min_qty,max_qty,price_eur,sort_order) VALUES (?,?,?,?,?)');foreach($tiers as $i=>$t)$s->execute([$pid,$t['min'],$t['max'],$t['price'],$i]);}
