'use strict';

define([
        'jquery',
        'underscore',
        'oro/translator',
        'tabulator',
        'pim/form/common/fields/field',
        'pim/template/form/common/fields/export-mapping'
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

            yesNoValues: {
                'true': __('pim_common.yes'),
                'false': __('pim_common.no'),
            },

            callbacks: {
                'toLowercase': __('candm_advanced_csv_connector.exportMapping.callbacks.to_lowercase'),
                'toUppercase': __('candm_advanced_csv_connector.exportMapping.callbacks.to_uppercase'),
            },

            localesValues: {
                'fr_FR': 'fr_FR',
                'en_GB': 'en_GB',
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
            postRender: function () {
                var self = this;
                var modelValueAsString = !_.isUndefined(self.getModelValue()) ? self.getModelValue() : '[]';
                var tabledata = JSON.parse(modelValueAsString);
                var table = new Tabulator("#mapping-table", {
                    data: tabledata,
                    layout: "fitColumns",
                    responsiveLayout: true,
                    columnHeaderSortMulti: false,
                    addRowPos: 'bottom',
                    cellEdited: function (cell) {
                        self.updateModelValue(cell._cell.table.getData());
                    },
                    rowDeleted: function (row) {
                        self.updateModelValue(row._row.table.getData());
                    },
                    columns: [
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.attribute_code'),
                            field: 'attributeCode',
                            headerSort: false,
                            editor: 'input'
                        },
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.column_name'),
                            field: 'columnName',
                            headerSort: false,
                            editor: 'input'
                        },
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.forced_value'),
                            field: 'forcedValue',
                            headerSort: false,
                            editor: 'input'
                        },
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.callback'),
                            field: 'callback',
                            headerSort: false,
                            editor: 'autocomplete',
                            editorParams: {
                                showListOnEmpty: true,
                                freetext: true,
                                allowEmpty: true,
                                values: self.callbacks
                            }
                        },
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.use_label'),
                            field: 'useLabel',
                            headerSort: false,
                            editor: 'select',
                            editorParams: {
                                values: self.yesNoValues
                            },
                            accessorData: self.booleanAccessor,
                            formatter: self.booleanFormatter,
                            formatterParams: {self: self}
                        },
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.locale'),
                            field: 'locale',
                            headerSort: false,
                            editor: 'select',
                            editorParams: {
                                values: self.localesValues
                            }
                        },
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.max_length'),
                            field: 'maxLength',
                            headerSort: false,
                            editor: 'input'
                        },
                        {
                            title: __('candm_advanced_csv_connector.exportMapping.columns.default_value'),
                            field: 'defaultValue',
                            headerSort: false,
                            editor: 'input'
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.actions.delete_row'),
                            field: 'delete',
                            formatter: 'tickCross',
                            headerSort: false,
                            cellClick: function (e, cell) {
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

            /**
             * Accessor data used to convert string value to boolean
             *
             * @param value
             * @param data
             * @param type
             * @param params
             * @param column
             */
            booleanAccessor: function (value, data, type, params, column) {
                return value === 'true';
            },

            /**
             * Boolean formatter
             *
             * @param cell
             * @param formaterParams
             * @param onRendered
             */
            booleanFormatter: function (cell, formaterParams, onRendered) {
                return _.has(formaterParams.self.yesNoValues, cell.getValue()) ? formaterParams.self.yesNoValues[cell.getValue()] : __('pim_common.no');
            },

            /**
             * Update model data
             *
             * @param data
             */
            updateModelValue: function (data) {
                var dataAsString = JSON.stringify(data);
                this.updateModel(dataAsString);
            }
        });
    });