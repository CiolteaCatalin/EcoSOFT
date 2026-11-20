<?php

require __DIR__ . '/../includes/auth.php';
require_login();

require __DIR__ . '/../includes/db.php';

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    exit('Rulează composer install în rădăcina aplicației.');
}

require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$config = require __DIR__ . '/../config/config.php';

$id = (int)($_GET['id'] ?? 0);

$s = $pdo->prepare(
    'SELECT
        o.*,
        c.name AS client_name,
        c.company,
        c.cui,
        c.reg_no,
        c.email,
        c.phone,
        c.address
     FROM offers o
     JOIN clients c ON c.id = o.client_id
     WHERE o.id = ?'
);

$s->execute([$id]);

$o = $s->fetch();

if (!$o) {
    exit('Oferta nu există.');
}

$s = $pdo->prepare(
    'SELECT *
     FROM offer_items
     WHERE offer_id = ?
     ORDER BY sort_order, id'
);

$s->execute([$id]);

$items = $s->fetchAll();


/*
|--------------------------------------------------------------------------
| Logo
|--------------------------------------------------------------------------
*/

$logoFile = __DIR__ . '/../assets/logo';

$logoSrc = '';

if (file_exists($logoFile)) {

    $mime = mime_content_type($logoFile);

    $logoData = base64_encode(
        file_get_contents($logoFile)
    );

    $logoSrc =
        'data:' .
        $mime .
        ';base64,' .
        $logoData;
}


ob_start();

?>
<!doctype html>

<html lang="ro">

<head>

<meta charset="utf-8">

<style>

@page{
    margin:22px 28px;
}

body{
    margin:0;
    font-family:DejaVu Sans,sans-serif;
    color:#172033;
    font-size:9px;
    line-height:1.4;
}

/* HEADER */

.top{
    background:#F6F9FC;
    color:#172033;
    padding:14px 18px;
    border:1px solid #E2EAF2;
    border-radius:10px;
}

.brand{
    margin-bottom:6px;
}

.brand img{
    width:150px;
    height:auto;
    display:block;
}

.offer-title{
    font-size:8.5px;
    font-weight:700;
    color:#174D82;
    letter-spacing:.45px;
    text-transform:uppercase;
}

/* META */

.meta{
    width:100%;
    margin:13px 0 8px;
    border-collapse:collapse;
}

.meta td{
    width:50%;
    vertical-align:top;
    padding:5px 10px 5px 0;
    font-size:8.5px;
    line-height:1.45;
}

.label{
    margin-bottom:2px;
    color:#7A8599;
    font-size:7px;
    font-weight:700;
    letter-spacing:.65px;
    text-transform:uppercase;
}

.meta b{
    color:#172033;
    font-size:9px;
}

/* INTRO */

.intro{
    margin:8px 0 12px;
    color:#556172;
    font-size:8.5px;
    line-height:1.4;
}

/* PRODUCTS */

.products{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

.products th{
    background:#F3F6F9;
    color:#174D82;
    text-align:left;
    padding:6px 7px;
    border-bottom:1px solid #DCE5EE;
    font-size:7px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.25px;
}

.products td{
    padding:6px 7px;
    border-bottom:1px solid #E8EDF2;
    vertical-align:top;
    font-size:8px;
}

.products td:first-child{
    width:43%;
}

.product-name{
    color:#172033;
    font-size:8.5px;
    font-weight:700;
    line-height:1.3;
}

.product-sku{
    margin-top:2px;
    color:#8A94A1;
    font-size:6.8px;
}

.product-desc{
    margin-top:3px;
    color:#667085;
    font-size:7.2px;
    line-height:1.3;
}

.num{
    text-align:right;
    white-space:nowrap;
}

/* TOTALS */

.totalbox{
    margin-left:auto;
    margin-top:11px;
    width:38%;
    border-collapse:collapse;
}

.totalbox td{
    padding:3px 4px;
    font-size:8px;
}

.totalbox td:first-child{
    color:#667085;
}

.totalbox .grand td{
    padding-top:7px;
    border-top:2px solid #EF7F1A;
    color:#172033;
    font-size:12px;
    font-weight:700;
}

/* TERMS */

.terms{
    margin-top:13px;
    padding:8px 10px;
    background:#F7F9FC;
    border:1px solid #E5EBF1;
    border-radius:8px;
    color:#556172;
    font-size:7.5px;
    line-height:1.4;
}

.terms b{
    color:#172033;
}

/* FOOTER */

.footer{
    margin-top:16px;
    padding-top:7px;
    border-top:1px solid #DCE5EE;
    color:#7A8599;
    font-size:6.8px;
    line-height:1.4;
}

</style>

</head>

<body>


<div class="top">

    <div class="brand">

        <?php if ($logoSrc): ?>

            <img
                src="<?= $logoSrc ?>"
                alt="EcoAutoFin"
            >

        <?php else: ?>

            <b>
                <?= e($config['company']['name']) ?>
            </b>

        <?php endif; ?>

    </div>


    <div class="offer-title">
        Ofertă comercială · <?= e($o['offer_number']) ?>
    </div>

</div>


<table class="meta">

<tr>

<td>

    <div class="label">
        Furnizor
    </div>

    <b>
        <?= e($config['company']['name']) ?>
    </b>

    <br>

    <?= e($config['company']['address']) ?>

    <br>

    CUI:
    <?= e($config['company']['cui']) ?>

    <?php if (!empty($config['company']['reg'])): ?>

        <br>

        Nr. Reg.:
        <?= e($config['company']['reg']) ?>

    <?php endif; ?>

    <?php if (!empty($config['company']['email'])): ?>

        <br>

        <?= e($config['company']['email']) ?>

    <?php endif; ?>

</td>


<td>

    <div class="label">
        Client
    </div>

    <b>
        <?= e(
            $o['company']
            ?: $o['client_name']
        ) ?>
    </b>

    <?php if (!empty($o['address'])): ?>

        <br>

        <?= nl2br(
            e($o['address'])
        ) ?>

    <?php endif; ?>

    <?php if (!empty($o['cui'])): ?>

        <br>

        CUI:
        <?= e($o['cui']) ?>

    <?php endif; ?>

    <?php if (!empty($o['reg_no'])): ?>

        <br>

        Nr. Reg.:
        <?= e($o['reg_no']) ?>

    <?php endif; ?>

    <?php if (!empty($o['email'])): ?>

        <br>

        <?= e($o['email']) ?>

    <?php endif; ?>

</td>

</tr>


<tr>

<td>

    <div class="label">
        Data ofertă
    </div>

    <?= e($o['offer_date']) ?>

</td>


<td>

    <div class="label">
        Valabilă până
    </div>

    <?= e(
        $o['valid_until']
        ?? '-'
    ) ?>

</td>

</tr>

</table>


<?php if (!empty($o['intro_text'])): ?>

<div class="intro">

    <?= nl2br(
        e($o['intro_text'])
    ) ?>

</div>

<?php endif; ?>


<table class="products">

<thead>

<tr>

    <th>
        Produs / serviciu
    </th>

    <th class="num">
        Cant.
    </th>

    <th class="num">
        Preț unitar
    </th>

    <th class="num">
        Disc.
    </th>

    <th class="num">
        TVA
    </th>

    <th class="num">
        Total
    </th>

</tr>

</thead>


<tbody>

<?php foreach ($items as $it): ?>

<?php

$description = trim(
    strip_tags(
        html_entity_decode(
            (string)($it['description'] ?? ''),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        )
    )
);

$shortDescription = '';

if ($description !== '') {

    $shortDescription = mb_strimwidth(
        $description,
        0,
        120,
        '...'
    );
}

?>

<tr>

<td>

    <div class="product-name">
        <?= e($it['item_name']) ?>
    </div>


    <?php if (!empty($it['item_sku'])): ?>

        <div class="product-sku">
            Cod:
            <?= e($it['item_sku']) ?>
        </div>

    <?php endif; ?>


    <?php if ($shortDescription !== ''): ?>

        <div class="product-desc">
            <?= e($shortDescription) ?>
        </div>

    <?php endif; ?>

</td>


<td class="num">

    <?= e(
        (string)$it['quantity']
    ) ?>

</td>


<td class="num">

    <?= money(
        (float)$it['unit_price']
    ) ?>

</td>


<td class="num">

    <?= e(
        (string)$it['discount_percent']
    ) ?>%

</td>


<td class="num">

    <?= e(
        (string)$it['vat_rate']
    ) ?>%

</td>


<td class="num">

    <b>
        <?= money(
            (float)$it['line_total']
        ) ?>
    </b>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>


<table class="totalbox">

<tr>

    <td>
        Subtotal
    </td>

    <td class="num">

        <?= money(
            (float)$o['subtotal']
        ) ?>

    </td>

</tr>


<tr>

    <td>
        Discount
    </td>

    <td class="num">

        -<?= money(
            (float)$o['discount_total']
        ) ?>

    </td>

</tr>


<tr>

    <td>
        TVA
    </td>

    <td class="num">

        <?= money(
            (float)$o['vat_total']
        ) ?>

    </td>

</tr>


<tr class="grand">

    <td>
        TOTAL
    </td>

    <td class="num">

        <?= money(
            (float)$o['grand_total']
        ) ?>

    </td>

</tr>

</table>


<div class="terms">

    <?php if (!empty($o['payment_terms'])): ?>

        <b>
            Condiții de plată:
        </b>

        <?= nl2br(
            e($o['payment_terms'])
        ) ?>

    <?php endif; ?>


    <?php if (!empty($o['delivery_terms'])): ?>

        <?php if (!empty($o['payment_terms'])): ?>

            <br>

        <?php endif; ?>

        <b>
            Livrare:
        </b>

        <?= nl2br(
            e($o['delivery_terms'])
        ) ?>

    <?php endif; ?>


    <?php if (!empty($o['notes'])): ?>

        <br>

        <b>
            Observații:
        </b>

        <?= nl2br(
            e($o['notes'])
        ) ?>

    <?php endif; ?>

</div>


<div class="footer">

    <?= e($config['company']['name']) ?>

    ·

    <?= e($config['company']['website']) ?>

    ·

    <?= e($config['company']['phone']) ?>

    ·

    <?= e($config['company']['email']) ?>

</div>


</body>

</html>

<?php

$html = ob_get_clean();

$options = new Options();

$options->set(
    'isRemoteEnabled',
    true
);

$options->set(
    'isHtml5ParserEnabled',
    true
);

$dompdf = new Dompdf(
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
    $o['offer_number'] . '.pdf',
    [
        'Attachment' => false
    ]
);