<?php
require __DIR__.'/../includes/auth.php';require_login();require __DIR__.'/../includes/db.php';

$id=(int)($_GET['id']??0);

$groups=$pdo->query('SELECT id,name,discount_percent,color FROM client_groups WHERE active=1 ORDER BY name')->fetchAll();

$c=[
    'name'=>'',
    'company'=>'',
    'group_id'=>null,
    'cui'=>'',
    'reg_no'=>'',
    'email'=>'',
    'phone'=>'',
    'address'=>'',
    'notes'=>''
];

if($id){
    $s=$pdo->prepare('SELECT * FROM clients WHERE id=?');
    $s->execute([$id]);
    $c=$s->fetch()?:$c;
}

if($_SERVER['REQUEST_METHOD']==='POST'){

    csrf_check();

    $groupId=(int)($_POST['group_id']??0);
    if(!$groupId)$groupId=null;

    $d=[
        trim($_POST['name']??''),
        trim($_POST['company']??''),
        $groupId,
        trim($_POST['cui']??''),
        trim($_POST['reg_no']??''),
        trim($_POST['email']??''),
        trim($_POST['phone']??''),
        trim($_POST['address']??''),
        trim($_POST['notes']??'')
    ];

    if(!$d[0]){

        $error='Numele este obligatoriu.';

    }else{

        if($id){

            $s=$pdo->prepare('UPDATE clients SET name=?,company=?,group_id=?,cui=?,reg_no=?,email=?,phone=?,address=?,notes=? WHERE id=?');
            $s->execute([...$d,$id]);

        }else{

            $s=$pdo->prepare('INSERT INTO clients(name,company,group_id,cui,reg_no,email,phone,address,notes) VALUES(?,?,?,?,?,?,?,?,?)');
            $s->execute($d);

            $id=(int)$pdo->lastInsertId();
        }

        flash('success','Client salvat.');
        redirect('/clients/edit.php?id='.$id);
    }
}

require __DIR__.'/../includes/header.php';
?>

<style>
.client-edit-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:18px}.client-edit-head h1{margin:3px 0 0;font-size:27px;letter-spacing:-.03em}.client-group-preview{display:flex;align-items:center;gap:8px;margin-top:8px}.client-group-badge{display:inline-flex;align-items:center;min-height:22px;padding:0 8px;border-radius:999px;color:#fff;font-size:8px;font-weight:800;white-space:nowrap}.client-group-discount{font-size:9px;color:#7b8593}.client-group-help{display:block;margin-top:5px;color:#8a94a1;font-size:8px}.client-form-actions{display:flex;align-items:center;gap:8px;margin-top:8px}@media(max-width:760px){.client-edit-head{flex-direction:column;align-items:flex-start}.client-form-actions{flex-direction:column;align-items:stretch}.client-form-actions .btn{width:100%}}
</style>

<div class="client-edit-head">

<div>
<span class="eyebrow">CRM</span>
<h1><?=$id?'Editează client':'Client nou'?></h1>

<?php
$currentGroup=null;

if(!empty($c['group_id'])){
    foreach($groups as $g){
        if((int)$g['id']===(int)$c['group_id']){
            $currentGroup=$g;
            break;
        }
    }
}
?>

<?php if($currentGroup):?>

<div class="client-group-preview">
<span class="client-group-badge" style="background:<?=e($currentGroup['color'])?>">
<?=e($currentGroup['name'])?>
</span>

<span class="client-group-discount">
Discount implicit <?=e((string)$currentGroup['discount_percent'])?>%
</span>
</div>

<?php endif;?>

</div>

<a class="btn" href="/clients/">← Înapoi</a>

</div>

<?php if(!empty($error)):?>
<div class="alert error"><?=e($error)?></div>
<?php endif;?>

<form method="post" class="panel">

<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

<div class="grid-2">

<label>
Persoană de contact
<input name="name" required value="<?=e($c['name'])?>">
</label>

<label>
Companie
<input name="company" value="<?=e($c['company'])?>">
</label>

<label>
Grupă client

<select name="group_id" id="clientGroupSelect" onchange="updateGroupPreview()">

<option value="">
Fără grupă
</option>

<?php foreach($groups as $g):?>

<option
value="<?=(int)$g['id']?>"
data-name="<?=e($g['name'])?>"
data-discount="<?=e((string)$g['discount_percent'])?>"
data-color="<?=e($g['color'])?>"
<?=(int)($c['group_id']??0)===(int)$g['id']?'selected':''?>
>
<?=e($g['name'])?> — <?=e((string)$g['discount_percent'])?>%
</option>

<?php endforeach;?>

</select>

<span class="client-group-help">
Discountul grupei va putea fi aplicat automat în ofertele clientului.
</span>

<div id="groupPreview" style="margin-top:7px;"></div>

</label>

<label>
CUI
<input name="cui" value="<?=e($c['cui'])?>">
</label>

<label>
Nr. Reg. Com.
<input name="reg_no" value="<?=e($c['reg_no'])?>">
</label>

<label>
Email
<input type="email" name="email" value="<?=e($c['email'])?>">
</label>

<label>
Telefon
<input name="phone" value="<?=e($c['phone'])?>">
</label>

</div>

<label>
Adresă
<textarea name="address" rows="3"><?=e($c['address'])?></textarea>
</label>

<label>
Observații
<textarea name="notes" rows="4"><?=e($c['notes'])?></textarea>
</label>

<div class="client-form-actions">

<button class="btn btn-primary">
Salvează clientul
</button>

<?php if($id):?>

<a class="btn" href="/offers/edit.php">
Creează ofertă
</a>

<?php endif;?>

</div>

</form>

<script>
function updateGroupPreview(){

    const select=document.getElementById('clientGroupSelect');
    const preview=document.getElementById('groupPreview');
    const option=select.options[select.selectedIndex];

    if(!option||!option.value){
        preview.innerHTML='';
        return;
    }

    const name=option.dataset.name||'';
    const discount=option.dataset.discount||'0';
    const color=option.dataset.color||'#64748B';

    preview.innerHTML=
        '<span class="client-group-badge" style="background:'+color+'">'+
        name+
        '</span> '+
        '<span class="client-group-discount">Discount implicit '+discount+'%</span>';
}

updateGroupPreview();
</script>

<?php require __DIR__.'/../includes/footer.php';?>