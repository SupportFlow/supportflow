=== SupportFlow ===
Contributors: VarunAgw, danielbachhuber, Viper007Bond, andrewspittle
Tags: Ticket, support, admin, customer, customer support, help desk, helpdesk, IT, support, ticket
Tested up to: 3.9.2
Stable tag: 0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SupportFlow is a web based help desk software. It allows teams to manage support tickets using a web interface.

== Description ==
SupportFlow is a web based help desk software. SupportFlow is a web based help desk software. It allows teams to manage support tickets using a web interface.

###A few of its features are:

+ Allow agents to create/reply tickets using web interface.

+ Customers can create ticket by sending an E-Mail or filling a web based form.

+ Agents can opt to get E-Mail notifications of replies of tickets.

+ Administrator can limit agents access to particular tags.

+ Administrators can see daily/weekly/monthly ticket statistics.

+ Agents can view tickets assigned to them in admin dashboard.

+ Agents can insert predefined replies to the tickets.

+ Agents can add their signature to tickets using one-click.

+ Agents can forward whole conversation to anyone using its E-Mail ID.

To contribute to SupportFlow visit [github] (http://github.com/SupportFlow/supportflow/).

== Installation ==
1. Install SupportFlow either via the WordPress.org plugin directory, or by uploading the files to your server and activate it.

2. After activating open E-Mail accounts page in SupportFlow menu and add an E-Mail account you wish to use with SupportFlow.

Now you are ready to use SupportFlow.

== Frequently Asked Questions ==

###How can I insert a SupportFlow contact form in my site.

Just add **[supportflow_submissionform]** shortcode to the post you want to add SupportFlow form to.

###I am unable to add an E-Mail account

1. Check if connection settings are correct especially port number and SSL settings for both IMAP and SMTP.

2. Some E-Mail providers disable IMAP access by default and allow it to enable through settings. So make sure it is enabled

3 In case you use 2-steps authentication, you have to use application specific password.

4. Ensure IMAP extension is installed and enabled. You may also want to ensure extension is build with SSL support

5. Check if E-Mail hosting provider is not blocking connection thinking it is suspicious. You may also receive an E-Mail alert from your E-Mail provider for the same.

== Changelog ==

= 0.3 =
* Auto-saving tickets
* Recently created tickets widget in dashboard
* Ticket assigned to current agent widget in dashboard
* Other tickets by the customer in view ticket page
* Allow agents to add signature in ticket
* Allowing basic HTML in replies
* Easy creation of support form using [supportflow_submissionform] shortcode
* Securely saving attachments
* Fixed bugs

= 0.2 =
* Fix nonce and escaping bugs.
* Add ability to forward a ticket to someone outside of SupportFlow.
* Add a statistics page.
* Various minor bug fixes.
* Various UI and UX tweaks.

= 0.1 =
* Initial release.
