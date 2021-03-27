# Pdfviewer
PDFviewer is a joomla content plugin which make it possible to show pdf files in content based on https://github.com/mozilla/pdf.js.
Compared to other existing pdfviewer for joomla this one is focused on integration with jDownloads (which does not have a pdfviewer) and searching.

## Features

- Set pagenumber
- Joomla Highlight smartsearch integration
- Advanced jDownloads integration

## Examples

### Joomla Content Examples

Show jdownloads file with ID 4.  
>{pdfviewer jdownloadsid=4 }

### Jdownloads examples

The following examples are for jdownloads.  
You can use them in &#39;&#39;downloads&#39; and &#39;downloads details&#39; layouts.

**Show pdfviewer**  
>{pdfviewer jdownloadsid={file\_id} }

### Optional parameters

Open on specific page
>page=[integer]  
Page will be ignore if there is a smartsearch highlight present in the url.  
Use ctrl+f5 to test it, else it will remember the old pagenumber.

Highlight key words
>Search=[string]

Additional parameter for use with jdownloads and a customfield, see advanced section.
>showpdfpreview=[Yes] (case sensitive) 

### Advanced 

**Use custom field for page**  
For this advanced example I created a custom integer field {jdfield 4} this will return an integer to represent the page number. You can create this custom field for in article or jdownloads. Note, You can not use the jdownloads custom field for in an article. Set 'Show label' to hide when creating a custom field for article. 

Jdownloads example custom field integer {jdfield 4}  
>{pdfviewer jdownloadsid={file\_id} page={jdfield 4} }

Article example custom field integer {field 2}  
>{pdfviewer jdownloadsid=4 page={field 2} } 

**Use custom field to choose for which file pdfpreview is enabled**  
If you do not want to show every pdf file as preview you can create an custom field in jdownloads which you can use to turn it on or off.
{jdfield 3} if returns &quot;Yes&quot; (case sensitive) then it will show the pdfviewer. You can use a checkbox or dropdown for this.

Jdownloads example custom field checkbox {jdfield 4}  
>{pdfviewer jdownloadsid={file\_id} showpdfpreview={jdfield 3} }

Article example custom field checkbox {field 2}   
>{pdfviewer jdownloadsid=4 page={field 2} } 


