<?php
/*
Plugin Name: WP Privacy（密码保护）
Description: 开启密码保护模式，访客需要知道密码才能访问你的WordPress网站。通过 <strong>设置 > 密码保护 > 设置你的密码</strong> 开启密码保护功能。如果你想禁用密码保护，取消勾选 <strong>设置 > 密码保护 > 启用密码保护</strong>。
Version: 1.0.0
Author: 热前端团队
Author URI: http://themes.reqianduan.com
License: GPL2
*/

$plugin_label = "密码保护";
$plugin_slug = "wp_privacy";

class wp_privacy{

	//define variables
	var $plugin_label = "密码保护";
	var $plugin_slug = "hide-my-site";

    public function __construct(){

		global $plugin_label, $plugin_slug;
		$this->plugin_slug = $plugin_slug;
		$this->plugin_label = $plugin_label;
		$this->plugin_dir = plugins_url( '' , __FILE__ );
		global $pagenow;
		include('includes/security.php');
		$this->security = new hidemysite_security();
		if( (!is_admin()) AND ($pagenow!='xmlrpc.php') AND ($pagenow!='wp-login.php') AND (get_option($this->plugin_slug.'_enabled', 1) == 1) AND (get_option($this->plugin_slug.'_password')) ) { //public site and plugin enabled with password set
			add_action('wp', array($this, 'rss_check')); //hooks into plugins_loaded. one of the earliest functions in wordpress
		}

        if(is_admin()){
		    add_action('admin_menu', array($this, 'add_plugin_page'));
		    add_action('admin_init', array($this, 'page_init'));
			//add admin notices
			add_action( 'admin_notices', array($this, 'admin_notices') );
			//add Settings link to plugin page
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array($this, 'add_plugin_action_links') );
			add_filter( 'plugin_row_meta', array($this,'plugin_row_links'), 10, 2 );

			//image upload script
			add_action('admin_enqueue_scripts', array($this,'motech_imageupload_script'));

			//custom image picker css for admin page
			add_action('admin_head', array($this,'motech_imagepicker_admin_css'));

			//custom image picker jquery for admin page
			add_action('admin_footer', array($this,'motech_imagepicker_admin_jquery'));

			add_action( 'admin_enqueue_scripts', array($this, 'enqueue_color_picker') ); //enqueue color picker
		}

    }

	function enqueue_color_picker( $hook_suffix ) {
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'motech-script-handle', plugins_url('js/motech-color-picker.js', __FILE__ ), array( 'wp-color-picker' ), false, true );
	}

	function motech_imageupload_script() {
		if (isset($_GET['page']) && $_GET['page'] == $this->plugin_slug.'-setting-admin') {
			wp_enqueue_media();
			wp_register_script('motech_imageupload-js', plugins_url( 'js/motech_imageupload.js' , __FILE__ ), array('jquery'));
			wp_enqueue_script('motech_imageupload-js');
		}
	}

	public function rss_check() {
		if(!$this->allow_because_rss()) {
			$this->verify_login();
		}
	}
	public function allow_because_rss() {
		if( (get_option($this->plugin_slug.'_public_rss', 0) == 1) and (is_feed()) ) {
			return true;
		} else {
			return false;
		}
	}
	public function get_cookie_name(){
		$name = $this->plugin_slug . "-access";
		return $name ;
	}
	public function get_cookie2_name(){
		$name = $this->get_cookie_name();
		$cookie2suffix = get_option($this->plugin_slug . '_cookie2suffix','');
		if(!empty($cookie2suffix)) { //cookie2suffix already set. add suffix from db
			$name .= $cookie2suffix;
		} else { //cookie2suffix not already set. generate new suffix, save to db, and add generated suffix
			$generated_suffix =	rand(1,99999);
			update_option( $this->plugin_slug . '_cookie2suffix', $generated_suffix );
			$name .= $generated_suffix;
		}
		return $name;
	}
	public function get_cookie_duration(){
		$duration_setting = get_option($this->plugin_slug.'_duration', 1);
		if($duration_setting > 0){
			return time()+(($duration_setting)*(86400));
		} else{
			return 0;
		}
	}
	public function no_admin_bypass() {
		if ( (get_option($this->plugin_slug.'_allow_admin', 0) == 1) AND (current_user_can( 'manage_options' )) ){	//site owner has chosen for admins to bypass login page, and user is an admin
			return false;
		} else {
			return true;
		}
	}
	public function get_robots_html() {
		if (  get_option( 'blog_public',1 ) != 1 ){	//site owner has chosen for search engines not to index the site
			return "<meta name='robots' content='noindex,follow'>";
		} else {
			return;
		}
	}
	public function get_title_html() {
		return;
	}
	public function get_preview_alert(){
		if($_GET['hmspreview'] == 'true') {
			return "
				<script>
				var form = document.forms[0];
				form.addEventListener('submit', function(evt){
					 evt.preventDefault();
					 alert('This is just a preview page. You can not submit the form.');
				});
				</script>
			";
		}
	}
    public function verify_login(){
		//a password was entered. first let's confirm the user isn't blocked...
		if ((isset($_POST['hwsp_motech']))) {
			$this->security->track_ip();
		}

		//set access cookie if password is correct
	 	if ((isset($_POST['hwsp_motech']) AND ($this->security->needs_to_wait != 1) AND ($_POST['hwsp_motech'] != "")) AND ($_POST['hwsp_motech'] == get_option($this->plugin_slug.'_password'))) {
    		setcookie($this->get_cookie2_name(), 1, $this->get_cookie_duration(), '/');
			$cookie_just_set = 1;
			$this->security->remove_ip();
		}
		if( (empty($_COOKIE[$this->get_cookie_name()])) AND (empty($_COOKIE[$this->get_cookie2_name()])) AND (empty($cookie_just_set)) AND ($this->no_admin_bypass()) or ($_GET['hmspreview'] == 'true') ) {
				// This is the login page for the public
				$current_hint = get_option($this->plugin_slug.'_password_hint');
				if(!empty($current_hint)) { //there is a password hint, set the hint html
					$hinthtml = "<div id='the_hint_wrap'><div id='the_hint_title'>密码提示:</div><div id='the_hint'>".$current_hint."</div></div>";
				} else { //no password hint
					$hinthtml = "";
				}

				$current_message_override = get_option($this->plugin_slug.'_custom_messaging_banner_override');
				$current_message = get_option($this->plugin_slug.'_custom_messaging_banner');
				if(!empty($current_message_override)) {
					$messagehtml = "<div id='custom_messaging_banner'>".$current_message_override."</div>";
				} elseif(!empty($current_message)) { //there is a message, set the html
					$messagehtml = "<div id='custom_messaging_banner'>".$current_message."</div>";
				} else { //no message
					$messagehtml = "";
				}

				echo "<!DOCTYPE html><html><head><title>".(get_option($this->plugin_slug . '_pagetitle','Password Protected Site'))."</title>".$this->get_robots_html().$this->security->get_alert()."<style>";//Begin HTML and login page CSS which can be customized via plugin setting page. also include security alert if applicable
				?>
                body {margin:0px;}
                #custom_messaging_banner {background: #eb583c;padding: 7px 10px;color:#fff;font-size:16px;position:relative;z-index:1;font-family:"Helvetica Neue","Arial","sans-serif";text-align:center;}
                <?php
				//use custom background image if there is one
				if(get_option($this->plugin_slug.'_custom_background_image_upload')) { ?>
					body { background: url(<?php echo get_option($this->plugin_slug.'_custom_background_image_upload') ?>) !important;}
					<?php
				}
				//use custom background image position if it's set
				if(get_option($this->plugin_slug.'_custom_background_image_position', '') != '') { ?>
					<?php if ( get_option($this->plugin_slug.'_custom_background_image_position') == 'croptofit' ) : ?>
						body {background-size: cover !important;background-position: center !important;}
					<?php elseif ( get_option($this->plugin_slug.'_custom_background_image_position') == 'repeat' ) : ?>
						body {background-repeat:repeat !important;}
					<?php elseif ( get_option($this->plugin_slug.'_custom_background_image_position') == 'stretch' ) : ?>
						body {background-size: 100% 100% !important;background-repeat: no-repeat !important;background-position: center !important;}
					<?php elseif ( get_option($this->plugin_slug.'_custom_background_image_position') == 'propstretch' ) : ?>
						body {background-size: contain !important;background-repeat: no-repeat !important;background-position: center !important;}
					<?php endif ?>
					<?php
				}
				//custom background color if applicable
				if(get_option($this->plugin_slug.'_background_color', '') != '') { ?>
                	body {background-color: <?php echo get_option($this->plugin_slug.'_background_color') ?> !important;}
					<?php
				}
				//use custom css if there is any
				if(get_option($this->plugin_slug.'_custom_css')) { ?>
					<?php echo get_option($this->plugin_slug.'_custom_css') ?>
					<?php
				}
				echo "</style></head>"; //End custom CSS set via plugin setting page


				//get the login page template
				$template_slug = get_option($this->plugin_slug . "_current_theme", "hmsdefault");
				if (  (locate_template( $template_slug.'.php' ))  ) {
				// if (  (locate_template( $template_slug.'.php' )) and (get_option($this->plugin_slug . '_ihmsa','') == 'hmsia')  ) { //template override via theme file
					include( locate_template( $template_slug.'.php' ) );
				} else { //not overriden, use plugin template
					include ('templates/'.$template_slug.'.php');
				}
				echo $this->get_preview_alert() . "</html>"; //end html
				exit;
		}
    }

    public function add_plugin_page(){
        // This page will be under "Settings"
		add_options_page('Settings Admin', $this->plugin_label, 'manage_options', $this->plugin_slug.'-setting-admin', array($this, 'create_admin_page'));
    }

    public function print_section_info(){ //section summary info goes here
		//print 'This is the where you set the password for your site.';
    }

    public function get_donate_button(){ ?>
	<style type="text/css">
	.mt_donate_wrap {position:relative;}
	.motechdonate{border: 1px solid #DADADA; background:white; font-family: tahoma,arial,helvetica,sans-serif;font-size: 12px;overflow: hidden;padding: 5px;position: absolute;right: 0;text-align: center;top: 0;width: 160px; box-shadow:0px 0px 8px rgba(153, 153, 153, 0.81);}
	.motechdonate form{display:block;}
	</style>
    <div class="motechdonate">
        <div style="overflow: hidden; width: 161px; text-align: center;">
        	<form action="https://shenghuo.alipay.com/send/payment/fill.htm" method="post" target="_blank">
                <input name="optEmail" type="hidden" value="arvinxiang@qq.com">
                <input id="payAmount" name="payAmount" type="hidden" value="50">
                <input type="image" src="<?php echo plugins_url( 'images/alipay.png' , __FILE__ ); ?>" width="100%" onclick="this.submit();">
            </form>
        	如果您觉得这个插件对你有帮助，请使用支付宝客户端，扫描二维码，捐赠**元，^_^，谢谢！
        </div>
	</div>

    <?php

    }

    public function create_admin_page(){
        ?>
		<div class="wrap" style="position:relative">
		    <?php screen_icon(); ?>
		    <h2 class="aplabel"><?php echo $this->plugin_label ?></h2>
            <div class="mt_donate_wrap">
            	<?php if (get_option($this->plugin_slug . '_ihmsa','') != 'hmsia') : ?>
                <?php $this->get_donate_button(); ?>
                <?php endif ?>
            </div>
		    <form method="post" action="options.php" class="<?php echo $this->plugin_slug ?>_form">
		        <?php
	            // This prints out all hidden setting fields
			    settings_fields($this->plugin_slug.'_option_group');
			    do_settings_sections($this->plugin_slug.'-setting-admin');
				?>
		        <?php submit_button(); ?>
		    </form>
		</div>
	<?php
    }

    public function page_init(){

        add_settings_section(
	    $this->plugin_slug.'_setting_section',
	    '设置',
	    array($this, 'print_section_info'),
	    $this->plugin_slug.'-setting-admin'
		);

		//add text input field
		$field_slug = "plk";
		$field_label = "Premium License Key";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		if( is_plugin_active( 'expansion-hide-my-site/index.php' ) ) {
			$enterprompt = "<a href='" . get_bloginfo( "wpurl" ) . "/wp-admin/options-general.php?page=wp_privacy_premium_expansion-setting-admin'>Enter your license key</a> to unlock premium features. <a href='javascript:void(0)' class='hms_get_premium'>Get Premium »</a>";
		} else {
			$enterprompt = "Enter your license key to unlock premium features. <a href='javascript:void(0)' class='hms_get_premium'>Get Premium »</a>";
		}
		$enterprompt .= "<br><a href='javascript:void(0)' class='how_to_redeem'>How To Redeem Your License Key</a><div class='redeem_info'><ol><li>Download the <a href='http://www.justinsaad.com/pau/expansion-hide-my-site/expansion-hide-my-site.zip'>密码保护 Premium Expansion plugin file</a> in zip format</li><li>Upload the zip file via WordPress plugin uploader (in your <strong>WordPress admin > Plugins > Add New > Upload</strong>) and activate it</li><li>Enter your license key in your <strong>WordPress admin > Settings > 密码保护 Premium Expansion > Premium License Key</strong></li></ol></div>";
		if (get_option($this->plugin_slug . '_ihmsa','') == 'hmsia') {
			$desc = "<div class='mvalid'>Valid</div>";
		} else {
			$desc = $enterprompt;
		}
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_text_input'), //callback function for text input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"class" => "hmshidden",
				"desc" => $desc, //description of the field (optional)
			)
		);

		//add checkbox field
		$field_slug = "enabled";
		$field_label = "启用密码保护";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_checkbox'), //callback function for checkbox
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '勾选后，启用密码保护功能。', //description of the field (optional)
				"default" => '1' //sets the default field value (optional), when grabbing this option value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet

			)
		);

		//add text input field
		$field_slug = "password";
		$field_label = "设置你的密码";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_text_input'), //callback function for text input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '设置你的密码，只有知道密码的访客才能访问你的网站内容。', //description of the field (optional)
			)
		);

		//add text input field
		$field_slug = "password_hint";
		$field_label = "密码提示（可选项）";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_text_input'), //callback function for text input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"maxlength" => 53, //set max length (optional)
				"desc" => '设置一个密码提示问题。比如：我的生日？', //description of the field (optional)
			)
		);

		//add text input field
		$field_slug = "duration";
		$field_label = "有效期";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_text_input'), //callback function for text input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '多长时间后需要重新输入密码，设置为0则在用户关闭浏览器后失效。', //description of the field (optional)
				"default" => '1' //sets the default field value (optional), when grabbing this option value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet
			)
		);

		//add checkbox field
		$field_slug = "bruteforce";
		$field_label = "暴力破解保护";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_checkbox'), //callback function for checkbox
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '勾选后，如果多次尝试密码失败，则需要间隔一段时间后才能尝试。', //description of the field (optional)
				"default" => '1' //sets the default field value (optional), when grabbing this option value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet

			)
		);

		//add a select input field
		$field_slug = "custom_messaging_banner";
		$field_label = "提示信息";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		$this->back_options = array(
								array("label" => "不显示", "value" => ""),
								array("label" => "网站正在开发中，请输入密码后访问。", "value" => "网站正在开发中，请输入密码后访问。"),
								array("label" => "这是一个密码保护网站，你必须输入密码后才能访问。", "value" => "这是一个密码保护网站，你必须输入密码后才能访问。"),
		);
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
			$field_id,
			$field_label,
			array($this, 'create_a_select_input'), //callback function for select input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends select field id to callback
				"default" => '', //sets the default field value (optional), when grabbing this field value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet
				"desc" => '选择一个提示信息。', //description of the field (optional)
				"meta" => 'style="max-width:450px;"',
				"select_options" => $this->back_options //sets select option data
			)
		);

		//add a textarea input field
		$field_slug = "custom_messaging_banner_override";
		$field_label = "自定义提示信息" . $this->get_premium_warning();
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id, array($this, 'po'));
		add_settings_field(
			$field_id,
			$field_label,
			array($this, 'create_a_textarea_input'), //callback function for select input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '输入自定义提示信息。', //description of the field (optional)
				"placeholder" => '这是一个密码保护网站，如需密码，请邮件 arvin@reqianduan.com' //sets the field placeholder which appears when the field is empty (optional)
			)
		);

		//add text input field
		$field_slug = "pagetitle";
		$field_label = "登陆页title";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_text_input'), //callback function for text input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '作为页面的title', //description of the field (optional)
				"placeholder" => '密码保护网站',
				"default" => '密码保护网站' //sets the default field value (optional), when grabbing this option value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet
			)
		);

		//add checkbox field
		$field_slug = "allow_admin";
		$field_label = "允许管理员";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_checkbox'), //callback function for checkbox
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '勾选后，为管理员放行。', //description of the field (optional)
				"default" => '0' //sets the default field value (optional), when grabbing this option value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet

			)
		);

		//add checkbox field
		$field_slug = "public_rss";
		$field_label = "允许RSS";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_checkbox'), //callback function for checkbox
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '勾选后，为RSS放行。', //description of the field (optional)
				"default" => '0' //sets the default field value (optional), when grabbing this option value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet

			)
		);

		//add text input field
		$field_slug = "prev";
		$field_label = "预览";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id);
		$desc = "<a href='" . get_bloginfo( "wpurl" ) . "?hmspreview=true' target='_blank'>预览页面 &raquo;</a><br>以访客身份预览页面。";
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_text_input'), //callback function for text input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"class" => "",
				"desc" => $desc, //description of the field (optional)
			)
		);

        add_settings_section(
	    $this->plugin_slug.'_setting_section_displayoptions',
	    '<br>外观选项',
	    array($this, 'get_image_picker'), //get image picker code via callback
	    $this->plugin_slug.'-setting-admin'
		);

		//add an image select input field
		$field_slug = "current_theme";
		$field_label = "";
		$field_id = $this->plugin_slug.'_'.$field_slug;
		$this->current_theme_options = array(
								array("label" => "Cobalt", "value" => "hmscobalt"),
								array("label" => "Ice", "value" => "hmsice"),
								array("label" => "Lock and Key", "value" => "hmslockandkey"),
								array("label" => "Binder", "value" => "hmsbinder"),
								array("label" => "Iris", "value" => "hmsiris"),
								array("label" => "Discreet", "value" => "hmsdiscreet"),
								array("label" => "Classic", "value" => "hmsclassic"),
								array("label" => "default", "value" => "hmsdefault"),
		);
		register_setting($this->plugin_slug.'_option_group', $field_id, array($this, 'po_theme'));
		add_settings_field(
			$field_id,
			$field_label,
			array($this, 'create_a_select_input'), //callback function for select input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends select field id to callback
				"default" => 'hmsdefault', //sets the default field value (optional), when grabbing this field value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet
				"select_options" => $this->current_theme_options //sets select option data
			)
		);

		//add image upload field
		$field_slug = "custom_background_image_upload";
		$field_label = "自定义背景图片（可选项）" . $this->get_premium_warning();
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id, array($this, 'po'));
		add_settings_field(
		  $field_id,            // ID of the option
		  $field_label,                      // Title of the option
		  array($this, 'create_image_upload'),  // Callback used to render the input field
		  $this->plugin_slug.'-setting-admin',               // Page to associate this option with
		  $this->plugin_slug.'_setting_section_displayoptions',       // Section to associate this option with
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '输入图片地址或者上传新图片，如果留空，会使用默认值。', //description of the field (optional)
			)
		);

		//add an image select input field
		$field_slug = "custom_background_image_position";
		$field_label = "自定义背景图片位置（可选项）" . $this->get_premium_warning();
		$field_id = $this->plugin_slug.'_'.$field_slug;
		$this->back_options = array(
								array("label" => "选择...", "value" => ""),
								array("label" => "平铺", "value" => "repeat"),
								array("label" => "填充", "value" => "croptofit"),
								array("label" => "拉伸", "value" => "stretch"),
								array("label" => "适应", "value" => "propstretch"),
		);
		register_setting($this->plugin_slug.'_option_group', $field_id, array($this, 'po'));
		add_settings_field(
			$field_id,
			$field_label,
			array($this, 'create_a_select_input'), //callback function for select input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section_displayoptions',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends select field id to callback
				"default" => '', //sets the default field value (optional), when grabbing this field value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet
				"desc" => '选择图片位置，如果留空，会使用默认值。', //description of the field (optional)
				"select_options" => $this->back_options //sets select option data
			)
		);

		//add color picker text input field
		$field_slug = "background_color";
		$field_label = "自定义背景色（可选项）" . $this->get_premium_warning();
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id, array($this, 'po'));
		add_settings_field(
		    $field_id,
		    $field_label,
		    array($this, 'create_a_text_input'), //callback function for text input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section_displayoptions',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '选择一个背景色，如果留空，会使用默认值。', //description of the field (optional)
				"default" => '', //sets the default field value (optional), when grabbing this option value later on remember to use get_option(option_name, default_value) so it will return default value if no value exists yet
				"class" => "motech-color-field" //designate this as color field. remember to uncomment js enqueue in class construct
			)
		);

		//add textarea input field
		$field_slug = "custom_css";
		$field_label = "自定义CSS（可选项）" . $this->get_premium_warning();
		$field_id = $this->plugin_slug.'_'.$field_slug;
		register_setting($this->plugin_slug.'_option_group', $field_id, array($this, 'po'));
		add_settings_field(
			$field_id,
			$field_label,
			array($this, 'create_a_textarea_input'), //callback function for textarea input
		    $this->plugin_slug.'-setting-admin',
		    $this->plugin_slug.'_setting_section_displayoptions',
		    array(								// The array of arguments to pass to the callback.
				"id" => $field_id, //sends field id to callback
				"desc" => '输入自定义CSS代码，如果你不知道这是什么，请留空。', //description of the field (optional)
				"placeholder" => '' //sets the field placeholder which appears when the field is empty (optional)
			)
		);

    }  //end page_init

	function po($input) {
		return $input;
		if (get_option($this->plugin_slug . '_ihmsa','') == 'hmsia') {
			return $input;
		}
		if (!empty($input)) {
			add_settings_error('plk_error_id8',esc_attr('settings_updated_8'),__('A premium option was not saved. You must first enter your license key to unlock this premium feature.'),'error');
		}
	}

	function po_theme($input) {
		return $input;
		if (get_option($this->plugin_slug . '_ihmsa','') == 'hmsia') {
			return $input;
		} else {
			if ($input != 'hmsdefault') {
				add_settings_error('plk_error_id8',esc_attr('settings_updated_8'),__('A premium option was not saved. You must first enter your license key to unlock this premium feature.'),'error');
			}
			return 'hmsdefault';
		}
	}

	function get_premium_warning() {
		return '';
		if (get_option($this->plugin_slug . '_ihmsa','') == 'hmsia') {
			return '';
		} else {
			return '<span class="motech_premium_only"> (Premium Only)</span>';
		}
	}


	//the image picker code
    public function get_image_picker(){
		?>
        	<strong style="display:block;font-size:14px;margin-bottom:5px;">选择一个模板<?php echo $this->get_premium_warning() ?></strong>
            <div class="motech_image_picker" selectid="<?php echo $this->plugin_slug ?>_current_theme"><?php /*?>put id of select field here<?php */?>
            <?php $options = $this->current_theme_options ?>
            <?php foreach ($options as $option) : ?>
            	<div class="motech_image_picker_wrap <?php if ( $option["value"] == get_option($this->plugin_slug . "_current_theme", "hmsdefault") ) echo "current"; ?>">
        			<img src="<?php echo plugins_url( 'images/'.$option["value"].'_screenshot.jpg' , __FILE__ ) ?>" alt="<?php echo $option["value"] ?>" />
                    <div><?php echo $option["label"] ?></div>
                </div>
            <?php endforeach ?>
            </div>
        <?php
    }

	function motech_imagepicker_admin_css() {
		if (isset($_GET['page']) && $_GET['page'] == $this->plugin_slug.'-setting-admin') { //if we are on our admin page
			?>
            <style>
				.hmshidden {display:none;}
				#wpbody h3 {font-size:20px;}
				#wp_privacy_current_theme {display:none;}
				div.updated.success {background-color: rgb(169, 252, 169);border-color: rgb(85, 151, 85);}
				.mvalid {background-color: rgb(169, 252, 169);border-color: rgb(85, 151, 85);width: 127px;font-weight: bold;padding-left: 10px;border: solid 1px rgb(85, 151, 85);border-radius: 3px;}
				.motech_premium_only {color:red;}
				#green_ribbon_top {position:relative;z-index:2;}
				#green_ribbon_left {background:url(<?php echo $this->plugin_dir ?>/images/green_ribbon_left.png) no-repeat -11px 0px;width: 80px;height: 60px;float: left;}
				#green_ribbon_right {background:url(<?php echo $this->plugin_dir ?>/images/green_ribbon_right.png) no-repeat;width: 80px;height: 60px;position: absolute;top: 0px;right: -10px;}
				#green_ribbon_base {background:url(<?php echo $this->plugin_dir ?>/images/green_ribbon_base.png) repeat-x;height: 60px;margin-left: 49px;margin-right: 70px;}
				#green_ribbon_base span {display: inline-block;color: white;position: relative;top: 11px;height: 35px; line-height:33px;font-size: 17px;font-weight: bold;font-style: italic;text-shadow: 1px 3px 2px #597c2a;}
				#hms_get_premium {background: rgb(58, 80, 27);background: rgba(58, 80, 27, 0.73);cursor:pointer;padding: 0px 12px;margin-left: -17px;font-style: normal !important;margin-right: 12px;text-shadow: 1px 3px 2px #364C18 !important;}
				#hms_get_premium:hover {background:rgb(30, 43, 12);background:rgba(30, 43, 12, 0.73);text-shadow: 1px 3px 2px #21310B !important;}
				.motech_premium_box {background:url(<?php echo $this->plugin_dir ?>/images/premium_back.png); margin-left: 49px;padding-top: 29px;padding-bottom:36px;margin-right: 70px;position:relative;top:-16px;display:none;}
				.motech_premium_box_wrap {margin-left:20px; margin-right:20px;}
				.motech_premium_box h2 {text-align: center;color: #585858;font-size: 36px;text-shadow: 1px 3px 2px #acabab;}
				.motech_premium_box .updated {margin-bottom: 20px !important;margin-top: 29px !important;}
				.motech_premium_box button {background: none;border: none; position:relative;cursor: pointer;overflow: visible;}
				.motech_purchase_button .purchase_graphic {background:url(<?php echo $this->plugin_dir ?>/images/buy_sprite.png) no-repeat;height: 100px;width: 101px;background-position: -17px -24px;color: white;font-size: 22px;padding: 20px 42px;padding-top: 57px;text-shadow: 1px 1px 7px black;position: absolute;top: -80px;left: -80px;line-height:normal;font-family: 'Open Sans', sans-serif;}
				.redeem_info{margin-top:20px;display:none;}
				.motech_purchase_button.unlimited_use .purchase_graphic {width: 115px;padding: 21px 36px;padding-top: 57px;}
				.motech_purchase_button.unlimited_use .purchase_graphic span {font-weight:bold;}
				.motech_purchase_button .purchase_bubble {background: white;border-radius: 9px;width: 350px;height: 123px;margin-bottom: 5px;-webkit-transition: all .2s ease-out;  -moz-transition: all .2s ease-out;-o-transition: all .2s ease-out;transition: all .2s ease-out;}
				.motech_purchase_button:hover .purchase_bubble {  background-color: #99dcf8;box-shadow:2px 3px 2px rgba(0, 0, 0, 0.31);}
				.motech_purchase_button.three_use:hover .purchase_bubble {  background-color: #96f5e4;}
				.motech_purchase_button.unlimited_use:hover .purchase_bubble {  background-color: #f8c4c6;}
				.motech_purchase_buttons {padding-top:90px;text-align:center;}
				.motech_purchase_button {display:inline-block;margin-right: 100px;vertical-align:top;}
				.motech_purchase_button .purchase_price {font-size: 60px;color: #585858;line-height:normal;}
				.motech_purchase_button:last-child {margin-right:0px;}
				.motech_purchase_button.three_use .purchase_graphic {background-position: -208px -24px;}
				.motech_purchase_button.unlimited_use .purchase_graphic {background-position: -397px -24px;}
				.motech_premium_cancel {color:#626262;text-align:center;font-size:22px;margin-top:43px;}
				.motech_premium_cancel span:hover {cursor:pointer;text-decoration:underline;}
				.<?php echo $this->plugin_slug ?>_form > .form-table {max-width:770px;}


				/*css for the image picker*/
				.motech_image_picker img {box-shadow: 0px 0px 0px 2px rgba(0, 0, 0, 0.2);}
				.motech_image_picker_wrap:hover img, .motech_image_picker_wrap:focus img {box-shadow: 0px 0px 0px 2px rgba(0, 0, 0, 0.5);}
				.motech_image_picker_wrap.current img, .motech_image_picker_wrap:active img {box-shadow: 0px 0px 0px 2px rgba(0, 0, 0, 1);}
				.motech_image_picker_wrap {display:inline-block;cursor: pointer;margin-right:20px;margin-bottom: 30px;}
				.motech_image_picker_wrap div {font-weight:bold;font-size:16px;margin-top:10px;color:rgba(0, 0, 0, 0.47);}

				/* Begin Responsive
				====================================================================== */
				@media only screen and (max-width: 1700px) {
					.motech_purchase_button .purchase_price {font-size: 42px;padding-top: 18px;}
					.motech_purchase_button .purchase_bubble {width: 252px;}
				}
				@media only screen and (max-width: 1535px) {
					.motech_purchase_button .purchase_bubble {width: 131px;padding-top: 69px;}
					.motech_purchase_button .purchase_graphic {left: -23px;}
					.motech_purchase_button {margin-right:70px;}
				}
				@media only screen and (max-width: 1025px) {
					.hms_get_premium_meta {display:none !important;}
				}
				@media only screen and (max-width: 980px) {
					.motech_purchase_button {display:block;margin-bottom: 80px;margin-right:0px;}
				}
				@media only screen and (max-width: 445px) {
					.motech_premium_box h2 {font-size:22px;}
				}
				@media only screen and (max-width: 380px) {
					#green_ribbon_base span {font-size: 12px;}
					#hms_get_premium {margin-right:0px;}
				}
				@media only screen and (max-width: 330px) {
					.motech_purchase_button {
						margin-left: -9px;
					}
			</style>

            <!--[if lt IE 9]>
                <style>
                    .motech_image_picker_wrap.current img, .motech_image_picker_wrap:active img {
                    	border: 4px solid rgb(0, 0, 255);
                        margin:-4px;
                    }
                    .motech_purchase_button {
                        display: block;
                        padding-bottom: 70px;
                        margin-right: 0px;
                    }
                    .motech_purchase_button.unlimited_use {
                    	padding-bottom: 0px;
                    }
                    .hms_get_premium_meta {display:none !important;}
                </style>
            <![endif]-->
            <?php
		}
	}

	function motech_imagepicker_admin_jquery() {
		if (isset($_GET['page']) && $_GET['page'] == $this->plugin_slug.'-setting-admin') { //if we are on our admin page
			?>
				<script>
					jQuery(function() {

						//jquery for color picker
						jQuery('tr.motech-color-field').removeClass('motech-color-field');

						//jquery for image picker
						jQuery(".motech_image_picker_wrap").click(function(){
							jQuery(this).closest(".motech_image_picker").find(".motech_image_picker_wrap").removeClass("current");
							jQuery(this).addClass("current");
							selectedvalue = jQuery(this).find("img").attr("alt");
							jQuery("#<?php echo $this->plugin_slug ?>_current_theme").val(selectedvalue);
						});
						jQuery("#<?php echo $this->plugin_slug ?>_current_theme").parent().parent().hide();
						<?php if (get_option($this->plugin_slug . '_ihmsa','') == 'hmsia') : ?>
							<?php
								if(get_option('wp_privacy_premium_expansion_plk','') != '') {
									$useval = get_option('wp_privacy_premium_expansion_plk','');
								} elseif(get_option($this->plugin_slug . '_plk','') != '') {
									$useval = get_option('wp_privacy_premium_expansion_plk','');
								}
							?>
							useval = '<?php echo $useval ?>';
							jQuery("#wp_privacy_plk").replaceWith("<div>"+useval+"</div>");
						<?php else : ?>
							jQuery("#wp_privacy_plk").replaceWith("<div></div>");
						<?php endif ?>

						jQuery("#hms_get_premium, .motech_premium_cancel span").click(function(){
							jQuery(".motech_premium_box").slideToggle(200);
						});
						jQuery(".how_to_redeem").click(function(){
							jQuery(".redeem_info").slideToggle(200);
						});
						jQuery(".hms_get_premium").click(function(){
							jQuery("html, body").animate({ scrollTop: 0 }, 300, function() {
    							// Animation complete.
								jQuery(".motech_premium_box").slideDown(200);
  							});
						});


					});
				</script>
            <?php
		}
	}


	/**
	 * This following set of functions handle all input field creation
	 *
	 */
	function create_image_upload($args) {
		?>
			<?php
			//set default value if applicable
            if(isset($args["default"])) {
                $default = $args["default"];
            } else {
                $default = false;
            }
            ?>
            <input class="motech_upload_image" type="text" size="36" name="<?php echo $args["id"] ?>" value="<?php echo get_option($args["id"], $default) ?>" />
            <input class="motech_upload_image_button" class="button" type="button" value="上传图片" />
        	<br />
			<?php
			if(isset($args["desc"])) {
				echo "<span class='description'>".$args["desc"]."</span>";
			} else {
				echo "<span class='description'>Enter a URL or upload an image.</span>";
			}
			?>
            <?php
				$current_image = get_option($args["id"],$default);
				if(!empty($current_image)) {
					echo "<img style='max-width: 50%; max-height: 400px;' src='".$current_image."'>";
				}
			?>
        <?php
	} // end create_image_upload

	function create_a_checkbox($args) {
		$html = '<input type="checkbox" id="'  . $args["id"] . '" name="'  . $args["id"] . '" value="1" ' . checked(1, get_option($args["id"], $args["default"]), false) . '/>';

		// Here, we will take the desc argument of the array and add it to a label next to the checkbox
		$html .= '<label for="'  . $args["id"] . '"> '  . $args["desc"] . '</label>';

		echo $html;

	} // end create_a_checkbox

	function create_a_text_input($args) {
		//grab placeholder if there is one
		if(isset($args["placeholder"])) {
			$placeholder_html = "placeholder=\"".$args["placeholder"]."\"";
		}	else {
			$placeholder_html = "";
		}
		//grab maxlength if there is one
		if(isset($args["maxlength"])) {
			$max_length_html = "maxlength=\"".$args["maxlength"]."\"";
		}	else {
			$max_length_html = "";
		}
		if(isset($args["default"])) {
			$default = $args["default"];
		} else {
			$default = false;
		}
		// Render the output
		echo '<input type="text" '  . $placeholder_html . $max_length_html . ' id="'  . $args["id"] . '" class="' . $args["class"]. '" name="'  . $args["id"] . '" value="' . get_option($args["id"], $default) . '" />';
		if($args["desc"]) {
			echo "<p class='description'>".$args["desc"]."</p>";
		}


	} // end create_a_text_input

	function create_a_textarea_input($args) {
		//grab placeholder if there is one
		if($args["placeholder"]) {
			$placeholder_html = "placeholder=\"".$args["placeholder"]."\"";
		}	else {
			$placeholder_html = "";
		}
		//get default value if there is one
		if(isset($args["default"])) {
			$default = $args["default"];
		} else {
			$default = false;
		}
		// Render the output
		echo '<textarea '  . $placeholder_html . ' id="'  . $args["id"] . '"  name="'  . $args["id"] . '" rows="5" cols="50">' . get_option($args["id"], $default) . '</textarea>';
		if($args["desc"]) {
			echo "<p class='description'>".$args["desc"]."</p>";
		}
	}

	function create_a_radio_input($args) {

		$radio_options = $args["radio_options"];
		$html = "";
		if($args["desc"]) {
			$html .= $args["desc"] . "<br>";
		}
		//get default value if there is one
		if(isset($args["default"])) {
			$default = $args["default"];
		} else {
			$default = false;
		}
		foreach($radio_options as $radio_option) {
			$html .= '<input type="radio" id="'  . $args["id"] . '_' . $radio_option["value"] . '" name="'  . $args["id"] . '" value="'.$radio_option["value"].'" ' . checked($radio_option["value"], get_option($args['id'], $default), false) . '/>';
			$html .= '<label for="'  . $args["id"] . '_' . $radio_option["value"] . '"> '.$radio_option["label"].'</label><br>';
		}

		echo $html;

	} // end create_a_radio_input callback

	function create_a_select_input($args) {

		$select_options = $args["select_options"];
		$html = "";
		//get default value if there is one
		if(isset($args["default"])) {
			$default = $args["default"];
		} else {
			$default = false;
		}
		if(isset($args["meta"])) {
			$meta = $args["meta"];
		} else {
			$meta = "";
		}
		$html .= '<select id="'  . $args["id"] . '" name="'  . $args["id"] . '" ' . $meta . '" >';
			foreach($select_options as $select_option) {
				$html .= '<option value="'.$select_option["value"].'" ' . selected( $select_option["value"], get_option($args["id"], $default), false) . '>'.$select_option["label"].'</option>';
			}
		$html .= '</select>';
		if($args["desc"]) {
			$html .= "<p class='description'>".$args["desc"]."</p>";
		}
		echo $html;

	} // end create_a_select_input callback


	/**
	 * Add admin notices logic
	 */

	public function admin_notices() {
		global $current_user;
		$userid = $current_user->ID;
		global $pagenow;

		// This notice will only be shown if no data entered for required input
		//check input field based on field slug
		$field_slug = "password";
		//check if plugin is enabled
		if((!(get_option($this->plugin_slug.'_'.$field_slug)) AND (get_option($this->plugin_slug.'_enabled', 1, false) == 1) )) {
			echo '
				<div class="updated">
					<p>'.$this->plugin_label.' <strong>已启用。</strong> 开始使用前，你必须 <a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page='.$this->plugin_slug.'-setting-admin">设置你的密码</a>。</p>
				</div>';
		}


	}

	//add plugin action links logic
	function add_plugin_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/options-general.php?page='.$this->plugin_slug.'-setting-admin">设置</a>'
			),
			$links
		);

	}

	public function plugin_row_links($links, $file) {
		$plugin = plugin_basename(__FILE__);
		if ($file == $plugin) // only for this plugin
				return array_merge( $links,
			array( '<a target="_blank" href="http://weibo.com/reqianduan">' . __('关注微博') . '</a>' )
		);
		return $links;
	}




} //end plugin class

//load the plugin
$custom_plugin = new $plugin_slug();