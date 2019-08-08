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
                            title: __('candm_advanced_csv_connector.importMapping.columns.callback'),
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
                            title: __('candm_advanced_csv_connector.importMapping.columns.default_value'),
                            field: 'defaultValue',
                            headerSort: false,
                            editor: 'input',
                            editorParams: {
                                values: self.callbacks
                            },
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.identifier'),
                            field: 'identifier',
                            headerSort: false,
                            editor: 'select',
                            editorParams: {
                                values: self.yesNoValues
                            },
                            accessor: self.booleanAccessor,
                            formatter: function (cell, formaterParams, onRendered) {
                                return _.has(self.yesNoValues, cell.getValue()) ? self.yesNoValues[cell.getValue()] : cell.getValue();
                            }
                        },
                        {
                            title: __('candm_advanced_csv_connector.importMapping.columns.only_on_creation'),
                            field: 'onlyOnCreation',
                            headerSort: false,
                            editor: 'select',
                            editorParams: {
                                values: self.yesNoValues
                            },
                            accessor: self.booleanAccessor,
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
                            accessor: self.booleanAccessor,
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
             * Accessor used to convert string value to boolean
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
             * Update model data
             *
             * @param data
             */
            updateModelValue: function(data) {
                var dataAsString = JSON.stringify(data);
                this.updateModel(dataAsString);
            }
        });
    });