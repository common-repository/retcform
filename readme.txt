=== ReCaptchad Tiny Contact Form ===
Contributors: Tom Braider, SidHarrell
Tags: email, mail, contact, form, recaptcha
Donate link: http://imaginedc.net
Requires at least: 2.8
Tested up to: 3.0.3
Stable tag: 0.2

Modification of the original Tiny Contact Form by Tom Braider to use Google's recaptcha service.

== Description ==

Use '[TINY-CONTACT-FORM]' within any post or page.
Add the widget to your sidebar. Make sure the container is at least 320px wide.

== Installation ==

1. unzip plugin directory into the '/wp-content/plugins/' directory
1. activate the plugin through the 'Plugins' menu in WordPress
1. check the settings (email, messages, style) in backend
1. insert '[TINY-CONTACT-FORM]' in your page or/and add the widget to your sidebar
1. without widgets use this code to insert the form in your sidebar.
   '&lt;?php if (isset($tiny_contact_form)) echo $tiny_contact_form->showForm(); ?&gt;'

== Frequently Asked Questions ==

= How to style? =
- The complete form is surrounded by a 'div class="contactform"'. Tags in FORM: LABEL, INPUT and TEXTAREA.
- To change the form style in your sidebar you can use '.widget .contactform' (plus tags above) in your template 'style.css'.
- Since v0.3 you can use the settings.

= Need Help? Find a Bug? =
email to sidney.harrell@gmail.com

== Screenshots ==

1. contact form on page
2. contact form widget in sidebar
3. settings page

== Arbitrary section ==

**Translations**

* by: Marcis Gasuns http://www.fatcow.com
* da: Jonas Thomsen http://jonasthomsen.com
* de: myself ;)
* es: Jeffrey Borb&oacute;n http://www.eljeffto.com 
* fr: Jef Blog
* he: Sahar Ben-Attar http://openit.co.il
* hr, it: Alen &Scaron;irola http://www.gloriatours.hr
* hu: MaXX http://www.novamaxx.hu
* sv: Thomas http://www.ajfix.se

== Changelog ==

= 0.2 =
* added contact info

= 0.1 =
* branched from version 0.7 of Tiny Contact Form
