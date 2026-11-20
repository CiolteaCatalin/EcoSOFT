<?php
require __DIR__.'/../includes/auth.php';require_login();require __DIR__.'/../includes/db.php';

$groups=$pdo->query('
SELECT
    cg.*,
    COUNT(c.id) AS clients_count
FROM client_groups cg
LEFT JOIN clients c ON c.group_id=cg.id
GROUP BY cg.id
ORDER BY cg.active DESC,cg.name
')->fetchAll();

require __DIR__.'/../includes/header.php';
?>

<style>
.groups-page{display:grid;gap:16px}.groups-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px}.groups-head h1{margin:3px 0 4px;font-size:27px;line-height:1.1;letter-spacing:-.03em;color:#172033}.groups-head p{margin:0;color:#7b8593;font-size:12px}.groups-list{background:#fff;border:1px solid #e3e9ef;border-radius:14px;overflow:hidden}.groups-header,.group-row{display:grid;grid-template-columns:minmax(160px,1.4fr) 120px 120px 100px auto;gap:14px;align-items:center;padding:11px 14px}.groups-header{background:#fafbfd;border-bottom:1px solid #edf1f5;color:#7b8593;font-size:8px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.group-row{border-bottom:1px solid #f0f3f6}.group-row:last-child{border-bottom:0}.group-row:hover{background:#fbfcfe}.group-name{display:flex;align-items:center;gap:8px;min-width:0}.group-badge{display:inline-flex;align-items:center;justify-content:center;min-height:24px;padding:0 9px;border-radius:999px;color:#fff;font-size:8px;font-weight:800;white-space:nowrap}.group-discount{font-size:12px;font-weight:800;color:#172033}.group-count{font-size:11px;color:#667085}.group-status{display:inline-flex;align-items:center;justify-content:center;min-height:21px;padding:0 7px;border-radius:999px;font-size:7px;font-weight:800;text-transform:uppercase}.group-status.active{background:#e8f7ee;color:#27734a}.group-status.inactive{background:#fdeaea;color:#a13c3c}.group-actions{display:flex;justify-content:flex-end}.group-edit{display:inline-flex;align-items:center;justify-content:center;min-height:29px;padding:5px 10px;border:1px solid #1769aa;border-radius:7px;background:#1769aa;color:#fff;font-size:10px;font-weight:700}.group-edit:hover{background:#0f5b96;color:#fff}.groups-empty{padding:34px;text-align:center;color:#7b8593;font-size:12px}@media(max-width:760px){.groups-head{flex-direction:column;align-items:flex-start}.groups-head .btn{width:100%}.groups-header{display:none}.group-row{grid-template-columns:1fr auto;gap:8px}.group-row>div:nth-child(2),.group-row>div:nth-child(3),.group-row>div:nth-child(4){font-size:9px}}
</style>

<div class="groups-page">

<div class="groups-head">
<div>
<span class="eyebrow">CRM</span>
<h1>Grupe clienți</h1>
<p>Segmente de clienți cu discount implicit configurabil.</p>
</div>

<a class="btn btn-primary" href="/client-groups/edit.php">
+ Grupă nouă
</a>
</div>

<div class="groups-list">

<?php if($groups):?>

<div class="groups-header">
<span>Grupă</span>
<span>Discount</span>
<span>Clienți</span>
<span>Status</span>
<span>Acțiuni</span>
</div>

<?php foreach($groups as $g):?>

<div class="group-row">

<div class="group-name">
<span class="group-badge" style="background:<?=e($g['color'])?>">
<?=e($g['name'])?>
</span>
</div>

<div class="group-discount">
<?=e((string)$g['discount_percent'])?>%
</div>

<div class="group-count">
<?=(int)$g['clients_count']?> clienți
</div>

<div>
<span class="group-status <?=!empty($g['active'])?'active':'inactive'?>">
<?=!empty($g['active'])?'Activă':'Inactivă'?>
</span>
</div>

<div class="group-actions">
<a class="group-edit" href="/client-groups/edit.php?id=<?=(int)$g['id']?>">
Editează
</a>
</div>

</div>

<?php endforeach;?>

<?php else:?>

<div class="groups-empty">
Nu există încă grupe de clienți.
</div>

<?php endif;?>

</div>

</div>

<?php require __DIR__.'/../includes/footer.php';?>