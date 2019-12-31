<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 * This script is distributed under the GNU General Public License 2 or later.
 *
 * @filesource  TLTest.php
 * @copyright   2005-2016, TestLink community
 * @link        http://www.testlink.org/
 *
 */

require_once(TL_ABS_PATH . '/lib/functions/tlPlugin.class.php');

require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_exception.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_logger.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_util.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_event.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_loop.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_clock.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_client_base.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_socket_client.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_xml_access.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_xml_stream.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_xml.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/core/jaxl_fsm.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xmpp/xmpp_jid.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xmpp/xmpp_stanza.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xmpp/xmpp_iq.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xmpp/xmpp_xep.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xmpp/xmpp_stream.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xmpp/xmpp_pres.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xmpp/xmpp.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xep/xep_0030.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/xep/xep_0115.php');
require_once(TL_ABS_PATH . '/plugins/Xmpp/JAXL/jaxl.php');


/**
 * Sample Testlink Plugin class that registers itself with the system and provides
 * UI hooks for
 * Left Top, Left Bottom, Right Top and Right Bottom screens.
 *
 * This also listens to testsuite creation and echoes out for example.
 *
 * Class XmppPlugin
 */
class XmppPlugin extends TestlinkPlugin
{
  public $xmppLogin = null;
  public $xmppPasswd = null;
  public $xmppReceiver = null;
  public $xmppServer = null;
  public $xmppPort = 0;
  private $client = null;

  function _construct()
  {
  }

  function register()
  {
    $this->name = 'Xmpp';
    $this->description = 'XMPP Plugin';

    $this->version = '1.0';

    $this->author = 'kkretsch';
    $this->contact = 'kai@kaikretschmann.de';
    $this->url = 'https://kai.kretschmann.consulting';

    $this->xmppReceiver = plugin_config_get('config1', 'kai@kretschmann.chat', $_SESSION['testprojectID']);
    $this->xmppLogin = plugin_config_get('config2', '', $_SESSION['testprojectID']);
    $this->xmppPasswd = plugin_config_get('config3', '', $_SESSION['testprojectID']);
    $this->xmppServer = plugin_config_get('config4', 'kretschmann.chat', $_SESSION['testprojectID']);
    $this->xmppPort = plugin_config_get('config5', 5222, $_SESSION['testprojectID']);
  }

  function config()
  {
    return array(
      'config1' => '',
      'config2' => '',
      'config3' => '',
      'config4' => '',
      'config5' => 0
    );
  }

  function hooks()
  {
    $hooks = array(
      'EVENT_TEST_SUITE_CREATE' => 'testsuite_create',
      'EVENT_TEST_PROJECT_CREATE' => 'testproject_create',
      'EVENT_TEST_PROJECT_UPDATE' => 'testproject_update',
      'EVENT_TEST_CASE_UPDATE' => 'testcase_update',
      'EVENT_TEST_REQUIREMENT_CREATE' => 'testrequirement_create',
      'EVENT_TEST_REQUIREMENT_UPDATE' => 'testrequirement_update',
      'EVENT_TEST_REQUIREMENT_DELETE' => 'testrequirement_delete',
      'EVENT_EXECUTE_TEST'  => 'testExecute',
      'EVENT_LEFTMENU_TOP' => 'top_link'
    );
    return $hooks;
  }

  function on_auth_success_callback()
  {
      JAXLLogger::info("got on_auth_success cb, jid ".$this->client->full_jid->to_string());
      // ?? $this->client->set_status("available!", "chat", 10);
      //JAXLXmlAccess $stanza = new JAXLXml
      //$this->client->send();
  }

  function on_auth_failure_callback($reason)
  {
      $this->client->send_end_stream();
      JAXLLogger::info("got on_auth_failure cb with reason $reason");
  }

  function on_chat_message_callback($msg)
  {
      JAXLLogger::info("got chat_message cb from " . $msg->from);
      $msg->to = $msg->from;
      $msg->from = $this->client->full_jid->to_string();
      $this->client->send($msg);
  }

  function xmppConnect()
  {
      $this->client = new JAXL(array(
          'jid' => $this->xmppLogin,
          'pass' => $this->xmppPasswd,
          'log_level' => JAXLLogger::DEBUG,
          'strict' => false,
          'resource' => 'Testlink'
      ));
      $this->client->add_cb('on_auth_success', array($this, 'on_auth_success_callback'));
      $this->client->add_cb('on_auth_failure', array($this, 'on_auth_failure_callback'));
      $this->client->add_cb('on_chat_message', array($this, 'on_chat_message_callback'));
      $this->client->start();

//       $this->conn = new XMPPHP_XMPP(
//           $this->xmppServer,
//           $this->xmppPort,
//           $this->xmppLogin,
//           $this->xmppPasswd,
//           'Testlink'
//           );
//       $this->conn->connect();
  }

  function xmppSend($msg)
  {
      tLog("Start XMPP sending to " . $this->xmppReceiver, "WARNING");
      $this->xmppConnect();
      $this->client->send_end_stream();
//       $this->xmppConnect();
//       $this->conn->processUntil('session_start');
//       $this->conn->message($this->xmppReceiver, $msg);
//       $this->conn->disconnect();
  }

  function testsuite_create($args)
  {
    $arg = func_get_args();   // To get all the arguments
    $db = $this->db;      // To show how to get a Database Connection
    echo plugin_lang_get("testsuite_display_message");
    tLog("Im in testsuite create", "WARNING");
  }

  function testproject_create()
  {
    $arg = func_get_args();   // To get all the arguments
    tLog("In TestProject Create with id: " . $arg[1] . ", name: " . $arg[2] . ", prefix: " . $arg[3], "WARNING");
  }

  function testproject_update()
  {
    $arg = func_get_args();   // To get all the arguments
    tLog("In TestProject Update with id: " . $arg[1] . ", name: " . $arg[2] . ", prefix: " . $arg[3], "WARNING");
  }

  function testcase_update()
  {
      $arg = func_get_args();   // To get all the arguments
      $this->xmppSend("Updated Testcase #" . $arg[1] . " " . $arg[3]);
  }

  function testrequirement_create()
  {
      $arg = func_get_args();   // To get all the arguments
      tLog("In TestRequirement Create with id: " . $arg[1], "WARNING");
  }

  function testrequirement_update()
  {
      $arg = func_get_args();   // To get all the arguments
      tLog("In TestRequirement Update with id: " . $arg[1], "WARNING");
  }

  function testrequirement_delete()
  {
      $arg = func_get_args();   // To get all the arguments
      tLog("In TestRequirement Delete with id: " . $arg[1], "WARNING");
  }

  function testExecute() {
    $arg = func_get_args();   // To get all the arguments
    tLog("In TestRun with testrunid: " . $arg[1] . ", planid: " . $arg[2] . ", buildid: " . $arg[3] . ", testcaseid: " . $arg[4] . ", Notes: " . $arg[5] . ", Status: " . $arg[6], "WARNING");
  }

  function top_link()
  {
      $tLink['href'] = plugin_page('config.php');
      $tLink['label'] = plugin_lang_get('config');
      return $tLink;
  }
}
