/**
 * @module package/quiqqer/productstags/bin/controls/TagSettings
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Locale
 * @require Mustache
 * @require text!package/quiqqer/productstags/bin/controls/TagSettings.html
 * @require text!package/quiqqer/productstags/bin/controls/TagSettingsCreate.html
 * @require css!package/quiqqer/productstags/bin/controls/TagSettings.css
 */
define('package/quiqqer/productstags/bin/controls/TagSettings', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Mustache',

    'text!package/quiqqer/productstags/bin/controls/TagSettings.html',
    'css!package/quiqqer/productstags/bin/controls/TagSettings.css'

], function (QUI, QUIControl, QUILocale, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/productstags';

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/productstags/bin/controls/TagSettings',

        Binds: [
            'update',
            '$onInject',
            '$onImport'
        ],

        options: {},

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject,
                onImport: this.$onImport
            });
        },

        /**
         * Create the DOMNode Element
         *
         * @return {HTMLDivElement}
         */
        create: function () {
            this.$Elm = new Element('div', {
                html  : Mustache.render(template, {
                    title     : QUILocale.get(lg, 'tagsettings.template.title'),
                    insertTags: QUILocale.get(lg, 'tagsettings.template.insertTags')
                }),
                styles: {
                    'float': 'left',
                    width  : '100%'
                }
            });

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var Parent = this.$Elm.getParent('.field-options');

            if (Parent) {
                Parent.setStyle('padding', 0);
            }

            this.$InsertTags = this.$Elm.getElement('[name="insert_tags"]');

            this.refresh();
        },

        /**
         * event : on import
         *
         * @param self
         * @param {HTMLInputElement} Node
         */
        $onImport: function (self, Node) {
            this.$Input = Node;
            this.$Elm   = this.create();

            var data = {};

            try {
                data = JSON.decode(this.$Input.value);
            } catch (e) {
                console.error(this.$Input.value);
                console.error(e);
            }

            if (!this.$data) {
                this.$data = [];
            }

            this.$Elm.wraps(this.$Input);
            this.$onInject();

            if ("insert_tags" in data) {
                this.$InsertTags.checked = data.insert_tags;
            } else {
                this.$InsertTags.checked = false;
            }

            this.$InsertTags.addEvent('change', this.update);
        },

        refresh: function() {
            // @todo
        },

        /**
         * Set the data to the input
         */
        update: function () {
            this.$Input.value = JSON.encode({
                insert_tags: this.$InsertTags.checked
            });
        }
    });
});
