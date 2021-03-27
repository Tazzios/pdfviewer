# Pdfviewer

## Features

- Set pagenumber to show
- Joomla Highlight smartsearch integration
- Jdownloads integration

## Examples

### Content Examples

Show jdownloads file 4 open on page 10.
 {pdfviewer jdownloadsid=4 page=10}
 Page will be ignore if there is a smartsearch highlight present in the url.

### Jdownloads examples

The following examples are for jdownloads. You can use them in &#39;&#39;downloads&#39; and &#39;downloads details&#39; layouts.

**Show pdf preview**
 {pdfviewer jdownloadsid={file\_id} }

**Open the pdfviewer on specific page and if it is allowed for this file.**

For this advanced example I created 2 custom fields:
 {jdfield 4} will return an integer to represent the page number.
 {jdfield 3} if returns &quot;Yes&quot; (case sensitive) then it will show the preview. You can create an checkbox or dropdown for this.

{pdfviewer jdownloadsid={file\_id} page={jdfield 4} showpdfpreview={jdfield 3} }


