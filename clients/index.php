<?php
require __DIR__.'/../includes/auth.php';
require_login();

require __DIR__.'/../includes/db.php';

$q=trim($_GET['q']??'');
$groupFilter=(int)($_GET['group_id']??0);
$sort=$_GET['sort']??'recent';

$groups=$pdo->query('
    SELECT id,name,color,discount_percent,active
    FROM client_groups
    ORDER BY active DESC,name
')->fetchAll();

$where=[];
$params=[];

if($q!==''){
    $where[]='(
        c.name LIKE ?
        OR c.company LIKE ?
        OR c.cui LIKE ?
        OR c.email LIKE ?
        OR c.phone LIKE ?
    )';

    $like='%'.$q.'%';

    array_push(
        $params,
        $like,
        $like,
        $like,
        $like,
        $like
    );
}

if($groupFilter>0){
    $where[]='c.group_id=?';
    $params[]=$groupFilter;
}

$whereSql=$where
    ?'WHERE '.implode(' AND ',$where)
    :'';

$orderSql='c.id DESC';

if($sort==='group'){
    $orderSql='cg.name ASC,c.company ASC,c.name ASC';
}elseif($sort==='name'){
    $orderSql='c.name ASC';
}elseif($sort==='company'){
    $orderSql='c.company ASC,c.name ASC';
}

$sql='
    SELECT
        c.*,
        cg.name AS group_name,
        cg.color AS group_color,
        cg.discount_percent AS group_discount,
        cg.active AS group_active
    FROM clients c
    LEFT JOIN client_groups cg ON cg.id=c.group_id
    '.$whereSql.'
    ORDER BY '.$orderSql;

$stmt=$pdo->prepare($sql);
$stmt->execute($params);
$clients=$stmt->fetchAll();

$totalClients=count($clients);

require __DIR__.'/../includes/header.php';
?>

<style>
.clients-page{display:grid;gap:18px}.clients-toolbar{display:flex;align-items:flex-end;justify-content:space-between;gap:18px}.clients-toolbar__copy h1{margin:3px 0 5px;font-size:27px;line-height:1.1;letter-spacing:-.03em;color:#172033}.clients-toolbar__copy p{margin:0;color:#7b8593;font-size:12px}.clients-count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:23px;padding:0 8px;margin-left:6px;border-radius:999px;background:#edf3f8;color:#607083;font-size:10px;font-weight:700;vertical-align:middle}.clients-search{display:grid;grid-template-columns:minmax(0,1fr) 180px 150px auto auto;align-items:center;gap:8px;padding:9px;background:#fff;border:1px solid #e3e9ef;border-radius:12px}.clients-search input,.clients-search select{margin:0}.clients-search input{min-width:0;border:0;background:transparent;box-shadow:none;padding:8px 10px}.clients-search input:focus{border:0;box-shadow:none}.clients-search select{height:38px;padding:0 10px;border:1px solid #e1e7ed;border-radius:9px;background:#fff;color:#465268;font-size:10px}.clients-panel{background:#fff;border:1px solid #e3e9ef;border-radius:14px;overflow:hidden}.clients-table{width:100%;border-collapse:collapse}.clients-table th{background:#fafbfd;padding:11px 14px;border-bottom:1px solid #edf1f5;color:#7b8593;font-size:9px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.clients-table td{padding:12px 14px;border-bottom:1px solid #f0f3f6;font-size:12px;vertical-align:middle}.clients-table tbody tr:last-child td{border-bottom:0}.clients-table tbody tr:hover{background:#fbfcfe}.client-cell{display:flex;align-items:center;gap:10px;min-width:180px}.client-avatar{width:34px;height:34px;flex:0 0 34px;border-radius:10px;display:grid;place-items:center;background:#eef4fa;color:#1769aa;font-size:11px;font-weight:800}.client-main{min-width:0}.client-name{display:block;color:#172033;font-size:12px;font-weight:700}.client-sub{display:block;margin-top:2px;color:#8993a0;font-size:9px}.client-muted{color:#9aa4b1}.client-group-badge{display:inline-flex;align-items:center;justify-content:center;min-height:22px;padding:0 8px;border-radius:999px;color:#fff;font-size:8px;font-weight:800;white-space:nowrap}.client-group-discount{display:block;margin-top:3px;color:#7b8593;font-size:8px}.client-edit{display:inline-flex;align-items:center;justify-content:center;min-height:28px;padding:5px 10px;border:1px solid #d5e0e9;border-radius:7px;background:#fff;color:#1769aa;font-size:10px;font-weight:700;transition:.15s ease}.client-edit:hover{background:#f3f7fb;border-color:#bfcfdd;color:#0f5b96}.clients-empty{padding:36px 20px;text-align:center;color:#7b8593;font-size:12px}@media(max-width:1050px){.clients-search{grid-template-columns:1fr 160px 140px auto}}@media(max-width:900px){.clients-toolbar{flex-direction:column;align-items:flex-start}.clients-toolbar .btn{width:100%}.clients-search{grid-template-columns:1fr 1fr}.clients-search .btn{width:100%}}@media(max-width:760px){.clients-search{grid-template-columns:1fr}.clients-panel{overflow:auto}.clients-table{min-width:900px}}
</style>

<div class="clients-page">

<div class="clients-toolbar">

<div class="clients-toolbar__copy">

<span class="eyebrow">
CRM
</span>

<h1>
Clienți
<span class="clients-count">
<?= (int)$totalClients ?>
</span>
</h1>

<p>
Persoane și companii folosite în oferte.
</p>

</div>

<a
class="btn btn-primary"
href="/clients/edit.php"
>
+ Client nou
</a>

</div>


<form
class="clients-search"
method="get"
>

<input
type="search"
name="q"
value="<?=e($q)?>"
placeholder="Caută după nume, companie, CUI, email sau telefon..."
>

<select name="group_id">
<option value="0">Toate grupele</option>

<?php foreach($groups as $g):?>

<option
value="<?=(int)$g['id']?>"
<?=$groupFilter===(int)$g['id']?'selected':''?>
>
<?=e($g['name'])?>
</option>

<?php endforeach;?>

</select>

<select name="sort">

<option value="recent" <?=$sort==='recent'?'selected':''?>>
Cei mai noi
</option>

<option value="group" <?=$sort==='group'?'selected':''?>>
După grupă
</option>

<option value="name" <?=$sort==='name'?'selected':''?>>
După nume
</option>

<option value="company" <?=$sort==='company'?'selected':''?>>
După companie
</option>

</select>

<button
type="submit"
class="btn"
>
Filtrează
</button>

<?php if($q!==''||$groupFilter>0||$sort!=='recent'):?>

<a
href="/clients/"
class="btn"
>
Reset
</a>

<?php endif;?>

</form>


<div class="clients-panel">

<?php if($clients):?>

<div class="table-wrap">

<table class="clients-table">

<thead>

<tr>
<th>Client</th>
<th>Companie</th>
<th>Grupă</th>
<th>CUI</th>
<th>Email</th>
<th>Telefon</th>
<th></th>
</tr>

</thead>

<tbody>

<?php foreach($clients as $c):?>

<?php
$displayName=trim((string)($c['name']??''));
$company=trim((string)($c['company']??''));

$initial=$displayName!==''
    ?mb_strtoupper(
        mb_substr(
            $displayName,
            0,
            1
        )
    )
    :'C';
?>

<tr>

<td>

<div class="client-cell">

<div class="client-avatar">
<?=e($initial)?>
</div>

<div class="client-main">

<span class="client-name">
<?=e(
    $displayName!==''
        ?$displayName
        :'Client fără nume'
)?>
</span>

<?php if(!empty($c['reg_no'])):?>

<span class="client-sub">
<?=e($c['reg_no'])?>
</span>

<?php endif;?>

</div>

</div>

</td>


<td>

<?= $company!==''
    ?e($company)
    :'<span class="client-muted">—</span>'
?>

</td>


<td>

<?php if(!empty($c['group_name'])):?>

<span
class="client-group-badge"
style="background:<?=e($c['group_color']?:'#64748B')?>"
>
<?=e($c['group_name'])?>
</span>

<span class="client-group-discount">
<?=e((string)$c['group_discount'])?>% discount
</span>

<?php else:?>

<span class="client-muted">
Fără grupă
</span>

<?php endif;?>

</td>


<td>

<?=!empty($c['cui'])
    ?e($c['cui'])
    :'<span class="client-muted">—</span>'
?>

</td>


<td>

<?=!empty($c['email'])
    ?e($c['email'])
    :'<span class="client-muted">—</span>'
?>

</td>


<td>

<?=!empty($c['phone'])
    ?e($c['phone'])
    :'<span class="client-muted">—</span>'
?>

</td>


<td>

<a
class="client-edit"
href="/clients/edit.php?id=<?=(int)$c['id']?>"
>
Editează
</a>

</td>

</tr>

<?php endforeach;?>

</tbody>

</table>

</div>

<?php else:?>

<div class="clients-empty">
Nu există clienți pentru filtrele selectate.
</div>

<?php endif;?>

</div>

</div>

<?php require __DIR__.'/../includes/footer.php';?>