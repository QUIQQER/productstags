<?php

/**
 * This file contains QUI\ERP\Tags\Field
 */

namespace QUI\ERP\Tags;

use Exception;
use QUI;
use QUI\ERP\Products;

use function current;
use function json_encode;

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
     * @throws QUI\Exception
     */
    public static function onPackageSetup(QUI\Package\Package $Package): void
    {
        if ($Package->getName() === 'quiqqer/productstags') {
            self::createTagStandardField();
        }
    }

    /**
     * Creates a standard field for products that contains tags
     *
     * @throws QUI\Exception
     */
    protected static function createTagStandardField(): void
    {
        $fieldData = [
            'id' => Field::FIELD_TAGS,
            'type' => Field::TYPE,
            'prefix' => '',
            'suffix' => '',
            'priority' => 13,
            'systemField' => 0,
            'standardField' => 1,
            'requiredField' => 0,
            'publicField' => 1,
            'search_type' => '',
            'options' => [
                'insert_tags' => true
            ],
            'titles' => [
                'de' => 'Tags',
                'en' => 'Tags'
            ]
        ];

        try {
            // check if field exists
            $result = QUI::getDataBase()->fetch([
                'count' => 1,
                'from' => QUI\ERP\Products\Utils\Tables::getFieldTableName(),
                'where' => [
                    'id' => Field::FIELD_TAGS
                ]
            ]);

            $isFieldAlreadyExisting = current(current($result)) > 0;
        } catch (QUI\Database\Exception) {
            // The table may not exist yet, or throw any other error
            $isFieldAlreadyExisting = false;
        }

        if ($isFieldAlreadyExisting) {
            QUI::getDataBase()->update(
                QUI\ERP\Products\Utils\Tables::getFieldTableName(),
                [
                    'type' => $fieldData['type'],
                    'prefix' => $fieldData['prefix'],
                    'suffix' => $fieldData['suffix'],
                    'priority' => $fieldData['priority'],
                    'systemField' => $fieldData['systemField'],
                    'standardField' => $fieldData['standardField'],
                    'search_type' => $fieldData['search_type'],
                    'options' => json_encode($fieldData['options'])
                ],
                ['id' => $fieldData['id']]
            );

            Products\Handler\Fields::setFieldTranslations($fieldData['id'], $fieldData);

            // create / update view permission
            QUI::getPermissionManager()->addPermission([
                'name' => "permission.products.fields.field{$fieldData['id']}.view",
                'title' => "quiqqer/products permission.products.fields.field{$fieldData['id']}.view.title",
                'desc' => "",
                'type' => 'bool',
                'area' => '',
                'src' => 'user'
            ]);

            // create / update edit permission
            QUI::getPermissionManager()->addPermission([
                'name' => "permission.products.fields.field{$fieldData['id']}.edit",
                'title' => "quiqqer/products permission.products.fields.field{$fieldData['id']}.edit.title",
                'desc' => "",
                'type' => 'bool',
                'area' => '',
                'src' => 'user'
            ]);

            return;
        }

        // if field does not exist -> create
        try {
            Products\Handler\Fields::createField($fieldData);
        } catch (Exception $Exception) {
            QUI\System\Log::addAlert($Exception->getMessage());
        }
    }

    /**
     * @return void
     *
     * @todo tag gruppe oder tag anlegen wenn es eine attributeliste ist
     * @todo peat
     */
    public static function onFieldSave()
    {
    }
}
