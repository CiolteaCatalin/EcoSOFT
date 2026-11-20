<?php
require __DIR__.'/../includes/auth.php';require_login();require __DIR__.'/../includes/db.php';
if(!file_exists(__DIR__.'/../vendor/autoload.php'))exit('Rulează composer install în rădăcina aplicației.');
require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$config=require __DIR__.'/../config/config.php';

$id=(int)($_GET['id']??0);

$s=$pdo->prepare('
SELECT
    o.*,
    c.name AS client_name,
    c.company,
    c.cui,
    c.reg_no,
    c.email,
    c.phone,
    c.address,
    c.group_id,
    cg.name AS group_name,
    cg.discount_percent AS group_discount,
    cg.color AS group_color
FROM offers o
JOIN clients c ON c.id=o.client_id
LEFT JOIN client_groups cg ON cg.id=c.group_id
WHERE o.id=?
');

$s->execute([$id]);
$o=$s->fetch();

if(!$o)exit('Oferta nu există.');

$s=$pdo->prepare('SELECT * FROM offer_items WHERE offer_id=? ORDER BY sort_order,id');
$s->execute([$id]);
$items=$s->fetchAll();

$showDescription=!empty($o['show_description']);
$showSpecs=!empty($o['show_specs']);

$logoSrc='';
$logoCandidates=[
    __DIR__.'/../assets/logoeco',
    __DIR__.'/../assets/logoeco.png'
];

foreach($logoCandidates as $logoFile){
    if(file_exists($logoFile)){
        $mime=mime_content_type($logoFile)?:'image/png';
        $logoSrc='data:'.$mime.';base64,'.base64_encode(file_get_contents($logoFile));
        break;
    }
}

$groupColor=$o['group_color']??'#64748B';
$groupName=$o['group_name']??'';
$groupDiscount=(float)($o['group_discount']??0);

ob_start();
?>
<!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">

<style>
@page{margin:22px 28px}body{margin:0;font-family:DejaVu Sans,sans-serif;color:#172033;font-size:9px;line-height:1.4}.top{background:#F6F9FC;color:#172033;padding:14px 18px;border:1px solid #E2EAF2;border-radius:10px}.brand{margin-bottom:6px}.brand img{width:150px;height:auto;display:block}.offer-title{font-size:8.5px;font-weight:700;color:#174D82;letter-spacing:.45px;text-transform:uppercase}.meta{width:100%;margin:13px 0 8px;border-collapse:collapse}.meta td{width:50%;vertical-align:top;padding:5px 10px 5px 0;font-size:8.5px;line-height:1.45}.label{margin-bottom:2px;color:#7A8599;font-size:7px;font-weight:700;letter-spacing:.65px;text-transform:uppercase}.meta b{color:#172033;font-size:9px}.client-group{margin-top:5px}.client-group-badge{display:inline-block;padding:3px 7px;border-radius:10px;color:#fff;font-size:6.5px;font-weight:700}.client-group-discount{display:inline-block;margin-left:5px;color:#667085;font-size:7px}.intro{margin:8px 0 12px;color:#556172;font-size:8.5px;line-height:1.4}.products{width:100%;border-collapse:collapse;margin-top:10px}.products th{background:#F3F6F9;color:#174D82;text-align:left;padding:6px 7px;border-bottom:1px solid #DCE5EE;font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:.25px}.products td{padding:6px 7px;border-bottom:1px solid #E8EDF2;vertical-align:top;font-size:8px}.products td:first-child{width:43%}.product-name{color:#172033;font-size:8.5px;font-weight:700;line-height:1.3}.product-variation{display:block;margin-top:2px;color:#1769aa;font-size:7.2px;font-weight:700;line-height:1.3}.product-code{display:block;margin-top:2px;font-size:6.8px;color:#8A94A1}.product-description{margin-top:4px;color:#667085;font-size:7.3px;line-height:1.35}.product-specs{margin-top:5px;padding-top:5px;border-top:1px solid #EEF1F4;color:#667085;font-size:7px;line-height:1.35}.product-specs .spec-row{display:block;margin-bottom:2px}.product-specs .spec-row b{color:#465268}.num{text-align:right;white-space:nowrap}.totalbox{margin-left:auto;margin-top:11px;width:38%;border-collapse:collapse}.totalbox td{padding:3px 4px;font-size:8px}.totalbox td:first-child{color:#667085}.totalbox .grand td{padding-top:7px;border-top:2px solid #EF7F1A;color:#172033;font-size:12px;font-weight:700}.terms{margin-top:13px;padding:8px 10px;background:#F7F9FC;border:1px solid #E5EBF1;border-radius:8px;color:#556172;font-size:7.5px;line-height:1.4}.terms b{color:#172033}.footer{margin-top:16px;padding-top:7px;border-top:1px solid #DCE5EE;color:#7A8599;font-size:6.8px;line-height:1.4}

/* EcoAutoFin document design v5 */
@page{margin:28px 32px 62px}body{color:#1c3959;font-size:9px;line-height:1.55}.top{background:#f0f6fe;border:0;border-bottom:3px solid #2b83d5;border-radius:0;padding:17px 20px}.offer-title{font-size:13px;color:#175b9f;letter-spacing:.15px}.brand img{width:165px}.meta{margin:20px 0 16px}.meta td{padding:11px;background:#f6f9fd;border:1px solid #e5edf7}.label{color:#6686a9}.products{margin-top:18px}.products thead{display:table-header-group}.products tr{page-break-inside:avoid}.products th{background:#175b9f;color:#fff;padding:10px 8px;border-bottom:0}.products td{padding:10px 8px;border-bottom:1px solid #e3edf7}.products tbody tr:nth-child(even) td{background:#f7faff}.product-name{color:#1c3959}.totalbox{margin-top:20px;width:45%}.totalbox td{padding:7px}.totalbox .grand td{border-top:2px solid #2b83d5;background:#e8f2ff;color:#175b9f;font-size:12px;padding:11px 7px}.terms{background:#f3f7fc;border:0;border-left:3px solid #2b83d5;border-radius:0;padding:12px 15px}.footer{position:fixed;bottom:-42px;left:0;right:0;margin:0;padding:10px 0;border-top:1px solid #d9e6f5;color:#6882a0;font-size:8px}.eco-promo{margin-top:18px;padding:12px 15px;background:#f0f6fe;color:#52779c;font-size:8px}.eco-promo b{color:#175b9f}
</style>

</head>

<body>

<div class="top">

<div class="brand">

<?php if($logoSrc):?>

<img src="<?=$logoSrc?>" alt="EcoAutoFin">

<?php else:?>

<b><?=e($config['company']['name'])?></b>

<?php endif;?>

</div>

<div class="offer-title">
Ofertă comercială · <?=e($o['offer_number'])?>
</div>

</div>


<table class="meta">

<tr>

<td>

<div class="label">
Furnizor
</div>

<b>
<?=e($config['company']['name'])?>
</b>

<br>

<?=e($config['company']['address'])?>

<br>

CUI:
<?=e($config['company']['cui'])?>

<?php if(!empty($config['company']['reg'])):?>

<br>

Nr. Reg. Com.:
<?=e($config['company']['reg'])?>

<?php endif;?>

<?php if(!empty($config['company']['email'])):?>

<br>

<?=e($config['company']['email'])?>

<?php endif;?>

</td>


<td>

<div class="label">
Client
</div>

<b>
<?=e($o['company']?:$o['client_name'])?>
</b>

<?php if(!empty($groupName)):?>

<div class="client-group">

<span
class="client-group-badge"
style="background:<?=e($groupColor)?>"
>
<?=e($groupName)?>
</span>

<span class="client-group-discount">
Discount grupă:
<?=e(rtrim(rtrim(number_format($groupDiscount,2,'.',''),'0'),'.'))?>%
</span>

</div>

<?php endif;?>

<?php if(!empty($o['address'])):?>

<br>

<?=nl2br(e($o['address']))?>

<?php endif;?>

<?php if(!empty($o['cui'])):?>

<br>

CUI:
<?=e($o['cui'])?>

<?php endif;?>

<?php if(!empty($o['reg_no'])):?>

<br>

Nr. Reg. Com.:
<?=e($o['reg_no'])?>

<?php endif;?>

<?php if(!empty($o['email'])):?>

<br>

<?=e($o['email'])?>

<?php endif;?>

</td>

</tr>


<tr>

<td>

<div class="label">
Data ofertă
</div>

<?=e($o['offer_date'])?>

</td>


<td>

<div class="label">
Valabilă până
</div>

<?=e($o['valid_until']??'-')?>

</td>

</tr>

</table>


<?php if(!empty($o['intro_text'])):?>

<div class="intro">
<?=nl2br(e($o['intro_text']))?>
</div>

<?php endif;?>


<table class="products">

<thead>

<tr>
<th>Produs / serviciu</th>
<th class="num">Cant.</th>
<th class="num">Preț unitar</th>
<th class="num">Disc.</th>
<th class="num">TVA</th>
<th class="num">Total</th>
</tr>

</thead>


<tbody>

<?php foreach($items as $it):?>

<?php
$specs=[];

if($showSpecs){
    $specs=json_decode(
        $it['specs_json']?:'[]',
        true
    )?:[];
}
?>

<tr>

<td>

<div class="product-name">
<?=e($it['item_name'])?>
</div>

<?php if(!empty($it['variation_name'])):?>

<div class="product-variation">
<?=e($it['variation_name'])?>
</div>

<?php endif;?>


<?php if(!empty($it['item_sku'])):?>

<span class="product-code">
Cod: <?=e($it['item_sku'])?>
</span>

<?php endif;?>


<?php if($showDescription&&!empty($it['description'])):?>

<div class="product-description">
<?=nl2br(e($it['description']))?>
</div>

<?php endif;?>


<?php if($showSpecs&&!empty($specs)):?>

<div class="product-specs">

<?php foreach($specs as $k=>$v):?>

<span class="spec-row">
<b><?=e($k)?>:</b> <?=e($v)?>
</span>

<?php endforeach;?>

</div>

<?php endif;?>

</td>


<td class="num">
<?=e((string)$it['quantity'])?>
</td>

<td class="num">
<?=money((float)$it['unit_price'])?>
</td>

<td class="num">

<?php if((float)$it['discount_percent']>0):?>

<?=e(
    rtrim(
        rtrim(
            number_format(
                (float)$it['discount_percent'],
                2,
                '.',
                ''
            ),
            '0'
        ),
        '.'
    )
)?>%

<?php else:?>

—

<?php endif;?>

</td>

<td class="num">
<?=e((string)$it['vat_rate'])?>%
</td>

<td class="num">
<b><?=money((float)$it['line_total'])?></b>
</td>

</tr>

<?php endforeach;?>

</tbody>

</table>


<table class="totalbox">

<tr>
<td>Subtotal</td>
<td class="num">
<?=money((float)$o['subtotal'])?>
</td>
</tr>


<?php if((float)$o['discount_total']>0):?>

<tr>
<td>Discount</td>
<td class="num">
-<?=money((float)$o['discount_total'])?>
</td>
</tr>

<?php endif;?>


<tr>
<td>TVA</td>
<td class="num">
<?=money((float)$o['vat_total'])?>
</td>
</tr>


<tr class="grand">
<td>TOTAL</td>
<td class="num">
<?=money((float)$o['grand_total'])?>
</td>
</tr>

</table>


<div class="terms">

<?php if(!empty($o['payment_terms'])):?>

<b>
Condiții de plată:
</b>

<?=nl2br(e($o['payment_terms']))?>

<?php endif;?>


<?php if(!empty($o['delivery_terms'])):?>

<?php if(!empty($o['payment_terms'])):?>
<br>
<?php endif;?>

<b>
Livrare:
</b>

<?=nl2br(e($o['delivery_terms']))?>

<?php endif;?>


<?php if(!empty($o['notes'])):?>

<br>

<b>
Observații:
</b>

<?=nl2br(e($o['notes']))?>

<?php endif;?>

</div>


<div class="eco-promo"><b>EcoAutoFin · ecoautofin.shop</b><br>Descoperă gama noastră de produse și soluții online.</div>
<div class="footer">

<?=e($config['company']['name'])?>

·

<?=e($config['company']['website'])?>

·

<?=e($config['company']['phone'])?>

·

<?=e($config['company']['email'])?>

</div>


</body>
</html>

<?php

$html=ob_get_clean();

$options=new Options();

$options->set(
    'isRemoteEnabled',
    true
);

$options->set(
    'isHtml5ParserEnabled',
    true
);

$dompdf=new Dompdf(
    $options
);

$dompdf->loadHtml(
    $html,
    'UTF-8'
);

$dompdf->setPaper(
    'A4',
    'portrait'
);

$dompdf->render();

$dompdf->stream(
    $o['offer_number'].'.pdf',
    [
        'Attachment'=>false
    ]
);