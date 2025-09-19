/*** INI MODAL PLUGIN */
(function($) {
    var methods = {
        open: function(options) {
            var settings = $.extend({
                image: null,
                title: null,
                message: null,
                captcha:false,
                button:null,
                // button : {
                //     type: 'danger',
                //     icon: 'sli-logout',
                //     text: 'Logout',
                //     // utk tambahan form setiap input|textarea|select wajib dikasi class payload
                //     form: `
                //      <small>Username*</small>
                //      <input class=payload name=username placeholder="Username" type=text>
                //      <small>Password*</small>
                //      <input class=payload name=password placeholder="Password" type=password>
                //     `
                // }, 
                timeOut: 0 // dalam milidetik, 0 berarti tidak auto close
            }, options),
            // object ini hanya experiment, wajib dikembangkan
            objUrl = $.parseMetaHttp(),
            dstUrl = objUrl.base ? objUrl.base : '' ;

            // buat properti modal backdrop di body
            var $backdrop = $('<div class="modal-backdrop"></div>').appendTo('body'),
                timeoutHandler,
                modalHTML = `
                <div class="modal-box-wrapper">
                    <div class="modal-box latar-bata">
                        <button class="close-btn"><i class=icon-sli-close></i></button>
                        <div class="modal-content text-center">
                            ${settings.image ? `<img src="${settings.image}">` : ''}
                            <div class="margin-bottom">
                                ${settings.title ? `<h5>${settings.title}</h5>` : ''}
                                ${settings.message ? `<p>${settings.message}</p>` : ''}
                                ${settings.button && settings.button.form ? `<div class="form">${settings.button.form}</div>` : ''}
                                ${settings.captcha ? `<small class="italic" data-text="Kode Validasi*">Kode Validasi*</small>
                                <div class="captcha">
                                    <img title="refresh captcha" src="${dstUrl}captcha/${Math.floor(Date.now())}.png">
                                    <input class="payload" type=text name=captcha placeholder="•••••" autocomplete="off">
                                </div>` : ''}     
                            </div>
                            ${settings.button ? `<button class="btn btn-${settings.button.type} rounded2x modal-btn" data-icon="${settings.button.icon}"> 
                                <i class="icon icon-${settings.button.icon}"></i><span class="text">${settings.button.text}</span>
                            </button>` : ''}
                        </div>
                    </div>
                </div>
            `;

            // Tampilkan modal
            $backdrop.html(modalHTML).fadeIn(200);
            $('body').addClass('no-scroll');
            $('.modal-box').fadeIn(200).addClass('show');

            // Setup timeout jika ada
            if (settings.timeOut > 0) {
                timeoutHandler = setTimeout(function() {
                    methods.close.call($backdrop);
                }, settings.timeOut);
            }

            // Bind close event
            $backdrop.find('.close-btn').off('click').on('click', function() {
                if (timeoutHandler) clearTimeout(timeoutHandler);
                methods.close.call($backdrop);
            });

            // Bind event Captcha
            if (settings.captcha) {
                $backdrop.find('div.captcha img').off('click').on('click', function() {
                    $(this).attr('src', dstUrl +'captcha/' + Math.floor(Date.now()) + '.png');
                });
            }
            // Bind confirm event
            if(settings.button)
                $backdrop.find('.modal-btn').off('click').on('click', function() {
                    let $btn  = $(this),
                        $icon = $(this).find(".icon");
                        
                    if ($btn.hasClass("loading")) return;
                    if (!$btn.hasClass("btn-ajax"))
                        $btn.addClass("btn-ajax");
                    $btn.addClass("loading").attr("disabled", true);
                    $icon.removeClass(`icon-${settings.button.icon}`).addClass("icon-spinner_2");
                    var $payload = {};
                    $backdrop.find('.payload').each(function() {                            
                        $payload[$(this).attr('name')] = $(this).val();
                    });
                    console.log($payload);
                    $.post(settings.button.url, $payload).done(function(response) {
                        console.log("Success!", response);
                        if (typeof settings.button.onSuccess === "function") {
                            settings.button.onSuccess(response)
                        }
                        methods.close.call($backdrop);
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        try {
                            // Coba parse respons JSON meskipun status error
                            const json = JSON.parse(jqXHR.responseText);
                            if(settings.captcha){
                                $backdrop.find('.modal-box').addClass('error-border');
                                let alert = $backdrop.find('small.italic');
                                alert.addClass('alert').text(json.message);
                                setTimeout(() => {
                                    $backdrop.find('.modal-box').removeClass('error-border');
                                    alert.removeClass('alert').text(alert.data('text'));
                                },1500);
                            }
                            console.warn(`⚠️ Gagal ${jqXHR.status} :`, json);
                        } catch (e) {
                            console.error("❌ Gagal terhubung ke Server:", errorThrown);
                        }
                    })
                    .always(function() {
                        $btn.removeClass("loading").prop("disabled", false);
                        $icon.removeClass("icon-spinner_2").addClass(`icon-${settings.button.icon}`);
                    });
                })
        },

        close: function() {
            // scan $backdrop modal jika ada, tutup dan remove
            return this.each(function() {
                $(this).find('.modal-box').fadeOut(200, () => {
                    $(this).fadeOut(200, () => {
                        $(this).remove();
                        $('body').removeClass('no-scroll');
                    });
                });
            });
        }
    };

    $.dynamicModal = function(methodOrOptions) {
        if (methods[methodOrOptions]) {
            return methods[methodOrOptions].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof methodOrOptions === 'object' || !methodOrOptions) {
            return methods.open.apply(this, arguments);
        } else {
            $.error('Method ' + methodOrOptions + ' does not exist on jQuery.dynamicModal');
        }
    };
})(jQuery);