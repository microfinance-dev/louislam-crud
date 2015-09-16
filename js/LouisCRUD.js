/**
 * Created by Louis Lam on 8/15/2015.
 *
 */
/// <reference path="jquery.d.ts" />
/// <reference path="jquery.dataTables-1.9.4.d.ts" />
var LouisCRUD = (function () {
    function LouisCRUD() {
        this.validateFunctions = [];
        this.errorMsgs = [];
        var self = this;
        $(document).ready(function () {
            // Disable Datatables' alert!
            $.fn.dataTableExt.sErrMode = 'throw';
            // Ajax Submit Form
            $("form.ajax").submit(function () {
                // Clear all msgs
                self.errorMsgs = [];
                var ok = true;
                for (var i = 0; i < self.validateFunctions.length; i++) {
                    if (self.validateFunctions[i]() == false) {
                        ok = false;
                    }
                }
                if (!ok) {
                    var str = "";
                    for (var i = 0; i < self.errorMsgs.length; i++) {
                        str += self.errorMsgs[i] + "\n";
                    }
                    alert(str);
                    return false;
                }
                $.ajax({
                    url: $(this).attr("action"),
                    type: $(this).data("method"),
                    data: $(this).serialize()
                }).done(function (result) {
                    alert(result);
                    if (self.ajaxFormCallback != null) {
                        self.ajaxFormCallback(result);
                    }
                });
                return false;
            });
            // Active Menu Item
            $(".main-sidebar ul li").each(function () {
                if (location.href.indexOf($(this).find("a").attr("href")) >= 0) {
                    $(this).addClass("active");
                }
            });
            self.refresh();
        });
    }
    LouisCRUD.prototype.addValidateFunction = function (func) {
        this.validateFunctions.push(func);
    };
    LouisCRUD.prototype.addErrorMsg = function (msg) {
        this.errorMsgs.push(msg);
    };
    LouisCRUD.prototype.initListView = function ($isAjax, tableURL) {
        var self = this;
        var data = {
            "pageLength": 25,
            "paging": true,
            "ordering": true,
            "info": true,
            "drawCallback": function (settings) {
                self.refresh();
            }
        };
        if ($isAjax) {
            data.serverSide = true;
            data.processing = true;
            data.searching = false;
            data.ajax = {
                url: tableURL,
                type: "POST"
            };
        }
        $(document).ready(function () {
            self.table = $('#louis-crud-table').DataTable(data);
            $('#louis-crud-table tfoot th').each(function () {
                if ($(this).index() == 0) {
                    return;
                }
                var title = $('#louis-crud-table thead th').eq($(this).index()).text();
                $(this).html('<input type="text" placeholder="Search ' + title + '" class="filter-box" />');
            });
            // Apply the search
            self.table.columns().every(function () {
                var that = this;
                $('input', this.footer()).on('keyup change', function () {
                    that.search(this.value).draw();
                });
            });
            // Column Filter
            self.columnFilter();
        });
    };
    LouisCRUD.prototype.columnFilter = function () {
        var self = this;
        $(".column-filter a").click(function (e) {
            e.stopPropagation();
        });
        $(".column-filter [type=checkbox]").change(function (e) {
            e.preventDefault();
            var checked = $(this).is(":checked");
            var column = self.table.column($(this).data('column'));
            column.visible(checked);
        });
    };
    LouisCRUD.prototype.setAjaxFormCallback = function (callback) {
        this.ajaxFormCallback = callback;
    };
    LouisCRUD.prototype.refresh = function () {
        var self = this;
        // Delete Button
        $(".btn-delete:not(.ok)").click(function () {
            var result = window.confirm("Are you sure?");
            if (result) {
                var btn = $(this);
                var deleteLink = $(this).data("url");
                $.ajax({
                    url: deleteLink,
                    type: "DELETE"
                }).done(function (data) {
                    btn.parents('tr').remove();
                    // self.table.ajax.reload();
                }).fail(function (data) {
                    console.log(data);
                });
            }
        }).addClass("ok");
    };
    return LouisCRUD;
})();
//# sourceMappingURL=LouisCRUD.js.map