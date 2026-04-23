'use strict';

define([
        'jquery',
        'underscore',
        'routing',
        'pim/form/common/fields/field',
        'pim/template/form/common/fields/lua-script'
    ],
    function (
        $,
        _,
        Routing,
        BaseField,
        template
    ) {
        return BaseField.extend({
            template: _.template(template),
            events: {
                'keyup textarea[name]': function (event) {
                    this.errors = [];
                    this.updateModel(this.getFieldValue(event.target));
                },
                'click .AknButton--apply': function (event) {
                    var $messageContainer = $('.lua-message-container');
                    var data = {
                        'script': this.getModelValue(),
                        'testValue': $('.AknTextField--testValue').val()
                    };

                    var rawOriginal = $('.AknTextareaField--testOriginalItem').val();
                    if (rawOriginal && rawOriginal.trim() !== '') {
                        try {
                            data.originalItem = JSON.parse(rawOriginal);
                        } catch (e) {
                            $messageContainer.empty();
                            $messageContainer.append('<div class="AknMessageBox AknMessageBox--danger">originalItem: invalid JSON — ' + _.escape(e.message) + '</div>');
                            return;
                        }
                    }

                    $.ajax({
                        method: 'POST',
                        url: Routing.generate('candm_advanced_csv_connector_api_luaUpdater_test'),
                        contentType: 'application/json',
                        data: JSON.stringify(data),
                        success: function (response) {
                            $messageContainer.empty();
                            if (_.has(response, 'value')) {
                                var raw = response.value;
                                var display = (raw !== null && typeof raw === 'object')
                                    ? JSON.stringify(raw, null, 2)
                                    : String(raw);
                                $messageContainer.append('<div class="AknMessageBox AknMessageBox--apply"><pre>' + _.escape(display) + '</pre></div>');
                            }
                        },
                        error: function (response) {
                            $messageContainer.empty();
                            if (_.has(response.responseJSON, 'message')) {
                                $messageContainer.append('<div class="AknMessageBox AknMessageBox--danger">' + response.responseJSON.message + '</div>');
                            }
                        }
                    });
                }
            },

            /**
             * {@inheritdoc}
             */
            renderInput: function (templateContext) {
                return this.template(_.extend(templateContext, {
                    value: this.getModelValue()
                }));
            },

            /**
             * {@inheritdoc}
             */
            getFieldValue: function (field) {
                return $(field).val();
            },
        });
    });
