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
 * @author www.pcsg.de (Patrick Müller)
 */
class Field extends Products\Field\Field
{
    const TYPE = 'productstags.tags';

    /**
     * Tag Manager instanced by language
     *
     * @var array
     */
    protected $tagManagers = array();

    /**
     * Cleanup the value, the value is valid now
     *
     * @param mixed $value
     * @return array
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

            $TagManager = $this->getTagManager($lang);
            $tagresult  = array();

            foreach ($tags as $tag) {
                if ($TagManager->existsTag($tag)) {
                    $tagresult[] = $tag;
                }
            }

            $result[$lang] = $tagresult;
        }

        return $result;
    }

    /**
     * Check the value
     * is the value valid for the field type?
     *
     * @param mixed $value
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
     * Adds a tag to this field
     *
     * @param string $tag
     * @param string $lang
     * @return bool - success
     */
    public function addTag($tag, $lang)
    {
        $tags = $this->getTags();

        if (!isset($tags[$lang])) {
            return false;
        }

        $tags[$lang][] = $tag;
        $this->setValue($tags);

        return true;
    }

    /**
     * Add multiple tags to this field
     *
     * @param array $tags
     * @param string $lang
     * @return bool - success
     */
    public function addTags($tags, $lang)
    {
        $fieldTags = $this->getTags();

        if (!isset($fieldTags[$lang])) {
            return false;
        }

        $fieldTags[$lang] = array_merge($fieldTags[$lang], $tags);
        $this->setValue($fieldTags);

        return true;
    }

    /**
     * Removes a tag from this field
     *
     * @param string $tag
     * @param string $lang
     * @return bool - success
     */
    public function removeTag($tag, $lang)
    {
        $tags = $this->getTags();

        if (!isset($tags[$lang])) {
            return false;
        }

        if (!in_array($tag, $tags[$lang])) {
            return false;
        }

        unset($tags[$lang][$tag]);
        $this->setValue($tags);

        return true;
    }

    /**
     * Get all tags that are assigned to this field
     *
     * @return array
     */
    public function getTags()
    {
        $val = $this->getValue();

        if (is_array($val)) {
            return $val;
        }

        $val = trim($val, ',');

        return explode(',', $val);
    }

    /**
     * @return string
     */
    public function getJavaScriptControl()
    {
        return 'package/quiqqer/productstags/bin/controls/FieldSettings';
    }

    /**
     * @return string
     */
    public function getJavaScriptSettings()
    {
        return 'package/quiqqer/productstags/bin/controls/TagSettings';
    }

    /**
     * Get Tag Manager for standard project for specific language
     *
     * @param string $lang
     * @return QUI\Tags\Manager
     * @throws QUI\Exception
     */
    protected function getTagManager($lang)
    {
        if (isset($this->tagManagers[$lang])) {
            return $this->tagManagers[$lang];
        }

        $Project = QUI::getProjectManager()->getStandard();
        $Project = QUI::getProjectManager()->getProject($Project->getName(), $lang);

        $TagManager               = new QUI\Tags\Manager($Project);
        $this->tagManagers[$lang] = $TagManager;

        return $TagManager;
    }
}
