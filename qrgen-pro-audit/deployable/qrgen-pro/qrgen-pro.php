<?php
/**
 * Plugin Name: QRGen Pro - تولید کننده حرفه‌ای QR Code
 * Plugin URI: https://signteb.com
 * Description: تولید کننده قدرتمند QR Code با پشتیبانی از انواع محتوا
 * Version: 1.1.0
 * Author: رضا آسیابی
 * Author URI: https://signteb.com
 * License: GPL v2 or later
 * Text Domain: qrgen-pro
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('QRGEN_PRO_VERSION', '1.1.0');
define('QRGEN_PRO_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once QRGEN_PRO_PLUGIN_PATH . 'includes/assets/admin.php';

register_activation_hook(__FILE__, 'qrgen_pro_activate');
function qrgen_pro_activate() {
    add_option('qrgen_default_size', '300');
    add_option('qrgen_default_color_dark', '#000000');
    add_option('qrgen_default_color_light', '#ffffff');
    add_option('qrgen_version', QRGEN_PRO_VERSION);
}

add_action('plugins_loaded', 'qrgen_pro_upgrade_check');
function qrgen_pro_upgrade_check() {
    if (get_option('qrgen_version') !== QRGEN_PRO_VERSION) {
        update_option('qrgen_version', QRGEN_PRO_VERSION);
    }
}

add_action('init', 'qrgen_pro_load_textdomain');
function qrgen_pro_load_textdomain() {
    load_plugin_textdomain('qrgen-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

class QRGen_Pro_Plugin {

    private static $style_printed = false;

    public function __construct() {
        add_shortcode('qrgen_pro', array($this, 'qrgen_pro_shortcode'));
        add_shortcode('qrgen', array($this, 'qrgen_pro_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function qrgen_pro_shortcode($atts) {
        return $this->get_qr_generator_html();
    }

    private function get_qr_generator_html() {
        $instance_id = wp_unique_id('qrgen-pro-');
        $print_style = !self::$style_printed;
        self::$style_printed = true;

        $default_size = (int) get_option('qrgen_default_size', 300);
        if (!in_array($default_size, array(200, 300, 400, 500), true)) {
            $default_size = 300;
        }
        $default_color_dark = get_option('qrgen_default_color_dark', '#000000');
        $default_color_light = get_option('qrgen_default_color_light', '#ffffff');

        ob_start();
        ?>
        <div id="<?php echo esc_attr($instance_id); ?>">
            <div class="qrgen-pro-wrapper">
                <!-- استایل‌های مستقیم -->
                <?php if ($print_style): ?>
                <style>
                    .qrgen-pro-wrapper {
                        font-family: Tahoma, Arial, sans-serif;
                        direction: rtl;
                        max-width: 1200px;
                        margin: 20px auto;
                        background: white;
                        border-radius: 10px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        overflow: hidden;
                    }
                    
                    .qrgen-pro-header {
                        background: linear-gradient(135deg, #7e57c2, #26a69a);
                        color: white;
                        padding: 25px;
                        text-align: center;
                    }
                    
                    .qrgen-pro-header h2 {
                        margin: 0 0 10px 0;
                        font-size: 24px;
                    }
                    
                    .qrgen-pro-header p {
                        margin: 0;
                        opacity: 0.9;
                        font-size: 14px;
                    }
                    
                    .qrgen-pro-main {
                        display: flex;
                        flex-wrap: wrap;
                        min-height: 500px;
                    }
                    
                    .qrgen-pro-input-section {
                        flex: 1;
                        min-width: 300px;
                        padding: 25px;
                        background: #f8f9fa;
                        border-left: 1px solid #e9ecef;
                    }
                    
                    .qrgen-pro-output-section {
                        flex: 1;
                        min-width: 300px;
                        padding: 25px;
                        background: white;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .qrgen-pro-form-group {
                        margin-bottom: 20px;
                    }
                    
                    .qrgen-pro-label {
                        display: block;
                        margin-bottom: 8px;
                        font-weight: bold;
                        color: #333;
                        font-size: 14px;
                    }
                    
                    .qrgen-pro-input, .qrgen-pro-select, .qrgen-pro-textarea, .qrgen-pro-button {
                        width: 100%;
                        padding: 12px 15px;
                        border: 1px solid #ddd;
                        border-radius: 6px;
                        font-size: 14px;
                        box-sizing: border-box;
                        font-family: inherit;
                    }
                    
                    .qrgen-pro-textarea {
                        min-height: 100px;
                        resize: vertical;
                    }
                    
                    .qrgen-pro-input:focus, .qrgen-pro-select:focus, .qrgen-pro-textarea:focus {
                        outline: none;
                        border-color: #7e57c2;
                        box-shadow: 0 0 0 2px rgba(126, 87, 194, 0.1);
                    }
                    
                    .qrgen-pro-button {
                        background: #7e57c2;
                        color: white;
                        border: none;
                        cursor: pointer;
                        font-weight: bold;
                        transition: all 0.3s ease;
                        margin-top: 10px;
                    }
                    
                    .qrgen-pro-button:hover {
                        background: #6a4bac;
                        transform: translateY(-1px);
                    }
                    
                    .qrgen-pro-button.success {
                        background: #28a745;
                    }
                    
                    .qrgen-pro-button.success:hover {
                        background: #218838;
                    }
                    
                    .qrgen-pro-button.secondary {
                        background: #26a69a;
                    }
                    
                    .qrgen-pro-button.secondary:hover {
                        background: #219a8e;
                    }
                    
                    .qrgen-pro-qrcode-container {
                        background: white;
                        padding: 30px;
                        border-radius: 10px;
                        text-align: center;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        border: 1px solid #e9ecef;
                        margin-bottom: 20px;
                        min-width: 280px;
                        min-height: 320px;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    #qrcode {
                        margin: 15px 0;
                    }
                    
                    .qrgen-pro-controls {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 12px;
                        width: 100%;
                        max-width: 300px;
                    }
                    
                    .qrgen-pro-tabs {
                        display: flex;
                        border-bottom: 1px solid #ddd;
                        margin-bottom: 20px;
                    }
                    
                    .qrgen-pro-tab {
                        padding: 12px 20px;
                        cursor: pointer;
                        border-bottom: 3px solid transparent;
                        transition: all 0.3s ease;
                        font-weight: 500;
                    }
                    
                    .qrgen-pro-tab.active {
                        border-bottom-color: #7e57c2;
                        color: #7e57c2;
                    }
                    
                    .qrgen-pro-tab-content {
                        display: none;
                    }
                    
                    .qrgen-pro-tab-content.active {
                        display: block;
                    }
                    
                    .qrgen-pro-data-types {
                        display: grid;
                        grid-template-columns: repeat(4, 1fr);
                        gap: 8px;
                        margin-bottom: 20px;
                    }
                    
                    .qrgen-pro-data-type {
                        padding: 10px 5px;
                        text-align: center;
                        border: 1px solid #ddd;
                        border-radius: 6px;
                        cursor: pointer;
                        background: white;
                        font-size: 12px;
                        transition: all 0.3s ease;
                    }
                    
                    .qrgen-pro-data-type.active {
                        border-color: #7e57c2;
                        background: #f0e6ff;
                        color: #7e57c2;
                        font-weight: bold;
                    }
                    
                    .qrgen-pro-content-type {
                        display: none;
                    }
                    
                    .qrgen-pro-content-type.active {
                        display: block;
                    }
                    
                    .qrgen-pro-color-controls {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 15px;
                        margin: 15px 0;
                    }
                    
                    .qrgen-pro-color-control {
                        text-align: center;
                    }
                    
                    .qrgen-pro-color-preview {
                        width: 45px;
                        height: 45px;
                        border-radius: 50%;
                        margin: 0 auto 8px;
                        border: 3px solid #ddd;
                        cursor: pointer;
                        transition: transform 0.3s ease;
                    }
                    
                    .qrgen-pro-color-preview:hover {
                        transform: scale(1.05);
                    }
                    
                    .qrgen-pro-preset-colors {
                        display: flex;
                        gap: 10px;
                        justify-content: center;
                        margin: 15px 0;
                        flex-wrap: wrap;
                    }
                    
                    .qrgen-pro-color-preset {
                        width: 28px;
                        height: 28px;
                        border-radius: 50%;
                        cursor: pointer;
                        border: 2px solid transparent;
                        transition: all 0.3s ease;
                    }
                    
                    .qrgen-pro-color-preset:hover {
                        transform: scale(1.1);
                        border-color: #333;
                    }
                    
                    .qrgen-pro-logo-preview {
                        width: 80px;
                        height: 80px;
                        border: 2px dashed #ddd;
                        border-radius: 8px;
                        margin: 15px auto;
                        background-size: contain;
                        background-position: center;
                        background-repeat: no-repeat;
                        display: none;
                        background-color: #f8f9fa;
                    }
                    
                    .qrgen-pro-test-result {
                        margin-top: 15px;
                        padding: 15px;
                        background: white;
                        border-radius: 6px;
                        border: 1px solid #e9ecef;
                        display: none;
                        width: 100%;
                        max-width: 300px;
                    }
                    
                    .qrgen-pro-success {
                        background: #d4edda;
                        color: #155724;
                        border: 1px solid #c3e6cb;
                        padding: 12px;
                        border-radius: 4px;
                    }
                    
                    .qrgen-pro-error {
                        background: #f8d7da;
                        color: #721c24;
                        border: 1px solid #f5c6cb;
                        padding: 12px;
                        border-radius: 4px;
                    }
                    
                    .qrgen-pro-size-info {
                        margin-top: 15px;
                        color: #666;
                        font-size: 13px;
                        text-align: center;
                    }
                    
                    .qrgen-pro-footer {
                        text-align: center;
                        padding: 20px;
                        background: #343a40;
                        color: white;
                        font-size: 12px;
                    }
                    
                    @media (max-width: 768px) {
                        .qrgen-pro-main {
                            flex-direction: column;
                        }
                        
                        .qrgen-pro-input-section {
                            border-left: none;
                            border-bottom: 1px solid #e9ecef;
                        }
                        
                        .qrgen-pro-data-types {
                            grid-template-columns: repeat(2, 1fr);
                        }
                        
                        .qrgen-pro-controls {
                            grid-template-columns: 1fr;
                        }
                    }
                </style>
                <?php endif; ?>

                <!-- هدر -->
                <div class="qrgen-pro-header">
                    <h2>تولید کننده حرفه‌ای QR Code</h2>
                    <p>پشتیبانی از انواع محتوا - لینک، متن، ایمیل، تماس، پیامک، وای‌فای و کارت ویزیت</p>
                </div>

                <!-- محتوای اصلی -->
                <div class="qrgen-pro-main">
                    <!-- بخش ورودی -->
                    <div class="qrgen-pro-input-section">
                        <!-- انتخاب نوع محتوا -->
                        <div class="qrgen-pro-form-group">
                            <label class="qrgen-pro-label">نوع محتوا</label>
                            <div class="qrgen-pro-data-types">
                                <div class="qrgen-pro-data-type active" data-type="url">🌐 لینک</div>
                                <div class="qrgen-pro-data-type" data-type="text">📝 متن</div>
                                <div class="qrgen-pro-data-type" data-type="email">📧 ایمیل</div>
                                <div class="qrgen-pro-data-type" data-type="contact">📞 تماس</div>
                                <div class="qrgen-pro-data-type" data-type="sms">💬 پیامک</div>
                                <div class="qrgen-pro-data-type" data-type="wifi">📶 وای‌فای</div>
                                <div class="qrgen-pro-data-type" data-type="vcard">📇 کارت ویزیت</div>
                            </div>
                        </div>

                        <!-- محتوای فعال -->
                        <div class="qrgen-pro-content-type active" data-type="url">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">آدرس وبسایت</label>
                                <input type="text" class="qrgen-pro-input url-input" placeholder="https://example.com" value="https://example.com">
                            </div>
                        </div>

                        <div class="qrgen-pro-content-type" data-type="text">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">متن دلخواه</label>
                                <textarea class="qrgen-pro-textarea text-input" placeholder="متن خود را وارد کنید...">متن نمونه برای QR Code</textarea>
                            </div>
                        </div>

                        <div class="qrgen-pro-content-type" data-type="email">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">آدرس ایمیل</label>
                                <input type="email" class="qrgen-pro-input email-input" placeholder="example@domain.com" value="example@domain.com">
                            </div>
                        </div>

                        <div class="qrgen-pro-content-type" data-type="contact">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">شماره تلفن</label>
                                <input type="text" class="qrgen-pro-input phone-input" placeholder="+989123456789" value="+989123456789">
                            </div>
                        </div>

                        <div class="qrgen-pro-content-type" data-type="sms">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">شماره تلفن</label>
                                <input type="text" class="qrgen-pro-input sms-phone" placeholder="+989123456789" value="+989123456789">
                            </div>
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">متن پیام</label>
                                <textarea class="qrgen-pro-textarea sms-body" placeholder="متن پیامک...">سلام، این یک پیام تستی است</textarea>
                            </div>
                        </div>

                        <div class="qrgen-pro-content-type" data-type="wifi">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">نام شبکه (SSID)</label>
                                <input type="text" class="qrgen-pro-input wifi-ssid" placeholder="نام شبکه وای‌فای" value="MyWiFi">
                            </div>
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">رمز عبور</label>
                                <input type="text" class="qrgen-pro-input wifi-password" placeholder="رمز وای‌فای" value="MyPassword123">
                            </div>
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">نوع شبکه</label>
                                <select class="qrgen-pro-select wifi-encryption">
                                    <option value="nopass">بدون رمز</option>
                                    <option value="WEP">WEP</option>
                                    <option value="WPA" selected>WPA/WPA2</option>
                                </select>
                            </div>
                        </div>

                        <div class="qrgen-pro-content-type" data-type="vcard">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">نام</label>
                                <input type="text" class="qrgen-pro-input vcard-name" placeholder="نام و نام خانوادگی" value="رضا آسیابی">
                            </div>
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">شماره تلفن</label>
                                <input type="text" class="qrgen-pro-input vcard-phone" placeholder="+989123456789" value="+989123456789">
                            </div>
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">ایمیل</label>
                                <input type="email" class="qrgen-pro-input vcard-email" placeholder="example@domain.com" value="example@domain.com">
                            </div>
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">شرکت</label>
                                <input type="text" class="qrgen-pro-input vcard-company" placeholder="نام شرکت" value="ساین طب">
                            </div>
                        </div>

                        <!-- تب‌ها -->
                        <div class="qrgen-pro-tabs">
                            <div class="qrgen-pro-tab active" data-tab="basic">تنظیمات پایه</div>
                            <div class="qrgen-pro-tab" data-tab="design">طراحی</div>
                        </div>

                        <!-- تب تنظیمات پایه -->
                        <div class="qrgen-pro-tab-content active" data-tab-content="basic">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">سایز QR Code</label>
                                <select class="qrgen-pro-select size-selector">
                                    <option value="200" <?php selected($default_size, 200); ?>>کوچک (200x200)</option>
                                    <option value="300" <?php selected($default_size, 300); ?>>متوسط (300x300)</option>
                                    <option value="400" <?php selected($default_size, 400); ?>>بزرگ (400x400)</option>
                                    <option value="500" <?php selected($default_size, 500); ?>>خیلی بزرگ (500x500)</option>
                                </select>
                            </div>

                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">رنگ‌بندی</label>
                                <div class="qrgen-pro-color-controls">
                                    <div class="qrgen-pro-color-control">
                                        <div class="qrgen-pro-color-preview dark-preview" style="background-color: <?php echo esc_attr($default_color_dark); ?>"></div>
                                        <input type="color" class="color-dark" value="<?php echo esc_attr($default_color_dark); ?>">
                                        <small>رنگ QR</small>
                                    </div>
                                    <div class="qrgen-pro-color-control">
                                        <div class="qrgen-pro-color-preview light-preview" style="background-color: <?php echo esc_attr($default_color_light); ?>"></div>
                                        <input type="color" class="color-light" value="<?php echo esc_attr($default_color_light); ?>">
                                        <small>رنگ پس‌زمینه</small>
                                    </div>
                                </div>
                                
                                <div class="qrgen-pro-preset-colors">
                                    <div class="qrgen-pro-color-preset" style="background-color: #000000" data-color="#000000" title="مشکی"></div>
                                    <div class="qrgen-pro-color-preset" style="background-color: #7e57c2" data-color="#7e57c2" title="بنفش"></div>
                                    <div class="qrgen-pro-color-preset" style="background-color: #26a69a" data-color="#26a69a" title="فیروزه‌ای"></div>
                                    <div class="qrgen-pro-color-preset" style="background-color: #42a5f5" data-color="#42a5f5" title="آبی"></div>
                                    <div class="qrgen-pro-color-preset" style="background-color: #ef5350" data-color="#ef5350" title="قرمز"></div>
                                    <div class="qrgen-pro-color-preset" style="background-color: #66bb6a" data-color="#66bb6a" title="سبز"></div>
                                </div>
                            </div>

                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">فرمت خروجی</label>
                                <select class="qrgen-pro-select format-selector">
                                    <option value="png">PNG (کیفیت بالا)</option>
                                    <option value="jpg">JPG (حجم کم)</option>
                                </select>
                            </div>
                        </div>

                        <!-- تب طراحی -->
                        <div class="qrgen-pro-tab-content" data-tab-content="design">
                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">شکل نقاط</label>
                                <select class="qrgen-pro-select shape-selector">
                                    <option value="square">مربع (پیش‌فرض)</option>
                                    <option value="circle">دایره‌ای</option>
                                    <option value="dots">نقطه‌ای</option>
                                </select>
                            </div>

                            <div class="qrgen-pro-form-group">
                                <label class="qrgen-pro-label">افزودن لوگو (اختیاری)</label>
                                <input type="file" class="qrgen-pro-input logo-upload" accept="image/*">
                                <div class="qrgen-pro-logo-preview"></div>
                                <button type="button" class="qrgen-pro-button secondary remove-logo" style="display: none; margin-top: 10px;">حذف لوگو</button>
                            </div>
                        </div>

                        <button class="qrgen-pro-button generate-btn">
                            تولید QR Code
                        </button>
                    </div>

                    <!-- بخش خروجی -->
                    <div class="qrgen-pro-output-section">
                        <div class="qrgen-pro-qrcode-container">
                            <div class="qrgen-pro-qrcode-target"></div>
                            <div class="qrgen-pro-size-info">
                                QR Code شما اینجا نمایش داده می‌شود
                            </div>
                        </div>

                        <div class="qrgen-pro-controls">
                            <button class="qrgen-pro-button success download-btn" style="display: none;">
                                دانلود QR Code
                            </button>
                            <button class="qrgen-pro-button secondary test-btn" style="display: none;">
                                تست QR Code
                            </button>
                        </div>

                        <div class="qrgen-pro-test-result"></div>
                    </div>
                </div>

                <!-- فوتر -->
                <div class="qrgen-pro-footer">
                    توسعه یافته توسط رضا آسیابی - QRGen Pro
                </div>
            </div>
        </div>

        <!-- اسکریپت -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('<?php echo esc_js($instance_id); ?>');
            if (!container) {
                return;
            }

            // متغیرهای全局
            let currentQRCode = null;
            let currentContent = "";
            let currentDataType = "url";
            let logoImage = null;

            // انتخاب نوع محتوا
            container.querySelectorAll('.qrgen-pro-data-type').forEach(type => {
                type.addEventListener('click', function() {
                    container.querySelectorAll('.qrgen-pro-data-type').forEach(t => t.classList.remove('active'));
                    container.querySelectorAll('.qrgen-pro-content-type').forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    const contentType = container.querySelector(`.qrgen-pro-content-type[data-type="${this.dataset.type}"]`);
                    if (contentType) {
                        contentType.classList.add('active');
                    }
                    currentDataType = this.dataset.type;
                });
            });

            // تب‌ها
            container.querySelectorAll('.qrgen-pro-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    container.querySelectorAll('.qrgen-pro-tab').forEach(t => t.classList.remove('active'));
                    container.querySelectorAll('.qrgen-pro-tab-content').forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    const tabContent = container.querySelector(`.qrgen-pro-tab-content[data-tab-content="${this.dataset.tab}"]`);
                    if (tabContent) {
                        tabContent.classList.add('active');
                    }
                });
            });

            // رنگ‌های از پیش تعریف شده
            container.querySelectorAll('.qrgen-pro-color-preset').forEach(preset => {
                preset.addEventListener('click', function() {
                    container.querySelector('.color-dark').value = this.dataset.color;
                    updateColorPreview();
                });
            });

            // آپلود لوگو
            const logoUpload = container.querySelector('.logo-upload');
            if (logoUpload) {
                logoUpload.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            logoImage = new Image();
                            logoImage.onload = function() {
                                const preview = container.querySelector('.qrgen-pro-logo-preview');
                                const removeBtn = container.querySelector('.remove-logo');
                                if (preview) {
                                    preview.style.backgroundImage = `url(${e.target.result})`;
                                    preview.style.display = 'block';
                                }
                                if (removeBtn) {
                                    removeBtn.style.display = 'block';
                                }
                            };
                            logoImage.src = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // حذف لوگو
            const removeLogo = container.querySelector('.remove-logo');
            if (removeLogo) {
                removeLogo.addEventListener('click', function() {
                    const logoUpload = container.querySelector('.logo-upload');
                    const preview = container.querySelector('.qrgen-pro-logo-preview');
                    if (logoUpload) logoUpload.value = '';
                    if (preview) preview.style.display = 'none';
                    this.style.display = 'none';
                    logoImage = null;
                });
            }

            // به‌روزرسانی پیش‌نمایش رنگ
            function updateColorPreview() {
                const darkColor = container.querySelector('.color-dark').value;
                const lightColor = container.querySelector('.color-light').value;
                
                const darkPreview = container.querySelector('.dark-preview');
                const lightPreview = container.querySelector('.light-preview');
                
                if (darkPreview) darkPreview.style.backgroundColor = darkColor;
                if (lightPreview) lightPreview.style.backgroundColor = lightColor;
            }

            const colorDark = container.querySelector('.color-dark');
            const colorLight = container.querySelector('.color-light');
            if (colorDark) colorDark.addEventListener('input', updateColorPreview);
            if (colorLight) colorLight.addEventListener('input', updateColorPreview);

            // تولید QR Code
            const generateBtn = container.querySelector('.generate-btn');
            if (generateBtn) {
                generateBtn.addEventListener('click', generateQRCode);
            }

            function generateQRCode() {
                const content = getContent();
                const size = parseInt(container.querySelector('.size-selector').value);
                const colorDark = container.querySelector('.color-dark').value;
                const colorLight = container.querySelector('.color-light').value;
                
                // پاک کردن QR Code قبلی
                const qrcodeElement = container.querySelector('.qrgen-pro-qrcode-target');
                if (qrcodeElement) {
                    qrcodeElement.innerHTML = "";
                }
                
                const downloadBtn = container.querySelector('.download-btn');
                const testBtn = container.querySelector('.test-btn');
                const testResult = container.querySelector('.qrgen-pro-test-result');
                
                if (downloadBtn) downloadBtn.style.display = "none";
                if (testBtn) testBtn.style.display = "none";
                if (testResult) {
                    testResult.style.display = "none";
                    testResult.innerHTML = "";
                }
                
                if (!content) {
                    showResult('لطفا محتوایی وارد کنید', 'error');
                    return;
                }

                currentContent = content;

                try {
                    // تولید QR Code
                    if (!qrcodeElement) {
                        showResult('خطا در پیدا کردن المان QR Code', 'error');
                        return;
                    }
                    
                    const qrcode = new QRCode(qrcodeElement, {
                        text: content,
                        width: size,
                        height: size,
                        colorDark: colorDark,
                        colorLight: colorLight,
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    // نمایش دکمه‌ها
                    if (downloadBtn) downloadBtn.style.display = "block";
                    if (testBtn) testBtn.style.display = "block";
                    
                    // به‌روزرسانی اطلاعات
                    const sizeInfo = container.querySelector('.qrgen-pro-size-info');
                    if (sizeInfo) {
                        sizeInfo.textContent = `سایز: ${size}x${size} | نوع: ${getTypeName(currentDataType)}`;
                    }
                    
                    setTimeout(() => {
                        const canvas = qrcodeElement.querySelector('canvas');
                        if (canvas) {
                            if (logoImage) {
                                const ctx = canvas.getContext('2d');
                                const logoSize = canvas.width * 0.2;
                                const x = (canvas.width - logoSize) / 2;
                                const y = (canvas.height - logoSize) / 2;
                                const padding = 4;
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(x - padding, y - padding, logoSize + padding * 2, logoSize + padding * 2);
                                ctx.drawImage(logoImage, x, y, logoSize, logoSize);
                            }
                            currentQRCode = canvas;
                            showResult('QR Code با موفقیت تولید شد!', 'success');
                        } else {
                            showResult('خطا در تولید QR Code', 'error');
                        }
                    }, 100);
                    
                } catch (error) {
                    console.error('Error:', error);
                    showResult('خطا در تولید QR Code. لطفا دوباره تلاش کنید.', 'error');
                }
            }

            function getContent() {
                switch(currentDataType) {
                    case 'url':
                        const urlInput = container.querySelector('.url-input');
                        return urlInput ? urlInput.value : 'https://example.com';
                    case 'text':
                        const textInput = container.querySelector('.text-input');
                        return textInput ? textInput.value : 'متن نمونه';
                    case 'email':
                        const emailInput = container.querySelector('.email-input');
                        return emailInput ? `mailto:${emailInput.value}` : 'mailto:example@domain.com';
                    case 'contact':
                        const phoneInput = container.querySelector('.phone-input');
                        return phoneInput ? `tel:${phoneInput.value}` : 'tel:+989123456789';
                    case 'sms':
                        const smsPhone = container.querySelector('.sms-phone');
                        const smsBody = container.querySelector('.sms-body');
                        const phone = smsPhone ? smsPhone.value : '+989123456789';
                        const body = smsBody ? smsBody.value : 'پیام تستی';
                        return `sms:${phone}?body=${encodeURIComponent(body)}`;
                    case 'wifi':
                        const wifiSSID = container.querySelector('.wifi-ssid');
                        const wifiPassword = container.querySelector('.wifi-password');
                        const wifiEncryption = container.querySelector('.wifi-encryption');
                        const ssid = wifiSSID ? wifiSSID.value : 'MyWiFi';
                        const password = wifiPassword ? wifiPassword.value : '';
                        const encryption = wifiEncryption ? wifiEncryption.value : 'WPA';
                        
                        if (encryption === 'nopass') {
                            return `WIFI:S:${ssid};T:nopass;;`;
                        } else {
                            return `WIFI:S:${ssid};T:${encryption};P:${password};;`;
                        }
                    case 'vcard':
                        const vcardName = container.querySelector('.vcard-name');
                        const vcardPhone = container.querySelector('.vcard-phone');
                        const vcardEmail = container.querySelector('.vcard-email');
                        const vcardCompany = container.querySelector('.vcard-company');
                        
                        const name = vcardName ? vcardName.value : 'نام';
                        const vcardPhoneValue = vcardPhone ? vcardPhone.value : '';
                        const email = vcardEmail ? vcardEmail.value : '';
                        const company = vcardCompany ? vcardCompany.value : '';

                        let vcard = 'BEGIN:VCARD\nVERSION:3.0\n';
                        vcard += `FN:${name}\n`;
                        if (vcardPhoneValue) vcard += `TEL:${vcardPhoneValue}\n`;
                        if (email) vcard += `EMAIL:${email}\n`;
                        if (company) vcard += `ORG:${company}\n`;
                        vcard += 'END:VCARD';
                        
                        return vcard;
                    default:
                        return 'https://example.com';
                }
            }

            function getTypeName(type) {
                const names = {
                    'url': 'لینک',
                    'text': 'متن',
                    'email': 'ایمیل',
                    'contact': 'تماس',
                    'sms': 'پیامک',
                    'wifi': 'وای‌فای',
                    'vcard': 'کارت ویزیت'
                };
                return names[type] || type;
            }

            function escapeHtml(value) {
                const div = document.createElement('div');
                div.textContent = value;
                return div.innerHTML;
            }

            function showResult(message, type) {
                const resultDiv = container.querySelector('.qrgen-pro-test-result');
                if (resultDiv) {
                    const className = type === 'success' ? 'qrgen-pro-success' : 'qrgen-pro-error';
                    resultDiv.innerHTML = `<div class="${className}">${escapeHtml(message)}</div>`;
                    resultDiv.style.display = 'block';
                }
            }

            // دانلود QR Code
            const downloadBtn = container.querySelector('.download-btn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    if (!currentQRCode) {
                        showResult('لطفا ابتدا QR Code را تولید کنید', 'error');
                        return;
                    }

                    const format = container.querySelector('.format-selector').value;
                    const filename = `qrcode-${Date.now()}.${format}`;
                    let mimeType = 'image/png';

                    try {
                        if (format === 'jpg') {
                            mimeType = 'image/jpeg';
                            // برای JPG باید پس‌زمینه سفید اضافه کنیم
                            const jpgCanvas = document.createElement('canvas');
                            const jpgCtx = jpgCanvas.getContext('2d');
                            jpgCanvas.width = currentQRCode.width;
                            jpgCanvas.height = currentQRCode.height;
                            jpgCtx.fillStyle = '#ffffff';
                            jpgCtx.fillRect(0, 0, jpgCanvas.width, jpgCanvas.height);
                            jpgCtx.drawImage(currentQRCode, 0, 0);
                            downloadImage(jpgCanvas.toDataURL(mimeType), filename);
                        } else {
                            downloadImage(currentQRCode.toDataURL(mimeType), filename);
                        }

                        showResult('QR Code با موفقیت دانلود شد!', 'success');
                    } catch (error) {
                        console.error('Download error:', error);
                        showResult('خطا در دانلود QR Code', 'error');
                    }
                });
            }

            function downloadImage(dataUrl, filename) {
                const a = document.createElement('a');
                a.href = dataUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }

            // تست QR Code
            const testBtn = container.querySelector('.test-btn');
            if (testBtn) {
                testBtn.addEventListener('click', function() {
                    if (!currentContent) {
                        showResult('لطفا ابتدا QR Code را تولید کنید', 'error');
                        return;
                    }

                    showResult(`محتوای QR Code: ${currentContent}`, 'success');
                });
            }

            // مقداردهی اولیه
            updateColorPreview();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function enqueue_scripts() {
        if (is_singular() && !$this->singular_post_has_shortcode()) {
            return;
        }
        wp_enqueue_script('qrcode-js', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0.0', true);
    }

    private function singular_post_has_shortcode() {
        global $post;
        if (!($post instanceof WP_Post)) {
            return false;
        }
        return has_shortcode($post->post_content, 'qrgen_pro') || has_shortcode($post->post_content, 'qrgen');
    }
}

new QRGen_Pro_Plugin();