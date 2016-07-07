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
        $tagFields   = $Product->getFieldsByType(Field::TYPE);
        $pId         = $Product->getId();
        $productTags = array();

        /** @var Field $Field */
        foreach ($tagFields as $Field) {
            $tags = $Field->getTags();

            foreach ($tags as $lang => $langTags) {
                if (!isset($productTags[$lang])) {
                    $productTags[$lang] = array();
                }

                $productTags[$lang] = array_merge($productTags[$lang], $langTags);
            }
        }

        // update products to tags
        $Project = QUI::getProjectManager()->getStandard();
        $DB      = QUI::getDataBase();

        foreach ($productTags as $lang => $langTags) {
            $LangProject      = QUI::getProject($Project->getName(), $lang);
            $tblProducts2Tags = QUI::getDBProjectTableName(Crons::TBL_PRODUCTS_2_TAGS, $LangProject);

            // check if entry exists
            $result = $DB->fetch(array(
                'count' => 1,
                'from'  => $tblProducts2Tags,
                'where' => array(
                    'id' => $pId
                )
            ));

            $exists = false;

            if (current(current($result)) > 0) {
                $exists = true;
            }

            // if product had tags previously and now doesnt -> delete db entry
            if ($exists && empty($langTags)) {
                $DB->delete(
                    $tblProducts2Tags,
                    array(
                        'id' => $pId
                    )
                );

                continue;
            }

            // if product didnt have tags previoulsy and now doesnt either -> do nothing
            if (empty($langTags)) {
                continue;
            }

            // if products didnt have tags previously but now does -> insert
            if (!$exists) {
                $DB->insert(
                    $tblProducts2Tags,
                    array(
                        'id'   => $pId,
                        'tags' => ',' . implode(',', $langTags) . ','
                    )
                );

                continue;
            }

            // if products did have tags previously and now does too -> update
            $DB->update(
                $tblProducts2Tags,
                array(
                    'tags' => ',' . implode(',', $langTags) . ','
                ),
                array(
                    'id' => $pId
                )
            );
        }

        // update tags to products
        foreach ($productTags as $lang => $langTags) {
            $LangProject      = QUI::getProject($Project->getName(), $lang);
            $tblTags2Products = QUI::getDBProjectTableName(Crons::TBL_TAGS_2_PRODUCTS, $LangProject);

            $insertTagsDB = array();
            $updateTagsDB = array();
            $deleteTagsDB = array();

            // get all tags that are currently associated with this product (in the database)
            $tagsWithProduct = array();
            $tags2ProductIds = array();

            $result = $DB->fetch(array(
                'select' => array(
                    'tag',
                    'productIds'
                ),
                'from'   => $tblTags2Products,
                'where'  => array(
                    'productIds' => array(
                        'type'  => '%LIKE%',
                        'value' => ',' . $pId . ','
                    )
                )
            ));

            foreach ($result as $row) {
                $tagsWithProduct[] = $row['tag'];

                $productIds = trim($row['productIds'], ',');
                $productIds = explode(',', $productIds);

                $tags2ProductIds[$row['tag']] = $productIds;
            }

            // determine all tags that have been previously but no longer are associated with this product
            $deleteTags = array_diff($tagsWithProduct, $langTags);

            foreach ($deleteTags as $tag) {
                $pIdKey = array_search($pId, $tags2ProductIds[$tag]);

                if ($pIdKey === false) {
                    continue;
                }

                unset($tags2ProductIds[$tag][$pIdKey]);

                if (empty($tags2ProductIds[$tag])) {
                    $deleteTagsDB[] = $tag;
                } else {
                    $updateTagsDB[$tag] = $tags2ProductIds[$tag];
                }
            }

            // delete from database
            if (!empty($deleteTagsDB)) {
                $DB->delete(
                    $tblTags2Products,
                    array(
                        'tag' => array(
                            'type'  => 'IN',
                            'value' => $deleteTagsDB
                        )
                    )
                );
            }

            // determine all tags that have been added to the product
            $newTags = array_diff($langTags, $tagsWithProduct);

            // get new tags from database to check if they have currently other products associated with them
            $tags2OtherProductIds = array();

            if (!empty($newTags)) {
                $result = $DB->fetch(array(
                    'select' => array(
                        'tag',
                        'productIds'
                    ),
                    'from'   => $tblTags2Products,
                    'where'  => array(
                        'tag' => array(
                            'type'  => 'IN',
                            'value' => $newTags
                        )
                    )
                ));

                foreach ($result as $row) {
                    $productIds = trim($row['productIds'], ',');
                    $productIds = explode(',', $productIds);

                    $tags2OtherProductIds[$row['tag']] = $productIds;
                }
            }

            // determine wether new tags for this product have to be inserted or updated
            foreach ($newTags as $tag) {
                if (isset($tags2OtherProductIds[$tag])) {
                    $tags2OtherProductIds[$tag][] = $pId;
                    $updateTagsDB[$tag]           = $tags2OtherProductIds[$tag];

                    continue;
                }

                $insertTagsDB[$tag] = array($pId);
            }

            // insert new tag entries
            foreach ($insertTagsDB as $tag => $productIds) {
                $DB->insert(
                    $tblTags2Products,
                    array(
                        'tag'        => $tag,
                        'productIds' => ',' . implode(',', $productIds) . ','
                    )
                );
            }

            // update existing tag entries
            foreach ($updateTagsDB as $tag => $productIds) {
                $DB->update(
                    $tblTags2Products,
                    array(
                        'productIds' => ',' . implode(',', $productIds) . ','
                    ),
                    array(
                        'tag' => $tag
                    )
                );
            }
        }
    }
}
