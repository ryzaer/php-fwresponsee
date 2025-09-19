$(document).ready(function() {
    $(".tabs").each(function(a, e) {
        current_tabs = $(this), $(this).prepend('<div class="tab-nav line"></div>');
        var l = $(e).find(".tab-label");
        $(this).children(".tab-nav").prepend(l), $(this).children(".tab-item").each(function(a) {
            $(this).attr("id", "tab-" + (a + 1))
        }), $(".tab-nav").each(function() {
            $(this).children().each(function(a) {
                $(this).attr("href", "#tab-" + (a + 1))
            })
        }), $(this).find(".tab-nav a").click(function(a) {
            $(this).parent().children().removeClass("active-btn"), $(this).addClass("active-btn");
            var e = $(this).attr("href");
            return $(this).parent().parent().find(".tab-item").not(e).css("display", "none"), $(this).parent().parent().find(e).fadeIn(), !1
        })
    }), $(".aside-nav > ul > li ul").each(function(a, e) {
        var l = '<span class="count-number"> ' + $(e).find("li").length + "</span>";
        $(e).closest("li").children("a").append(l)
    }), $(".aside-nav > ul > li:has(ul)").addClass("aside-submenu"), $(".aside-nav > ul ul > li:has(ul)").addClass("aside-sub-submenu"), $(".aside-nav > ul > li.aside-submenu > a").attr("aria-haspopup", "true").click(function() {
        $(".aside-nav ul li.aside-submenu > ul").removeClass("show-aside-ul", "slow"), $(".aside-nav ul li.aside-submenu:hover > ul").toggleClass("show-aside-ul", "slow")
    }), $(".aside-nav > ul ul > li.aside-sub-submenu > a").attr("aria-haspopup", "true").click(function() {
        $(".aside-nav ul ul li > ul").removeClass("show-aside-ul", "slow"), $(".aside-nav ul ul li:hover > ul").toggleClass("show-aside-ul", "slow")
    }), $(".aside-nav-text").each(function(a, e) {
        $(e).click(function() {
            $(".aside-nav > ul").toggleClass("show-menu", "slow")
        })
    }), $(".nav-menu > ul > li ul").each(function(a, e) {
        var l = '<span class="count-number"> ' + $(e).find("li").length + "</span>";
        $(e).closest("li").children("a").append(l)
    }), $(".nav-menu > ul li:has(ul)").addClass("submenu"), $(".nav-menu > ul ul li:has(ul)").addClass("sub-submenu").removeClass("submenu"), $(".nav-menu > ul li.submenu > a").attr("aria-haspopup", "true").click(function() {
        $(".nav-menu > ul li.submenu > ul").removeClass("show-ul", "slow"), $(".nav-menu > ul li.submenu:hover > ul").toggleClass("show-ul", "slow")
    }), $(".nav-menu > ul ul > li.sub-submenu > a").attr("aria-haspopup", "true").click(function() {
        $(".nav-menu ul ul li > ul").removeClass("show-ul", "slow"), $(".nav-menu ul ul li:hover > ul").toggleClass("show-ul", "slow")
    }), $(".nav-text").click(function() {
        $(".nav-menu > ul").toggleClass("show-menu", "slow")
    }), $(function() {
        "placeholder" in document.createElement("input") == 0 && $("[placeholder]").focus(function() {
            var a = $(this);
            a.val() == a.attr("placeholder") && (a.val("").removeClass("placeholder"), a.hasClass("password") && (a.removeClass("password"), this.type = "password"))
        }).blur(function() {
            var a = $(this);
            "" != a.val() && a.val() != a.attr("placeholder") || ("password" == this.type && (a.addClass("password"), this.type = "text"), a.addClass("placeholder").val(a.attr("placeholder")))
        }).blur().parents("form").submit(function() {
            $(this).find("[placeholder]").each(function() {
                var a = $(this);
                a.val() == a.attr("placeholder") && a.val("")
            })
        })
    }), $(".tooltip-container").each(function() {
        $(this).hover(function() {
            var a = $(this).position(),
                e = $(this);
            a = e.offset();
            tip = $(this).find(".tooltip-content"), tip_top = $(this).find(".tooltip-content.tooltip-top"), tip_bottom = $(this).find(".tooltip-content.tooltip-bottom");
            var l = tip.height();
            tip.fadeIn("fast"), tip_top.css({
                top: a.top - l,
                left: a.left + e.width() / 2 - tip.outerWidth(!0) / 2
            }), tip_bottom.css({
                top: a.top,
                left: a.left + e.width() / 2 - tip.outerWidth(!0) / 2
            })
        }, function() {
            tip.fadeOut("fast")
        })
    });
    var a = window.location.href;
    $("a").filter(function() {
        return this.href == a
    }).parent("li").addClass("active-item");
    a = window.location.href;
    $(".aside-nav a").filter(function() {
        return this.href == a
    }).parent("li").parent("ul").addClass("active-aside-item");
    a = window.location.href;
    $(".aside-nav a").filter(function() {
        return this.href == a
    }).parent("li").parent("ul").parent("li").parent("ul").addClass("active-aside-item");
    a = window.location.href;
    $(".aside-nav a").filter(function() {
        return this.href == a
    }).parent("li").parent("ul").parent("li").parent("ul").parent("li").parent("ul").addClass("active-aside-item"), 
    /** BOX MENU */
    $(".box-menu li li").hover(function(){
        $(this).find('span').css({
            color:"white"
        })
    }),
    /** ACCORDION-1 */
    $('.accordion-1 .head').click(function() {
        $(this).toggleClass('active'),
        $(this).parent().find('.head h4').toggleClass('text-transform'),
        $(this).parent().find('.arrow').toggleClass('arrow-animate'),
        $(this).parent().find('.accobox').slideToggle(280)
    }),
    $('.accordion-1:last-child').click(function() {
        var __this = this;
        if (!$(this).find('.head').hasClass('active') && $(this).find('.head').css('border-radius') == '0px') {
            setTimeout(() => {
                $(__this).find('.head').css({'border-radius': '0px 0px 10px 10px'})
            }, 280)
        } else {
            $(__this).find('.head').css({'border-radius': '0px'})
        }
    }),
    /** scrolling to the top */
    $('#tothetop').on('click',function(e){
        e.preventDefault(),
        $('html, body').animate({scrollTop: '0px'}, 200)
    }),
    $(window).on('scroll', function() {
        var scrollPosition = $(window).scrollTop() + $(window).height();
        var documentHeight = $(document).height();
        var scrollPercentage = (scrollPosition / documentHeight) * 100;
        if (scrollPercentage >= 40) {
            $('#tothetop').addClass('show');
        }
        // Tombol akan hilang jika posisi scroll kembali ke atas (kurang dari 10% halaman)
        if ($(window).scrollTop() <= documentHeight * 0.1) {
            $('#tothetop').removeClass('show');
        }
    }),
    
    /** MODAL */
    $('.modal-logout-btn').on('click',function(e) {
        e.preventDefault();
        $('.modal-backdrop').dynamicModal({
            title: 'Logout',
            type: 'warning',
            message: 'Ingin Keluar dari aplikasi?',
            image: 'assets/image/wewenang.webp',
            buttonText: 'Ya, Keluarkan aku',
            // timeOut : 3000,
            onConfirm: function() {
                console.log('Logout diproses...');
                window.location.href = './'
                
            }
        });
    }),
    $('.modal-login-btn').on('click',function() {
        $.ajax({
            url: 'assign',
            type: 'POST',
            data: {name: 'Riza'},
            // beforeSend: function() {
            //     // Menampilkan loading spinner
            //     $('#loading').show();
            //     // Menonaktifkan tombol submit
            //     $('#submit-btn').prop('disabled', true);
            //     console.log('Request sedang diproses...');
            // },
            success: function(response) {
                console.log(response);
                if(response.result.status === true){
                    window.location.href = './member';
                }else{
                    $('.modal-backdrop').dynamicModal({
                        title: 'Login Info',
                        type: 'info',
                        message: 'Ini untuk login',
                        image: 'assets/image/wewenang.webp',
                        buttonText: 'Logout',
                        // timeOut : 3000,
                        onConfirm: function() {
                            console.log('Logout diproses...');
                            // Arahkan ke logout atau kirim request POST
                            
                        }
                    });
                }
                
            }
            // ,complete: function() {
            //     // Menyembunyikan loading spinner
            //     $('#loading').hide();
                
            //     // Mengaktifkan kembali tombol submit
            //     $('#submit-btn').prop('disabled', false);
            // },
            // error: function(xhr) {
            //     console.error('Error:', xhr.statusText);
            // }
        });

        
    }),
    // $('.close-btn').click(closeModal),
    
    /** Mencegah modal tertutup saat klik backdrop/modal*/
    $('.modal-backdrop, .modal-box').click(function (e) {
        e.stopPropagation()
    })

    /***LOGIN */
    // t.ajax({
    //     url: 'assign/login',
    //     type: 'POST',
    //     dataType: 'json',
    //     data: { key1: 'value1', key2: 'value2' }, // data yang dikirim
    //     success: function(response) {
    //         console.log(response);
    //     },
    //     error: function(xhr, status, error) {
    //         console.error('Error:', error);
    //     }
    // });
    // var session = $.sessionHandler({
    //     storageType: 'local'
    // });
    // session.set('isLogin', true);
    // session.set('user', {name: 'Riza', email: 'riza@example.com'});
    // session.set('token', 'h89879hy9891h298u02193j9');
    // session.remove('isLogin');
    // session.clearAll();
    // var isLogin = session.get('isLogin');
    // var userData = session.get('user');
    // var token = session.get('token');
    // console.log(isLogin, userData,token);
    

});

