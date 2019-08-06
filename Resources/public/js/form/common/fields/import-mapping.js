'use strict';

define([
        'jquery',
        'underscore',
        'oro/translator',
        'tabulator',
        'pim/form/common/fields/field',
        'pim/template/form/common/fields/import-mapping'
    ],
    function (
        $,
        _,
        __,
        Tabulator,
        BaseField,
        template
    ) {
        return BaseField.extend({
            template: _.template(template),

            callbacks: {
                'setMetricUnitAsSuffix': __('candm_advanced_csv_connector.importMapping.callbacks.metric_unit_as_suffix'),
                'downloadVisualFromUrl': __('candm_advanced_csv_connector.importMapping.callbacks.download_visual_from_url'),
            },

            yesNoValues: {
                'true': __('pim_common.yes'),
                'false': __('pim_common.no'),
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

            /**
             * {@inheritdoc}
             */
            postRender: function () {
                var tabledata = [
                    {
                        id: 1,
                        attributeCode: "Oli Bob",
                        field: "12",
                        callback: "red",
                        defaultValue: "coucou",
                        onlyOnCreation: 'false',
                        deleteIfNull: 'true',
                        delete: false
                    },
                ];
                var self = this;
                var table = new Tabulator("#mapping-table", {
                    data: tabledata,
                    layout: "fitColumns",
                    responsiveLayout: true,
                    columnHeaderSortMulti: false,
                    addRowPos: 'bottom',
                    columns: [
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.attribute_code'),
                            field: 'attributeCode',
                            headerSort: false,
                            editor: 'input'
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.column_name'),
                            field: 'dataCode',
                            headerSort: false,
                            editor: 'input'
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.native_callback'),
                            field: 'callback',
                            headerSort: false,
                            editor: 'select',
                            editorParams: {
                                values: self.callbacks
                            },
                            formatter: function (cell, formaterParams, onRendered) {
                                return _.has(self.callbacks, cell.getValue()) ? self.callbacks[cell.getValue()] : cell.getValue();
                            }
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.default_value'),
                            field: 'defaultValue',
                            headerSort: false,
                            editor: 'input',
                            editorParams: {
                                values: self.callbacks
                            },
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.only_on_creation'),
                            field: 'onlyOnCreation',
                            headerSort: false,
                            editor: 'select',
                            editorParams: {
                                values: self.yesNoValues
                            },
                            formatter: function (cell, formaterParams, onRendered) {
                                return _.has(self.yesNoValues, cell.getValue()) ? self.yesNoValues[cell.getValue()] : cell.getValue();
                            }
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.delete_if_null'),
                            field: 'deleteIfNull',
                            headerSort: false,
                            editor: 'select',
                            editorParams: {
                                values: self.yesNoValues
                            },
                            formatter: function (cell, formaterParams, onRendered) {
                                return _.has(self.yesNoValues, cell.getValue()) ? self.yesNoValues[cell.getValue()] : cell.getValue();
                            }
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.actions.delete_row'),
                            field: 'delete',
                            formatter: 'tickCross',
                            headerSort: false,
                            cellClick: function (e, cell) {
                                console.log(cell._cell);
                                cell._cell.row.delete();
                            },
                        }
                    ]
                });

                // Manage clicks
                $("#add-row").click(function () {
                    table.addRow({});
                });
            },
        });
    });