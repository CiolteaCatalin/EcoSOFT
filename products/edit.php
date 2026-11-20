<?php
require __DIR__.'/../includes/auth.php';
require_login();

require __DIR__.'/../includes/db.php';

$id=(int)($_GET['id']??0);

$product=[
    'sku'=>'',
    'name'=>'',
    'category'=>'',
    'description'=>'',
    'price'=>'0.00',
    'vat_rate'=>'21',
    'image_path'=>null,
    'active'=>1,
    'source'=>'manual',
    'product_type'=>'simple',
    'variation_count'=>0
];

$specs=[];
$variations=[];

if($id){

    $s=$pdo->prepare('SELECT * FROM products WHERE id=?');
    $s->execute([$id]);
    $product=$s->fetch()?:$product;

    $s=$pdo->prepare('SELECT * FROM product_specs WHERE product_id=? ORDER BY sort_order,id');
    $s->execute([$id]);
    $specs=$s->fetchAll();

    $s=$pdo->prepare('SELECT * FROM product_variations WHERE product_id=? ORDER BY id');
    $s->execute([$id]);
    $variations=$s->fetchAll();
}

$isVariable=($product['product_type']??'simple')==='variable';
$isWoo=($product['source']??'manual')==='woo';

if($_SERVER['REQUEST_METHOD']==='POST'){

    csrf_check();

    try{

        if(
            $id &&
            isset($_POST['delete_product']) &&
            $_POST['delete_product']==='1'
        ){

            $pdo->beginTransaction();

            $pdo
                ->prepare('DELETE FROM product_specs WHERE product_id=?')
                ->execute([$id]);

            $pdo
                ->prepare('DELETE FROM product_variations WHERE product_id=?')
                ->execute([$id]);

            $pdo
                ->prepare('DELETE FROM products WHERE id=?')
                ->execute([$id]);

            $pdo->commit();

            flash('success','Produsul a fost șters.');

            redirect('/products/');
        }

        $image=$product['image_path'];

        if(!empty($_FILES['image']['name'])){
            $image=upload_product_image($_FILES['image']);
        }

        $data=[
            trim($_POST['sku']??''),
            trim($_POST['name']??''),
            trim($_POST['category']??''),
            trim($_POST['description']??''),
            (float)($_POST['price']??0),
            (float)($_POST['vat_rate']??21),
            $image,
            isset($_POST['active'])?1:0
        ];

        if(!$data[1]){
            throw new RuntimeException('Denumirea produsului este obligatorie.');
        }

        $pdo->beginTransaction();

        if($id){

            $st=$pdo->prepare(
                'UPDATE products
                 SET
                    sku=?,
                    name=?,
                    category=?,
                    description=?,
                    price=?,
                    vat_rate=?,
                    image_path=?,
                    active=?
                 WHERE id=?'
            );

            $st->execute([
                ...$data,
                $id
            ]);

        }else{

            $st=$pdo->prepare(
                'INSERT INTO products(
                    sku,
                    name,
                    category,
                    description,
                    price,
                    vat_rate,
                    image_path,
                    active
                 )
                 VALUES(?,?,?,?,?,?,?,?)'
            );

            $st->execute($data);

            $id=(int)$pdo->lastInsertId();
        }

        $pdo
            ->prepare('DELETE FROM product_specs WHERE product_id=?')
            ->execute([$id]);

        $keys=$_POST['spec_key']??[];
        $vals=$_POST['spec_value']??[];

        $ins=$pdo->prepare(
            'INSERT INTO product_specs(
                product_id,
                spec_key,
                spec_value,
                sort_order
             )
             VALUES(?,?,?,?)'
        );

        foreach($keys as $i=>$k){

            $k=trim($k);
            $v=trim($vals[$i]??'');

            if($k!==''&&$v!==''){
                $ins->execute([
                    $id,
                    $k,
                    $v,
                    $i
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | VARIAȚII
        |--------------------------------------------------------------------------
        */

        if($isVariable){

            $variationIds=$_POST['variation_id']??[];
            $variationSkus=$_POST['variation_sku']??[];
            $variationNames=$_POST['variation_name']??[];
            $variationPrices=$_POST['variation_price']??[];
            $variationActives=$_POST['variation_active']??[];

            $updateVariation=$pdo->prepare(
                'UPDATE product_variations
                 SET
                    sku=?,
                    variation_name=?,
                    price=?,
                    active=?
                 WHERE id=?
                   AND product_id=?'
            );

            foreach($variationIds as $i=>$variationId){

                $variationId=(int)$variationId;

                if(!$variationId){
                    continue;
                }

                $vSku=trim($variationSkus[$i]??'');
                $vName=trim($variationNames[$i]??'');
                $vPrice=max(0,(float)($variationPrices[$i]??0));

                $vActive=
                    isset($variationActives[$variationId])
                    ?1
                    :0;

                $updateVariation->execute([
                    $vSku,
                    $vName,
                    $vPrice,
                    $vActive,
                    $variationId,
                    $id
                ]);
            }

            /*
            | Recalculăm prețul "de la"
            */

            $stmt=$pdo->prepare(
                'SELECT MIN(price)
                 FROM product_variations
                 WHERE product_id=?
                   AND active=1
                   AND price>0'
            );

            $stmt->execute([$id]);

            $minVariationPrice=$stmt->fetchColumn();

            if($minVariationPrice!==false&&$minVariationPrice!==null){

                $pdo
                    ->prepare('UPDATE products SET price=? WHERE id=?')
                    ->execute([
                        (float)$minVariationPrice,
                        $id
                    ]);
            }

            /*
            | Recalculăm numărul de variații active
            */

            $stmt=$pdo->prepare(
                'SELECT COUNT(*)
                 FROM product_variations
                 WHERE product_id=?
                   AND active=1'
            );

            $stmt->execute([$id]);

            $activeVariationCount=(int)$stmt->fetchColumn();

            $pdo
                ->prepare('UPDATE products SET variation_count=? WHERE id=?')
                ->execute([
                    $activeVariationCount,
                    $id
                ]);
        }

        $pdo->commit();

        flash('success','Produs salvat.');

        redirect('/products/edit.php?id='.$id);

    }catch(Throwable $e){

        if($pdo->inTransaction()){
            $pdo->rollBack();
        }

        $error=$e->getMessage();
    }
}

require __DIR__.'/../includes/header.php';
?>

<style>
.product-edit-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:18px}.product-edit-head__actions{display:flex;gap:8px}.product-meta-strip{display:flex;flex-wrap:wrap;gap:7px;margin-top:8px}.product-meta-badge{display:inline-flex;align-items:center;min-height:22px;padding:0 8px;border-radius:999px;background:#eef3f8;color:#657386;font-size:8px;font-weight:800;text-transform:uppercase}.product-meta-badge.variable{background:#fff2df;color:#b76513}.product-meta-badge.woo{background:#eaf4ff;color:#1769aa}.variations-panel{overflow:hidden}.variations-note{margin-bottom:14px;padding:10px 12px;border-radius:9px;background:#fff8e8;border:1px solid #f2e3ba;color:#806323;font-size:9px;line-height:1.45}.variations-list{display:grid;gap:8px}.variation-row{display:grid;grid-template-columns:minmax(150px,1.5fr) minmax(110px,.8fr) minmax(100px,.7fr) 90px;gap:8px;align-items:end;padding:10px;border:1px solid #e7edf2;border-radius:10px;background:#fbfcfd}.variation-row label{margin:0;font-size:8px}.variation-row input{margin-top:4px}.variation-active{height:40px;display:flex;align-items:center;justify-content:center;gap:6px;border:1px solid #e3e9ef;border-radius:9px;background:#fff;font-size:9px;font-weight:700;color:#556172}.variation-active input{width:auto;margin:0}.variation-attrs{grid-column:1/-1;margin-top:2px;font-size:8px;color:#7b8593}.product-delete{margin-top:18px;padding-top:18px;border-top:1px solid #edf1f5}.product-delete-box{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px;background:#fff8f8;border:1px solid #f2d7d7;border-radius:11px}.product-delete-copy strong{display:block;color:#8f3030;font-size:11px}.product-delete-copy small{display:block;margin-top:4px;color:#9a6a6a;font-size:9px;line-height:1.4}.btn-danger{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:8px 12px;border:1px solid #c93c3c;border-radius:8px;background:#fff;color:#b42f2f;font-size:11px;font-weight:700;cursor:pointer}.btn-danger:hover{background:#b42f2f;border-color:#b42f2f;color:#fff}@media(max-width:900px){.variation-row{grid-template-columns:1fr 1fr}.variation-active{justify-content:flex-start;padding:0 10px}}@media(max-width:760px){.product-edit-head{flex-direction:column;align-items:flex-start}.product-edit-head__actions{width:100%}.product-edit-head__actions .btn{flex:1}.product-delete-box{flex-direction:column;align-items:flex-start}.btn-danger{width:100%}}@media(max-width:560px){.variation-row{grid-template-columns:1fr}}
</style>

<div class="product-edit-head">

<div>

<span class="eyebrow">
Catalog
</span>

<h1>
<?=$id?'Editează produs':'Produs nou'?>
</h1>

<?php if($id):?>

<div class="product-meta-strip">

<?php if($isVariable):?>
<span class="product-meta-badge variable">
Variabil · <?=count($variations)?> variații
</span>
<?php else:?>
<span class="product-meta-badge">
Produs simplu
</span>
<?php endif;?>

<?php if($isWoo):?>
<span class="product-meta-badge woo">
WooCommerce
</span>
<?php else:?>
<span class="product-meta-badge">
Manual
</span>
<?php endif;?>

</div>

<?php endif;?>

</div>


<div class="product-edit-head__actions">

<a class="btn" href="/products/">
← Înapoi
</a>

</div>

</div>


<?php if(!empty($error)):?>

<div class="alert error">
<?=e($error)?>
</div>

<?php endif;?>


<form
method="post"
enctype="multipart/form-data"
class="form-grid"
>

<input
type="hidden"
name="csrf"
value="<?=e(csrf_token())?>"
>


<section class="panel">

<h2>
Date produs
</h2>


<div class="grid-2">

<label>
Denumire

<input
name="name"
required
value="<?=e($product['name'])?>"
>
</label>


<label>
SKU / Cod

<input
name="sku"
value="<?=e($product['sku']??'')?>"
>
</label>


<label>
Categorie

<input
name="category"
value="<?=e($product['category']??'')?>"
>
</label>


<label>
<?=$isVariable?'Preț minim variații':'Preț fără TVA'?>

<input
type="number"
step="0.01"
name="price"
value="<?=e((string)$product['price'])?>"
<?=$isVariable?'readonly':''?>
>
</label>


<label>
TVA %

<input
type="number"
step="0.01"
name="vat_rate"
value="<?=e((string)$product['vat_rate'])?>
">
</label>


<label>
Imagine

<input
type="file"
name="image"
accept="image/jpeg,image/png,image/webp"
>
</label>

</div>


<label>
Descriere

<textarea
name="description"
rows="6"
><?=e($product['description']??'')?></textarea>

</label>


<label class="check">

<input
type="checkbox"
name="active"
<?=$product['active']?'checked':''?>
>

Produs activ

</label>

</section>


<?php if($isVariable):?>

<section class="panel variations-panel">

<div class="panel-head">

<h2>
Variații
</h2>

<span class="mini">
<?=count($variations)?> opțiuni
</span>

</div>


<?php if($isWoo):?>

<div class="variations-note">
Acest produs este sincronizat din WooCommerce. Poți modifica local SKU-ul, denumirea, prețul sau starea unei variații, dar valorile pot fi suprascrise la următoarea actualizare WooCommerce.
</div>

<?php endif;?>


<div class="variations-list">

<?php foreach($variations as $v):?>

<?php
$attrs=json_decode(
    $v['attributes_json']?:'{}',
    true
)?:[];

$attrsText=[];

foreach($attrs as $k=>$val){
    $attrsText[]=$k.': '.$val;
}
?>

<div class="variation-row">

<input
type="hidden"
name="variation_id[]"
value="<?=(int)$v['id']?>"
>


<label>
Variație

<input
name="variation_name[]"
value="<?=e($v['variation_name']??'')?>"
>
</label>


<label>
SKU

<input
name="variation_sku[]"
value="<?=e($v['sku']??'')?>"
>
</label>


<label>
Preț fără TVA

<input
type="number"
step="0.01"
min="0"
name="variation_price[]"
value="<?=e((string)$v['price'])?>"
>
</label>


<label class="variation-active">

<input
type="checkbox"
name="variation_active[<?=(int)$v['id']?>]"
value="1"
<?=!empty($v['active'])?'checked':''?>
>

Activă

</label>


<?php if($attrsText):?>

<div class="variation-attrs">

<?=e(implode(' · ',$attrsText))?>

</div>

<?php endif;?>

</div>

<?php endforeach;?>


<?php if(!$variations):?>

<div style="padding:20px;text-align:center;color:#8a94a1;font-size:10px;border:1px dashed #dce5ee;border-radius:10px;">
Nu există variații sincronizate pentru acest produs.
</div>

<?php endif;?>

</div>

</section>

<?php endif;?>


<section class="panel">

<div class="panel-head">

<h2>
Specificații tehnice
</h2>

<button
type="button"
class="btn"
onclick="addSpec()"
>
+ Adaugă
</button>

</div>


<div id="specs">

<?php foreach($specs as $sp):?>

<div class="spec-row">

<input
name="spec_key[]"
value="<?=e($sp['spec_key'])?>"
placeholder="Ex: Putere"
>

<input
name="spec_value[]"
value="<?=e($sp['spec_value'])?>"
placeholder="Ex: 180 CP"
>

<button
type="button"
onclick="this.parentNode.remove()"
>
×
</button>

</div>

<?php endforeach;?>

</div>

</section>


<div>

<button class="btn btn-primary">
Salvează produsul
</button>

</div>


<?php if($id):?>

<div class="product-delete">

<div class="product-delete-box">

<div class="product-delete-copy">

<strong>
Ștergere produs
</strong>

<small>
Produsul va fi eliminat din catalog împreună cu variațiile lui. Acțiunea nu poate fi anulată.
</small>

</div>

<button
type="submit"
name="delete_product"
value="1"
class="btn-danger"
onclick="return confirm('Sigur vrei să ștergi acest produs? Acțiunea nu poate fi anulată.')"
>
Șterge produsul
</button>

</div>

</div>

<?php endif;?>

</form>


<script>
function addSpec(){
    document
        .getElementById('specs')
        .insertAdjacentHTML(
            'beforeend',
            '<div class="spec-row"><input name="spec_key[]" placeholder="Specificație"><input name="spec_value[]" placeholder="Valoare"><button type="button" onclick="this.parentNode.remove()">×</button></div>'
        );
}
</script>

<?php require __DIR__.'/../includes/footer.php';?>