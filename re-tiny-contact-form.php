<?php
/*
Plugin Name: Recaptchad Tiny Contact Form
Plugin URI: http://www.tomsdimension.de/wp-plugins/RE-TINY-CONTACT-FORM
Description: Little form that allows site visitors to contact you. Use [RE-TINY-CONTACT-FORM] within any post or page.
Author: Tom Braider (original Tiny Contact Form), SidHarrell (recaptcha modifications)
Author URI: http://www.tomsdimension.de
Version: 0.2
*/

$retcf_version = '0.2';
$retcf_script_printed = 0;
$re_re_tiny_contact_form = new ReTinyContactForm();

class ReTinyContactForm
{

var $o; // options
var $userdata;
var $nr = 0; // form number to use more then once forms/widgets


/**
 * Constructor
 */	
function ReTinyContactForm()
{
	// get options from DB
	$this->o = get_option('re_tiny_contact_form');
	// widget
	add_action('widgets_init', array( &$this, 'register_widgets'));
	// options page in menu
	add_action('admin_menu', array( &$this, 'addOptionsPage'));
	// shortcode
	add_shortcode('RE-TINY-CONTACT-FORM', array( &$this, 'shortcode'));
	// add stylesheet
	add_action('wp_head', array( &$this, 'addStyle'));
	// uninstall function
	if ( function_exists('register_uninstall_hook') )
		register_uninstall_hook(ABSPATH.PLUGINDIR.'/RE-TINY-CONTACT-FORM/RE-TINY-CONTACT-FORM.php', array( &$this, 'uninstall')); 
	// settingslink on plugin page
	add_filter('plugin_action_links', array( &$this, 'pluginActions'), 10, 2);
	// add locale support
	if (defined('WPLANG') && function_exists('load_plugin_textdomain'))
		load_plugin_textdomain('tcf-lang', false, 'RE-TINY-CONTACT-FORM/locale');
	// creates image recources
	$this->setRecources();
}

/**
 * creates retcf code
 *
 * @return string form code
 */
function showForm( $params = '' )
{
	$n = ($this->nr == 0) ? '' : $this->nr;
	$this->nr++;

	if ( isset($_POST['tcf_sender'.$n]) )
		$result = $this->sendMail( $n, $params );
	
	$form = '<div class="contactform" id="tcform'.$n.'">';
	
	if ( !empty($result) )
	{
		if ( $result == $this->o['msg_ok'] )
			// mail successfully sent, no form
			$form .= '<p class="contactform_respons">'.$result.'</p>';
		else
			// error message
			$form .= '<p class="contactform_error">'.$result.'</p>';
	}
		
	if ( empty($result) || (!empty($result) && !$this->o['hideform']) )
	{
		// subject from form
		if ( !empty($_POST['tcf_subject'.$n]) )
			$tcf_subject = $_POST['tcf_subject'.$n];
		// subject from widget instance
		else if ( is_array($params) && !empty($params['subject']))
			$tcf_subject = $params['subject'];
		// subject from URL
		else if ( empty($_POST['tcf_subject'.$n]) && !empty($_GET['subject']) )
			$tcf_subject = $_GET['subject'];
		// subject from shortcode
		else if ( empty($_POST['tcf_subject'.$n]) && !empty($this->userdata['subject']) )
			$tcf_subject = $this->userdata['subject'];
		else
			$tcf_subject = '';
			
		$tcf_sender = (isset($_POST['tcf_sender'.$n])) ? $_POST['tcf_sender'.$n] : ''; 
		$tcf_email = (isset($_POST['tcf_email'.$n])) ? $_POST['tcf_email'.$n] : '';
		$tcf_msg = (isset($_POST['tcf_msg'.$n])) ? $_POST['tcf_msg'.$n] : '';
		
		$form .= '
			<form action="#tcform'.$n.'" method="post" id="tinyform'.$n.'">
			<div>
			<input name="tcf_name'.$n.'" id="tcf_name'.$n.'" value="" class="tcf_input" />
			<input name="tcf_sendit'.$n.'" id="tcf_sendit'.$n.'" value="1" class="tcf_input" />
			<label for="tcf_sender'.$n.'" class="tcf_label">'.__('Name', 'tcf-lang').':</label>
			<input name="tcf_sender'.$n.'" id="tcf_sender'.$n.'" size="30" value="'.$tcf_sender.'" class="tcf_field" />
			<label for="tcf_email'.$n.'" class="tcf_label">'.__('Email', 'tcf-lang').':</label>
			<input name="tcf_email'.$n.'" id="tcf_email'.$n.'" size="30" value="'.$tcf_email.'" class="tcf_field" />';
		// additional fields
		for ( $x = 1; $x <=5; $x++ )
		{
			$i = 'tcf_field_'.$x.$n;
			$tcf_f = (isset($_POST[$i])) ? $_POST[$i] : '';
			$f = $this->o['field_'.$x];
			if ( !empty($f) )
				$form .= '
				<label for="'.$i.'" class="tcf_label">'.$f.':</label>
				<input name="'.$i.'" id="'.$i.'" size="30" value="'.$tcf_f.'" class="tcf_field" />';
		}
		$form .= '
			<label for="tcf_subject'.$n.'" class="tcf_label">'.__('Subject', 'tcf-lang').':</label>
			<input name="tcf_subject'.$n.'" id="tcf_subject'.$n.'" size="30" value="'.$tcf_subject.'" class="tcf_field" />
			<label for="tcf_msg'.$n.'" class="tcf_label">'.__('Your Message', 'tcf-lang').':</label>
			<textarea name="tcf_msg'.$n.'" id="tcf_msg'.$n.'" class="tcf_textarea" cols="40" rows="10">'.$tcf_msg.'</textarea>
			';
		$form .= recaptcha_get_html($this->o['captcha_public_key']);
		$title = (!empty($this->o['submit'])) ? 'value="'.$this->o['submit'].'"' : '';
		$form .= '	
			<input type="submit" name="submit'.$n.'" id="contactsubmit'.$n.'" class="tcf_submit" '.$title.'  onclick="return checkForm(\''.$n.'\');" />
			</div>
			</form>';
	}
	
	$form .= '</div>'; 
	$form .= $this->addScript();
	return $form;
}

/**
 * adds javescript code to check the values
 */
function addScript()
{
	global $retcf_script_printed;
	if ($retcf_script_printed) // only once
		return;
	
	$script = "
		<script type=\"text/javascript\">
		//<![CDATA[
		function checkForm( n )
		{
			var f = new Array();
			f[1] = document.getElementById('tcf_sender' + n).value;
			f[2] = document.getElementById('tcf_email' + n).value;
			f[3] = document.getElementById('tcf_subject' + n).value;
			f[4] = document.getElementById('tcf_msg' + n).value;
			f[5] = f[6] = f[7] = f[8] = f[9] = '-';
		";
	for ( $x = 1; $x <=5; $x++ )
		if ( !empty($this->o['field_'.$x]) )
			$script .= 'f['.($x + 4).'] = document.getElementById("tcf_field_'.$x.'" + n).value;'."\n";
	$script .= '
		var msg = "";
		for ( i=0; i < f.length; i++ )
		{
			if ( f[i] == "" )
				msg = "'.__('Please fill out all fields.', 'tcf-lang').'\nPlease fill out all fields.\n\n";
		}
		if ( !isEmail(f[2]) )
			msg += "'.__('Wrong Email.', 'tcf-lang').'\nWrong Email.";
		if ( msg != "" )
		{
			alert(msg);
			return false;
		}
	}
	function isEmail(email)
	{
		var rx = /^([^\s@,:"<>]+)@([^\s@,:"<>]+\.[^\s@,:"<>.\d]{2,}|(\d{1,3}\.){3}\d{1,3})$/;
		var part = email.match(rx);
		if ( part )
			return true;
		else
			return false
	}
	//]]>
	</script>
	';
	$retcf_script_printed = 1;
	return $script;
}

/**
 * send mail
 * 
 * @return string Result, Message
 */
function sendMail( $n = '', $params = '' )
{
	$result = $this->checkInput( $n );
		
    if ( $result == 'OK' )
    {
    	$result = '';
    	
    	// use "to" from widget instance
		if ( is_array($params) && !empty($params['to']))
			$to = $params['to'];
    	// or from shortcode
		else if ( !empty($this->userdata['to']) )
			$to = $this->userdata['to'];
		// or default
		else
			$to = $this->o['to_email'];
		
		$from	= $this->o['from_email'];
	
		$name	= $_POST['tcf_sender'.$n];
		$email	= $_POST['tcf_email'.$n];
		$subject= $this->o['subpre'].' '.$_POST['tcf_subject'.$n];
		$msg	= $_POST['tcf_msg'.$n];
		
		// additional fields
		$extra = '';
		foreach ($_POST as $k => $f )
			if ( strpos( $k, 'tcf_field_') !== false )
				$extra .= $this->o[substr($k, 4, 7)].": $f\r\n";
		
		// create mail
		$headers =
		"MIME-Version: 1.0\r\n".
		"Reply-To: \"$name\" <$email>\r\n".
		"Content-Type: text/plain; charset=\"".get_settings('blog_charset')."\"\r\n";
		if ( !empty($from) )
			$headers .= "From: ".get_bloginfo('name')." - $name <$from>\r\n";
		else if ( !empty($email) )
			$headers .= "From: ".get_bloginfo('name')." - $name <$email>\r\n";

		$fullmsg =
		"Name: $name\r\n".
		"Email: $email\r\n".
		$extra."\r\n".
		'Subject: '.$_POST['tcf_subject'.$n]."\r\n\r\n".
		wordwrap($msg, 76, "\r\n")."\r\n\r\n".
		'Referer: '.$_SERVER['HTTP_REFERER']."\r\n".
		'Browser: '.$_SERVER['HTTP_USER_AGENT']."\r\n";
		
    	// send mail
		if ( wp_mail( $to, $subject, $fullmsg, $headers) )
		{
			// ok
			if ( $this->o['hideform'] )
			{
				unset($_POST['tcf_sender'.$n]);
				unset($_POST['tcf_email'.$n]);
				unset($_POST['tcf_subject'.$n]);
				unset($_POST['tcf_msg'.$n]);
				foreach ($_POST as $k => $f )
					if ( strpos( $k, 'tcf_field_') !== false )
						unset($k);
			}
			$result = $this->o['msg_ok'];
		}
		else
			// error
			$result = $this->o['msg_err'];
    }
    return $result;
}

/**
 * shows options page
 */
function optionsPage()
{	
	global $retcf_version;
	if (!current_user_can('manage_options'))
		wp_die(__('Sorry, but you have no permissions to change settings.'));
		
	// save data
	if ( isset($_POST['tcf_save']) )
	{
		$to = stripslashes($_POST['tcf_to_email']);
		if ( empty($to) )
			$to = get_option('admin_email');
		$msg_ok = stripslashes($_POST['tcf_msg_ok']);
		if ( empty($msg_ok) )
			$msg_ok = "Thank you! Your message was sent successfully.";
		$msg_err = stripslashes($_POST['tcf_msg_err']);
		if ( empty($msg_err) )
			$msg_err = "Sorry. An error occured while sending the message!";
		$hideform = ( isset($_POST['tcf_hideform']) ) ? 1 : 0;
		
		$this->o = array(
			'to_email'		=> $to,
			'from_email'	=> stripslashes($_POST['tcf_from_email']),
			'css'			=> stripslashes($_POST['tcf_css']),
			'msg_ok'		=> $msg_ok,
			'msg_err'		=> $msg_err,
			'submit'		=> stripslashes($_POST['tcf_submit']),
			'captcha_public_key'=> stripslashes($_POST['tcf_captcha_public_key']),
			'captcha_private_key'=> stripslashes($_POST['tcf_captcha_private_key']),
			'subpre'		=> stripslashes($_POST['tcf_subpre']),
			'field_1'		=> stripslashes($_POST['tcf_field_1']),
			'field_2'		=> stripslashes($_POST['tcf_field_2']),
			'field_3'		=> stripslashes($_POST['tcf_field_3']),
			'field_4'		=> stripslashes($_POST['tcf_field_4']),
			'field_5'		=> stripslashes($_POST['tcf_field_5']),
			'hideform'			=> $hideform
			);
		update_option('re_tiny_contact_form', $this->o);
	}
		
	// show page
	?>
	<div id="poststuff" class="wrap">
		<h2><img src="<?php echo $this->getResource('tcf_logo.png') ?>" alt="" style="width:24px;height:24px" /> Tiny Contact Form</h2>
		<div class="postbox">
		<h3><?php _e('Options', 'cpd') ?></h3>
		<div class="inside">
		
		<form action="options-general.php?page=RE-TINY-CONTACT-FORM" method="post">
	    <table class="form-table">
		<tr>
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Form', 'tcf-lang'); ?></strong></td>
		</tr>
    	<tr>
			<th><?php _e('TO:', 'tcf-lang')?></th>
			<td><input name="tcf_to_email" type="text" size="70" value="<?php echo $this->o['to_email'] ?>" /><br /><?php _e('E-mail'); ?>, <?php _e('one or more (e.g. email1,email2,email3)', 'tcf-lang'); ?></td>
		</tr>
    	<tr>
			<th><?php _e('FROM:', 'tcf-lang')?> <?php _e('(optional)', 'tcf-lang'); ?></th>
			<td><input name="tcf_from_email" type="text" size="70" value="<?php echo $this->o['from_email'] ?>" /><br /><?php _e('E-mail'); ?></td>
		</tr>
    	<tr>
			<th><?php _e('Message OK:', 'tcf-lang')?></th>
			<td><input name="tcf_msg_ok" type="text" size="70" value="<?php echo $this->o['msg_ok'] ?>" /></td>
		</tr>
    	<tr>
			<th><?php _e('Message Error:', 'tcf-lang')?></th>
			<td><input name="tcf_msg_err" type="text" size="70" value="<?php echo $this->o['msg_err'] ?>" /></td>
		</tr>
		<tr>
			<th><?php _e('Submit Button:', 'tcf-lang')?> <?php _e('(optional)', 'tcf-lang'); ?></th>
			<td><input name="tcf_submit" type="text" size="70" value="<?php echo $this->o['submit'] ?>" /></td>
		</tr>
    	<tr>
			<th><?php _e('Subject Prefix:', 'tcf-lang')?> <?php _e('(optional)', 'tcf-lang'); ?></th>
			<td><input name="tcf_subpre" type="text" size="70" value="<?php echo $this->o['subpre'] ?>" /></td>
		</tr>
    	<tr>
			<th><?php _e('Additional Fields:', 'tcf-lang')?></th>
			<td>
				<p><?php _e('The contact form includes the fields Name, Email, Subject and Message. If you need more (e.g. Phone, Website) type in the name of the field.', 'tcf-lang'); ?></p>
				<?php
				for ( $x = 1; $x <= 5; $x++ )
					echo '<p>'.__('Field', 'tcf-lang').' '.$x.': <input name="tcf_field_'.$x.'" type="text" size="30" value="'.$this->o['field_'.$x].'" /></p>';
				?>
			</td>
		</tr>
    	<tr>
			<th><?php _e('After Submit', 'tcf-lang')?>:</th>
			<td><label for="tcf_hideform"><input name="tcf_hideform" id="tcf_hideform" type="checkbox" <?php if($this->o['hideform']==1) echo 'checked="checked"' ?> /> <?php _e('hide the form', 'tcf-lang'); ?></label></td>
		</tr>
		<tr>
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Captcha', 'tcf-lang'); ?></strong></td>
		</tr>
    	<tr>
			<th><?php _e('ReCaptcha Public Key:', 'tcf-lang')?></th>
			<td><input name="tcf_captcha_public_key" type="text" size="70" value="<?php echo $this->o['captcha_public_key'] ?>" /></td>
		</tr>
    	<tr>
			<th><?php _e('ReCaptcha Private Key:', 'tcf-lang')?></th>
			<td><input name="tcf_captcha_private_key" type="text" size="70" value="<?php echo $this->o['captcha_private_key'] ?>" /></td>
		</tr>
		<tr>
			<td colspan="2" style="border-top: 1px #ddd solid; background: #eee"><strong><?php _e('Style', 'tcf-lang'); ?></strong></td>
		</tr>
    	<tr>
			<th>
				<?php _e('StyleSheet:', 'tcf-lang'); ?><br />
				<a href="javascript:resetCss();"><?php _e('reset', 'tcf-lang'); ?></a>
			</th>
			<td>
				<textarea name="tcf_css" id="tcf_css" style="width:100%" rows="10"><?php echo $this->o['css'] ?></textarea><br />
				<?php _e('Use this field or the <code>style.css</code> in your theme directory.', 'tcf-lang') ?>
			</td>
		</tr>
		</table>
		<p class="submit">
			<input name="tcf_save" class="button-primary" value="<?php _e('Save Changes'); ?>" type="submit" />
		</p>
		</form>
		
		<script type="text/javascript">
		function resetCss()
		{
			css = ".contactform {}\n.contactform label {}\n.contactform input {}\n.contactform textarea {}\n"
				+ ".contactform_respons {}\n.contactform_error {}\n.widget .contactform { /* same fields but in sidebar */ }";
			document.getElementById('tcf_css').value = css;
		}
		</script>
	</div>
	</div>
	
	<div class="postbox">
		<h3><?php _e('Contact', 'tcf-lang') ?></h3>
		<div class="inside">
			<p>
			Recaptchad Tiny Contact Form: <code><?php echo $retcf_version ?></code><br />
			<?php _e('Bug? Problem? Question? Hint? Praise?', 'tcf-lang') ?><br />
			<?php printf(__('Write a comment on the <a href="%s">plugin page</a>.', 'tcf-lang'), 'http://www.tomsdimension.de/wp-plugins/RE-TINY-CONTACT-FORM'); ?><br />
			<?php _e('License') ?>: <a href="http://www.tomsdimension.de/postcards">Postcardware :)</a>
			</p>
			<p><a href="<?php echo get_bloginfo('wpurl').'/'.PLUGINDIR ?>/RE-TINY-CONTACT-FORM/readme.txt?KeepThis=true&amp;TB_iframe=true" title="Tiny Contact Form - Readme.txt" class="thickbox"><strong>Readme.txt</strong></a></p>
		</div>
	</div>
	
	</div>
	<?php
}

/**
 * adds admin menu
 */
function addOptionsPage()
{
	global $wp_version;
	$menutitle = '';
	if ( version_compare( $wp_version, '2.6.999', '>' ) )
		$menutitle = '<img src="'.$this->getResource('tcf_menu.png').'" alt="" /> ';
	$menutitle .= 'Tiny Contact Form';
	add_options_page('Tiny Contact Form', $menutitle, 9, 'RE-TINY-CONTACT-FORM', array( &$this, 'optionsPage'));
}

/**
 * parses parameters
 *
 * @param string $atts parameters
 */
function shortcode( $atts )
{
	// e.g. [TINY-CONTENT-FORM to="abc@xyz.com" subject="xyz"]
	
	extract( shortcode_atts( array(
		'to' => '',
		'subject' => ''
	), $atts) );
	$this->userdata = array(
		'to' => $to,
		'subject' => $subject
	);
	return $this->showForm();
}

/**
 * check input fields
 * 
 * @return string message
 */
function checkInput( $n = '' )
{
	// exit if no form data
	if ( !isset($_POST['tcf_sendit'.$n]))
		return false;

	// hidden field check
	if ( (isset($_POST['tcf_sendit'.$n]) && $_POST['tcf_sendit'.$n] != 1)
		|| (isset($_POST['tcf_name'.$n]) && $_POST['tcf_name'.$n] != '') )
	{
		return 'No Spam please!';
	}
	
	$_POST['tcf_sender'.$n] = stripslashes(trim($_POST['tcf_sender'.$n]));
	$_POST['tcf_email'.$n] = stripslashes(trim($_POST['tcf_email'.$n]));
	$_POST['tcf_subject'.$n] = stripslashes(trim($_POST['tcf_subject'.$n]));
	$_POST['tcf_msg'.$n] = stripslashes(trim($_POST['tcf_msg'.$n]));
//    extra felder

	$error = array();
	if ( empty($_POST['tcf_sender'.$n]) )
		$error[] = __('Name', 'tcf-lang');
    if ( !is_email($_POST['tcf_email'.$n]) )
		$error[] = __('Email', 'tcf-lang');
    if ( empty($_POST['tcf_subject'.$n]) )
		$error[] = __('Subject', 'tcf-lang');
    if ( empty($_POST['tcf_msg'.$n]) )
		$error[] = __('Your Message', 'tcf-lang');
	$resp = recaptcha_check_answer ($this->o['captcha_private_key'],
                                $_SERVER["REMOTE_ADDR"],
                                $_POST["recaptcha_challenge_field"],
                                $_POST["recaptcha_response_field"]);
    if (!$resp->is_valid) {
      // What happens when the CAPTCHA was entered incorrectly
      $error[] = "The reCAPTCHA wasn't entered correctly. Go back and try it again." .
           "(reCAPTCHA said: " . $resp->error . ")";
    }

	if ( !empty($error) )
		return __('Check these fields:', 'tcf-lang').' '.implode(', ', $error);
	
	return 'OK';
}

/**
 * clean up when uninstall
 */
function uninstall()
{
	delete_option('re_tiny_contact_form');
}

/**
 * adds custom style to page
 */
function addStyle()
{
	echo "\n<!-- Tiny Contact Form -->\n"
		."<style type=\"text/css\">\n"
		.".tcf_input {display:none !important; visibility:hidden !important;}\n"
		.$this->o['css']."\n"
		."</style>\n";
}

/**
 * adds an action link to the plugins page
 */
function pluginActions($links, $file)
{
	if( $file == plugin_basename(__FILE__)
		&& strpos( $_SERVER['SCRIPT_NAME'], '/network/') === false ) // not on network plugin page
	{
		$link = '<a href="options-general.php?page=RE-TINY-CONTACT-FORM">'.__('Settings').'</a>';
		array_unshift( $links, $link );
	}
	return $links;
}

/**
 * defines base64 encoded image recources
 */
function setRecources()
{
	if ( isset($_GET['resource']) && !empty($_GET['resource']) )
	{
		# base64 encoding
		$resources = array(
			'tcf_menu.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAYAAABWdVznAAAAAX'.
			'NSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYA'.
			'AICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAABh0RV'.
			'h0U29mdHdhcmUAUGFpbnQuTkVUIHYzLjM2qefiJQAAAEtJREFU'.
			'KFNj9GD4/58BCnYwMDI2NDTA+TBxymiQDTAMMglkAz5Mum20t4'.
			'GQm9HlwZ4k1iNgtWRpIMVZINewEusksDrkYEWOQFyGAABXBYxc'.
			'mDNSvQAAAABJRU5ErkJggg==',
			'tcf_logo.png' =>
			'iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAX'.
			'NSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYA'.
			'AICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAABh0RV'.
			'h0U29mdHdhcmUAUGFpbnQuTkVUIHYzLjM2qefiJQAAAHpJREFU'.
			'SEtj9GD4/58BC9jBwMgIEm5oaMAqj00PVjGQBdgwTDHIAkow0Q'.
			'4ZvAppHkQ0t2Dwhi2xLqN5ENHcAmJ9OnjVUVIMEKMXXJjREsMt'.
			'oHYYwxw9agHOkMUIIlpFNO1TEdCPrFBM7YQEN4+2FuAq7NDFyf'.
			'YerS0AAHa/Vp9sTByIAAAAAElFTkSuQmCC');
			 
		if ( array_key_exists($_GET['resource'], $resources) )
		{
			$content = base64_decode($resources[ $_GET['resource'] ]);
			$lastMod = filemtime(__FILE__);
			$client = ( isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false );
			if (isset($client) && (strtotime($client) == $lastMod))
			{
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 304);
				exit;
			}
			else
			{
				header('Last-Modified: '.gmdate('D, d M Y H:i:s', $lastMod).' GMT', true, 200);
				header('Content-Length: '.strlen($content));
				header('Content-Type: image/' . substr(strrchr($_GET['resource'], '.'), 1) );
				echo $content;
				exit;
			}
		}
	}
}

/**
 * gets image recource with given name
 */
function getResource( $resourceID ) {
	return trailingslashit( get_bloginfo('url') ).'?resource='.$resourceID;
}


/**
 * calls widget class
 */
function register_widgets()
{
	register_widget('ReTinyContactForm_Widget');
}

} // TCF class

class ReTinyContactForm_Widget extends WP_Widget
{
	var $fields = array('Title', 'Subject', 'To');
	
	/**
	 * constructor
	 */	 
	function ReTinyContactForm_Widget() {
		parent::WP_Widget('tcform_widget', 'Tiny Contact Form', array('description' => 'Little Contact Form'));	
	}
 
	/**
	 * display widget
	 */	 
	function widget( $args, $instance)
	{
		global $re_re_tiny_contact_form;
		extract($args, EXTR_SKIP);
		$title = empty($instance['title']) ? '&nbsp;' : apply_filters('widget_title', $instance['title']);
		echo $before_widget;
		if ( !empty( $title ) )
			echo $before_title.$title.$after_title;
		echo $re_re_tiny_contact_form->showForm( $instance );
		echo $after_widget;
	}
 
	/**
	 *	update/save function
	 */	 	
	function update( $new_instance, $old_instance )
	{
		$instance = $old_instance;
		foreach ( $this->fields as $f )
			$instance[strtolower($f)] = strip_tags($new_instance[strtolower($f)]);
		return $instance;
	}
 
	/**
	 *	admin control form
	 */	 	
	function form( $instance )
	{
		$default = array('title' => 'Tiny Contact Form');
		$instance = wp_parse_args( (array) $instance, $default );
 
		foreach ( $this->fields as $field )
		{ 
			$f = strtolower( $field );
			$field_id = $this->get_field_id( $f );
			$field_name = $this->get_field_name( $f );
			echo "\r\n".'<p><label for="'.$field_id.'">'.__($field, 'tcf-lang').': <input type="text" class="widefat" id="'.$field_id.'" name="'.$field_name.'" value="'.attribute_escape( $instance[$f] ).'" /><label></p>';
		}
	}
} // widget class

/*
 * This is a PHP library that handles calling reCAPTCHA.
 *    - Documentation and latest version
 *          http://recaptcha.net/plugins/php/
 *    - Get a reCAPTCHA API Key
 *          https://www.google.com/recaptcha/admin/create
 *    - Discussion group
 *          http://groups.google.com/group/recaptcha
 *
 * Copyright (c) 2007 reCAPTCHA -- http://recaptcha.net
 * AUTHORS:
 *   Mike Crawford
 *   Ben Maurer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * The reCAPTCHA server URL's
 */
define("RECAPTCHA_API_SERVER", "http://www.google.com/recaptcha/api");
define("RECAPTCHA_API_SECURE_SERVER", "https://www.google.com/recaptcha/api");
define("RECAPTCHA_VERIFY_SERVER", "www.google.com");

/**
 * Encodes the given data into a query string format
 * @param $data - array of string elements to be encoded
 * @return string - encoded request
 */
function _recaptcha_qsencode ($data) {
        $req = "";
        foreach ( $data as $key => $value )
                $req .= $key . '=' . urlencode( stripslashes($value) ) . '&';

        // Cut the last '&'
        $req=substr($req,0,strlen($req)-1);
        return $req;
}



/**
 * Submits an HTTP POST to a reCAPTCHA server
 * @param string $host
 * @param string $path
 * @param array $data
 * @param int port
 * @return array response
 */
function _recaptcha_http_post($host, $path, $data, $port = 80) {

        $req = _recaptcha_qsencode ($data);

        $http_request  = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . strlen($req) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $req;

        $response = '';
        if( false == ( $fs = @fsockopen($host, $port, $errno, $errstr, 10) ) ) {
                die ('Could not open socket');
        }

        fwrite($fs, $http_request);

        while ( !feof($fs) )
                $response .= fgets($fs, 1160); // One TCP-IP packet
        fclose($fs);
        $response = explode("\r\n\r\n", $response, 2);

        return $response;
}



/**
 * Gets the challenge HTML (javascript and non-javascript version).
 * This is called from the browser, and the resulting reCAPTCHA HTML widget
 * is embedded within the HTML form it was called from.
 * @param string $pubkey A public key for reCAPTCHA
 * @param string $error The error given by reCAPTCHA (optional, default is null)
 * @param boolean $use_ssl Should the request be made over ssl? (optional, default is false)

 * @return string - The HTML to be embedded in the user's form.
 */
function recaptcha_get_html ($pubkey, $error = null, $use_ssl = false)
{
	if ($pubkey == null || $pubkey == '') {
		die ("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>");
	}
	
	if ($use_ssl) {
                $server = RECAPTCHA_API_SECURE_SERVER;
        } else {
                $server = RECAPTCHA_API_SERVER;
        }

        $errorpart = "";
        if ($error) {
           $errorpart = "&amp;error=" . $error;
        }
        return '<script type="text/javascript" src="'. $server . '/challenge?k=' . $pubkey . $errorpart . '"></script>

	<noscript>
  		<iframe src="'. $server . '/noscript?k=' . $pubkey . $errorpart . '" height="300" width="500" frameborder="0"></iframe><br/>
  		<textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
  		<input type="hidden" name="recaptcha_response_field" value="manual_challenge"/>
	</noscript>';
}




/**
 * A ReCaptchaResponse is returned from recaptcha_check_answer()
 */
class ReCaptchaResponse {
        var $is_valid;
        var $error;
}


/**
  * Calls an HTTP POST function to verify if the user's guess was correct
  * @param string $privkey
  * @param string $remoteip
  * @param string $challenge
  * @param string $response
  * @param array $extra_params an array of extra variables to post to the server
  * @return ReCaptchaResponse
  */
function recaptcha_check_answer ($privkey, $remoteip, $challenge, $response, $extra_params = array())
{
	if ($privkey == null || $privkey == '') {
		die ("To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>");
	}

	if ($remoteip == null || $remoteip == '') {
		die ("For security reasons, you must pass the remote ip to reCAPTCHA");
	}

	
	
        //discard spam submissions
        if ($challenge == null || strlen($challenge) == 0 || $response == null || strlen($response) == 0) {
                $recaptcha_response = new ReCaptchaResponse();
                $recaptcha_response->is_valid = false;
                $recaptcha_response->error = 'incorrect-captcha-sol';
                return $recaptcha_response;
        }

        $response = _recaptcha_http_post (RECAPTCHA_VERIFY_SERVER, "/recaptcha/api/verify",
                                          array (
                                                 'privatekey' => $privkey,
                                                 'remoteip' => $remoteip,
                                                 'challenge' => $challenge,
                                                 'response' => $response
                                                 ) + $extra_params
                                          );

        $answers = explode ("\n", $response [1]);
        $recaptcha_response = new ReCaptchaResponse();

        if (trim ($answers [0]) == 'true') {
                $recaptcha_response->is_valid = true;
        }
        else {
                $recaptcha_response->is_valid = false;
                $recaptcha_response->error = $answers [1];
        }
        return $recaptcha_response;

}

/**
 * gets a URL where the user can sign up for reCAPTCHA. If your application
 * has a configuration page where you enter a key, you should provide a link
 * using this function.
 * @param string $domain The domain where the page is hosted
 * @param string $appname The name of your application
 */
function recaptcha_get_signup_url ($domain = null, $appname = null) {
	return "https://www.google.com/recaptcha/admin/create?" .  _recaptcha_qsencode (array ('domains' => $domain, 'app' => $appname));
}

function _recaptcha_aes_pad($val) {
	$block_size = 16;
	$numpad = $block_size - (strlen ($val) % $block_size);
	return str_pad($val, strlen ($val) + $numpad, chr($numpad));
}

/* Mailhide related code */

function _recaptcha_aes_encrypt($val,$ky) {
	if (! function_exists ("mcrypt_encrypt")) {
		die ("To use reCAPTCHA Mailhide, you need to have the mcrypt php module installed.");
	}
	$mode=MCRYPT_MODE_CBC;   
	$enc=MCRYPT_RIJNDAEL_128;
	$val=_recaptcha_aes_pad($val);
	return mcrypt_encrypt($enc, $ky, $val, $mode, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
}


function _recaptcha_mailhide_urlbase64 ($x) {
	return strtr(base64_encode ($x), '+/', '-_');
}

/* gets the reCAPTCHA Mailhide url for a given email, public key and private key */
function recaptcha_mailhide_url($pubkey, $privkey, $email) {
	if ($pubkey == '' || $pubkey == null || $privkey == "" || $privkey == null) {
		die ("To use reCAPTCHA Mailhide, you have to sign up for a public and private key, " .
		     "you can do so at <a href='http://www.google.com/recaptcha/mailhide/apikey'>http://www.google.com/recaptcha/mailhide/apikey</a>");
	}
	

	$ky = pack('H*', $privkey);
	$cryptmail = _recaptcha_aes_encrypt ($email, $ky);
	
	return "http://www.google.com/recaptcha/mailhide/d?k=" . $pubkey . "&c=" . _recaptcha_mailhide_urlbase64 ($cryptmail);
}

/**
 * gets the parts of the email to expose to the user.
 * eg, given johndoe@example,com return ["john", "example.com"].
 * the email is then displayed as john...@example.com
 */
function _recaptcha_mailhide_email_parts ($email) {
	$arr = preg_split("/@/", $email );

	if (strlen ($arr[0]) <= 4) {
		$arr[0] = substr ($arr[0], 0, 1);
	} else if (strlen ($arr[0]) <= 6) {
		$arr[0] = substr ($arr[0], 0, 3);
	} else {
		$arr[0] = substr ($arr[0], 0, 4);
	}
	return $arr;
}

/**
 * Gets html to display an email address given a public an private key.
 * to get a key, go to:
 *
 * http://www.google.com/recaptcha/mailhide/apikey
 */
function recaptcha_mailhide_html($pubkey, $privkey, $email) {
	$emailparts = _recaptcha_mailhide_email_parts ($email);
	$url = recaptcha_mailhide_url ($pubkey, $privkey, $email);
	
	return htmlentities($emailparts[0]) . "<a href='" . htmlentities ($url) .
		"' onclick=\"window.open('" . htmlentities ($url) . "', '', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=500,height=300'); return false;\" title=\"Reveal this e-mail address\">...</a>@" . htmlentities ($emailparts [1]);

}

?>
