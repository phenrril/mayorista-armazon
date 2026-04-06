/*!
    * Start Bootstrap - SB Admin v6.0.2 (https://startbootstrap.com/template/sb-admin)
    * Copyright 2013-2020 Start Bootstrap
    * Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-sb-admin/blob/master/LICENSE)
    */
    (function($) {
    "use strict";

    // Add active state to sidbar nav links
    var path = window.location.href; // because the 'href' property of the DOM element is the absolute path
    var $body = $("body");
    var $sidebarToggle = $("#sidebarToggle");
    var $sidebarLinks = $("#layoutSidenav_nav .sb-sidenav a.nav-link");
    var $layoutContent = $("#layoutSidenav_content");
    var $mobileNavOverlay = $("#mobileNavOverlay");

    if (!$mobileNavOverlay.length) {
        $mobileNavOverlay = $('<div id="mobileNavOverlay" class="mobile-nav-overlay" aria-hidden="true"></div>');
        $body.append($mobileNavOverlay);
    }

    $sidebarLinks.each(function() {
        if (this.href === path) {
            $(this).addClass("active");
        }
    });

    function isMobileViewport() {
        return window.innerWidth < 992;
    }

    function syncSidebarState() {
        var isExpanded = $body.hasClass("sb-sidenav-toggled");
        $sidebarToggle.attr("aria-expanded", isExpanded ? "true" : "false");
        $mobileNavOverlay.toggleClass("is-visible", isExpanded && isMobileViewport());
    }

    function closeMobileSidebar() {
        if (!isMobileViewport()) {
            return;
        }

        $body.removeClass("sb-sidenav-toggled");
        syncSidebarState();
    }

    // Toggle the side navigation
    $sidebarToggle.on("click", function(e) {
        e.preventDefault();
        $body.toggleClass("sb-sidenav-toggled");
        syncSidebarState();
    });

    $sidebarLinks.on("click", function() {
        closeMobileSidebar();
    });

    $mobileNavOverlay.on("click", function() {
        closeMobileSidebar();
    });

    function syncMobileSidebar() {
        if (isMobileViewport()) {
            $body.removeClass("sb-sidenav-toggled");
        }

        syncSidebarState();
    }

    syncMobileSidebar();
    $(window).on("resize", syncMobileSidebar);
})(jQuery);
