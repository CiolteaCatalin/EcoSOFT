<?php
require __DIR__.'/../includes/auth.php';
require_login();

require __DIR__.'/../includes/db.php';

date_default_timezone_set('Europe/Bucharest');

$statusFile='/home/backend.ecoautofin.shop/private/storage/woo-sync.json';

$status=null;
$message=null;
$messageType='success';

function formatRomaniaDate(?string $date):string
{
    if(!$date){
        return '-';
    }

    try{
        $sourceTimezone=new DateTimeZone('Europe/Bucharest');
        $romaniaTimezone=new DateTimeZone('Europe/Bucharest');

        $dt=new DateTime($date,$sourceTimezone);
        $dt->setTimezone($romaniaTimezone);

        return $dt->format('d.m.Y H:i:s');
    }catch(Throwable $e){
        return $date;
    }
}

if(file_exists($statusFile)){
    $status=json_decode(
        file_get_contents($statusFile),
        true
    );
}

if($_SERVER['REQUEST_METHOD']==='POST'){

    csrf_check();

    $output=[];
    $code=0;

    /*
    |--------------------------------------------------------------------------
    | PHP CLI
    |--------------------------------------------------------------------------
    |
    | Nu folosim PHP_BINARY aici deoarece în LiteSpeed / PHP web poate fi gol
    | sau poate indica un binar care nu poate fi executat din contextul web.
    |--------------------------------------------------------------------------
    */

    $phpBinary='/usr/bin/php';
    $syncScript=__DIR__.'/woocommerce.php';

    if(!file_exists($phpBinary)){
        $message='Nu a fost găsit executabilul PHP CLI: '.$phpBinary;
        $messageType='error';
    }elseif(!file_exists($syncScript)){
        $message='Nu a fost găsit scriptul de sincronizare WooCommerce.';
        $messageType='error';
    }else{

        $command=
            escapeshellarg($phpBinary).
            ' '.
            escapeshellarg($syncScript).
            ' 2>&1';

        exec(
            $command,
            $output,
            $code
        );

        /*
        |--------------------------------------------------------------------------
        | Recitim statusul generat de sync
        |--------------------------------------------------------------------------
        */

        clearstatcache(true,$statusFile);

        if(file_exists($statusFile)){
            $status=json_decode(
                file_get_contents($statusFile),
                true
            );
        }

        if(
            $code===0 &&
            $status &&
            !empty($status['success'])
        ){
            $message='Catalogul WooCommerce a fost actualizat cu succes.';
            $messageType='success';
        }else{

            $errorParts=[];

            if(
                $status &&
                !empty($status['error'])
            ){
                $errorParts[]=$status['error'];
            }

            if($output){
                $errorParts[]=implode(' | ',$output);
            }

            $message=
                'Sincronizarea nu a putut fi finalizată.'.
                ' Cod: '.$code;

            if($errorParts){
                $message.=' · '.implode(' · ',$errorParts);
            }

            $messageType='error';
        }
    }
}

require __DIR__.'/../includes/header.php';
?>

<style>
.woo-page{display:grid;gap:18px}.woo-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px}.woo-head h1{margin:3px 0 5px;font-size:27px;line-height:1.1;letter-spacing:-.03em;color:#172033}.woo-head p{margin:0;color:#7b8593;font-size:12px}.woo-panel{background:#fff;border:1px solid #e3e9ef;border-radius:14px;overflow:hidden}.woo-panel-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 18px;border-bottom:1px solid #edf1f5}.woo-panel-head h2{margin:0;font-size:15px;color:#172033}.woo-status{display:inline-flex;align-items:center;gap:6px;min-height:24px;padding:0 9px;border-radius:999px;font-size:8px;font-weight:800;text-transform:uppercase}.woo-status.ok{background:#e8f7ee;color:#27734a}.woo-status.error{background:#fdeaea;color:#a13c3c}.woo-status-dot{width:6px;height:6px;border-radius:50%;background:currentColor}.woo-content{padding:18px}.woo-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:16px}.woo-stat{padding:14px;border:1px solid #e7edf2;border-radius:11px;background:#fafbfd}.woo-stat span{display:block;color:#7b8593;font-size:9px;font-weight:600}.woo-stat strong{display:block;margin-top:6px;color:#172033;font-size:22px;letter-spacing:-.03em}.woo-meta{display:grid;gap:8px;margin-bottom:18px;padding:12px 14px;border:1px solid #edf1f5;border-radius:10px;background:#f8fafc}.woo-meta-row{display:flex;align-items:center;justify-content:space-between;gap:20px;font-size:10px}.woo-meta-row span{color:#7b8593}.woo-meta-row strong{color:#172033;text-align:right}.woo-error{margin-bottom:16px;padding:11px 13px;border:1px solid #f0d3d3;border-radius:9px;background:#fff5f5;color:#a13c3c;font-size:10px;line-height:1.5;word-break:break-word}.woo-actions{display:flex;align-items:center;gap:8px}.woo-sync-btn{min-width:210px}.woo-help{font-size:9px;color:#8a94a1}.woo-message{padding:11px 14px;border-radius:10px;font-size:10px;font-weight:600;line-height:1.5;word-break:break-word}.woo-message.success{background:#e8f7ee;border:1px solid #cfeada;color:#27734a}.woo-message.error{background:#fdeaea;border:1px solid #f2d2d2;color:#a13c3c}.woo-timezone{display:inline-flex;align-items:center;gap:5px;color:#8a94a1;font-size:8px}.woo-timezone::before{content:"";width:5px;height:5px;border-radius:50%;background:#2f8f5b}@media(max-width:760px){.woo-head{flex-direction:column;align-items:flex-start}.woo-stats{grid-template-columns:1fr}.woo-actions{flex-direction:column;align-items:stretch}.woo-sync-btn{width:100%}.woo-meta-row{align-items:flex-start;flex-direction:column;gap:3px}}
</style>

<div class="woo-page">

<div class="woo-head">

<div>
<span class="eyebrow">Integrare</span>
<h1>WooCommerce</h1>
<p>Sincronizare produse simple, produse variabile și prețuri.</p>
</div>

<a class="btn" href="/products/">
Vezi produsele
</a>

</div>


<?php if($message):?>

<div class="woo-message <?=e($messageType)?>">
<?=e($message)?>
</div>

<?php endif;?>


<div class="woo-panel">

<div class="woo-panel-head">

<h2>
Sincronizare catalog
</h2>

<?php if($status):?>

<?php if(!empty($status['success'])):?>

<span class="woo-status ok">
<span class="woo-status-dot"></span>
Sincronizat
</span>

<?php else:?>

<span class="woo-status error">
<span class="woo-status-dot"></span>
Eroare
</span>

<?php endif;?>

<?php endif;?>

</div>


<div class="woo-content">

<?php if($status):?>

<?php if(!empty($status['success'])):?>

<div class="woo-stats">

<div class="woo-stat">
<span>Produse procesate</span>
<strong>
<?=(int)($status['products']??0)?>
</strong>
</div>

<div class="woo-stat">
<span>Variații procesate</span>
<strong>
<?=(int)($status['variations']??0)?>
</strong>
</div>

<div class="woo-stat">
<span>Status</span>
<strong style="font-size:15px;color:#27734a;">
OK
</strong>
</div>

</div>


<div class="woo-meta">

<div class="woo-meta-row">
<span>Ultima sincronizare</span>

<strong>
<?=e(
    formatRomaniaDate(
        $status['synced_at']
        ?? null
    )
)?>
</strong>
</div>


<div class="woo-meta-row">
<span>Fus orar</span>

<strong>
Europe/Bucharest
</strong>
</div>


<div class="woo-meta-row">
<span>Sursă</span>

<strong>
WooCommerce API
</strong>
</div>


<div class="woo-meta-row">
<span>Produse variabile</span>

<strong>
Variațiile sunt sincronizate individual
</strong>
</div>

</div>


<?php else:?>

<div class="woo-error">

<strong>
Eroare sincronizare
</strong>

<br>

<?=e(
    $status['error']
    ?? 'Eroare necunoscută'
)?>

</div>

<?php endif;?>


<?php else:?>

<div class="woo-meta">

<div class="woo-meta-row">

<span>
Status
</span>

<strong>
Nu a fost efectuată încă nicio sincronizare.
</strong>

</div>

</div>

<?php endif;?>


<form method="post">

<input
type="hidden"
name="csrf"
value="<?=e(csrf_token())?>"
>

<div class="woo-actions">

<button
type="submit"
class="btn btn-primary woo-sync-btn"
onclick="
    this.disabled=true;
    this.innerText='Se sincronizează...';
    this.form.submit();
"
>
Actualizează din WooCommerce
</button>

<div>

<div class="woo-help">
Produsele și variațiile sunt actualizate din magazin.
</div>

<div class="woo-timezone">
Ora afișată este ora României
</div>

</div>

</div>

</form>

</div>

</div>

</div>

<?php require __DIR__.'/../includes/footer.php';?>