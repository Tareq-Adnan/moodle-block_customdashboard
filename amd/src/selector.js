// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for child selector and zoom filter in Custom Dashboard block.
 *
 * @module     block_customdashboard/selector
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        init: function() {
            // Child selector for parents.
            $('#child-selector').on('change', function() {
                const selectedChildId = $(this).val();
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('selectedchild', selectedChildId);
                window.location.href = currentUrl.toString();
            });

            // View child profile button.
            $('#view-child-profile').on('click', function(e) {
                e.preventDefault();
                const selectedChildId = $('#child-selector').val();
                if (selectedChildId) {
                    const profileUrl = M.cfg.wwwroot + '/user/profile.php?id=' + selectedChildId;
                    window.open(profileUrl, '_blank');
                }
            });

            // Zoom classes filter.
            $('#zoom-filter').on('change', function() {
                const filterValue = $(this).val();
                filterZoomClasses(filterValue);
            });

            /**
             * Filter zoom classes based on selected option.
             * @param {string} filter - The filter value ('today' or 'upcoming')
             */
            function filterZoomClasses(filter) {
                const todayStart = new Date();
                todayStart.setHours(0, 0, 0, 0);
                const todayStartTimestamp = Math.floor(todayStart.getTime() / 1000);
                const todayEnd = new Date();
                todayEnd.setHours(23, 59, 59, 999);
                const todayEndTimestamp = Math.floor(todayEnd.getTime() / 1000);

                const $items = $('.zoom-class-item');
                let visibleCount = 0;

                $items.each(function() {
                    const $item = $(this);
                    const timestamp = parseInt($item.data('timestamp'), 10);

                    let show = false;
                    if (filter === 'today') {
                        // Show classes happening today.
                        show = timestamp >= todayStartTimestamp && timestamp <= todayEndTimestamp;
                    } else if (filter === 'upcoming') {
                        // Show classes starting from tomorrow onwards (excludes today's classes).
                        show = timestamp > todayEndTimestamp;
                    }

                    if (show) {
                        $item.show();
                        visibleCount++;
                    } else {
                        $item.hide();
                    }
                });

                // Show/hide no classes message.
                const $container = $('#zoom-classes-container');
                const $noClassesMsg = $container.find('.no-zoom-classes-message');

                if (visibleCount === 0) {
                    if ($noClassesMsg.length === 0) {
                        $container.find('.list-group').after(
                            '<p class="text-muted no-zoom-classes-message">No zoom classes found.</p>'
                        );
                    }
                    $container.find('.list-group').hide();
                } else {
                    $noClassesMsg.remove();
                    $container.find('.list-group').show();
                }
            }

            // Initialize with default filter on page load.
            if ($('#zoom-filter').length > 0) {
                const defaultFilter = $('#zoom-filter').val();
                filterZoomClasses(defaultFilter);
            }

            var $selector = $('#course-activity-selector');

            function showSelectedCourse() {
                var selectedCourseId = $selector.val();

                // Hide all activity lists
                $('.activity-course-list').hide();

                // Show the selected course activities
                $('.activity-course-list[data-courseid="' + selectedCourseId + '"]').show();
            }

            // Run on dropdown change
            $selector.on('change', showSelectedCourse);

            // Run once on page load
            showSelectedCourse();
        }
    };
});
