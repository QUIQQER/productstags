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
class Events
{
    /**
     * @param QUI\Package\Package $Package
     */
    public static function onPackageSetup($Package)
    {
        self::createTagStandardField();
    }

    /**
     * Creates a standard field for products that contains tags
     *
     * @throws QUI\Exception
     */
    protected static function createTagStandardField()
    {
        $fieldData = array(
            'id'            => Field::FIELD_TAGS,
            'type'          => Field::TYPE,
            'prefix'        => '',
            'suffix'        => '',
            'priority'      => 13,
            'systemField'   => 0,
            'standardField' => 1,
            'requiredField' => 0,
            'publicField'   => 1,
            'search_type'   => '',
            'options'       => array(
                'insert_tags' => true
            ),
            'titles'        => array(
                'de' => 'Tags',
                'en' => 'Tags'
            )
        );

        // check if field exists
        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => QUI\ERP\Products\Utils\Tables::getFieldTableName(),
            'where' => array(
                'id' => Field::FIELD_TAGS
            )
        ));

        // if field exists -> update
        if (current(current($result)) > 0) {
            QUI::getDataBase()->update(
                QUI\ERP\Products\Utils\Tables::getFieldTableName(),
                array(
                    'type'          => $fieldData['type'],
                    'prefix'        => $fieldData['prefix'],
                    'suffix'        => $fieldData['suffix'],
                    'priority'      => $fieldData['priority'],
                    'systemField'   => $fieldData['systemField'],
                    'standardField' => $fieldData['standardField'],
                    'search_type'   => $fieldData['search_type'],
                    'options'       => json_encode($fieldData['options'])
                ),
                array('id' => $fieldData['id'])
            );

            Products\Handler\Fields::setFieldTranslations($fieldData['id'], $fieldData);

            // create / update view permission
            QUI::getPermissionManager()->addPermission(array(
                'name'  => "permission.products.fields.field{$fieldData['id']}.view",
                'title' => "quiqqer/products permission.products.fields.field{$fieldData['id']}.view.title",
                'desc'  => "",
                'type'  => 'bool',
                'area'  => '',
                'src'   => 'user'
            ));

            // create / update edit permission
            QUI::getPermissionManager()->addPermission(array(
                'name'  => "permission.products.fields.field{$fieldData['id']}.edit",
                'title' => "quiqqer/products permission.products.fields.field{$fieldData['id']}.edit.title",
                'desc'  => "",
                'type'  => 'bool',
                'area'  => '',
                'src'   => 'user'
            ));

            return;
        }

        // if field does not exist -> create
        try {
            Products\Handler\Fields::createField($fieldData);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert($Exception->getMessage());
        }
    }
}
