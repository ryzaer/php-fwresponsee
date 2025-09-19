var App = (function () {
  var initializedModules = {},
      // list pages sementara utk deteksi manual modul
      modulePage = ['member'],
      // inisiasi di tag experimental meta[name=x-http]
      parseHttp = parseMetaHttp(),
      timestamp = Math.floor(Date.now() / 1000),
      // fungsi loader bar
      loadingBar = {
          start: function() {
              $('.preloading').show();
              $('#loading-bar').css({
                  width: '0%',
                  opacity: 1
              }).animate({
                  width: '80%'
              }, 1500);
          },
          finish: function() {
              $('#loading-bar').stop().animate({
                  width: '100%'
              }, 700, function() {
                  $(this).fadeOut(100, function() {
                    $(this).css('width', '0%');
                    $('.preloading').hide();
                    $('.wrapper').fadeIn();
                  });
              })
          },
          reset: function() {
              $('#loading-bar').stop(true, true).css({
                  width: '0%',
                  opacity: 1
              }).show();
          }
      }
  function parseMetaHttp() {
    const meta = document.querySelector('meta[name="x-http"]');
    if (meta) {
        const content = meta.getAttribute('content');
        // dapatkan info x-http
        return Object.fromEntries(
            content.split(',').map(part => {
                const [key, value] = part.split('=').map(s => s.trim());
                return [key, value];
            })
        );
    }else{
        return [];
    }
  }
  function loadExternalScript(src, callback) {
    src = src || parseHttp.base + 'assets/js/jquery.min.js';
    const el = document.createElement('script');
    // CDN JQuery
    el.src = src;
    el.async = true;
    el.onload = function () {
        // load plugin wajib
        loadPlugins(['jquery-ui','widget','ajaxButton','dynamicModal','simpleAlert','simpleSelect']).then(() => {
            init(),
            loadStyles()
        })
    };
    el.onerror = function () {
      console.error('Failed to load script:', src);
    };

    const firstScript = document.querySelector('script');
    if (firstScript) {
      firstScript.parentNode.insertBefore(el, firstScript);
    } else {
      document.head.appendChild(el);
    }
  }
  function loadScript(url) {
    return $.ajax({
      url: url,
      dataType: "script",
      cache: true
    });
  }
  function loadCSS(url) {
    return $('<link>', {
      rel: 'stylesheet',
      type: 'text/css',
      href: url
    }).appendTo('head');
  }
  function checkCSSExistsAndLoad(url) {
    fetch(url, { method: 'HEAD' })
      .then(res => {
        if (res.ok) {
          loadCSS(url);
        }
      })
      .catch(() => {});
  }
  function loadPlugins(plugins) {
    const promises = plugins.map(function (plugin) {
      const jsPromise = loadScript(parseHttp.base + 'assets/js/plugins/' + plugin + '.min.js?_=' + timestamp);
      checkCSSExistsAndLoad(parseHttp.base + 'assets/css/plugins/' + plugin + '.min.css?_=' + timestamp);
      return jsPromise;
    });
    return Promise.all(promises);
  }
  function init() {
    loadingBar.start();
    // path untuk route error 
    var path = 'error';
    if(parseHttp.code === '200') {
      // inisiasi jika page ada dalam list modulePage
      path = 'home';
      modulePage.forEach((page)=>{
        if(parseHttp.path == page)
            path = parseHttp.path
      })
    }
    if (path && !initializedModules[path]) {
      var modulePath = parseHttp.base + 'templates/modules/' + path + '.js?_=' + timestamp;
      loadScript(modulePath).then(function () {
        var page = App.pages[path];
        if (page) {
          if (page.title) {
            document.title = page.title;
          }
          if (Array.isArray(page.plugins)) {
            loadPlugins(page.plugins).then(() => {
              if (typeof page.init === 'function') {
                page.init();
                initializedModules[path] = true;
              }
            });
          }else{
            if (typeof page.init === 'function') {
              page.init();
              initializedModules[path] = true;
            }
          }
        }
      });
    }
    loadingBar.finish();
  }
  // MODAl LOGIN
  function getModalLogin() {
            $('.modal-backdrop').dynamicModal({
                image: parseHttp.base + 'assets/image/wewenang.webp',
                message: 'Login Polresta Pontianak',
                captcha: true,
                button : {
                    url : parseHttp.base + 'assign/login',
                    type: 'info',
                    icon: 'sli-login',
                    text: 'Login',
                    form: `
                            <small>Username*</small>
                            <input class=payload name=username placeholder="Username" type=text>
                            <small>Password*</small>
                            <input class=payload name=password placeholder="Password" type=password>
                            `,
                    onSuccess: function(resp) {
                        // redirect ke assign page akses 
                        // console.log(resp);
                        window.location.href = parseHttp.base + (resp.result.assign ? resp.result.assign : '') ;
                        session.set('token',resp.result.token)
                    }
                }
            })
        }

  // default js style Myresponsee templates
  function loadStyles() {
    // login storage
    let session = $.sessionHandler();
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
    /** Animasi automatis scrolling ke atas */
    $('#tothetop').animateToTheTop(),
    /** MODAL */
    $('.modal-login-btn').on('click',function() {        
        // session.remove('token')
        if(session.get('token')){
            $.ajax({
                url: parseHttp.base + 'assign/status',
                type: 'POST',
                data: {token: session.get('token')},
                success: function(response) {
                    // login ke assign akses page
                    // console.log(response);
                    if(response.result){
                        if(response.result.token)
                            session.remove('token');
                        session.set('token',response.result.token),
                        window.location.href = parseHttp.base + (response.result.assign || '')
                    }
                },
                error: function(xhr) {
                    // console.log(JSON.parse(xhr.responseText)),
                    session.remove('token'),
                    getModalLogin()
                }
            });
        }else{
            getModalLogin()
        }
    }),
    $('.modal-logout-btn').on('click',function(e) {
        e.preventDefault();
        $('.modal-backdrop').dynamicModal({
            image: parseHttp.base + 'assets/image/wewenang.webp',
            message: 'Ingin Keluar dari aplikasi?',
            button : {
                url : parseHttp.base + 'assign/logout',
                type: 'warning',
                icon: 'sli-logout',
                text: 'Logout',
                onSuccess: function(resp) {
                    // logout redirect ke index
                    session.remove('token')
                    window.location.href = parseHttp.base ;
                }
            }
        });
    })
  }
  return {
    dispatch:loadExternalScript,
    init:init,
    pages: {}
  };
})();
App.dispatch()