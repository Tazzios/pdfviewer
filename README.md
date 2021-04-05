# Pdfviewer
PDFviewer is a joomla content plugin which make it possible to show pdf files in content based on https://github.com/mozilla/pdf.js.
Compared to other existing pdfviewer for joomla this one is focused on integration with jDownloads (which does not have a pdfviewer) and searching.

## Features

- Set pagenumber
- Joomla Highlight smartsearch integration
- Advanced jDownloads integration

## Examples

Demo website: http://marijqg132.132.axc.nl/demopdfviewer/

### Joomla Content Examples

Show jdownloads file with ID 4.  
>{pdfviewer jdownloadsid=4 }

Link to a pdf file  
>{pdfviewer file=https://samedomain.com/file.pdf }  
Pdfjs does not allow to open pdfs from other domains

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

Highlight keywords
>Search="[keyword1 keyword2]" 

double qoutes are only needed with multiple keywords. Each keyword will be highlighted separately.

Parameter for use with jdownloads and a customfield, see advanced section.
>showpdfpreview=[Yes] 

Override default preview style  
>style=[embed|popup|new]

with the embed and popup you can change the size at set the link text
>height=[integer] width=[integer] linktext="[string]"

Use double qoutes around the linktext is it contains a space.

With embed you can use % for width
>width=80%


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

Show preview  
Jdownloads example custom field checkbox {jdfield 3}  
>{pdfviewer jdownloadsid={file\_id} showpdfpreview={jdfield 3} }

Article example custom field checkbox {field 2}   
>{pdfviewer jdownloadsid=4 page={field 2} } 

Search terms  
Jdownloads example custom field text {jdfield 5}  
>{pdfviewer jdownloadsid={file\_id} showpdfpreview={jdfield 5} }

Article example custom field text {field 5}   
>{pdfviewer jdownloadsid=4 search={field 5} } 

**Make pdf 'searchable'**
Create a custom text field (with large pdfs you need multiple)
copy the text from the pdf in the textfield
