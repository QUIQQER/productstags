<?php

/**
 * This file contains QUI\ERP\Tags\Field
 */

namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products;
use QUI\ERP\Products\Handler\Fields;

/**
 * Event handling for product events
 *
 * @package QUI\ERP\Tags
 * @author www.pcsg.de (Patrick Müller)
 */
class ProductEvents
{
    /**
     * quiqqer/products: onQuiqqerProductsProductCleanup
     *
     * @return void
     */
    public static function onQuiqqerProductsProductCleanup(): void
    {
        $productIdsQuery = "SELECT `id` FROM ".QUI\ERP\Products\Utils\Tables::getProductTableName();
        $productIdsQuery .= " WHERE `fieldData` NOT LIKE '%\"type\":\"".Fields::TYPE_ATTRIBUTE_GROUPS."\"%'";
        $productIdsQuery .= " AND `fieldData` NOT LIKE '%\"type\":\"".Fields::TYPE_ATTRIBUTE_LIST."\"%'";
        $productIdsQuery .= " AND `active` = 1";

        try {
            Crons::generateProductAttributeListTags(\array_column($productIdsQuery, 'id'));
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * @param Products\Product\Model $Product
     * @param bool $generateAttributeListTags (optional)
     * @throws QUI\Exception
     */
    public static function onProductSave($Product, bool $generateAttributeListTags = true)
    {
        if ($generateAttributeListTags) {
            Crons::generateProductAttributeListTags([$Product->getId()]);
        }

        $tagFields   = $Product->getFieldsByType(Field::TYPE);
        $pId         = $Product->getId();
        $productTags = [];

        /** @var Field $Field */
        foreach ($tagFields as $Field) {
            $tags = $Field->getTagList();

            foreach ($tags as $lang => $langTags) {
                if (!isset($productTags[$lang])) {
                    $productTags[$lang] = [];
                }

                $productTags[$lang] = \array_merge($productTags[$lang], $langTags);
            }
        }

        // update products to tags
        $Project = QUI::getProjectManager()->getStandard();
        $DB      = QUI::getDataBase();

        foreach ($productTags as $lang => $langTags) {
            $LangProject      = QUI::getProject($Project->getName(), $lang);
            $tblProducts2Tags = QUI::getDBProjectTableName(Crons::TBL_PRODUCTS_2_TAGS, $LangProject);

            // check if entry exists
            $result = $DB->fetch([
                'count' => 1,
                'from'  => $tblProducts2Tags,
                'where' => [
                    'id' => $pId
                ]
            ]);

            $exists = false;

            if (\current(\current($result)) > 0) {
                $exists = true;
            }

            // if product had tags previously and now doesnt -> delete db entry
            if ($exists && empty($langTags)) {
                $DB->delete(
                    $tblProducts2Tags,
                    [
                        'id' => $pId
                    ]
                );
            }

            // if product didnt have tags previoulsy and now doesnt either -> do nothing
            if (empty($langTags)) {
                continue;
            }

            $langTags = \array_values(\array_unique($langTags));

            // if products didnt have tags previously but now does -> insert
            if (!$exists) {
                $DB->insert(
                    $tblProducts2Tags,
                    [
                        'id'   => $pId,
                        'tags' => ','.\implode(',', $langTags).','
                    ]
                );
            }

            // if products did have tags previously and now does too -> update
            $DB->update(
                $tblProducts2Tags,
                [
                    'tags' => ','.\implode(',', $langTags).','
                ],
                [
                    'id' => $pId
                ]
            );

            // upate product cache table with tags
            $DB->update(
                Products\Utils\Tables::getProductCacheTableName(),
                [
                    'tags' => ','.implode(',', $langTags).','
                ],
                [
                    'id'   => $Product->getId(),
                    'lang' => $lang
                ]
            );
        }

        // update tags to products
        foreach ($productTags as $lang => $langTags) {
            $LangProject      = QUI::getProject($Project->getName(), $lang);
            $tblTags2Products = QUI::getDBProjectTableName(Crons::TBL_TAGS_2_PRODUCTS, $LangProject);

            $insertTagsDB = [];
            $updateTagsDB = [];
            $deleteTagsDB = [];

            // get all tags that are currently associated with this product (in the database)
            $tagsWithProduct = [];
            $tags2ProductIds = [];

            $result = $DB->fetch([
                'select' => [
                    'tag',
                    'productIds'
                ],
                'from'   => $tblTags2Products,
                'where'  => [
                    'productIds' => [
                        'type'  => '%LIKE%',
                        'value' => ','.$pId.','
                    ]
                ]
            ]);

            foreach ($result as $row) {
                $tagsWithProduct[] = $row['tag'];

                $productIds = \trim($row['productIds'], ',');
                $productIds = \explode(',', $productIds);

                $tags2ProductIds[$row['tag']] = $productIds;
            }

            // determine all tags that have been previously but no longer are associated with this product
            $deleteTags = \array_diff($tagsWithProduct, $langTags);

            foreach ($deleteTags as $tag) {
                $pIdKey = \array_search($pId, $tags2ProductIds[$tag]);

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
                    [
                        'tag' => [
                            'type'  => 'IN',
                            'value' => $deleteTagsDB
                        ]
                    ]
                );
            }

            // determine all tags that have been added to the product
            $newTags = \array_diff($langTags, $tagsWithProduct);

            // get new tags from database to check if they have currently other products associated with them
            $tags2OtherProductIds = [];

            if (!empty($newTags)) {
                $result = $DB->fetch([
                    'select' => [
                        'tag',
                        'productIds'
                    ],
                    'from'   => $tblTags2Products,
                    'where'  => [
                        'tag' => [
                            'type'  => 'IN',
                            'value' => $newTags
                        ]
                    ]
                ]);

                foreach ($result as $row) {
                    $productIds = \trim($row['productIds'], ',');
                    $productIds = \explode(',', $productIds);

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

                $insertTagsDB[$tag] = [$pId];
            }

            // insert new tag entries
            foreach ($insertTagsDB as $tag => $productIds) {
                $DB->insert(
                    $tblTags2Products,
                    [
                        'tag'        => $tag,
                        'productIds' => ','.\implode(',', $productIds).','
                    ]
                );
            }

            // update existing tag entries
            foreach ($updateTagsDB as $tag => $productIds) {
                $DB->update(
                    $tblTags2Products,
                    [
                        'productIds' => ','.\implode(',', $productIds).','
                    ],
                    [
                        'tag' => $tag
                    ]
                );
            }
        }
    }
}
