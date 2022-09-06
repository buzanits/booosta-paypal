<?php
namespace booosta\paypal_com;

use \booosta\Framework as b;
b::init_module('paypal_com');

use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Payment;


class Paypal_com extends \booosta\base\Module
{ 
  use moduletrait_paypal_com;

  protected $sandbox;
  protected $id = '0';
  protected $vendor_id, $paypal_id, $paypal_key;
  protected $currency;
  protected $disabled_features;


  public function __construct($paypal_id = null, $paypal_key = null, $vendor_id = null)
  {
    parent::__construct();

    $this->sandbox = $this->config('paypal_sandbox') ?? false;
    $this->paypal_id = $paypal_id ?? $this->config('paypal_id');
    $this->paypal_key = $paypal_key ?? $this->config('paypal_key');
    $this->currency = $this->config('paypal_currency') ?? 'EUR';
    $this->disabled_features = $this->config('paypal_disabled') ?? '';
    $this->vendor_id = $vendor_id ?? $this->config('paypal_vendor_id');
  }

  public function set_id($data) { $this->id = $data; }
  public function set_vendor_id($data) { $this->vendor_id = $data; }
  public function set_paypal_id($data) { $this->paypal_id = $data; }
  public function set_paypal_key($data) { $this->paypal_key = $data; }
  public function set_currency($data) { $this->currency = $data; }
  public function set_disabled_features($data) { $this->disabled_features = $data; }

  public function get_checkout_form($url, $redirect, $amountstr, $description = '', $hidden = null)
  {
    if(!is_object($this->topobj)) return false;

    if($this->disabled_features) $disabled_features = "&disable-funding=$this->disabled_features";
    $jsfile = "https://www.paypal.com/sdk/js?client-id=$this->paypal_id&currency=$this->currency$disabled_features";
    if(!$this->config('paypal_js_always_loaded')) $this->topobj->add_javascriptfile($jsfile);
   
    $extradata = '';
    if(is_array($hidden)) foreach($hidden as $key=>$value) $extradata .= ", $key: '$value'";
    $success_js = "window.location.href='$redirect';";
 
    $js = file_get_contents('lib/modules/paypal_com/js.tpl');
    $js = str_replace(['{amount}', '{success-js}', '{url}', '{extradata}', '{id}'], [$amountstr, $success_js, $url, $extradata, $this->id], $js);
    $this->topobj->add_javascript($js);

    return "<div id='paypal-button-container-$this->id'></div>";
  }

  public function get_plan_button($url, $redirect, $cancel_redirect, $amountstr, $description = '', $interval = 'M', $custom_value = '', $trialmonths = 0)
  {
    $sandboxstr = $this->sandbox ? 'sandbox.' : '';
    $tpl = file_get_contents('tpl/paypal_button.tpl');

    if(is_array($trialmonths)):
      $trialamount = $trialmonths['amount'];
      $trialperiods = $trialmonths['periods'];
      $trialperiod = $trialmonths['period'];

      $trialcode = file_get_contents('tpl/paypal_button_trial.tpl');
      $tpl = str_replace('{trialperiod}', $trialcode, $tpl);
    elseif($trialmonths > 0):
      $trialamount = 0;
      $trialperiods = $trialmonths;
      $trialperiod = 'M';

      $trialcode = file_get_contents('tpl/paypal_button_trial.tpl');
      $tpl = str_replace('{trialperiod}', $trialcode, $tpl);
    endif;

    $tags = ['{vendor_id}', '{custom}', '{name}', '{currency}', '{amount}', '{interval}', '{success_url}', '{cancel_url}', '{notify_url}', '{sandbox}', '{trialperiods}', '{trialperiod}', '{trialamount}'];
    $values = [$this->vendor_id, $custom_value, $description, $this->currency, $amountstr, $interval, $redirect, $cancel_redirect, $url, $sandboxstr, $trialperiods, $trialperiod, $trialamount];

    return str_replace($tags, $values, $tpl);
  }

  protected function get_client()
  {
    if($this->sandbox) return new PayPalHttpClient(new SandboxEnvironment($this->paypal_id, $this->paypal_key));
    return new PayPalHttpClient(new ProductionEnvironment($this->paypal_id, $this->paypal_key));
  }

  public function get_response()
  {
    $result['input'] = json_decode(file_get_contents('php://input'), true);
    #if($result['input']['orderID'] == '') return 'ERROR: no order_id provided';

    $client = $this->get_client();
    $response = $client->execute(new OrdersGetRequest($result['input']['orderID']));

    $result['status_code'] = $response->statusCode;
    $result['status'] = $response->result->status;
    $result['order_id'] = $response->result->id;
    $result['links'] = $response->result->links;
    $result['currency'] = $response->result->purchase_units[0]->amount->currency_code;
    $result['amount'] = $response->result->purchase_units[0]->amount->value;

    return $result;
  }

  public function verify_payment($payment_id)
  {
    #\booosta\debug(new ApiContext(new OAuthTokenCredential($this->paypal_id, $this->paypal_key)));
    $apiContext = new ApiContext(new OAuthTokenCredential($this->paypal_id, $this->paypal_key));
    $apiContext->setConfig(['mode' => $this->sandbox ? 'sandbox' : '', 'log.LogEnabled' => true, 'log.FileName' => '../logs/PayPal.log',
      'log.LogLevel' => 'DEBUG', 'validation.level' => 'log', 'cache.enabled' => false]);

    try {
      #$payment = Payment::get($payment_id, $apiContext);
      #$result = json_decode($payment, true);

      $params = array('count' => 10, 'start_index' => 0); 
      $payments = Payment::all($params, $apiContext);
      $result = json_decode($payments, true);
    } catch (\Exception $ex) {
      return $ex->getMessage();  // for debug purposes
      return false;
    }

    return $result;  // for debug purposes
    return true;
  }
}
