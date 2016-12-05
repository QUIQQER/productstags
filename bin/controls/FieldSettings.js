/**
 * @module package/quiqqer/productstags/bin/controls/FieldSettings
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Select
 * @require Locale
 * @require Projects
 * @require package/quiqqer/tags/bin/TagContainer
 */
define('package/quiqqer/productstags/bin/controls/FieldSettings', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Select',
    'Locale',
    'Projects',
    'package/quiqqer/tags/bin/tags/Select'

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
                var i, len;
                var languages = config.langs.split(',');

                for (i = 0, len = languages.length; i < len; i++) {
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
                    projectName: QUIQQER_PROJECT.name,
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
                        var tags            = value[current];
                        var tagsToContainer = [];

                        for (i = 0, len = tags.length; i < len; i++) {
                            tagsToContainer.push(tags[i].tag);
                        }

                        this.$Tags.setAttribute('projectLang', current);
                        this.$Tags.clear();
                        this.$Tags.addTags(tagsToContainer);
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

            // keep current tags
            var i, len;
            var current   = this.getAttribute('current'),
                tags      = this.getAttribute('value'),
                tagvalues = this.$Tags.getValue().split(',');

            if (!tags) {
                return;
            }

            if (!(current in tags)) {
                tags[current] = [];
            }

            var isInTags = function (tag, tags) {
                for (var i = 0, len = tags.length; i < len; i++) {
                    if (tags[i].tag == tag) {
                        return true;
                    }
                }
                return false;
            };

            for (i = 0, len = tagvalues.length; i < len; i++) {
                if (!isInTags(tagvalues[i], tags[current])) {
                    tags[current].push({
                        tag      : tagvalues[i],
                        generator: 'user'
                    });
                }
            }

            this.setAttribute('value', tags);

            // change language
            this.setAttribute('current', lang);

            if (!(lang in tags)) {
                return;
            }

            var langTags        = tags[lang];
            var tagsToContainer = [];

            for (i = 0, len = langTags.length; i < len; i++) {
                tagsToContainer.push(langTags[i].tag);
            }

            this.$Tags.setAttribute('projectLang', lang);
            this.$Tags.clear();
            this.$Tags.addTags(tagsToContainer);
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