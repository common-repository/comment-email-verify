=== Comment E-Mail Verification ===
Contributors: tfnab
Donate link: http://ten-fingers-and-a-brain.com/donate/
Tags: comments, spam, email, verification, email verification, commentmeta
Requires at least: 2.9
Tested up to: 3.8
Stable tag: 0.4.2

If a comment is held for moderation an email message is sent to the comment author with a link to verify the comment author's email address.

== Description ==

If a comment is held for moderation an email message is sent to the comment author with a link to verify the comment author's email address. When the comment author clicks on that link the comment gets approved immediately. This makes discussions more lively as users don't have to wait for the blog admin to approve the comment.

Blog owners may also choose to hold the comments in the moderation queue even after successful verification. The verification status is shown in the comment lists in the admin.

If an author has a previously approved comment and his comments gets approved automatically according to the 'comment_whitelist' option in WordPress no email is sent.

If a comment is classified as spam by Akismet or another anti-spam plugin no email is sent.

This plugin uses the `commentmeta` table and thus requires WordPress 2.9

== Installation ==

1. Upload the entire `comment-email-verify` folder to the `wp-content/plugins` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to <em>Settings</em> -> <em>Comment E-Mail Verification</em> to customize the email message that is sent to comment authors. You may use a number of <a href="http://ten-fingers-and-a-brain.com/wordpress-plugins/comment-email-verify/shortcodes/">shortcodes</a>

== Frequently Asked Questions ==

= Will this plugin work with versions of WordPress older than 2.9? =

Absolutely not. This plugin uses the `commentmeta` table new to WordPress 2.9

= I don't get notified when a comment is held for moderation. =

This plugin doesn't notify you. It sends mail to the comment author.

= When I test this using my Yahoo/Gmail/Hotmail/xyz address I don't receive a verification message. It's not even in my spam folder. =

You should try configuring the plugin to use SMTP!

If you have already done so, please check your settings!

If it's only Yahoo/Gmail/Hotmail/xyz users who don't receive your messages, but it works well for others, then it might be that your host has a bad reputation and is blacklisted. (I've seen that happen so many times. Notice the "might".)

== Screenshots ==

1. Customize the email message that is sent to comment authors
2. Comment authors will see a hint after posting their comment (theme must be compatible)

== Changelog ==

= 0.4.2 =
* Fixes another bug that caused verification links generated with older versions of the plugin to malfunction with some setups
* JavaScript sanity checks of SMTP options
* Better sanitization of SMTP options in PHP (when JavaScript is turned off)
* Verification codes not generated for trackbacks or pingbacks (thanks <a href="http://yoast.com/">Joost de Valk</a> for feedback and code contribution)

= 0.4.1 =
* Fixed a bug that caused the verification link to malfunction with some setups

= 0.4 =
* Note that E-mail address has not been verified in comment row actions, next to link 'Approve'
* Option to hold comments for moderation even after commenters have verified their E-mail address
* Splash screens editable

= 0.3 =
* Send mail using SMTP (thanks <a href="http://meandmark.com/">Mark</a> for testing and feedback)
* German L10n

= 0.2 =
* Administrators can now customize the sender mail address
* 'Your comment is awaiting moderation.' hint altered – requires internationalized comment template (default template is perfect), can be customized
* Optional splash screens when someone clicks on a verification link
* Nicer error messages in case something goes wrong
* Longer (safer) verification keys

= 0.1.2.1 =
* Fixes a bug where mail was sent to authors of spam comments

= 0.1.2 =
* Contextual help, with links to <a href="http://ten-fingers-and-a-brain.com/wordpress-plugins/comment-email-verify/shortcodes/">shortcodes</a> and <a href="http://ten-fingers-and-a-brain.com/wordpress-plugins/comment-email-verify/troubleshooting/">troubleshooting e-mail delivery</a>
* Code styling: wrapper class
* WordPress core settings that affect the behaviour of this plugin can be altered on the plugin's options page directly
* Much nicer default message template

= 0.1.1 =
* Administrators can now customize the email message that is sent to comment authors
* Blogname encoding issue fixed
* Default message slightly reformatted
* Short GPLv3 note in source code

= 0.1 =
* initial public release

== Upgrade Notice ==

= 0.4.2 =
Verification codes not generated for trackbacks or pingbacks

= 0.4.1 =
Fixed a bug that caused the verification link to malfunction with some setups

= 0.4 =
Splash screens editable, Option to hold comments for moderation even after commenters have verified their E-mail address

= 0.3 =
Send mail using SMTP

= 0.2 =
Change the sender mail address, 'Your comment is awaiting moderation.' hint altered, optional splash screens, nicer error messages

= 0.1.2.1 =
Fixes a bug where mail was sent to authors of spam comments

= 0.1.2 =
Much nicer default message template

= 0.1.1 =
Administrators can now customize the email message that is sent to comment authors
