<?php

if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require __DIR__.'/../includes/db.php';

$wc=require '/home/backend.ecoautofin.shop/private/woocommerce.php';

$storeUrl=rtrim(
    $wc['url']
    ?? $wc['store_url']
    ?? $wc['base_url']
    ?? '',
    '/'
);

$consumerKey=
    $wc['consumer_key']
    ?? $wc['ck']
    ?? '';

$consumerSecret=
    $wc['consumer_secret']
    ?? $wc['cs']
    ?? '';

if(!$storeUrl||!$consumerKey||!$consumerSecret){
    exit("WooCommerce configuration invalid.\n");
}

$storageDir='/home/backend.ecoautofin.shop/private/storage';

if(!is_dir($storageDir)){
    mkdir($storageDir,0700,true);
}

function wcRequest(
    string $storeUrl,
    string $consumerKey,
    string $consumerSecret,
    string $endpoint,
    array $params=[]
):array{

    $url=
        $storeUrl.
        '/wp-json/wc/v3/'.
        ltrim($endpoint,'/');

    if($params){
        $url.='?'.http_build_query($params);
    }

    $ch=curl_init();

    curl_setopt_array($ch,[
        CURLOPT_URL=>$url,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>60,
        CURLOPT_CONNECTTIMEOUT=>15,
        CURLOPT_USERPWD=>$consumerKey.':'.$consumerSecret,
        CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER=>[
            'Accept: application/json'
        ],
        CURLOPT_USERAGENT=>'EcoSoft-WooSync/1.0',
        CURLOPT_SSL_VERIFYPEER=>true,
        CURLOPT_SSL_VERIFYHOST=>2
    ]);

    $body=curl_exec($ch);

    if($body===false){
        $err=curl_error($ch);
        curl_close($ch);
        throw new RuntimeException(
            'WooCommerce CURL error: '.$err
        );
    }

    $status=(int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if($status<200||$status>=300){
        throw new RuntimeException(
            'WooCommerce HTTP '.$status.': '.
            substr($body,0,500)
        );
    }

    $data=json_decode($body,true);

    if(!is_array($data)){
        throw new RuntimeException(
            'WooCommerce returned invalid JSON.'
        );
    }

    return $data;
}


function wcAllPages(
    string $storeUrl,
    string $consumerKey,
    string $consumerSecret,
    string $endpoint,
    array $params=[]
):array{

    $result=[];
    $page=1;

    do{

        $batch=wcRequest(
            $storeUrl,
            $consumerKey,
            $consumerSecret,
            $endpoint,
            array_merge(
                $params,
                [
                    'per_page'=>100,
                    'page'=>$page
                ]
            )
        );

        foreach($batch as $row){
            $result[]=$row;
        }

        $page++;

    }while(count($batch)===100);

    return $result;
}


function normalizeDate(?string $date):?string{

    if(!$date){
        return null;
    }

    $ts=strtotime($date);

    if(!$ts){
        return null;
    }

    return date(
        'Y-m-d H:i:s',
        $ts
    );
}


function cleanDescription(?string $html):string{

    if(!$html){
        return '';
    }

    $html=str_replace(
        ['<br>','<br/>','<br />'],
        "\n",
        $html
    );

    $text=strip_tags($html);

    $text=html_entity_decode(
        $text,
        ENT_QUOTES|ENT_HTML5,
        'UTF-8'
    );

    $text=preg_replace(
        "/[ \t]+/",
        ' ',
        $text
    );

    $text=preg_replace(
        "/\n{3,}/",
        "\n\n",
        $text
    );

    return trim($text);
}


function variationLabel(array $variation):string{

    $parts=[];

    foreach(
        $variation['attributes']??[]
        as $attribute
    ){

        $name=trim(
            (string)($attribute['name']??'')
        );

        $option=trim(
            (string)($attribute['option']??'')
        );

        if($option===''){
            continue;
        }

        if($name!==''){
            $parts[]=$name.': '.$option;
        }else{
            $parts[]=$option;
        }
    }

    if(!$parts){
        return 'Variație #'.
            (int)($variation['id']??0);
    }

    return implode(
        ' · ',
        $parts
    );
}


function variationAttributes(array $variation):array{

    $result=[];

    foreach(
        $variation['attributes']??[]
        as $attribute
    ){

        $name=trim(
            (string)($attribute['name']??'')
        );

        $option=trim(
            (string)($attribute['option']??'')
        );

        if($name!==''&&$option!==''){
            $result[$name]=$option;
        }
    }

    return $result;
}


try{

    $startedAt=date('Y-m-d H:i:s');

    echo "Starting WooCommerce sync...\n";

    $wooProducts=wcAllPages(
        $storeUrl,
        $consumerKey,
        $consumerSecret,
        'products',
        [
            'status'=>'any'
        ]
    );

    $productCount=0;
    $variationCount=0;

    foreach($wooProducts as $wp){

        $wooId=(int)($wp['id']??0);

        if(!$wooId){
            continue;
        }

        $type=
            trim(
                (string)($wp['type']??'simple')
            )
            ?: 'simple';

        $name=trim(
            (string)($wp['name']??'')
        );

        if($name===''){
            continue;
        }

        $sku=trim(
            (string)($wp['sku']??'')
        );

        $category='';

        if(!empty($wp['categories'][0]['name'])){
            $category=trim(
                (string)$wp['categories'][0]['name']
            );
        }

        $description=cleanDescription(
            $wp['description']
            ?? $wp['short_description']
            ?? ''
        );

        $imagePath='';

        if(!empty($wp['images'][0]['src'])){
            $imagePath=trim(
                (string)$wp['images'][0]['src']
            );
        }

        $price=(float)(
            $wp['price']
            ?? 0
        );

        $active=
            ($wp['status']??'')
            ==='publish'
            ?1
            :0;

        $modified=normalizeDate(
            $wp['date_modified_gmt']
            ?? $wp['date_modified']
            ?? null
        );

        /*
        |--------------------------------------------------------------------------
        | Existing product?
        |--------------------------------------------------------------------------
        */

        $find=$pdo->prepare(
            'SELECT id
             FROM products
             WHERE woocommerce_id=?
             LIMIT 1'
        );

        $find->execute([$wooId]);

        $productId=(int)$find->fetchColumn();

        if($productId){

            $update=$pdo->prepare(
                'UPDATE products SET
                    source="woo",
                    product_type=?,
                    sku=?,
                    name=?,
                    category=?,
                    description=?,
                    price=?,
                    image_path=?,
                    active=?,
                    woocommerce_modified_at=?,
                    last_synced_at=NOW()
                 WHERE id=?'
            );

            $update->execute([
                $type,
                $sku,
                $name,
                $category,
                $description,
                $price,
                $imagePath,
                $active,
                $modified,
                $productId
            ]);

        }else{

            $insert=$pdo->prepare(
                'INSERT INTO products(
                    source,
                    product_type,
                    woocommerce_id,
                    woocommerce_modified_at,
                    last_synced_at,
                    sku,
                    name,
                    category,
                    description,
                    price,
                    vat_rate,
                    image_path,
                    active,
                    variation_count
                 ) VALUES(
                    "woo",
                    ?,
                    ?,
                    ?,
                    NOW(),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    21,
                    ?,
                    ?,
                    0
                 )'
            );

            $insert->execute([
                $type,
                $wooId,
                $modified,
                $sku,
                $name,
                $category,
                $description,
                $price,
                $imagePath,
                $active
            ]);

            $productId=(int)$pdo->lastInsertId();
        }


        /*
        |--------------------------------------------------------------------------
        | Product specifications
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | variation=true attributes do NOT become technical specs.
        |--------------------------------------------------------------------------
        */

        $pdo
            ->prepare(
                'DELETE FROM product_specs
                 WHERE product_id=?'
            )
            ->execute([$productId]);

        $specInsert=$pdo->prepare(
            'INSERT INTO product_specs(
                product_id,
                spec_key,
                spec_value,
                sort_order
             ) VALUES(?,?,?,?)'
        );

        $specOrder=0;

        foreach($wp['attributes']??[] as $attribute){

            /*
            | Attribute used for variations?
            | Don't save it as a technical specification.
            */

            if(!empty($attribute['variation'])){
                continue;
            }

            $key=trim(
                (string)($attribute['name']??'')
            );

            $options=$attribute['options']??[];

            if(
                $key==='' ||
                !is_array($options) ||
                !$options
            ){
                continue;
            }

            $value=implode(
                ', ',
                array_map(
                    'strval',
                    $options
                )
            );

            if(trim($value)===''){
                continue;
            }

            $specInsert->execute([
                $productId,
                $key,
                $value,
                $specOrder++
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | VARIABLE PRODUCT
        |--------------------------------------------------------------------------
        */

        if($type==='variable'){

            echo "Variable: {$name}\n";

            $variations=wcAllPages(
                $storeUrl,
                $consumerKey,
                $consumerSecret,
                'products/'.$wooId.'/variations'
            );

            $seenVariationIds=[];
            $prices=[];

            foreach($variations as $variation){

                $wooVariationId=(int)(
                    $variation['id']
                    ?? 0
                );

                if(!$wooVariationId){
                    continue;
                }

                $seenVariationIds[]=$wooVariationId;

                $variationSku=trim(
                    (string)($variation['sku']??'')
                );

                $variationName=variationLabel(
                    $variation
                );

                $attributes=variationAttributes(
                    $variation
                );

                $variationPrice=(float)(
                    $variation['price']
                    ?? 0
                );

                if($variationPrice>0){
                    $prices[]=$variationPrice;
                }

                $variationImage='';

                if(!empty($variation['image']['src'])){
                    $variationImage=trim(
                        (string)$variation['image']['src']
                    );
                }

                if($variationImage===''){
                    $variationImage=$imagePath;
                }

                $variationActive=
                    !empty($variation['status'])
                    && $variation['status']==='publish'
                    ?1
                    :0;

                /*
                Woo sometimes doesn't send status in the
                exact same way depending on versions.
                */

                if(
                    isset($variation['purchasable']) &&
                    !$variation['purchasable']
                ){
                    $variationActive=0;
                }

                $variationModified=normalizeDate(
                    $variation['date_modified_gmt']
                    ?? $variation['date_modified']
                    ?? null
                );

                $existingVariation=$pdo->prepare(
                    'SELECT id
                     FROM product_variations
                     WHERE woocommerce_variation_id=?
                     LIMIT 1'
                );

                $existingVariation->execute([
                    $wooVariationId
                ]);

                $variationId=
                    (int)$existingVariation
                        ->fetchColumn();

                if($variationId){

                    $vUpdate=$pdo->prepare(
                        'UPDATE product_variations SET
                            product_id=?,
                            sku=?,
                            variation_name=?,
                            attributes_json=?,
                            price=?,
                            image_path=?,
                            active=?,
                            woocommerce_modified_at=?,
                            last_synced_at=NOW()
                         WHERE id=?'
                    );

                    $vUpdate->execute([
                        $productId,
                        $variationSku,
                        $variationName,
                        json_encode(
                            $attributes,
                            JSON_UNESCAPED_UNICODE
                            |JSON_UNESCAPED_SLASHES
                        ),
                        $variationPrice,
                        $variationImage,
                        $variationActive,
                        $variationModified,
                        $variationId
                    ]);

                }else{

                    $vInsert=$pdo->prepare(
                        'INSERT INTO product_variations(
                            product_id,
                            woocommerce_variation_id,
                            sku,
                            variation_name,
                            attributes_json,
                            price,
                            image_path,
                            active,
                            woocommerce_modified_at,
                            last_synced_at
                         ) VALUES(
                            ?,?,?,?,?,?,?,?,?,
                            NOW()
                         )'
                    );

                    $vInsert->execute([
                        $productId,
                        $wooVariationId,
                        $variationSku,
                        $variationName,
                        json_encode(
                            $attributes,
                            JSON_UNESCAPED_UNICODE
                            |JSON_UNESCAPED_SLASHES
                        ),
                        $variationPrice,
                        $variationImage,
                        $variationActive,
                        $variationModified
                    ]);
                }

                $variationCount++;
            }


            /*
            |--------------------------------------------------------------------------
            | Disable removed Woo variations
            |--------------------------------------------------------------------------
            */

            if($seenVariationIds){

                $placeholders=implode(
                    ',',
                    array_fill(
                        0,
                        count($seenVariationIds),
                        '?'
                    )
                );

                $params=array_merge(
                    [$productId],
                    $seenVariationIds
                );

                $disable=$pdo->prepare(
                    'UPDATE product_variations
                     SET active=0
                     WHERE product_id=?
                       AND woocommerce_variation_id
                           NOT IN('.$placeholders.')'
                );

                $disable->execute($params);

            }else{

                $pdo
                    ->prepare(
                        'UPDATE product_variations
                         SET active=0
                         WHERE product_id=?'
                    )
                    ->execute([$productId]);
            }


            /*
            |--------------------------------------------------------------------------
            | Parent minimum price + variation count
            |--------------------------------------------------------------------------
            */

            $minPrice=$prices
                ?min($prices)
                :0;

            $activeVariationCount=
                (int)$pdo
                    ->prepare(
                        'SELECT COUNT(*)
                         FROM product_variations
                         WHERE product_id=?
                           AND active=1'
                    )
                    ->execute([$productId]);

            /*
            PDO execute returns bool, so actually fetch:
            */

            $cnt=$pdo->prepare(
                'SELECT COUNT(*)
                 FROM product_variations
                 WHERE product_id=?
                   AND active=1'
            );

            $cnt->execute([$productId]);

            $activeVariationCount=
                (int)$cnt->fetchColumn();

            $parentUpdate=$pdo->prepare(
                'UPDATE products
                 SET
                    price=?,
                    variation_count=?
                 WHERE id=?'
            );

            $parentUpdate->execute([
                $minPrice,
                $activeVariationCount,
                $productId
            ]);

        }else{

            /*
            |--------------------------------------------------------------------------
            | SIMPLE product
            |--------------------------------------------------------------------------
            */

            $pdo
                ->prepare(
                    'UPDATE product_variations
                     SET active=0
                     WHERE product_id=?'
                )
                ->execute([$productId]);

            $pdo
                ->prepare(
                    'UPDATE products
                     SET variation_count=0
                     WHERE id=?'
                )
                ->execute([$productId]);
        }

        $productCount++;
    }


    /*
    |--------------------------------------------------------------------------
    | Sync status
    |--------------------------------------------------------------------------
    */

    $status=[
        'success'=>true,
        'synced_at'=>date('Y-m-d H:i:s'),
        'products'=>$productCount,
        'variations'=>$variationCount
    ];

    file_put_contents(
        $storageDir.'/woo-sync.json',
        json_encode(
            $status,
            JSON_PRETTY_PRINT
            |JSON_UNESCAPED_UNICODE
        ),
        LOCK_EX
    );

    echo "\n";
    echo "Sync complete.\n";
    echo "Products: {$productCount}\n";
    echo "Variations: {$variationCount}\n";

}catch(Throwable $e){

    $status=[
        'success'=>false,
        'synced_at'=>date('Y-m-d H:i:s'),
        'error'=>$e->getMessage()
    ];

    @file_put_contents(
        $storageDir.'/woo-sync.json',
        json_encode(
            $status,
            JSON_PRETTY_PRINT
            |JSON_UNESCAPED_UNICODE
        ),
        LOCK_EX
    );

    fwrite(
        STDERR,
        "Sync failed: ".
        $e->getMessage().
        "\n"
    );

    exit(1);
}