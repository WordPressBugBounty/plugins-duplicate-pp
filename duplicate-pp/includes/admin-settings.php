<?php
if (!defined('ABSPATH')) {
    exit;
}

class DPP_Admin_Settings {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action( 'admin_enqueue_scripts', [ $this, 'dpp_admin_page_assets' ] );
        add_action( 'admin_init', [ $this, 'duplicate_pp_dci_plugin' ] );
    }

    public function add_admin_menu() {
        add_options_page(
            __('Duplicate PP Settings', 'duplicate-pp'),
            __('Duplicate PP', 'duplicate-pp'),
            'manage_options',
            'duplicate-pp-settings',
            array($this, 'settings_page')
        );
    }

    // Admin Assets
    public function dpp_admin_page_assets($screen) {
        if( 'settings_page_duplicate-pp-settings' == $screen ) {
            wp_enqueue_style( 'admin-asset', DPP_PLUGIN_URL . 'assets/css/admin.css', array(), '1.0.0', 'all' );
        }
    }

    public function register_settings() {
        register_setting('dpp_settings', 'dpp_settings', array($this, 'sanitize_settings'));

        add_settings_section(
            'dpp_general_section',
            __('General Settings', 'duplicate-pp'),
            array($this, 'section_description'),
            'duplicate-pp-settings'
        );

        // Post Status
        add_settings_field(
            'post_status',
            __('Default Post Status', 'duplicate-pp'),
            array($this, 'post_status_field'),
            'duplicate-pp-settings',
            'dpp_general_section'
        );

        // Title Settings
        add_settings_field(
            'title_prefix',
            __('Title Prefix', 'duplicate-pp'),
            array($this, 'text_field'),
            'duplicate-pp-settings',
            'dpp_general_section',
            array('field' => 'title_prefix')
        );

        add_settings_field(
            'title_suffix',
            __('Title Suffix', 'duplicate-pp'),
            array($this, 'text_field'),
            'duplicate-pp-settings',
            'dpp_general_section',
            array('field' => 'title_suffix')
        );

        // Slug Settings
        add_settings_field(
            'slug_prefix',
            __('Slug Prefix', 'duplicate-pp'),
            array($this, 'text_field'),
            'duplicate-pp-settings',
            'dpp_general_section',
            array('field' => 'slug_prefix')
        );

        add_settings_field(
            'slug_suffix',
            __('Slug Suffix', 'duplicate-pp'),
            array($this, 'text_field'),
            'duplicate-pp-settings',
            'dpp_general_section',
            array('field' => 'slug_suffix')
        );
    }

    public function section_description() {
        echo '<p>' . esc_html__('Configure how duplicated posts should be handled.', 'duplicate-pp') . '</p>';
    }

    public function post_status_field() {
        $options = get_option('dpp_settings');
        $status = isset($options['post_status']) ? $options['post_status'] : 'draft';
        ?>
        <select name="dpp_settings[post_status]">
            <option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Draft', 'duplicate-pp'); ?></option>
            <option value="publish" <?php selected($status, 'publish'); ?>><?php esc_html_e('Published', 'duplicate-pp'); ?></option>
            <option value="private" <?php selected($status, 'private'); ?>><?php esc_html_e('Private', 'duplicate-pp'); ?></option>
            <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pending', 'duplicate-pp'); ?></option>
        </select>
        <?php
    }

    public function text_field($args) {
        $options = get_option('dpp_settings');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        ?>
        <input type="text" 
               name="dpp_settings[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['post_status'] = isset($input['post_status']) 
            ? sanitize_key($input['post_status']) 
            : 'draft';

        $sanitized['title_prefix'] = isset($input['title_prefix'])
            ? sanitize_text_field($input['title_prefix'])
            : '';

        $sanitized['title_suffix'] = isset($input['title_suffix'])
            ? sanitize_text_field($input['title_suffix'])
            : ' (Copy)';

        $sanitized['slug_prefix'] = isset($input['slug_prefix'])
            ? sanitize_text_field($input['slug_prefix'])
            : '';

        $sanitized['slug_suffix'] = isset($input['slug_suffix'])
            ? sanitize_text_field($input['slug_suffix'])
            : '-copy';

        return $sanitized;
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'duplicate-pp'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="#" class="nav-tab nav-tab-active" data-tab="dpp-promotions"><?php esc_html_e('Welcome', 'duplicate-pp'); ?></a>
                <a href="#" class="nav-tab" data-tab="dpp-settings"><?php esc_html_e('Settings', 'duplicate-pp'); ?></a>
            </h2>

            <div id="dpp-promotions" class="tab-content active">
                <div class="admin_page_container">
                    <div class="plugin_head">
                        <div class="head_container">
                            <h1 class="plugin_title"> <?php echo esc_html("Duplicate PP", "duplicate-pp"); ?> </h1>
                            <h4 class="plugin_subtitle"><?php echo esc_html("A Light-weight Plugin to Duplicate Any Post Type", "duplicate-pp"); ?></h4>
                            <div class="support_btn">
                                <a href="https://gutenbergkits.com/contact" target="_blank" rel="nofollow noreferrer" style="background: #D37F00"><?php echo esc_html("Get Support", "duplicate-pp"); ?></a>
                                <a href="https://wordpress.org/plugins/duplicate-pp/#reviews" target="_blank" rel="nofollow noreferrer" style="background: #0174A2"><?php echo esc_html("Rate Plugin", "duplicate-pp"); ?></a>
                            </div>
                        </div>
                    </div>
                    <div class="plugin_body">
                        <div class="doc_video_area">
                            <div class="doc_video">
                            <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . '../img/dpp.jpg'); ?>">
                            </div>
                        </div>
                        <div class="support_area">
                            <div class="single_support">
                                <h4 class="support_title"><?php echo esc_html("Freelance Work", "duplicate-pp"); ?></h4>
                                <div class="support_btn">
                                    <a href="https://www.fiverr.com/users/devs_zak/" target="_blank" rel="nofollow noreferrer" style="background: #1DBF73"><?php echo esc_html("@Fiverr", "duplicate-pp"); ?></a>
                                    <a href="https://www.upwork.com/freelancers/~010af183b3205dc627" target="_blank" rel="nofollow noreferrer" style="background: #14A800"><?php echo esc_html("@UpWork", "duplicate-pp"); ?></a>
                                </div>
                            </div>
                            <div class="single_support">
                                <h4 class="support_title"><?php echo esc_html("Get Support", "duplicate-pp"); ?></h4>
                                <div class="support_btn">
                                    <a href="https://gutenebrgkits.com/contact" target="_blank" rel="nofollow noreferrer" style="background: #002B42"><?php echo esc_html("Contact", "duplicate-pp"); ?></a>
                                    <a href="mailto:info@gutenbergkits.com" style="background: #EA4335"><?php echo esc_html("Send Mail", "duplicate-pp"); ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="dpp-settings" class="tab-content">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('dpp_settings');
                    do_settings_sections('duplicate-pp-settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>

        <style>
            .tab-content {
                display: none;
            }
            .tab-content.active {
                display: block;
            }
            .nav-tab-active {
                background: #ffffff;
                color: #000000;
            }
        </style>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const tabs = document.querySelectorAll(".nav-tab");
                const contents = document.querySelectorAll(".tab-content");

                tabs.forEach(tab => {
                    tab.addEventListener("click", function(event) {
                        event.preventDefault();
                        
                        // Remove active class from all tabs and contents
                        tabs.forEach(t => t.classList.remove("nav-tab-active"));
                        contents.forEach(c => c.classList.remove("active"));

                        // Activate selected tab and corresponding content
                        this.classList.add("nav-tab-active");
                        document.getElementById(this.dataset.tab).classList.add("active");
                    });
                });

                // Show the first tab by default
                document.getElementById("dpp-promotions").classList.add("active");
            });
        </script>
        <?php
    }

    /**
     * SDK Integration
     */
    public function duplicate_pp_dci_plugin() {

        // Include DCI SDK.
        require_once dirname( __FILE__ ) . '/dci/start.php';
        wp_register_style('dci-sdk-duplicate-pp', plugins_url('dci/assets/css/dci.css', __FILE__), array(), '1.2.1', 'all');
        wp_enqueue_style('dci-sdk-duplicate-pp');

        dci_dynamic_init( array(
            'sdk_version'   => '1.2.1',
            'product_id'    => 4,
            'plugin_name'   => 'Duplicate PP', // make simple, must not empty
            'plugin_title'  => 'Love using Duplicate PP? Congrats 🎉  ( Never miss an Important Update )', // You can describe your plugin title here
            'api_endpoint'  => 'https://dashboard.codedivo.com/wp-json/dci/v1/data-insights',
            'slug'          => 'duplicate-pp', // folder-name or write 'no-need' if you don't want to use
            'core_file'     => false,
            'plugin_deactivate_id' => false,
            'menu'          => array(
                'slug' => 'duplicate-pp-settings',
            ),
            'public_key'    => 'pk_n3DWcvdznkO3xdLlV3WWhFVu4EnywrOK',
            'is_premium'    => false,
            'popup_notice'  => false,
            'deactivate_feedback' => false,
            'text_domain'  => 'duplicate-pp',
            'plugin_msg'   => '<p>Be Top-contributor by sharing non-sensitive plugin data and create an impact to the global WordPress community today! You can receive valuable emails periodically.</p>',
        ) );

    }
              
}

// Initialize settings
DPP_Admin_Settings::get_instance();
