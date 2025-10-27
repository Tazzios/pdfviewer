<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Registry\Registry;

HTMLHelper::_('behavior.core');
HTMLHelper::_('behavior.polyfill', array('event'), 'lt IE 9');

$document = Factory::getDocument();
$this->eName = Factory::getApplication()->input->getCmd('e_name', '');
$this->eName = preg_replace('#[^A-Z0-9\-\_\[\]]#i', '', $this->eName);

$document->addScript(Uri::root() . 'plugins/editors-xtd/pdfviewer/assets/pdfviewer.js');

// check if jdownloads is installed
$path = JPATH_ROOT . '/administrator/components/com_jdownloads';
$dropdown = '';
$radiojdownload = '';
$radioexternalpdf = '';
if (file_exists($path)) {
    // get all published jdownloads files
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select($db->quoteName(['id','title']))
        ->from($db->quoteName('#__jdownloads_files'))
        ->where($db->quoteName('published') . ' = 1')
        ->order($db->quoteName('publish_up') . ' DESC');
    $db->setQuery($query);
    $fields = $db->loadAssocList();

    // create dropdown with jdownloads files
    $dropdown = '<div id="jdownloadsid_div">
                    <label for="jdownloadsid">jDownloads file</label>
                    <select id="jdownloadsid" name="jdownloadsid" onchange="filesettings()">';
    foreach ($fields as $field) {
        $dropdown .= '<option value="' . htmlspecialchars($field['id'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($field['title'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $dropdown .= '</select>  </div>';
    $radiojdownload = 'checked';
} else {
    $radioexternalpdf = 'checked';
    $radiojdownload = 'disabled';
}

// Get plugin parameters
$plugin = PluginHelper::getPlugin('editors-xtd', 'pdfviewer');

$plugindefault_viewer = 'pdfjs';
$plugindefault_style = 'embed';

if ($plugin)
{
    $pluginParams = new Registry($plugin->params);
    $plugindefault_viewer = $pluginParams->get('viewer', $plugindefault_viewer);
    $plugindefault_style  = $pluginParams->get('style', $plugindefault_style);
}
?>

<body onload="filesettings();">

<div class="container-popup">
    <form class="form-horizontal">
        <div class="form-check form-check-inline">
            <input type="radio" id="jdownloadsid_radio" name="filetype" value="jdownloadsid" onchange="filesettings()" <?php echo $radiojdownload; ?>><label for="jdownloads">Jdownloads</label>
        </div>
        <div class="form-check form-check-inline">
            <input type="radio" id="file_radio" name="filetype" value="file" onchange="filesettings()" <?php echo $radioexternalpdf; ?> ><label for="file">external pdf</label>

        <div id="form_div" style="display: none;">
            <table style="width:100%" >

                <tr>
                    <td valign=top>

                        <input type="hidden" id="plugindefault_viewer" name="plugindefault_viewer" value="<?php echo htmlspecialchars($plugindefault_viewer, ENT_QUOTES, 'UTF-8'); ?>" >
                        <input type="hidden" id="plugindefault_style" name="plugindefault_style" value="<?php echo htmlspecialchars($plugindefault_style, ENT_QUOTES, 'UTF-8'); ?>"   >

                        <!--jdownloads dropdown -->
                        <?php echo $dropdown; ?>

                        <div id="file_div" title="Insert full link (https://) or relative link (/)">
                        <label for="file">url file link</label>  <input type="text" id="file" name="file" min="1" onchange="filesettings()">
                        </div>

                        <div id="viewer_div">
                        <label for="viewer">Viewer</label>
                        <select id="viewer" name="viewer" onchange="viewersettings()">
                              <option value="default" selected >default (<?php echo htmlspecialchars($plugindefault_viewer, ENT_QUOTES, 'UTF-8'); ?>)</option>
                              <option value="pdfjs" >pdfjs</option>
                              <option value="pdfimage" >pdfimage</option>
                            </select>
                        <br><br>
                        </div>

                        <label for="style">Display Style</label>
                        <select id="style" name="style" onchange="stylesettings()"  >
                            <option value="default" selected >default (<?php echo htmlspecialchars($plugindefault_style, ENT_QUOTES, 'UTF-8'); ?>)</option>
                            <option value="embed" >embed</option>
                            <option value="popup" >popup</option>
                            <option value="new" >new</option>
                        </select>
                        <br><br>

                        <div id="pdfjssettings_div">
                            <b>Advanced PDF.js options</b> <br>
                            <label for="zoom">Page zoom</label>
                            <select id="zoom" name="zoom"   >
                                <option value="default" >default (auto)</option>
                                <option value="auto-fit" >auto</option>
                                <option value="page-width" >fit width</option>
                                <option value="page-height" >fit height</option>
                                <option value="page-fit" >fit page</option>
                            </select>

                            <label for="pagemode">Sidebar pagemode</label>
                            <select id="pagemode" name="pagemode"   >
                                <option value="default" >default (None)</option>
                                <option value="none" >none</option>
                                <option value="bookmarks" >bookmarks</option>
                                <option value="thumbs" >thumbs</option>
                                <option value="attachments" >attachments</option>
                            </select>
                        </div>

                        <div id="sizesettings_div" >
                            <label for="width">Width</label>
                            <input type="text" id="width" name="width" style="width:40px" > <div id="width_info" > </div>
                            <br>
                            <label for="height">Height</label>
                            <input type="text" id="height" name="height" min="0" style="width:40px" >  <div id="height_info" > </div>
                        </div>

                    </td>

                    <td valign=top>
                        <div id="search_div">
                            <label for="search">Search</label>
                            <input type="text" id="search" name="search" onchange="searchsettings()"  >
                        </div>
                        <div id="searchphrase_div">
                            <label for="searchphrase">phrase</label>
                            <input type="checkbox" id="searchphrase" name="searchphrase" >
                        </div>
                        <div id="pagenumber_div">
                            <label for="pagenumber">Pagenumber</label>
                            <input type="number" id="page" name="page" min="0" style="width:60px" >
                        </div>
                        <div id="linktext_div">
                            <br>
                            <label for="Linktext">Linktext</label>
                            <input type="text" id="linktext" name="search" >
                        </div>

                    </td>
                </tr>

            </table>
        </div>

    <button onclick="insertPagebreak('<?php echo $this->eName; ?>');" class="btn btn-success pull-right">Insert</button>

    </form>
</div>

</body>

<script>
    // (the existing JS in your file can remain unchanged)
    function filesettings() {
        var file = document.getElementById("jdownloadsid_radio").checked;

        if(!document.getElementById("jdownloadsid_div")){
            document.getElementById("jdownloadsid_radio").disabled=true;
        }

        if (file ==true ) { //jdownloads file
            document.getElementById("file_div").style.display = "none";
            if(document.getElementById("jdownloadsid_div")){
                document.getElementById("jdownloadsid_div").style.display = "block";
            }
            document.getElementById("viewer_div").style.display = "block";

            document.getElementById("form_div").style.display = "block";

            // set filename as default linktext
            var select = document.getElementById('jdownloadsid');
            var value = select.options[select.selectedIndex].text;
            document.getElementById("linktext").value = value;

        } else { // external file

            if(document.getElementById("jdownloadsid_div")){
                document.getElementById("jdownloadsid_div").style.display = "none";
            }

            document.getElementById("file_div").style.display = "block";
            document.getElementById("viewer_div").style.display = "none";

            document.getElementById("form_div").style.display = "block";

            document.getElementById("linktext").value = "";

        }

        stylesettings()

    }

    function viewersettings() {
        var viewer = document.getElementById("viewer").value;

        if (viewer == 'pdfimage' ) {
            document.getElementById("sizesettings_div").style.display = "none";
            document.getElementById("search_div").style.display = "none";
            document.getElementById("searchphrase_div").style.display = "none";
            document.getElementById("pdfjssettings_div").style.display = "none";
        } else {
            document.getElementById("sizesettings_div").style.display = "block";
            document.getElementById("search_div").style.display = "block";
            document.getElementById("searchphrase_div").style.display = "block";
            document.getElementById("pdfjssettings_div").style.display = "block";
        }
    }

    function stylesettings() {
        var style = document.getElementById("style").value;

        // reset width and height after change
        document.getElementById("width").value = "";
        document.getElementById("height").value = "";

        if (style == 'embed' || style == 'popup' || (style == 'default' && document.getElementById("plugindefault_style").value!='embed') ) {
            document.getElementById("sizesettings_div").style.display = "block";
        } else {
            document.getElementById("sizesettings_div").style.display = "none";
        }

        if (style == 'embed' || (style == 'default' && document.getElementById("plugindefault_style").value)=='embed') {
            document.getElementById("linktext_div").style.display = "none";
        } else {
            document.getElementById("linktext_div").style.display = "block";
        }
    }

    function searchsettings() {
        var search = document.getElementById("search").value;

        if (search != '' ) {
            document.getElementById("pagenumber_div").style.display = "none";
            document.getElementById("searchphrase_div").style.display = "block";
        } else {
            document.getElementById("pagenumber_div").style.display = "block";
            document.getElementById("searchphrase_div").style.display = "none";
        }
    }

</script>
