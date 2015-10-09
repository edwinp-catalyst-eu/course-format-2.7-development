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
    var activetabs = window.location.hash.split("-");
    var toptab = 0;
    var subtab = 0;

    if (activetabs[0] == "#subtabs") {
        if (activetabs[1] != null) {
            toptab = activetabs[1];
        }
        if (activetabs[2] != null) {
            subtab = activetabs[2];
        }
    }

    $('#tabs').tabs({
        active: toptab
    });

    $('.turforlag_subtabs').tabs({
        active: subtab,
        disabled: [0],
        activate: function(e, ui) {
            ui.newPanel.css('margin-top', ui.newTab.position().top);
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
