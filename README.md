[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate/?business=SAT23GPU7F6AS&no_recurring=1&currency_code=EUR)

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

The Editor button is gone. :(

## Examples

[Demo website](https://famrodenburg.net/demo/)

### basic article examples

Show jdownloads file with ID 4.  
>{pdfviewer jdownloadsid=4 }

Link to a pdf file on other domain (does not work when CORS is active on that domain)
>{pdfviewer file=https://raw.githubusercontent.com/mozilla/pdf.js/master/examples/learning/helloworld.pdf }  

Link to a pdf file relative to domain name 
>{pdfviewer file=/documents/file.pdf }  


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

PDfjs show the page on default with auto size but you can change that with the following parameter
>zoom=[page-width|page-height|page-fit|auto] (default) 

PDfjs show nothing by default on the left side but it can be usefull to show the bookmarks for example
>pagemode=[thumbs|bookmarks|attachments|none] (default) )

Open on specific page
>page=[integer]

Page will be ignore if there is a smartsearch highlight present in the url.  
Use ctrl+f5 to test it, else it will remember the old pagenumber.

Link to a page can also with named destination (not a bookmark) but is untested
>nameddest=[destination name]

Highlight keywords
>search="[keyword1] [keyword2]" 
keyword will be highlighted separately. If you want to search a combination enable phrase.

Enable phrase to search for a combination of words.
>phrase=true 

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
>?search=[keyword1]%20[keyword2]&phrase=true
  
These url parameters do not work with the pdfimage viewer else everone could access every page of the pdf file.  

page and search parameters priority order:  
1 highlight search (joomla smartsearch)
2 url search
3 url namedest
4 url page 
5 param search
6 param namedest
7 param page	 
				
zoom, pagemode and nameddest parameters can also be used. if you add multiple urlparameters think about the syntacs '?' (for the first) en '&' for the others.
>?page=10&pagemode=bookmarks&zoom=page-height
				
  
## Make pdf 'searchable'
Create a custom text field (with large pdfs you maybe need multiple fields) copy the text from the pdf in the textfield you can now search for the text with smart search.

## Override pdfjs

If you want to customize PDFjs you can place an override in the following folder:
>[TEMPLATE]/html/plg_content_pdfviewer/assets/pdfjs
The codes checks if  '[TEMPLATE]/html/plg_content_pdfviewer/assets/pdfjs/web/viewer.html' exist if so it will be used instead of the default pdfjs viewer.

## Development

Run the automated tests (needs PHP 8.1+ and Composer):

>composer update  
>vendor/bin/phpunit

The tests run the content plugin against the real Joomla Framework input filter, with small stand-ins for the Joomla CMS classes (`tests/Stubs/joomla.php`). GitHub Actions runs them, plus a PHP syntax check and a manifest check, on every push and pull request.

### Update PDF.js

The plugin bundles the PDF.js viewer with a few changes (see `PATCHES` in `tools/update-pdfjs.js`): the Open file button and the Current view link are hidden, and PDF files from other domains are allowed. To update PDF.js (needs Node.js 18+):

>node tools/update-pdfjs.js            (newest release)  
>node tools/update-pdfjs.js 6.5.438    (a specific release)  
>node tools/update-pdfjs.js --check    (only show current and newest version)

The script downloads the release, replaces `content_plugin/assets/pdfjs` and applies the changes. If PDF.js changed the code a change depends on, it stops with an error and leaves the old files in place.

The GitHub Actions workflow "Update PDF.js" does the same and opens a pull request with the result. It only runs when you start it by hand from the Actions tab (Actions, Update PDF.js, Run workflow), optionally with a version. For this the repository setting "Allow GitHub Actions to create and approve pull requests" (Settings, Actions, General) must be on.
