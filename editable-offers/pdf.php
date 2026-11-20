<?php
require __DIR__.'/../includes/new-common.php';require __DIR__.'/../vendor/autoload.php';$id=(int)($_GET['id']??0);$s=$pdo->prepare('SELECT * FROM eco_editable_offers WHERE id=?');$s->execute([$id]);$o=$s->fetch();if(!$o){http_response_code(404);exit('Oferta nu există.');}$items=eco_offer_items($pdo,$id);$config=require __DIR__.'/../config/config.php';$lang=in_array($o['language'],['ro','en','it'],true)?$o['language']:'ro';
$italian=['OFERTĂ COMERCIALĂ'=>'OFFERTA COMMERCIALE','Client'=>'Cliente','Adresă facturare'=>'Indirizzo di fatturazione','Adresă livrare'=>'Indirizzo di consegna','Cod TVA / CUI'=>'Partita IVA','Telefon'=>'Telefono','Condiții de plată'=>'Condizioni di pagamento','Condiții de livrare'=>'Condizioni di consegna','Condiții preț'=>'Prezzi','Referința noastră'=>'Nostro rif.','Referință'=>'Codice','Produs și specificații'=>'Descrizione e specifiche','Imagine'=>'Immagine','Preț'=>'Prezzo','Cant.'=>'Qtà','Total'=>'Totale','PREȚ NET FINAL'=>'Prezzo netto finale','GRATUIT'=>'OMAGGIO','Preț net'=>'Prezzo netto','Transport'=>'Spese di trasporto','Pentru acceptarea condițiilor de vânzare și a prețurilor:'=>'Per accettazione delle condizioni di vendita e dei prezzi indicati:','Locul și data'=>'Luogo e data','Clientul (Ștampilă și semnătură)'=>'Il Cliente (Timbro e firma)'];
$tr=function($ro,$eng)use($lang,$italian){return $lang==='it'?($italian[$ro]??$eng):($lang==='en'?$eng:$ro);};
$currency=$o['currency'];$subtotal=0;foreach($items as $it)if(!$it['is_free'])$subtotal+=(float)$it['price']*(float)$it['quantity'];$discount=$o['discount_percent']!==null?$subtotal*(float)$o['discount_percent']/100:0;$net=$subtotal-$discount;$final=$net+(float)($o['transport_cost']??0);
$logo='';$logoFile=__DIR__.'/../assets/logoeco.png';if(is_file($logoFile))$logo='data:image/png;base64,'.base64_encode(file_get_contents($logoFile));
$img=function($path){$path=(string)$path;if(!preg_match('~^uploads/offer-images/[a-f0-9]{32}\.(jpg|png|webp)$~',$path))return ''; $file=__DIR__.'/../'.$path;if(!is_file($file))return '';return 'data:'.(mime_content_type($file)?:'image/png').';base64,'.base64_encode(file_get_contents($file));};
$fields=[[$tr('Client','Customer'),'customer'],[$tr('Adresă facturare','Invoicing address'),'invoice_address'],[$tr('Adresă livrare','Delivery address'),'delivery_address'],[$tr('Cod TVA / CUI','VAT Number'),'vat_number'],['Email','email'],[$tr('Telefon','Phone'),'phone'],[$tr('Condiții de plată','Terms of payment'),'payment_terms'],[$tr('Condiții de livrare','Delivery terms'),'delivery_terms'],[$tr('Condiții preț','Prices'),'price_terms'],[$tr('Referința noastră','Our Ref.'),'our_ref'],['File Link','file_link']];
ob_start();?><!doctype html><html lang="<?=e($lang)?>"><head><meta charset="utf-8"><style>

@page { size: A4 portrait; margin: 0; }
html,body { margin:0; padding:0; }
.sheet { width: 495pt; margin: 38pt 0 62pt 48pt; padding: 0; }
body { font-family:"DejaVu Sans",sans-serif; font-size:8.3px; line-height:1.38; color:#29475e; }
table { width:100%; border-collapse:collapse; border-spacing:0; table-layout:fixed; }
.top { margin:0 0 13px; }
.top td { padding:0; vertical-align:middle; }
.logo { width:45%; }
.logo img { width:153px; max-height:49px; }
.offer-heading { text-align:right; font-size:13px; line-height:1.5; font-weight:bold; color:#024574; }
.muted { color:#70879a; font-size:8px; font-weight:normal; }
.rule { height:2px; background:#2ab5ef; margin:0 0 14px; }
.details { table-layout:fixed; margin:0 0 17px; }
.details td { width:50%; padding:6px 13px 7px 0; vertical-align:top; border-bottom:1px solid #e3ebf1; }
.details .lab { display:block; color:#72889b; font-size:7px; line-height:1.3; margin-bottom:3px; text-transform:uppercase; }
.details .val { display:block; color:#173e5c; font-size:8.6px; line-height:1.38; font-weight:normal; white-space:pre-line; overflow-wrap:break-word; }
.items { table-layout:fixed; margin:0; }
.items thead { display:table-header-group; }
.items th { background:#eaf5fb; color:#024574; font-size:7.3px; font-weight:bold; padding:9px 4px; text-align:left; border-bottom:1px solid #b8ddeb; }
.items td { padding:11px 4px; vertical-align:top; border-bottom:1px solid #e3ebf1; font-size:7.8px; line-height:1.4; word-wrap:break-word; }
.items tr { page-break-inside:avoid; }
.items .code { font-size:7px; color:#597083; font-weight:bold; }
.items .product { font-size:8.7px; line-height:1.35; font-weight:bold; color:#024574; }
.items .description { font-size:8px; line-height:1.45; color:#486174; white-space:pre-line; margin-top:5px; }
.items .photo { display:block; width:120px; max-width:100%; max-height:155px; height:auto; }
.items .num { font-size:6.7px; text-align:right; white-space:normal; word-wrap:break-word; padding-left:2px; padding-right:2px; }
.items .addon td { background:#f4f8fb; }
.items .addon .product { font-weight:normal; }
.summary { width:53%; margin:15px 0 0 auto; page-break-inside:avoid; }
.summary td { padding:7px 9px; border-bottom:1px solid #e3ebf1; font-size:8.1px; }
.summary td:last-child { text-align:right; font-weight:bold; color:#024574; }
.summary .final td { background:#eaf5fb; color:#024574; font-size:9px; font-weight:bold; padding:10px 9px; border-top:2px solid #2ab5ef; }
.notes { margin-top:12px; color:#526a7d; white-space:pre-line; font-size:8px; line-height:1.4; }
.accept { margin-top:20px; page-break-inside:avoid; font-size:8px; }
.accept p { margin:0 0 15px; }
.accept p:first-child { margin-bottom:13px; }
.sign { display:inline-block; min-width:160px; height:13px; border-bottom:1px solid #a4b3bf; }
.footer { position:fixed; left:48pt; right:52pt; bottom:25pt; text-align:center; color:#718699; border-top:1px solid #dce7ee; padding-top:9px; font-size:7px; line-height:1.5; }
.footer strong { color:#024574; }
</style></head><body><div class="sheet"><table class="top"><tr><td class="logo"><?php if($logo):?><img src="<?=$logo?>"><?php else:?>ECO AUTOFIN<?php endif;?></td><td class="offer-heading"><?=$tr('OFERTĂ COMERCIALĂ','COMMERCIAL OFFER')?><br><span style="font-size:10px"><?=e($o['number'])?></span><br><span class="muted"><?=e($o['offer_date'])?></span></td></tr></table><div class="rule"></div><table class="details"><?php $visible=array_values(array_filter($fields,fn($f)=>trim((string)($o[$f[1]]??''))!==''));foreach(array_chunk($visible,2) as $pair):?><tr><?php foreach($pair as [$label,$key]):?><td><span class="lab"><?=e($label)?></span><span class="val"><?=e((string)$o[$key])?></span></td><?php endforeach;?><?php if(count($pair)===1):?><td></td><?php endif;?></tr><?php endforeach;?></table>
<table class="items"><colgroup><col style="width:10%"><col style="width:40%"><col style="width:22%"><col style="width:10%"><col style="width:6%"><col style="width:12%"></colgroup><thead><tr><th><?=$tr('Referință','Reference')?></th><th><?=$tr('Produs și specificații','Product and specifications')?></th><th><?=$tr('Imagine','Image')?></th><th><?=$tr('Preț','Price')?></th><th><?=$tr('Cant.','Qty')?></th><th><?=$tr('Total','Total')?></th></tr></thead><tbody><?php foreach($items as $it):?><tr class="<?=$it['parent_id']!==null?'addon':''?>"><td class="code"><?=e($it['reference'])?></td><td><div class="product"><?=$it['parent_id']!==null?'↳ ':''?><?=e($it['name'])?></div><?php if(trim((string)$it['description'])!==''):?><div class="description"><?=e($it['description'])?></div><?php endif;?></td><td><?php if($src=$img($it['image_path'])):?><img class="photo" src="<?=$src?>"><?php endif;?></td><td class="num"><?=eco_money($it['price'],$currency)?></td><td class="num"><?=number_format((float)$it['quantity'],(float)$it['quantity']==floor((float)$it['quantity'])?0:3,',','.')?></td><td class="num"><?=$it['is_free']?$tr('GRATUIT','FREE'):eco_money((float)$it['price']*(float)$it['quantity'],$currency)?></td></tr><?php endforeach;?></tbody></table>
<table class="summary"><tr><td><?=$tr('Total','Total')?></td><td><?=eco_money($subtotal,$currency)?></td></tr><?php if($o['discount_percent']!==null):?><tr><td>Discount (<?=e((string)$o['discount_percent'])?>%)</td><td>− <?=eco_money($discount,$currency)?></td></tr><tr><td><?=$tr('Preț net','Net price')?></td><td><?=eco_money($net,$currency)?></td></tr><?php endif;?><?php if($o['transport_cost']!==null||trim((string)$o['transport_note'])!==''):?><tr><td><?=$tr('Transport','Transport')?> <?=e($o['transport_note']??'')?></td><td><?=$o['transport_cost']!==null?eco_money($o['transport_cost'],$currency):'—'?></td></tr><?php endif;?><tr class="final"><td><?=$tr('PREȚ NET FINAL','FINAL NET PRICE')?></td><td><?=eco_money($final,$currency)?></td></tr></table>
<?php if(trim((string)$o['notes'])!==''):?><div class="notes"><?=e($o['notes'])?></div><?php endif;?><div class="accept"><p><strong><?=$tr('Pentru acceptarea condițiilor de vânzare și a prețurilor:','For Your Acceptance of Sale Conditions and Prices:')?></strong></p><p><?=$tr('Locul și data','Place and Date')?>: <span class="sign"></span></p><p><?=$tr('Clientul (Ștampilă și semnătură)','The Customer (Stamp and Signature)')?>: <span class="sign"></span></p></div></div><div class="footer"><strong><?=e($config['company']['name'])?> SRL</strong> · <?=e($config['company']['address'])?> · CUI <?=e($config['company']['cui'])?><br><?=e($config['company']['phone'])?> · <?=e($config['company']['email'])?> · <?=e($config['company']['website'])?></div></body></html><?php $html=ob_get_clean();$options=new Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isHtml5ParserEnabled',true);$options->set('defaultFont','DejaVu Sans');$dompdf=new Dompdf\Dompdf($options);$dompdf->loadHtml($html,'UTF-8');$dompdf->setPaper('A4','portrait');$dompdf->render();$dompdf->stream('Oferta-'.preg_replace('/[^A-Za-z0-9_-]/','-',$o['number']).'.pdf',['Attachment'=>false]);
