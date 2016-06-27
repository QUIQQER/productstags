<?php

/**
 * This file contains QUI\ERP\Tags\Field
 */
namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products;

/**
 * Class Field
 *
 * @package QUI\ERP\Tags
 */
class Field extends Products\Field\Field
{
    /**
     * Cleanup the value, the value is valid now
     *
     * @param mixed $value
     * @return string
     */
    public function cleanup($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return array();
        }

        $Project   = QUI::getProjectManager()->getStandard();
        $languages = $Project->getLanguages();
        $result    = array();

        foreach ($languages as $lang) {
            if (!isset($value[$lang])) {
                $result[$lang] = array();
                continue;
            }

            $tags = $value[$lang];

            if (!is_array($tags)) {
                $result[$lang] = array();
                continue;
            }

            $Project    = QUI::getProjectManager()->getProject($Project->getName(), $lang);
            $TagManager = new QUI\Tags\Manager($Project);
            $tagresult  = array();

            foreach ($tags as $tag) {
                if ($TagManager->existsTag($tag)) {
                    $tagresult[] = $tags;
                }
            }

            $result[$lang] = $value[$lang];
        }

        return $result;
    }

    /**
     * Check the value
     * is the value valid for the field type?
     *
     * @param integer $value
     * @throws \QUI\ERP\Products\Field\Exception
     */
    public function validate($value)
    {
        if (empty($value)) {
            return;
        }

        if (!is_string($value) && !is_array($value)) {
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Products\Field\Exception(array(
                    'quiqqer/products',
                    'exception.field.invalid',
                    array(
                        'fieldId'    => $this->getId(),
                        'fieldTitle' => $this->getTitle(),
                        'fieldType'  => $this->getType()
                    )
                ));
            }
        }

        if (is_string($value)) {
            json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Products\Field\Exception(array(
                    'quiqqer/products',
                    'exception.field.invalid',
                    array(
                        'fieldId'    => $this->getId(),
                        'fieldTitle' => $this->getTitle(),
                        'fieldType'  => $this->getType()
                    )
                ));
            }
        }
    }

    /**
     * @return string
     */
    public function getJavaScriptControl()
    {
        return '';
    }

    /**
     * @return string
     */
    public function getJavaScriptSettings()
    {
        return 'package/quiqqer/productstags/bin/controls/FieldSettings';
    }
}
