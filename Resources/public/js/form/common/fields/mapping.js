'use strict';

define([
        'jquery',
        'underscore',
        'tabulator',
        'pim/form/common/fields/field',
        'pim/template/form/common/fields/mapping'
    ],
    function (
        $,
        _,
        Tabulator,
        BaseField,
        template
    ) {
        return BaseField.extend({
            template: _.template(template),
            events: {
                'keyup input': function (event) {
                    this.errors = [];
                    this.updateModel(this.getFieldValue(event.target));
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

            /**
             * {@inheritdoc}
             */
            postRender: function () {
                var tabledata = [
                    {id:1, name:"Oli Bob", age:"12", col:"red", dob:""},
                    {id:2, name:"Mary May", age:"1", col:"blue", dob:"14/05/1982"},
                    {id:3, name:"Christine Lobowski", age:"42", col:"green", dob:"22/05/1982"},
                    {id:4, name:"Brendon Philips", age:"125", col:"orange", dob:"01/08/1980"},
                    {id:5, name:"Margret Marmajuke", age:"16", col:"yellow", dob:"31/01/1999"},
                ];
                var table = new Tabulator("#example-table", {
                    height:205,
                    data:tabledata,
                    layout:"fitColumns",
                    columns:[
                        {title:"Name", field:"name", width:150},
                        {title:"Age", field:"age", align:"left", formatter:"progress"},
                        {title:"Favourite Color", field:"col"},
                        {title:"Date Of Birth", field:"dob", sorter:"date", align:"center"},
                    ],
                    rowClick:function(e, row){ //trigger an alert message when the row is clicked
                        alert("Row " + row.getData().id + " Clicked!!!!");
                    },
                });
            },
        });
    });