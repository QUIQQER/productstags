<?php

/**
 * This file contains QUI\ERP\Tags\Field
 */
namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products;

/**
 * Event handling for product events
 *
 * @package QUI\ERP\Tags
 * @author www.pcsg.de (Patrick Müller)
 */
class ProductEvents
{
    /**
     * @param Products\Product\Model $Product
     */
    public static function onProductSave($Product)
    {
        
    }
}
