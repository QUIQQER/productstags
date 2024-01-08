<?php

use QUI\ERP\Products\Controls\Category\ProductList;

$tags = $Site->getAttribute('quiqqer.productstags.tags');

if (!is_string($tags)) {
    $tags = '';
}

/**
 * CATEGORY
 */
$ProductList = new ProductList([
//    'categoryId'           => $Site->getAttribute('quiqqer.products.settings.categoryId'),
    'categoryView' => false,
    'showFilter' => false,
    'showFilterInfo' => false,
    'searchParams' => [
        'tags' => explode(',', $tags)
    ],
    'autoload' => false,
    'view' => QUI\ERP\Products\Search\Utils::getViewParameterFromRequest(),
    'autoloadAfter' => false
]);

$Engine->assign([
    'ProductList' => $ProductList
]);
