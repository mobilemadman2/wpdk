<?php

/**
 * Useful class to manage mail as post.
 *
 * ## Overview
 * Extends WPDKPost with useful method and property to mail.
 *
 * @class           WPDKMail
 * @author          =undo= <info@wpxtre.me>
 * @copyright       Copyright (C) 2012-2014 wpXtreme Inc. All Rights Reserved.
 * @date            2014-05-15
 * @version         1.0.1
 *
 */
class WPDKMail extends WPDKPost {

  /**
   * Header for carbon copy
   *
   * @brief Carbon copy
   * @since 1.4.9
   *
   * @var string $cc
   */
  public $cc = '';

  /**
   * Header for carbon copy
   *
   * @brief Carbon copy
   * @since 1.4.9
   *
   * @var string $bcc
   */
  public $bcc = '';

  /**
   * From for header
   *
   * @brief From
   *
   * @var string $from
   */
  private $from = '';

  /**
   * Create an instance of WPDKMail class
   *
   * @brief Construct
   *
   * @param string|int|object|null $mail      Optional. Post ID, post object, post slug or null
   * @param string                 $post_type Optional. If $record is a string (slug) then this is the post type where search.
   *                                          Default is 'page'
   *
   * @return WPDKMail
   */
  public function __construct( $mail = null, $post_type = 'page' )
  {
    parent::__construct( $mail, $post_type );
  }

  /**
   * Return the array with the available post type used to send a mail.
   *
   * @brief Post types for mail
   *
   * @return array
   */
  public static function postTypes()
  {
    // Register the post type for mail
    $post_types = array(
      'post' => 'Post',
      'page' => 'Page'
    );

    /**
     * Filter the available post types used to send a mail.
     *
     * @param array $post_types An array with post type id and label.
     */

    return apply_filters( 'wpdk_mail_post_types', $post_types );
  }

  /**
   * Perform a send mail. Return FALSE if an error occour.
   *
   * @brief Send mail
   *
   * @param string|int      $to                     String target 'name <email>' or user id
   * @param bool|string     $subject                Optional. String subject for this mail, if FALSE will bw used the title of
   *                                                post-mail
   * @param bool|int|string $from                   Optional. String from 'name <email>' or user id, set to empty to use default
   *                                                blog name and admin email
   * @param array           $placeholders           Optional. A Key value pairs with placeholders substitution.
   *
   * @return bool|WPDKError
   */
  public function send( $to, $subject = false, $from = '', $placeholders = array() ) {

    // Use shared private property
    $this->from = $from;

    if ( is_numeric( $this->from ) ) {
      $user = new WP_User( $from );
      $this->from = sprintf( '%s <%s>', $user->data->display_name, $user->get( 'user_email' ) );
    }

    // $from is as 'NOME <email>', eg: 'wpXtreme <info@wpxtre.me>'
    if ( empty( $this->from ) ) {

      // Get the default WordPress email
      $this->from = sprintf( '%s <%s>', get_option( 'blogname' ), get_option( 'admin_email' ) );
    }

    // User id for $to?
    $user = false;
    if ( is_numeric( $to ) ) {
      $user  = new WP_User( $to );
      $email = sanitize_email( $user->get( 'user_email' ) );

      // If user has not email exit
      if ( empty( $email ) ) {
        return;
      }
      $to = sprintf( '  %s <%s>', $user->data->display_name, $user->get( 'user_email' ) );
    }

    if ( $subject === false ) {
      $subject = apply_filters( 'the_title', $this->post_title );
    }

    //$body = apply_filters( 'the_content', $post->post_content );
    $body = $this->post_content;
    $body = $this->replacePlaceholder( $body, $user, $placeholders );

    try {
      $result = wp_mail( $to, $subject, $body, $this->headers() );
    }
    catch ( phpmailerException $e ) {
      return new WPDKError( 'wpdk-mail-send', $e->getMessage(), $e );
    }

    return $result;
  }

  /**
   * Return the computated header for mail
   *
   * @brief Headers
   *
   * @return string
   */
  private function headers()
  {
    // Build the header
    $headers = array(
      'From: ' . $this->from,
      'Content-Type: text/html'
    );

    // Added cc and bcc
    if ( !empty( $this->cc ) ) {
      $this->cc = explode( ',', $this->cc );
      foreach ( $this->cc as $email ) {
        $headers[] = sprintf( 'Cc: %s', $email );
      }
    }

    if ( !empty( $this->bcc ) ) {
      $this->bcc = explode( ',', $this->bcc );
      foreach ( $this->bcc as $email ) {
        $headers[] = sprintf( 'Bcc: %s', $email );
      }
    }

    /**
     * Filter the headers array.
     *
     * @param array $headers The headers array.
     */
    $headers = apply_filters( 'wpdk_mail_headers', $headers );

    return implode( "\r\n", $headers );
  }

  /**
   * Replace every placeholder in content body mail with true data value. However some placeholder must be as extra
   * parameter. For example the password placeholder (the password is not decryptable) must be as extra param.
   *
   * @brief Replace placeholder with value
   *
   * @param string          $content Content to filter
   * @param bool|int|object $user_id Optional. User ID or FALSE to get the current user id. You can set as object WP_User
   * @param array           $extra   Optional. Extra placeholder to replace, for custom use.
   *
   * @return string
   */
  private function replacePlaceholder( $content, $user_id = false, $extra = array() )
  {
    // If no user set get the current user logged in
    if ( false === $user_id ) {
      $user_id = get_current_user_id();
      $user    = get_user_by( 'id', $user_id );
    }
    elseif ( is_object( $user_id ) && is_a( $user_id, 'WP_User' ) ) {
      $user = $user_id;
    }
    elseif ( is_numeric( $user_id ) ) {
      $user = get_user_by( 'id', $user_id );
    }

    // Stability
    if ( empty( $user ) ) {
      return $content;
    }

    // Defaults placeholder
    $str_replaces = array(
      WPDKMailPlaceholders::USER_FIRST_NAME   => $user->get( 'first_name' ),
      WPDKMailPlaceholders::USER_LAST_NAME    => $user->get( 'last_name' ),
      WPDKMailPlaceholders::USER_DISPLAY_NAME => $user->data->display_name,
      WPDKMailPlaceholders::USER_EMAIL        => $user->data->user_email,
    );

    // Inline extra placeholder
    if ( !empty( $extra ) ) {
      $str_replaces = array_merge( $str_replaces, $extra );
    }

    /**
     * Filter the defaults placeholder.
     *
     * @param array $str_replaces An array with {place holder key} => {value}.
     * @param int   $user_id      The User ID.
     */
    $str_replaces = apply_filters( 'wpdk_mail_replace_placeholders', $str_replaces, $user_id );

    // Replace in content
    $content = strtr( $content, $str_replaces );

    return $content;
  }

}

/**
 * This class contains the definition of mail placeholder
 *
 * @class              WPDKMailPlaceholders
 * @author             =undo= <info@wpxtre.me>
 * @copyright          Copyright (C) 2012-2013 wpXtreme Inc. All Rights Reserved.
 * @date               2013-02-18
 * @version            0.8.1
 */
class WPDKMailPlaceholders {

  const USER_DISPLAY_NAME = '${USER_DISPLAY_NAME}';
  const USER_EMAIL        = '${USER_EMAIL}';
  const USER_FIRST_NAME   = '${USER_FIRST_NAME}';
  const USER_LAST_NAME    = '${USER_LAST_NAME}';

  /**
   * Return a key values pairs array with the list of placehodlers. This array has a key with the placeholder string
   * and an array( description, plugin name )
   *
   * @brief Placeholders list
   *
   * @return array
   */
  public static function placeholders()
  {

    $placeholders = array(
      self::USER_FIRST_NAME   => array( __( 'User First name', WPDK_TEXTDOMAIN ), 'Core' ),
      self::USER_LAST_NAME    => array( __( 'User Last name', WPDK_TEXTDOMAIN ), 'Core' ),
      self::USER_DISPLAY_NAME => array( __( 'User Display name', WPDK_TEXTDOMAIN ), 'Core' ),
      self::USER_EMAIL        => array( __( 'User email', WPDK_TEXTDOMAIN ), 'Core' ),
    );

    /**
     * Filter the mail placeholder.
     *
     * @param array $placeholders An array with {placeholder string} => array( {label description} => {owner} ).
     */

    return apply_filters( 'wpdk_mail_placeholders', $placeholders );
  }

}

