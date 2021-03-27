# Pdfviewer
PFFviewer is a joomla content plugin which make it possible to show pdf files in content.

## Features

- Set pagenumber
- Joomla Highlight smartsearch integration
- Advanced Jdownloads integration

## Examples

### Joomla Content Examples

Show jdownloads file with ID 4 open on page 10.  
>{pdfviewer jdownloadsid=4 page=10}

Page will be ignore if there is a smartsearch highlight present in the url.  
Use ctrl+f5 to test it, else it will remember the old pagenumber.

### Jdownloads examples

The following examples are for jdownloads.  
You can use them in &#39;&#39;downloads&#39; and &#39;downloads details&#39; layouts.

**Show pdf preview**  
>{pdfviewer jdownloadsid={file\_id} }

### Optional parameters

Open on specific page
>page=[integer]

Highlight key words
>Search=[string]

Additional parameter for use with jdownloads and a customfield, see advanced section.
>showpdfpreview=[Yes] (case sensitive) 

### Advanced 

**Use custom field for page**
For this advanced example I created a custom integer field {jdfield 4} this will return an integer to represent the page number. You can create this custom field for in article or jdownloads. 

Open the pdfviewer on specific page. 
>{pdfviewer jdownloadsid={file\_id} page={jdfield 4} }

**Use custom field to those for which file pdfpreview is enabled**
If you do not want to show every pdf file as preview you can create an custom field in jdownloads which you can use to run it on or off.
{jdfield 3} if returns &quot;Yes&quot; (case sensitive) then it will show the preview. default Yes
You can create an checkbox or dropdown for this. 
>{pdfviewer jdownloadsid={file\_id} showpdfpreview={jdfield 3} }



