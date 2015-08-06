<?php
/*
   Plugin Name: AfterEM
   Plugin URI: http://bitbucket.org/gsulibwebmaster/after-em
   Description: This plugin extends Events Manager to allow follow-up emails to be sent after an event.
   Version: 1.0.1
   Author: Georgia State University Library
   Author URI: http://library.gsu.edu/
   License: GPLv3


   AfterEM - A WordPress Plugin
   Copyright (C) 2015 Georgia State University Library

   This program is free software: you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


if(!class_exists('AEM')) {
   /*
      Class: AEM
         The AEM class which enables the AfterEM functionality in WordPress.
   */
   class AEM {
      const ID = 'AEM';
      const OPTIONS = 'AEM_options';
      const PLUGIN_NAME = 'AfterEM';
      const SCHEDULE_TIME = '06:00:00'; // The time at which the scheduler will run.
      private static $defaultOptions = array(
         'afterEventEnabled'   =>   true,
         'afterEventSubject'   =>   'Thank You for Attending #_EVENTNAME',
         'afterEventBody'      =>   '<p>Dear #_BOOKINGNAME,</p>
<p>Please take a few minutes to evaluate the event you recently attended, <strong>#_EVENTNAME</strong>, using the following form: <a href="#">#</a>.
<p>Thank you for your attendance and participation!</p>
<p>AfterEM</p>'
      );


      /*
         Function: __construct
            The constructor function is used to setup WordPress hooks.
      */
      public function __construct() {
         register_activation_hook(__FILE__, array('AEM', 'activationHook'));
         register_deactivation_hook(__FILE__, array('AEM', 'deactivationHook'));
         register_uninstall_hook(__FILE__, array('AEM', 'uninstallHook'));

         add_action('admin_init', array(&$this, 'adminInit'));
         add_action('admin_menu', array(&$this, 'addSubmenuItem'), 11); // Lower priority to make sure parent menu has a chance to be loaded first.
         add_action(self::ID.'_sendMail', array(&$this, 'sendMail')); // Create a hook for the scheduler.
      }


      /*
         Function: activationHook
            Adds scheduled mailer hook and options to database.
            ***STATIC FUNCTION***
      */
      public static function activationHook() {
         if(!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
         }

         // Setup/make sure scheduler is setup.
         if(!wp_next_scheduled(self::ID.'_sendMail')) {
            wp_schedule_event(strtotime(self::SCHEDULE_TIME.' +1 day', current_time('timestamp')), 'daily', self::ID.'_sendMail');
         }

         // Add options to database, no autoload.
         add_option(self::OPTIONS, self::$defaultOptions, '', 'no');
      }


      /*
         Function: deactivationHook
            Removes scheduled mailer hook.
            ***STATIC FUNCTION***
      */
      public static function deactivationHook() {
         if(!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
         }

         // Remove scheduled hook.
         wp_clear_scheduled_hook(self::ID.'_sendMail');
      }


      /*
         Function: uninstallHook
            Makes sure scheduled mailer hook is gone and removes options from database.
            ***STATIC FUNCTION***
      */
      public static function uninstallHook() {
         // Do not check WP_UNINSTALL_PLUGIN here, it is only present for uninstall.php files!!!
         if(!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
         }

         // Remove options from database.
         delete_option(self::OPTIONS);

         // Make sure the scheduler is gone.
         wp_clear_scheduled_hook(self::ID.'_sendMail');
      }


      /*
         Function: adminInit
            Saves options and sends the test email.
      */
      public function adminInit() {
         // Make sure user is an admin (lower permissions?).
         if(current_user_can('manage_options')) {
            // If form was submitted and the nonce is verified save it to the options.
            if(isset($_POST[self::ID.'_save']) && isset($_POST[self::ID.'_nonce']) && wp_verify_nonce($_POST[self::ID.'_nonce'], 'saveTemplate')) {
               $options = array();

               $options['afterEventEnabled'] = (isset($_POST['afterEventEnabled']) && $_POST['afterEventEnabled'] == 'on') ? true : false;
               $options['afterEventSubject'] = isset($_POST['afterEventSubject']) ? $_POST['afterEventSubject'] : '';
               $options['afterEventBody'] = isset($_POST['afterEventBody']) ? $_POST['afterEventBody'] : '';

               update_option(self::OPTIONS, $options, 'no');
            }

            // Send test email if nonce is good.
            if(isset($_POST[self::ID.'_test_email']) && isset($_POST['testEmail']) && isset($_POST[self::ID.'_nonce']) && wp_verify_nonce($_POST[self::ID.'_nonce'], 'sendEmail')) {
               $options = stripslashes_deep(get_option(self::OPTIONS, self::$defaultOptions));

               if($to = sanitize_email($_POST['testEmail'])) {
                  wp_mail($to, $options['afterEventSubject'], $options['afterEventBody'], 'Content-type: text/html');
               }
            }
         }
      }


      /*
         Function: addSubmenuItem
            Adds the new submenu item under Events.
      */
      public function addSubmenuItem() {
         add_submenu_page('edit.php?post_type=event', self::PLUGIN_NAME, self::PLUGIN_NAME, 'manage_options', self::ID, array(&$this, 'submenuPage'));
      }


      /*
         Function: submenuPage
            Controls output on this page including messages.

         Outputs:
            HTML for the submenu page.
      */
      public function submenuPage() {
         // stripslashes_deep must be used. For whatever reason the option value is escaped before inserting it into
         // the options table but the slashes are not removed upon retrieval.
         $options = stripslashes_deep(get_option(self::OPTIONS, self::$defaultOptions));
         ?>

         <div class="wrap">
            <h2><?= self::PLUGIN_NAME; ?> Plugin</h2>

            <?php
            // If options were saved display a message.
            if(isset($_POST[self::ID.'_save'])) {
            ?>

               <div id="remove-me" class="updated">
                  <p><strong>The template has been saved!</strong></p>
               </div>

            <?php
            } else if(isset($_POST[self::ID.'_test_email']) && isset($_POST['testEmail']) && sanitize_email($_POST['testEmail'])) {
            ?>

               <div id="remove-me" class="updated">
                  <p><strong>An email has been sent to <?= sanitize_email($_POST['testEmail']); ?>.</strong></p>
               </div>

            <?php
            }
            ?>

            <script type="text/javascript">
            if(jQuery) {
               jQuery('#remove-me').delay(2000).fadeOut(3000);
            }
            </script>

            <h3>Email Template</h3>
            <p><em>This accepts booking, event, and location related placeholders. Please see <a href="<?= EM_ADMIN_URL.'&page=events-manager-help'; ?>">Events Manager Help</a> for more information on placeholders.</em></p>

            <form action="" method="POST">
               <table class="form-table" cellpadding="0" cellspacing="0">
                  <tr>
                     <td><label for="chk1"><strong>Enabled</strong></label></td>
                     <td><input id="chk1" type="checkbox" name="afterEventEnabled" <?= ($options['afterEventEnabled']) ? 'checked="checked"' : ''; ?>/></td>
                  </tr>
                  <tr>
                     <td><label for="txt1"><strong>Subject</strong></label></td>
                     <td><input id="txt1" type="text" name="afterEventSubject" size="38" value="<?= esc_attr($options['afterEventSubject']); ?>" /></td>
                  </tr>
                  <tr>
                     <td><label for="txtarea1"><strong>Body (html)</strong></label></td>
                     <td><textarea id="txtarea1" name="afterEventBody" cols="60" rows="10"><?= esc_textarea($options['afterEventBody']); ?></textarea></td>
                  </tr>
                  <tr>
                     <td colspan="2"><input class="button-primary" type="submit" name="<?= self::ID ?>_save" value="Save Changes" /></td>
                  </tr>
               </table>

               <?= wp_nonce_field('saveTemplate', self::ID.'_nonce', true, false); ?>
            </form>

            <h3>Send Test Email <em>(save changes above first)</em></h3>
            <p><em>Note: This does not replace the placeholders in the email.</em></p>

            <form action="" method="POST">
               <p><label for="testEmail1"><strong>To</strong></label>&nbsp;&nbsp;&nbsp;
               <input id="testEmail1" type="text" name="testEmail" size="20" />&nbsp;&nbsp;&nbsp;
               <input class="button-secondary" type="submit" name="<?= self::ID ?>_test_email" value="Send Email" /></p>
               <?= wp_nonce_field('sendEmail', self::ID.'_nonce', true, false); ?>
            </form>
         </div>

         <?php
      }


      /*
         Function: sendMail
            Sends an email to the registered users for each even that ended yesterday.
      */
      public function sendMail() {
         // Make sure classes exist.
         if(!class_exists(EM_Events) || !class_exists(EM_Event)) { return; }

         $options = stripslashes_deep(get_option(self::OPTIONS, self::$defaultOptions));

         // Is this enabled?
         if(!$options['afterEventEnabled']) { return; }

         // Get all events that ENDED yesterday.
         $yesterdaysEvents = EM_Events::get(array('scope'=>date('Y-m-d', strtotime('-1 day', current_time('timestamp')))));

         // For each event.
         foreach($yesterdaysEvents as $x) {
            $event = new EM_Event($x->event_id);
            $event->get_location(); // Adds the location object to the event object.
            $event->get_bookings(); // Get the bookings (adds the bookings object to the event object).

            // Each user booking is one booking.
            foreach($event->bookings as $y) {
               // If booking status is not "approved" then do not send an email (1 is approved).
               if($y->booking_status != 1) {
                  continue;
               }

               // Parses event, location, and booking related placeholders.
               $subject = $y->output($event->output($event->location->output($options['afterEventSubject'])));
               $body = $y->output($event->output($event->location->output($options['afterEventBody'])));

               wp_mail($y->get_person()->user_email, $subject, $body, 'Content-type: text/html');
            }
         }
      }
   }


   $AEM = new AEM();
}
