<?php
namespace Automattic\WooCommerce\StoreApi\Exceptions { class RouteException extends \Exception { public function __construct($code,$message,$status) { parent::__construct($message,$status); } } }
namespace {
define('ABSPATH', __DIR__);
function get_option($key,$default=[]){return $GLOBALS['test_options']??$default;}
function __($text,$domain){return $GLOBALS['test_translations'][$text]??$text;}
function wp_html_excerpt($text,$length,$more){return substr($text,0,$length);}
function esc_html($text){return htmlspecialchars($text,ENT_QUOTES);}
function esc_html__($text,$domain){return htmlspecialchars(__($text,$domain),ENT_QUOTES);}
$GLOBALS['test_translations']=json_decode(file_get_contents(__DIR__.'/translations.json'),true);
function sanitize_textarea_field($s){return trim(strip_tags($s));}
function esc_url_raw($s,$schemes=[]){return $s;}
function add_filter(...$args) { $GLOBALS['twc_test_filters'][]=$args; } function add_action(...$args) { $GLOBALS['twc_test_actions'][]=$args; }
function wp_doing_ajax(){return false;} function current_user_can($cap){return true;} function sanitize_text_field($value){return $value;} function sanitize_key($value){return strtolower($value);} function wp_unslash($value){return $value;} function admin_url($value){return $value;} function wp_safe_redirect($url){throw new \Exception('Unexpected redirect: '.$url);}
function wp_parse_url($url,$component){return parse_url($url,$component);}
function wc_privacy_policy_page_id(){return 1;}
function remove_meta_box($id,$screen,$context){$GLOBALS['removed_boxes'][]=[$id,$screen,$context];}
function is_admin(){return $GLOBALS['test_admin']??true;}
function remove_menu_page($slug){global $menu; foreach($menu as $k=>$item){if($item[2]===$slug){unset($menu[$k]);}}}
function remove_submenu_page($parent,$slug){global $submenu; foreach($submenu[$parent]??[] as $k=>$item){if($item[2]===$slug){unset($submenu[$parent][$k]);}}}
class WC_Product { public function __construct(public $price=0,public $virtual=false,public $download=false){} function get_price(){return $this->price;} function is_virtual(){return $this->virtual;} function is_downloadable(){return $this->download;} function get_downloads(){return $GLOBALS['test_no_files']??false ? [] : ['file'];} }
class WC_Cart { function __construct(public $products, public $total=0){} function is_empty(){return !$this->products;} function get_cart(){return array_map(fn($p)=>['data'=>$p],$this->products);} function get_total($context){return $this->total;} function get_fees(){return $GLOBALS['test_fees']??[];} function get_shipping_total(){return $GLOBALS['test_shipping_total']??0;} function needs_shipping(){return $GLOBALS["test_shipping_enabled"]??true;} }
class WC_Order_Item {function __construct(public $product=null,public $total=0,public $tax=0){} function get_product(){return $this->product;} function get_total(){return $this->total;} function get_total_tax(){return $this->tax;}}
class WC_Order {public $billing=['first_name'=>'Test','last_name'=>'Person','address_1'=>'Street 1','city'=>'Town','country'=>'DE','postcode'=>'12345']; public $shipping=['address_1'=>'Street 1']; public $methods=[1]; public $products=[]; public $fees=[]; function __construct(public $total){} function get_total(){return $this->total;} function get_items($type){if($type==='line_item'){return array_map(fn($p)=>new WC_Order_Item($p),$this->products?:[new WC_Product()]);} if($type==='fee'){return $this->fees;} return array_map(fn($p)=>new WC_Order_Item(null,$p===1?0:$p),$this->methods);} function get_shipping_address_1(){return $this->shipping['address_1']??'';} function needs_shipping_address(){return (bool)$this->methods;} function get_address($type){return $this->$type;} function set_address($data,$type){$this->$type=$data;} function has_status($status){return true;} }
class WP_Error {public $errors=[]; function add($code,$message){$this->errors[$code]=$message;} }
require __DIR__.'/../includes/settings.php';
$GLOBALS['test_options']=DDW_TWC_Settings::preset('free','physical');$GLOBALS['test_options']['admin_label']='Audiodienst';
require __DIR__.'/../includes/commerce.php';
DDW_TWC_Commerce::register();
$count=0;
function check($condition,$label){global $count; if(!$condition){throw new \Exception('FAIL: '.$label);} ++$count;}
foreach ([['free physical',new WC_Product(),0,false],['postage',new WC_Product(),3,true],['paid discounted',new WC_Product(10),0,true],['empty price',new WC_Product(''),0,true],['virtual',new WC_Product(0,true),0,true],['downloadable',new WC_Product(0,false,true),0,true],['negative fee',new WC_Product(),-1,true],['decimal zero',new WC_Product('0.00'),0,false]] as [$label,$p,$total,$bad]) { $e=new WP_Error(); DDW_TWC_Commerce::store_validation($e,new WC_Cart([$p],$total)); check((bool)$e->errors===$bad,$label); }
$types=DDW_TWC_Commerce::notes_query(['type'=>['marketing','warning','survey']]); check($types['type']===['warning'],'warnings retained');
$types=DDW_TWC_Commerce::notes_query(['type'=>['marketing']]); check($types['type']===['ddw-twc-no-note-type'],'promo-only no wildcard');
$types=DDW_TWC_Commerce::notes_query([]); check(in_array('update',$types['type'],true),'updates retained');
$f=['billing'=>['billing_email'=>[], 'billing_country'=>[], 'billing_company'=>[], 'billing_phone'=>[]],'shipping'=>['shipping_postcode'=>[]]];
$t=DDW_TWC_Commerce::checkout_fields($f); check(isset($t['billing']['billing_email'],$t['billing']['billing_country'],$t['shipping']['shipping_postcode'])&&isset($t['billing']['billing_phone'])&&!isset($t['billing']['billing_company']),'address retained');
check(DDW_TWC_Commerce::settings_tabs(['shipping'=>1,'checkout'=>2,'account'=>3])===['shipping'=>1,'account'=>3],'shipping tab retained');
foreach([false,true] as $store){$caught=false; try {if($store){DDW_TWC_Commerce::store_order(new WC_Order(3));}else{DDW_TWC_Commerce::classic_order(1,[],new WC_Order(3));}}catch(\Exception $e){$caught=true;}check($caught,'positive order stopped');}
DDW_TWC_Commerce::store_order(new WC_Order(0)); ++$count;
foreach (['/wp-admin/admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM','admin.php?tab=checkout&page=wc-settings','https://example.org/wp-admin/admin.php?page=wc-settings&amp;tab=checkout'] as $url) {check(DDW_TWC_Commerce::is_payment_settings_link($url),'payment URL recognized');}
foreach (['admin.php?page=wc-settings&tab=shipping','admin.php?page=wc-orders','wc-admin&path=/analytics/overview','admin.php?page[]=wc-settings&tab=checkout'] as $url) {check(!DDW_TWC_Commerce::is_payment_settings_link($url),'other surface retained');}
check(DDW_TWC_Commerce::admin_translation('WooCommerce Status','WooCommerce Status','woocommerce')==='Audiodienst Status','admin translation');
$GLOBALS['test_admin']=false; check(DDW_TWC_Commerce::admin_translation('WooCommerce','WooCommerce','woocommerce')==='WooCommerce','frontend unchanged');
$GLOBALS['test_admin']=true; check(DDW_TWC_Commerce::admin_translation('WooCommerce','WooCommerce','other')==='WooCommerce','other domain unchanged');
$menu=[['WooCommerce <span class="count">2</span>','cap','woocommerce','WooCommerce'],['Payments','cap','admin.php?tab=checkout&page=wc-settings'],['Products','cap','edit.php?post_type=product']];
$submenu=['woocommerce'=>[['Shipping','cap','admin.php?page=wc-settings&tab=shipping'],['Payments','cap','admin.php?page=wc-settings&tab=checkout']]];
DDW_TWC_Commerce::menus(); check(count($menu)===2&&$menu[0][0]==='Audiodienst <span class="count">2</span>'&&$menu[0][2]==='woocommerce','rename preserves badge and slug'); check(count($submenu['woocommerce'])===1,'payment submenu removed shipping retained');
$wp_meta_boxes=['dashboard'=>['normal'=>['core'=>['woocommerce_dashboard_status'=>['title'=>'WooCommerce Status','callback'=>'original'],'another'=>['title'=>'Other']]]]];
DDW_TWC_Commerce::dashboard_titles(); check($wp_meta_boxes['dashboard']['normal']['core']['woocommerce_dashboard_status']===['title'=>'Audiodienst – Übersicht','callback'=>'original'],'widget callback retained');
check($menu[0][6]==='dashicons-album','CD icon');
check(DDW_TWC_Commerce::is_payment_settings_link('wc-admin&path=/payments/connect'),'payments menu slug');
check(DDW_TWC_Commerce::is_payment_settings_link('admin.php?page=wc-admin&path=%2Fpayments%2Fconnect'),'encoded payments path');
check(!DDW_TWC_Commerce::is_payment_settings_link('admin.php?page=wc-admin&path=/payments-other'),'unrelated path');
DDW_TWC_Commerce::menus(); check($menu[0][0]==='Audiodienst <span class="count">2</span>','repeated cleanup retains badge');
$hooks=array_column($GLOBALS['twc_test_actions']??[],0);
check(in_array('admin_menu',$hooks,true),'bootstrap registers admin menu');
check(in_array('admin_head',$hooks,true),'bootstrap registers late admin cleanup');
check(in_array('wp_dashboard_setup',$hooks,true),'bootstrap registers dashboard cleanup');
check(in_array('woocommerce_store_api_cart_errors',$hooks,true),'bootstrap registers cart validation');
$filters=array_column($GLOBALS['twc_test_filters']??[],0);
check(in_array('woocommerce_available_payment_gateways',$filters,true),'bootstrap disables available gateways');
$before=count($GLOBALS['twc_test_actions']);
include __DIR__.'/../includes/commerce.php';
DDW_TWC_Commerce::register();
check(count($GLOBALS['twc_test_actions'])===$before,'duplicate include is safe without duplicate hooks');
$o=new WC_Order(0); $o->shipping=[]; DDW_TWC_Commerce::copy_delivery_address($o); check($o->shipping['address_1']==='Street 1','copy empty shipping from billing');
$o->shipping=['address_1'=>'Other 2']; DDW_TWC_Commerce::copy_delivery_address($o); check($o->shipping['address_1']==='Other 2','preserve different address');
$o->shipping=[]; DDW_TWC_Commerce::copy_delivery_address($o,['ship_to_different_address'=>1]); check($o->shipping===[],'do not silently replace explicit different address');
foreach(['methods','shipping'] as $property){$o=new WC_Order(0);$o->$property=[];$caught=false;try{DDW_TWC_Commerce::store_order($o);}catch(\Exception $e){$caught=true;}check($caught,'missing shipping rejected');}
$GLOBALS['test_shipping_enabled']=false;$e=new WP_Error();DDW_TWC_Commerce::store_validation($e,new WC_Cart([new WC_Product()]));check((bool)$e->errors,'disabled shipping rejected');$GLOBALS['test_shipping_enabled']=true;
check(DDW_TWC_Commerce::order_button('Place order')==='Kostenlos bestellen','button text');
$GLOBALS['test_admin']=false; check(DDW_TWC_Commerce::customer_translation('Rechnungsdaten','Billing details','woocommerce')==='Ihre Kontaktdaten und Adresse','billing caption'); $GLOBALS['test_admin']=true;
check(str_contains(DDW_TWC_Commerce::privacy_text('English','checkout'),'[privacy_policy]'),'privacy link placeholder');
check(DDW_TWC_Commerce::privacy_text('Original','registration')==='Original','registration untouched');
DDW_TWC_Commerce::order_boxes('shop_order',null);check(count($GLOBALS['removed_boxes'])===3,'order boxes removed');DDW_TWC_Commerce::order_boxes('product',null);check(count($GLOBALS['removed_boxes'])===3,'product boxes untouched');
foreach(['/customers','/customers/1','/analytics/customers'] as $path){check(DDW_TWC_Commerce::is_customer_route($path),'customer route allowed');}
check(!DDW_TWC_Commerce::is_customer_route('/payments'),'payments not customer route');
$_SERVER['REQUEST_METHOD']='GET';
foreach(['','/','/customers','/analytics/customers'] as $path){$_GET=['page'=>'wc-admin','path'=>$path];DDW_TWC_Commerce::redirect_admin();check(true,'shared admin and customers not redirected');}
$submenu=['woocommerce'=>[['Home','cap','wc-admin'],['Customers','cap','wc-admin&path=/customers']]]; DDW_TWC_Commerce::menus();check(count($submenu['woocommerce'])===2,'shared root and customers menu retained');
$_GET=['page'=>'wc-settings','tab'=>'checkout'];$blocked=false;try{DDW_TWC_Commerce::redirect_admin();}catch(\Exception $e){$blocked=true;}check($blocked,'payment UI still redirected');
$GLOBALS['test_options']=['operation_mode'=>'standard','hide_payments_menu'=>false,'hide_marketplace_menu'=>false,'hide_marketing_menu'=>false,'rename_menu'=>false];
check(DDW_TWC_Commerce::order_button('Place order')==='Place order','paid mode restores button');
$menu=[['WooCommerce','cap','woocommerce','WooCommerce'],['Payments','cap','admin.php?page=wc-settings&tab=checkout'],['Marketing','cap','woocommerce-marketing']];
$submenu=[]; DDW_TWC_Commerce::menus(); check(count($menu)===3&&$menu[0][0]==='WooCommerce','disabled modules preserve menu');
$_GET=['page'=>'wc-settings','tab'=>'checkout'];DDW_TWC_Commerce::redirect_admin();check(true,'paid mode keeps payment routes');
$GLOBALS['test_options']=['admin_label'=>'CD Shop'];check(DDW_TWC_Commerce::label()==='CD Shop','custom admin label');
$GLOBALS['test_options']=DDW_TWC_Settings::preset('free','downloads');
foreach ([['download',new WC_Product(0,true,true),false],['physical',new WC_Product(),true],['service',new WC_Product(0,true,false),true],['not virtual',new WC_Product(0,false,true),true],['paid download',new WC_Product(5,true,true),true]] as [$label,$product,$bad]) {
 $errors=new WP_Error();DDW_TWC_Commerce::store_validation($errors,new WC_Cart([$product]));check((bool)$errors->errors===$bad,'download mode '.$label);
}
$GLOBALS['test_no_files']=true;$e=new WP_Error();DDW_TWC_Commerce::store_validation($e,new WC_Cart([new WC_Product(0,true,true)]));check((bool)$e->errors,'missing download files rejected');$GLOBALS['test_no_files']=false;
$order=new WC_Order(0);$order->products=[new WC_Product(0,true,true)];$order->methods=[];$order->shipping=[];DDW_TWC_Commerce::store_order($order);check(true,'download order without shipping passes');
DDW_TWC_Commerce::copy_delivery_address($order);check($order->shipping===[],'download does not copy physical address');
$GLOBALS['removed_boxes']=[];DDW_TWC_Commerce::order_boxes('shop_order',null);check(count($GLOBALS['removed_boxes'])===2&&!in_array('woocommerce-order-downloads',array_column($GLOBALS['removed_boxes'],0),true),'download permissions retained');
$e=new WP_Error();DDW_TWC_Commerce::store_validation($e,new WC_Cart([new WC_Product(0,true,true),new WC_Product()]));check((bool)$e->errors,'mixed cart rejected');
foreach ([[(object)['amount'=>2],(object)['amount'=>-2]],[(object)['amount'=>-1]]] as $fees){$GLOBALS['test_fees']=$fees;$e=new WP_Error();DDW_TWC_Commerce::store_validation($e,new WC_Cart([new WC_Product(0,true,true)]));check((bool)$e->errors,'nonzero fee cannot cancel out');}$GLOBALS['test_fees']=[];
$order->fees=[new WC_Order_Item(null,2),new WC_Order_Item(null,-2)];$caught=false;try{DDW_TWC_Commerce::store_order($order);}catch(Exception $e){$caught=true;}check($caught,'late cancelling fees rejected');
$GLOBALS['test_options']=DDW_TWC_Settings::preset('tidy');$e=new WP_Error();DDW_TWC_Commerce::store_validation($e,new WC_Cart([new WC_Product(20)],20));check(!$e->errors,'regular sale is unconstrained');
$before=$GLOBALS['test_options'];check(DDW_TWC_Settings::preset('individual')===$before,'individual preserves saved switches');check($GLOBALS['test_options']===$before,'preset lookup does not write settings');
$GLOBALS['test_options']=['cleanup'=>false,'paymentless'=>true,'rename_admin'=>true,'admin_label'=>'Audiodienst','order_view'=>true];$o=DDW_TWC_Settings::options();check($o['operation_mode']==='free_physical'&&!$o['hide_marketplace_ads']&&$o['rename_menu']&&$o['hide_payments_menu'],'legacy physical settings retained');check($GLOBALS['test_options']['paymentless']===true&&!isset($GLOBALS['test_options']['operation_mode']),'legacy conversion remains read-only');
check(DDW_TWC_Settings::sanitize(['operation_mode'=>'invalid','admin_icon'=>'injected','hide_marketplace_ads'=>'false'])['operation_mode']==='standard','invalid mode safe fallback');
check(DDW_TWC_Settings::sanitize(['operation_mode'=>'free_downloads','hide_downloads_box'=>1])['hide_downloads_box'],'download mode preserves stored display preference');
$GLOBALS['test_options']=['cleanup'=>true,'paymentless'=>false,'trim_checkout'=>true];check(!DDW_TWC_Settings::options()['trim_checkout'],'legacy paid mode retains original company field');
$GLOBALS['test_options']=DDW_TWC_Settings::preset('free','physical');$o=new WC_Order(0);$o->shipping=['company'=>'Existing'];DDW_TWC_Commerce::copy_delivery_address($o);check($o->shipping===['company'=>'Existing'],'partial shipping company retained');
echo "$count isolated checks passed. No WordPress integration test.\n";
}
