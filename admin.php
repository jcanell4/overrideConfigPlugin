<?php
/**
 * Configuration Manager admin plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 * @author     Ben Coburn <btcoburn@silicodon.net>
 * @author of IOC changes: Josep Cañellas <jcanell4@ioc.cat> & Eduard Latorre <eduardo.latorre@gmail.com>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

define('CM_KEYMARKER','____');            // used for settings with multiple dimensions of array indices

define('PLUGIN_SELF',dirname(__FILE__).'/');
define('PLUGIN_METADATA',PLUGIN_SELF.'settings/config.metadata.php');
if(!defined('DOKU_PLUGIN_IMAGES')) define('DOKU_PLUGIN_IMAGES',DOKU_BASE.'lib/plugins/config/images/');

require_once(PLUGIN_SELF.'settings/config.class.php');  // main configuration class and generic settings classes
require_once(PLUGIN_SELF.'settings/extra.class.php');   // settings classes specific to these settings

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_config extends DokuWiki_Admin_Plugin {
    //[START: IOC]
    var $needRefresh=false;
    var $allowedRefresh=true;
    //[END: IOC]

    //[START: IOC]
    protected $_file = PLUGIN_METADATA;
    protected $_config = null;
    protected $_input = null;
    protected $_changed = false;          // set to true if configuration has altered
    protected $_error = false;
    protected $_session_started = false;
    protected $_localised_prompts = false;
    
    

    public function getMenuSort() { return 100; }

    /**
     * handle user request
     */
    public function handle() {
        global $ID, $INPUT;

        if(!$this->_restore_session() || $INPUT->int('save') != 1 || !checkSecurityToken()) {
            $this->_close_session();
            return;
        }

        if(is_null($this->_config)) {
            $this->_config = new configuration($this->_file);
        }

        // don't go any further if the configuration is locked
        if($this->_config->locked) {
            $this->_close_session();
            return;
        }
        //[END: IOC]

        $this->_input = $INPUT->arr('config');

        //[START: IOC]
        foreach ($this->_config->setting as $key => $value){
        //[END: IOC]
            $input = isset($this->_input[$key]) ? $this->_input[$key] : null;
            if ($this->_config->setting[$key]->update($input)) {
                $this->_changed = true;
            }
            if ($this->_config->setting[$key]->error()) $this->_error = true;
        }
        //[START: IOC]
        $this->needRefresh=false;
        //[END: IOC]
        if ($this->_changed  && !$this->_error) {
            $this->_config->save_settings($this->getPluginName());

            // save state & force a page reload to get the new settings to take effect
            $_SESSION['PLUGIN_CONFIG'] = array('state' => 'updated', 'time' => time());

            //[START: IOC]
            if($this->allowedRefresh){
            //[END: IOC]
                $this->_close_session();
                send_redirect(wl($ID,array('do'=>'admin','page'=>'config'),true,'&'));
                exit();
             //[START: IOC]
            }else{
                $this->forceRefresh();
            }
            //[END: IOC]
        } elseif(!$this->_error) {
            $this->_config->touch_settings(); // just touch to refresh cache
        }

        $this->_close_session();
    }

    /**
     * output appropriate html
     */
    //[START: IOC]
    public function html() {
    //[END: IOC]
        $allow_debug = $GLOBALS['conf']['allowdebug']; // avoid global $conf; here.
        global $lang;
        global $ID;

        if (is_null($this->_config)) { $this->_config = new configuration($this->_file); }
        $this->setupLocale(true);

        print $this->locale_xhtml('intro');

        ptln('<div id="config__manager">');

        if ($this->_config->locked)
            ptln('<div class="info">'.$this->getLang('locked').'</div>');
        elseif ($this->_error)
            ptln('<div class="error">'.$this->getLang('error').'</div>');
        elseif ($this->_changed)
            ptln('<div class="success">'.$this->getLang('updated').'</div>');

        // POST to script() instead of wl($ID) so config manager still works if
        // rewrite config is broken. Add $ID as hidden field to remember
        // current ID in most cases.
        ptln('<form action="'.script().'" method="post">');
        ptln('<div class="no"><input type="hidden" name="id" value="'.$ID.'" /></div>');
        formSecurityToken();
        $this->_print_h1('dokuwiki_settings', $this->getLang('_header_dokuwiki'));

        $undefined_settings = array();
        $in_fieldset = false;
        $first_plugin_fieldset = true;
        $first_template_fieldset = true;
        foreach($this->_config->setting as $setting) {
            if (is_a($setting, 'setting_hidden')) {
                // skip hidden (and undefined) settings
                if ($allow_debug && is_a($setting, 'setting_undefined')) {
                    $undefined_settings[] = $setting;
                } else {
                    continue;
                }
            } else if (is_a($setting, 'setting_fieldset')) {
                // config setting group
                if ($in_fieldset) {
                    ptln('  </table>');
                    ptln('  </div>');
                    ptln('  </fieldset>');
                } else {
                    $in_fieldset = true;
                }
                if ($first_plugin_fieldset && substr($setting->_key, 0, 10)=='plugin'.CM_KEYMARKER) {
                    $this->_print_h1('plugin_settings', $this->getLang('_header_plugin'));
                    $first_plugin_fieldset = false;
                } else if ($first_template_fieldset && substr($setting->_key, 0, 7)=='tpl'.CM_KEYMARKER) {
                    $this->_print_h1('template_settings', $this->getLang('_header_template'));
                    $first_template_fieldset = false;
                }
                ptln('  <fieldset id="'.$setting->_key.'">');
                ptln('  <legend>'.$setting->prompt($this).'</legend>');
                ptln('  <div class="table">');
                ptln('  <table class="inline">');
            } else {
                // config settings
                list($label,$input) = $setting->html($this, $this->_error);

                $class = $setting->is_default() ? ' class="default"' : ($setting->is_protected() ? ' class="protected"' : '');
                $error = $setting->error() ? ' class="value error"' : ' class="value"';
                $icon = $setting->caution() ? '<img src="'.DOKU_PLUGIN_IMAGES.$setting->caution().'.png" alt="'.$setting->caution().'" title="'.$this->getLang($setting->caution()).'" />' : '';

                ptln('    <tr'.$class.'>');
                ptln('      <td class="label">');
                ptln('        <span class="outkey">'.$setting->_out_key(true, true).'</span>');
                ptln('        '.$icon.$label);
                ptln('      </td>');
                ptln('      <td'.$error.'>'.$input.'</td>');
                ptln('    </tr>');
            }
        }

        ptln('  </table>');
        ptln('  </div>');
        if ($in_fieldset) {
            ptln('  </fieldset>');
        }

        // show undefined settings list
        if ($allow_debug && !empty($undefined_settings)) {
            //[START: IOC]
            function _setting_natural_comparison($a, $b) {
                return strnatcmp($a->_key, $b->_key);
            }
            //[END: IOC]
            usort($undefined_settings, '_setting_natural_comparison');
            $this->_print_h1('undefined_settings', $this->getLang('_header_undefined'));
            ptln('<fieldset>');
            ptln('<div class="table">');
            ptln('<table class="inline">');
            $undefined_setting_match = array();
            foreach($undefined_settings as $setting) {
                if (preg_match('/^(?:plugin|tpl)'.CM_KEYMARKER.'.*?'.CM_KEYMARKER.'(.*)$/', $setting->_key, $undefined_setting_match)) {
                    $undefined_setting_key = $undefined_setting_match[1];
                } else {
                    $undefined_setting_key = $setting->_key;
                }
                ptln('  <tr>');
                ptln('    <td class="label"><span title="$meta[\''.$undefined_setting_key.'\']">$'.$this->_config->_name.'[\''.$setting->_out_key().'\']</span></td>');
                ptln('    <td>'.$this->getLang('_msg_'.get_class($setting)).'</td>');
                ptln('  </tr>');
            }
            ptln('</table>');
            ptln('</div>');
            ptln('</fieldset>');
        }

        // finish up form
        ptln('<p>');
        ptln('  <input type="hidden" name="do"     value="admin" />');
        ptln('  <input type="hidden" name="page"   value="config" />');

        if (!$this->_config->locked) {
            ptln('  <input type="hidden" name="save"   value="1" />');
            ptln('  <button type="submit" name="submit" accesskey="s">'.$lang['btn_save'].'</button>');
            ptln('  <button type="reset">'.$lang['btn_reset'].'</button>');
        }

        ptln('</p>');

        ptln('</form>');
        ptln('</div>');
    }

    /**
     * @return boolean   true - proceed with handle, false - don't proceed
     */
    //[START: IOC]
    protected function _restore_session() {
    //[END: IOC]

        // dokuwiki closes the session before act_dispatch. $_SESSION variables are all set,
        // however they can't be changed without starting the session again
        if (!headers_sent()) {
            session_start();
            $this->_session_started = true;
        }

        if (!isset($_SESSION['PLUGIN_CONFIG'])) return true;

        $session = $_SESSION['PLUGIN_CONFIG'];
        unset($_SESSION['PLUGIN_CONFIG']);

        // still valid?
        if (time() - $session['time'] > 120) return true;

        switch ($session['state']) {
            case 'updated' :
                $this->_changed = true;
                return false;
        }

        return true;
    }

    //[START: IOC]
    protected function _close_session() {
    //[END: IOC]
      if ($this->_session_started) session_write_close();
    }

    //[START: IOC]
    public function setupLocale($prompts=false) {
    //[END: IOC]

        parent::setupLocale();
        if (!$prompts || $this->_localised_prompts) return;

        $this->_setup_localised_plugin_prompts();
        $this->_localised_prompts = true;

    }

    //[START: IOC]
    protected function _setup_localised_plugin_prompts() {
    //[END: IOC]
        global $conf;

        $langfile   = '/lang/'.$conf['lang'].'/settings.php';
        $enlangfile = '/lang/en/settings.php';

        //[START: IOC]
        if (($dh = opendir(DOKU_PLUGIN))) {
        //[END: IOC]
            while (false !== ($plugin = readdir($dh))) {
                if ($plugin == '.' || $plugin == '..' || $plugin == 'tmp' || $plugin == 'config') continue;
                if (is_file(DOKU_PLUGIN.$plugin)) continue;

                if (@file_exists(DOKU_PLUGIN.$plugin.$enlangfile)){
                    $lang = array();
                    @include(DOKU_PLUGIN.$plugin.$enlangfile);
                    if ($conf['lang'] != 'en') @include(DOKU_PLUGIN.$plugin.$langfile);
                    foreach ($lang as $key => $value){
                        $this->lang['plugin'.CM_KEYMARKER.$plugin.CM_KEYMARKER.$key] = $value;
                    }
                }

                // fill in the plugin name if missing (should exist for plugins with settings)
                if (!isset($this->lang['plugin'.CM_KEYMARKER.$plugin.CM_KEYMARKER.'plugin_settings_name'])) {
                    $this->lang['plugin'.CM_KEYMARKER.$plugin.CM_KEYMARKER.'plugin_settings_name'] =
                      ucwords(str_replace('_', ' ', $plugin));
                }
            }
            closedir($dh);
      }

        // the same for the active template
        $tpl = $conf['template'];

        if (@file_exists(tpl_incdir().$enlangfile)){
            $lang = array();
            @include(tpl_incdir().$enlangfile);
            if ($conf['lang'] != 'en') @include(tpl_incdir().$langfile);
            foreach ($lang as $key => $value){
                $this->lang['tpl'.CM_KEYMARKER.$tpl.CM_KEYMARKER.$key] = $value;
            }
        }

        // fill in the template name if missing (should exist for templates with settings)
        if (!isset($this->lang['tpl'.CM_KEYMARKER.$tpl.CM_KEYMARKER.'template_settings_name'])) {
            $this->lang['tpl'.CM_KEYMARKER.$tpl.CM_KEYMARKER.'template_settings_name'] =
              ucwords(str_replace('_', ' ', $tpl));
        }

        return true;
    }

    //[START: IOC]
    /**
     * Generates a two-level table of contents for the config plugin.
     * @author Ben Coburn <btcoburn@silicodon.net>
     * @return array
     */
    public function getTOC() {
    //[END: IOC]
        if (is_null($this->_config)) { $this->_config = new configuration($this->_file); }
        $this->setupLocale(true);

        $allow_debug = $GLOBALS['conf']['allowdebug']; // avoid global $conf; here.

        // gather toc data
        $has_undefined = false;
        $toc = array('conf'=>array(), 'plugin'=>array(), 'template'=>null);
        foreach($this->_config->setting as $setting) {
            if (is_a($setting, 'setting_fieldset')) {
                if (substr($setting->_key, 0, 10)=='plugin'.CM_KEYMARKER) {
                    $toc['plugin'][] = $setting;
                } else if (substr($setting->_key, 0, 7)=='tpl'.CM_KEYMARKER) {
                    $toc['template'] = $setting;
                } else {
                    $toc['conf'][] = $setting;
                }
            } else if (!$has_undefined && is_a($setting, 'setting_undefined')) {
                $has_undefined = true;
            }
        }

        // build toc
        $t = array();
        $check = false;
        $title = $this->getLang('_configuration_manager');

        $t[] = html_mktocitem(sectionID($title, $check), $title, 1);
        $t[] = html_mktocitem('dokuwiki_settings', $this->getLang('_header_dokuwiki'), 1);
        foreach($toc['conf'] as $setting) {
            $name = $setting->prompt($this);
            $t[] = html_mktocitem($setting->_key, $name, 2);
        }
        if (!empty($toc['plugin'])) {
            $t[] = html_mktocitem('plugin_settings', $this->getLang('_header_plugin'), 1);
        }
        foreach($toc['plugin'] as $setting) {
            $name = $setting->prompt($this);
            $t[] = html_mktocitem($setting->_key, $name, 2);
        }
        if (isset($toc['template'])) {
            $t[] = html_mktocitem('template_settings', $this->getLang('_header_template'), 1);
            $setting = $toc['template'];
            $name = $setting->prompt($this);
            $t[] = html_mktocitem($setting->_key, $name, 2);
        }
        if ($has_undefined && $allow_debug) {
            $t[] = html_mktocitem('undefined_settings', $this->getLang('_header_undefined'), 1);
        }

        return $t;
    }

    //[START: IOC]
    protected function _print_h1($id, $text) {
    //[END: IOC]
        ptln('<h1 id="'.$id.'">'.$text.'</h1>');
    }


    /**
     * Adds a translation to this plugin's language array
     */
    public function addLang($key, $value) {
        if (!$this->localised) $this->setupLocale();
        $this->lang[$key] = $value;
    }

    //[START: IOC]
    function setAllowedRefresh($value=true){
        $ret = $this->allowedRefresh;
        $this->allowedRefresh=$value;
        return $ret;
    }

    function preventRefresh(){
        $ret = $this->allowedRefresh;
        $this->allowedRefresh=false;
        return $ret;
    }

    function forceRefresh(){
        $this->needRefresh = true;
    }

    function isRefreshNeeded(){
        return $this->needRefresh;
    }
    //[END: IOC]
}
