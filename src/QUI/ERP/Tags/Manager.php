<?php

/**
 * This file contains \QUI\ERP\Tags\Manager
 */

namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products\Handler\Products;

/**
 * Product Tags Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 * @authro www.pcsg.de (Patrick Müller)
 */
class Manager
{
    /**
     * Return all product ids that have specific tags assigned
     *
     * @param array $tags - list of tags
     * @param string $lang - lang of products project
     * @param integer $limit (optional)
     *
     * @return array
     */
    public function getProductIdsFromTags($tags, $lang, $limit = null)
    {
        $ids     = array();
        $Project = QUI::getProjectManager()->getStandard();
        $Project = QUI::getProject($Project->getName(), $lang);

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'productIds'
            ),
            'from'   => QUI::getDBProjectTableName(Crons::TBL_TAGS_2_PRODUCTS, $Project),
            'where'  => array(
                'tag' => array(
                    'type'  => 'IN',
                    'value' => $tags
                )
            ),
            'limit'  => is_null($limit) ? false : (int)$limit
        ));

        if (empty($result)) {
            return $ids;
        }

        $data = current($result);
        $ids  = trim($data['productIds'], ',');
        $ids  = explode(',', $ids);

        return $ids;
    }

    /**
     * Return all products as objects that have specific tags assigned
     *
     * @param array $tags - list of tags
     * @param string $lang - lang of products project
     * @param integer $limit (optional)
     *
     * @return array
     */
    public function getProductsFromTags($tags, $lang, $limit = null)
    {
        $products   = array();
        $productIds = $this->getProductIdsFromTags($tags, $lang, $limit);

        foreach ($productIds as $pId) {
            $products[] = Products::getProduct($pId);
        }

        return $products;
    }

    /**
     * Get all tags associated with a product
     *
     * @param QUI\ERP\Products\Product\Product $Product
     * @param string $lang
     * @param integer $limit (optional)
     *
     * @return array
     */
    public function getTagsFromProduct($Product, $lang, $limit = null)
    {
        $Project = QUI::getProjectManager()->getStandard();
        $Project = QUI::getProject($Project->getName(), $lang);
        $tags    = array();

        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'tags'
            ),
            'from'   => QUI::getDBProjectTableName(Crons::TBL_PRODUCTS_2_TAGS, $Project),
            'where'  => array(
                'id' => $Product->getId()
            ),
            'limit'  => is_null($limit) ? false : (int)$limit
        ));

        if (empty($result)) {
            return $tags;
        }

        $data = current($result);
        $tags = trim($data['tags'], ',');
        $tags = explode(',', $tags);

        return $tags;
    }
}
