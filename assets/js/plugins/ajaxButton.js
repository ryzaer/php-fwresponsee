/* Contoh penggunaan button ajax
 * $('#btnLogout').ajaxButton({
 *     url : "https://jsonplaceholder.typicode.com/posts",
 *     payload: { userId: 1, message: 'Hello' },
 *     onSuccess: function(res, btn) {
 *         alert(btn.data('alert'));
 *         console.log("Sukses dari tombol:", btn.text(), res);
 *     },
 *     onError: function(err, btn) {
 *         console.error("Gagal dari tombol:", btn.text(), err);
 *     }
 * });
 */
!function($) {
  $.fn.ajaxButton = function(options) {
    if(typeof options !== "string" && methods[options]) {
      return events[option].apply(this, Array.prototype.slice.call(arguments, 1));
    }
    return this.each(function() {
      let $btn    = $(this),
          $icon   = $btn.find(".icon"),
          icon    = $btn.data("icon") || "check",
          url     = $btn.data("url") || options.url || false,
          method  = $btn.data("method") || options.method || "GET",
          alert   = $btn.data("alert") || options.alert;

      $btn.on("click", function() {
        if ($btn.hasClass("loading")) return;

        if (!$btn.hasClass("btn-ajax")) {
          $btn.addClass("btn-ajax");
        }

        $btn.addClass("loading").prop("disabled", true);
        $icon.removeClass(`icon-${icon}`).addClass("icon-spinner_2");

        $.ajax({
          url: url,
          method: method,
          data: options.payload || { example: "data" },

          success: function(response) {
            if (typeof options.onSuccess === "function") {
              options.onSuccess(response, $btn);
            }
          },

          error: function(error) {
            if (typeof options.onError === "function") {
              options.onError(error, $btn);
            }
          },

          complete: function() {
            $btn.removeClass("loading").prop("disabled", false);
            $icon.removeClass("icon-spinner_2").addClass(`icon-${icon}`);
          }
        });
      });
    });
  };
}(jQuery);