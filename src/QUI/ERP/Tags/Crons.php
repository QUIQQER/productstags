<?php

namespace QUI\ERP\Tags;

use Exception;
use QUI;
use QUI\ERP\Products\Handler\Categories;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Search\Utils as ProductSearchUtils;
use QUI\Tags\Groups\Handler as TagGroupsHandler;

use function array_column;
use function array_diff;
use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function array_walk;
use function class_exists;
use function date_create;
use function explode;
use function implode;
use function in_array;
use function ini_get;
use function is_numeric;
use function json_decode;
use function json_encode;
use function set_time_limit;

/**
 * Crons for product tags
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class Crons
{
    const TBL_PRODUCTS_2_TAGS = 'products_to_tags';
    const TBL_TAGS_2_PRODUCTS = 'tags_to_products';
    const TBL_SITES_TO_PRODUCT_TAGS = 'sites_to_product_tags';

    /**
     * Tag generator value for product tags
     */
    const TAG_GENERATOR = 'quiqqer/productstags';

    protected static array $productIdsByCategoryId = [];

    /**
     * Creates and updated product tag cache
     *
     * @return void
     * @throws QUI\Exception
     */
    public static function createCache(): void
    {
        $Project = QUI::getProjectManager()->getStandard();
        $langs = $Project->getLanguages();
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

        $productIds = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => QUI\ERP\Products\Utils\Tables::getProductTableName(),
            'where' => [
                'active' => 1
            ]
        ]);

        $productIds = array_column($productIds, 'id');

        foreach ($productIds as $productId) {
            try {
                ProductEvents::onProductSave(Products::getProduct($productId), false);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        self::createSitesToProductTagsCache();
    }

    /**
     * Determines all product tags of a product category site and saves this data in a database table
     *
     * @return void
     * @throws QUI\Exception
     */
    public static function createSitesToProductTagsCache(): void
    {
        QUI\Watcher::$globalWatcherDisable = true;

        $Project = QUI::getProjectManager()->getStandard();
        $langs = $Project->getLanguages();
        $DataBase = QUI::getDataBase();

        // empty tables
        foreach ($langs as $l) {
            $LangProject = QUI::getProject($Project->getName(), $l);

            $tblProducts2Tags = QUI::getDBProjectTableName(self::TBL_PRODUCTS_2_TAGS, $LangProject);
            $tblSitesToProductTags = QUI::getDBProjectTableName(self::TBL_SITES_TO_PRODUCT_TAGS, $LangProject);

            $Statement = $DataBase->getPDO()->prepare('TRUNCATE TABLE ' . $tblSitesToProductTags . ';');
            $Statement->execute();

            $productCategorySiteIds = $LangProject->getSitesIds([
                'where' => [
                    'type' => 'quiqqer/products:types/category'
                ]
            ]);

            foreach ($productCategorySiteIds as $entry) {
                $categorySiteId = $entry['id'];
                $siteProductTags = [];
                $productCategoryIds = [];

                try {
                    $Site = $LangProject->get($categorySiteId);

                    $mainProductCategoryId = $Site->getAttribute('quiqqer.products.settings.categoryId');

                    if (is_numeric($mainProductCategoryId)) {
                        $productCategoryIds[] = $mainProductCategoryId;
                    }

                    $extraProductCategoryIds = $Site->getAttribute('quiqqer.products.settings.extraProductCategories');

                    if (!empty($extraProductCategoryIds)) {
                        $extraProductCategoryIds = explode(',', $extraProductCategoryIds);

                        $extraProductCategoryIds = array_map(function ($categoryId) {
                            return (int)$categoryId;
                        }, $extraProductCategoryIds);

                        $productCategoryIds = array_merge($productCategoryIds, $extraProductCategoryIds);
                    }

                    $productCategoryIds = array_values(array_unique($productCategoryIds));

                    if (empty($productCategoryIds)) {
                        continue;
                    }

                    foreach ($productCategoryIds as $categoryId) {
                        if (!isset(self::$productIdsByCategoryId[$categoryId])) {
                            $ProductCategory = Categories::getCategory($categoryId);

                            $productIds = $ProductCategory->getProductIds([
                                'where' => [
                                    'active' => 1
                                ]
                            ]);

                            self::$productIdsByCategoryId[$categoryId] = $productIds;
                        } else {
                            $productIds = self::$productIdsByCategoryId[$categoryId];
                        }

                        if (empty($productIds)) {
                            continue;
                        }

                        // Fetch all tags
                        $result = QUI::getDataBase()->fetch([
                            'select' => [
                                'tags'
                            ],
                            'from' => $tblProducts2Tags,
                            'where' => [
                                'id' => [
                                    'type' => 'IN',
                                    'value' => $productIds
                                ],
                                'tags' => [
                                    'type' => 'NOT',
                                    'value' => null
                                ]
                            ]
                        ]);

                        foreach ($result as $row) {
                            $tags = explode(',', $row['tags']);

                            $tags = array_filter($tags, function ($tag) {
                                return !empty($tag);
                            });

                            $siteProductTags = array_merge($siteProductTags, $tags);
                        }
                    }
                } catch (Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                    continue;
                }

                $siteProductTags = array_values(array_unique($siteProductTags));

                if (empty($siteProductTags)) {
                    continue;
                }

                try {
                    QUI::getDataBase()->insert(
                        $tblSitesToProductTags,
                        [
                            'id' => $categorySiteId,
                            'tags' => ',' . implode(',', $siteProductTags) . ','
                        ]
                    );
                } catch (Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }
    }

    /**
     * Generates tags for every entry in every product attribute list field
     * and assigns them to projects, products and product category sites
     *
     * @param array $productIds (optional) - Fixed list of product ids
     * @throws QUI\Database\Exception
     * @throws QUI\ERP\Products\Field\Exception
     * @throws QUI\ERP\Products\Product\Exception
     * @throws QUI\Exception
     * @throws QUI\Tags\Exception
     */
    public static function generateProductAttributeListTags(array $productIds = []): void
    {
        // Disable specific procedures for mass tasks
        Products::disableGlobalProductSearchCacheUpdate();
        Products::disableGlobalFireEventsOnProductSave();

        if (class_exists('\\QUI\\Watcher')) {
            QUI\Watcher::$globalWatcherDisable = true;
        }

        // Get last execution date of this cron
        $considerCronExecDate = empty($productIds);
        $LastCronExecDate = date_create('1970-01-01 00:00:00');

        if ($considerCronExecDate) {
            $result = QUI::getDataBase()->fetch([
                'select' => 'lastexec',
                'from' => QUI\Cron\Manager::table(),
                'where' => [
                    'exec' => '\QUI\ERP\Tags\Crons::generateProductAttributeListTags'
                ],
                'limit' => 1
            ]);

            if (!empty($result)) {
                $LastCronExecDate = date_create($result[0]['lastexec']);
            }
        }

        $projects = QUI::getProjectManager()->getProjects();

        // get all tag groups that have been previously generated by this script
        $tagGroupIdsCurrent = [];

        foreach ($projects as $projectName) {
            $Project = QUI::getProject($projectName);
            $projectLanguages = $Project->getAttribute('langs');

            foreach ($projectLanguages as $l) {
                $Project = QUI::getProject($projectName, $l);

                if (!isset($tagGroupIdsCurrent[$l])) {
                    $tagGroupIdsCurrent[$l] = [];
                }

                $tagGroupIdsCurrent[$l][$Project->getName()] = TagGroupsHandler::getGroupIds(
                    $Project,
                    [
                        'where' => [
                            'generator' => self::TAG_GENERATOR
                        ]
                    ]
                );
            }
        }

        $tagsGroupIdsNew = [];
        $languages = QUI::availableLanguages();

        foreach ($languages as $l) {
            $tagsGroupIdsNew[$l] = [];

            foreach ($projects as $projectName) {
                $tagsGroupIdsNew[$l][$projectName] = [];
            }
        }

        $Locale = new QUI\Locale();

        // reset time limit
        set_time_limit(ini_get('max_execution_time'));

        $tagsPerField = []; // only applied when parsing $Field of type AttributeGroup
        $tagsPerAttributeGroupField = [];

        if (empty($productIds)) {
            $productIdsQuery = "SELECT `id` FROM " . QUI\ERP\Products\Utils\Tables::getProductTableName();
            $productIdsQuery .= " WHERE (`fieldData` LIKE '%\"type\":\"" . Fields::TYPE_ATTRIBUTE_GROUPS . "\"%'";
            $productIdsQuery .= " OR `fieldData` LIKE '%\"type\":\"" . Fields::TYPE_ATTRIBUTE_LIST . "\"%')";
            $productIdsQuery .= " AND `active` = 1";

            $productIds = QUI::getDataBase()->fetchSQL($productIdsQuery);
            $productIds = array_column($productIds, 'id');
        }

        // Determine which fields are already set as global search filters
        // For these tags, tag groups are not automatically assigned to product category sites.
        $ProductSearchConfig = ProductSearchUtils::getConfig();
        $defaultSearchFilterFieldsSetting = $ProductSearchConfig->get('search', 'frontend');
        $defaultSearchFilterFieldIds = [];

        if (!empty($defaultSearchFilterFieldsSetting)) {
            $defaultSearchFilterFieldIds = explode(',', $defaultSearchFilterFieldsSetting);

            array_walk($defaultSearchFilterFieldIds, function (&$fieldId) {
                $fieldId = (int)$fieldId;
            });
        }

        // Prepare array for tag groups that will be assigned to category sites
        $tagGroupIdsBySite = [];

        foreach ($projects as $projectName) {
            $Project = QUI::getProject($projectName);

            $tagGroupIdsBySite[$projectName] = [];

            $projectLanguages = $Project->getAttribute('langs');

            foreach ($projectLanguages as $lang) {
                $tagGroupIdsBySite[$projectName][$lang] = [];
            }
        }

        // Fetch relevant fields
        $fields = array_merge(
            Fields::getFieldsByType(Fields::TYPE_ATTRIBUTE_GROUPS),        // de: "Attribut-Liste"
            Fields::getFieldsByType(Fields::TYPE_ATTRIBUTE_LIST)   // de: "Auswahl-Liste"
        );

        $fieldIdsThatGenerateTags = [];

        /** @var QUI\ERP\Products\Field\Field $Field */
        foreach ($fields as $Field) {
            $EditDate = date_create($Field->getAttribute('e_date'));

            $fieldId = $Field->getId();
            $options = $Field->getOptions();
            $fieldTagGroups = [];
            $generateTags = !empty($options['generate_tags']);

            if (!$generateTags) {
                continue;
            }

            $fieldIdsThatGenerateTags[] = $fieldId;

            if ($considerCronExecDate && $EditDate <= $LastCronExecDate) {
                continue;
            }

            if (!isset($options['entries'])) {
                QUI\System\Log::addWarning(
                    'Cron :: generateProductAttributeListTags -> Could not find'
                    . ' product attribute list entries for field #' . $Field->getId()
                );

                continue;
            }

            $tagsByLang = [];
            $TagGroup = null;

            // generate tag group for each language and project
            foreach ($projects as $projectName) {
                $Project = QUI::getProject($projectName);
                $projectLanguages = $Project->getAttribute('langs');

                foreach ($projectLanguages as $l) {
                    $Project = QUI::getProject($projectName, $l);

                    $Locale->setCurrent($l);

                    if (!isset($fieldTagGroups[$l])) {
                        $fieldTagGroups[$l] = [];
                    }

                    try {
                        $TagGroup = self::addTagGroupToProject(
                            $Project,
                            $Field->getTitle($Locale),
                            $Field->getWorkingTitle($Locale)
                        );
                    } catch (QUI\Exception $exception) {
                        QUI\System\Log::addError(
                            $exception->getMessage(),
                            [
                                'field' => $Field->getTitle(),
                                'field-id' => $Field->getId()
                            ]
                        );

                        continue;
                    }

                    $tagsGroupIdsNew[$l][$Project->getName()][] = $TagGroup->getId();

                    // remove all tags generated by this script from tag group
                    $TagGroup->removeTagsByGenerator(self::TAG_GENERATOR);

                    $fieldTagGroups[$l][] = $TagGroup;
                }
            }

            // generate tags
            $tags = [];
            $tagTitlesByLang = [];
            $isAttributeGroup = $Field instanceof QUI\ERP\Products\Field\Types\AttributeGroup;

            if ($isAttributeGroup) {
                $tagsPerAttributeGroupField[$fieldId] = [];
            }

            foreach ($options['entries'] as $entry) {
                $image = !empty($entry['image']) ? $entry['image'] : false;

                foreach ($entry['title'] as $lang => $text) {
                    if (empty($lang) || empty($text)) {
                        continue;
                    }

                    $tagTitlesByLang[$lang][] = [
                        'title' => $text,
                        'image' => $image
                    ];
                }

                if ($isAttributeGroup) {
                    $tagsPerAttributeGroupField[$fieldId][$entry['valueId']] = [];
                }
            }

            foreach ($tagTitlesByLang as $lang => $tagEntries) {
                $tagGroups = $fieldTagGroups[$lang];
                $tagGroupIds = [];

                /** @var QUI\Tags\Groups\Group $TagGroup */
                foreach ($tagGroups as $TagGroupInstance) {
                    $tagGroupIds[] = $TagGroupInstance->getId();
                }

                if (!isset($tagsByLang[$lang])) {
                    $tagsByLang[$lang] = [];
                }

                // add tags to projects
                foreach ($projects as $projectName) {
                    // skip project if lang does not exist
                    try {
                        $Project = QUI::getProject($projectName, $lang);
                    } catch (QUI\Exception $Exception) {
                        if ($Exception->getCode() === 806) {
                            // Project lang not found
                            continue;
                        } else {
                            QUI\System\Log::writeException($Exception);
                            throw $Exception;
                        }
                    }

                    $tags = self::addTagsToProject($Project, $tagEntries);

                    if (!isset($tagsPerField[$fieldId][$lang])) {
                        $tagsPerField[$fieldId][$lang] = [];
                    }

                    $tagsPerField[$fieldId][$lang] = array_merge(
                        $tagsPerField[$fieldId][$lang],
                        [
                            'project' => $Project->getName(),
                            'tagGroupId' => $TagGroup?->getId(),
                            'tags' => $tags
                        ]
                    );

                    $categoryIds = Categories::getCategoryIds();

                    // Assign tag group based on field to all relevant category Sites.
                    // But ONLY if the field is not already a default search filter field.
                    if (!in_array($fieldId, $defaultSearchFilterFieldIds)) {
                        /** @var QUI\ERP\Products\Category\Category $Category */
                        foreach ($categoryIds as $categoryId) {
                            $Category = Categories::getCategory($categoryId);

                            if (!$Category->getField($Field->getId())) {
                                continue;
                            }

                            $sites = $Category->getSites($Project);

                            /** @var QUI\Projects\Site $CategorySite */
                            foreach ($sites as $CategorySite) {
                                $siteId = $CategorySite->getId();

                                if (isset($tagGroupIdsBySite[$projectName][$lang][$siteId])) {
                                    $tagGroupIdsBySite[$projectName][$lang][$siteId] = array_merge(
                                        $tagGroupIdsBySite[$projectName][$lang][$siteId],
                                        $tagGroupIds
                                    );

                                    continue;
                                }

                                $Edit = $CategorySite->getEdit();

                                $siteTagGroupIds = $Edit->getAttribute('quiqqer.tags.tagGroups');
                                $siteTagGroupIds = explode(',', $siteTagGroupIds);

                                $siteTagGroupIds = array_values(
                                    array_filter($siteTagGroupIds, function ($value) {
                                        return !empty($value);
                                    })
                                );

                                $tagGroupIdsBySite[$projectName][$lang][$siteId] = array_merge(
                                    $siteTagGroupIds,
                                    $tagGroupIds
                                );
                            }
                        }
                    }
                }

                $tagsByLang[$lang] = $tags;

                /**
                 * Try to assign tag names to AttributeGroup field entry values so that it can be
                 * decided which product gets which tags exactly.
                 */
                // $tagName = unique tag identifier
                if ($isAttributeGroup && isset($tagsPerAttributeGroupField[$fieldId])) {
                    foreach ($tags as $k => $tagName) {
                        $i = 0;

                        foreach ($tagsPerAttributeGroupField[$fieldId] as $valueId => $tagsPerAttributeEntry) {
                            if ($i++ === $k) {
                                $tagsPerAttributeGroupField[$fieldId][$valueId][$lang] = $tagName;
                            }
                        }
                    }
                }

                // add tags to tag groups
                /** @var QUI\Tags\Groups\Group $TagGroup */
                foreach ($tagGroups as $TagGroup) {
                    $TagGroup->addTags($tags);
                    $TagGroup->save();
                }
            }
        }

        // Add tag groups to category sites
        foreach ($tagGroupIdsBySite as $projectName => $tagsByLang) {
            foreach ($tagsByLang as $lang => $tagsBySiteId) {
                $Project = new QUI\Projects\Project($projectName, $lang);

                foreach ($tagsBySiteId as $siteId => $tags) {
                    // Fetch site extras directly from db
                    $siteExtrasResult = QUI::getDataBase()->fetch([
                        'select' => 'extra',
                        'from' => $Project->table(),
                        'where' => [
                            'id' => $siteId
                        ],
                        'limit' => 1
                    ]);

                    $siteExtras = json_decode($siteExtrasResult[0]['extra'], true);

                    $siteExtras['quiqqer.tags.tagGroups'] = implode(',', $tags);

                    // Insert extras
                    QUI::getDataBase()->update(
                        $Project->table(),
                        [
                            'extra' => json_encode($siteExtras)
                        ],
                        [
                            'id' => $siteId
                        ]
                    );
                }
            }
        }

        // delete tag groups that are not existing anymore and have no user-tags added
        foreach ($tagGroupIdsCurrent as $lang => $projects) {
            foreach ($projects as $projectName => $tagGroupIds) {
                if (empty($tagsGroupIdsNew[$lang][$projectName])) {
                    $tagsGroupIdsNew[$lang][$projectName] = [];
                }

                // Only consider tag groups that are not generated fields that are still generating tags
                $Project = new QUI\Projects\Project($projectName, $lang);
                $keepTagGroupIds = [];

                foreach ($fieldIdsThatGenerateTags as $fieldId) {
                    $Field = Fields::getField($fieldId);
                    $tagGroupId = self::getTagGroupIdOfField($Field, $Project, $lang);

                    if ($tagGroupId) {
                        $keepTagGroupIds[] = $tagGroupId;
                    }
                }

                // determine deletion candidates
                $deleteTagGroupIds = array_diff(
                    array_diff($tagGroupIds, $keepTagGroupIds),
                    $tagsGroupIdsNew[$lang][$projectName]
                );

                if (empty($deleteTagGroupIds)) {
                    continue;
                }

                $Project = QUI::getProject($projectName, $lang);

                foreach ($deleteTagGroupIds as $tagGroupId) {
                    $TagGroup = TagGroupsHandler::get($Project, $tagGroupId);
                    $tagGroupTags = $TagGroup->getTags();
                    $deleteTagGroup = true;

                    // check if any tags exist in the group other than generated by this script
                    foreach ($tagGroupTags as $tagData) {
                        if ($tagData['generator'] != self::TAG_GENERATOR) {
                            $deleteTagGroup = false;
                        }
                    }

                    if ($deleteTagGroup) {
                        $TagGroup->delete();
                    }
                }
            }
        }

        // Determine the tags per product
        foreach ($productIds as $productId) {
            $Product = Products::getProduct($productId);
            $tagsAddedToProduct = [];

            foreach ($tagsPerField as $fieldId => $tagsByLang) {
                if (!$Product->hasField($fieldId)) {
                    continue;
                }

                foreach ($tagsByLang as $lang => $entry) {
                    if (!isset($tagsAddedToProduct[$lang])) {
                        $tagsAddedToProduct[$lang] = [];
                    }

                    $tagsAddedToProduct[$lang] = array_merge(
                        $tagsAddedToProduct[$lang],
                        $entry
                    );
                }
            }

            $forbiddenTags = [];

//            if ($Product->getType() === QUI\ERP\Products\Product\Types\VariantChild::class) {
            /**
             * Determine tags that should NOT be added. This is the case if the product has
             * a field of type AttributeGroup and has only selected one or less than all entries
             * from that field as its value.
             */
            /** @var QUI\ERP\Products\Field\Types\AttributeGroup $AttributeGroupField */
            foreach ($Product->getFieldsByType(Fields::TYPE_ATTRIBUTE_GROUPS) as $AttributeGroupField) {
                $productValueId = $AttributeGroupField->getValue();
                $fieldId = $AttributeGroupField->getId();

                if (!isset($tagsPerAttributeGroupField[$fieldId])) {
                    continue;
                }

                foreach ($tagsPerAttributeGroupField[$fieldId] as $valueId => $valueTags) {
                    if ($valueId != $productValueId) {
                        foreach ($valueTags as $valueLang => $valueTag) {
                            $forbiddenTags[$valueLang][] = $valueTag;
                        }
                    }
                }
            }
//            }

            // Add tags to the product
            $tagFields = $Product->getFieldsByType(QUI\ERP\Tags\Field::TYPE);

            /** @var QUI\ERP\Tags\Field $ProductField */
            foreach ($tagFields as $ProductField) {
                if (empty($ProductField->getOption('insert_tags'))) {
                    continue;
                }

                // determine tags to delete
                $deleteTags = [];

                foreach ($tagsAddedToProduct as $lang => $entry) {
                    $TagGroup = TagGroupsHandler::get(
                        new QUI\Projects\Project($entry['project'], $lang),
                        $entry['tagGroupId']
                    );

                    $tags = $entry['tags'];

                    if (isset($forbiddenTags[$lang])) {
                        $tags = array_diff($tags, $forbiddenTags[$lang]);
                    }

                    // determine tags to delete
                    $tagGroupTags = array_column($TagGroup->getTags(), 'tag');
                    $deleteTags[$lang] = array_diff($tagGroupTags, $tags);

                    // add tags from product
                    $ProductField->addTags($tags, $lang, self::TAG_GENERATOR);
                }

                foreach ($deleteTags as $lang => $tags) {
                    foreach ($tags as $tag) {
                        $ProductField->removeTag($tag, $lang);
                    }
                }
            }

            try {
                $Product->save();
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        self::createSitesToProductTagsCache();
    }

    /**
     * Adds a tag to a project
     *
     * @param QUI\Projects\Project $Project
     * @param array $tagEntries - tag titles
     *
     * @return array - tag names
     */
    protected static function addTagsToProject(QUI\Projects\Project $Project, array $tagEntries): array
    {
        $TagManager = new QUI\Tags\Manager($Project);
        $tagNames = [];

        try {
            foreach ($tagEntries as $tagEntry) {
                $tagTitle = $tagEntry['title'];

                if ($TagManager->existsTagTitle($tagTitle)) {
                    $tag = $TagManager->getByTitle($tagTitle);
                    $tagNames[] = $tag['tag'];
                    continue;
                }

                $tagCreateData = [
                    'title' => $tagTitle,
                    'generator' => self::TAG_GENERATOR,
                    'generated' => 1
                ];

                if ($tagEntry['image']) {
                    $tagCreateData['image'] = $tagEntry['image'];
                }

                $tagNames[] = $TagManager->add($tagTitle, $tagCreateData);
            }
        } catch (Exception $Exception) {
            QUI\System\Log::addError(
                'Cron :: generateProductAttributeListTags -> Could not'
                . ' add tag to project ' . $Project->getTitle() . ' with lang "' . $Project->getLang() . '" -> '
                . $Exception->getMessage()
            );
        }

        return $tagNames;
    }

    /**
     * @throws QUI\Database\Exception
     */
    protected static function getTagGroupIdOfField(
        QUI\ERP\Products\Field\Field $Field,
        QUI\Projects\Project $Project,
        string $lang
    ): ?int {
        $L = new QUI\Locale();
        $L->setCurrent($lang);

        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id'
            ],
            'from' => QUI::getDBProjectTableName('tags_groups', $Project),
            'where' => [
                'workingtitle' => $Field->getWorkingTitle($L),
                'generator' => self::TAG_GENERATOR
            ]
        ]);

        if (empty($result)) {
            return null;
        }

        return (int)$result[0]['id'];
    }

    /**
     * Adds a tag group to a project
     *
     * @param QUI\Projects\Project $Project
     * @param string $tagGroupTitle - title of tag group
     * @param string $tagGroupWorkingTitle - title of tag group
     *
     * @return QUI\Tags\Groups\Group - generated tag group
     *
     * @throws QUI\Exception
     */
    protected static function addTagGroupToProject(
        QUI\Projects\Project $Project,
        string $tagGroupTitle,
        string $tagGroupWorkingTitle
    ): QUI\Tags\Groups\Group {
        // check if tag group already exists (by working title)
        $result = QUI::getDataBase()->fetch([
            'select' => [
                'id'
            ],
            'from' => QUI::getDBProjectTableName('tags_groups', $Project),
            'where' => [
                'title' => $tagGroupTitle,
                'workingtitle' => $tagGroupWorkingTitle,
                'generator' => self::TAG_GENERATOR
            ]
        ]);

        if (!empty($result)) {
            return TagGroupsHandler::get($Project, $result[0]['id']);
        }

        try {
            $TagGroup = TagGroupsHandler::create($Project, $tagGroupTitle);

            $TagGroup->setWorkingTitle($tagGroupWorkingTitle);
            $TagGroup->setGenerator(self::TAG_GENERATOR);
            $TagGroup->save();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addError(
                'Cron :: generateProductAttributeListTags -> Could not'
                . ' add tag group to project ' . $Project->getName() . ' with lang "' . $Project->getLang() . '" -> '
                . $Exception->getMessage()
            );

            throw $Exception;
        }

        return $TagGroup;
    }
}
