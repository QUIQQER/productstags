<?php

/**
 * This file contains QUI\ERP\Tags\Field
 */

namespace QUI\ERP\Tags;

use QUI;
use QUI\ERP\Products;

use QUI\ERP\Products\Field\Exception;

use QUI\ERP\Products\Field\View;

use function array_merge;
use function implode;
use function is_array;
use function is_null;
use function is_string;
use function json_decode;
use function json_last_error;

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
    public bool $searchable = true;

    /**
     * Tag Manager instanced by language
     *
     * @var array
     */
    protected array $tagManagers = [];

    /**
     * These options must be part of a field value
     *
     * @var array
     */
    protected array $valueOptions = [
        'tag' => true,
        'generator' => true
    ];

    /**
     * Cleanup the value, the value is valid now
     *
     * @param mixed $value
     * @return array
     * @throws QUI\Exception
     */
    public function cleanup(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return [];
        }

        $Project = QUI::getProjectManager()->getStandard();
        $languages = $Project->getLanguages();
        $result = [];

        foreach ($languages as $lang) {
            if (!isset($value[$lang])) {
                $result[$lang] = [];
                continue;
            }

            $tags = $value[$lang];

            if (!is_array($tags)) {
                $result[$lang] = [];
                continue;
            }

            $TagManager = $this->getTagManager($lang);
            $tagresult = [];
            $addedTags = [];

            foreach ($tags as $tagData) {
                foreach ($this->valueOptions as $option => $v) {
                    if (empty($tagData[$option])) {
                        continue 2;
                    }
                }

                $tag = $tagData['tag'];

                if (isset($addedTags[$tag])) {
                    continue;
                }

                if (!$TagManager->existsTag($tag)) {
                    continue;
                }

                $resultTagData = [];

                foreach ($this->valueOptions as $option => $v) {
                    $resultTagData[$option] = $tagData[$option];
                }

                $tagresult[] = $resultTagData;
                $addedTags[$tag] = true; // cache added tags to prevent duplicates
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
     * @throws Exception
     */
    public function validate(mixed $value): void
    {
        if (empty($value)) {
            return;
        }

        if (!is_string($value) && !is_array($value)) {
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception([
                    'quiqqer/products',
                    'exception.field.invalid',
                    [
                        'fieldId' => $this->getId(),
                        'fieldTitle' => $this->getTitle(),
                        'fieldType' => $this->getType()
                    ]
                ]);
            }
        }

        if (is_string($value)) {
            json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception([
                    'quiqqer/products',
                    'exception.field.invalid',
                    [
                        'fieldId' => $this->getId(),
                        'fieldTitle' => $this->getTitle(),
                        'fieldType' => $this->getType()
                    ]
                ]);
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
     * @throws QUI\Exception
     */
    public function addTag(string $tag, string $lang, string $generator = 'user'): bool
    {
        $tags = $this->getTags();

        if (!isset($tags[$lang])) {
            return false;
        }

        $tags[$lang][] = [
            'tag' => $tag,
            'generator' => $generator
        ];

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
     * @throws QUI\Exception
     */
    public function addTags(array $tags, string $lang, string $generator = 'user'): bool
    {
        $fieldTags = $this->getTags();

        if (!isset($fieldTags[$lang])) {
            return false;
        }

        $newTags = [];

        foreach ($tags as $tag) {
            $newTags[] = [
                'tag' => $tag,
                'generator' => $generator
            ];
        }

        $fieldTags[$lang] = array_merge($fieldTags[$lang], $newTags);

        $this->setValue($fieldTags);

        return true;
    }

    /**
     * Remove tags of specific language
     *
     * @param string $lang
     * @param string|null $generator (optional) - remove only tags from entity who created the tags [default: "user"];
     * can be package name for example
     *
     * @return bool - success
     * @throws QUI\Exception
     */
    public function removeTags(string $lang, string $generator = null): bool
    {
        $tags = $this->getTags();

        if (!isset($tags[$lang])) {
            return true;
        }

        if ($generator === null) {
            $tags[$lang] = [];
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
     * @throws QUI\Exception
     */
    public function removeTag(string $tag, string $lang): bool
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
     * @param string|null $generator (optional) - get tags generated by specific generator
     * @return array - array with tag data (tag name and generator)
     */
    public function getTags(string $generator = null): array
    {
        $val = $this->getValue();

        if (empty($val)) {
            $val = [];
        }

        if (!is_array($val)) {
            $val = json_decode($val, true);
        }

        $tags = [];

        foreach ($val as $lang => $langTags) {
            if (!isset($tags[$lang])) {
                $tags[$lang] = [];
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
     * @param string|null $generator (optional) - get tags generated by specific generator
     * @return array - array with tags only
     */
    public function getTagList(string $generator = null): array
    {
        $val = $this->getValue();

        if (empty($val)) {
            $val = [];
        }

        if (!is_array($val)) {
            $val = json_decode($val, true);
        }

        $tags = [];

        foreach ($val as $lang => $langTags) {
            if (!isset($tags[$lang])) {
                $tags[$lang] = [];
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
    public function getJavaScriptControl(): string
    {
        return 'package/quiqqer/productstags/bin/controls/FieldSettings';
    }

    /**
     * @return string
     */
    public function getJavaScriptSettings(): string
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
    protected function getTagManager(string $lang): QUI\Tags\Manager
    {
        if (isset($this->tagManagers[$lang])) {
            return $this->tagManagers[$lang];
        }

        $Project = QUI::getProjectManager()->getStandard();
        $Project = QUI::getProjectManager()->getProject($Project->getName(), $lang);

        $TagManager = new QUI\Tags\Manager($Project);
        $this->tagManagers[$lang] = $TagManager;

        return $TagManager;
    }

    /**
     * Return the view
     *
     * @return View
     */
    public function getFrontendView(): View
    {
        return new FieldFrontendView($this->getFieldDataForView());
    }

    /**
     * Return value for use in product search cache
     *
     * @param null $Locale
     * @return string|null
     */
    public function getSearchCacheValue($Locale = null): ?string
    {
        $val = $this->getValue();

        if (empty($val)) {
            return null;
        }

        $searchCacheValues = [];

        foreach ($val as $lang => $langTags) {
            try {
                $TagManager = $this->getTagManager($lang);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                continue;
            }

            foreach ($langTags as $tagData) {
                try {
                    $tagResult = $TagManager->get($tagData['tag']);
                    $searchCacheValues[] = $tagResult['title'];
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }
        }

        return ',' . implode(',', $searchCacheValues) . ',';
    }
}
