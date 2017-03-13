<?php

use \QUI\ERP\Products;
use \QUI\ERP\Products\Controls\Category\ProductList;

$tags = $Site->getAttribute('quiqqer.productstags.tags');

if (!is_string($tags)) {
    $tags = '';
}

/**
 * CATEGORY
 */
$ProductList = new ProductList(array(
//    'categoryId'           => $Site->getAttribute('quiqqer.products.settings.categoryId'),
    'categoryView' => false,
    'showFilter'   => false,
    'searchParams' => array(
        'tags' => explode(',', $tags)
    ),
    'autoload'     => false,
    'view'         => Products\Utils\Search::getViewParameterFromRequest()
));

$Engine->assign(array(
    'ProductList' => $ProductList
));