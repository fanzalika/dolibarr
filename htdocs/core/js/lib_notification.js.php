<?php
/* Copyright (C) 2016	   Sergio Sanchis		<sergiosanchis@hotmail.com>
 * Copyright (C) 2017	   Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2020	   Destailleur Laurent  <eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);

require_once '../../main.inc.php';

if (!($_SERVER['HTTP_REFERER'] === $dolibarr_main_url_root.'/' || $_SERVER['HTTP_REFERER'] === $dolibarr_main_url_root.'/index.php'
    || preg_match('/getmenu_div\.php/', $_SERVER['HTTP_REFERER'])))
{
    global $langs, $conf;

    top_httphead('text/javascript; charset=UTF-8');

    print 'var login = \''.$_SESSION['dol_login'].'\';'."\n";
	print 'var nowtime = Date.now();';
    print 'var time_auto_update = '.$conf->global->MAIN_BROWSER_NOTIFICATION_FREQUENCY.';'."\n"; // Always defined
    print 'var time_js_next_test = (nowtime + time_auto_update);'."\n";
    ?>

	/* Check if permission ok */
	if (Notification.permission !== "granted") {
		console.log("Ask Notification.permission");
        Notification.requestPermission()
    }

	/* Launch timer */
   	// We set a delay before launching first test so next check will arrive after the time_auto_update compared to previous one.
    var time_first_execution = (time_auto_update - (nowtime - time_js_next_test)) * 1000;	//need milliseconds
    if (login != '') {
    	console.log("Launch browser notif check: setTimeout is set to launch 'first_execution' function after a wait of time_first_execution="+time_first_execution+". nowtime (time php page generation) = "+nowtime+" time_js_next_test = "+time_js_next_test+" time_auto_update="+time_auto_update);
    	setTimeout(first_execution, time_first_execution);
    } //first run auto check


    function first_execution() {
    	console.log("Call first_execution time_auto_update (MAIN_BROWSER_NOTIFICATION_FREQUENCY) = "+time_auto_update);
        check_events();	//one check before launching timer to launch other checks
        setInterval(check_events, time_auto_update * 1000); //program time to run next check events
    }

    function check_events() {
    	if (Notification.permission === "granted")
    	{
    		console.log("Call check_events time_js_next_test = date we are looking for event after this date = "+time_js_next_test);
            $.ajax("<?php print DOL_URL_ROOT.'/core/ajax/check_notifications.php'; ?>", {
                type: "post",   // Usually post or get
                async: true,
                data: {time: time_js_next_test},
                success: function (result) {
                    var arr = JSON.parse(result);
                    if (arr.length > 0) {
                    	var audio = null;
                        <?php
						if (!empty($conf->global->AGENDA_REMINDER_BROWSER_SOUND)) {
							print 'audio = new Audio(\''.DOL_URL_ROOT.'/theme/common/sound/notification_agenda.wav\');';
						}
						?>
						var listofreminderids = '';

                        $.each(arr, function (index, value) {
                            var url = "notdefined";
                            var title = "Not defined";
                            var body = value['type'] + ': ' + value['label'];
                            if (value['type'] == 'agenda' && value['location'] != null && value['location'] != '') {
                                body += '\n' + value['location'];
                            }

                            if(value['type'] == 'agenda' && (value['event_date_start_formated'] != null || event_date_start_formated['event_date_start'] != '')) {
                                body += '\n' + value['event_date_start_formated'];
                            }

                            if (value['type'] == 'agenda')
                            {
                             	url = '<?php echo DOL_URL_ROOT.'/comm/action/card.php?id='; ?>' + value['id'];
                                title = '<?php print $langs->trans('Agenda') ?>';
                            }
                            var extra = {
                                icon: '<?php print DOL_URL_ROOT.'/theme/common/bell.png'; ?>',
                                body: body,
                                tag: value['id']
                            };

                            // We release the notify
                            console.log("Send notification on browser");
                            var noti = new Notification(title, extra);
                            if (index==0 && audio)
                            {
                            	audio.play();
                            }

                            if (noti) {
	                            noti.onclick = function (event) {
	                                console.log("A click on notification on browser has been done");
	                                event.preventDefault(); // prevent the browser from focusing the Notification's tab
	                                window.focus();
	                                window.open(url, '_blank');
	                                noti.close();
	                            };

	                            listofreminderids = listofreminderids + '-' + value['id_reminder']
	                        }
                        });

                        // Update status of all notifications we sent on browser (listofreminderids)
                        console.log("Flag notification as done for listofreminderids="+listofreminderids);
						$.ajax("<?php print DOL_URL_ROOT.'/core/ajax/check_notifications.php?action=stopreminder&listofreminderids='; ?>"+listofreminderids, {
			                type: "get",   // Usually post or get
			                async: true,
			                data: {time: time_js_next_test}
			                });
                    }
                }
            });
        }
        else
        {
        	console.log("Cancel check_events. Useless because javascript Notification.permission is "+Notification.permission+".");
        }

        time_js_next_test += time_auto_update;
		console.log('Updated time_js_next_test. New value is '+time_js_next_test);
    }
    <?php
}
