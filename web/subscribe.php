<?
// subscribe.php:
// Signing up for YCML.
//
// Copyright (c) 2005 UK Citizens Online Democracy. All rights reserved.
// Email: matthew@mysociety.org. WWW: http://www.mysociety.org
//
// $Id: subscribe.php,v 1.2 2005-07-18 16:26:21 matthew Exp $

require_once '../phplib/ycml.php';
require_once '../phplib/constituent.php';
require_once '../../phplib/person.php';
require_once '../../phplib/utility.php';
require_once '../../phplib/importparams.php';
require_once '../../phplib/mapit.php';
require_once '../../phplib/dadem.php';

$title = _('Signing up');
page_header($title);
if (get_http_var('subscribe')) {
    $errors = do_subscribe();
    if (is_array($errors)) {
        print '<div id="errors"><ul><li>';
        print join ('</li><li>', $errors);
        print '</li></ul></div>';
        constituent_subscribe_box();
    }
/* } elseif (get_http_var('direct_unsubscribe')) {
    // Clicked from email to unsubscribe
    $alert_id = get_http_var('direct_unsubscribe');
    $P = person_if_signed_on();
    if (!$P) 
        err(_('Unexpectedly not signed on after following unsubscribe link'));
    $desc = alert_h_description($alert_id);
    print '<p>';
    if ($desc) {
        alert_unsubscribe($P->id(), $alert_id);
        printf(_("Thanks!  You won't receive more email about %s."), $desc);
    } else {
        print _("Thanks!  You are already unsubscribed from YCML.");
    }
    print '</p>';
*/
} else {
    constituent_subscribe_box();
}
page_footer();

function do_subscribe() {
    global $q_email, $q_name, $q_postcode;
    $errors = importparams(
                array('name',      "/./", 'Please enter a name'),
                array('email',      "importparams_validate_email"),
                array('postcode',      "importparams_validate_postcode")
            );
    if (!is_null($errors))
        return $errors;

    /* Get the user to log in. */
    $r = array();
    $r['reason_web'] = _('Before adding you to YCML, we need to confirm your email address.');
    $r['reason_email'] = _("You'll then be emailed when the threshold is reached, etc.");
    $r['reason_email_subject'] = _("Subscribe to YCML");
    $person = person_signon($r, $q_email, $q_name);
    $person_id = $person->id();
    
    $postcode = canonicalise_postcode($q_postcode);
    $areas = mapit_get_voting_areas($postcode);
    if (mapit_get_error($areas)) {
        /* This error should never happen, as earlier postcode validation in form will stop it */
        err('Invalid postcode while setting alert, please check and try again.');
    }
    $wmc_id = $areas['WMC'];
    $area_info = mapit_get_voting_area_info($wmc_id);
    $area_rep_name = $area_info['rep_name'];
    $area_rep_suffix = $area_info['rep_suffix'];
    $area_name = $area_info['name'];
    
    $area_status = dadem_get_area_status($wmc_id);
    $reps = dadem_get_representatives($wmc_id);
    $rep_info = dadem_get_representative_info($reps[0]);
    $rep_name = $rep_info['name'];
    $rep_party = $rep_info['party'];
    # TODO: Get method (email only?) from here

    $already_signed = db_getOne("select id from constituent where 
        constituency = ? and person_id = ?
        for update", array( $wmc_id, $person_id ) );
    if (!$already_signed) {
        db_query("insert into constituent (
                    person_id, constituency,
                    postcode, creation_ipaddr
                )
                values (?, ?, ?, ?)", array(
                    $person_id, $wmc_id,
                    $postcode, $_SERVER['REMOTE_ADDR']
                ));
        db_commit();
    
        $count = db_getOne("select count(*) from constituent where constituency = ?", array( $wmc_id ) );
        if ($count == 20) {
            # Time to send first email
        } elseif ($count == 50) {
            # Time to send second email
        } elseif ($count % 100 == 0) {
            # Send another reminder email?
        }
?>
<p class="loudmessage" align="center"><?=sprintf(_("Thanks for subscribing to %s's YCML for the %s constituency!  You're the %s person to sign up. You'll now get emailed when threshold reached, person sends then, etc."), $rep_name, $area_name, english_ordinal($count)) ?> <a href="/"><?=_('YCML home page') ?></a></p>
<?
    } else { ?>
<p class="loudmessage" align="center">You have already signed up to this YCML!</p>
<?
    }

}

?>
