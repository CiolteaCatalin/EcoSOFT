<?php
require __DIR__.'/../includes/auth.php';require_login();require __DIR__.'/../includes/db.php';

$q=trim($_GET['q']??'');

if($q!==''){
    $stmt=$pdo->prepare('
        SELECT *
        FROM products
        WHERE name LIKE ?
           OR sku LIKE ?
           OR category LIKE ?
        ORDER BY id DESC
    ');
    $like='%'.$q.'%';
    $stmt->execute([$like,$like,$like]);
    $products=$stmt->fetchAll();
}else{
    $products=$pdo->query('
        SELECT *
        FROM products
        ORDER BY id DESC
    ')->fetchAll();
}

$totalProducts=count($products);

require __DIR__.'/../includes/header.php';
?>

<style>
.products-page{display:grid;gap:16px}.products-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px}.products-head h1{margin:3px 0 4px;font-size:27px;line-height:1.1;letter-spacing:-.03em;color:#172033}.products-head p{margin:0;color:#7b8593;font-size:12px}.products-count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:23px;margin-left:6px;padding:0 8px;border-radius:999px;background:#edf3f8;color:#607083;font-size:10px;font-weight:700;vertical-align:middle}.products-actions{display:flex;gap:8px}.products-search{display:flex;align-items:center;gap:8px;padding:9px;background:#fff;border:1px solid #e3e9ef;border-radius:12px}.products-search input{flex:1;min-width:260px;margin:0;border:0;background:transparent;box-shadow:none;padding:8px 10px}.products-search input:focus{border:0;box-shadow:none}.products-list{background:#fff;border:1px solid #e3e9ef;border-radius:14px;overflow:hidden}.products-header-row{display:grid;grid-template-columns:70px minmax(0,1.8fr) minmax(130px,.9fr) minmax(110px,.7fr) minmax(125px,.75fr) auto;gap:14px;padding:10px 14px;background:#fafbfd;border-bottom:1px solid #edf1f5;color:#7b8593;font-size:8px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.products-header-row span:last-child{text-align:right}.product-row{display:grid;grid-template-columns:70px minmax(0,1.8fr) minmax(130px,.9fr) minmax(110px,.7fr) minmax(125px,.75fr) auto;align-items:center;gap:14px;padding:11px 14px;border-bottom:1px solid #f0f3f6;transition:background .15s ease}.product-row:last-child{border-bottom:0}.product-row:hover{background:#fbfcfe}.product-thumb{width:54px;height:54px;border:1px solid #edf1f4;border-radius:9px;background:rgba(255,255,255,.82);display:flex;align-items:center;justify-content:center;overflow:hidden}.product-thumb img{width:100%;height:100%;object-fit:contain;padding:5px;background:rgba(255,255,255,.82);display:block}.product-thumb span{font-size:8px;color:#9aa5b1;text-align:center}.product-main{min-width:0}.product-title-line{display:flex;align-items:center;gap:7px;min-width:0}.product-title{display:block;min-width:0;color:#172033;font-size:12px;font-weight:700;line-height:1.35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.product-type{display:inline-flex;align-items:center;justify-content:center;min-height:18px;padding:0 6px;border-radius:999px;background:#fff2df;color:#b76513;font-size:6.5px;font-weight:800;text-transform:uppercase;white-space:nowrap;flex:0 0 auto}.product-meta{display:flex;align-items:center;gap:5px;margin-top:4px;color:#8a94a1;font-size:9px;white-space:nowrap;overflow:hidden}.product-meta-sep{color:#c1c7ce}.product-category{color:#2AB5EF;font-weight:700;overflow:hidden;text-overflow:ellipsis}.variation-count{display:inline-flex;align-items:center;justify-content:center;min-height:18px;padding:0 6px;border-radius:999px;background:#eef5fb;color:#1769aa;font-size:7px;font-weight:700;white-space:nowrap}.product-price-wrap{display:flex;flex-direction:column;gap:2px}.product-price-label{font-size:7px;color:#8a94a1;text-transform:uppercase;letter-spacing:.04em}.product-price{font-size:12px;font-weight:800;color:#172033;white-space:nowrap}.product-source{display:inline-flex;align-items:center;justify-content:center;min-height:21px;padding:0 7px;border-radius:999px;background:#eef4fa;color:#56728e;font-size:7px;font-weight:800;text-transform:uppercase;white-space:nowrap}.product-source.manual{background:#f1f3f5;color:#6f7782}.product-status{display:inline-flex;align-items:center;justify-content:center;min-height:21px;padding:0 7px;border-radius:999px;font-size:7px;font-weight:800;text-transform:uppercase;white-space:nowrap}.product-status.active{background:#e8f7ee;color:#27734a}.product-status.inactive{background:#fdeaea;color:#a13c3c}.product-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px}.product-edit{display:inline-flex;align-items:center;justify-content:center;min-height:29px;padding:5px 10px;border:1px solid #1769aa;border-radius:7px;background:#1769aa;color:#fff;font-size:10px;font-weight:700;transition:.15s ease}.product-edit:hover{background:#0f5b96;border-color:#0f5b96;color:#fff}.products-empty{padding:34px 20px;text-align:center;color:#7b8593;font-size:12px}@media(max-width:1100px){.product-row,.products-header-row{grid-template-columns:60px minmax(0,1fr) 110px 100px auto}.product-source-col{display:none}}@media(max-width:800px){.products-head{flex-direction:column;align-items:flex-start}.products-actions{width:100%}.products-actions .btn{flex:1}.products-search{flex-direction:column;align-items:stretch}.products-search input{min-width:0;width:100%}.products-header-row{display:none}.product-row{grid-template-columns:58px minmax(0,1fr) auto;padding:10px}.product-price-col,.product-status-col{display:none}.product-thumb{width:48px;height:48px}.product-actions{justify-content:flex-end}.product-title{font-size:11px}.product-meta{font-size:8px;flex-wrap:wrap;white-space:normal}.product-title-line{align-items:flex-start}}
</style>

<div class="products-page">

<div class="products-head">

<div>
<span class="eyebrow">Catalog</span>

<h1>
Produse
<span class="products-count"><?=$totalProducts?></span>
</h1>

<p>
Produse simple și variabile sincronizate din WooCommerce.
</p>
</div>

<div class="products-actions">

<a class="btn" href="/sync/">
Actualizează WooCommerce
</a>

<a class="btn btn-primary" href="/products/edit.php">
+ Produs nou
</a>

</div>

</div>


<form class="products-search" method="get">

<input
type="search"
name="q"
value="<?=e($q)?>"
placeholder="Caută produs, SKU sau categorie..."
>

<button type="submit" class="btn">
Caută
</button>

<?php if($q!==''):?>

<a class="btn" href="/products/">
Reset
</a>

<?php endif;?>

</form>


<div class="products-list">

<?php if($products):?>

<div class="products-header-row">

<span></span>
<span>Produs</span>
<span>Preț</span>
<span class="product-source-col">Sursă</span>
<span>Status</span>
<span>Acțiuni</span>

</div>


<?php foreach($products as $p):?>

<?php
$imagePath=trim((string)($p['image_path']??''));
$imageSrc='';

if($imagePath!==''){
    $imageSrc=preg_match('~^https?://~i',$imagePath)
        ?$imagePath
        :'/'.ltrim($imagePath,'/');
}

$category=trim((string)($p['category']??''));
$sku=trim((string)($p['sku']??''));
$source=$p['source']??'manual';

$productType=$p['product_type']??'simple';
$isVariable=$productType==='variable';
$variationCount=(int)($p['variation_count']??0);
?>

<div class="product-row">

<div class="product-thumb">

<?php if($imageSrc!==''):?>

<img
src="<?=e($imageSrc)?>"
alt="<?=e($p['name'])?>"
loading="lazy"
decoding="async"
onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
>

<span style="display:none;">
Fără imagine
</span>

<?php else:?>

<span>
Fără imagine
</span>

<?php endif;?>

</div>


<div class="product-main">

<div class="product-title-line">

<span class="product-title">
<?=e($p['name'])?>
</span>

<?php if($isVariable):?>

<span class="product-type">
Variabil
</span>

<?php endif;?>

</div>


<div class="product-meta">

<span class="product-category">
<?=e($category!==''?$category:'Necategorizat')?>
</span>

<span class="product-meta-sep">·</span>

<span>
<?=e($sku!==''?$sku:'fără SKU')?>
</span>


<?php if($isVariable):?>

<span class="product-meta-sep">·</span>

<span class="variation-count">
<?=$variationCount?> variații
</span>

<?php endif;?>

</div>

</div>


<div class="product-price-col">

<div class="product-price-wrap">

<?php if($isVariable):?>

<span class="product-price-label">
De la
</span>

<?php endif;?>

<span class="product-price">
<?=money((float)$p['price'])?>
</span>

</div>

</div>


<div class="product-source-col">

<span class="product-source <?=$source==='woo'?'':'manual'?>">
<?=$source==='woo'?'WooCommerce':'Manual'?>
</span>

</div>


<div class="product-status-col">

<span class="product-status <?=!empty($p['active'])?'active':'inactive'?>">
<?=!empty($p['active'])?'Activ':'Inactiv'?>
</span>

</div>


<div class="product-actions">

<a
class="product-edit"
href="/products/edit.php?id=<?=(int)$p['id']?>"
>
Editează
</a>

</div>

</div>

<?php endforeach;?>


<?php else:?>

<div class="products-empty">
Nu există produse pentru căutarea selectată.
</div>

<?php endif;?>

</div>

</div>

<?php require __DIR__.'/../includes/footer.php';?>