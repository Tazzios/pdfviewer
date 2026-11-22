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

$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$editor   = $displayData['editor'];
$files    = $displayData['jdownloadsFiles'];
$defaults = $displayData['defaults'];
$content  = $displayData['content'];
$settings = json_encode(['button' => $defaults, 'content' => $content], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$hasJd    = is_array($files) && count($files) > 0;
?>
<div class="container-popup">
    <form id="pdfviewer-form" class="pdfviewer-xtd"
          data-editor="<?php echo $e($editor); ?>"
          data-settings="<?php echo $e($settings); ?>">

        <fieldset class="mb-3">
            <legend class="visually-hidden">Source</legend>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="pdfviewer-source-jd" name="source" value="jdownloads"
                    <?php echo $hasJd ? 'checked' : 'disabled'; ?>>
                <label class="form-check-label" for="pdfviewer-source-jd">jDownloads file</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" id="pdfviewer-source-file" name="source" value="file"
                    <?php echo $hasJd ? '' : 'checked'; ?>>
                <label class="form-check-label" for="pdfviewer-source-file">PDF link</label>
            </div>
            <?php if ($files === null) : ?>
                <div class="form-text">jDownloads is not installed.</div>
            <?php elseif (!$hasJd) : ?>
                <div class="form-text">jDownloads has no published files.</div>
            <?php endif; ?>
        </fieldset>

        <div class="row">
            <div class="col-sm-6">
                <?php if ($hasJd) : ?>
                    <div class="mb-3" id="pdfviewer-jd-group">
                        <label class="form-label" for="pdfviewer-jdownloadsid">jDownloads file</label>
                        <select class="form-select" id="pdfviewer-jdownloadsid">
                            <?php foreach ($files as $file) : ?>
                                <option value="<?php echo (int) $file['id']; ?>"><?php echo $e($file['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="mb-3" id="pdfviewer-file-group">
                    <label class="form-label" for="pdfviewer-file">Link to the PDF</label>
                    <input class="form-control" type="text" id="pdfviewer-file" placeholder="/images/file.pdf or https://...">
                    <div class="form-text">Full link (https://) or a link relative to the site (/images/...).</div>
                </div>

                <div class="mb-3" id="pdfviewer-viewer-group">
                    <label class="form-label" for="pdfviewer-viewer">Viewer</label>
                    <select class="form-select" id="pdfviewer-viewer">
                        <option value="default" selected>default (<?php echo $e($defaults['viewer']); ?>)</option>
                        <option value="pdfjs">pdfjs</option>
                        <option value="pdfimage">pdfimage</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="pdfviewer-style">Display style</label>
                    <select class="form-select" id="pdfviewer-style">
                        <option value="default" selected>default (<?php echo $e($defaults['style']); ?>)</option>
                        <option value="embed">embed</option>
                        <option value="popup">popup</option>
                        <option value="new">new window</option>
                    </select>
                </div>

                <div class="row mb-3" id="pdfviewer-size-group">
                    <div class="col">
                        <label class="form-label" for="pdfviewer-width">Width</label>
                        <input class="form-control" type="text" id="pdfviewer-width" placeholder="100% or 800">
                    </div>
                    <div class="col">
                        <label class="form-label" for="pdfviewer-height">Height</label>
                        <input class="form-control" type="text" id="pdfviewer-height" placeholder="800">
                    </div>
                </div>

                <div class="mb-3" id="pdfviewer-linktext-group">
                    <label class="form-label" for="pdfviewer-linktext">Link text</label>
                    <input class="form-control" type="text" id="pdfviewer-linktext">
                </div>
            </div>

            <div class="col-sm-6">
                <div class="mb-3" id="pdfviewer-search-group">
                    <label class="form-label" for="pdfviewer-search">Search</label>
                    <input class="form-control" type="text" id="pdfviewer-search">
                    <div class="form-check mt-1" id="pdfviewer-phrase-group">
                        <input class="form-check-input" type="checkbox" id="pdfviewer-phrase">
                        <label class="form-check-label" for="pdfviewer-phrase">Search as one phrase</label>
                    </div>
                </div>

                <div class="mb-3" id="pdfviewer-page-group">
                    <label class="form-label" for="pdfviewer-page">Page number</label>
                    <input class="form-control" type="number" id="pdfviewer-page" min="1">
                </div>

                <div id="pdfviewer-pdfjs-group">
                    <div class="mb-3">
                        <label class="form-label" for="pdfviewer-zoom">Page zoom</label>
                        <select class="form-select" id="pdfviewer-zoom">
                            <option value="default" selected>default (auto)</option>
                            <option value="auto">auto</option>
                            <option value="page-width">fit width</option>
                            <option value="page-height">fit height</option>
                            <option value="page-fit">fit page</option>
                            <option value="page-actual">actual size</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pdfviewer-pagemode">Sidebar</label>
                        <select class="form-select" id="pdfviewer-pagemode">
                            <option value="default" selected>default (none)</option>
                            <option value="none">none</option>
                            <option value="bookmarks">bookmarks</option>
                            <option value="thumbs">thumbnails</option>
                            <option value="attachments">attachments</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <p class="small text-muted mb-2">Tag: <code id="pdfviewer-preview"></code></p>

        <button type="submit" class="btn btn-success">Insert</button>
    </form>
</div>
