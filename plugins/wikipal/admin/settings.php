<?php
// initial constants
define('ADMIN_SELECTED_PAGE', 'plugins');
define('ADMIN_SELECTED_SUB_PAGE', 'plugin_manage');

// includes and security
include_once('../../../core/includes/master.inc.php');
include_once(DOC_ROOT . '/' . ADMIN_FOLDER_NAME . '/_local_auth.inc.php');

// load plugin details
$pluginId = (int) $_REQUEST['id'];
$plugin   = $db->getRow("SELECT * FROM plugin WHERE id = " . (int) $pluginId . " LIMIT 1");
if (!$plugin)
{
    adminFunctions::redirect(ADMIN_WEB_ROOT . '/plugin_manage.php?error=' . urlencode('There was a problem loading the plugin details.'));
}
define('ADMIN_PAGE_TITLE', $plugin['plugin_name'] . ' Plugin Settings');

// prepare variables
$plugin_enabled = (int) $plugin['plugin_enabled'];
$wikipal_webgate_id   = 'xxxxxxxx';
$wikipal_method   = 'wsdl';

// load existing settings
if (strlen($plugin['plugin_settings']))
{
    $plugin_settings = json_decode($plugin['plugin_settings'], true);
    if ($plugin_settings)
    {
        $wikipal_webgate_id = $plugin_settings['wikipal_webgate_id'];
        $wikipal_method = $plugin_settings['wikipal_method'];
    }
}

// handle page submissions
if (isset($_REQUEST['submitted']))
{
    // get variables
    $plugin_enabled = (int) $_REQUEST['plugin_enabled'];
    $plugin_enabled = $plugin_enabled != 1 ? 0 : 1;
    $wikipal_webgate_id   = trim(strtolower($_REQUEST['wikipal_webgate_id']));
    $wikipal_method   = trim(strtolower($_REQUEST['wikipal_method']));

    // validate submission
    if (_CONFIG_DEMO_MODE == true)
    {
        adminFunctions::setError(adminFunctions::t("no_changes_in_demo_mode"));
    }
    elseif (strlen($wikipal_webgate_id) == 0)
    {
        adminFunctions::setError(adminFunctions::t("please_enter_your_wikipal_webgate_id", "Please enter your WikiPal account Merchant."));
    }

    // update the settings
    if (adminFunctions::isErrors() == false)
    {
        // compile new settings
        $settingsArr                 = array();
        $settingsArr['wikipal_webgate_id'] = $wikipal_webgate_id;
        $settingsArr['wikipal_method'] = $wikipal_method;
        $settings                    = json_encode($settingsArr);

        // update the user
        $dbUpdate                  = new DBObject("plugin", array("plugin_enabled", "plugin_settings"), 'id');
        $dbUpdate->plugin_enabled  = $plugin_enabled;
        $dbUpdate->plugin_settings = $settings;
        $dbUpdate->id              = $pluginId;
        $dbUpdate->update();

        adminFunctions::redirect(ADMIN_WEB_ROOT . '/plugin_manage.php?se=1');
    }
}

// page header
include_once(ADMIN_ROOT . '/_header.inc.php');
?>

<script>
    $(function() {
        // formvalidator
        $("#userForm").validationEngine();
    });
</script>

<div class="row clearfix">
    <div class="col_12">
        <div class="sectionLargeIcon" style="background: url(../assets/img/icons/128px.png) no-repeat;"></div>
        <div class="widget clearfix">
            <h2 style="font-size:12px; font-family:tahoma;">سامانه پرداخت الکترونیک ویکی پال</h2>
            <div class="widget_inside">
                <?php echo adminFunctions::compileNotifications(); ?>
                <form method="POST" action="settings.php" name="pluginForm" id="pluginForm" autocomplete="off">
                    <div class="clearfix col_12">
                        <div class="col_4">
                            <h3 style="font-size:12px;">فعال سازی سامانه پرداخت الکترونیک ویکی پال</h3>
                            <p>در صورتی که میخواهید کاربر بتوان از طریق سامانه پرداخت الکترونیک ویکی پال قادر به پرداخت باشد باید درگاه را فعال نمایید .</p>
                        </div>
                        <div class="col_8 last">
                            <div class="form">
                                <div class="clearfix alt-highlight">
                                    <label>فعال سازی :</label>
                                    <div class="input">
                                        <select name="plugin_enabled" id="plugin_enabled" class="medium validate[required]">
                                            <?php
                                            $enabledOptions = array(0 => 'خیر', 1 => 'بله');
                                            foreach ($enabledOptions AS $k => $enabledOption)
                                            {
                                                echo '<option value="' . $k . '"';
                                                if ($plugin_enabled == $k)
                                                {
                                                    echo ' SELECTED';
                                                }
                                                echo '>' . $enabledOption . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="clearfix col_12">
                        <div class="col_4">
                            <h3 style="font-size:12px;">تنظیمات سامانه پرداخت الکترونیک ویکی پال</h3>
                            <p>جهت انجام عملیات پرداخت صحیح تنظیمات را به درستی انجام دهید .</p>
                            <p><a target="_blank" href="http://wikipal.ir">سامانه پرداخت الکترونیک ویکی پال</a></p>
                        </div>
                        <div class="col_8 last">
                            <div class="form">
                                <div class="clearfix alt-highlight">
                                    <label>مرچنت کد</label>
                                    <div class="input"><input id="wikipal_webgate_id" name="wikipal_webgate_id" type="text" class="large validate[required]" value="<?php echo adminFunctions::makeSafe($wikipal_webgate_id); ?>" dir="ltr"/></div>
                                </div>
                            </div>
							
                        </div>
                    </div>

                    <div class="clearfix col_12">
                        <div class="col_4 adminResponsiveHide">&nbsp;</div>
                        <div class="col_8 last">
                            <div class="clearfix">
                                <div class="input no-label">
                                    <input type="submit" value="ذخیره سازی تغییرات" class="button blue">
                                </div>
                            </div>
                        </div>
                    </div>

                    <input name="submitted" type="hidden" value="1"/>
                    <input name="id" type="hidden" value="<?php echo $pluginId; ?>"/>
                </form>
            </div>
        </div>   
    </div>
</div>

<?php
include_once(ADMIN_ROOT . '/_footer.inc.php');
?>