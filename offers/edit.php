<?php
require __DIR__.'/../includes/auth.php';require_login();require __DIR__.'/../includes/db.php';

$id=(int)($_GET['id']??0);

$clients=$pdo->query('
SELECT
    c.id,
    c.name,
    c.company,
    c.group_id,
    cg.name AS group_name,
    cg.discount_percent AS group_discount,
    cg.color AS group_color
FROM clients c
LEFT JOIN client_groups cg ON cg.id=c.group_id
ORDER BY c.company,c.name
')->fetchAll();

$products=$pdo->query('
SELECT
    p.*,
    GROUP_CONCAT(
        CONCAT(ps.spec_key,"|||",ps.spec_value)
        ORDER BY ps.sort_order
        SEPARATOR "~~~"
    ) specs
FROM products p
LEFT JOIN product_specs ps ON ps.product_id=p.id
WHERE p.active=1
GROUP BY p.id
ORDER BY p.name
')->fetchAll();

$variationRows=$pdo->query('
SELECT *
FROM product_variations
WHERE active=1
ORDER BY product_id,id
')->fetchAll();

$variationsByProduct=[];

foreach($variationRows as $v){
    $attrs=json_decode($v['attributes_json']?:'{}',true)?:[];

    $variationsByProduct[(int)$v['product_id']][]=[
        'id'=>(int)$v['id'],
        'woocommerce_id'=>(int)($v['woocommerce_variation_id']??0),
        'sku'=>$v['sku']??'',
        'name'=>$v['variation_name']??'Variație',
        'attributes'=>$attrs,
        'price'=>(float)$v['price'],
        'image'=>$v['image_path']??''
    ];
}

$offer=[
    'client_id'=>'',
    'offer_date'=>date('Y-m-d'),
    'valid_until'=>date('Y-m-d',strtotime('+14 days')),
    'status'=>'draft',
    'intro_text'=>'Vă mulțumim pentru interes. Vă prezentăm mai jos oferta noastră comercială.',
    'notes'=>'',
    'payment_terms'=>'Plată conform contractului / facturii.',
    'delivery_terms'=>'Termen de livrare conform disponibilității.',
    'show_description'=>0,
    'show_specs'=>0
];

$items=[];

if($id){
    $s=$pdo->prepare('SELECT * FROM offers WHERE id=?');
    $s->execute([$id]);
    $offer=$s->fetch()?:$offer;

    $s=$pdo->prepare('SELECT * FROM offer_items WHERE offer_id=? ORDER BY sort_order,id');
    $s->execute([$id]);
    $items=$s->fetchAll();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();

    try{

        if($id&&isset($_POST['delete_offer'])&&$_POST['delete_offer']==='1'){
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM offer_items WHERE offer_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM offers WHERE id=?')->execute([$id]);
            $pdo->commit();
            flash('success','Oferta a fost ștearsă.');
            redirect('/offers/');
        }

        $client=(int)($_POST['client_id']??0);

        if(!$client){
            throw new RuntimeException('Selectează un client.');
        }

        $showDescription=isset($_POST['show_description'])?1:0;
        $showSpecs=isset($_POST['show_specs'])?1:0;

        $names=$_POST['item_name']??[];
        $pids=$_POST['product_id']??[];
        $variationIds=$_POST['variation_id']??[];
        $variationNames=$_POST['variation_name']??[];
        $qtys=$_POST['quantity']??[];
        $prices=$_POST['unit_price']??[];
        $discounts=$_POST['discount_percent']??[];
        $vats=$_POST['vat_rate']??[];
        $descs=$_POST['description']??[];
        $specjson=$_POST['specs_json']??[];
        $imgs=$_POST['image_path']??[];
        $skus=$_POST['item_sku']??[];

        if(!$names){
            throw new RuntimeException('Adaugă cel puțin un produs.');
        }

        $subtotal=$discountTotal=$vatTotal=$grand=0;
        $prepared=[];

        foreach($names as $i=>$name){

            $name=trim($name);

            if($name===''){
                continue;
            }

            $q=max(.01,(float)($qtys[$i]??1));
            $price=max(0,(float)($prices[$i]??0));
            $disc=min(100,max(0,(float)($discounts[$i]??0)));
            $vat=max(0,(float)($vats[$i]??21));

            $grossBase=$q*$price;
            $discount=$grossBase*$disc/100;
            $net=$grossBase-$discount;
            $v=$net*$vat/100;
            $total=$net+$v;

            $subtotal+=$grossBase;
            $discountTotal+=$discount;
            $vatTotal+=$v;
            $grand+=$total;

            $prepared[]=[
                (int)($pids[$i]??0)?:null,
                (int)($variationIds[$i]??0)?:null,
                $name,
                trim($variationNames[$i]??''),
                trim($skus[$i]??''),
                trim($descs[$i]??''),
                $specjson[$i]??'[]',
                trim($imgs[$i]??''),
                $q,
                $price,
                $disc,
                $vat,
                $net,
                $v,
                $total,
                $i
            ];
        }

        if(!$prepared){
            throw new RuntimeException('Adaugă cel puțin un produs valid.');
        }

        $pdo->beginTransaction();

        if($id){

            $sql='UPDATE offers SET client_id=?,offer_date=?,valid_until=?,status=?,intro_text=?,notes=?,payment_terms=?,delivery_terms=?,show_description=?,show_specs=?,subtotal=?,discount_total=?,vat_total=?,grand_total=? WHERE id=?';

            $pdo->prepare($sql)->execute([
                $client,
                $_POST['offer_date'],
                $_POST['valid_until']?:null,
                $_POST['status'],
                trim($_POST['intro_text']??''),
                trim($_POST['notes']??''),
                trim($_POST['payment_terms']??''),
                trim($_POST['delivery_terms']??''),
                $showDescription,
                $showSpecs,
                $subtotal,
                $discountTotal,
                $vatTotal,
                $grand,
                $id
            ]);

            $pdo->prepare('DELETE FROM offer_items WHERE offer_id=?')->execute([$id]);

        }else{

            $num=offer_number($pdo);

            $sql='INSERT INTO offers(offer_number,offer_year,sequence_no,client_id,offer_date,valid_until,status,intro_text,notes,payment_terms,delivery_terms,show_description,show_specs,subtotal,discount_total,vat_total,grand_total,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';

            $pdo->prepare($sql)->execute([
                $num['number'],
                $num['year'],
                $num['seq'],
                $client,
                $_POST['offer_date'],
                $_POST['valid_until']?:null,
                $_POST['status'],
                trim($_POST['intro_text']??''),
                trim($_POST['notes']??''),
                trim($_POST['payment_terms']??''),
                trim($_POST['delivery_terms']??''),
                $showDescription,
                $showSpecs,
                $subtotal,
                $discountTotal,
                $vatTotal,
                $grand,
                $_SESSION['user_id']
            ]);

            $id=(int)$pdo->lastInsertId();
        }

        $ins=$pdo->prepare('
        INSERT INTO offer_items(
            offer_id,
            product_id,
            variation_id,
            item_name,
            variation_name,
            item_sku,
            description,
            specs_json,
            image_path,
            quantity,
            unit_price,
            discount_percent,
            vat_rate,
            line_net,
            line_vat,
            line_total,
            sort_order
        ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ');

        foreach($prepared as $row){
            $ins->execute([$id,...$row]);
        }

        $pdo->commit();

        flash('success','Oferta a fost salvată.');
        redirect('/offers/edit.php?id='.$id);

    }catch(Throwable $e){

        if($pdo->inTransaction()){
            $pdo->rollBack();
        }

        $error=$e->getMessage();
    }
}

$productPayload=[];

foreach($products as $p){

    $specs=[];

    if(!empty($p['specs'])){
        foreach(explode('~~~',$p['specs']) as $pair){
            [$k,$v]=array_pad(explode('|||',$pair,2),2,'');
            $specs[$k]=$v;
        }
    }

    $pid=(int)$p['id'];

    $productPayload[$pid]=[
        'id'=>$pid,
        'type'=>$p['product_type']??'simple',
        'variation_count'=>(int)($p['variation_count']??0),
        'name'=>$p['name'],
        'sku'=>$p['sku'],
        'description'=>$p['description'],
        'price'=>(float)$p['price'],
        'vat'=>(float)$p['vat_rate'],
        'image'=>$p['image_path'],
        'specs'=>$specs,
        'variations'=>$variationsByProduct[$pid]??[]
    ];
}

$clientPayload=[];

foreach($clients as $c){
    $clientPayload[(int)$c['id']]=[
        'id'=>(int)$c['id'],
        'group_id'=>(int)($c['group_id']??0),
        'group_name'=>$c['group_name']??'',
        'discount'=>(float)($c['group_discount']??0),
        'color'=>$c['group_color']??'#64748B'
    ];
}

require __DIR__.'/../includes/header.php';
?>

<style>
.offer-edit-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:18px}.offer-edit-head h1{margin:3px 0 0;font-size:27px;letter-spacing:-.03em}.offer-edit-actions{display:flex;gap:8px}.client-group-info{display:none;align-items:center;gap:8px;margin-top:7px;padding:7px 9px;border:1px solid #e5ebf1;border-radius:9px;background:#f8fafc}.client-group-info.show{display:flex}.client-group-badge{display:inline-flex;align-items:center;justify-content:center;min-height:22px;padding:0 8px;border-radius:999px;color:#fff;font-size:8px;font-weight:800;white-space:nowrap}.client-group-copy{font-size:9px;color:#667085}.client-group-copy b{color:#172033}.apply-group-discount{margin-left:auto;border:0;background:transparent;color:#1769aa;font-size:8px;font-weight:800;cursor:pointer;padding:3px 5px}.apply-group-discount:hover{text-decoration:underline}.offer-product-row{border:1px solid #e3e9ef;border-radius:12px;padding:12px;margin-bottom:10px;background:#fff}.offer-product-top{display:flex;gap:12px;align-items:flex-start}.offer-product-thumb{width:72px;height:72px;flex:0 0 72px;border:1px solid #edf1f4;border-radius:9px;background:rgba(255,255,255,.82);overflow:hidden;display:flex;align-items:center;justify-content:center}.offer-product-thumb img{width:100%;height:100%;object-fit:contain;padding:5px;background:rgba(255,255,255,.82)}.offer-product-thumb span{font-size:8px;color:#9aa5b1;text-align:center}.offer-product-fields{flex:1;min-width:0}.offer-product-title-row{display:grid;grid-template-columns:minmax(0,1fr) 34px;gap:8px}.offer-product-title-row input{margin:0}.offer-remove{width:34px;height:34px;border:0;border-radius:8px;background:#f1f4f7;color:#7b8593;font-size:18px;cursor:pointer}.offer-variation{display:flex;align-items:center;gap:7px;margin-top:6px}.variation-pill{display:inline-flex;align-items:center;min-height:24px;padding:4px 8px;border-radius:7px;background:#eef5fb;color:#1769aa;font-size:9px;font-weight:700}.variation-change{border:0;background:transparent;color:#1769aa;font-size:9px;font-weight:700;cursor:pointer;padding:3px}.offer-item-grid{display:grid;grid-template-columns:1fr 1.4fr 1fr 1fr 1.2fr;gap:8px;margin-top:9px}.offer-item-grid label{margin:0;font-size:9px}.offer-line-total{height:39px;padding:9px 10px;border:1px solid #e3e9ef;border-radius:9px;background:#f8fafc}.offer-line-total span{display:block;font-size:7px;color:#8a94a1;text-transform:uppercase}.offer-line-total b{display:block;margin-top:2px;font-size:11px}.offer-desc{margin-top:9px}.offer-desc textarea{margin-top:5px}.picker-item--product{display:grid;grid-template-columns:52px minmax(0,1fr) auto;align-items:center;gap:10px;padding:9px 10px}.picker-thumb{width:52px;height:52px;border-radius:8px;overflow:hidden;background:rgba(255,255,255,.82);border:1px solid #edf1f4;display:flex;align-items:center;justify-content:center}.picker-thumb img{width:100%;height:100%;object-fit:contain;padding:4px}.picker-thumb span{font-size:8px;color:#9aa5b1}.picker-product-info{min-width:0;text-align:left}.picker-product-info strong{display:block;font-size:11px;color:#172033;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.picker-product-info small{display:block;margin-top:3px;font-size:8px;color:#7b8593;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.picker-price{font-size:10px;white-space:nowrap}.variable-badge{display:inline-flex;margin-top:5px;padding:3px 6px;border-radius:999px;background:#fff2df;color:#b76513;font-size:7px;font-weight:800;text-transform:uppercase}.modal-card{width:min(760px,100%)}#pickerList,#variationList{max-height:420px;overflow:auto}.variation-item{display:grid;grid-template-columns:52px minmax(0,1fr) auto;align-items:center;gap:10px;width:100%;padding:9px 10px;margin-bottom:7px;border:1px solid #e3e9ef;border-radius:10px;background:#fff;cursor:pointer;text-align:left}.variation-item:hover{background:#f8fafc;border-color:#bfd0df}.variation-info{min-width:0}.variation-info strong{display:block;font-size:11px;color:#172033}.variation-info small{display:block;margin-top:3px;font-size:8px;color:#7b8593}.variation-price{font-size:10px;font-weight:800;color:#172033;white-space:nowrap}.variation-head-copy{margin-bottom:12px}.variation-head-copy strong{display:block;font-size:13px;color:#172033}.variation-head-copy small{display:block;margin-top:4px;font-size:9px;color:#7b8593}.summary-total{margin-top:8px;padding-top:14px;border-top:2px solid #ef7f1a}.summary-total strong{font-size:20px}.pdf-options{margin-top:16px;padding:14px;border:1px solid #e3e9ef;border-radius:11px;background:#f8fafc}.pdf-options__head{margin-bottom:10px}.pdf-options__head strong{display:block;font-size:11px;color:#172033}.pdf-options__head small{display:block;margin-top:3px;font-size:9px;color:#7b8593}.pdf-option{display:flex!important;align-items:flex-start;gap:9px;margin:0!important;padding:8px 0;border-top:1px solid #edf1f4;cursor:pointer}.pdf-option input{width:15px;height:15px;margin:1px 0 0;flex:0 0 15px}.pdf-option span{display:block}.pdf-option b{display:block;font-size:10px;color:#344054}.pdf-option small{display:block;margin-top:2px;font-size:8px;font-weight:400;color:#8a94a1}.offer-delete{margin-top:20px}.offer-delete-box{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px;background:#fff8f8;border:1px solid #f2d7d7;border-radius:11px}.offer-delete-copy strong{display:block;color:#8f3030;font-size:11px}.offer-delete-copy small{display:block;margin-top:4px;color:#9a6a6a;font-size:9px;line-height:1.4}.btn-danger{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:8px 12px;border:1px solid #c93c3c;border-radius:8px;background:#fff;color:#b42f2f;font-size:11px;font-weight:700;cursor:pointer}.btn-danger:hover{background:#b42f2f;color:#fff}@media(max-width:850px){.offer-edit-head{align-items:flex-start;flex-direction:column}.offer-edit-actions{width:100%;flex-wrap:wrap}.offer-edit-actions .btn{flex:1}.offer-item-grid{grid-template-columns:1fr 1fr}.offer-product-thumb{width:62px;height:62px;flex-basis:62px}}@media(max-width:760px){.client-group-info{align-items:flex-start;flex-wrap:wrap}.apply-group-discount{width:100%;text-align:left;margin-left:0}.offer-delete-box{flex-direction:column;align-items:flex-start}.btn-danger{width:100%}}@media(max-width:600px){.offer-product-thumb{display:none}.offer-item-grid{grid-template-columns:1fr 1fr}}
</style>

<div class="offer-edit-head">
<div><span class="eyebrow">Ofertare</span><h1><?=$id?'Editează oferta':'Ofertă nouă'?></h1></div>
<div class="offer-edit-actions">
<a class="btn" href="/offers/">← Înapoi</a>
<?php if($id):?>
<a class="btn" href="/offers/view.php?id=<?=$id?>">Vezi oferta</a>
<a class="btn btn-primary" href="/offers/pdf.php?id=<?=$id?>" target="_blank" rel="noopener">PDF</a>
<?php endif;?>
</div>
</div>

<?php if(!empty($error)):?><div class="alert error"><?=e($error)?></div><?php endif;?>

<form method="post" id="offerForm">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

<div class="offer-layout">
<div>

<section class="panel">
<h2>Date ofertă</h2>

<div class="grid-2">

<label>Client
<select name="client_id" id="clientSelect" required onchange="ecoClientChanged()">
<option value="">Selectează client</option>

<?php foreach($clients as $c):?>

<option
value="<?=(int)$c['id']?>"
<?=$offer['client_id']==$c['id']?'selected':''?>
>
<?=e(($c['company']?$c['company'].' — ':'').$c['name'])?>
<?php if(!empty($c['group_name'])):?>
 — <?=e($c['group_name'])?>
<?php endif;?>
</option>

<?php endforeach;?>

</select>

<div id="clientGroupInfo" class="client-group-info">

<span
id="clientGroupBadge"
class="client-group-badge"
>
Grupă
</span>

<span class="client-group-copy">
Discount implicit:
<b id="clientGroupDiscount">0%</b>
</span>

<button
type="button"
class="apply-group-discount"
onclick="ecoApplyGroupDiscount()"
>
Aplică discountul tuturor produselor
</button>

</div>

</label>


<label>Status
<select name="status">
<option value="draft" <?=$offer['status']==='draft'?'selected':''?>>Draft</option>
<option value="sent" <?=$offer['status']==='sent'?'selected':''?>>Trimisă</option>
<option value="accepted" <?=$offer['status']==='accepted'?'selected':''?>>Acceptată</option>
<option value="rejected" <?=$offer['status']==='rejected'?'selected':''?>>Respinsă</option>
</select>
</label>

<label>Data ofertă
<input type="date" name="offer_date" value="<?=e($offer['offer_date'])?>" required>
</label>

<label>Valabilă până la
<input type="date" name="valid_until" value="<?=e($offer['valid_until']??'')?>">
</label>

</div>

<label>Introducere
<textarea name="intro_text" rows="3"><?=e($offer['intro_text']??'')?></textarea>
</label>

<div class="pdf-options">

<div class="pdf-options__head">
<strong>Conținut PDF</strong>
<small>Implicit oferta este compactă și orientată spre preț.</small>
</div>

<label class="pdf-option">
<input type="checkbox" name="show_description" value="1" <?=!empty($offer['show_description'])?'checked':''?>>
<span><b>Afișează descrierea produselor</b><small>Include descrierea produselor în PDF.</small></span>
</label>

<label class="pdf-option">
<input type="checkbox" name="show_specs" value="1" <?=!empty($offer['show_specs'])?'checked':''?>>
<span><b>Afișează specificațiile tehnice</b><small>Include specificațiile produselor în PDF.</small></span>
</label>

</div>

</section>


<section class="panel">

<div class="panel-head">
<h2>Produse ofertate</h2>
<button type="button" class="btn btn-primary" onclick="ecoOpenPicker()">+ Adaugă produs</button>
</div>

<div id="offerItems"></div>

</section>


<section class="panel">

<h2>Condiții comerciale</h2>

<div class="grid-2">

<label>Condiții plată
<textarea name="payment_terms" rows="3"><?=e($offer['payment_terms']??'')?></textarea>
</label>

<label>Livrare
<textarea name="delivery_terms" rows="3"><?=e($offer['delivery_terms']??'')?></textarea>
</label>

</div>

<label>Observații
<textarea name="notes" rows="4"><?=e($offer['notes']??'')?></textarea>
</label>

</section>

</div>


<aside>

<div class="panel sticky-summary">

<span class="eyebrow">Total ofertă</span>

<div id="summaryGroup" style="display:none;margin:10px 0 4px;"></div>

<div class="summary-row">
<span>Subtotal</span>
<b id="sumSubtotal">0,00 RON</b>
</div>

<div class="summary-row">
<span>Discount</span>
<b id="sumDiscount">0,00 RON</b>
</div>

<div class="summary-row">
<span>TVA</span>
<b id="sumVat">0,00 RON</b>
</div>

<div class="summary-total">
<span>Total</span>
<strong id="sumTotal">0,00 RON</strong>
</div>

<button class="btn btn-primary btn-block">
Salvează oferta
</button>

</div>

</aside>

</div>

</form>


<?php if($id):?>

<div class="offer-delete">

<form
method="post"
onsubmit="return confirm('Sigur vrei să ștergi această ofertă? Acțiunea este definitivă.')"
>

<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="delete_offer" value="1">

<div class="offer-delete-box">

<div class="offer-delete-copy">
<strong>Ștergere ofertă</strong>
<small>Oferta și liniile de produse asociate vor fi eliminate definitiv.</small>
</div>

<button type="submit" class="btn-danger">
Șterge oferta
</button>

</div>

</form>

</div>

<?php endif;?>


<div class="modal" id="productModal">

<div class="modal-card">

<div class="panel-head">
<h2>Selectează produsul</h2>
<button type="button" class="icon-btn" onclick="ecoClosePicker()">×</button>
</div>

<input
id="productSearch"
placeholder="Caută produs, SKU sau categorie..."
oninput="ecoFilterProducts()"
>

<div class="picker-list" id="pickerList">

<?php foreach($products as $p):

$img=trim((string)($p['image_path']??''));

$imgSrc=$img!==''
    ?(preg_match('~^https?://~i',$img)?$img:'/'.ltrim($img,'/'))
    :'';

$type=$p['product_type']??'simple';
$count=(int)($p['variation_count']??0);

?>

<button
type="button"
class="picker-item picker-item--product"
data-search="<?=e(
    ($p['name']??'').' '.
    ($p['sku']??'').' '.
    ($p['category']??'')
)?>"
onclick="ecoPickProduct(<?=$p['id']?>)"
>

<span class="picker-thumb">

<?php if($imgSrc):?>
<img src="<?=e($imgSrc)?>" alt="" loading="lazy">
<?php else:?>
<span>Fără imagine</span>
<?php endif;?>

</span>

<span class="picker-product-info">

<strong>
<?=e($p['name'])?>
</strong>

<small>
<?=e($p['sku']??'')?> · <?=e($p['category']??'')?>
</small>

<?php if($type==='variable'):?>
<span class="variable-badge">
<?=$count?> variații
</span>
<?php endif;?>

</span>

<b class="picker-price">
<?=$type==='variable'?'de la ':''?><?=money((float)$p['price'])?>
</b>

</button>

<?php endforeach;?>

</div>

<button
type="button"
class="btn"
onclick="ecoAddManual();ecoClosePicker()"
>
+ Linie manuală
</button>

</div>

</div>


<div class="modal" id="variationModal">

<div class="modal-card">

<div class="panel-head">
<h2>Selectează variația</h2>
<button type="button" class="icon-btn" onclick="ecoCloseVariationPicker()">×</button>
</div>

<div class="variation-head-copy">
<strong id="variationProductName"></strong>
<small>Alege varianta exactă pe care dorești să o incluzi în ofertă.</small>
</div>

<div id="variationList"></div>

<div style="margin-top:12px">
<button type="button" class="btn" onclick="ecoBackToProducts()">
← Înapoi la produse
</button>
</div>

</div>

</div>


<script>
const PRODUCTS=<?=json_encode($productPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const CLIENTS=<?=json_encode($clientPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const EXISTING=<?=json_encode($items,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;

let ITEMS=[];
let ITEMID=0;
let ACTIVE_PRODUCT_ID=null;
let EDIT_VARIATION_UID=null;
let ACTIVE_CLIENT_DISCOUNT=0;

const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

const money=n=>new Intl.NumberFormat('ro-RO',{
    minimumFractionDigits:2,
    maximumFractionDigits:2
}).format(Number(n)||0)+' RON';

const imgsrc=p=>{
    p=String(p||'').trim();
    return !p?'':/^https?:\/\//i.test(p)?p:'/'+p.replace(/^\/+/,'');
};

function ecoClientChanged(){

    const select=document.getElementById('clientSelect');
    const clientId=Number(select.value)||0;
    const client=CLIENTS[clientId]||null;

    const info=document.getElementById('clientGroupInfo');
    const badge=document.getElementById('clientGroupBadge');
    const discount=document.getElementById('clientGroupDiscount');
    const summary=document.getElementById('summaryGroup');

    ACTIVE_CLIENT_DISCOUNT=client
        ?Number(client.discount)||0
        :0;

    if(
        client &&
        client.group_id &&
        client.group_name
    ){

        badge.textContent=client.group_name;
        badge.style.background=client.color||'#64748B';

        discount.textContent=
            ACTIVE_CLIENT_DISCOUNT.toLocaleString(
                'ro-RO',
                {maximumFractionDigits:2}
            )+'%';

        info.classList.add('show');

        summary.style.display='block';

        summary.innerHTML=
            '<span class="client-group-badge" style="background:'+
            esc(client.color||'#64748B')+
            '">'+
            esc(client.group_name)+
            '</span> '+
            '<span style="font-size:8px;color:#7b8593;margin-left:5px;">'+
            ACTIVE_CLIENT_DISCOUNT.toLocaleString(
                'ro-RO',
                {maximumFractionDigits:2}
            )+
            '% discount</span>';

    }else{

        info.classList.remove('show');
        summary.style.display='none';
        summary.innerHTML='';
    }
}

function ecoApplyGroupDiscount(){

    if(!ITEMS.length){
        return;
    }

    ITEMS.forEach(item=>{
        item.discount_percent=ACTIVE_CLIENT_DISCOUNT;
    });

    ecoRender();
}

function ecoOpenPicker(){
    EDIT_VARIATION_UID=null;
    document.getElementById('productModal').classList.add('show');
    document.getElementById('productSearch').value='';
    ecoFilterProducts();
    setTimeout(()=>document.getElementById('productSearch').focus(),50);
}

function ecoClosePicker(){
    document.getElementById('productModal').classList.remove('show');
}

function normalizeSearch(value){
    return String(value||'')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g,'')
        .toLowerCase()
        .trim();
}

function ecoFilterProducts(){

    const q=normalizeSearch(
        document.getElementById('productSearch').value
    );

    document
        .querySelectorAll('#pickerList .picker-item--product')
        .forEach(item=>{

            const haystack=normalizeSearch(
                item.dataset.search||''
            );

            item.style.display=
                !q||haystack.includes(q)
                    ?'grid'
                    :'none';
        });
}

function ecoPickProduct(id){

    let p=PRODUCTS[id];

    if(!p){
        return;
    }

    if(
        p.type==='variable' &&
        Array.isArray(p.variations) &&
        p.variations.length
    ){
        ecoClosePicker();
        ecoOpenVariationPicker(id);
        return;
    }

    ITEMS.push({
        uid:++ITEMID,
        product_id:p.id,
        variation_id:'',
        variation_name:'',
        item_name:p.name||'',
        item_sku:p.sku||'',
        description:p.description||'',
        specs_json:JSON.stringify(p.specs||{}),
        image_path:p.image||'',
        quantity:1,
        unit_price:Number(p.price)||0,
        discount_percent:ACTIVE_CLIENT_DISCOUNT,
        vat_rate:Number(p.vat)||21
    });

    ecoRender();
    ecoClosePicker();
}

function ecoOpenVariationPicker(productId){

    let p=PRODUCTS[productId];

    if(!p){
        return;
    }

    ACTIVE_PRODUCT_ID=productId;

    document
        .getElementById('variationProductName')
        .textContent=p.name||'Produs';

    let list=document.getElementById('variationList');

    if(!p.variations||!p.variations.length){

        list.innerHTML=
            '<div style="padding:20px;text-align:center;color:#8a94a1">Produsul nu are variații active.</div>';

    }else{

        list.innerHTML=p.variations.map(v=>{

            let src=imgsrc(v.image||p.image);

            let thumb=src
                ?`<img src="${esc(src)}" alt="" loading="lazy">`
                :'<span>Fără imagine</span>';

            let attrs=Object.entries(v.attributes||{})
                .map(([k,val])=>`${esc(k)}: ${esc(val)}`)
                .join(' · ');

            return `
<button
type="button"
class="variation-item"
onclick="ecoPickVariation(${productId},${v.id})"
>

<span class="picker-thumb">
${thumb}
</span>

<span class="variation-info">
<strong>${esc(v.name)}</strong>
<small>${esc(v.sku||'fără SKU')}${attrs?' · '+attrs:''}</small>
</span>

<span class="variation-price">
${money(v.price)}
</span>

</button>
`;

        }).join('');
    }

    document
        .getElementById('variationModal')
        .classList
        .add('show');
}

function ecoCloseVariationPicker(){
    document.getElementById('variationModal').classList.remove('show');
    ACTIVE_PRODUCT_ID=null;
    EDIT_VARIATION_UID=null;
}

function ecoBackToProducts(){
    ecoCloseVariationPicker();
    ecoOpenPicker();
}

function ecoPickVariation(productId,variationId){

    let p=PRODUCTS[productId];

    if(!p){
        return;
    }

    let v=(p.variations||[])
        .find(x=>Number(x.id)===Number(variationId));

    if(!v){
        return;
    }

    if(EDIT_VARIATION_UID!==null){

        let item=ITEMS.find(
            x=>x.uid===EDIT_VARIATION_UID
        );

        if(item){
            item.variation_id=v.id;
            item.variation_name=v.name||'';
            item.item_sku=v.sku||p.sku||'';
            item.unit_price=Number(v.price)||0;
            item.image_path=v.image||p.image||'';
        }

    }else{

        ITEMS.push({
            uid:++ITEMID,
            product_id:p.id,
            variation_id:v.id,
            variation_name:v.name||'',
            item_name:p.name||'',
            item_sku:v.sku||p.sku||'',
            description:p.description||'',
            specs_json:JSON.stringify(p.specs||{}),
            image_path:v.image||p.image||'',
            quantity:1,
            unit_price:Number(v.price)||0,
            discount_percent:ACTIVE_CLIENT_DISCOUNT,
            vat_rate:Number(p.vat)||21
        });
    }

    ecoCloseVariationPicker();
    ecoRender();
}

function ecoChangeVariation(uid){

    let item=ITEMS.find(
        x=>x.uid===uid
    );

    if(!item||!item.product_id){
        return;
    }

    let p=PRODUCTS[item.product_id];

    if(!p||p.type!=='variable'){
        return;
    }

    EDIT_VARIATION_UID=uid;

    ecoOpenVariationPicker(
        item.product_id
    );
}

function ecoAddManual(){

    ITEMS.push({
        uid:++ITEMID,
        product_id:'',
        variation_id:'',
        variation_name:'',
        item_name:'',
        item_sku:'',
        description:'',
        specs_json:'[]',
        image_path:'',
        quantity:1,
        unit_price:0,
        discount_percent:ACTIVE_CLIENT_DISCOUNT,
        vat_rate:21
    });

    ecoRender();
}

function ecoRemove(uid){
    ITEMS=ITEMS.filter(
        x=>x.uid!==uid
    );
    ecoRender();
}

function ecoUpdate(uid,key,val){

    let x=ITEMS.find(
        i=>i.uid===uid
    );

    if(!x){
        return;
    }

    x[key]=[
        'quantity',
        'unit_price',
        'discount_percent',
        'vat_rate'
    ].includes(key)
        ?Number(val)||0
        :val;

    ecoTotals();
}

function ecoRender(){

    let el=document.getElementById('offerItems');

    if(!ITEMS.length){

        el.innerHTML=
            '<div style="padding:22px;text-align:center;color:#8a94a1;font-size:11px;border:1px dashed #dce5ee;border-radius:10px">Nu ai adăugat încă produse.</div>';

        ecoTotals();

        return;
    }

    el.innerHTML=ITEMS.map(x=>{

        let src=imgsrc(x.image_path);

        let thumb=src
            ?`<img src="${esc(src)}" alt="" loading="lazy">`
            :'<span>Fără imagine</span>';

        let variation=x.variation_name
            ?`<div class="offer-variation"><span class="variation-pill">${esc(x.variation_name)}</span><button type="button" class="variation-change" onclick="ecoChangeVariation(${x.uid})">Schimbă</button></div>`
            :'';

        return `
<div class="offer-product-row" data-uid="${x.uid}">

<input type="hidden" name="product_id[]" value="${esc(x.product_id)}">
<input type="hidden" name="variation_id[]" value="${esc(x.variation_id)}">
<input type="hidden" name="variation_name[]" value="${esc(x.variation_name)}">
<input type="hidden" name="item_sku[]" value="${esc(x.item_sku)}">
<input type="hidden" name="specs_json[]" value="${esc(x.specs_json)}">
<input type="hidden" name="image_path[]" value="${esc(x.image_path)}">

<div class="offer-product-top">

<div class="offer-product-thumb">
${thumb}
</div>

<div class="offer-product-fields">

<div class="offer-product-title-row">

<input
name="item_name[]"
value="${esc(x.item_name)}"
placeholder="Nume produs"
oninput="ecoUpdate(${x.uid},'item_name',this.value)"
required
>

<button
type="button"
class="offer-remove"
onclick="ecoRemove(${x.uid})"
>
×
</button>

</div>

${variation}

<div class="offer-item-grid">

<label>
Cantitate

<input
type="number"
min=".01"
step=".01"
name="quantity[]"
value="${esc(x.quantity)}"
oninput="ecoUpdate(${x.uid},'quantity',this.value)"
>
</label>


<label>
Preț unitar

<input
type="number"
min="0"
step=".01"
name="unit_price[]"
value="${esc(x.unit_price)}"
oninput="ecoUpdate(${x.uid},'unit_price',this.value)"
>
</label>


<label>
Discount %

<input
type="number"
min="0"
max="100"
step=".01"
name="discount_percent[]"
value="${esc(x.discount_percent)}"
oninput="ecoUpdate(${x.uid},'discount_percent',this.value)"
>
</label>


<label>
TVA %

<input
type="number"
min="0"
step=".01"
name="vat_rate[]"
value="${esc(x.vat_rate)}"
oninput="ecoUpdate(${x.uid},'vat_rate',this.value)"
>
</label>


<div class="offer-line-total">
<span>Total</span>
<b id="lineTotal${x.uid}">0,00 RON</b>
</div>

</div>


<label class="offer-desc">

Descriere

<textarea
rows="2"
name="description[]"
oninput="ecoUpdate(${x.uid},'description',this.value)"
>${esc(x.description)}</textarea>

</label>

</div>

</div>

</div>
`;
    }).join('');

    ecoTotals();
}

function ecoTotals(){

    let sub=0;
    let disc=0;
    let vat=0;
    let total=0;

    ITEMS.forEach(x=>{

        let q=Math.max(.01,Number(x.quantity)||0);
        let p=Math.max(0,Number(x.unit_price)||0);
        let d=Math.min(100,Math.max(0,Number(x.discount_percent)||0));
        let v=Math.max(0,Number(x.vat_rate)||0);

        let base=q*p;
        let di=base*d/100;
        let net=base-di;
        let tx=net*v/100;
        let gr=net+tx;

        sub+=base;
        disc+=di;
        vat+=tx;
        total+=gr;

        let t=document.getElementById(
            'lineTotal'+x.uid
        );

        if(t){
            t.textContent=money(gr);
        }
    });

    document.getElementById('sumSubtotal').textContent=money(sub);
    document.getElementById('sumDiscount').textContent=money(disc);
    document.getElementById('sumVat').textContent=money(vat);
    document.getElementById('sumTotal').textContent=money(total);
}

EXISTING.forEach(i=>ITEMS.push({
    uid:++ITEMID,
    product_id:i.product_id||'',
    variation_id:i.variation_id||'',
    variation_name:i.variation_name||'',
    item_name:i.item_name||'',
    item_sku:i.item_sku||'',
    description:i.description||'',
    specs_json:i.specs_json||'[]',
    image_path:i.image_path||'',
    quantity:Number(i.quantity)||1,
    unit_price:Number(i.unit_price)||0,
    discount_percent:Number(i.discount_percent)||0,
    vat_rate:Number(i.vat_rate)||21
}));

ecoClientChanged();
ecoRender();

document
    .getElementById('productModal')
    .addEventListener(
        'click',
        e=>{
            if(e.target.id==='productModal'){
                ecoClosePicker();
            }
        }
    );

document
    .getElementById('variationModal')
    .addEventListener(
        'click',
        e=>{
            if(e.target.id==='variationModal'){
                ecoCloseVariationPicker();
            }
        }
    );
</script>

<?php require __DIR__.'/../includes/footer.php';?>