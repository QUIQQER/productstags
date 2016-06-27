/**
 * @module package/quiqqer/productstags/bin/controls/FieldSettings
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 */
define('package/quiqqer/productstags/bin/controls/FieldSettings', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Select',
    'Locale',
    'Projects',
    'package/quiqqer/tags/bin/TagContainer'

], function (QUI, QUIControl, QUISelect, QUILocale, Projects, Tags) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/productstags/bin/controls/FieldSettings',

        Binds: [
            '$onInject',
            '$changeLanguage'
        ],

        options: {
            value  : {},
            current: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Select = null;
            this.$Tags   = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * event : on import
         */
        $onInject: function () {
            var self    = this,
                Elm     = this.getElm(),
                current = QUILocale.getCurrent();

            Elm.setStyles({
                'float': 'left',
                height : '100%',
                width  : '100%'
            });

            Elm.set({
                html: '<div class="language-select"></div>' +
                      '<div class="tag-container"></div>'
            });

            var TagContainer  = Elm.getElement('.tag-container');
            var LangContainer = Elm.getElement('.language-select');

            TagContainer.setStyles({
                height: 'calc(100% - 50px)'
            });

            LangContainer.setStyles({
                padding: '0 0 10px 0',
                display: 'inline-block',
                width  : '100%'
            });

            this.$Select = new QUISelect({
                styles: {
                    margin: 0,
                    width : '100%'
                }
            }).inject(LangContainer);

            var Project = Projects.get(
                Projects.getName()
            );

            Project.getConfig().then(function (config) {
                var languages = config.langs.split(',');

                for (var i = 0, len = languages.length; i < len; i++) {
                    this.$Select.appendChild(
                        QUILocale.get('quiqqer/system', 'language.' + languages[i]),
                        languages[i],
                        URL_BIN_DIR + '16x16/flags/' + languages[i] + '.png'
                    );
                }

                this.$Select.setValue(current);
                this.setAttribute('current', current);

                this.$Select.addEvents({
                    onChange: function (value) {
                        self.$changeLanguage(value);
                    }
                });

                this.$Tags = new Tags({
                    project    : QUIQQER_PROJECT.name,
                    projectLang: current,
                    styles     : {
                        padding: 0,
                        height : '100%',
                        width  : '100%'
                    }
                }).inject(TagContainer);

                var value = this.getAttribute('value');

                try {
                    if (typeOf(value) == 'string') {
                        this.setAttribute('value', JSON.decode(value));
                    }

                    value = this.getValue();

                    if (current in value) {
                        this.$Tags.setAttribute('projectLang', current);
                        this.$Tags.clear();
                        this.$Tags.addTags(value[current]);
                    }

                } catch (e) {
                }

            }.bind(this));
        },

        /**
         * Change language
         *
         * @param {String} lang
         */
        $changeLanguage: function (lang) {
            if (!this.$Select) {
                return;
            }

            var value   = this.getValue();
            var current = this.getAttribute('current');

            value[current] = this.$Tags.getTags();

            this.setAttribute('value', value);
            this.setAttribute('current', lang);

            if (lang in value) {
                this.$Tags.setAttribute('projectLang', lang);
                this.$Tags.clear();
                this.$Tags.addTags(value[lang]);
            }
        },

        /**
         * Return the current value (lang tag relationship)
         *
         * @returns {Object}
         */
        getValue: function () {
            var value = this.getAttribute('value');

            return typeOf(value) == 'object' ? value : {};
        },

        /**
         * Save the data
         *
         * @return {String}
         */
        save: function () {
            this.$changeLanguage(this.getAttribute('current'));

            return JSON.encode(this.getValue());
        }
    });
});