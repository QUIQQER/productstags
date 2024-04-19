<?php

/**
 * This file contains \QUI\ERP\Tags\Manager
 */

namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Product\Product;
use QUI\Exception;

use function array_filter;
use function current;
use function explode;
use function trim;

/**
 * Product Tags Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 * @authro www.pcsg.de (Patrick Müller)
 */
class Manager
{
    /**
     * Get all (product) tags for a product category Site.
     *
     * @param QUI\Interfaces\Projects\Site $Site
     * @return string[] - Internal tag names
     *
     * @throws QUI\Exception
     */
    public static function getTagsFromProductCategorySite(QUI\Interfaces\Projects\Site $Site): array
    {
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'tags'
            ],
            'from' => QUI::getDBProjectTableName(Crons::TBL_SITES_TO_PRODUCT_TAGS, $Site->getProject()),
            'where' => [
                'id' => $Site->getId()
            ],
            'limit' => 1
        ]);

        if (empty($result) || empty($result[0]['tags'])) {
            return [];
        }

        $tags = explode(',', $result[0]['tags']);

        return array_filter($tags, function ($tag) {
            return !empty($tag);
        });
    }

    /**
     * Return all product ids that have specific tags assigned
     *
     * @param array $tags - list of tags
     * @param string $lang - lang of products project
     * @param integer|null $limit (optional)
     *
     * @return array
     * @throws Exception
     */
    public function getProductIdsFromTags(array $tags, string $lang, int $limit = null): array
    {
        $ids = [];
        $Project = QUI::getProjectManager()->getStandard();
        $Project = QUI::getProject($Project->getName(), $lang);

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'productIds'
            ],
            'from' => QUI::getDBProjectTableName(Crons::TBL_TAGS_2_PRODUCTS, $Project),
            'where' => [
                'tag' => [
                    'type' => 'IN',
                    'value' => $tags
                ]
            ],
            'limit' => $limit === null ? false : $limit
        ]);

        if (empty($result)) {
            return $ids;
        }

        $data = current($result);
        $ids = trim($data['productIds'], ',');

        return explode(',', $ids);
    }

    /**
     * Return all products as objects that have specific tags assigned
     *
     * @param array $tags - list of tags
     * @param string $lang - lang of products project
     * @param integer|null $limit (optional)
     *
     * @return array
     * @throws Exception
     */
    public function getProductsFromTags(array $tags, string $lang, int $limit = null): array
    {
        $products = [];
        $productIds = $this->getProductIdsFromTags($tags, $lang, $limit);

        foreach ($productIds as $pId) {
            try {
                $products[] = Products::getProduct($pId);
            } catch (QUI\Exception) {
            }
        }

        return $products;
    }

    /**
     * Get all tags associated with a product
     *
     * @param Product $Product
     * @param string $lang
     * @param integer|null $limit (optional)
     *
     * @return array
     * @throws Exception
     * @throws QUI\Database\Exception
     */
    public function getTagsFromProduct(
        QUI\ERP\Products\Product\Product $Product,
        string $lang,
        int $limit = null
    ): array {
        $Project = QUI::getProjectManager()->getStandard();
        $Project = QUI::getProject($Project->getName(), $lang);
        $tags = [];

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'tags'
            ],
            'from' => QUI::getDBProjectTableName(Crons::TBL_PRODUCTS_2_TAGS, $Project),
            'where' => [
                'id' => $Product->getId()
            ],
            'limit' => $limit === null ? false : $limit
        ]);

        if (empty($result)) {
            return $tags;
        }

        $data = current($result);
        $tags = trim($data['tags'], ',');

        return explode(',', $tags);
    }
}
