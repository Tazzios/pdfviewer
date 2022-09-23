# Pdfviewer
PDFviewer is a joomla content plugin which make it possible to show pdf files in content based on https://github.com/mozilla/pdf.js.
Compared to other existing pdfviewer for joomla this one is focused on integration with jDownloads and searching.

## Features

- Uses the pdf.js webviewer
- Set pagenumber to open by tag or url.
- Joomla Highlight smartsearch integration
- Set search by url.
- Show the PDF file as Embedded, popup or in a new window
- Customize height and width for each PDF embedding.
- Advanced jDownloads integration
- Show one page as an image
- Editor button


## Examples

Demo website: http://marijqg132.132.axc.nl/demopdfviewer/

### basic article examples

Show jdownloads file with ID 4.  
>{pdfviewer jdownloadsid=4 }

Link to a pdf file  
>{pdfviewer file=https://domain.com/file.pdf }  

### Basic Jdownloads example

The following examples are for jdownloads.  
You can use them in &#39;downloads&#39; and &#39;downloads details&#39; layouts.

>{pdfviewer jdownloadsid={file\_id} filename="{file\_name}" }
    
Filename is needed to check if the file is a pdf file.

### Jdownloads custom field examples

**Page**  
For this advanced example I created a custom integer field {jdfield 4} this will return an integer to represent the page number. 

Jdownloads example custom field integer {jdfield 4}  
>{pdfviewer jdownloadsid={file\_id} filename="{file\_name}" page={jdfield 4} }

Article example custom field integer {field 2}  
>{pdfviewer jdownloadsid=4 page={field 2} } 

**Use custom field to choose for which file pdfpreview is enabled**  
If you do not want to show every pdf file as preview you can create an custom field in jdownloads which you can use to turn it on or off.
{jdfield 3} if returns &quot;Yes&quot; then it will show the pdfviewer. You can use a checkbox or dropdown for this.

**Show preview**
Jdownloads example custom field checkbox {jdfield }  
>{pdfviewer jdownloadsid={file\_id} filename="{file\_name}" showpdfpreview={jdfield 3} }

**Search terms**  
Jdownloads example custom field text {jdfield 5}  
>{pdfviewer jdownloadsid={file\_id} filename="{file\_name}" search={jdfield 5} }

You also can use custom fields from articles in a article. Set 'Show label' to hide when creating a custom field for article. For example highlight keywords:
>{pdfviewer jdownloadsid=[ID] search={field 5} }

You can create this custom field for in article or jdownloads. Note, You can not use the jdownloads custom field for in an article. Set 'Show label' to hide when creating a custom field for article. 

### Optional tagparameters

Select viewer. Show the full pdf or only one page as an image.
>viewer=[pdfjs|pdfimage]

Only jdownloads pdf files can be shown as image. When set to pdfimage by default the first page will beshown set page= to show an other page. Warning; Images will be created with imagick each time the page is loaded. 

Open on specific page
>page=[integer]

Page will be ignore if there is a smartsearch highlight present in the url.  
Use ctrl+f5 to test it, else it will remember the old pagenumber.

Highlight keywords
>Search="[keyword1] [keyword2]" 

double qoutes are only needed with multiple keywords. Each keyword will be highlighted separately.

Override default preview style  
>style=[embed|popup|new]

with the embed and popup you can change the size at set the link text
>height=[integer] width=[integer] linktext="[string]"

Use double qoutes around the linktext if it contains a space.

With embed you can also use % for width
>width=80%
  
Parameter for use with jdownloads and a customfield, see advanced section.  
>showpdfpreview=[Yes]  
  
### Optional urlparameters  
  
If you want to link to a webpage with a pdffile embedded you can set following parameters to open a specific page.  
  
>?page=[integer]  
  
>?search=[keyword1]%20[keyword2]  
  
These url parameters do not work with the pdfimage viewer else everone could access every page of the pdf file.  

page and search parameters priority order:  
1 highlight search  
2 url search  
3 url page  
4 param search  
5 param page  
				
  
## Make pdf 'searchable'
Create a custom text field (with large pdfs you maybe need multiple fields) copy the text from the pdf in the textfield you can now search for the text with smart search.

## Override pdfjs

If you want to customize PDFjs you can place an override in the following folder:
>[TEMPLATE]/html/plg_content_pdfviewer/assets/pdfjs
The codes checks if  '[TEMPLATE]/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html' exist if so it will be used instead of the default pdfjs viewer.
