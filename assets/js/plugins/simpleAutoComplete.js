/** Simple Autocomplete 
 * 
 *  <div class="autocomplete-box">
        <input type="text" id="country-input" class="autocomplete-input" placeholder="Find Country" autocomplete="off">
        <span class="clear-icon"><i class="icon-sli-close"></i></span>
    </div>
 * (optional) autocomplete="off" jika tidak ingin interupsi dari autocomplete browser bawaan
 * 
 * $('#country-input').simpleAutocomplete({
    data: [
        [
            id: 1,
            label: 'Indonesia',
            thumbnail: 'https://upload.wikimedia.org/wikipedia/en/thumb/9/9f/Flag_of_Indonesia.svg/1200px-Flag_of_Indonesia.svg.png'
        ],
        [
            id: 2,
            label: 'Malaysia',
            thumbnail: 'https://upload.wikimedia.org/wikipedia/en/thumb/b/b9/Flag_of_Malaysia.svg/1200px-Flag_of_Malaysia.svg.png'
        ]
    ],
    onNotExistLabel: function(params) {
        var newId = params.latestId + 1;
        var newLabel = params.label;
        var dataArr = params.data;
        alert(newLabel + ' is not Exist! Menambah data ' + $(this).val());
        dataArr.push({
            id: newId,
            label: newLabel,
            // (opsional) jika data ada key thumbnail, berikan ini utk default
            thumbnail: 'assets/img/no-image.png'
        });
        // set data-id agar input tetap menyimpan id baru
        $(this).data("id", newId);
        // agar event memakai data yang sudah di update
        $(this).trigger('keyup');        
    }});
 * Thumbnail is optional
 * input will return data-id and text
 * 
*/
(function ($) {
    $.fn.simpleAutocomplete = function (options) {
        var settings = $.extend({
                data: [],
                onNotExistLabel:null
            }, options),
            $input = $(this),
            $suggestions = $('<div class="autocomplete-suggestions"></div>').insertAfter($input),
            selectedIndex = -1;
                
        function escapeRegex(text) {
            return text.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
        }
        function highlightMatch(text, term) {
            var regex = new RegExp("(" + escapeRegex(term) + ")", "ig");
            return $("<div/>").text(text).html().replace(regex, '<span class="highlight">$1</span>');
        }

        function filterSuggestions(query) {
            var results = $.grep(settings.data, function (item) {
                return item.label.toLowerCase().indexOf(query.toLowerCase()) !== -1;
            });
            return results;
        }

        function renderSuggestions(query) {
            var matches = filterSuggestions(query);
            $suggestions.empty().hide();
            selectedIndex = -1;
            if (query && matches.length !== 0) {
                $.each(matches, function (index, item) {
                    var $item = $('<div class="autocomplete-item"></div>')
                                .attr("data-id", item.id)
                                .attr("data-label", item.label);
                    if (item.thumbnail) {
                        var $img = $('<img class="thumbnail">').attr("src", item.thumbnail);
                        $item.append($img);
                    }
                    $item.append('<span class="label-text">' + highlightMatch(item.label, query) + '</span>');
                    $suggestions.append($item);
                });
                $suggestions.show();
            }
        }
        function moveSelection(step) {
            var $items = $suggestions.children(".autocomplete-item");

            if ($items.length === 0) return;

            selectedIndex += step;

            if (selectedIndex < 0) selectedIndex = $items.length - 1;
            if (selectedIndex >= $items.length) selectedIndex = 0;

            $items.removeClass("hover");
            var $selected = $items.eq(selectedIndex).addClass("hover");

            var scrollTop = $suggestions.scrollTop();
            var containerHeight = $suggestions.height();
            var itemTop = $selected.position().top;
            var itemHeight = $selected.outerHeight();

            if (itemTop + itemHeight > containerHeight) {
                $suggestions.scrollTop(scrollTop + itemTop + itemHeight - containerHeight);
            } else if (itemTop < 0) {
                $suggestions.scrollTop(scrollTop + itemTop);
            }
        }        
        $input.on("keydown", function (e) {
            var key = e.which;
            if (key === 38) { // Up
                moveSelection(-1);
                e.preventDefault();
            } else if (key === 40 || key === 9) { // Down
                moveSelection(1);
                e.preventDefault();
            } else if (key === 13) { // Enter
                selectedIndex = selectedIndex < 0 ? 0 : selectedIndex;
                var $selectedItem = $suggestions.children(".autocomplete-item").eq(selectedIndex);
                if ($selectedItem.length) {
                    $input.val($selectedItem.data("label"))
                        .data("id", $selectedItem.data("id"));
                    $suggestions.hide();
                } else {
                    // Tidak memilih suggestion, cek manual di blur
                    $input.blur(); // trigger blur untuk validasi
                }
                e.preventDefault();
            } else if (key === 27 ) { // Esc key
                $suggestions.hide();
            }
        });
        $input.on("keyup", function (e) {
            if ($.inArray(e.which, [13, 38, 40, 27, 9]) === -1) {
                renderSuggestions($input.val());
            }
        });
        $suggestions.on("mousedown", ".autocomplete-item", function (e) {
            e.preventDefault(); 
            $input.val($(this).data("label")).data("id",$(this).data("id"));
            $suggestions.hide();
        });
        $input.on("blur", function () {
            // cek apakah value input cocok dengan data yang ada di data list
            let matchedItem = null;
            $.each(settings.data, function (index, item) {
                if ($.trim(item.label).toLowerCase() === $.trim($input.val()).toLowerCase())
                    matchedItem = item;
            });
            if (!matchedItem) {
                // event onNotExistLabel adalah callback untuk menambah data
                if ($input.val() && typeof settings.onNotExistLabel === 'function') {
                    var latest = settings.data.reduce(function (prev, current) {
                        return (prev.id > current.id) ? prev : current;
                    }, { id: 0 });
                    settings.onNotExistLabel.call($input, {
                        label: $input.val(),
                        latestId: latest.id,
                        data: settings.data
                    });
                } else {
                    $input.data("id", null);
                    $input.val("");
                    $clearIcon.hide();
                }
            }
        });

        $(document).on("click", function (e) {
            if (!$(e.target).closest($input).length && !$(e.target).closest($suggestions).length)
                $suggestions.hide()
        });
        // icon reset
        var $clearIcon = $input.siblings(".clear-icon");
        $input.on("input", function () {
            if ($(this).val().length > 0) {
                $clearIcon.show();
            } else {
                $clearIcon.hide();
                $suggestions.hide();
            }
        });

        $clearIcon.on("click", function () {
            $input.val("").data("id", null).focus();
            $(this).hide();
            $suggestions.hide();
        });
    };
})(jQuery);