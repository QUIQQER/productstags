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
    /**
     * Field type
     */
    const TYPE = 'productstags.tags';

    /**
     * Standard Field ID
     */
    const FIELD_TAGS = 101;

    /**
     * @var bool
     */
    public $searchable = false;

    /**
     * Tag Manager instanced by language
     *
     * @var array
     */
    protected $tagManagers = array();

    /**
     * These options must be part of a field value
     *
     * @var array
     */
    protected $valueOptions = array(
        'tag'       => true,
        'generator' => true
    );

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

            foreach ($tags as $tagData) {
                foreach ($this->valueOptions as $option => $v) {
                    if (!isset($tagData[$option])
                        || empty($tagData[$option])
                    ) {
                        continue 2;
                    }
                }

                if (!$TagManager->existsTag($tagData['tag'])) {
                    continue;
                }

                $resultTagData = array();

                foreach ($this->valueOptions as $option => $v) {
                    $resultTagData[$option] = $tagData[$option];
                }

                $tagresult[] = $resultTagData;
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
     * @param string $generator (optional) - the entitiy who created the tag [default: "user"];
     *                                       can be package name for example
     * @return bool - success
     */
    public function addTag($tag, $lang, $generator = 'user')
    {
        $tags = $this->getTags();

        if (!isset($tags[$lang])) {
            return false;
        }

        $tags[$lang][] = array(
            'tag'       => $tag,
            'generator' => $generator
        );

        $this->setValue($tags);

        return true;
    }

    /**
     * Add multiple tags to this field
     *
     * @param array $tags
     * @param string $lang
     * @param string $generator (optional) - the entitiy who created the tags [default: "user"];
     *                                       can be package name for example
     * @return bool - success
     */
    public function addTags($tags, $lang, $generator = 'user')
    {
        $fieldTags = $this->getTags();

        if (!isset($fieldTags[$lang])) {
            return false;
        }

        $newTags = array();

        foreach ($tags as $tag) {
            $newTags[] = array(
                'tag'       => $tag,
                'generator' => $generator
            );
        }

        $fieldTags[$lang] = array_merge($fieldTags[$lang], $newTags);

        $this->setValue($fieldTags);

        return true;
    }

    /**
     * Remove tags of specific language
     *
     * @param string $lang
     * @param string $generator (optional) - remove only tags from entitiy who created the tags [default: "user"];
     * can be package name for example
     *
     * @return bool - success
     */
    public function removeTags($lang, $generator = null)
    {
        $tags = $this->getTags();

        if (!isset($tags[$lang])) {
            return true;
        }

        if (is_null($generator)) {
            $tags[$lang] = array();
            $this->setValue($tags);

            return true;
        }

        foreach ($tags[$lang] as $k => $tagData) {
            if ($tagData['generator'] == $generator) {
                unset($tags[$lang][$k]);
            }
        }

        $this->setValue($tags);

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

        foreach ($tags[$lang] as $k => $tagData) {
            if ($tagData['tag'] != $tag) {
                continue;
            }

            unset($tags[$lang][$k]);
            break;
        }

        $this->setValue($tags);

        return true;
    }

    /**
     * Get all tags that are assigned to this field
     *
     * @param string $generator (optional) - get tags generated by specific generator
     * @return array - array with tag data (tag name and generator)
     */
    public function getTags($generator = null)
    {
        $val = $this->getValue();

        if (empty($val)) {
            $val = array();
        }

        if (!is_array($val)) {
            $val = json_decode($val, true);
        }

        $tags = array();

        foreach ($val as $lang => $langTags) {
            if (!isset($tags[$lang])) {
                $tags[$lang] = array();
            }

            foreach ($langTags as $tagData) {
                if (!is_null($generator)) {
                    if ($tagData['generator'] != $generator) {
                        continue;
                    }
                }

                $tags[$lang][] = $tagData;
            }
        }

        return $tags;
    }

    /**
     * Get all tags that are assigned to this field
     *
     * @param string $generator (optional) - get tags generated by specific generator
     * @return array - array with tags only
     */
    public function getTagList($generator = null)
    {
        $val = $this->getValue();

        if (empty($val)) {
            $val = array();
        }

        if (!is_array($val)) {
            $val = json_decode($val, true);
        }

        $tags = array();

        foreach ($val as $lang => $langTags) {
            if (!isset($tags[$lang])) {
                $tags[$lang] = array();
            }

            foreach ($langTags as $tagData) {
                if (!is_null($generator)) {
                    if ($tagData['generator'] != $generator) {
                        continue;
                    }
                }

                $tags[$lang][] = $tagData['tag'];
            }
        }

        return $tags;
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

    /**
     * Return the view
     *
     * @return \QUI\ERP\Products\Field\View
     */
    public function getFrontendView()
    {
        return new FieldFrontendView($this->getFieldDataForView());
    }

//    /**
//     * Return the field data for a view
//     *
//     * @return array
//     */
//    protected function getFieldDataForView()
//    {
//        $attributes = $this->getAttributes();
//
//        $tags     = $this->getValue();
//        $viewTags = array();
//
//        foreach ($tags as $lang => $langTags) {
//            if (!isset($viewTags[$lang])) {
//                $viewTags[$lang] = array();
//            }
//
//            foreach ($langTags as $tagData) {
//                $viewTags[$lang][] = $tagData['tag'];
//            }
//        }
//
//        $attributes['value'] = $viewTags;
//
//        return $attributes;
//    }
}
