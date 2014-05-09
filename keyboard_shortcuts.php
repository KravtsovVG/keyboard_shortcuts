<?php
/**
 * keyboard_shortcuts
 *
 * Enables some common tasks to be executed with keyboard shortcuts
 *
 * @version 3.0
 * @author Patrik Kullman / Roland 'rosali' Liebl
 * @author Cor Bosman <cor@roundcu.be>
 * @licence GNU GPL
 *
 **/


class keyboard_shortcuts extends rcube_plugin
{
    public $task = 'mail|compose|addressbook|settings';
    //public $noajax = true;
    private $rcmail;
    private $prefs;
    private $commands;

    function init()
    {
        // load rcmail
        $this->rcmail = rcmail::get_instance();

        // require jqueryui
        $this->require_plugin('jqueryui');

        // load config
        $this->load_config();

        if($this->rcmail->output->ajax_call) {
            $this->register_action('plugin.ks_add_row', array($this, 'add_row'));
            return;
        }

        // set up hooks
        $this->add_hook('template_container', array($this, 'html_output'));
        if($this->rcmail->config->get('keyboard_shortcuts_userconfigurable', true) and $this->rcmail->task == 'settings') {
            $this->add_hook('preferences_list', array($this, 'preferences_list'));
            $this->add_hook('preferences_save', array($this, 'preferences_save'));
            $this->add_hook('preferences_sections_list',array($this, 'preferences_section'));
            $this->add_hook('preferences_section_header',array($this, 'preferences_section_header'));

            $disallowed_keys = json_serialize($this->rcmail->config->get('keyboard_shortcuts_disallowed_keys', array()));
            $keyboard_shortcuts_commands = json_serialize($this->rcmail->config->get('keyboard_shortcuts_commands', array()));

            $this->rcmail->output->add_script("var keyboard_shortcuts_disallowed_keys = $disallowed_keys; var keyboard_shortcuts_commands = $keyboard_shortcuts_commands;", 'foot');

            $this->rcmail->output->add_label('addressbook', 'logout', 'mail', 'settings', 'collapse-all', 'compose', 'expand', 'delete', 'expand-all', 'expand-unread', 'nextpage', 'previouspage', 'nextmessage', 'previousmessage', 'forward', 'print', 'reply', 'replyall','search');
        }

        // include js/css
        $this->include_stylesheet('keyboard_shortcuts.css');
        $this->include_script('keyboard_shortcuts.js');

        // add the keys to js
        if(in_array($this->rcmail->task, array('mail','compose','addressbook','settings'))) {
            $commands = json_serialize($this->rcmail->config->get('keyboard_shortcuts', $this->rcmail->config->get('keyboard_shortcuts_default')));
            $this->rcmail->output->add_script("var keyboard_shortcuts = $commands;", 'foot');
        }

        // set up localization
        $this->add_texts('localization', true);

        // load config file
        //$this->load_config();
    }

    function preferences_section_header($args)
    {
        if($args['section'] == 'keyboard_shortcuts') {
            $args['header'] = html::tag('p', null, Q($this->gettext('header')));
        }
        return $args;
    }

    // add a section to the preferences tab
    function preferences_section($args) {
        $args['list']['keyboard_shortcuts'] = array(
            'id'      => 'keyboard_shortcuts',
            'section' => Q($this->gettext('title'))
        );
        return($args);
    }

    // preferences screen
    function preferences_list($args)
    {
        if($args['section'] == 'keyboard_shortcuts') {
            // rcmail
            if(!$this->rcmail) $this->rcmail = rcmail::get_instance();

            // load shortcuts
            $this->prefs = $this->rcmail->config->get('keyboard_shortcuts', $this->rcmail->config->get('keyboard_shortcuts_default'));

            // all available commands
            $this->commands = $this->rcmail->config->get('keyboard_shortcuts_commands', array());

            $id = 0;
            // loop through all sections, and print the configured keys
            foreach($this->prefs as $section => $keys) {
                $args['blocks'][$section] = array(
                    'name' => Q($this->gettext($section)),
                    'options' => array()
                );
                uksort($keys, 'self::chrsort');
                xs_log($keys);
                foreach($keys as $key => $command) {

                    $title   = $this->create_title($id, $section, $command);
                    $content = $this->create_row($id++, $section, $key);

                    $args['blocks'][$section]['options'][$command.$key] = array(
                        'title' => $title,
                        'content' => $content
                    );
                }
                // plus button
                $args['blocks'][$section]['options']['add'] = array(
                    'title' => '',
                    'content' => html::tag('span', array('class' => 'ks_content'), html::tag('a', array('class' => 'button ks_add', 'data-section' => $section), ''))
                );
            }

        }

        return($args);
    }

    function add_row()
    {
        if(!$this->rcmail) $this->rcmail = rcmail::get_instance();
        $this->add_texts('localization', false);

        // get input
        $section = get_input_value('_ks_section',   RCUBE_INPUT_GET);
        $id      = 'new' . get_input_value('_ks_id',   RCUBE_INPUT_GET);

        // create new input row
        $content =  html::tag('tr', array(),
                        html::tag('td', array('class' => 'title'), $this->create_title($id, $section)) .
                        html::tag('td', array(), $this->create_row($id, $section))
                    );

        // return response
        $this->rcmail->output->command('plugin.ks_receive_row', array('section' => $section, 'row' => $content));
    }

    function create_title($id, $section, $command = false)
    {

        // title
        if($command !== false) {
            $title = isset($this->commands[$section][$command]['label']) ? Q($this->gettext($this->commands[$section][$command]['label'])) : Q($this->gettext($command));
            // command
            $hidden_command =  new html_hiddenfield(array('name' => "_ks_command[$section][]", 'value' => $command));
            $content = $title . $hidden_command->show();
        } else {
            // get commands
            if(!$this->commands) $this->commands = $this->rcmail->config->get('keyboard_shortcuts_commands', array());

            // create a select box of the available commands in this section
            $select = new html_select(array('name' => "_ks_command[$section][]"));

            xs_log($section);
            xs_log($this->commands);
            if(is_array($this->commands)) {
                xs_log('commands');
                foreach($this->commands[$section] as $command => $options) {
                    xs_log("command: $command");
                    $label = $options['label'] ?: $command;
                    xs_log("label: $label");
                    $select->add(Q($this->gettext($label)),$command);
                }
            }

            $content = $select->show();
        }

        return $content;
    }

    function create_row($id, $section, $key = false)
    {

        // ascii key
        $input = html::tag('input', array('name' => "_ks_ascii[$section][]", 'class' => 'rcmfd_ks_input key', 'type' => 'text', 'autocomplete' => 'off', 'value' => $key ? chr($key) : '', 'data-section' => $section, 'data-id' => $id));

        // key code
        $hidden_keycode = new html_hiddenfield(array('name' => "_ks_keycode[$section][]", 'value' => $key, 'class' => 'keycode', 'data-section' => $section, 'data-id' => $id++));

        // del button
        $button = html::a(array('class' => 'button ks_del'), '');

        // content
        $content = html::tag('span', array('class' => 'ks_content'), $input . $hidden_keycode->show() . $button);

        return $content;
    }

    function preferences_save($args)
    {
        if($args['section'] == 'keyboard_shortcuts') {
            if(!$this->rcmail) $this->rcmail = rcmail::get_instance();
            $prefs = array();

            $input_ascii   = get_input_value('_ks_ascii',   RCUBE_INPUT_POST);
            $input_command = get_input_value('_ks_command', RCUBE_INPUT_POST);
            $input_keycode = get_input_value('_ks_keycode', RCUBE_INPUT_POST);

            foreach($input_keycode as $section => $keys) {
                foreach($keys as $i => $key) {
                    if(is_numeric($key)) $prefs[$section][$key] = $input_command[$section][$i];
                }
            }

            $args['prefs']['keyboard_shortcuts'] = $prefs;
        }
        return $args;
    }

    function html_output($p) {
        if ($p['name'] == "listcontrols") {
            if(!$this->rcmail) $this->rcmail = rcmail::get_instance();
            $skin  = $this->rcmail->config->get('skin');

            if(!file_exists('plugins/keyboard_shortcuts/skins/' . $skin . '/images/keyboard.png')){
                $skin = "default";
            }

            $c = "";
            $c .= '<span id="keyboard_shortcuts_title">' . $this->gettext("title") . ":&nbsp;</span><a id='keyboard_shortcuts_link' href='#' class='button' title='".$this->gettext("keyboard_shortcuts")." ".$this->gettext("show")."' onclick='return keyboard_shortcuts_show_help()'><img align='top' src='plugins/keyboard_shortcuts/skins/".$skin."/images/keyboard.png' alt='".$this->gettext("keyboard_shortcuts")." ".$this->gettext("show")."' /></a>\n";
            $c .= "<div id='keyboard_shortcuts_help'>";
            $c .= "<div><h4>".$this->gettext("mailboxview")."</h4>";
            $c .= "<div class='shortcut_key'>?</div> ".$this->gettext('help')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>a</div> ".$this->gettext('selectallvisiblemessages')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>A</div> ".$this->gettext('markallvisiblemessagesasread')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>c</div> ".$this->gettext('compose')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>d</div> ".$this->gettext('deletemessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>f</div> ".$this->gettext('forwardmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>j</div> ".$this->gettext('previouspage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>k</div> ".$this->gettext('nextpage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>p</div> ".$this->gettext('printmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>r</div> ".$this->gettext('replytomessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>R</div> ".$this->gettext('replytoallmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>s</div> ".$this->gettext('quicksearch')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>u</div> ".$this->gettext('checkmail')."<br class='clear' />";
            $c .= "<div class='shortcut_key'> </div> <br class='clear' />";
            $c .= "</div>";

            if(!is_object($thisrcmail->imap)){
                $this->rcmail->imap_connect();
            }
            $threading_supported = $this->rcmail->imap->get_capability('thread=references')
                || $this->rcmail->imap->get_capability('thread=orderedsubject')
                || $this->rcmail->imap->get_capability('thread=refs');

            if ($threading_supported) {
                $c .= "<div><h4>".$this->gettext("threads")."</h4>";
                $c .= "<div class='shortcut_key'>E</div> ".$this->gettext('expand-all')."<br class='clear' />";
                $c .= "<div class='shortcut_key'>C</div> ".$this->gettext('collapse-all')."<br class='clear' />";
                $c .= "<div class='shortcut_key'>U</div> ".$this->gettext('expand-unread')."<br class='clear' />";
                $c .= "<div class='shortcut_key'> </div> <br class='clear' />";
                $c .= "</div>";
            }
            $c .= "<div><h4>".$this->gettext("messagesdisplaying")."</h4>";
            $c .= "<div class='shortcut_key'>d</div> ".$this->gettext('deletemessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>c</div> ".$this->gettext('compose')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>f</div> ".$this->gettext('forwardmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>j</div> ".$this->gettext('previousmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>k</div> ".$this->gettext('nextmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>p</div> ".$this->gettext('printmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>r</div> ".$this->gettext('replytomessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'>R</div> ".$this->gettext('replytoallmessage')."<br class='clear' />";
            $c .= "<div class='shortcut_key'> </div> <br class='clear' />";
            $c .= "</div></div>";

            //$this->prefs = $this->rcmail->config->get('keyboard_shortcuts', $this->rcmail->config->get('keyboard_shortcuts_default'));

            //$rcmail->output->set_env('ks_commands', $this->prefs);

            $p['content'] = $c . $p['content'];
        }
        return $p;
    }

    public static function chrsort($a, $b)
    {
        return strcasecmp(chr($a), chr($b));
    }

    function xs_log($log) {
      error_log(print_r($log,1). "\n",3,"/tmp/log");
    }
}
