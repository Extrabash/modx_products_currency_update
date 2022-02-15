<?php

/*
 * Апдейт цен товаров в зависимости от валюты
 * 
 * 
 */


// Подключаем
define('MODX_API_MODE', true);
require '/home/a/abashy68/abashy68.beget.tech/public_html/index.php';

// Включаем обработку ошибок
$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);

// Запросим все не пустые опции для евро и доллара
$sql = "SELECT * FROM `modx_ms2_product_options` WHERE (`key` = 'price-dollar' OR `key` = 'price-evro') AND `value` > 0 AND `value` IS NOT NULL ORDER BY `product_id` DESC";
$all_usd_eur_prices = $modx->query($sql);

if (!is_object($all_usd_eur_prices)) {
    $modx->log(modX::LOG_LEVEL_ERROR, 'Mysql запрос вернул пустоту', '', 'bash_pruducts_currency.php');
    return 'Mysql запрос вернул пустоту';
}

$all_usd_eur_prices = $all_usd_eur_prices->fetchAll(PDO::FETCH_ASSOC);

$re_prices = array();
foreach($all_usd_eur_prices as $price_option)
{
    $re_prices[$price_option['product_id']]['key'] = $price_option['key'];
    $re_prices[$price_option['product_id']]['value'] = $price_option['value'];
}
unset($all_usd_eur_prices);


$product_ids = array_keys($re_prices);

$where = array(
    'id:IN' => $product_ids
    );
$products_collection = $modx->getCollection('msProduct', $where);

if($products_collection)
{
    $q_to_update = count($products_collection);
    
    $products_updated_count = 0;
    $problems_count = 0;
    
    foreach($products_collection as $product)
    {
        $p_id = $product->get('id');

        if($re_prices[$p_id]['key'] == 'price-dollar')
            $multiplier = 'USD';
        elseif($re_prices[$p_id]['key'] == 'price-evro')
            $multiplier = 'EUR';
        
        
        // Пересчитаем ценники
        $new_price = $modx->runSnippet('CRcalc', [
                'input' 		=> $re_prices[$p_id]['value'],
                'multiplier' 	=> $multiplier,
                'format' 	=> '[1, ".", " "]',
                'noZeros' 	=> '0'
        ]);
        
        if($new_price == 0)
        {
            $problem = 'Проблема в обновлении - id товара '.$p_id.' Валюта и цена '.$multiplier.' '.$re_prices[$p_id]['value'].' -> '.$new_price.'<br/>';
            $modx->log(modX::LOG_LEVEL_ERROR, $problem, '', 'bash_pruducts_currency.php');
            $problems_count++;
        }
        else
        {
            $product->set('price', $new_price);
            $product->save();
            $products_updated_count++;
        }
        
    }
    
    $modx->cacheManager->refresh();

    $result = 'Обновлено '.$products_updated_count.', возникло '. $problems_count . ' проблем';
    $modx->log(modX::LOG_LEVEL_INFO, $result, '', 'bash_pruducts_currency.php');
    return $result;
}
else
{
    $modx->log(modX::LOG_LEVEL_ERROR, 'Пустая коллекция товаров', '', 'bash_pruducts_currency.php');
    return 'Пустая коллекция товаров';
}