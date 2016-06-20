=== SupportFlow ===
Contributors: VarunAgw, danielbachhuber, Viper007Bond, andrewspittle, iandunn, kovshenin
Tags: Ticket, support, admin, customer, customer support, help desk, helpdesk, IT, support, ticket
Requires at least: 3.5
Tested up to: 4.5
Stable tag: 0.7
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
### How can I insert a SupportFlow contact form in my site.

Just add **[supportflow_submissionform]** shortcode to the post you want to add SupportFlow form to.

### Why aren't messages being sent or received? =
After successfully adding an e-mail account under SupportFlow > E-mail Accounts, make sure you wait for at least 5 minutes because the retrieval script only runs once every 5 minutes.

If it still isn't working, check the log for any error messages or clues. To view the logs, add this line to a
[functionality plugin](http://wpcandy.com/teaches/how-to-create-a-functionality-plugin/), and then visit the SupportFlow > Log page.

`add_filter( 'supportflow_show_log', '__return_true' );`

**Warning:** The logs will contain private data, so be very careful if you share them with anyone. Make sure you redact anything that you don't want to share.


== Changelog ==

= 0.7 (2016-06-28) =
* Security: Fix two XSS vulnerabilities: [#145091-h1](https://hackerone.com/reports/145091) with CVSS score [6.1 (Medium)](https://www.first.org/cvss/calculator/3.0#CVSS:3.0/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N), and [#145086-h1](https://hackerone.com/reports/145086) with CVSS score [4.7 (Medium)](https://www.first.org/cvss/calculator/3.0#CVSS:3.0/AV:N/AC:H/PR:N/UI:R/S:C/C:L/I:L/A:N). Thanks to [whitehatter](https://hackerone.com/whitehatter) for discovering and disclosing them responsibly.
* Security: Add extra output escaping throughout plugin.
* Fix: Respect the Reply-To header.
* Update: Increase Gmail synchronization interval to 10 minutes.
* [Full changelog](https://github.com/SupportFlow/supportflow/compare/bb204abf5e48d0b3f6d12ebf4435613622ba10bd...fe706ccdb9859af32dddfff1c94c4c7048d794ea)

= 0.6 =
* Security: Tightened access controls to read and set SupportFlow permissions.
* Privacy: Prevented leaking e-mail subject lines in search results.
* Privacy: Prevented leaking customer e-mail addresses to WordPress users that lacked SupportFlow permissions.

= 0.5 =
* Improved handling of large mailboxes during initial message download.
* Tickets are now re-opened when new replies are added.
* Better handling of non-ASCII messages.
* Outgoing messages are now prefixed with a sequential ticket number instead of a random code.
* Agents are now notified if a message failed to send.
* Several minor bug fixes.
* [Full changelog](https://github.com/SupportFlow/supportflow/compare/518832bf05df04a5f4af6787b253f99269773bd6...a93947b597d5c5a14ec35ef15ec2ca38af60253e)

= 0.4 =
* Uploads are now attached when sending a reply.
* Fix the timestamps on replies.
* Added ctrl-enter keyboard shortcut to submit replies.
* The original message is now quoted in replies.
* Added a filter to modify the IMAP cron interval.
* Added some logging to help troubleshoot problems.
* [Full changelog](https://github.com/SupportFlow/supportflow/compare/c1535678da9a8380e42672ceef71174f2d01fc88...5675f1d7e2c1dd3e7e8359d409a1e80ca8a8b141)

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

== Upgrade Notice ==

= 0.7 =
Version 0.7 contains fixes for two medium-severity security bugs. We strongly recommend that you upgrade.
