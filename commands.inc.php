<?php

/**
 * All the commands and their options. Each command is listed in the screen section they're active in.
 *
 * options:
 *
 * function     the function to call, default = name of command
 * parameters   any parameters to the function we call, can be string or array
 * label        a label for this command, default = name of command
 */

$rcmail_config['keyboard_shortcuts_commands'] = array(
    'all'           => array(
            'addressbook'   => array(),
            'help'          => array(),
            'logout'        => array(),
            'mail'          => array(),
            'settings'      => array(),
            'search'        => array()
    ),
    'mail'          => array(
            'collapse-all'      => array(),
            'compose'           => array(),
            'delete'            => array(),
            'download'          => array(),
            'expand-all'        => array(),
            'expand-unread'     => array(),
            'forward'           => array(),
            'mark-flagged'      => array('function' => 'mark', 'parameters' => 'flagged'),
            'mark-read'         => array('function' => 'mark', 'parameters' => 'read'),
            'mark-unflagged'    => array('function' => 'mark', 'parameters' => 'unflagged'),
            'mark-unread'       => array('function' => 'mark', 'parameters' => 'unread'),
            'nextpage'          => array(),
            'previouspage'      => array(),
            'print'             => array(),
            'reply'             => array(),
            'replyall'          => array(),
            'select-all'        => array(),
            'select-flagged'    => array('function' => 'select-all', 'parameters' => 'flagged'),
            'select-invert'     => array('function' => 'select-all', 'parameters' => 'invert'),
            'select-none'       => array('function' => 'select-all', 'parameters' => 'none'),
            'select-page'       => array('function' => 'select-all', 'parameters' => 'page'),
            'select-unread'     => array('function' => 'select-all', 'parameters' => 'unread'),
            'viewsource'        => array()
    ),
    'addressbook'   => array(),
    'compose'       => array(),
);



