<?php

/**
 * This file contains QUI\ERP\Tags\FieldFrontendView
 */

namespace QUI\ERP\Tags;

use QUI;

use function htmlspecialchars;
use function is_array;

/**
 * Class FieldFrontendView
 *
 * @package QUI\ERP\Tags
 */
class FieldFrontendView extends QUI\ERP\Products\Field\View
{
    /**
     * Return the html
     *
     * @return string
     */
    public function create(): string
    {
        if (!$this->hasViewPermission()) {
            return '';
        }

        $tagHtml = '';

        $value = $this->getValue();
        $title = htmlspecialchars($this->getTitle());
        $lang = QUI::getLocale()->getCurrent();

        $TagManager = new \QUI\Tags\Manager(QUI::getRewrite()->getProject());

        if (is_array($value) && isset($value[$lang])) {
            foreach ($value[$lang] as $tag) {
                try {
                    $tagData = $TagManager->get($tag['tag']);
                } catch (QUI\Exception) {
                    continue;
                }

                $tagHtml .= '<div class="qui-tags-tag">' . $tagData['title'] . '</div>';
            }
        }

        return '<div class="quiqqer-product-field" 
            data-qui="package/quiqqer/productstags/bin/controls/frontend/FieldFrontendView"
        >
            <div class="quiqqer-product-field-title">' . $title . '</div>  
            <div class="quiqqer-product-field-value">' . $tagHtml . '</div>
        </div>';
    }
}
