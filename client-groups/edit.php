<?php
require __DIR__.'/../includes/auth.php';require_login();require __DIR__.'/../includes/db.php';

$id=(int)($_GET['id']??0);

$group=[
    'name'=>'',
    'discount_percent'=>'0.00',
    'color'=>'#1769AA',
    'active'=>1
];

if($id){
    $s=$pdo->prepare('SELECT * FROM client_groups WHERE id=?');
    $s->execute([$id]);
    $group=$s->fetch()?:$group;
}

if($_SERVER['REQUEST_METHOD']==='POST'){

    csrf_check();

    try{

        if(
            $id &&
            isset($_POST['delete_group']) &&
            $_POST['delete_group']==='1'
        ){

            $pdo->beginTransaction();

            $pdo
                ->prepare('UPDATE clients SET group_id=NULL WHERE group_id=?')
                ->execute([$id]);

            $pdo
                ->prepare('DELETE FROM client_groups WHERE id=?')
                ->execute([$id]);

            $pdo->commit();

            flash('success','Grupa a fost ștearsă.');

            redirect('/client-groups/');
        }

        $name=trim($_POST['name']??'');
        $discount=min(100,max(0,(float)($_POST['discount_percent']??0)));
        $color=trim($_POST['color']??'#1769AA');
        $active=isset($_POST['active'])?1:0;

        if($name===''){
            throw new RuntimeException('Numele grupei este obligatoriu.');
        }

        if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$color)){
            $color='#1769AA';
        }

        if($id){

            $s=$pdo->prepare('UPDATE client_groups SET name=?,discount_percent=?,color=?,active=? WHERE id=?');
            $s->execute([$name,$discount,$color,$active,$id]);

        }else{

            $s=$pdo->prepare('INSERT INTO client_groups(name,discount_percent,color,active) VALUES(?,?,?,?)');
            $s->execute([$name,$discount,$color,$active]);

            $id=(int)$pdo->lastInsertId();
        }

        flash('success','Grupa a fost salvată.');

        redirect('/client-groups/edit.php?id='.$id);

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
.group-edit-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:18px}.group-edit-head h1{margin:3px 0 0;font-size:27px;letter-spacing:-.03em}.group-preview{display:flex;align-items:center;gap:9px;margin-top:10px}.group-preview-badge{display:inline-flex;align-items:center;justify-content:center;min-height:26px;padding:0 10px;border-radius:999px;color:#fff;font-size:9px;font-weight:800}.group-preview-discount{font-size:10px;color:#7b8593}.color-field{display:flex;align-items:center;gap:8px}.color-field input[type=color]{width:48px;height:42px;padding:3px;cursor:pointer}.color-code{font-size:10px;color:#667085}.group-delete{margin-top:18px;padding-top:18px;border-top:1px solid #edf1f5}.group-delete-box{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px;background:#fff8f8;border:1px solid #f2d7d7;border-radius:11px}.group-delete-copy strong{display:block;color:#8f3030;font-size:11px}.group-delete-copy small{display:block;margin-top:4px;color:#9a6a6a;font-size:9px}.btn-danger{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:8px 12px;border:1px solid #c93c3c;border-radius:8px;background:#fff;color:#b42f2f;font-size:11px;font-weight:700;cursor:pointer}.btn-danger:hover{background:#b42f2f;color:#fff}@media(max-width:760px){.group-edit-head{flex-direction:column;align-items:flex-start}.group-delete-box{flex-direction:column;align-items:flex-start}.btn-danger{width:100%}}
</style>

<div class="group-edit-head">

<div>
<span class="eyebrow">CRM</span>
<h1><?=$id?'Editează grupa':'Grupă nouă'?></h1>

<div class="group-preview">
<span
id="groupPreviewBadge"
class="group-preview-badge"
style="background:<?=e($group['color'])?>"
>
<?=e($group['name']?:'Grupă')?>
</span>

<span id="groupPreviewDiscount" class="group-preview-discount">
Discount <?=e((string)$group['discount_percent'])?>%
</span>
</div>

</div>

<a class="btn" href="/client-groups/">
← Înapoi
</a>

</div>

<?php if(!empty($error)):?>
<div class="alert error"><?=e($error)?></div>
<?php endif;?>

<form method="post" class="panel">

<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">

<div class="grid-2">

<label>
Nume grupă
<input
id="groupName"
name="name"
required
value="<?=e($group['name'])?>"
oninput="updateGroupPreview()"
>
</label>

<label>
Discount implicit %

<input
id="groupDiscount"
type="number"
name="discount_percent"
min="0"
max="100"
step="0.01"
value="<?=e((string)$group['discount_percent'])?>"
oninput="updateGroupPreview()"
>
</label>

<label>
Culoare badge

<div class="color-field">

<input
id="groupColor"
type="color"
name="color"
value="<?=e($group['color'])?>"
oninput="updateGroupPreview()"
>

<span id="colorCode" class="color-code">
<?=e($group['color'])?>
</span>

</div>
</label>

<label class="check">

<input
type="checkbox"
name="active"
<?=!empty($group['active'])?'checked':''?>
>

Grupă activă

</label>

</div>

<button class="btn btn-primary">
Salvează grupa
</button>

<?php if($id):?>

<div class="group-delete">

<div class="group-delete-box">

<div class="group-delete-copy">
<strong>Ștergere grupă</strong>
<small>Clienții din această grupă vor rămâne fără grupă.</small>
</div>

<button
type="submit"
name="delete_group"
value="1"
class="btn-danger"
onclick="return confirm('Sigur vrei să ștergi această grupă?')"
>
Șterge grupa
</button>

</div>

</div>

<?php endif;?>

</form>

<script>
function updateGroupPreview(){
    const name=document.getElementById('groupName').value.trim()||'Grupă';
    const discount=document.getElementById('groupDiscount').value||'0';
    const color=document.getElementById('groupColor').value||'#1769AA';

    document.getElementById('groupPreviewBadge').textContent=name;
    document.getElementById('groupPreviewBadge').style.background=color;
    document.getElementById('groupPreviewDiscount').textContent='Discount '+discount+'%';
    document.getElementById('colorCode').textContent=color.toUpperCase();
}
</script>

<?php require __DIR__.'/../includes/footer.php';?>