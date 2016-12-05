<?php

/**
 * This file contains \QUI\Tags\Cron
 */

namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products\Handler\Products;
use QUI\ERP\Products\Handler\Fields;
use QUI\Tags\Groups\Handler as TagGroupsHandler;
use QUI\ERP\Products\Handler\Categories;

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
     * Tag generator value for product tags
     */
    const TAG_GENERATOR = 'quiqqer/productstags';

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

            ProductEvents::onProductSave($Product);
            continue;

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

        return;

        // insert tags to products
        foreach ($tagProducts as $lang => $langTags) {
            foreach ($langTags as $tag => $productIds) {
                $LangProject      = QUI::getProject($Project->getName(), $lang);
                $tblTags2Products = QUI::getDBProjectTableName(self::TBL_TAGS_2_PRODUCTS, $LangProject);

                // delete tag entry if no products are assigned
                if (empty($productIds)) {
                    $DataBase->delete(
                        $tblTags2Products,
                        array(
                            'tag' => $tag
                        )
                    );
                }

                $data = array(
                    'tag'        => $tag,
                    'productIds' => ',' . implode(',', $productIds) . ','
                );

                $DataBase->insert($tblTags2Products, $data);
            }
        }
    }

    /**
     * Generates tags for every entry in every product attribute list field
     * and assigns them to projects, products and product category sites
     *
     * @hrows QUI\Exception
     */
    public static function generateProductAttributeListTags()
    {
        ini_set('display_errors', 1);

        $fields = Fields::getFields(array(
            'where' => array(
                'type' => 'ProductAttributeList'
            )
        ));

        $projects = QUI::getProjectManager()->getProjects(false);

        // get all tag groups that have been previously generated by this script
        $tagGroupIdsCurrent = array();

        foreach ($projects as $projectName) {
            $Project      = QUI::getProject($projectName);
            $projectLangs = $Project->getAttribute('langs');

            foreach ($projectLangs as $l) {
                $Project = QUI::getProject($projectName, $l);

                if (!isset($tagGroupIdsCurrent[$l])) {
                    $tagGroupIdsCurrent[$l] = array();
                }

                $tagGroupIdsCurrent[$l][$Project->getName()] = TagGroupsHandler::getGroupIds(
                    $Project,
                    array(
                        'where' => array(
                            'generator' => self::TAG_GENERATOR
                        )
                    )
                );
            }
        }

        $tagsGroupIdsNew = array();
        $langs           = QUI::availableLanguages();

        foreach ($langs as $l) {
            $tagsGroupIdsNew[$l] = array();

            foreach ($projects as $projectName) {
                $tagsGroupIdsNew[$l][$projectName] = array();
            }
        }

        $Locale = new QUI\Locale();

        // remove all generated tags from products
        $products = Products::getProducts();

        /** @var QUI\ERP\Products\Product\Product $Product */
        foreach ($products as $Product) {
            $tagFields = $Product->getFieldsByType(QUI\ERP\Tags\Field::TYPE);

            /** @var QUI\ERP\Tags\Field $TagField */
            foreach ($tagFields as $TagField) {
                foreach ($langs as $lang) {
                    $TagField->removeTags($lang, self::TAG_GENERATOR);
                }
            }

            $Product->save();
        }

        /** @var QUI\ERP\Products\Field\Field $Field */
        foreach ($fields as $Field) {
            $options        = $Field->getOptions();
            $fieldTagGroups = array();

            if (!isset($options['generate_tags'])
                || !$options['generate_tags']
            ) {
                continue;
            }

            if (!isset($options['entries'])) {
                QUI\System\Log::addWarning(
                    'Cron :: generateProductAttributeListTags -> Could not find'
                    . ' product attribute list entries for field #' . $Field->getId()
                );

                continue;
            }

            // generate tag group for each language and project
            foreach ($projects as $projectName) {
                $Project      = QUI::getProject($projectName);
                $projectLangs = $Project->getAttribute('langs');

                foreach ($projectLangs as $l) {
                    $Project = QUI::getProject($projectName, $l);

                    $Locale->setCurrent($l);

                    if (!isset($fieldTagGroups[$l])) {
                        $fieldTagGroups[$l] = array();
                    }

                    $TagGroup = self::addTagGroupToProject(
                        $Project,
                        $Field->getTitle($Locale),
                        $Field->getWorkingTitle($Locale)
                    );

                    $tagsGroupIdsNew[$l][$Project->getName()][] = $TagGroup->getId();

                    // remove all tags generated by this scripts from tag group
                    $TagGroup->removeTagsByGenerator(self::TAG_GENERATOR);

                    $fieldTagGroups[$l][] = $TagGroup;
                }
            }

            // generate tags
            $tagTitlesByLang = array();
            $tagsByLang      = array();
            $tagList         = array();

            foreach ($options['entries'] as $entry) {
                foreach ($entry['title'] as $lang => $text) {
                    if (empty($lang)
                        || empty($text)
                    ) {
                        continue;
                    }

                    $tagTitlesByLang[$lang][] = $text;
                    $tagList[]                = $text;
                }
            }

            foreach ($tagTitlesByLang as $lang => $tagTitles) {
                $tagGroups = $fieldTagGroups[$lang];

                if (!isset($tagsByLang[$lang])) {
                    $tagsByLang[$lang] = array();
                }

                // add tags to projects
                foreach ($projects as $projectName) {
                    // skip project if lang does not exist
                    try {
                        $Project = QUI::getProject($projectName, $lang);
                    } catch (QUI\Exception $Exception) {
                        if ($Exception->getCode() === 806) {
                            continue;
                        } else {
                            throw $Exception;
                        }
                    }

                    $tags       = self::addTagsToProject($Project, $tagTitles);
                    $categories = Categories::getCategories();

                    /** @var QUI\ERP\Products\Category\Category $Category */
                    foreach ($categories as $Category) {
                        if (!$Category->getField($Field->getId())) {
                            continue;
                        }

                        $sites = $Category->getSites($Project);

                        /** @var QUI\Projects\Site $CategorySite */
                        foreach ($sites as $CategorySite) {
                            $Edit            = $CategorySite->getEdit();
                            $siteTagGroupIds = $Edit->getAttribute('quiqqer.tags.tagGroups');
                            $siteTagGroupIds = explode(',', $siteTagGroupIds);

                            $siteTagGroupIds = array_values(
                                array_filter($siteTagGroupIds, function ($value) {
                                    return !empty($value);
                                })
                            );

                            // add tag groups to category sites
                            /** @var QUI\Tags\Groups\Group $TagGroup */
                            foreach ($tagGroups as $TagGroup) {
                                if (!in_array($TagGroup->getId(), $siteTagGroupIds)) {
                                    $siteTagGroupIds[] = $TagGroup->getId();
                                }
                            }

                            $Edit->setAttribute('quiqqer.tags.tagGroups', implode(',', $siteTagGroupIds));
                            $Edit->save(QUI::getUsers()->getSystemUser());
                        }
                    }
                }

                $tagsByLang[$lang] = $tags;

                // add tags to tag groups
                /** @var QUI\Tags\Groups\Group $TagGroup */
                foreach ($tagGroups as $TagGroup) {
                    $TagGroup->addTags($tags);
                    $TagGroup->save();
                }
            }

            // add tags to products
            $products = $Field->getProducts();

            /** @var QUI\ERP\Products\Product\Product $Product */
            foreach ($products as $Product) {
                $tagFields = $Product->getFieldsByType(QUI\ERP\Tags\Field::TYPE);

                /** @var QUI\ERP\Tags\Field $ProductField */
                foreach ($tagFields as $ProductField) {
                    foreach ($tagsByLang as $lang => $tags) {
                        // add tags from product
                        if ($ProductField->getOption('insert_tags')) {
                            $ProductField->addTags($tags, $lang, self::TAG_GENERATOR);
                        }
                    }
                }

                $Product->save();
            }
        }

        // delete tag groups that are not existing anymore and have no user-tags added
        foreach ($tagGroupIdsCurrent as $lang => $projects) {
            foreach ($projects as $projectName => $tagGroupIds) {
                if (!isset($tagsGroupIdsNew[$lang])
                    && !isset($tagsGroupIdsNew[$lang][$projectName])
                ) {
                    continue;
                }

                // determine deletion candidates
                $deleteTagGroupIds = array_diff(
                    $tagGroupIds,
                    $tagsGroupIdsNew[$lang][$projectName]
                );

                if (empty($deleteTagGroupIds)) {
                    continue;
                }

                $Project = QUI::getProject($projectName, $lang);

                foreach ($deleteTagGroupIds as $tagGroupId) {
                    $TagGroup     = TagGroupsHandler::get($Project, $tagGroupId);
                    $tagGroupTags = $TagGroup->getTags();

                    // check if any tags exist in the group other than generated by this script
                    foreach ($tagGroupTags as $tagData) {
                        if ($tagData['generator'] != self::TAG_GENERATOR) {
                            continue 2;
                        }
                    }

                    $TagGroup->delete();
                }
            }
        }
    }

    /**
     * Adds a tag to a project
     *
     * @param QUI\Projects\Project $Project
     * @param array $tagTitles - tag titles
     *
     * @return array - tag names
     */
    protected static function addTagsToProject($Project, $tagTitles)
    {
        $TagManager = new QUI\Tags\Manager($Project);
        $tagNames   = array();

        try {
            foreach ($tagTitles as $tagTitle) {
                if ($TagManager->existsTagTitle($tagTitle)) {
                    $tag        = $TagManager->getByTitle($tagTitle);
                    $tagNames[] = $tag['tag'];
                    continue;
                }

                $tagNames[] = $TagManager->add(
                    $tagTitle,
                    array(
                        'title'     => $tagTitle,
                        'generator' => self::TAG_GENERATOR,
                        'generated' => 1
                    )
                );
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                'Cron :: generateProductAttributeListTags -> Could not'
                . ' add tag to project ' . $Project->getTitle() . ' with lang "' . $Project->getLang() . '" -> '
                . $Exception->getMessage()
            );
        }

        return $tagNames;
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
    protected static function addTagGroupToProject($Project, $tagGroupTitle, $tagGroupWorkingTitle)
    {
        // check if tag group already exists (by working title)
        $result = QUI::getDataBase()->fetch(array(
            'select' => array(
                'id'
            ),
            'from'   => QUI::getDBProjectTableName('tags_groups', $Project),
            'where'  => array(
                'title'        => $tagGroupTitle,
                'workingtitle' => $tagGroupWorkingTitle,
                'generator'    => self::TAG_GENERATOR
            )
        ));

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
