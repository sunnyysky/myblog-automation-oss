/**
 * Defer comment-related logic until the comment area enters viewport.
 * Dispatches a custom `loadComments` event once.
 */
(function () {
  "use strict";

  var commentArea = document.querySelector("#comments");
  if (!commentArea || !("IntersectionObserver" in window)) {
    return;
  }

  var observer = new IntersectionObserver(
    function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        document.dispatchEvent(new CustomEvent("loadComments"));
        observer.unobserve(entry.target);
      });
    },
    { rootMargin: "100px" }
  );

  observer.observe(commentArea);
})();
