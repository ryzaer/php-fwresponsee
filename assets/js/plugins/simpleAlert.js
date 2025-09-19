/** ini contoh simple Alert 
 * $.simpleAlert({
        title: 'Sukses',
        message: 'Data baru telah di tambahkan!',
        type: 'success',
        timeOut: 1500
    })
 * 
 * 
*/
(function($) {
    $.simpleAlert = function(options) {
        var settings = $.extend({
            type: '', // success, error, warning, info
            title: 'Alert',
            message: 'This is a simple alert!',
            timeOut: 0, // in milliseconds, 0 = manual close
            btnText: 'OK',
            onClose: null, // this callback
            // Aktif Button disini
            showYesNo: false,
            btnYes: 'Yes',
            onYes: null, // this callback
            btnNo: 'No',
            onNo: null // this callback
        }, options);

        $('.simple-alert-overlay').remove();

        // Icon Setup
        var iconHTML='',typeClass='';
        switch (settings.type) {
            case 'success':
                iconHTML  = '<div class="animated_logo"><i class="icon-check_circle"></i></div>';
                typeClass = 'success';
                break;
            case 'danger':
                iconHTML  = '<div class="animated_logo"><i class="icon-warning""></i></div>';
                typeClass = 'danger'
                break;
            case 'error':
                iconHTML  = '<div class="animated_logo"><i class="icon-cancel_circle"></i></div>';
                typeClass = 'error';
                break;
            case 'warning':
                iconHTML  = '<div class="animated_logo"><i class="icon-warning"></i></div>';
                typeClass = 'warning';
                break;
            case 'info':
                iconHTML  = '<div class="animated_logo"><i class="icon-information_black"></i></div>';
                typeClass = 'info';
                break;
            default:
                iconHTML = '';
        }

        var box = $('<div class="simple-alert-box"></div>').addClass(typeClass),
            title = $('<h4></h4>').text(settings.title),
            message = $('<p></p>').html(settings.message),
            overlay = $('<div class="simple-alert-overlay"></div>'),
            iconElement = $(iconHTML).addClass(typeClass);

        box.append(iconElement, title, message);

        // Button Config
        if (settings.showYesNo) {
            var btnYes = $('<button class="btn btn-linear btn-success"></button>').text(settings.btnYes),
                btnNo = $('<button class="btn btn-linear btn-warning"></button>').text(settings.btnNo);

            btnYes.on('click', function() {
                overlay.fadeOut(200, function() {
                    overlay.remove();
                    if (typeof settings.onYes === 'function') settings.onYes.apply(box);
                });
            });

            btnNo.on('click', function() {
                overlay.fadeOut(200, function() {
                    overlay.remove();
                    if (typeof settings.onNo === 'function') settings.onNo.apply(box);
                });
            });

            box.append(btnYes, btnNo);
        } else if (settings.timeOut === 0) {
            var btnType  = typeClass ? ( typeClass === 'error' ? 'danger' : typeClass ) : 'info',
                btnClose = $(`<button class="btn btn-linear btn-${btnType}"></button>`).text(settings.btnText);
            btnClose.on('click', function() {
                overlay.fadeOut(200, function() {
                    overlay.remove();
                    if (typeof settings.onClose === 'function') settings.onClose();
                });
            });
            box.append(btnClose);
        }

        overlay.append(box);
        $('body').append(overlay);

        if (settings.timeOut > 0) {
            setTimeout(function() {
                overlay.fadeOut(200, function() {
                    overlay.remove();
                    if (typeof settings.onClose === 'function') settings.onClose();
                });
            }, settings.timeOut);
        }
    };
})(jQuery);