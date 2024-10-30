<?php
/*
Plugin Name: Comment E-Mail Verification
Plugin URI: http://ten-fingers-and-a-brain.com/wordpress-plugins/comment-email-verify/
Version: 0.4.2
Description: If a comment is held for moderation an e-mail message is sent to the comment's author with a link to verify the comment author's e-mail address. When the comment author clicks on that link the comment gets approved immediately. This makes discussions more lively as users don't have to wait for the blog admin to approve the comment.
Author: Martin Lormes
Author URI: http://ten-fingers-and-a-brain.com/
Text Domain: comment-email-verify
*/
/*
Copyright (c) 2010 Martin Lormes

This program is free software; you can redistribute it and/or modify it under 
the terms of the GNU General Public License as published by the Free Software 
Foundation; either version 3 of the License, or (at your option) any later 
version.

This program is distributed in the hope that it will be useful, but WITHOUT 
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with 
this program. If not, see <http://www.gnu.org/licenses/>.
*/
/** Comment E-Mail Verification (WordPress Plugin) */

// i18n/l10n
load_plugin_textdomain ( 'comment-email-verify', '', basename ( dirname ( __FILE__ ) ) );

/** Comment E-Mail Verification (WordPress Plugin) functions wrapped in a class. (namespacing pre PHP 5.3) */
class comment_email_verify
{

  /**
   * hooked to {@link http://codex.wordpress.org/Plugin_API/Filter_Reference WordPress filter}: {@link http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail_from wp_mail_from}
   *
   * @since 0.2
   */
  function wp_mail_from ( $s )
  {
    $options = get_option ( 'comment-email-verify' );
    if ( isset ( $options['advanced']['from'] ) && '' != $options['advanced']['from'] ) return $options['advanced']['from'];
    return $s;
  }

  /**
   * hooked to {@link http://codex.wordpress.org/Plugin_API/Filter_Reference WordPress filter}: {@link http://codex.wordpress.org/Plugin_API/Filter_Reference/wp_mail_from_name wp_mail_from_name}
   *
   * @since 0.2
   */
  function wp_mail_from_name ( $s )
  {
    $options = get_option ( 'comment-email-verify' );
    if ( isset ( $options['advanced']['from_name'] ) && '' != $options['advanced']['from_name'] ) return $options['advanced']['from_name'];
    return $s;
  }

  /**
   * @since 0.3
   */
  function phpmailer_init ( &$phpmailer )
  {
    $options = get_option ( 'comment-email-verify' );
    
    if ( isset ( $options['smtp']['Host'] ) && '' != $options['smtp']['Host'] )
    {
      $phpmailer->IsSmtp ();
      $phpmailer->Host = $options['smtp']['Host'];
      
      if ( isset ( $options['smtp']['SMTPSecure'] ) && '' != $options['smtp']['SMTPSecure'] )
      {
        $phpmailer->SMTPSecure = $options['smtp']['SMTPSecure'];
        if ( 'ssl' == $options['smtp']['SMTPSecure'] ) $phpmailer->Port = '465';
      }
      
      if ( isset ( $options['smtp']['Port'] ) && '' != $options['smtp']['Port'] ) $phpmailer->Port = $options['smtp']['Port'];
      
      if ( isset ( $options['smtp']['Timeout'] ) && '' != $options['smtp']['Timeout'] ) $phpmailer->Timeout = $options['smtp']['Timeout'];
      if ( isset ( $options['smtp']['Helo'] ) && '' != $options['smtp']['Helo'] ) $phpmailer->Helo = $options['smtp']['Helo'];
      
      if ( isset ( $options['smtp']['Username'] ) && '' != $options['smtp']['Username'] )
      {
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $options['smtp']['Username'];
        $phpmailer->Password = $options['smtp']['Password'];
      }
    }
  }
  
  /**
   * hooked to {@link http://codex.wordpress.org/Plugin_API/Action_Reference WordPress action}: {@link http://codex.wordpress.org/Plugin_API/Action_Reference/wp_insert_comment wp_insert_comment}
   *
   * moved to this hook (from comment_post) in 0.4.2, props {@link http://yoast.com/ yoast}
   */
  function wp_insert_comment ( $id, $thecomment )
  {
    if ( '0' == $thecomment->comment_approved && ( '' == $thecomment->comment_type || 'comment' == $thecomment->comment_type ) ) // don't do anything for trackbacks or pingbacks
    {
      // get plugin options
      $options = get_option ( 'comment-email-verify' );
      
      // create verification key and url
      $key = wp_generate_password ( 8, false );
      update_comment_meta($id,'comment_email_verify',$key);
      $verification_url = get_bloginfo('url')."/?comment_email_verify=$id%20$key";
      
      // get data we need for the shortcodes
      $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
      $blogdescription = wp_specialchars_decode(get_option('blogdescription'), ENT_QUOTES);
      $home = get_bloginfo('url');
      
      $author_name = $thecomment->comment_author;
      $author_email = $thecomment->comment_author_email;
      $author_url = $thecomment->comment_author_url;
      $author_ip = $thecomment->comment_author_IP;
      $author_hostname = gethostbyaddr ( $author_ip );
      $comment = $thecomment->comment_content;
      
      $thepost = get_post($thecomment->comment_post_ID);
      
      $post_title = $thepost->post_title;
      $post_permalink = get_permalink($thecomment->comment_post_ID);
      
      if ( !$options or !isset ( $options['template'] ) or !isset ( $options['template']['subject'] ) or '' == trim ( $options['template']['subject'] ) )
      {
        $subject = __( '[[blogname]] Please verify your e-mail address', 'comment-email-verify' );
      }
      else
      {
        $subject = $options['template']['subject'];
      }
      
      $subject = preg_replace (
        array (
          '/\[blogname]/i',
          '/\[post-title]/i',
        ),
        array (
          $blogname,
          $post_title,
        ),
        $subject
      );
      
      if ( !$options or !isset ( $options['template'] ) or !isset ( $options['template']['body'] ) or '' == trim ( $options['template']['body'] ) )
      {
        $body = __( "Dear [author-name],\n\nthanks for replying to [post-title] ([post-permalink]) on [blogname] - [blogdescription] ([home]).\n\nPlease verify your email address by clicking on this link:\n[verification-url]\n\nThis is your comment:\n\n[comment]\n\n--\nThis comment was posted from [author-ip] - [author-hostname]", 'comment-email-verify' );
      }
      else
      {
        $body = $options['template']['body'];
      }
      
      if ( !preg_match ( '/\[verification-url]/i', $body ) ) $body .= "\n\n$verification_url";
      $body = preg_replace (
        array (
          '/\[verification-url]/i',
          '/\[blogname]/i',
          '/\[blogdescription]/i',
          '/\[home]/i',
          '/\[post-title]/i',
          '/\[post-permalink]/i',
          '/\[author-name]/i',
          '/\[author-email]/i',
          '/\[author-url]/i',
          '/\[author-ip]/i',
          '/\[author-hostname]/i',
          '/\[comment]/i',
        ),
        array (
          $verification_url,
          $blogname,
          $blogdescription,
          $home,
          $post_title,
          $post_permalink,
          $author_name,
          $author_email,
          $author_url,
          $author_ip,
          $author_hostname,
          $comment,
        ),
        $body
      );
      
      add_filter ( 'wp_mail_from', array ( 'comment_email_verify', 'wp_mail_from' ) );
      add_filter ( 'wp_mail_from_name', array ( 'comment_email_verify', 'wp_mail_from_name' ) );
      add_action ( 'phpmailer_init', array ( 'comment_email_verify', 'phpmailer_init' ) );
      
      wp_mail($author_email,$subject,$body);
      
      remove_action ( 'phpmailer_init', array ( 'comment_email_verify', 'phpmailer_init' ) );
      remove_filter ( 'wp_mail_from', array ( 'comment_email_verify', 'wp_mail_from' ) );
      remove_filter ( 'wp_mail_from_name', array ( 'comment_email_verify', 'wp_mail_from_name' ) );
    }
  }

  /**
   * hooked to {@link http://codex.wordpress.org/Plugin_API/Filter_Reference WordPress filter}: {@link http://codex.wordpress.org/Plugin_API/Filter_Reference/gettext gettext}
   *
   * @since 0.2
   */
  function gettext ( $s, $original )
  {
    if ( 'Your comment is awaiting moderation.' != $original ) return $s;
    
    $options = get_option ( 'comment-email-verify' );
    if ( !$options or !isset ( $options['messages'] ) or !isset ( $options['messages']['awaiting-moderation'] ) or '' == trim ( $options['messages']['awaiting-moderation'] ) )
    {
      return sprintf ( __( "%s<br/>\nIf you verify your address your comment will be approved immediately. Instructions have been sent to you by e-mail.", 'comment-email-verify' ), $s );
    }
    
    return $options['messages']['awaiting-moderation'];
  }
  
  /** hooked to {@link http://codex.wordpress.org/Plugin_API/Action_Reference WordPress action}: {@link http://codex.wordpress.org/Plugin_API/Action_Reference/init init} */
  function init ()
  {
    add_action ( 'wp_insert_comment', array ( 'comment_email_verify', 'wp_insert_comment' ), 10, 2 );
    add_filter ( 'gettext', array ( 'comment_email_verify', 'gettext' ), 10, 2 );
    
    if ( 'GET' != $_SERVER['REQUEST_METHOD'] or empty ( $_GET['comment_email_verify'] ) ) return;
    
    $options = get_option ( 'comment-email-verify' );
    $hold_verified = is_array ( $options ) && isset ( $options['advanced']['hold-verified'] ) && ( '1' == $options['advanced']['hold-verified'] ); // must use && here because AND has a lower precedence than the assignment operator and the variable would (almost) always be TRUE
    $splash = is_array ( $options ) && isset ( $options['advanced']['splash'] ) && ( '1' == $options['advanced']['splash'] ); // must use && here because AND has a lower precedence than the assignment operator and the variable would (almost) always be TRUE
    
    $code = $_GET['comment_email_verify'];
    list ( $id, $key ) = ( false !== strpos ( $code, ' ' ) ) ? explode ( ' ', $code, 2 ) : explode ( '+', $code, 2 );
    
    $comment = get_comment($id);
    $status = $comment->comment_approved;
    
    if ( '1' == $status )
    {
      $error_title = __( 'Comment E-Mail Verification', 'comment-email-verify' );
      if ( !$options or !isset ( $options['splash'] ) or !isset ( $options['splash']['onpreviouslyapproved'] ) or '' == trim ( $options['splash']['onpreviouslyapproved'] ) )
      {
      /* translators: do NOT start or end this with <p>, </p> as that will be covered by wp_die() */
        $message = __( 'Good news: your comment has already been approved. &ndash; <a href="%1$s">Click here to see it</a>', 'comment-email-verify' );
      }
      else
      {
        $message = $options['splash']['onpreviouslyapproved'];
      }
      $error_message = sprintf ( $message, get_comment_link ( $id ) );
      if ( !$splash ) wp_redirect(get_comment_link($id));
      wp_die ( $error_message, $error_title, array ( 'response' => ($splash)?200:302, ) );
    }
    
    if ( get_comment_meta ( $id, 'comment_email_verify', true ) != $key )
    {
      $error_title = __( 'Comment E-Mail Verification', 'comment-email-verify' );
      /* translators: do NOT start or end this with <p>, </p> as that will be covered by wp_die() */
      $error_message = sprintf ( __( 'Your address could not be verified. &ndash; <a href="%1$s">Return to the %2$s homepage</a>', 'comment-email-verify' ), get_bloginfo ( 'url' ), get_option ( 'blogname' ) );
      wp_die ( $error_message, $error_title, array ( 'response' => 403, ) );
    }
    
    if ( '0' != $status )
    {
      $error_title = __( 'Comment E-Mail Verification', 'comment-email-verify' );
      /* translators: do NOT start or end this with <p>, </p> as that will be covered by wp_die() */
      $error_message = sprintf ( __( 'Your comment could not be approved. &ndash; <a href="%1$s">Return to the %2$s homepage</a>', 'comment-email-verify' ), get_bloginfo ( 'url' ), get_option ( 'blogname' ) );
      wp_die ( $error_message, $error_title, array ( 'response' => 403, ) );
    }
    
    global $wpdb;
    if ( !$hold_verified )
    $wpdb->update( $wpdb->comments, array('comment_approved' => 1), array('comment_ID' => $id) );
    
    delete_comment_meta($id,'comment_email_verify');
    if ( $hold_verified ) update_comment_meta($id,'comment_email_verify_status','1');
    
    clean_comment_cache($id);
    $comment = get_comment($id);
    wp_update_comment_count($comment->comment_post_ID);
    
    if ( get_option('comments_notify') ) wp_notify_postauthor($id, $comment->comment_type);
    
    $error_title = __( 'Comment E-Mail Verification', 'comment-email-verify' );
    if ( !$options or !isset ( $options['splash'] ) or !isset ( $options['splash']['onsuccess'] ) or '' == trim ( $options['splash']['onsuccess'] ) )
    {
    /* translators: do NOT start or end this with <p>, </p> as that will be covered by wp_die() */
      $message = __( 'Your comment has now been approved. &ndash; <a href="%1$s">Click here to see it</a>', 'comment-email-verify' );
    }
    else
    {
      $message = $options['splash']['onsuccess'];
    }
    $error_message = sprintf ( $message, get_comment_link ( $id ) );
    if ( !$splash ) wp_redirect(get_comment_link($id));
    wp_die ( $error_message, $error_title, array ( 'response' => ($splash)?200:302, ) );
  }

  /** */
  function settings_section__template ()
  {
    _e( '<p>Customize the e-mail message you are sending to comment authors. Leave a field blank to reset it to the default.</p>', 'comment-email-verify' );
  }
  
  /** */
  function settings_section__messages ()
  {
    _e( '<p>Customize the hint comment authors are given about their comment awaiting moderation. Leave the field blank to reset it to the default.</p>', 'comment-email-verify' );
  }
  
  /**
   * @since 0.4
   */
  function settings_section__splash ()
  {
    _e ( '<p>When a commenter clicks on a verification link they are taken straight back to their own comment. You may also show them a splash screen with details about the verification process. Leave a field blank to reset it to the default.</p>', 'comment-email-verify' );
  }

  /** */
  function settings_section__advanced ()
  {
    echo sprintf ( __ ( '<p>By default a comment is approved immediately when the comment author clicks on the verification link.</p><p>You may also keep comments in moderation even after the comment authors have verified their address. In this case you should consider turning on the splash screens in the previous section and editing the default messages. You should also edit the comment moderation notification two sections up on this page.</p><p>Whether or not comment authors have verified their address will be indicated on the Dashboard and on the <a href="edit-comments.php?comment_status=moderated">%1$s</a> page.</p>', 'comment-email-verify' ), __( 'Edit Comments' ) );
  }

  /**
   * @since 0.3
   */
  function settings_section__smtp ()
  {
  }

  /** */
  function settings_section__core_options ()
  {
    echo sprintf ( __( '<p>These are WordPress core settings. They may affect this plugin. They can also be set on <a href="options-discussion.php">%s</a>.</p>', 'comment-email-verify' ), __( 'Discussion Settings' ) );
  }

  /** */
  function settings_field__template__subject ()
  {
    $options = get_option ( 'comment-email-verify' );
    if ( !$options or !isset ( $options['template'] ) or !isset ( $options['template']['subject'] ) or '' == trim ( $options['template']['subject'] ) )
    {
      $subject = __( '[[blogname]] Please verify your e-mail address', 'comment-email-verify' );
    }
    else
    {
      $subject = $options['template']['subject'];
    }
    ?>
    <input type="text" class="regular-text" name="comment-email-verify[template][subject]" value="<?php echo htmlspecialchars ( $subject ); ?>" />
    <span class="description">
      <?php _e( 'You may use the following shortcodes in the subject line: <code>[blogname]</code>, <code>[post-title]</code>', 'comment-email-verify' ); ?>
    </span>
    <?php
  }

  /** */
  function settings_field__template__body ()
  {
    $options = get_option ( 'comment-email-verify' );
    if ( !$options or !isset ( $options['template'] ) or !isset ( $options['template']['body'] ) or '' == trim ( $options['template']['body'] ) )
    {
      $body = __( "Dear [author-name],\n\nthanks for replying to [post-title] ([post-permalink]) on [blogname] - [blogdescription] ([home]).\n\nPlease verify your email address by clicking on this link:\n[verification-url]\n\nThis is your comment:\n\n[comment]\n\n--\nThis comment was posted from [author-ip] - [author-hostname]", 'comment-email-verify' );
    }
    else
    {
      $body = $options['template']['body'];
    }
    ?>
    <textarea rows="10" cols="50" class="large-text" name="comment-email-verify[template][body]"><?php echo htmlspecialchars ( $body ); ?></textarea>
    <span class="description">
      <?php _e( 'You may use the following shortcodes in the message body: <code>[verification-url]</code>*, <code>[blogname]</code>, <code>[blogdescription]</code>, <code>[home]</code>, <code>[post-title]</code>, <code>[post-permalink]</code>, <code>[author-name]</code>, <code>[author-email]</code>, <code>[author-url]</code>, <code>[author-ip]</code>, <code>[author-hostname]</code>, <code>[comment]</code><br/><br/>* If you don\'t use <code>[verification-url]</code> that information will be appended to your message after a blank line.', 'comment-email-verify' ); ?>
    </span>
    <?php
  }
  
  /** */
  function settings_field__messages__awaiting_moderation ()
  {
    $message = __( 'Your comment is awaiting moderation.' );
    ?>
    <textarea rows="4" cols="50" class="large-text" name="comment-email-verify[messages][awaiting-moderation]"><?php echo htmlspecialchars ( $message ); ?></textarea>
    <span class="description">
      <?php echo sprintf ( __( 'Unlike in your posts, pages, or comments, line breaks will not be converted to HTML line breaks. You must insert <code>&lt;br/&gt</code> manually.<br/><br/>Your theme must either use the default comment template or have <code>%s</code> in the custom comment template.', 'comment-email-verify' ), "_e('Your comment is awaiting moderation.');" ); ?>
    </span>
    <?php
  }
  
  /**
   * @since 0.4
   */
  function settings_field__splash_onsuccess ()
  {
    $options = get_option ( 'comment-email-verify' );
    if ( !$options or !isset ( $options['splash'] ) or !isset ( $options['splash']['onsuccess'] ) or '' == trim ( $options['splash']['onsuccess'] ) )
    {
      /* translators: do NOT start or end this with <p>, </p> as that will be covered by wp_die() */
      $message = __( 'Your comment has now been approved. &ndash; <a href="%1$s">Click here to see it</a>', 'comment-email-verify' );
    }
    else
    {
      $message = $options['splash']['onsuccess'];
    }
    ?>
    <textarea rows="4" cols="50" class="large-text" name="comment-email-verify[splash][onsuccess]" id="comment-email-verify-splash-onsuccess"><?php echo htmlspecialchars ( $message ); ?></textarea>
    <span class="description">
      <?php _e( 'Unlike in your posts, pages, or comments, line breaks will not be converted to HTML line breaks. You must insert <code>&lt;br/&gt</code> manually. To link to the comment please use <code>&lt;a href="%1$s"&gt;</code>. Leave this field blank to reset it to the default message.', 'comment-email-verify' ); ?>
    </span>
    <?php
  }
  
  /**
   * @since 0.4
   */
  function settings_field__splash_onpreviouslyapproved ()
  {
    $options = get_option ( 'comment-email-verify' );
    if ( !$options or !isset ( $options['splash'] ) or !isset ( $options['splash']['onpreviouslyapproved'] ) or '' == trim ( $options['splash']['onpreviouslyapproved'] ) )
    {
      /* translators: do NOT start or end this with <p>, </p> as that will be covered by wp_die() */
      $message = __( 'Good news: your comment has already been approved. &ndash; <a href="%1$s">Click here to see it</a>', 'comment-email-verify' );
    }
    else
    {
      $message = $options['splash']['onpreviouslyapproved'];
    }
    ?>
    <textarea rows="4" cols="50" class="large-text" name="comment-email-verify[splash][onpreviouslyapproved]" id="comment-email-verify-splash-onpreviouslyapproved"><?php echo htmlspecialchars ( $message ); ?></textarea>
    <span class="description">
      <?php _e( 'Unlike in your posts, pages, or comments, line breaks will not be converted to HTML line breaks. You must insert <code>&lt;br/&gt</code> manually. To link to the comment please use <code>&lt;a href="%1$s"&gt;</code>. Leave this field blank to reset it to the default message.', 'comment-email-verify' ); ?>
    </span>
    <?php
  }
  
  /**
   * @since 0.4
   */
  function settings_field__advanced__hold_verified ()
  {
    $options = get_option ( 'comment-email-verify' );
    $hold_verified = is_array ( $options ) && isset ( $options['advanced']['hold-verified'] ) && ( '1' == $options['advanced']['hold-verified'] ); // must use && here because AND has a lower precedence than the assignment operator and the variable would (almost) always be TRUE
    ?>
    <input type="checkbox" name="comment-email-verify[advanced][hold-verified]" id="comment-email-verify-advanced-hold-verified" value="1"<?php if ( $hold_verified ) echo ' checked="checked"'; ?> />
    <label for="comment-email-verify-advanced-hold-verified"><?php _e( 'Turn off self-approval (i.e. hold comment for moderation even after successful E-mail verification)', 'comment-email-verify' ); ?></label>
    <?php
  }
  
  /**
   * @since 0.2
   */
  function settings_field__advanced__splash ()
  {
    $options = get_option ( 'comment-email-verify' );
    $splash = is_array ( $options ) && isset ( $options['advanced']['splash'] ) && ( '1' == $options['advanced']['splash'] ); // must use && here because AND has a lower precedence than the assignment operator and the variable would (almost) always be TRUE
    ?>
    <input onchange="return cev_splash_screens_enable(true);" type="radio" name="comment-email-verify[advanced][splash]" id="comment-email-verify-advanced-splash-1" value="1"<?php if ( $splash ) echo ' checked="checked"'; ?> />
    <label for="comment-email-verify-advanced-splash-1"><?php _e( 'Show a splash screen (as configured below)', 'comment-email-verify' ); ?></label>
    <br/>
    <input onchange="return cev_splash_screens_enable(false);" type="radio" name="comment-email-verify[advanced][splash]" id="comment-email-verify-advanced-splash-0" value="0"<?php if ( !$splash ) echo ' checked="checked"'; ?> />
    <label for="comment-email-verify-advanced-splash-0"><?php _e( 'Redirect to the comment without further ado', 'comment-email-verify' ); ?></label>
    <?php
  }
  
  /** */
  function settings_field__advanced__from ()
  {
    $options = get_option ( 'comment-email-verify' );
    $from = $options['advanced']['from'];
    
    // from WP 2.9.2 wp-includes/pluggable.php lines 363-367 function wp_mail :
    
    // Get the site domain and get rid of www.
    $sitename = strtolower( $_SERVER['SERVER_NAME'] );
    if ( substr( $sitename, 0, 4 ) == 'www.' ) {
      $sitename = substr( $sitename, 4 );
    }
    
    ?>
    <input type="text" class="regular-text" name="comment-email-verify[advanced][from]" value="<?php echo htmlspecialchars ( $from ); ?>" />
    <span class="description">
      <?php echo sprintf ( __( 'leave empty to use the default <code>wordpress@%s</code>', 'comment-email-verify' ), $sitename ); ?>
    </span>
    <?php
  }

  /** */
  function settings_field__advanced__from_name ()
  {
    $options = get_option ( 'comment-email-verify' );
    $from_name = $options['advanced']['from_name'];
    ?>
    <input type="text" class="regular-text" name="comment-email-verify[advanced][from_name]" value="<?php echo htmlspecialchars ( $from_name ); ?>" />
    <span class="description">
      <?php _e( 'leave empty to use the default <code>WordPress</code>', 'comment-email-verify' ); ?>
    </span>
    <?php
  }

  /**
   * @since 0.3
   */
  function settings_field__smtp__Host ()
  {
    $options = get_option ( 'comment-email-verify' );
    $smtpHost = $options['smtp']['Host'];
    ?>
    <input type="text" class="regular-text" name="comment-email-verify[smtp][Host]" value="<?php echo htmlspecialchars ( $smtpHost ); ?>" />
    <span class="description">
      <?php _e( 'leave empty to send mail using the <a href="http://php.net/manual/en/function.mail.php">mail</a> function', 'comment-email-verify' ); ?>
    </span>
    <?php
  }

  /**
   * @since 0.3
   */
  function settings_field__smtp__Port ()
  {
    $options = get_option ( 'comment-email-verify' );
    $smtpPort = $options['smtp']['Port'];
    ?>
    <input type="text" class="small-text" name="comment-email-verify[smtp][Port]" value="<?php echo htmlspecialchars ( $smtpPort ); ?>" />
    <span class="description">
      <?php _e( 'leave empty to use the default, which is <code>25</code> for unencrypted connections or TLS or <code>465</code> for SSL', 'comment-email-verify' ); ?>
    </span>
    <?php
  }

  /**
   * @since 0.3
   */
  function settings_field__smtp__SMTPSecure ()
  {
    $options = get_option ( 'comment-email-verify' );
    $smtpSMTPSecure = $options['smtp']['SMTPSecure'];
    $optionslist = array (
      '' => __( '- None -', 'comment-email-verify' ),
      'ssl' => __( 'SSL', 'comment-email-verify' ),
      'tls' => __( 'TLS', 'comment-email-verify' ),
    );
    ?>
    <select name="comment-email-verify[smtp][SMTPSecure]">
      <?php
      foreach ( $optionslist as $k => $v )
      {
        $selected = ( $k == $smtpSMTPSecure ) ? ' selected="selected"' : '';
        echo "<option value=\"$k\"$selected>$v</option>";
      }
      ?>
    </select>
    <?php
  }

  /**
   * @since 0.3
   */
  function settings_field__smtp__Timeout ()
  {
    $options = get_option ( 'comment-email-verify' );
    $smtpTimeout = $options['smtp']['Timeout'];
    ?>
    <input type="text" class="small-text" name="comment-email-verify[smtp][Timeout]" value="<?php echo htmlspecialchars ( $smtpTimeout ); ?>" />
    <span class="description">
      <?php _e( 'defaults to <code>10</code> &ndash; should only be changed if you know you have a slow SMTP server &ndash; does not work on Windows webservers', 'comment-email-verify' ); ?>
    </span>
    <?php
  }

  /**
   * @since 0.3
   */
  function settings_field__smtp__Username ()
  {
    $options = get_option ( 'comment-email-verify' );
    $smtpUsername = $options['smtp']['Username'];
    ?>
    <input type="text" class="regular-text" name="comment-email-verify[smtp][Username]" value="<?php echo htmlspecialchars ( $smtpUsername ); ?>" />
    <span class="description">
      <?php _e( 'leave empty to send mail without authentication, e.g. when your webserver is a trusted host in your mailserver configuration', 'comment-email-verify' ); ?>
    </span>
    <?php
  }

  /**
   * @since 0.3
   */
  function settings_field__smtp__Password ()
  {
    $options = get_option ( 'comment-email-verify' );
    $smtpPassword = $options['smtp']['Password'];
    ?>
    <input type="password" id="cev-password-field" class="regular-text" name="comment-email-verify[smtp][Password]" value="<?php echo htmlspecialchars ( $smtpPassword ); ?>" />
    <script type="text/javascript">
    //<![CDATA[
    document.write('<span id="cev-password-showing" style="display:none;">');
    document.write('<a href="#cev-password-field" onclick="return cev_show_password(false);"><?php _e( 'hide the password', 'comment-email-verify' ); ?></a>');
    document.write('</span>');
    document.write('<span id="cev-password-hidden">');
    document.write('<a href="#cev-password-field" onclick="return cev_show_password(true);"><?php _e( 'show the password', 'comment-email-verify' ); ?></a>');
    document.write('</span>');
    //]]>
    </script>
    <?php
  }

  /**
   * @since 0.3
   */
  function settings_field__smtp__Helo ()
  {
    $options = get_option ( 'comment-email-verify' );
    $smtpHelo = $options['smtp']['Helo'];
    $servername = $_SERVER['SERVER_NAME'];
    ?>
    <input type="text" class="regular-text" name="comment-email-verify[smtp][Helo]" value="<?php echo htmlspecialchars ( $smtpHelo ); ?>" />
    <span class="description">
      <?php echo sprintf ( __( 'defaults to <code>%s</code> &ndash; you may have to change this if you use SMTP without authentication on a server that performs HELO/EHLO checks', 'comment-email-verify' ), $servername ); ?>
    </span>
    <?php
  }

  /** */
  function settings_field__core_options__other ()
  {
    $require_name_email = get_option ( 'require_name_email' );
    $comment_registration = get_option ( 'comment_registration' );
    
    ?>
    <input type="checkbox" name="require_name_email" id="require_name_email" value="1"<?php if ( '1' == $require_name_email ) echo ' checked="checked"'; ?> />
    <label for="require_name_email"><?php _e( 'Comment author must fill out name and e-mail' ); // this is in the default domain ?></label>
    <br/>
    <input type="checkbox" name="comment_registration" id="comment_registration" value="1"<?php if ( '1' == $comment_registration ) echo ' checked="checked"'; ?> />
    <label for="comment_registration"><?php _e( 'Users must be registered and logged in to comment' ); // this is in the default domain ?></label>
    <?php
  }

  /** */
  function settings_field__core_options__email ()
  {
    $comments_notify = get_option ( 'comments_notify' );
    $moderation_notify = get_option ( 'moderation_notify' );
    
    ?>
    <input type="checkbox" name="comments_notify" id="comments_notify" value="1"<?php if ( '1' == $comments_notify ) echo ' checked="checked"'; ?> />
    <label for="comments_notify"><?php _e( 'Anyone posts a comment' ); // this is in the default domain ?></label>
    <br/>
    <input type="checkbox" name="moderation_notify" id="moderation_notify" value="1"<?php if ( '1' == $moderation_notify ) echo ' checked="checked"'; ?> />
    <label for="moderation_notify"><?php _e( 'A comment is held for moderation' ); // this is in the default domain ?></label>
    <?php
  }

  /** */
  function settings_field__core_options__before ()
  {
    $comment_moderation = get_option ( 'comment_moderation' );
    $comment_whitelist = get_option ( 'comment_whitelist' );
    
    ?>
    <input type="checkbox" name="comment_moderation" id="comment_moderation" value="1"<?php if ( '1' == $comment_moderation ) echo ' checked="checked"'; ?> />
    <label for="comment_moderation"><?php _e( 'An administrator must always approve the comment' ); // this is in the default domain ?></label>
    <br/>
    <input type="checkbox" name="comment_whitelist" id="comment_whitelist" value="1"<?php if ( '1' == $comment_whitelist ) echo ' checked="checked"'; ?> />
    <label for="comment_whitelist"><?php _e( 'Comment author must have a previously approved comment' ); // this is in the default domain ?></label>
    <?php
  }

  /** sanitize */
  function sanitize__comment_email_verify ( $settings )
  {
    $smtp_port = trim ( $settings['smtp']['Port'] );
    if ( !empty($smtp_port) )
    {
      // make this a number
      $smtp_port = intval ( $smtp_port );
      // ... between 1 and 65535, or reset to default
      if ( ( 1 > $smtp_port ) || ( 65535 < $smtp_port ) ) $smtp_port = '';
    }
    
    $smtp_secure = $settings['smtp']['SMTPSecure'];
    if ( !empty($smtp_secure) )
    {
      // this should be empty or 'ssl' or 'tls'
      if ( ( 'ssl' != $smtp_secure ) AND ( 'tls' != $smtp_secure ) ) $smtp_secure = '';
    }
    
    $smtp_timeout = trim ( $settings['smtp']['Timeout'] );
    if ( !empty($smtp_timeout) )
    {
      // numbers only
      $smtp_timeout = intval ( $smtp_timeout );
      // range checks in JavaScript on the client side
    }
    
    return array (
      'template' => array (
        'subject' => trim ( $settings['template']['subject'] ),
        'body' => $settings['template']['body'],
      ),
      'messages' => array (
        'awaiting-moderation' => $settings['messages']['awaiting-moderation'],
      ),
      'advanced' => array (
        'hold-verified' => ( isset ( $settings['advanced']['hold-verified'] ) AND '1' == $settings['advanced']['hold-verified'] ) ? '1' : '0',
        'splash' => ( '1' == $settings['advanced']['splash'] ) ? '1' : '0',
        'from' => trim ( $settings['advanced']['from'] ),
        'from_name' => $settings['advanced']['from_name'],
      ),
      'smtp' => array (
        'Host' => trim ( $settings['smtp']['Host'] ), // todo: remove spaces
        'Port' => $smtp_port,
        'SMTPSecure' => $smtp_secure,
        'Username' => trim ( $settings['smtp']['Username'] ),
        'Password' => $settings['smtp']['Password'],
        'Timeout' => $smtp_timeout,
        'Helo' => trim ( $settings['smtp']['Helo'] ), // todo: remove spaces
      ),
      'splash' => array (
        'onsuccess' => $settings['splash']['onsuccess'],
        'onpreviouslyapproved' => $settings['splash']['onpreviouslyapproved'],
      ),
    );
  }

  /** */
  function sanitize_bool ( $settings )
  {
    return ( '1' == $settings ) ? '1' : '';
  }
  
  /**
   * hooked to {@link http://codex.wordpress.org/Plugin_API/Filter_Reference WordPress filter}: {@link http://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links plugin_action_links}
   * @since 0.1.2
   */
  function plugin_action_links ( $links )
  {
    $links[] = sprintf ( '<a href="options-general.php?page=comment-email-verify">%s</a>', __( 'Settings' ) ); // 'Settings' is in the default domain!
    return $links;
  }
  
  /**
   * hooked to {@link http://codex.wordpress.org/Plugin_API/Filter_Reference WordPress filter}: {@link http://adambrown.info/p/wp_hooks/hook/comment_row_actions comment_row_actions}
   * @since 0.4
   */
  function comment_row_actions ( $actions, $comment )
  {
    if ( isset ( $actions['approve'] ) AND get_comment_meta ( $comment->comment_ID, 'comment_email_verify', true ) )
    {
      $actions['approve'] .= ' ' . __( '(pending E-mail verification)', 'comment-email-verify' );
    }
    if ( isset ( $actions['approve'] ) AND get_comment_meta ( $comment->comment_ID, 'comment_email_verify_status', true ) )
    {
      $actions['approve'] .= ' ' . __( '(E-mail verified)', 'comment-email-verify' );
    }
    return $actions;
  }
  
  /** hooked to {@link http://codex.wordpress.org/Plugin_API/Action_Reference WordPress action}: {@link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_init admin_init} */
  function admin_init ()
  {
    add_settings_section ( 'comment-email-verify_template', __( 'E-mail message template', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_section__template' ), 'comment-email-verify' );
    add_settings_field ( 'comment-email-verify_template_subject', __( 'Subject', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__template__subject' ), 'comment-email-verify', 'comment-email-verify_template' );
    add_settings_field ( 'comment-email-verify_template_body', __( 'Message body', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__template__body' ), 'comment-email-verify', 'comment-email-verify_template' );
    
    // moved here from "Advanced" section in 0.4
    add_settings_field ( 'comment-email-verify_advanced_from', __( 'From e-mail address', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__advanced__from' ), 'comment-email-verify', 'comment-email-verify_template' );
    add_settings_field ( 'comment-email-verify_advanced_from-name', __( 'From name', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__advanced__from_name' ), 'comment-email-verify', 'comment-email-verify_template' );
    
    remove_filter ( 'gettext', array ( 'comment_email_verify', 'gettext' ), 10, 2 ); // leave 'Your comment is awaiting moderation.' unaltered (but we want to have it translated) (two lines down)
    
    add_settings_section ( 'comment-email-verify_messages', __( 'Comment moderation notification', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_section__messages' ), 'comment-email-verify' );
    add_settings_field ( 'comment-email-verify_messages_awaiting-moderation', sprintf ( __( 'Change &ldquo;%s&rdquo; to:', 'comment-email-verify' ), __( 'Your comment is awaiting moderation.' ) ), array ( 'comment_email_verify', 'settings_field__messages__awaiting_moderation' ), 'comment-email-verify', 'comment-email-verify_messages' );
    
    add_filter ( 'gettext', array ( 'comment_email_verify', 'gettext' ), 10, 2 ); // turn filter back on that was just temporarily turned off (three lines up)
    
    add_settings_section ( 'comment-email-verify_splash', __( 'Splash screens', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_section__splash' ), 'comment-email-verify' );
    add_settings_field ( 'comment-email-verify_splash_onoff', __( 'When someone clicks on a verification link', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__advanced__splash' ), 'comment-email-verify', 'comment-email-verify_splash' );
    add_settings_field ( 'comment-email-verify_splash_verified', __( 'On successful verification', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__splash_onsuccess' ), 'comment-email-verify', 'comment-email-verify_splash' );
    add_settings_field ( 'comment-email-verify_splash_previouslyapproved', __( 'If the comment was previously approved', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__splash_onpreviouslyapproved' ), 'comment-email-verify', 'comment-email-verify_splash' );
    
    add_settings_section ( 'comment-email-verify_advanced', __( 'Advanced settings', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_section__advanced' ), 'comment-email-verify' );
    add_settings_field ( 'comment-email-verify_advanced_hold-verified', __( 'Turn off self-approval', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__advanced__hold_verified' ), 'comment-email-verify', 'comment-email-verify_advanced' );

    add_settings_section ( 'comment-email-verify_smtp', __( 'SMTP settings', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_section__smtp' ), 'comment-email-verify' );
    add_settings_field ( 'comment-email-verify_smtp_Host', __( 'Server', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__smtp__Host' ), 'comment-email-verify', 'comment-email-verify_smtp' );
    add_settings_field ( 'comment-email-verify_smtp_Port', __( 'Port', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__smtp__Port' ), 'comment-email-verify', 'comment-email-verify_smtp' );
    add_settings_field ( 'comment-email-verify_smtp_SMTPSecure', __( 'Encryption', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__smtp__SMTPSecure' ), 'comment-email-verify', 'comment-email-verify_smtp' );
    add_settings_field ( 'comment-email-verify_smtp_Username', __( 'Username', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__smtp__Username' ), 'comment-email-verify', 'comment-email-verify_smtp' );
    add_settings_field ( 'comment-email-verify_smtp_Password', __( 'Password', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__smtp__Password' ), 'comment-email-verify', 'comment-email-verify_smtp' );
    add_settings_field ( 'comment-email-verify_smtp_Timeout', __( 'Server timeout [seconds]', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__smtp__Timeout' ), 'comment-email-verify', 'comment-email-verify_smtp' );
    add_settings_field ( 'comment-email-verify_smtp_Helo', __( 'HELO', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_field__smtp__Helo' ), 'comment-email-verify', 'comment-email-verify_smtp' );
    
    add_settings_section ( 'comment-email-verify_core-options', __( 'WordPress core settings', 'comment-email-verify' ), array ( 'comment_email_verify', 'settings_section__core_options' ), 'comment-email-verify' );
    add_settings_field ( 'comment-email-verify_core-options_other', __( 'Other comment settings' ), array ( 'comment_email_verify', 'settings_field__core_options__other' ), 'comment-email-verify', 'comment-email-verify_core-options' );
    add_settings_field ( 'comment-email-verify_core-options_email', __( 'E-mail me whenever' ), array ( 'comment_email_verify', 'settings_field__core_options__email' ), 'comment-email-verify', 'comment-email-verify_core-options' );
    add_settings_field ( 'comment-email-verify_core-options_before', __( 'Before a comment appears' ), array ( 'comment_email_verify', 'settings_field__core_options__before' ), 'comment-email-verify', 'comment-email-verify_core-options' );
    
    register_setting ( 'comment-email-verify', 'comment-email-verify', array ( 'comment_email_verify', 'sanitize__comment_email_verify' ) );
    register_setting ( 'comment-email-verify', 'require_name_email', array ( 'comment_email_verify', 'sanitize_bool' ) );
    register_setting ( 'comment-email-verify', 'comment_registration', array ( 'comment_email_verify', 'sanitize_bool' ) );
    register_setting ( 'comment-email-verify', 'comments_notify', array ( 'comment_email_verify', 'sanitize_bool' ) );
    register_setting ( 'comment-email-verify', 'moderation_notify', array ( 'comment_email_verify', 'sanitize_bool' ) );
    register_setting ( 'comment-email-verify', 'comment_moderation', array ( 'comment_email_verify', 'sanitize_bool' ) );
    register_setting ( 'comment-email-verify', 'comment_whitelist', array ( 'comment_email_verify', 'sanitize_bool' ) );

    add_filter ( 'plugin_action_links_' . plugin_basename ( __FILE__ ), array ( 'comment_email_verify', 'plugin_action_links' ) );
    
    add_filter ( 'comment_row_actions', array ( 'comment_email_verify', 'comment_row_actions' ), 10, 2 );
  }

  /** */
  function options_page ()
  {
    ?>
    <div class="wrap">
      <div id="icon-options-general" class="icon32"><br></div>
      <h2><?php _e( 'Comment E-Mail Verification Settings', 'comment-email-verify' ); ?></h2>
      <script type="text/javascript">
        //<![CDATA[
        function cev_trim(s)
        {
          return s.replace(/^\s+|\s+$/g,'');
        }
        function cev_is_sane(form)
        {
          // no spaces in SMTP server
          eHost=form.elements['comment-email-verify[smtp][Host]'];
          eHost.value=cev_trim(eHost.value);
          if (eHost.value.match(/\s/))
          {
            window.alert('<?php /* translators: please double escape newlines and triple escape apostrophes as this will be a literal string in JavaScript */
            _e( 'SMTP Server name or address must not contain whitespace!', 'comment-email-verify' ); ?>');
            eHost.select();
            return false;
          }
          
          // SMTP port between 1 and 65535
          ePort=form.elements['comment-email-verify[smtp][Port]'];
          ePort.value=cev_trim(ePort.value);
          if ((''!=ePort.value)&&((ePort.value*10!=ePort.value+'0')||(1>ePort.value)||(65535<ePort.value)))
          {
            window.alert('<?php /* translators: please double escape newlines and triple escape apostrophes as this will be a literal string in JavaScript */
            _e( 'SMTP Port must be numerical and must be between 1 and 65535!', 'comment-email-verify' ); ?>');
            ePort.select();
            return false;
          }
          
          // SMTP timeout
          eTimeout=form.elements['comment-email-verify[smtp][Timeout]'];
          eTimeout.value=cev_trim(eTimeout.value);
          if ((''!=eTimeout.value)&&(eTimeout.value*10!=eTimeout.value+'0'))
          {
            window.alert('<?php /* translators: please double escape newlines and triple escape apostrophes as this will be a literal string in JavaScript */
            _e( 'SMTP Server timeout must be numerical!', 'comment-email-verify' ); ?>');
            eTimeout.select();
            return false;
          }
          if (10>eTimeout.value)
          {
            ok=window.confirm('<?php /* translators: please double escape newlines and triple escape apostrophes as this will be a literal string in JavaScript */
            _e( 'You should not use SMTP Server timeout values below the default 10 seconds!\\n\\nClick \\\'OK\\\' to continue anyway or \\\'Cancel\\\' to change!', 'comment-email-verify' ); ?>');
            if(!ok)
            {
              //eTimeout.value=10;
              eTimeout.select();
              return false;
            }
          }
          if (<?php echo $max_execution_time=ini_get('max_execution_time'); ?><=eTimeout.value)
          {
            ok=window.confirm('<?php /* translators: please double escape newlines and triple escape apostrophes as this will be a literal string in JavaScript */
            echo sprintf ( __( 'You should not use SMTP Server timeout values of %1$d seconds or above!\\nPHP scripts do not run this long on your server, so your SMTP server might prevent users from posting comments.\\n\\nClick \\\'OK\\\' to continue anyway or \\\'Cancel\\\' to change!', 'comment-email-verify' ), $max_execution_time ); ?>');
            if(!ok)
            {
              //eTimeout.value=<?php echo $max_execution_time; ?>;
              eTimeout.select();
              return false;
            }
          }
          
          // no spaces in SMTP HELO
          eHELO=form.elements['comment-email-verify[smtp][Helo]'];
          eHELO.value=cev_trim(eHELO.value);
          if (eHELO.value.match(/\s/))
          {
            window.alert('<?php /* translators: please double escape newlines and triple escape apostrophes as this will be a literal string in JavaScript */
            _e( 'SMTP HELO must not contain whitespace!', 'comment-email-verify' ); ?>');
            eHELO.select();
            return false;
          }
          
          /* DEBUG
          window.alert('OK');
          return false;
          //*/
          
          cev_splash_screens_enable(true);
          return true;
        }
        function cev_splash_screens_enable(enable)
        {
          onsuccess=document.getElementById('comment-email-verify-splash-onsuccess');
          onpreappr=document.getElementById('comment-email-verify-splash-onpreviouslyapproved');
          onsuccess.disabled=!enable;
          onpreappr.disabled=!enable;
          fclass=(enable)?'large-text':'large-text disabled';
          onsuccess.className=fclass;
          onpreappr.className=fclass;
          return true;
        }
        function cev_show_password(show)
        {
          document.getElementById('cev-password-showing').style.display=((show)?'inline':'none');
          document.getElementById('cev-password-hidden').style.display= ((show)?'none'  :'inline');
          document.getElementById('cev-password-field').type=           ((show)?'text'  :'password');
          document.getElementById('cev-password-field').select();
          return false;
        }
        //]]>
      </script>
      <form method="post" action="options.php" onsubmit="return cev_is_sane(this);">
        <?php do_settings_sections ( 'comment-email-verify' ); ?>
        <?php settings_fields ( 'comment-email-verify' ); ?>
        <p class="submit"><input class="button-primary" type="submit" value="<?php esc_attr_e( 'Save Changes' ); // this is in the default domain! ?>" /></p>
      </form>
      <script type="text/javascript">
        //<![CDATA[
        cev_splash_screens_enable(document.getElementById('comment-email-verify-advanced-splash-1').checked);
        //]]>
      </script>
    </div>
    <?php
  }

  /** hooked to {@link http://codex.wordpress.org/Plugin_API/Action_Reference WordPress action}: {@link http://codex.wordpress.org/Plugin_API/Action_Reference/admin_menu admin_menu} */
  function admin_menu ()
  {
    $page = add_options_page ( __( 'Comment E-Mail Verification Settings', 'comment-email-verify' ), __( 'Comment E-Mail Verification', 'comment-email-verify' ), 'manage_options', 'comment-email-verify', array ( 'comment_email_verify', 'options_page' ) );
    /* translators: %1$s is the plugin name, %2$s is the section title "Advanced settings" */
    $help = sprintf ( __( '<p><em>%1$s</em> sends an e-mail message to the comment author when a comment is held for moderation. The e-mail message contains a link to verify the comment author\'s e-mail address. When the comment author clicks on that link the comment gets approved immediately. This makes discussions more lively as users don\'t have to wait for the blog admin to approve the comment.</p><p>You may also keep comments in moderation even after the comment authors have verified their address. To do so please scroll down to the "%2$s" section.</p><p>You can customize the e-mail message using a number of <a href="http://ten-fingers-and-a-brain.com/wordpress-plugins/comment-email-verify/shortcodes/">shortcodes</a>.</p><p>Please note: the e-mail message is sent to the <em>comment</em> author, not the post author.</p><p>A number of factors can influence e-mail delivery to the comment authors. The messages might not be received at all or caught by spam filters. <a href="http://ten-fingers-and-a-brain.com/wordpress-plugins/comment-email-verify/troubleshooting/">Troubleshoot e-mail delivery</a></p>', 'comment-email-verify' ), __( 'Comment E-Mail Verification', 'comment-email-verify' ), __( 'Advanced settings', 'comment-email-verify' ) );
    add_contextual_help ( $page, $help );
  }

} // class comment_email_verify

add_action ( 'init', array ( 'comment_email_verify', 'init' ) );
add_action ( 'admin_init', array ( 'comment_email_verify', 'admin_init' ) );
add_action ( 'admin_menu', array ( 'comment_email_verify', 'admin_menu' ) );
