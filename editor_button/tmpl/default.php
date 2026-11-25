<?php

/**
 * Dialog of the pdfviewer editor button.
 *
 * @copyright  (C) 2023 Tazzios
 * @license    GNU General Public License version 3 or later
 *
 * @var  array  $displayData  editor, jdownloadsFiles (array|null),
 *                            defaults (button settings) and content (content plugin settings),
 *                            each with viewer, style and sizes
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$editor   = $displayData['editor'];
$files    = $displayData['jdownloadsFiles'];
$defaults = $displayData['defaults'];
$content  = $displayData['content'];
$settings = json_encode(
    ['button' => $defaults, 'content' => $content],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
$hasJd    = is_array($files) && count($files) > 0;
?>
<div class="container-popup pdfviewer-xtd-popup">
    <form id="pdfviewer-form" class="pdfviewer-xtd"
          data-editor="<?php echo $e($editor); ?>"
          data-settings="<?php echo $e($settings); ?>">

        <fieldset class="mb-3">
            <legend class="visually-hidden"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_SOURCE')); ?></legend>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="pdfviewer-source-jd" name="source" value="jdownloads"
                    <?php echo $hasJd ? 'checked' : 'disabled'; ?>>
                <label class="form-check-label" for="pdfviewer-source-jd"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_SOURCE_JDOWNLOADS')); ?></label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="pdfviewer-source-file" name="source" value="file"
                    <?php echo $hasJd ? '' : 'checked'; ?>>
                <label class="form-check-label" for="pdfviewer-source-file"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_SOURCE_FILE')); ?></label>
            </div>
            <?php if ($files === null) : ?>
                <div class="form-text"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_JDOWNLOADS_NOT_INSTALLED')); ?></div>
            <?php elseif (!$hasJd) : ?>
                <div class="form-text"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_JDOWNLOADS_NO_FILES')); ?></div>
            <?php endif; ?>
        </fieldset>

        <div class="pdfviewer-xtd-columns">
            <div class="pdfviewer-xtd-column">
                <?php if ($hasJd) : ?>
                    <div class="mb-3" id="pdfviewer-jd-group">
                        <label class="form-label" for="pdfviewer-jdownloadsid"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_JDOWNLOADS_FILE')); ?></label>
                        <select class="form-select" id="pdfviewer-jdownloadsid">
                            <?php foreach ($files as $file) : ?>
                                <option value="<?php echo (int) $file['id']; ?>"><?php echo $e($file['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3" id="pdfviewer-file-group">
                    <label class="form-label" for="pdfviewer-file"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_FILE')); ?></label>
                    <input class="form-control" type="text" id="pdfviewer-file" placeholder="<?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_FILE_HINT')); ?>">
                    <div class="form-text"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_FILE_DESC')); ?></div>
                </div>

                <div class="mb-3" id="pdfviewer-viewer-group">
                    <label class="form-label" for="pdfviewer-viewer"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_VIEWER')); ?></label>
                    <select class="form-select" id="pdfviewer-viewer">
                        <option value="default" selected><?php echo $e(Text::sprintf('PLG_EDITORSXTD_PDFVIEWER_DEFAULT_VALUE', $defaults['viewer'])); ?></option>
                        <option value="pdfjs">pdfjs</option>
                        <option value="pdfimage">pdfimage</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="pdfviewer-style"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_STYLE')); ?></label>
                    <select class="form-select" id="pdfviewer-style">
                        <option value="default" selected><?php echo $e(Text::sprintf('PLG_EDITORSXTD_PDFVIEWER_DEFAULT_VALUE', $defaults['style'])); ?></option>
                        <option value="embed"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_STYLE_EMBED')); ?></option>
                        <option value="popup"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_STYLE_POPUP')); ?></option>
                        <option value="new"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_STYLE_NEW')); ?></option>
                    </select>
                </div>

                <div class="pdfviewer-xtd-pair mb-3" id="pdfviewer-size-group">
                    <div>
                        <label class="form-label" for="pdfviewer-width"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_WIDTH')); ?></label>
                        <input class="form-control" type="text" id="pdfviewer-width" placeholder="<?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_WIDTH_HINT')); ?>">
                    </div>
                    <div>
                        <label class="form-label" for="pdfviewer-height"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_HEIGHT')); ?></label>
                        <input class="form-control" type="text" id="pdfviewer-height" placeholder="800">
                    </div>
                </div>

                <div class="mb-3" id="pdfviewer-linktext-group">
                    <label class="form-label" for="pdfviewer-linktext"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_LINKTEXT')); ?></label>
                    <input class="form-control" type="text" id="pdfviewer-linktext">
                </div>
            </div>

            <div class="pdfviewer-xtd-column">
                <div class="mb-3" id="pdfviewer-search-group">
                    <label class="form-label" for="pdfviewer-search"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_SEARCH')); ?></label>
                    <input class="form-control" type="text" id="pdfviewer-search">
                    <div class="form-check mt-1" id="pdfviewer-phrase-group">
                        <input class="form-check-input" type="checkbox" id="pdfviewer-phrase">
                        <label class="form-check-label" for="pdfviewer-phrase"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_PHRASE')); ?></label>
                    </div>
                </div>

                <div class="mb-3" id="pdfviewer-page-group">
                    <label class="form-label" for="pdfviewer-page"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_PAGE')); ?></label>
                    <input class="form-control" type="number" id="pdfviewer-page" min="1">
                </div>

                <div id="pdfviewer-pdfjs-group">
                    <div class="mb-3">
                        <label class="form-label" for="pdfviewer-zoom"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_ZOOM')); ?></label>
                        <select class="form-select" id="pdfviewer-zoom">
                            <option value="default" selected><?php echo $e(Text::sprintf('PLG_EDITORSXTD_PDFVIEWER_DEFAULT_VALUE', 'auto')); ?></option>
                            <option value="auto">auto</option>
                            <option value="page-width"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_ZOOM_PAGE_WIDTH')); ?></option>
                            <option value="page-height"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_ZOOM_PAGE_HEIGHT')); ?></option>
                            <option value="page-fit"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_ZOOM_PAGE_FIT')); ?></option>
                            <option value="page-actual"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_ZOOM_PAGE_ACTUAL')); ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pdfviewer-pagemode"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_PAGEMODE')); ?></label>
                        <select class="form-select" id="pdfviewer-pagemode">
                            <option value="default" selected><?php echo $e(Text::sprintf('PLG_EDITORSXTD_PDFVIEWER_DEFAULT_VALUE', 'none')); ?></option>
                            <option value="none"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_PAGEMODE_NONE')); ?></option>
                            <option value="bookmarks"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_PAGEMODE_BOOKMARKS')); ?></option>
                            <option value="thumbs"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_PAGEMODE_THUMBS')); ?></option>
                            <option value="attachments"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_PAGEMODE_ATTACHMENTS')); ?></option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="pdfviewer-xtd-footer">
            <p class="small text-muted mb-2"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_TAG')); ?> <code id="pdfviewer-preview"></code></p>
            <button type="submit" class="btn btn-success"><?php echo $e(Text::_('PLG_EDITORSXTD_PDFVIEWER_INSERT')); ?></button>
        </div>
    </form>
</div>
