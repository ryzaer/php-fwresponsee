/** Simple Paginate 
 * 
// cara panggil plugin
$("#myTable").simplePaginate({
    data: data,
    rowsPerPage: 5,
    currentPage: 3, // (optional) misal ingin langsung kehalaman 3
    columns: ["no", "nama", "alamat"],
    onSuccess: function (data, tbody) {
        $(this).find('tbody td').on('click', function () {
            $('tr').removeClass('active');
            $(this).parent('tr').addClass('active');
        });
    }
});
// Jika data API sudah diperbarui tetap di currentPage aktif
$("#myTable").simplePaginate('updateData', newData);
// Jika data API sudah diperbarui dan ingin pergi ke halaman 3
$("#myTable").simplePaginate('updateData', newData, 3);
// Jika ingin mendestroy plugin
$("#myTable").simplePaginate('destroy');

CONTOH untuk Costum Row

$("#myTable").simplePaginate({
    data: data,
    rowsPerPage: 5,
    renderRow: function (row, index) {
        return `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>${row.nama}</td>
                <td>${row.alamat}</td>
            </tr>
        `;
    },
    onSuccess: function () {
        // custom event setelah render
    }
});

*/

(function ($) {
    $.fn.simplePaginate = function (optionsOrMethod) {
        if (typeof optionsOrMethod === 'string') {
            const method = optionsOrMethod;
            const args = Array.prototype.slice.call(arguments, 1);
            const instance = this.data('simplePaginate');

            if (instance && typeof instance[method] === 'function') {
                return instance[method].apply(instance, args);
            }
            return this;
        }

        const settings = $.extend({
            data: [],
            rowsPerPage: 5,
            currentPage: 1,
            columns: [],
            onSuccess: ()=>{}
        }, optionsOrMethod);

        const table = this;
        const pagination = $('<div class="align-content-center simple-pg"></div>').insertAfter(table);
        let currentPage = settings.currentPage;

        function renderPage(page) {
            const startIndex = (page - 1) * settings.rowsPerPage;
            const endIndex = startIndex + settings.rowsPerPage;
            const pageData = settings.data.slice(startIndex, endIndex);

            let tbody = table.find("tbody");
            if (tbody.length === 0) {
                tbody = $("<tbody></tbody>").appendTo(table);
            }

            tbody.empty();

            pageData.forEach(rowData => {
                const row = $("<tr></tr>");
                settings.columns.forEach(column => {
                    row.append(`<td>${rowData[column]}</td>`);
                });
                tbody.append(row);
            });

            settings.onSuccess.call(table, pageData);
        }

        function renderPagination() {
            const totalPages = Math.ceil(settings.data.length / settings.rowsPerPage);

            pagination.empty();

            if (settings.data.length <= settings.rowsPerPage) {
                pagination.hide();
            } else {
                pagination.show();
                for (let i = 1; i <= totalPages; i++) {
                    const button = $(`<button data-page="${i}">${i}</button>`);
                    if (i === currentPage) button.addClass("active");
                    pagination.append(button);
                }
            }
        }

        pagination.on("click", "button", function () {
            currentPage = parseInt($(this).attr("data-page"));
            renderPage(currentPage);
            renderPagination();
        });

        this.data('simplePaginate', {
            updateData: function (newData, newPage) {
                settings.data = newData;

                if (typeof newPage !== 'undefined' && !isNaN(newPage)) {
                    currentPage = newPage;
                }

                const totalPages = Math.ceil(settings.data.length / settings.rowsPerPage);
                if (currentPage > totalPages) {
                    currentPage = totalPages > 0 ? totalPages : 1;
                }

                renderPage(currentPage);
                renderPagination();
            },
            destroy: function () {
                pagination.remove();
                table.find('tbody').remove();
                table.removeData('simplePaginate');
            }
        });

        renderPage(currentPage);
        renderPagination();

        return this;
    }
})(jQuery);