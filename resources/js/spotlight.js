/**
 * webtrees: online genealogy
 * Copyright (C) 2021 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
/* eslint no-unused-vars: 0 */
/* exported spotlight */
/* global Spotlight */

/**
 * @return {Object}
 */
const spotlight = (function () {
  'use strict';

  /**
   *
   * @param {Object} params
   *
   * @return {void}
   */
  const init = (params) => {
    const showGallery = params.showGallery || false;
    const closeOnBody = params.closeOnBody || false;
    const filterTabs = params.filterTabs || false;
    const allowDownload = params.allowDownload || false;

    document.body.addEventListener('click', function (e) {
      const node = e.target.parentNode || false;
      if (node && node.matches('a.media')) {
        const type = node.dataset.media;
        e.preventDefault();
        let items;
        if (showGallery && type !== 'node') {
          items = buildCollection(type, filterTabs);
        } else {
          items = [node];
        }
        openSpotlight(items, node.href, allowDownload);
      } else if (closeOnBody && e.target &&
          (e.target.matches('.spl-pane') || e.target.matches('#node-container'))) {
        // Close Spotlight when backdrop clicked
        Spotlight.close();
      }
    });
  };

  /**
   *
   * @param {string} type
   * @param {boolean} filterTabs
   * @returns {Array<object>}
   */
  const buildCollection = (type, filterTabs) => {
    const tabs = document.getElementById('individual-tabs');
    let items = [];
    let el = document;

    if (filterTabs && tabs && type === 'image') {
      // if on individual page only get main image and items on active tab
      el = document.querySelector('.tab-content div.active');
      items = Array.from(tabs.previousElementSibling.querySelectorAll('[data-media="image"]'));
    }
    // Build an array of DOM elements of <type> and filter to remove duplicates
    return items
      .concat(Array.from(el.querySelectorAll('[data-media="' + type + '"]')))
      .filter((value, index, self) => self.findIndex(t => (t.href === value.href)) === index);
  };

  /**
   *
   * @param {Array.Object} items
   * @param {string} href
   * @param {boolean} allowDownload
   *
   * @return {void}
   */
  const openSpotlight = (items, href, allowDownload) => {
    Spotlight.show(items, {
      autohide: false,
      Autoslide: false,
      index: items.findIndex((el) => el.href === href) + 1,
      infinite: items.length > 1,
      play: false, // items.length > 1,
      theme: 'webtrees',
      download: allowDownload,
      onchange: function (index, options) {
        // Decode HTML entities in title and description
        const titleElement = document.querySelector('#spotlight .spl-title');
        const descriptionElement = document.querySelector('#spotlight .spl-description');
        const item = items[index - 1];
        const title = item.dataset.media === 'image' ? item.firstChild.alt : item.dataset.title;
        const description = item.dataset.description;

        titleElement.innerHTML = decodeURIComponent(title);
        if (description) {
          descriptionElement.innerHTML = decodeURIComponent(description);
        }
      },
      onshow: function (index) {
        const item = items[index - 1];
        if (item.dataset.media === 'node') {
          document.getElementById('node-content').setAttribute('src', item.href);
        }
      }
    });
  };

  return {
    init: init
  };
})();
