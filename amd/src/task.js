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
 * Provides the mod_psgrading/task module
 *
 * @package   mod_psgrading
 * @category  output
 * @copyright 2020 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @module mod_psgrading/task
 */
define(['jquery', 'core/log', 'core/templates', 'core/modal_factory', 'core/modal_events'],
  function ($, Log, Templates, ModalFactory, ModalEvents) {
      'use strict';

      /**
       * Initializes the task component.
       */
      function init() {
          Log.debug('mod_psgrading/task: initializing');

          var rootel = $('form[data-form="psgrading-task"]');

          if (!rootel.length) {
              Log.error('mod_psgrading/tasks: form[data-form="psgrading-task"] not found!');
              return;
          }

          var task = new Task(rootel);
          task.main();
      }

      /**
       * The constructor
       *
       * @constructor
       * @param {jQuery} rootel
       */
      function Task(rootel) {
          var self = this;
          self.rootel = rootel;

          // Get the stub criterion.
          var txt = document.createElement("textarea");
          txt.innerHTML = self.rootel.find('.criterion-selector').data('stub');
          self.stubcriterion = $.parseJSON(txt.value);

          var txtengagement = document.createElement("textarea");
          txtengagement.innerHTML = self.rootel.find('.engagement-selector').data('stub');
          self.stubengagement = $.parseJSON(txt.value);


          // Setup initial json.
          self.regenerateEvidenceJSON();
          self.regenerateCriterionJSON();
          self.regenerateEngagementJSON();
      }

      /**
       * Run the Audience Selector.
       *
       */
      Task.prototype.main = function () {
          var self = this;

          // Cancel edit.
          self.rootel.on('click', 'input[name="cancel"]', function (e) {
              self.rootel.find('[name="action"]').val('cancel');
          });

          // Delete task.
          self.rootel.on('click', 'input[name="delete"]', function (e) {
              self.rootel.find('[name="action"]').val('delete');
          });

          // Save.
          self.rootel.on('click', 'input[name="save"]', function (e) {
              self.rootel.find('[name="action"]').val('save');
              self.regenerateEvidenceJSON();
              self.regenerateCriterionJSON();
              self.regenerateEngagementJSON();
              Log.debug('regenerated evidence, engagement and criteria json');
          });

          // Add criterion.
          self.rootel.on('click', '#btn-addcriterion', function (e) {
              e.preventDefault();
              self.addCriterion();
          });

          // Hide criterion.
          self.rootel.on('click', '.toggle', function (e) {
              var toggle = $(this);
              self.toggleCriterion(toggle);
          });

          // Delete criterion.
          self.rootel.on('click', '.btn-delete', function (e) {
              e.preventDefault();
              var button = $(this);
              self.deleteCriterion(button);
          });


          // Add engagement.
          self.rootel.on('click', '#btn-addengagement', function (e) {
              e.preventDefault();
              self.addEngagement();
          });

          // Hide engagement.
          self.rootel.on('click', '.toggle-engagement', function (e) {
              var toggle = $(this);
              self.toggleEngagement(toggle);
          });

          // Delete engagement.
          self.rootel.on('click', '.btn-delete-engagement', function (e) {
              e.preventDefault();
              var button = $(this);
              self.deleteEngagement(button);
          });

          // Toggle evidence.
          self.rootel.on('click', '.activity.labelonly', function (e) {
              e.preventDefault();
              var toggle = $(this);
              self.toggleEvidence(toggle);
          });

          // Toggle weights visibility when enableweights checkbox changes.
          self.rootel.on('change', 'input[name="enableweights"]', function (e) {
              self.toggleWeights();
              self.showWeightChangeWarning();
          });

          // Initialize weight visibility on page load.
          self.toggleWeights();

          // Styling the criterion selects based on selected option.
          self.rootel.on('change', 'select', function (e) {
              var select = $(this);
              select.removeClass('selected');
              if (select.children(':selected').val()) {
                  select.addClass('selected');
              }
              //select.attr('class', 'form-control').addClass(select.children(':selected').val());
          });
          self.rootel.find('select').change();

          // Preload the modals and templates.
          self.templates = {
              CRITERION: 'mod_psgrading/criterion_selector_row',
              ENGAGEMENT: 'mod_psgrading/engagement_selector_row',
          };

            self.modals = {
                DELETE: null,
            };

          var preloads = [];
          preloads.push(self.loadTemplate('CRITERION'));
          preloads.push(self.loadTemplate('ENGAGEMENT'));
          preloads.push(self.loadModal('DELETE', 'Confirm delete', 'Delete', ModalFactory.types.SAVE_CANCEL));
          // Do not show actions until preloads are complete.
          $.when.apply($, preloads).then(function () {
              self.rootel.addClass('preloads-completed');
          })


          // Set up drag reordering of criterions.
          if (typeof Sortable != 'undefined') {
              var el = document.getElementById('task-criterions');
              var sortable = new Sortable(el, {
                  handle: '.btn-reorder',
                  animation: 150,
                  ghostClass: 'reordering',
                  //onEnd: self.SortEnd
              });
          }
          // Set up drag reordering of engagementcriterions.
          // if (typeof Sortable != 'undefined') {
          //     var el = document.getElementById('task-engagement-criterions'); // TODO
          //     var sortable = new Sortable(el, {
          //         handle: '.btn-reorder-engagementr',
          //         animation: 150,
          //         ghostClass: 'reordering',
          //         //onEnd: self.SortEnd
          //     });
          // }
      };

      Task.prototype.SortEnd = function (e) {
          self = this;
          //console.log(e);
      };

      /**
       * Regenerate evidence json.
       *
       * @method
       */
      Task.prototype.regenerateEvidenceJSON = function () {
          var self = this;

          var evidencejson = $('input[name="evidencejson"]');
          var evidences = new Array();

          self.rootel.find('.evidence-selector .activity .cmid:checked').each(function () {
              var checkbox = $(this);
              var cm = {
                  evidencetype: 'cm_' + checkbox.data('modname'),
                  refdata: checkbox.val(),
              };
              evidences.push(cm);
          });

          // Encode to json and add tag to hidden input.
          var evidencesStr = '';
          if (evidences.length) {
              evidencesStr = JSON.stringify(evidences);
          }

          evidencejson.val(evidencesStr);
      };

      /**
       * Regenerate criterion json.
       *
       * @method
       */
      Task.prototype.regenerateCriterionJSON = function () {
          var self = this;

          var criterionjson = $('input[name="criterionjson"]');
          var criterions = new Array();

          self.rootel.find('.criterions .tbl-tr').each(function () {
              var row = $(this);
              var subject = row.find('[name=subject]').val();
              if (subject == null) {
                  subject = '';
              }
              var criterion = {
                  id: row.data('id'),
                  description: row.find('[name=description]').val(),
                  level5: row.find('[name=level5]').val(),
                  level4: row.find('[name=level4]').val(),
                  level3: row.find('[name=level3]').val(),
                  level2: row.find('[name=level2]').val(),
                  level1: row.find('[name=level1]').val(),
                  subject: subject,
                  weight: row.find('[name=weight]').val(),
                  hidden: row.find('[name=hidden]').is(":checked") ? 0 : 1,
              };

              // console.log(criterion);
              criterions.push(criterion);
          });

          // Encode to json and add tag to hidden input.
          var criterionsStr = '';
          if (criterions.length) {
              criterionsStr = JSON.stringify(criterions);
              criterionjson.val(criterionsStr);
          }

          return criterionsStr;
      };
      /**
       * Regenerate engagement json.
       *
       * @method
       */
      Task.prototype.regenerateEngagementJSON = function () {
          var self = this;

          var engagementjson = $('input[name="engagementjson"]');
          var engagements = new Array();

          self.rootel.find('.engagements .tbl-tr').each(function () {
              var row = $(this);
              var subject = row.find('[name=subject]').val();
              if (subject == null) {
                  subject = '';
              }
              var engagement = {
                  id: row.data('id'),
                  description: row.find('[name=description]').val(),
                  level4: row.find('[name=level4]').val(),
                  level3: row.find('[name=level3]').val(),
                  level2: row.find('[name=level2]').val(),
                  level1: row.find('[name=level1]').val(),
                  subject: subject,
                  weight: row.find('[name=weight]').val(),
                  hidden: row.find('[name=hidden]').is(":checked") ? 0 : 1,
              };
              engagements.push(engagement);
          });

          // Encode to json and add tag to hidden input.
          var engagementsStr = '';
          if (engagements.length) {
              engagementsStr = JSON.stringify(engagements);
              engagementjson.val(engagementsStr);
          }
          console.log(engagementsStr);

          return engagementsStr;
      };

      /**
       * Add a new blank criterion to the form.
       *
       * @method
       */
      Task.prototype.addCriterion = function () {
          var self = this;

          var stubcriterion = { "criterions": [self.stubcriterion] };
          console.log(stubcriterion);

          Templates.render(self.templates.CRITERION, stubcriterion)
              .done(function (html) {
                  self.rootel.find('.criterions').append(html);
                  // Apply current weight visibility state to newly added criterion
                  self.toggleWeights();
              });
      };

      /**
       * Toggle criterion visibility.
       *
       * @method
       */
      Task.prototype.toggleCriterion = function (toggle) {
          var self = this;

          var control = toggle.closest('.hidden-control');
          var tbltr = control.closest('.tbl-tr');
          var input = control.find('input');
          var desc = control.find('.desc');
          if (input.is(":checked")) {
              // Checked = visible. Make it hidden.
              input.prop('checked', false);
              tbltr.addClass('criterion-hidden');
              desc.html('Hidden from students');
          } else {
              // Unchecked = hidden. Make it visible.
              input.prop('checked', true);
              tbltr.removeClass('criterion-hidden');
              desc.html('Visible');
          }
      };

      /**
       * Delete criterion.
       *
       * @method
       */
      Task.prototype.deleteCriterion =  function (button) {
          var self = this;

          var tbltr = button.closest('.tbl-tr');
          // Only show the alert if there is more than one row.
          if($('#task-criterions').children().length > 1) {

            var description = $( $(tbltr.children('li')[0]).children()[0]).val();

            if (self.modals.DELETE) {
              self.modals.DELETE.setBody('<p>Are you sure you want to delete this criterion?<br><strong>' + description + '</strong></p>');
              self.modals.DELETE.getRoot().on(ModalEvents.save, function (e){
                tbltr.fadeOut(200, function () {
                  $(this).remove();
              });
              })
              self.modals.DELETE.show();
            }

          } else { // just one, do as its done now.
            tbltr.fadeOut(200, function () {
                $(this).remove();
            });

          }
      };


      /**
       * Add a new blank engagement to the form.
       *
       * @method
       */
      Task.prototype.addEngagement = function () {
          var self = this;

          var stubengagement = { "engagements": [self.stubengagement] };

          Templates.render(self.templates.ENGAGEMENT, stubengagement)
              .done(function (html) {
                  self.rootel.find('.engagements').append(html);
              });
      };

      /**
       * Toggle engagement visibility.
       *
       * @method
       */
      Task.prototype.toggleEngagement = function (toggle) {
          var self = this;

          var control = toggle.closest('.hidden-control');
          var tbltr = control.closest('.tbl-tr');
          var input = control.find('input');
          var desc = control.find('.desc');
          if (input.is(":checked")) {
              // Checked = visible. Make it hidden.
              input.prop('checked', false);
              tbltr.addClass('criterion-hidden');
              desc.html('Hidden from students');
          } else {
              // Unchecked = hidden. Make it visible.
              input.prop('checked', true);
              tbltr.removeClass('criterion-hidden');
              desc.html('Visible');
          }
      };

      /**
       * Delete engagement.
       *
       * @method
       */
      Task.prototype.deleteEngagement = function (button) {
          var self = this;

          var tbltr = button.closest('.tbl-tr');
          if ($('#task-engagement').children().length > 1) {
            var description = $( $(tbltr.children('li')[0]).children()[0]).val();
            if (self.modals.DELETE) {
              self.modals.DELETE.setBody('<p>Are you sure you want to delete this engagement?<br><strong>' + description + '</strong></p>');
              self.modals.DELETE.getRoot().on(ModalEvents.save, function (e){
                tbltr.fadeOut(200, function () {
                  $(this).remove();
              });
              })
              self.modals.DELETE.show();
            }
          } else {
              tbltr.fadeOut(200, function () {
                  $(this).remove();
              });
          }
      };

      /**
       * Toggle evidence visibility.
       *
       * @method
       */
      Task.prototype.toggleEvidence = function (toggle) {
          var self = this;
          var cmid = toggle.data('cmid');

          if (toggle.hasClass("subhidden")) {
              // Show subs.
              self.rootel.find('.activity.sub[data-cmid="' + cmid + '"]').show();
              toggle.removeClass("subhidden");
          } else {
              // Hide subs.
              self.rootel.find('.activity.sub[data-cmid="' + cmid + '"]').hide();
              toggle.addClass("subhidden");
          }
      };

      /**
       * Toggle weight dropdowns visibility based on enableweights checkbox.
       *
       * @method
       */
      Task.prototype.toggleWeights = function () {
          var self = this;
          var enableWeightsCheckbox = self.rootel.find('input[name="enableweights"]');
          var isEnabled = enableWeightsCheckbox.is(':checked');
          
          // Show/hide weight dropdowns in criterion rows only
          if (isEnabled) {
              self.rootel.find('.criterions .mod-psgrading-weight').show();
          } else {
              self.rootel.find('.criterions .mod-psgrading-weight').hide();
          }
      };

      /**
       * Show warning message when weight settings change and there are existing grades.
       *
       * @method
       */
      Task.prototype.showWeightChangeWarning = function () {
          var self = this;
          var editField = self.rootel.find('input[name="edit"]');
          var isEditing = editField.length && editField.val() > 0;
          
          // Only show warning when editing existing tasks (not new tasks)
          if (!isEditing) {
              return;
          }
          
          // Check if task has grades (look for hasgrades data attribute or similar indicator)
          var hasGrades = self.rootel.data('hasgrades') || 
                         self.rootel.find('[data-hasgrades="true"]').length > 0;
          
          if (hasGrades) {
              // Show warning message
              var warningHtml = '<div class="alert alert-warning alert-dismissible fade show mt-2" role="alert">' +
                  '<strong>Weight Setting Changed:</strong> Existing student grades may be inconsistent. ' +
                  'Consider re-grading students to ensure all grades use the same calculation method.' +
                  '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                  '<span aria-hidden="true">&times;</span></button></div>';
              
              // Remove any existing warnings first
              self.rootel.find('.alert-warning').remove();
              
              // Add warning after the enableweights checkbox
              self.rootel.find('input[name="enableweights"]').closest('.fitem').after(warningHtml);
          }
      };

      /**
       * Helper used to preload a template
       *
       * @method loadModal
       * @param {string} templatekey The property of the global templates variable
       * @return {object} jQuery promise
       */
      Task.prototype.loadTemplate = function (templatekey) {
          var self = this;
          return Templates.render(self.templates[templatekey], {});
      }



  //   Task.prototype.loadModal2 = function () {
  //     var self = this;
  //     console.log(Modal);  // Check if the Modal object is loaded properly

  //     // Correctly creating the modal using ModalFactory
  //     Modal.create({
  //         title: 'Test title',
  //         body: '<p>Example body content</p>',
  //         footer: 'An example footer content',
  //         show: true,
  //         removeOnClose: true
  //     }).then(function(modal) {
  //         // Handle the modal here
  //         console.log('Modal created');
  //         modal.show();
  //     }).catch(function(error) {
  //         // Handle error if modal creation fails
  //         console.log('Error creating modal:', error);
  //     });
  // };

    Task.prototype.loadModal = function (modalkey, title, buttontext, type) {
      var self = this;
      return ModalFactory.create({ type: type }).then(function (modal) {
          modal.setTitle(title);
          if (buttontext) {
              modal.setSaveButtonText(buttontext);
          }
          self.modals[modalkey] = modal;
          // Preload backgrop.
          modal.getBackdrop();
          modal.getRoot().addClass('modal-' + modalkey);
      });
};





      return {
          init: init
      };
  });