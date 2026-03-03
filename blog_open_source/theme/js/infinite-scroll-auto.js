/**
 * Auto-trigger "load more" on list pages when user scrolls near page bottom.
 * Requires jQuery and an existing `.j-load-more` button pattern in theme.
 */
(function ($) {
  "use strict";

  var config = {
    threshold: 200,
    loading: false,
    checkInterval: 500
  };

  function checkScroll() {
    if (config.loading) return;
    var windowHeight = $(window).height();
    var documentHeight = $(document).height();
    var scrollTop = $(window).scrollTop();
    if (scrollTop + windowHeight >= documentHeight - config.threshold) {
      triggerLoadMore();
    }
  }

  function triggerLoadMore() {
    var $loadMoreBtn = $(".j-load-more");
    if ($loadMoreBtn.length === 0 || $loadMoreBtn.hasClass("disabled")) {
      return;
    }

    config.loading = true;
    $loadMoreBtn.trigger("click");

    var checkLoading = setInterval(function () {
      var $parent = $loadMoreBtn.parent();
      if ($parent.hasClass("loading")) return;

      clearInterval(checkLoading);
      config.loading = false;

      if (!$loadMoreBtn.hasClass("disabled")) {
        setTimeout(checkScroll, 500);
      }
    }, config.checkInterval);
  }

  $(document).ready(function () {
    if (!$("body").hasClass("home") && !$("body").hasClass("blog")) {
      return;
    }

    setTimeout(checkScroll, 1000);
    $(window).on("scroll", checkScroll);
  });
})(jQuery);
