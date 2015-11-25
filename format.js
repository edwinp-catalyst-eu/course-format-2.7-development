// Javascript functions for TURFORLAG course format

M.course = M.course || {};

M.course.format = M.course.format || {};

/**
 * Get sections config for this format
 *
 * The section structure is:
 * <ul class="turforlag">
 *  <li class="section">...</li>
 *  <li class="section">...</li>
 *   ...
 * </ul>
 *
 * @return {object} section list configuration
 */
M.course.format.get_config = function() {
    return {
        container_node : 'ul',
        container_class : 'turforlag',
        section_node : 'li',
        section_class : 'section'
    };
}

/**
 * Swap section
 *
 * @param {YUI} Y YUI3 instance
 * @param {string} node1 node to swap to
 * @param {string} node2 node to swap with
 * @return {NodeList} section list
 */
M.course.format.swap_sections = function(Y, node1, node2) {
    var CSS = {
        COURSECONTENT : 'course-content',
        SECTIONADDMENUS : 'section_add_menus'
    };

    var sectionlist = Y.Node.all('.'+CSS.COURSECONTENT+' '+M.course.format.get_section_selector(Y));
    // Swap menus.
    sectionlist.item(node1).one('.'+CSS.SECTIONADDMENUS).swap(sectionlist.item(node2).one('.'+CSS.SECTIONADDMENUS));
}

/**
 * Process sections after ajax response
 *
 * @param {YUI} Y YUI3 instance
 * @param {array} response ajax response
 * @param {string} sectionfrom first affected section
 * @param {string} sectionto last affected section
 * @return void
 */
M.course.format.process_sections = function(Y, sectionlist, response, sectionfrom, sectionto) {
    var CSS = {
        SECTIONNAME : 'sectionname'
    },
    SELECTORS = {
        SECTIONLEFTSIDE : '.left .section-handle img'
    };

    if (response.action == 'move') {
        // If moving up swap around 'sectionfrom' and 'sectionto' so the that loop operates.
        if (sectionfrom > sectionto) {
            var temp = sectionto;
            sectionto = sectionfrom;
            sectionfrom = temp;
        }

        // Update titles and move icons in all affected sections.
        var ele, str, stridx, newstr;

        for (var i = sectionfrom; i <= sectionto; i++) {
            // Update section title.
            sectionlist.item(i).one('.'+CSS.SECTIONNAME).setContent(response.sectiontitles[i]);

            // Update move icon.
            ele = sectionlist.item(i).one(SELECTORS.SECTIONLEFTSIDE);
            str = ele.getAttribute('alt');
            stridx = str.lastIndexOf(' ');
            newstr = str.substr(0, stridx +1) + i;
            ele.setAttribute('alt', newstr);
            ele.setAttribute('title', newstr); // For FireFox as 'alt' is not refreshed.

            // Remove the current class as section has been moved.
            sectionlist.item(i).removeClass('current');
        }
        // If there is a current section, apply corresponding class in order to highlight it.
        if (response.current !== -1) {
            // Add current class to the required section.
            sectionlist.item(response.current).addClass('current');
        }
    }
}

$(function() {

    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) {
            return parts.pop().split(";").shift();
        } else {
            return null;
        }
    }

    function getUrlVars() {
        var vars = [], hash;
        var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');

        for(var i = 0; i < hashes.length; i++)
        {
            hash = hashes[i].split('=');
        hash[1] = unescape(hash[1]);
        vars.push(hash[0]);
            vars[hash[0]] = hash[1];
        }

        return vars;
    }

    var courseID = getUrlVars()["id"];
    var toptab = getCookie("turtab" + courseID) ? getCookie("turtab" + courseID) : 0;
    var subtab = getCookie("tursubtab" + courseID) ? getCookie("tursubtab" + courseID) : false;

    $('#tabs').tabs({
        active: toptab,
        activate: function(e, ui) {
            document.cookie = "turtab" + courseID + "=" + (ui.newTab.attr('data-turtab'));
        }
    });

    $('.turforlag_subtabs').tabs({
        active: subtab,
        collapsible: true,
        activate: function(e, ui) {
            ui.newPanel.css('margin-top', ui.newTab.position().top);
            document.cookie = "tursubtab" + courseID + "=" + (ui.newTab.attr('data-tursubtab'));
        },
        create: function(e, ui) {
            if (ui.tab.position()) {
                ui.panel.css('margin-top', ui.tab.position().top);
            }
            if (ui.tab.hasClass('turforlag_directlink')) {
                e.preventDefault();
            }
        },
        beforeLoad: function(e, ui) {
            if (ui.tab.hasClass('turforlag_directlink')) {
                e.preventDefault();
            }
        },
        beforeActivate: function(e, ui) {
            if (ui.newTab.hasClass('turforlag_directlink')) {
                e.preventDefault();
                window.location.href = ui.newTab.find('a').attr('href');
            }
        }
    });
});
