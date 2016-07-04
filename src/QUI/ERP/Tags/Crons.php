<?php

/**
 * This file contains \QUI\Tags\Cron
 */

namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products\Handler\Products;

/**
 * Crons for product tags
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Crons
{
    const TBL_PRODUCTS_2_TAGS = 'products_to_tags';
    const TBL_TAGS_2_PRODUCTS = 'tags_to_products';

    /**
     * Creates and updated product tag cache
     *
     * @return void
     */
    public static function createCache()
    {
        $Project  = QUI::getProjectManager()->getStandard();
        $langs    = $Project->getLanguages();
        $DataBase = QUI::getDataBase();

        // empty tables
        foreach ($langs as $l) {
            $LangProject = QUI::getProject($Project->getName(), $l);

            $tblProducts2Tags = QUI::getDBProjectTableName(self::TBL_PRODUCTS_2_TAGS, $LangProject);
            $tblTags2Products = QUI::getDBProjectTableName(self::TBL_TAGS_2_PRODUCTS, $LangProject);

            $Statement = $DataBase->getPDO()->prepare('TRUNCATE TABLE ' . $tblTags2Products . ';');
            $Statement->execute();

            $Statement = $DataBase->getPDO()->prepare('TRUNCATE TABLE ' . $tblProducts2Tags . ';');
            $Statement->execute();
        }

        $productIds  = QUI\ERP\Products\Handler\Products::getProductIds();
        $tagProducts = array();

        foreach ($productIds as $productId) {
            $Product   = Products::getProduct($productId);
            $tagFields = $Product->getFieldsByType(Field::TYPE);

            if (empty($tagFields)) {
                continue;
            }

            $productTags = array();

            /** @var Field $Field */
            foreach ($tagFields as $Field) {
                $tags = $Field->getTags();
                $tags = $Field->cleanup($tags);

                // build products2tags for this product
                foreach ($langs as $lang) {
                    if (!isset($productTags[$lang])) {
                        $productTags[$lang] = array();
                    }

                    if (isset($tags[$lang])) {
                        $productTags[$lang] = array_merge($productTags[$lang], $tags[$lang]);
                    }
                }

                // build tags2products
                foreach ($tags as $lang => $langTags) {
                    if (!isset($tagProducts[$lang])) {
                        $tagProducts[$lang] = array();
                    }

                    foreach ($langTags as $tag) {
                        if (!isset($tagProducts[$lang][$tag])) {
                            $tagProducts[$lang][$tag] = array();
                        }

                        $tagProducts[$lang][$tag][] = $Product->getId();
                    }
                }
            }

            // insert products to tags
            foreach ($langs as $l) {
                if (empty($productTags[$l])) {
                    continue;
                }

                $data = array(
                    'id'   => $Product->getId(),
                    'tags' => ',' . implode(',', $productTags[$l]) . ','
                );

                $LangProject      = QUI::getProject($Project->getName(), $l);
                $tblProducts2Tags = QUI::getDBProjectTableName(self::TBL_PRODUCTS_2_TAGS, $LangProject);

                $DataBase->insert($tblProducts2Tags, $data);
            }
        }

        // insert tags to products
        foreach ($tagProducts as $lang => $langTags) {
            foreach ($langTags as $tag => $productIds) {
                if (empty($productIds)) {
                    $data['productIds'] = null;
                }

                $data = array(
                    'tag'        => $tag,
                    'productIds' => ',' . implode(',', $productIds) . ','
                );

                $LangProject      = QUI::getProject($Project->getName(), $lang);
                $tblTags2Products = QUI::getDBProjectTableName(self::TBL_TAGS_2_PRODUCTS, $LangProject);

                $DataBase->insert($tblTags2Products, $data);
            }
        }
    }
}
