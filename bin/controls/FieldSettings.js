/**
 * @module package/quiqqer/productstags/bin/controls/FieldSettings
 * @author www.pcsg.de (Henning Leutz)
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
            this.$Tags = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * event : on import
         */
        $onInject: function () {
            const self    = this,
                  Elm     = this.getElm(),
                  current = QUILocale.getCurrent();

            Elm.setStyles({
                'float': 'left',
                height : '100%',
                width  : '100%'
            });

            Elm.set({
                'data-qui': "package/quiqqer/productstags/bin/controls/FieldSettings",
                html      : '<div class="language-select"></div>' +
                            '<div class="tag-container"></div>'
            });

            const TagContainer = Elm.getElement('.tag-container');
            const LangContainer = Elm.getElement('.language-select');

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

            const Project = Projects.get(Projects.getName());

            Project.getConfig().then((config) => {
                let i, len;
                const languages = config.langs.split(',');

                for (i = 0, len = languages.length; i < len; i++) {
                    this.$Select.appendChild(
                        QUILocale.get('quiqqer/quiqqer', 'language.' + languages[i]),
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
                    projectName      : QUIQQER_PROJECT.name,
                    projectLang      : current,
                    considerMaxAmount: false,
                    events           : {
                        onRemoveTag: function (tag) {
                            self.$onRemoveTag(tag);
                        }
                    },
                    styles           : {
                        padding: 0,
                        height : '100%',
                        width  : '100%'
                    }
                }).inject(TagContainer);

                let value = this.getAttribute('value');

                try {
                    if (typeOf(value) === 'string') {
                        this.setAttribute('value', JSON.decode(value));
                    }

                    value = this.getValue();

                    if (current in value) {
                        const tags = value[current];
                        const tagsToContainer = [];

                        for (i = 0, len = tags.length; i < len; i++) {
                            tagsToContainer.push(tags[i].tag);
                        }

                        this.$Tags.setAttribute('projectLang', current);
                        this.$Tags.clear();
                        this.$Tags.addTags(tagsToContainer);
                    }
                } catch (e) {
                }
            });
        },

        /**
         * event: on remove tag from tag container
         *
         * @param {string} tag
         */
        $onRemoveTag: function (tag) {
            const current = this.getAttribute('current');
            const tags = this.getAttribute('value');

            if (!(current in tags)) {
                return;
            }

            const langTags = tags[current];

            for (let i = 0, len = langTags.length; i < len; i++) {
                if (langTags[i].tag.toLowerCase() === tag.toLowerCase()) {
                    tags[current].splice(i, 1);
                    break;
                }
            }

            this.setAttribute('value', tags);
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
            let i, len;
            const current = this.getAttribute('current');

            let tags      = this.getAttribute('value'),
                tagValues = this.$Tags.getValue().split(',');

            if (!tags) {
                tags = {};
            }

            if (typeof tags[current] === 'undefined') {
                tags[current] = [];
            }

            const isInTags = function (tag, tags) {
                for (let i = 0, len = tags.length; i < len; i++) {
                    if (tags[i].tag.toLowerCase() === tag.toLowerCase()) {
                        return true;
                    }
                }
                return false;
            };

            for (i = 0, len = tagValues.length; i < len; i++) {
                if (!isInTags(tagValues[i], tags[current])) {
                    tags[current].push({
                        tag      : tagValues[i],
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

            const langTags = tags[lang];
            const tagsToContainer = [];

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
            const value = this.getAttribute('value');
            return typeOf(value) === 'object' ? value : {};
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