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
 * JavaScript for modals in Custom Dashboard block.
 *
 * @module     block_customdashboard/modals
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/str'], function($, ModalFactory, ModalEvents, Str) {
    return {
        init: function() {
            // Use event delegation to handle dynamically loaded content
            $(document).on('click', '.view-activities, .view-activities-link', function(e) {
                e.preventDefault();
                const courseId = $(this).data('courseid');
                const courseName = $(this).data('coursename');
                const activityData = $('.activity-data[data-courseid="' + courseId + '"]');

                // Load all required strings first
                const stringPromises = [
                    {key: 'activityname', component: 'block_customdashboard'},
                    {key: 'activitytype', component: 'block_customdashboard'},
                    {key: 'status', component: 'block_customdashboard'},
                    {key: 'nocourseactivities', component: 'block_customdashboard'},
                    {key: 'activitiesfor', component: 'block_customdashboard', param: courseName}
                ];

                Str.get_strings(stringPromises).then(function(strings) {
                    let activitiesHtml = '<table class="table table-striped"><thead><tr>' +
                        '<th>' + strings[0] + '</th>' +
                        '<th class="text-center">' + strings[1] + '</th>' +
                        '<th class="text-center">' + strings[2] + '</th>' +
                        '</tr></thead><tbody>';

                    if (activityData.find('.activity-item').length > 0) {
                        activityData.find('.activity-item').each(function() {
                            const name = $(this).data('name');
                            const type = $(this).data('type');
                            const iconUrl = $(this).data('iconurl');
                            const completedText = $(this).data('completedtext');
                            const completedClass = $(this).data('completedclass');

                            activitiesHtml += '<tr>' +
                                '<td><img src="' + iconUrl +
                                '" class="icon" alt="" style="width:16px;height:16px;margin-right:8px;"> ' + name + '</td>' +
                                '<td class="text-center">' + type + '</td>' +
                                '<td class="text-center"><span class="badge ' + completedClass + '">'
                                + completedText + '</span></td>' +
                                '</tr>';
                        });
                    } else {
                        activitiesHtml += '<tr><td colspan="3" class="text-center">' +
                            strings[3] + '</td></tr>';
                    }

                    activitiesHtml += '</tbody></table>';

                    return ModalFactory.create({
                        type: ModalFactory.types.DEFAULT,
                        title: strings[4],
                        body: activitiesHtml
                    });
                }).then(function(modal) {
                    modal.show();
                    return modal;
                }).catch(function() {
                    // Fallback if string loading fails
                    let activitiesHtml = '<table class="table table-striped"><thead><tr>' +
                        '<th>Activity Name</th>' +
                        '<th class="text-center">Type</th>' +
                        '<th class="text-center">Status</th>' +
                        '</tr></thead><tbody>';

                    if (activityData.find('.activity-item').length > 0) {
                        activityData.find('.activity-item').each(function() {
                            const name = $(this).data('name');
                            const type = $(this).data('type');
                            const iconUrl = $(this).data('iconurl');
                            const completedText = $(this).data('completedtext');
                            const completedClass = $(this).data('completedclass');

                            activitiesHtml += '<tr>' +
                                '<td><img src="' + iconUrl +
                                '" class="icon" alt="" style="width:16px;height:16px;margin-right:8px;"> ' + name + '</td>' +
                                '<td class="text-center">' + type + '</td>' +
                                '<td class="text-center"><span class="badge ' + completedClass + '">'
                                + completedText + '</span></td>' +
                                '</tr>';
                        });
                    } else {
                        activitiesHtml += '<tr><td colspan="3" class="text-center">No activities</td></tr>';
                    }

                    activitiesHtml += '</tbody></table>';

                    return ModalFactory.create({
                        type: ModalFactory.types.DEFAULT,
                        title: 'Activities for ' + courseName,
                        body: activitiesHtml
                    }).then(function(modal) {
                        modal.show();
                    });
                });
            });

            // View Grades button handler - use event delegation
            $(document).on('click', '.view-grades, .view-grades-link', function(e) {
                e.preventDefault();
                const courseId = $(this).data('courseid');
                const courseName = $(this).data('coursename');
                const gradesData = $('.grades-data[data-courseid="' + courseId + '"]');

                // Load all required strings first
                const stringPromises = [
                    {key: 'activityname', component: 'block_customdashboard'},
                    {key: 'grade', component: 'block_customdashboard'},
                    {key: 'na', component: 'block_customdashboard'},
                    {key: 'finalgrade', component: 'block_customdashboard'},
                    {key: 'nogrades', component: 'block_customdashboard'},
                    {key: 'gradesfor', component: 'block_customdashboard', param: courseName}
                ];

                Str.get_strings(stringPromises).then(function(strings) {
                    let gradesHtml = '<table class="table table-striped"><thead><tr>' +
                        '<th>' + strings[0] + '</th>' +
                        '<th>' + strings[1] + '</th>' +
                        '</tr></thead><tbody>';

                    if (gradesData.find('.grade-item').length > 0) {
                        gradesData.find('.grade-item').each(function() {
                            const name = $(this).data('name');
                            const grade = $(this).data('grade');
                            const hasGrade = $(this).data('hasgrade');
                            const isScale = $(this).data('isscale');
                            const scaleItemsStr = $(this).data('scaleitems');
                            const achievedScale = $(this).data('achievedscale');
                            const gradeMax = $(this).data('grademax');

                            let gradeDisplay = '';

                            if (!hasGrade) {
                                gradeDisplay = '<span class="text-muted">' + strings[2] + '</span>';
                            } else if (isScale) {
                                // For scales, show achieved scale with all available scales.
                                const scaleItems = scaleItemsStr ? scaleItemsStr.split(',').filter(item => item.trim()) : [];
                                gradeDisplay = '<strong>' + achievedScale + '</strong><br>';
                                gradeDisplay += '<small class="text-muted">Scale: ' + scaleItems.join(', ') + '</small>';
                            } else {
                                // For numeric grades, show achieved out of max.
                                gradeDisplay = '<strong>' + Number(grade).toFixed(2)
                                + '</strong> (out of <strong>' + Number(gradeMax).toFixed(2) + '</strong>)';
                            }

                            gradesHtml += '<tr>' +
                                '<td>' + name + '</td>' +
                                '<td>' + gradeDisplay + '</td>' +
                                '</tr>';
                        });

                        // Add final grade row only if grade exists
                        const finalGrade = gradesData.data('finalgrade');
                        const finalGradeText = gradesData.data('finalgradetext');

                        if (finalGrade && finalGrade !== '-') {
                            gradesHtml += '<tr class="table-primary"><td><strong>' +
                                strings[3] +
                                '</strong></td><td><strong>' + finalGrade + ' (' + finalGradeText + ')</strong></td></tr>';
                        }
                    } else {
                        gradesHtml += '<tr><td colspan="2" class="text-center">' +
                            strings[4] + '</td></tr>';
                    }

                    gradesHtml += '</tbody></table>';

                    return ModalFactory.create({
                        type: ModalFactory.types.DEFAULT,
                        title: strings[5],
                        body: gradesHtml
                    });
                }).then(function(modal) {
                    modal.show();
                    return modal;
                }).catch(function() {
                    // Fallback if string loading fails
                    let gradesHtml = '<table class="table table-striped"><thead><tr>' +
                        '<th>Activity Name</th>' +
                        '<th>Grade</th>' +
                        '</tr></thead><tbody>';

                    if (gradesData.find('.grade-item').length > 0) {
                        gradesData.find('.grade-item').each(function() {
                            const name = $(this).data('name');
                            const grade = $(this).data('grade');
                            const hasGrade = $(this).data('hasgrade');
                            const isScale = $(this).data('isscale');
                            const scaleItemsStr = $(this).data('scaleitems');
                            const achievedScale = $(this).data('achievedscale');
                            const gradeMax = $(this).data('grademax');

                            let gradeDisplay = '';

                            if (!hasGrade) {
                                gradeDisplay = '<span class="text-muted">N/A</span>';
                            } else if (isScale) {
                                const scaleItems = scaleItemsStr ? scaleItemsStr.split(',').filter(item => item.trim()) : [];
                                gradeDisplay = '<strong>' + achievedScale + '</strong><br>';
                                gradeDisplay += '<small class="text-muted">Scale: ' + scaleItems.join(', ') + '</small>';
                            } else {
                                gradeDisplay = '<strong>' + grade + '</strong> (out of <strong>' + gradeMax + '</strong>)';
                            }

                            gradesHtml += '<tr>' +
                                '<td>' + name + '</td>' +
                                '<td>' + gradeDisplay + '</td>' +
                                '</tr>';
                        });

                        // Add final grade row only if grade exists
                        const finalGrade = gradesData.data('finalgrade');
                        const finalGradeText = gradesData.data('finalgradetext');

                        if (finalGrade && finalGrade !== '-') {
                            gradesHtml += '<tr class="table-primary"><td><strong>Final Grade</strong></td>' +
                                '<td><strong>' + finalGrade + ' (' + finalGradeText + ')</strong></td></tr>';
                        }
                    } else {
                        gradesHtml += '<tr><td colspan="2" class="text-center">No grades</td></tr>';
                    }

                    gradesHtml += '</tbody></table>';

                    return ModalFactory.create({
                        type: ModalFactory.types.DEFAULT,
                        title: 'Grades for ' + courseName,
                        body: gradesHtml
                    }).then(function(modal) {
                        modal.show();
                    });
                });
            });
        }
    };
});
