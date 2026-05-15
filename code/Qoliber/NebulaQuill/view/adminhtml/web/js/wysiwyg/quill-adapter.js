define([], function () {
    'use strict';

    function QuillAdapter(htmlId, config) {
        this.id = htmlId;
        this.config = config || {};
    }

    QuillAdapter.prototype = {
        setup: function () {
            var element = document.getElementById(this.id);

            if (!element) {
                return;
            }

            element.removeAttribute('style');
            element.hidden = false;
        },

        openFileBrowser: function () {},

        toggle: function () {
            return false;
        },

        onFormValidation: function () {},

        encodeContent: function (content) {
            return content;
        }
    };

    return {
        getAdapterPrototype: function () {
            return QuillAdapter;
        }
    };
});
